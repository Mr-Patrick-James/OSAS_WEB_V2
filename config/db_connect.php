<?php
/**
 * Database configuration.
 * Local WAMP: uses defaults below (root, no password).
 * AWS EC2: copy config/db_connect.aws.example.php → config/db_connect.local.php
 */
date_default_timezone_set('Asia/Manila');

$localFile = __DIR__ . '/db_connect.local.php';

if (file_exists($localFile)) {
    require $localFile;
    if (!isset($conn)) {
        $conn = @new mysqli($host ?? 'localhost', $user ?? 'root', $pass ?? '', $dbname ?? 'osas');
    }
} elseif (getenv('DB_HOST') || getenv('DB_USER') || getenv('DB_PASS') !== false || getenv('DB_NAME')) {
    $host   = getenv('DB_HOST') ?: 'localhost';
    $user   = getenv('DB_USER') ?: 'root';
    $pass   = getenv('DB_PASS') ?: '';
    $dbname = getenv('DB_NAME') ?: 'osas';
    $conn   = @new mysqli($host, $user, $pass, $dbname);
} else {
    $host   = 'localhost';
    $user   = 'root';
    $pass   = '';
    $dbname = 'osas';
    $conn   = @new mysqli($host, $user, $pass, $dbname);
}

if ($conn->connect_error) {
    error_log('Database connection failed: ' . $conn->connect_error);
} else {
    $conn->query("SET time_zone = '+08:00'");
    if (!$conn->set_charset('utf8mb4')) {
        error_log('Warning: Failed to set charset utf8mb4: ' . $conn->error);
    }
}
