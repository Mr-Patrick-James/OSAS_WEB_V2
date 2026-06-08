<?php
/**
 * Logout - Destroys session and redirects to login
 */
require_once __DIR__ . '/../../core/Session.php';
Session::start();

// Clear session data first
$_SESSION = array();

// Destroy session using Session helper
Session::destroy();

// Clear all cookies (must match the path and domain used when setting them)
$cookies = ['user_id', 'username', 'role', 'student_id', 'student_id_code'];
foreach ($cookies as $cookie) {
    // Clear with path "/" (no domain specified, so it uses current domain)
    setcookie($cookie, "", time() - 3600, "/");
}
header('Location: ' . dirname($_SERVER['SCRIPT_NAME'], 4) . '/index.php?direct=true');
exit;

