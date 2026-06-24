# School Attendance System

## Setup in XAMPP

1. Place this folder inside your XAMPP `htdocs` directory or configure a virtual host.
   - Example: `C:\xampp\htdocs\school-attendance-system`

2. Start Apache and MySQL using the XAMPP control panel.

3. Open phpMyAdmin at `http://localhost/phpmyadmin`.

4. Import the database:
   - Select `Import`.
   - Choose `school_attendance.sql` from this folder.
   - Execute the import.

5. Open the application:
   - `http://localhost/school-attendance-system/login.php`

## Default users

- Admin: `admin@example.com` / `admin123`
- System Admin: `sysadmin@example.com` / `systemadmin123`
- Student: `student@example.com` / `student123`
- Parent: `parent@example.com` / `parent123`
- Lecturer: `lecturer@example.com` / `lecturer123`

## Files created

- `config.php` — database settings and session helpers
- `functions.php` — database helper functions and password utilities
- `login.php` — login page with database authentication
- `signup.php` — registration form for students, parents, and lecturers
- `logout.php` — destroys session and returns to login
- `admin.php` — admin portal showing users, courses, and attendance
- `student.php` — student portal with attendance records
- `parents.php` — parent portal showing linked child attendance
- `lecturer.php` — lecturer portal for assigned courses
- `courses.php` — course listing page with semester filter
- `fingerprint_register.php` — lecturer fingerprint enrollment page using WebAuthn
- `fingerprint_login.php` — lecturer fingerprint login page using a device authenticator
- `systemadmin.php` — system administrator portal showing logs
- `school_attendance.sql` — database schema and sample data

## Notes

- The app uses server-side PHP and MySQL; do not open the PHP pages directly from the file system.
- Use a browser with `http://localhost/...`.
