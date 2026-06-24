<?php
require_once 'functions.php';
require_role('student');

$conn = db_connect();
$attendanceRecords = db_fetch_all(
    $conn,
    'SELECT c.title AS course_title, a.attendance_percent, a.absences FROM attendance a JOIN courses c ON a.course_id = c.id WHERE a.student_id = ? ORDER BY c.title',
    [current_user()['id']]
);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Student Portal</title>
  <link rel="stylesheet" href="styles.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css" crossorigin="anonymous" referrerpolicy="no-referrer" />
</head>
<body>
  <div class="sidebar">
    <h2>Student Portal</h2>
    <a href="courses.php"><i class="fas fa-book"></i> Courses</a>
    <a href="student.php" class="active"><i class="fas fa-chart-line"></i> Attendance</a>
    <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
  </div>

  <div class="main">
    <div class="top-bar">
      <h1>Welcome, <?php echo sanitize(current_user()['name']); ?></h1>
      <div class="action-buttons">
        <button class="dark-mode-toggle" onclick="toggleDarkMode()">Toggle Dark Mode</button>
        <button class="btn" onclick="window.location.href='logout.php'">Logout</button>
      </div>
    </div>

    <?php if (empty($attendanceRecords)): ?>
      <div class="message">No attendance records found for your account yet.</div>
    <?php else: ?>
      <table id="attendance-table">
        <thead>
          <tr>
            <th>Course Name</th>
            <th>Attendance (%)</th>
            <th>Absences</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($attendanceRecords as $record): ?>
            <tr>
              <td><?php echo sanitize($record['course_title']); ?></td>
              <td><?php echo sanitize($record['attendance_percent']); ?>%</td>
              <td><?php echo sanitize($record['absences']); ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </div>

  <script>
    function toggleDarkMode() {
      document.body.classList.toggle('dark-mode');
      document.getElementById('attendance-table').classList.toggle('dark-mode');
    }
  </script>
</body>
</html>
