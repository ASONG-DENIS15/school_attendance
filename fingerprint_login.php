<?php
ob_start();
require_once 'functions.php';

function respond_json($payload, $status = 200)
{
    if (ob_get_length()) {
        ob_clean();
    }
    http_response_code($status);
    header('Content-Type: application/json');
    echo json_encode($payload, JSON_UNESCAPED_SLASHES);
    exit;
}

set_error_handler(function ($severity, $message, $file, $line) {
    respond_json(['error' => "Server error: {$message}"], 500);
});
set_exception_handler(function ($exception) {
    respond_json(['error' => 'Server exception: ' . $exception->getMessage()], 500);
});
register_shutdown_function(function () {
    $error = error_get_last();
    if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        respond_json(['error' => 'Fatal server error occurred.'], 500);
    }
});

if ($_SERVER['REQUEST_METHOD'] === 'GET' && ($_GET['action'] ?? '') === 'options') {
    $email = trim($_GET['email'] ?? '');
    if ($email === '') {
        respond_json(['error' => 'Email is required to start fingerprint login.'], 400);
    }

    $conn = db_connect();
    $user = db_fetch_one($conn, 'SELECT id, email, name, fingerprint_credential_id FROM users WHERE email = ? AND role = ? AND fingerprint_credential_id IS NOT NULL', [$email, 'lecturer']);
    if (!$user) {
        echo json_encode(['error' => 'No lecturer account with a registered fingerprint was found for that email.']);
        exit;
    }

    $challenge = base64url_encode(random_bytes(32));
    $_SESSION['fingerprint_login_challenge'] = $challenge;
    $_SESSION['fingerprint_login_email'] = $email;

    $publicKey = [
        'challenge' => $challenge,
        'rpId' => $_SERVER['SERVER_NAME'],
        'allowCredentials' => [
            [
                'type' => 'public-key',
                'id' => $user['fingerprint_credential_id'],
            ],
        ],
        'userVerification' => 'required',
        'timeout' => 60000,
    ];

    respond_json(['publicKey' => $publicKey]);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_GET['action'] ?? '') === 'authenticate') {
    header('Content-Type: application/json');
    $body = json_decode(file_get_contents('php://input'), true);
    $credentialId = $body['credentialId'] ?? '';
    $clientDataJSON = base64url_decode($body['clientDataJSON'] ?? '');
    $authenticatorData = base64url_decode($body['authenticatorData'] ?? '');
    $signature = base64url_decode($body['signature'] ?? '');

    $challenge = $_SESSION['fingerprint_login_challenge'] ?? '';
    $email = $_SESSION['fingerprint_login_email'] ?? '';

    if (!$challenge || !$email || !$credentialId) {
        respond_json(['error' => 'Missing fingerprint login context.'], 400);
    }

    $conn = db_connect();
    $user = db_fetch_one($conn, 'SELECT id, name, email, role, fingerprint_public_key_jwk, fingerprint_sign_count FROM users WHERE fingerprint_credential_id = ? AND role = ?', [$credentialId, 'lecturer']);
    if (!$user) {
        respond_json(['error' => 'Fingerprint credential not recognized.'], 401);
    }

    $clientData = json_decode($clientDataJSON, true);
    if (!$clientData || ($clientData['type'] ?? '') !== 'webauthn.get' || ($clientData['challenge'] ?? '') !== $challenge) {
        respond_json(['error' => 'Invalid fingerprint response from the authenticator.'], 400);
    }

    if (($clientData['origin'] ?? '') !== get_origin()) {
        respond_json(['error' => 'Unexpected origin in fingerprint data.'], 400);
    }

    $authData = parse_authenticator_data($authenticatorData);
    if (!$authData || !is_user_present_flag($authData['flags'])) {
        echo json_encode(['error' => 'User presence was not verified by the fingerprint authenticator.']);
        exit;
    }

    $publicKeyJwk = json_decode($user['fingerprint_public_key_jwk'], true);
    if (!$publicKeyJwk || !verify_webauthn_signature($publicKeyJwk, $authenticatorData . hash_sha256($clientDataJSON), $signature)) {
        respond_json(['error' => 'Fingerprint verification failed.'], 401);
    }

    if ($authData['signCount'] > intval($user['fingerprint_sign_count'])) {
        db_query($conn, 'UPDATE users SET fingerprint_sign_count = ? WHERE id = ?', [$authData['signCount'], $user['id']]);
    }

    $_SESSION['user'] = [
        'id' => $user['id'],
        'name' => $user['name'],
        'email' => $user['email'],
        'role' => $user['role'],
    ];

    log_action($conn, 'Lecturer logged in via fingerprint', $user['name']);
    respond_json(['success' => true, 'redirect' => 'lecturer.php']);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Lecturer Fingerprint Login</title>
  <link rel="stylesheet" href="styles.css">
</head>
<body>
  <div class="form-container">
    <h1>Lecturer Fingerprint Login</h1>
    <p>Enter your lecturer email and use the fingerprint sensor on your device.</p>
    <label for="lecturer_email">Lecturer Email</label>
    <input type="email" id="lecturer_email" placeholder="Enter your lecturer email" required>
    <button class="btn btn-primary" id="fingerprintLoginBtn">Use Fingerprint</button>
    <div id="message" class="alert-box" style="margin-top: 1rem;"></div>
    <div style="margin-top: 1rem;">
      <a href="login.php">Back to standard login</a>
    </div>
  </div>

  <script>
    function base64urlToBuffer(base64url) {
      const padding = '='.repeat((4 - base64url.length % 4) % 4);
      const base64 = (base64url + padding).replace(/-/g, '+').replace(/_/g, '/');
      const raw = atob(base64);
      const buffer = new Uint8Array(raw.length);
      for (let i = 0; i < raw.length; ++i) {
        buffer[i] = raw.charCodeAt(i);
      }
      return buffer;
    }

    function bufferToBase64url(buffer) {
      let binary = '';
      const bytes = new Uint8Array(buffer);
      for (let i = 0; i < bytes.byteLength; i++) {
        binary += String.fromCharCode(bytes[i]);
      }
      return btoa(binary).replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/, '');
    }

    function isWebAuthnSupported() {
      return window.PublicKeyCredential && navigator.credentials && typeof navigator.credentials.get === 'function';
    }

    function setCompatibilityState() {
      const messageEl = document.getElementById('message');
      const loginBtn = document.getElementById('fingerprintLoginBtn');
      if (!isWebAuthnSupported()) {
        loginBtn.disabled = true;
        messageEl.textContent = 'Fingerprint login is not supported in this browser or device. Use a supported browser with Web Authentication enabled.';
        messageEl.className = 'error-box';
      } else if (window.location.protocol !== 'https:' && window.location.hostname !== 'localhost') {
        loginBtn.disabled = true;
        messageEl.textContent = 'Fingerprint login requires a secure connection (HTTPS) or localhost. Please access this page over HTTPS or use localhost.';
        messageEl.className = 'error-box';
      }
    }

    async function loginWithFingerprint() {
      const messageEl = document.getElementById('message');
      messageEl.textContent = '';
      const email = document.getElementById('lecturer_email').value.trim();
      if (!email) {
        messageEl.textContent = 'Please enter your lecturer email.';
        messageEl.className = 'error-box';
        return;
      }

      try {
        const optionsResponse = await fetch('fingerprint_login.php?action=options&email=' + encodeURIComponent(email));
        const optionsText = await optionsResponse.text();
        let optionsData;
        try {
          optionsData = JSON.parse(optionsText);
        } catch (e) {
          throw new Error('Server returned invalid JSON during options request: ' + optionsText);
        }
        if (optionsData.error) {
          messageEl.textContent = optionsData.error;
          messageEl.className = 'error-box';
          return;
        }

        const publicKey = optionsData.publicKey;
        publicKey.challenge = base64urlToBuffer(publicKey.challenge);
        publicKey.allowCredentials = publicKey.allowCredentials.map(cred => ({
          type: cred.type,
          id: base64urlToBuffer(cred.id),
        }));

        const assertion = await navigator.credentials.get({ publicKey });
        const payload = {
          credentialId: bufferToBase64url(assertion.rawId),
          clientDataJSON: bufferToBase64url(assertion.response.clientDataJSON),
          authenticatorData: bufferToBase64url(assertion.response.authenticatorData),
          signature: bufferToBase64url(assertion.response.signature),
        };

        const authResponse = await fetch('fingerprint_login.php?action=authenticate', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify(payload),
        });
        const authText = await authResponse.text();
        let authData;
        try {
          authData = JSON.parse(authText);
        } catch (e) {
          throw new Error('Server returned invalid JSON during authenticate request: ' + authText);
        }

        if (authData.error) {
          messageEl.textContent = authData.error;
          messageEl.className = 'error-box';
          return;
        }

        window.location.href = authData.redirect || 'lecturer.php';
      } catch (error) {
        messageEl.textContent = 'Fingerprint login failed: ' + error.message;
        messageEl.className = 'error-box';
      }
    }

    document.getElementById('fingerprintLoginBtn').addEventListener('click', loginWithFingerprint);
    setCompatibilityState();
  </script>
</body>
</html>
