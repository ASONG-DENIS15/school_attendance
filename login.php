<?php
require_once 'functions.php';

$errors = [];
$email = '';
$role = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $role = $_POST['role'] ?? '';

    if ($email === '' || $password === '' || $role === '') {
        $errors[] = 'Please fill in all fields.';
    } else {
        $conn = db_connect();
        $user = db_fetch_one($conn, 'SELECT id, name, email, password, role FROM users WHERE email = ? AND role = ?', [$email, $role]);

        if (!$user || !verify_password($password, $user['password'])) {
            $errors[] = 'Invalid email, password, or role.';
        } else {
            $_SESSION['user'] = [
                'id' => $user['id'],
                'name' => $user['name'],
                'email' => $user['email'],
                'role' => $user['role'],
            ];

            log_action($conn, 'User logged in', $user['name']);

            if ($user['role'] === 'admin') {
                redirect_to('admin.php');
            }
            if ($user['role'] === 'systemadmin') {
                redirect_to('systemadmin.php');
            }
            if ($user['role'] === 'student') {
                redirect_to('student.php');
            }
            if ($user['role'] === 'parent') {
                redirect_to('parents.php');
            }
            if ($user['role'] === 'lecturer') {
                redirect_to('lecturer.php');
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Login</title>
  <link rel="stylesheet" href="styles.css">
</head>
<body>
  <div class="form-container">
    <h1>Login</h1>
    <?php if ($errors): ?>
      <div class="error-box"><?php echo sanitize(implode('<br>', $errors)); ?></div>
    <?php endif; ?>
    <form method="post" action="login.php">
      <label for="email">Email</label>
      <input type="email" id="email" name="email" placeholder="Enter your email" value="<?php echo sanitize($email); ?>" required>

      <label for="password">Password</label>
      <input type="password" id="password" name="password" placeholder="Enter your password" required>

      <label for="role">Role</label>
      <select id="role" name="role" required>
        <option value="admin" <?php echo $role === 'admin' ? 'selected' : ''; ?>>Admin</option>
        <option value="systemadmin" <?php echo $role === 'systemadmin' ? 'selected' : ''; ?>>System Admin</option>
        <option value="student" <?php echo $role === 'student' ? 'selected' : ''; ?>>Student</option>
        <option value="parent" <?php echo $role === 'parent' ? 'selected' : ''; ?>>Parent</option>
        <option value="lecturer" <?php echo $role === 'lecturer' ? 'selected' : ''; ?>>Lecturer</option>
      </select>

      <button class="btn btn-primary" type="submit">Login</button>
    </form>
    <p class="form-note">Lecturers can also <a class="nav-link" href="fingerprint_login.php">login with fingerprint</a>.</p>
    <div class="signup-link">
      Don't have an account? <a class="nav-link" href="signup.php">Sign up</a>
    </div>
  </div>
</body>
</html>
