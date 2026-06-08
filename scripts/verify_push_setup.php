<?php
mysqli_report(MYSQLI_REPORT_OFF);
require_once __DIR__ . '/../config/db_connect.php';
echo isset($conn) && !$conn->connect_error ? "Database: OK\n" : "Database: FAILED — " . ($conn->connect_error ?? '') . "\n";

$c = require __DIR__ . '/../app/config/push_config.php';
echo 'Config enabled: ' . (!empty($c['enabled']) ? 'yes' : 'no') . "\n";
echo 'VAPID public: ' . (trim($c['vapid']['publicKey'] ?? '') !== '' ? 'yes' : 'no') . "\n";
echo 'Composer vendor: ' . (file_exists(__DIR__ . '/../vendor/autoload.php') ? 'yes' : 'no') . "\n";
