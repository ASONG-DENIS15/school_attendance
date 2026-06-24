<?php
require_once 'functions.php';
require_role(['admin', 'systemadmin']);

$conn = db_connect();
$errors = [];
$messages = [];

$allowedRoles = ['student', 'parent', 'lecturer'];
if (current_user()['role'] === 'systemadmin') {
    $allowedRoles = array_merge($allowedRoles, ['admin', 'systemadmin']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add_user') {
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $role = $_POST['role'] ?? '';

        if ($name === '' || $email === '' || $password === '' || $role === '') {
            $errors[] = 'Please complete all user fields.';
        } elseif (!in_array($role, $allowedRoles, true)) {
            $errors[] = 'You are not allowed to create that role.';
        } else {
            $existing = db_fetch_one($conn, 'SELECT id FROM users WHERE email = ?', [$email]);
            if ($existing) {
                $errors[] = 'A user with that email already exists.';
            } else {
                $hashed = hash_password($password);
                db_query($conn, 'INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, ?)', [$name, $email, $hashed, $role]);
                $messages[] = 'User added successfully.';
                log_action($conn, 'Added new user (' . $role . ')', current_user()['name']);
            }
        }
    }

    if ($action === 'delete_user') {
        $userId = intval($_POST['user_id'] ?? 0);
        if ($userId === current_user()['id']) {
            $errors[] = 'You cannot delete your own account.';
        } else {
            $target = db_fetch_one($conn, 'SELECT role, name FROM users WHERE id = ?', [$userId]);
            if (!$target) {
                $errors[] = 'User not found.';
            } elseif ($target['role'] === 'systemadmin' && current_user()['role'] !== 'systemadmin') {
                $errors[] = 'Only a system admin can delete another system admin.';
            } else {
                db_query($conn, 'DELETE FROM users WHERE id = ?', [$userId]);
                $messages[] = 'User deleted successfully.';
                log_action($conn, 'Deleted user (' . $target['name'] . ')', current_user()['name']);
            }
        }
    }

    if ($action === 'add_course') {
        $code = trim($_POST['code'] ?? '');
        $title = trim($_POST['title'] ?? '');
        $credits = intval($_POST['credits'] ?? 0);
        $semester = intval($_POST['semester'] ?? 0);
        $status = $_POST['status'] ?? 'ongoing';
        $progress = intval($_POST['progress'] ?? 0);
        $lecturerId = intval($_POST['lecturer_id'] ?? 0) ?: null;

        if ($code === '' || $title === '' || $credits <= 0 || $semester <= 0) {
            $errors[] = 'Please fill in all course fields with valid values.';
        } else {
            if ($lecturerId !== null) {
                $lecturer = db_fetch_one($conn, 'SELECT id FROM users WHERE id = ? AND role = ?', [$lecturerId, 'lecturer']);
                if (!$lecturer) {
                    $errors[] = 'Selected lecturer is invalid.';
                }
            }
        }

        if (empty($errors)) {
            db_query($conn, 'INSERT INTO courses (code, title, credits, semester, progress, status, lecturer_id) VALUES (?, ?, ?, ?, ?, ?, ?)', [$code, $title, $credits, $semester, $progress, $status, $lecturerId]);
            $messages[] = 'Course added successfully.';
            log_action($conn, 'Added new course (' . $code . ')', current_user()['name']);
        }
    }

    if ($action === 'delete_course') {
        $courseId = intval($_POST['course_id'] ?? 0);
        $course = db_fetch_one($conn, 'SELECT title FROM courses WHERE id = ?', [$courseId]);
        if (!$course) {
            $errors[] = 'Course not found.';
        } else {
            db_query($conn, 'DELETE FROM courses WHERE id = ?', [$courseId]);
            $messages[] = 'Course deleted successfully.';
            log_action($conn, 'Deleted course (' . $course['title'] . ')', current_user()['name']);
        }
    }
}

$users = db_fetch_all($conn, 'SELECT id, name, role, email FROM users ORDER BY role, name');
$lecturers = db_fetch_all($conn, 'SELECT id, name FROM users WHERE role = ? ORDER BY name', ['lecturer']);
$courses = db_fetch_all($conn, 'SELECT c.id, c.code, c.title, c.credits, c.semester, c.progress, c.status, u.name AS lecturer FROM courses c LEFT JOIN users u ON c.lecturer_id = u.id ORDER BY c.code');
$attendance = db_fetch_all($conn, 'SELECT a.id, u.name AS student, c.title AS course, a.attendance_percent, a.absences FROM attendance a JOIN users u ON a.student_id = u.id JOIN courses c ON a.course_id = c.id ORDER BY a.id');
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Admin Portal</title>
  <link rel="stylesheet" href="styles.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css" crossorigin="anonymous" referrerpolicy="no-referrer" />
</head>
<body>
  <div class="sidebar">
    <h2>Admin Portal</h2>
    <a href="admin.php" class="active"><i class="fas fa-users"></i> Manage Users</a>
    <a href="courses.php"><i class="fas fa-book"></i> Manage Courses</a>
    <a href="admin.php#attendance"><i class="fas fa-calendar-check"></i> Manage Attendance</a>
    <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
  </div>

  <div class="main">
    <?php if (!empty($messages)): ?>
      <div class="message-box"><?php echo sanitize(implode(' ', $messages)); ?></div>
    <?php endif; ?>
    <?php if (!empty($errors)): ?>
      <div class="error-box"><?php echo sanitize(implode(' ', $errors)); ?></div>
    <?php endif; ?>
    <div class="top-bar">
      <h1>Welcome, <?php echo sanitize(current_user()['name']); ?></h1>
      <button class="btn" onclick="window.location.href='logout.php'">Logout</button>
    </div>

    <div class="card">
      <h3>Manage Users</h3>
      <div class="table-container">
        <table>
          <thead>
            <tr>
              <th>User ID</th>
              <th>Name</th>
              <th>Role</th>
              <th>Email</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($users as $user): ?>
              <tr>
                <td><?php echo sanitize($user['id']); ?></td>
                <td><?php echo sanitize($user['name']); ?></td>
                <td><?php echo sanitize($user['role']); ?></td>
                <td><?php echo sanitize($user['email']); ?></td>
                <td>
                  <?php if ($user['id'] !== current_user()['id']): ?>
                    <form method="post" class="inline-form">
                      <input type="hidden" name="action" value="delete_user">
                      <input type="hidden" name="user_id" value="<?php echo sanitize($user['id']); ?>">
                      <button class="btn" type="submit" onclick="return confirm('Delete this user?');">Delete</button>
                    </form>
                  <?php else: ?>
                    -
                  <?php endif; ?>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <form method="post" class="form-grid">
        <input type="hidden" name="action" value="add_user">
        <div class="form-group">
          <label for="name">Name</label>
          <input type="text" id="name" name="name" required>
        </div>
        <div class="form-group">
          <label for="email">Email</label>
          <input type="email" id="email" name="email" required>
        </div>
        <div class="form-group">
          <label for="password">Password</label>
          <input type="password" id="password" name="password" required>
        </div>
        <div class="form-group">
          <label for="role">Role</label>
          <select id="role" name="role" required>
            <?php foreach ($allowedRoles as $roleOption): ?>
              <option value="<?php echo sanitize($roleOption); ?>"><?php echo sanitize(ucfirst($roleOption)); ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-actions">
          <button class="btn" type="submit">Add User</button>
        </div>
      </form>
    </div>

    <div class="card">
      <h3>Manage Courses</h3>
      <div class="table-container">
        <table>
          <thead>
            <tr>
              <th>Course ID</th>
              <th>Course Code</th>
              <th>Course Name</th>
              <th>Instructor</th>
              <th>Credits</th>
              <th>Status</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($courses as $course): ?>
              <tr>
                <td><?php echo sanitize($course['id']); ?></td>
                <td><?php echo sanitize($course['code']); ?></td>
                <td><?php echo sanitize($course['title']); ?></td>
                <td><?php echo sanitize($course['lecturer'] ?: 'Unassigned'); ?></td>
                <td><?php echo sanitize($course['credits']); ?></td>
                <td><?php echo sanitize($course['status']); ?></td>
                <td>
                  <form method="post" class="inline-form">
                    <input type="hidden" name="action" value="delete_course">
                    <input type="hidden" name="course_id" value="<?php echo sanitize($course['id']); ?>">
                    <button class="btn" type="submit" onclick="return confirm('Delete this course?');">Delete</button>
                  </form>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <form method="post" class="form-grid">
        <input type="hidden" name="action" value="add_course">
        <div class="form-group">
          <label for="code">Course Code</label>
          <input type="text" id="code" name="code" required>
        </div>
        <div class="form-group">
          <label for="title">Course Title</label>
          <input type="text" id="title" name="title" required>
        </div>
        <div class="form-group">
          <label for="credits">Credits</label>
          <input type="number" id="credits" name="credits" min="1" required>
        </div>
        <div class="form-group">
          <label for="semester">Semester</label>
          <input type="number" id="semester" name="semester" min="1" required>
        </div>
        <div class="form-group">
          <label for="status">Status</label>
          <select id="status" name="status" required>
            <option value="ongoing">Ongoing</option>
            <option value="completed">Completed</option>
          </select>
        </div>
        <div class="form-group">
          <label for="progress">Progress (%)</label>
          <input type="number" id="progress" name="progress" min="0" max="100" value="0" required>
        </div>
        <div class="form-group">
          <label for="lecturer_id">Lecturer</label>
          <select id="lecturer_id" name="lecturer_id">
            <option value="">Unassigned</option>
            <?php foreach ($lecturers as $lecturer): ?>
              <option value="<?php echo sanitize($lecturer['id']); ?>"><?php echo sanitize($lecturer['name']); ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-actions">
          <button class="btn" type="submit">Add Course</button>
        </div>
      </form>
    </div>

    <div class="card" id="attendance">
      <h3>Manage Attendance</h3>
      <div class="table-container">
        <table>
          <thead>
            <tr>
              <th>Attendance ID</th>
              <th>Student</th>
              <th>Course</th>
              <th>Attendance (%)</th>
              <th>Absences</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($attendance as $row): ?>
              <tr>
                <td><?php echo sanitize($row['id']); ?></td>
                <td><?php echo sanitize($row['student']); ?></td>
                <td><?php echo sanitize($row['course']); ?></td>
                <td><?php echo sanitize($row['attendance_percent']); ?></td>
                <td><?php echo sanitize($row['absences']); ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</body>
</html>
