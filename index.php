<?php
require_once 'config.php';

$user = current_user();
$role = $user['role'] ?? null;

$dashboardLink = 'courses.php';
if ($role === 'admin') {
    $dashboardLink = 'admin.php';
} elseif ($role === 'systemadmin') {
    $dashboardLink = 'systemadmin.php';
} elseif ($role === 'lecturer') {
    $dashboardLink = 'lecturer.php';
} elseif ($role === 'student') {
    $dashboardLink = 'student.php';
} elseif ($role === 'parent') {
    $dashboardLink = 'parents.php';
}

$topLinks = [];
if ($user) {
    $topLinks[] = ['href' => $dashboardLink, 'label' => 'Go to Dashboard'];
    $topLinks[] = ['href' => 'logout.php', 'label' => 'Logout'];
} else {
    $topLinks[] = ['href' => 'login.php', 'label' => 'Login'];
    $topLinks[] = ['href' => 'signup.php', 'label' => 'Sign Up'];
}
$topLinks[] = ['href' => 'courses.php', 'label' => 'Course Catalog'];

$cards = [];
if (!$user) {
    $cards[] = ['title' => 'Student', 'text' => 'Register as a student to track your course attendance and progress.', 'href' => 'signup.php?role=student'];
    $cards[] = ['title' => 'Parent', 'text' => 'Register as a parent to monitor your child’s attendance records.', 'href' => 'signup.php?role=parent'];
    $cards[] = ['title' => 'Lecturer', 'text' => 'Register as a lecturer to take class attendance and manage your courses.', 'href' => 'signup.php?role=lecturer'];
    $cards[] = ['title' => 'Admin', 'text' => 'Register as an admin to manage users, courses, and attendance data.', 'href' => 'signup.php?role=admin'];
    $cards[] = ['title' => 'System Admin', 'text' => 'Register as a system admin to oversee the platform and review logs.', 'href' => 'signup.php?role=systemadmin'];
    $cards[] = ['title' => 'Course Catalog', 'text' => 'View available courses and lecturer assignments before you register.', 'href' => 'courses.php'];
} else {
    $cards[] = ['title' => 'Dashboard', 'text' => 'Open your dedicated dashboard and continue with your role-specific work.', 'href' => $dashboardLink];
    $cards[] = ['title' => 'Course Catalog', 'text' => 'Browse current courses and lecturer assignments.', 'href' => 'courses.php'];

    if ($role === 'admin') {
        $cards[] = ['title' => 'Manage Users', 'text' => 'Access admin tools for users, lecturers, and course records.', 'href' => 'admin.php'];
        $cards[] = ['title' => 'Manage Courses', 'text' => 'Create and update course details and lecturer assignments.', 'href' => 'admin.php'];
    }
    if ($role === 'systemadmin') {
        $cards[] = ['title' => 'System Admin', 'text' => 'Review logs and maintain system administration.', 'href' => 'systemadmin.php'];
    }
    if ($role === 'lecturer') {
        $cards[] = ['title' => 'Take Attendance', 'text' => 'Mark attendance live for your courses in class.', 'href' => 'lecturer.php'];
        $cards[] = ['title' => 'My Courses', 'text' => 'Review your assigned courses and attendance records.', 'href' => 'lecturer.php'];
    }
    if ($role === 'student') {
        $cards[] = ['title' => 'My Attendance', 'text' => 'Review attendance scores and class progress.', 'href' => 'student.php'];
    }
    if ($role === 'parent') {
        $cards[] = ['title' => 'Child Attendance', 'text' => 'View your child’s attendance and course performance.', 'href' => 'parents.php'];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Rodrick University Attendance System</title>
  <link rel="stylesheet" href="styles.css">
</head>
<body>
  <header class="app-header">
    <div class="brand">
      <div class="brand-icon">RU</div>
      <div class="brand-details">
        <h1>Rodrick University</h1>
        <p>Attendance Management Portal</p>
      </div>
    </div>
    <nav class="app-nav">
      <?php foreach ($topLinks as $link): ?>
        <a class="nav-link" href="<?php echo sanitize($link['href']); ?>"><?php echo sanitize($link['label']); ?></a>
      <?php endforeach; ?>
    </nav>
  </header>

  <section class="hero">
    <div class="hero-copy">
      <p class="hero-label">Rodrick University</p>
      <h1>Attendance management built for every Rodrick University role</h1>
      <p>Secure, role-aware access for students, parents, lecturers, admins, and system administrators. Sign up or login to start managing attendance, courses, and progress.</p>
      <div class="hero-actions">
        <a class="button-link btn" href="<?php echo sanitize($user ? $dashboardLink : 'login.php'); ?>"><?php echo sanitize($user ? 'Continue to Dashboard' : 'Login'); ?></a>
        <?php if (!$user): ?>
          <a class="button-link nav-link" href="signup.php">Register Now</a>
        <?php endif; ?>
      </div>
      <?php if ($user): ?>
        <p class="hero-summary">Welcome back, <strong><?php echo sanitize($user['name']); ?></strong>. Your account has access to <strong><?php echo sanitize(ucwords($role)); ?></strong> features.</p>
      <?php endif; ?>
    </div>
    <div class="hero-visual">
      <div class="hero-callout">Live attendance tracking and role-specific dashboards for Rodrick University.</div>
    </div>
  </section>

  <main class="main">
    <div class="page-header">
      <div>
        <h2 class="page-title"><?php echo $user ? 'Your available tools' : 'Register for your role'; ?></h2>
        <p class="page-subtitle"><?php echo $user ? 'Quick access to the most important features for your role.' : 'Choose a role below to sign up and start using the Rodrick University portal.'; ?></p>
      </div>
    </div>

    <div class="grid-cards">
      <?php foreach ($cards as $card): ?>
        <div class="card">
          <h3><?php echo sanitize($card['title']); ?></h3>
          <p><?php echo sanitize($card['text']); ?></p>
          <a href="<?php echo sanitize($card['href']); ?>">Open</a>
        </div>
      <?php endforeach; ?>
    </div>
  </main>

  <footer class="page-footer">
    &copy; <?php echo date('Y'); ?> Rodrick University Attendance System.
  </footer>
</body>
</html>
