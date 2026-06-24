<?php
require_once 'functions.php';
require_login();

$semester = $_GET['semester'] ?? 'all';
$conn = db_connect();
if ($semester === 'all') {
    $courses = db_fetch_all(
        $conn,
        'SELECT c.id, c.code, c.title, c.credits, c.semester, c.progress, c.status, u.name AS lecturer FROM courses c LEFT JOIN users u ON c.lecturer_id = u.id ORDER BY c.code'
    );
} else {
    $courses = db_fetch_all(
        $conn,
        'SELECT c.id, c.code, c.title, c.credits, c.semester, c.progress, c.status, u.name AS lecturer FROM courses c LEFT JOIN users u ON c.lecturer_id = u.id WHERE c.semester = ? ORDER BY c.code',
        [(int)$semester]
    );
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Courses</title>
  <link rel="stylesheet" href="styles.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css" crossorigin="anonymous" referrerpolicy="no-referrer" />
</head>
<body>
  <div class="header">
    <div>
      <h1>Courses</h1>
      <p>Browse the available courses in the attendance system.</p>
    </div>
    <div>
      <button class="btn" onclick="window.location.href='logout.php'">Logout</button>
    </div>
  </div>

  <div class="container">
    <div class="filter-bar">
      <div>
        <label for="semester-filter"><strong>Filter by Semester:</strong></label>
        <form id="semester-form" class="filter-form" method="get" action="courses.php">
          <select id="semester-filter" name="semester" onchange="document.getElementById('semester-form').submit();">
            <option value="all" <?php echo $semester === 'all' ? 'selected' : ''; ?>>All Semesters</option>
            <option value="1" <?php echo $semester === '1' ? 'selected' : ''; ?>>Semester 1</option>
            <option value="2" <?php echo $semester === '2' ? 'selected' : ''; ?>>Semester 2</option>
          </select>
        </form>
      </div>
      <div>
        Logged in as <?php echo sanitize(current_user()['name']); ?>
      </div>
    </div>

    <div class="course-list">
      <?php if (empty($courses)): ?>
        <div class="course-card">
          <p>No courses found for this semester.</p>
        </div>
      <?php else: ?>
        <?php foreach ($courses as $course): ?>
          <div class="course-card">
            <h3><?php echo sanitize($course['title']); ?></h3>
            <p><strong>Code:</strong> <?php echo sanitize($course['code']); ?></p>
            <p><strong>Instructor:</strong> <?php echo sanitize($course['lecturer'] ?: 'Unassigned'); ?></p>
            <p><strong>Credits:</strong> <?php echo sanitize($course['credits']); ?></p>
            <p class="status <?php echo sanitize($course['status']); ?>"><strong>Status:</strong> <?php echo sanitize($course['status']); ?></p>
            <div class="progress-bar"><span style="width: <?php echo sanitize($course['progress']); ?>%;"></span></div>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </div>
</body>
</html>
