<?php
require_once 'functions.php';
require_role('lecturer');

$conn = db_connect();
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_attendance'])) {
    $course_id = intval($_POST['course_id'] ?? 0);
    $student_ids = $_POST['student_ids'] ?? [];
    $present_students = $_POST['present_students'] ?? [];

    $course = db_fetch_one($conn, 'SELECT id, code, title FROM courses WHERE id = ? AND lecturer_id = ?', [$course_id, current_user()['id']]);

    if (!$course) {
        $error = 'Invalid course selected.';
    } elseif (empty($student_ids) || !is_array($student_ids)) {
        $error = 'No students found to mark attendance.';
    } else {
        $updatedCount = 0;
        foreach ($student_ids as $studentIdRaw) {
            $student_id = intval($studentIdRaw);
            $student = db_fetch_one($conn, 'SELECT id, name FROM users WHERE id = ? AND role = ?', [$student_id, 'student']);
            if (!$student) {
                continue;
            }

            $present = in_array($student_id, array_map('intval', $present_students), true);
            $attendance_percent = $present ? 100 : 0;
            $absences = $present ? 0 : 1;

            db_query(
                $conn,
                'INSERT INTO attendance (student_id, course_id, attendance_percent, absences) VALUES (?, ?, ?, ?)',
                [$student_id, $course_id, $attendance_percent, $absences]
            );
            $updatedCount++;
        }

        if ($updatedCount > 0) {
            $success = 'Class attendance recorded for ' . sanitize($course['code'] . ' - ' . $course['title']) . '.';
            log_action($conn, 'Recorded class attendance for course ' . $course['code'] . ' (' . $course['title'] . ')', current_user()['name']);
        } else {
            $error = 'No valid students were selected for attendance.';
        }
    }
}

$courses = db_fetch_all(
    $conn,
    'SELECT c.id, c.code, c.title, c.credits, c.semester, c.status, c.progress, COUNT(a.id) AS student_count FROM courses c LEFT JOIN attendance a ON a.course_id = c.id WHERE c.lecturer_id = ? GROUP BY c.id ORDER BY c.code',
    [current_user()['id']]
);

$students = db_fetch_all($conn, 'SELECT id, name, email FROM users WHERE role = ? ORDER BY name', ['student']);

$userInfo = db_fetch_one($conn, 'SELECT fingerprint_credential_id, fingerprint_registered_at FROM users WHERE id = ?', [current_user()['id']]);
$fingerprintRegistered = !empty($userInfo['fingerprint_credential_id']);
$fingerprintRegisteredAt = $userInfo['fingerprint_registered_at'] ?? '';

$attendance_records = db_fetch_all(
    $conn,
    'SELECT a.id, a.student_id, u.name AS student_name, u.email AS student_email, c.id AS course_id, c.code, c.title, a.attendance_percent, a.absences, a.last_updated
     FROM attendance a
     JOIN users u ON u.id = a.student_id
     JOIN courses c ON c.id = a.course_id
     WHERE c.lecturer_id = ?
     ORDER BY c.code, u.name',
    [current_user()['id']]
); 
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Lecturer Management System</title>
  <link rel="stylesheet" href="styles.css">
</head>
<body>
  <div class="sidebar">
    <h2>Lecturer Management</h2>
    <a href="courses.php"><i class="fas fa-book"></i> Courses</a>
    <a href="lecturer.php" class="active"><i class="fas fa-chalkboard-teacher"></i> My Courses</a>
    <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
  </div>

  <div class="main">
    <div class="top-bar">
      <div>
        <h1>Welcome, <?php echo sanitize(current_user()['name']); ?></h1>
        <p>Instructor dashboard for your assigned courses.</p>
      </div>
      <button class="btn" onclick="window.location.href='logout.php'">Logout</button>
    </div>

    <?php if ($error): ?>
      <div class="card alert-card error-box">
        <strong>Error:</strong> <?php echo sanitize($error); ?>
      </div>
    <?php endif; ?>
    <?php if ($success): ?>
      <div class="card alert-card success-box">
        <strong>Success:</strong> <?php echo sanitize($success); ?>
      </div>
    <?php endif; ?>

    <div class="card">
      <h3>Lecturer Fingerprint Enrollment</h3>
      <?php if ($fingerprintRegistered): ?>
        <p>Fingerprint login is registered. Last registered at <?php echo sanitize($fingerprintRegisteredAt); ?>.</p>
        <a class="btn" href="fingerprint_register.php">Re-register fingerprint</a>
      <?php else: ?>
        <p>Register a lecturer fingerprint to enable secure fingerprint login for your account.</p>
        <a class="btn" href="fingerprint_register.php">Register fingerprint</a>
      <?php endif; ?>
    </div>

    <?php if (empty($courses)): ?>
      <div class="card">
        <h3>Mark Attendance</h3>
        <p>No courses are assigned to you yet. Ask admin to assign courses to your account.</p>
      </div>
    <?php else: ?>
      <div class="card">
        <h3>Mark Attendance</h3>
        <p>Tick students present in class for each assigned course. Submit once after selecting present students.</p>
      </div>
      <?php foreach ($courses as $course): ?>
        <div class="card">
          <h4><?php echo sanitize($course['code'] . ' - ' . $course['title']); ?></h4>
          <form method="post">
            <input type="hidden" name="mark_attendance" value="1">
            <input type="hidden" name="course_id" value="<?php echo sanitize($course['id']); ?>">
            <table>
              <thead>
                <tr>
                  <th>Present</th>
                  <th>Student</th>
                  <th>Email</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($students as $student): ?>
                  <tr>
                    <td>
                      <input type="checkbox" name="present_students[]" value="<?php echo sanitize($student['id']); ?>">
                      <input type="hidden" name="student_ids[]" value="<?php echo sanitize($student['id']); ?>">
                    </td>
                    <td><?php echo sanitize($student['name']); ?></td>
                    <td><?php echo sanitize($student['email']); ?></td>
                  </tr>
                <?php endforeach; ?>
                <?php if (empty($students)): ?>
                  <tr><td colspan="3">No students available to mark yet.</td></tr>
                <?php endif; ?>
              </tbody>
            </table>
            <button class="btn" type="submit">Save Class Attendance</button>
          </form>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>

    <div class="card">
      <h3>Assigned Courses</h3>
      <table>
        <thead>
          <tr>
            <th>Course Code</th>
            <th>Course Name</th>
            <th>Credits</th>
            <th>Semester</th>
            <th>Status</th>
            <th>Student Count</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($courses)): ?>
            <tr><td colspan="6">No courses assigned yet.</td></tr>
          <?php else: ?>
            <?php foreach ($courses as $course): ?>
              <tr>
                <td><?php echo sanitize($course['code']); ?></td>
                <td><?php echo sanitize($course['title']); ?></td>
                <td><?php echo sanitize($course['credits']); ?></td>
                <td><?php echo sanitize($course['semester']); ?></td>
                <td><?php echo sanitize($course['status']); ?></td>
                <td><?php echo sanitize($course['student_count']); ?></td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>

    <div class="card">
      <h3>Attendance Records</h3>
      <table>
        <thead>
          <tr>
            <th>Course</th>
            <th>Student</th>
            <th>Email</th>
            <th>Attendance %</th>
            <th>Absences</th>
            <th>Last Updated</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($attendance_records)): ?>
            <tr><td colspan="6">No attendance entries yet. Use the form above to add attendance.</td></tr>
          <?php else: ?>
            <?php foreach ($attendance_records as $record): ?>
              <tr>
                <td><?php echo sanitize($record['code'] . ' - ' . $record['title']); ?></td>
                <td><?php echo sanitize($record['student_name']); ?></td>
                <td><?php echo sanitize($record['student_email']); ?></td>
                <td><?php echo sanitize($record['attendance_percent']); ?></td>
                <td><?php echo sanitize($record['absences']); ?></td>
                <td><?php echo sanitize($record['last_updated']); ?></td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</body>
</html>
