<?php
/**
 * Monthly Reset Cron Job
 * 
 * Set up on AWS EC2 with crontab:
 *   0 0 1 * * /usr/bin/php /var/www/osas/api/cron_monthly_reset.php >> /var/log/osas_cron.log 2>&1
 * 
 * This runs at midnight on the 1st of every month.
 * It can also be triggered manually via HTTP (admin only) or CLI.
 */

// Allow CLI execution
$isCLI = (php_sapi_name() === 'cli');

if (!$isCLI) {
    // HTTP access — require admin session or a secret token
    session_start();
    $token = $_GET['token'] ?? '';
    $validToken = getenv('CRON_SECRET') ?: 'osas_cron_2026';

    if ($token !== $validToken && (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin')) {
        http_response_code(403);
        echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
        exit;
    }
}

require_once __DIR__ . '/../app/core/Model.php';
require_once __DIR__ . '/../app/models/ViolationModel.php';

try {
    $model = new ViolationModel();
    $result = $model->archivePreviousMonthViolations();

    // Update the last reset tracker in DB
    $currentMonth = date('Y-m');
    $conn = $model->getConnection();
    $stmt = $conn->prepare(
        "INSERT INTO system_settings (setting_key, setting_value)
         VALUES ('last_monthly_reset', ?)
         ON DUPLICATE KEY UPDATE setting_value = ?"
    );
    $stmt->bind_param('ss', $currentMonth, $currentMonth);
    $stmt->execute();
    $stmt->close();

    $msg = "✅ Monthly reset completed for $currentMonth";
    error_log($msg);

    if ($isCLI) {
        echo $msg . PHP_EOL;
    } else {
        header('Content-Type: application/json');
        echo json_encode(['status' => 'success', 'message' => $msg]);
    }
} catch (Exception $e) {
    $msg = "❌ Monthly reset failed: " . $e->getMessage();
    error_log($msg);

    if ($isCLI) {
        echo $msg . PHP_EOL;
        exit(1);
    } else {
        http_response_code(500);
        header('Content-Type: application/json');
        echo json_encode(['status' => 'error', 'message' => $msg]);
    }
}
