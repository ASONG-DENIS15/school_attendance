<?php
ob_start();
require_once 'functions.php';
require_role('lecturer');

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

$conn = db_connect();
$user = db_fetch_one($conn, 'SELECT id, name, email, fingerprint_credential_id, fingerprint_registered_at FROM users WHERE id = ?', [current_user()['id']]);

if ($_SERVER['REQUEST_METHOD'] === 'GET' && ($_GET['action'] ?? '') === 'options') {
    $challenge = base64url_encode(random_bytes(32));
    $_SESSION['fingerprint_register_challenge'] = $challenge;

    $publicKey = [
        'challenge' => $challenge,
        'rp' => [
            'name' => 'Class Attendance System',
            'id' => $_SERVER['SERVER_NAME'],
        ],
        'user' => [
            'id' => base64url_encode((string) $user['id']),
            'name' => $user['email'],
            'displayName' => $user['name'],
        ],
        'pubKeyCredParams' => [
            ['type' => 'public-key', 'alg' => -7],
        ],
        'attestation' => 'direct',
        'authenticatorSelection' => [
            'authenticatorAttachment' => 'platform',
            'userVerification' => 'required',
        ],
        'timeout' => 60000,
    ];

    respond_json(['publicKey' => $publicKey]);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_GET['action'] ?? '') === 'register') {
    $body = json_decode(file_get_contents('php://input'), true);
    $challenge = $_SESSION['fingerprint_register_challenge'] ?? '';
    $clientDataJSON = base64url_decode($body['clientDataJSON'] ?? '');
    $clientData = json_decode($clientDataJSON, true);

    if (!$clientData || ($clientData['challenge'] ?? '') !== $challenge || ($clientData['type'] ?? '') !== 'webauthn.create') {
        respond_json(['error' => 'Invalid registration response.'], 400);
    }

    $credentialId = $body['credentialId'] ?? '';
    $publicKeyJwk = $body['publicKeyJwk'] ?? null;
    $signCount = intval($body['signCount'] ?? 0);

    if (!$credentialId || !is_array($publicKeyJwk)) {
        respond_json(['error' => 'Missing fingerprint registration data.'], 400);
    }

    db_query(
        $conn,
        'UPDATE users SET fingerprint_credential_id = ?, fingerprint_public_key_jwk = ?, fingerprint_sign_count = ?, fingerprint_registered_at = NOW() WHERE id = ?',
        [$credentialId, json_encode($publicKeyJwk), $signCount, $user['id']]
    );

    respond_json(['success' => 'Fingerprint registered successfully.']);
}

$fingerprintRegistered = !empty($user['fingerprint_credential_id']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Register Lecturer Fingerprint</title>
  <link rel="stylesheet" href="styles.css">
</head>
<body>
  <div class="form-container">
    <h1>Register Lecturer Fingerprint</h1>
    <?php if ($fingerprintRegistered): ?>
      <div class="success-box">
        Fingerprint is already registered. Last registered at <?php echo sanitize($user['fingerprint_registered_at']); ?>.
      </div>
    <?php endif; ?>
    <p>Use your device fingerprint sensor to enroll as a lecturer for faster login.</p>
    <button class="btn btn-primary" id="registerBtn">Register Fingerprint</button>
    <div id="message" class="alert-box" style="margin-top: 1rem;"></div>
    <div style="margin-top: 1rem;">
      <a href="lecturer.php">Back to dashboard</a>
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
      return window.PublicKeyCredential && navigator.credentials && typeof navigator.credentials.create === 'function';
    }

    function setCompatibilityState() {
      const messageEl = document.getElementById('message');
      const registerBtn = document.getElementById('registerBtn');
      if (!isWebAuthnSupported()) {
        registerBtn.disabled = true;
        messageEl.textContent = 'Fingerprint registration is not supported in this browser or device. Use a supported browser with Web Authentication enabled.';
        messageEl.className = 'error-box';
      } else if (window.location.protocol !== 'https:' && window.location.hostname !== 'localhost') {
        registerBtn.disabled = true;
        messageEl.textContent = 'Fingerprint registration requires a secure connection (HTTPS) or localhost. Please access this page over HTTPS or use localhost.';
        messageEl.className = 'error-box';
      }
    }

    function parseCBOR(data) {
      let pos = 0;
      const decoder = new TextDecoder('utf-8');

      function readLength(ai) {
        if (ai < 24) {
          return ai;
        }
        if (ai === 24) {
          return data[pos++];
        }
        if (ai === 25) {
          const value = (data[pos] << 8) | data[pos + 1];
          pos += 2;
          return value;
        }
        if (ai === 26) {
          const value = (data[pos] << 24) | (data[pos + 1] << 16) | (data[pos + 2] << 8) | data[pos + 3];
          pos += 4;
          return value;
        }
        throw new Error('Unsupported CBOR length');
      }

      function decodeItem() {
        const initial = data[pos++];
        const majorType = initial >> 5;
        const additionalInfo = initial & 0x1f;

        if (majorType === 6) {
          readLength(additionalInfo);
          return decodeItem();
        }

        if (majorType === 0) {
          return readLength(additionalInfo);
        }
        if (majorType === 1) {
          return -1 - readLength(additionalInfo);
        }
        if (majorType === 2) {
          const length = readLength(additionalInfo);
          const value = data.slice(pos, pos + length);
          pos += length;
          return value;
        }
        if (majorType === 3) {
          const length = readLength(additionalInfo);
          const bytes = data.slice(pos, pos + length);
          pos += length;
          return decoder.decode(bytes);
        }
        if (majorType === 4) {
          const length = readLength(additionalInfo);
          const array = [];
          for (let i = 0; i < length; i++) {
            array.push(decodeItem());
          }
          return array;
        }
        if (majorType === 5) {
          const length = readLength(additionalInfo);
          const map = {};
          for (let i = 0; i < length; i++) {
            const key = decodeItem();
            const value = decodeItem();
            map[key] = value;
          }
          return map;
        }
        throw new Error('Unsupported CBOR type: ' + majorType);
      }

      return decodeItem();
    }

    function decodeAttestationObject(attestationObject) {
      const decoded = parseCBOR(attestationObject);
      return {
        authData: decoded.authData,
        fmt: decoded.fmt,
        attStmt: decoded.attStmt,
      };
    }

    function parseAuthData(authData) {
      const dataView = new DataView(authData.buffer, authData.byteOffset, authData.byteLength);
      const rpIdHash = authData.slice(0, 32);
      const flags = authData[32];
      const signCount = dataView.getUint32(33, false);
      const aaguid = authData.slice(37, 53);
      const credentialIdLength = (authData[53] << 8) | authData[54];
      const credentialId = authData.slice(55, 55 + credentialIdLength);
      const cosePublicKey = authData.slice(55 + credentialIdLength);
      const coseKey = parseCBOR(cosePublicKey);
      return { signCount, credentialId, coseKey, flags };
    }

    function coseKeyToJwk(coseKey) {
      const x = bufferToBase64url(coseKey[-2]);
      const y = bufferToBase64url(coseKey[-3]);
      return {
        kty: 'EC',
        crv: 'P-256',
        x,
        y,
      };
    }

    async function registerFingerprint() {
      const messageEl = document.getElementById('message');
      messageEl.textContent = '';

      try {
        const response = await fetch('fingerprint_register.php?action=options');
        const responseText = await response.text();
        let result;
        try {
          result = JSON.parse(responseText);
        } catch (e) {
          throw new Error('Server returned invalid JSON during registration options request: ' + responseText);
        }
        const publicKey = result.publicKey;

        publicKey.challenge = base64urlToBuffer(publicKey.challenge);
        publicKey.user.id = base64urlToBuffer(publicKey.user.id);

        const credential = await navigator.credentials.create({ publicKey });
        const attestationBuffer = new Uint8Array(credential.response.attestationObject);
        const attestation = decodeAttestationObject(attestationBuffer);
        const authData = parseAuthData(attestation.authData);

        const payload = {
          credentialId: bufferToBase64url(credential.rawId),
          clientDataJSON: bufferToBase64url(credential.response.clientDataJSON),
          publicKeyJwk: coseKeyToJwk(authData.coseKey),
          signCount: authData.signCount,
        };

        const registerResponse = await fetch('fingerprint_register.php?action=register', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify(payload),
        });
        const registerText = await registerResponse.text();
        let registerResult;
        try {
          registerResult = JSON.parse(registerText);
        } catch (e) {
          throw new Error('Server returned invalid JSON during registration request: ' + registerText);
        }

        if (registerResult.error) {
          messageEl.textContent = registerResult.error;
          messageEl.className = 'error-box';
        } else {
          messageEl.textContent = registerResult.success;
          messageEl.className = 'success-box';
        }
      } catch (error) {
        messageEl.textContent = 'Fingerprint registration failed: ' + error.message;
        messageEl.className = 'error-box';
      }
    }

    document.getElementById('registerBtn').addEventListener('click', registerFingerprint);
    setCompatibilityState();
  </script>
</body>
</html>
