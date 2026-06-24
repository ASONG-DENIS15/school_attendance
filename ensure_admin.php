<?php
require_once 'functions.php';

// Local-only helper to ensure an admin user exists for testing
function abort_if_not_local()
{
    if (php_sapi_name() === 'cli') return;
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    if (!in_array($ip, ['127.0.0.1', '::1', 'localhost'], true)) {
        http_response_code(403);
        echo "Forbidden\n";
        exit;
    }
}

abort_if_not_local();

$email = 'admin@example.com';
$password = 'admin123';
$conn = db_connect();
$existing = db_fetch_one($conn, 'SELECT id FROM users WHERE email = ?', [$email]);
if ($existing) {
    echo "Admin already exists (id={$existing['id']}).\n";
    exit;
}

$hashed = hash_password($password);
db_query($conn, 'INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, ?)', ['Admin User', $email, $hashed, 'admin']);
echo "Admin user created with email {$email} and password {$password}\n";
