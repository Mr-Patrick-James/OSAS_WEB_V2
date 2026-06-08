<?php
session_start();

// Clear all session variables
$_SESSION = array();

// Destroy the session cookie
if (isset($_COOKIE[session_name()])) {
    setcookie(session_name(), '', time() - 3600, '/');
}

// Clear authentication cookies
$cookieOptions = [
    'expires' => time() - 3600,
    'path' => '/',
    'secure' => false,
    'httponly' => true,
    'samesite' => 'Lax'
];

setcookie('user_id', '', $cookieOptions);
setcookie('username', '', $cookieOptions);
setcookie('role', '', $cookieOptions);
setcookie('student_id', '', $cookieOptions);
setcookie('student_id_code', '', $cookieOptions);

// Destroy the session
session_destroy();

// Return success response
header('Content-Type: application/json');
echo json_encode([
    'status' => 'success',
    'message' => 'Logged out successfully'
]);
exit;
