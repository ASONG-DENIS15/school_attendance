<?php
session_start();

// Database connection settings for XAMPP
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'school_attendance_system');

function redirect_to($url)
{
    header('Location: ' . $url);
    exit;
}

function is_logged_in()
{
    return !empty($_SESSION['user']);
}

function current_user()
{
    return $_SESSION['user'] ?? null;
}

function require_login()
{
    if (!is_logged_in()) {
        redirect_to('login.php');
    }
}

function require_role($roles)
{
    require_login();
    if (is_string($roles)) {
        $roles = [$roles];
    }
    $user = current_user();
    if (!in_array($user['role'], $roles, true)) {
        redirect_to('login.php');
    }
}

function sanitize($value)
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}
