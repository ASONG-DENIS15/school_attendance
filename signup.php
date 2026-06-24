<?php
require_once 'functions.php';

$errors = [];
$name = '';
$email = '';
$role = $_POST['role'] ?? $_GET['role'] ?? '';

$allowedRoles = ['student', 'parent', 'lecturer', 'admin', 'systemadmin'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $role = $_POST['role'] ?? $role;

    if ($name === '' || $email === '' || $password === '' || $role === '') {
        $errors[] = 'Please fill in all fields.';
    } elseif (!in_array($role, $allowedRoles, true)) {
        $errors[] = 'Invalid user role selected.';
    } else {
        $conn = db_connect();
        $existing = db_fetch_one($conn, 'SELECT id FROM users WHERE email = ?', [$email]);

        if ($existing) {
            $errors[] = 'This email is already registered. Please login instead.';
        } else {
            $hashedPassword = hash_password($password);
            $stmt = db_query($conn, 'INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, ?)', [$name, $email, $hashedPassword, $role]);
            $userId = $conn->insert_id;
            $_SESSION['user'] = [
                'id' => $userId,
                'name' => $name,
                'email' => $email,
                'role' => $role,
            ];
            log_action($conn, 'New user registered as ' . $role, $name);
            $dashboardUrl = 'login.php';
            if ($role === 'admin') {
                $dashboardUrl = 'admin.php';
            } elseif ($role === 'systemadmin') {
                $dashboardUrl = 'systemadmin.php';
            } elseif ($role === 'lecturer') {
                $dashboardUrl = 'lecturer.php';
            } elseif ($role === 'student') {
                $dashboardUrl = 'student.php';
            } elseif ($role === 'parent') {
                $dashboardUrl = 'parents.php';
            }
            redirect_to($dashboardUrl);
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Signup</title>
  <link rel="stylesheet" href="styles.css">
</head>
<body>
  <div class="form-container">
    <h1>Sign Up</h1>
    <?php if ($errors): ?>
      <div class="error-box"><?php echo sanitize(implode('<br>', $errors)); ?></div>
    <?php endif; ?>
    <form method="post" action="signup.php">
      <label for="name">Name</label>
      <input type="text" id="name" name="name" placeholder="Enter your name" value="<?php echo sanitize($name); ?>" required>

      <label for="email">Email</label>
      <input type="email" id="email" name="email" placeholder="Enter your email" value="<?php echo sanitize($email); ?>" required>

      <label for="password">Password</label>
      <input type="password" id="password" name="password" placeholder="Enter your password" required>

      <label for="role">Role</label>
      <select id="role" name="role" required>
        <option value="student" <?php echo $role === 'student' ? 'selected' : ''; ?>>Student</option>
        <option value="parent" <?php echo $role === 'parent' ? 'selected' : ''; ?>>Parent</option>
        <option value="lecturer" <?php echo $role === 'lecturer' ? 'selected' : ''; ?>>Lecturer</option>
        <option value="admin" <?php echo $role === 'admin' ? 'selected' : ''; ?>>Admin</option>
        <option value="systemadmin" <?php echo $role === 'systemadmin' ? 'selected' : ''; ?>>System Admin</option>
      </select>

      <button class="btn btn-primary" type="submit">Sign Up</button>
    </form>
    <div class="login-link">
      Already have an account? <a class="nav-link" href="login.php">Login</a>
    </div>
  </div>
</body>
</html>
