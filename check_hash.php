<?php
require_once 'config.php';
$m = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($m->connect_errno) {
    echo "DB connect failed: " . $m->connect_error . "\n";
    exit(1);
}
// List all users for debugging
$res = $m->query("SELECT id, name, email FROM users");
while ($r = $res->fetch_assoc()) {
    echo "{$r['id']}: {$r['name']} <{$r['email']}>\n";
}

// Check admin@example.com specifically
$res2 = $m->query("SELECT password FROM users WHERE email='admin@example.com'");
if ($res2 && ($row = $res2->fetch_assoc())) {
    echo password_verify('admin123', $row['password']) ? "MATCH\n" : "NO\n";
} else {
    echo "admin@example.com not found\n";
}
