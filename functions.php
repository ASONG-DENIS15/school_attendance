<?php
require_once 'config.php';

function db_connect()
{
    $mysqli = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    if ($mysqli->connect_errno) {
        die('Database connection failed: ' . $mysqli->connect_error);
    }
    $mysqli->set_charset('utf8mb4');
    ensure_user_fingerprint_columns($mysqli);
    return $mysqli;
}

function ensure_user_fingerprint_columns($conn)
{
    $result = $conn->query("SHOW COLUMNS FROM users LIKE 'fingerprint_credential_id'");
    if ($result && $result->num_rows === 0) {
        $conn->query(
            "ALTER TABLE users ADD COLUMN fingerprint_credential_id VARCHAR(255) NULL, " .
            "ADD COLUMN fingerprint_public_key_jwk TEXT NULL, " .
            "ADD COLUMN fingerprint_sign_count INT UNSIGNED NOT NULL DEFAULT 0, " .
            "ADD COLUMN fingerprint_registered_at DATETIME NULL"
        );
    }
    if ($result) {
        $result->free();
    }
}

function base64url_encode($data)
{
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

function base64url_decode($data)
{
    $remainder = strlen($data) % 4;
    if ($remainder) {
        $data .= str_repeat('=', 4 - $remainder);
    }
    return base64_decode(strtr($data, '-_', '+/'));
}

function get_origin()
{
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    return $scheme . '://' . $_SERVER['HTTP_HOST'];
}

function asn1_length($length)
{
    if ($length < 128) {
        return chr($length);
    }

    $temp = '';
    while ($length > 0) {
        $temp = chr($length & 0xff) . $temp;
        $length >>= 8;
    }

    return chr(0x80 | strlen($temp)) . $temp;
}

function jwk_to_pem($jwk)
{
    if (empty($jwk['kty']) || $jwk['kty'] !== 'EC' || empty($jwk['crv']) || $jwk['crv'] !== 'P-256') {
        return false;
    }

    $x = base64url_decode($jwk['x'] ?? '');
    $y = base64url_decode($jwk['y'] ?? '');
    if ($x === false || $y === false) {
        return false;
    }

    $uncompressedPoint = "\x04" . $x . $y;
    $oidEcPublicKey = "\x06\x07\x2A\x86\x48\xCE\x3D\x02\x01";
    $oidPrime256v1 = "\x06\x08\x2A\x86\x48\xCE\x3D\x03\x01\x07";
    $algo = "\x30" . asn1_length(strlen($oidEcPublicKey . $oidPrime256v1)) . $oidEcPublicKey . $oidPrime256v1;
    $pubKeyBitString = "\x03" . asn1_length(1 + strlen($uncompressedPoint)) . "\x00" . $uncompressedPoint;
    $spki = "\x30" . asn1_length(strlen($algo . $pubKeyBitString)) . $algo . $pubKeyBitString;

    return "-----BEGIN PUBLIC KEY-----\n" . chunk_split(base64_encode($spki), 64, "\n") . "-----END PUBLIC KEY-----\n";
}

function verify_webauthn_signature($jwk, $data, $signature)
{
    $pem = jwk_to_pem($jwk);
    if (!$pem) {
        return false;
    }

    $result = openssl_verify($data, $signature, $pem, OPENSSL_ALGO_SHA256);
    return $result === 1;
}

function parse_authenticator_data($authData)
{
    if (strlen($authData) < 37) {
        return null;
    }

    $flags = ord($authData[32]);
    $counterData = substr($authData, 33, 4);
    $counter = unpack('N', $counterData)[1];

    return [
        'flags' => $flags,
        'signCount' => $counter,
    ];
}

function is_user_present_flag($flags)
{
    return ($flags & 0x01) !== 0;
}

function hash_sha256($data)
{
    return hash('sha256', $data, true);
}

function db_query($conn, $sql, $params = [])
{
    $stmt = $conn->prepare($sql);
    if ($stmt === false) {
        die('Query prepare failed: ' . $conn->error);
    }

    if ($params) {
        $types = '';
        foreach ($params as $param) {
            if (is_int($param)) {
                $types .= 'i';
            } elseif (is_float($param)) {
                $types .= 'd';
            } else {
                $types .= 's';
            }
        }

        $bindNames = [];
        $bindNames[] = $types;
        foreach ($params as $key => $value) {
            $bindNames[] = &$params[$key];
        }
        call_user_func_array([$stmt, 'bind_param'], $bindNames);
    }

    if (!$stmt->execute()) {
        die('Query execute failed: ' . $stmt->error);
    }

    return $stmt;
}

function db_fetch_all($conn, $sql, $params = [])
{
    $stmt = db_query($conn, $sql, $params);
    $result = $stmt->get_result();
    return $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
}

function db_fetch_one($conn, $sql, $params = [])
{
    $stmt = db_query($conn, $sql, $params);
    $result = $stmt->get_result();
    return $result ? $result->fetch_assoc() : null;
}

function hash_password($password)
{
    return password_hash($password, PASSWORD_DEFAULT);
}

function verify_password($password, $hash)
{
    return password_verify($password, $hash);
}

function log_action($conn, $action, $userName)
{
    db_query($conn, 'INSERT INTO system_logs (action, user_name) VALUES (?, ?)', [$action, $userName]);
}

function old($key)
{
    return $_POST[$key] ?? '';
}
