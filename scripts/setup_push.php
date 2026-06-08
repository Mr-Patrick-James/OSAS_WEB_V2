<?php
mysqli_report(MYSQLI_REPORT_OFF);
require_once __DIR__ . '/../config/db_connect.php';

if (!isset($conn) || $conn->connect_error) {
    fwrite(STDERR, "DB failed: " . ($conn->connect_error ?? '') . "\n");
    fwrite(STDERR, "AWS: cp config/db_connect.aws.example.php config/db_connect.local.php\n");
    exit(1);
}
echo "DB OK\n";

$sql = file_get_contents(__DIR__ . '/../migrations/add_push_subscriptions.sql');
$conn->multi_query($sql);
while ($conn->more_results() && $conn->next_result()) { /* flush */ }
if ($conn->errno) {
    fwrite(STDERR, "Migration error: " . $conn->error . "\n");
    exit(1);
}

$r = $conn->query("SHOW TABLES LIKE 'push_subscriptions'");
echo ($r && $r->num_rows) ? "Table push_subscriptions: OK\n" : "Table missing\n";

$needHash = $conn->query("SHOW COLUMNS FROM push_subscriptions LIKE 'endpoint_hash'");
if (!$needHash || $needHash->num_rows === 0) {
    require __DIR__ . '/fix_push_schema.php';
    exit;
}

$c = require __DIR__ . '/../app/config/push_config.php';
echo !empty($c['enabled']) && !empty($c['vapid']['publicKey'])
    ? "Push VAPID: OK\n"
    : "Push VAPID: set app/config/push_config.local.php\n";
