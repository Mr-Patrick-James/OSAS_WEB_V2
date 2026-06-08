<?php
// Populate users table from students: email + default password + role 'user'
require_once __DIR__ . '/../app/config/db_connect.php';

if ($conn->connect_error) {
    die("DB connect error: " . $conn->connect_error);
}

// Detect users table schema
$columns = [];
$res = $conn->query('SHOW COLUMNS FROM users');
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $columns[$row['Field']] = true;
    }
}
$hasFullName = isset($columns['full_name']);
$hasIsActive = isset($columns['is_active']);

// Default password
$defaultPassword = password_hash('password123', PASSWORD_DEFAULT);

// Get students with valid emails
$students = $conn->query("SELECT student_id, first_name, last_name, email FROM students WHERE email IS NOT NULL AND email <> ''");
if (!$students) {
    die("Query students failed: " . $conn->error);
}

$created = 0;
$updated = 0;
$skipped = 0;

while ($s = $students->fetch_assoc()) {
    $studentId = trim((string)$s['student_id']);
    $first = trim((string)$s['first_name']);
    $last = trim((string)$s['last_name']);
    $email = trim((string)$s['email']);
    if ($email === '' || $studentId === '') { $skipped++; continue; }
    $username = $studentId; // username = student ID
    $fullName = trim($first . ' ' . $last);

    // Check if a user already exists with this email
    $stmtCheck = $conn->prepare("SELECT id, role FROM users WHERE email = ? LIMIT 1");
    $stmtCheck->bind_param("s", $email);
    $stmtCheck->execute();
    $result = $stmtCheck->get_result();

    if ($result && $result->num_rows > 0) {
        $user = $result->fetch_assoc();
        $stmtCheck->close();
        // If it's already a student/user, ensure role 'user' and set active
        if ($user['role'] !== 'admin') {
            $sql = "UPDATE users SET role = 'user', is_active = 1 WHERE id = ?";
            $stmtUpd = $conn->prepare($sql);
            $stmtUpd->bind_param("i", $user['id']);
            if ($stmtUpd->execute()) { $updated++; }
            $stmtUpd->close();
        } else {
            $skipped++;
        }
        continue;
    }
    $stmtCheck->close();

    // Insert new user
    if ($hasFullName && $hasIsActive) {
        $sql = "INSERT INTO users (username, email, password, role, full_name, student_id, is_active, created_at) VALUES (?, ?, ?, 'user', ?, ?, 1, NOW())";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sssss", $username, $email, $defaultPassword, $fullName, $studentId);
    } else {
        // Minimal schema fallback
        $sql = "INSERT INTO users (username, email, password, role, student_id, created_at) VALUES (?, ?, ?, 'user', ?, NOW())";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssss", $username, $email, $defaultPassword, $studentId);
    }

    if ($stmt && $stmt->execute()) {
        $created++;
    } else {
        $skipped++;
    }
    if ($stmt) { $stmt->close(); }
}

echo "Created: {$created}\nUpdated: {$updated}\nSkipped: {$skipped}\n";

// Done
$conn->close();
?>
