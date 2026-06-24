<?php
require_once 'functions.php';
require_role('parent');

$conn = db_connect();
$attendanceRecords = db_fetch_all(
    $conn,
    'SELECT u.name AS child_name, c.title AS course_title, a.attendance_percent, a.absences FROM attendance a JOIN users u ON a.student_id = u.id JOIN parent_child pc ON pc.student_id = u.id JOIN courses c ON a.course_id = c.id WHERE pc.parent_id = ? ORDER BY u.name, c.title',
    [current_user()['id']]
);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Parent Portal</title>
  <link rel="stylesheet" href="styles.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css" crossorigin="anonymous" referrerpolicy="no-referrer" />
</head>
<body>
  <div class="sidebar">
    <h2>Parent Portal</h2>
    <a href="courses.php"><i class="fas fa-book"></i> Courses</a>
    <a href="parents.php" class="active"><i class="fas fa-user"></i> My Child's Attendance</a>
    <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
  </div>

  <div class="main">
    <div class="top-bar">
      <h1><?php echo sanitize(current_user()['name']); ?>'s Child Attendance</h1>
      <button class="btn" onclick="window.location.href='logout.php'">Logout</button>
    </div>

    <?php if (empty($attendanceRecords)): ?>
      <div class="message">No child attendance records are available yet. Make sure your student is linked to your parent account in the database.</div>
    <?php else: ?>
      <table>
        <thead>
          <tr>
            <th>Student</th>
            <th>Course Name</th>
            <th>Attendance (%)</th>
            <th>Absences</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($attendanceRecords as $record): ?>
            <tr>
              <td><?php echo sanitize($record['child_name']); ?></td>
              <td><?php echo sanitize($record['course_title']); ?></td>
              <td><?php echo sanitize($record['attendance_percent']); ?>%</td>
              <td><?php echo sanitize($record['absences']); ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </div>
</body>
</html>
