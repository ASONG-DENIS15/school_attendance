<?php
require_once 'functions.php';

$errors = [];
$email = '';

// Simple rate limiting settings
$MAX_ATTEMPTS = 5;
$LOCKOUT_SECONDS = 15 * 60; // 15 minutes

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($email === '' || $password === '') {
        $errors[] = 'Please fill in all fields.';
    } else {
        if (!isset($_SESSION['admin_login_attempts'])) {
            $_SESSION['admin_login_attempts'] = ['count' => 0, 'last' => 0];
        }

        $attempts = &$_SESSION['admin_login_attempts'];

        if ($attempts['count'] >= $MAX_ATTEMPTS && (time() - $attempts['last']) < $LOCKOUT_SECONDS) {
            $errors[] = 'Too many failed attempts. Please try again later.';
        } else {
            $conn = db_connect();

            // only allow admin or systemadmin roles here
            $user = db_fetch_one($conn, 'SELECT id, name, email, password, role FROM users WHERE email = ? AND role IN (?, ?)', [$email, 'admin', 'systemadmin']);

            if (!$user || !verify_password($password, $user['password'])) {
                $attempts['count'] = ($attempts['count'] ?? 0) + 1;
                $attempts['last'] = time();
                $errors[] = 'Invalid email, password, or you do not have admin access.';
                log_action($conn, 'Failed admin login attempt for ' . $email, $email);
            } else {
                // success: reset attempts and set session
                $attempts = ['count' => 0, 'last' => 0];
                $_SESSION['user'] = [
                    'id' => $user['id'],
                    'name' => $user['name'],
                    'email' => $user['email'],
                    'role' => $user['role'],
                ];

                log_action($conn, 'Admin logged in', $user['name']);
                redirect_to('admin.php');
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Admin Login</title>
  <link rel="stylesheet" href="styles.css">
</head>
<body>
  <div class="form-container">
    <h1>Admin Sign In</h1>
    <?php if ($errors): ?>
      <div class="error-box"><?php echo sanitize(implode('<br>', $errors)); ?></div>
    <?php endif; ?>

    <form method="post" action="admin_login.php">
      <label for="email">Email</label>
      <input type="email" id="email" name="email" value="<?php echo sanitize($email); ?>" required>

      <label for="password">Password</label>
      <input type="password" id="password" name="password" required>

      <button class="btn btn-primary" type="submit">Sign in as Admin</button>
    </form>

    <div class="signup-link">
      Use the <a class="nav-link" href="login.php">regular login</a> to sign in with other roles.
    </div>
  </div>
</body>
</html>
