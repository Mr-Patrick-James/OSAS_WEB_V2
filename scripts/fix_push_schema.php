<?php
/**
 * Repairs push_subscriptions for Web Push (endpoint_hash, scope, endpoint, keys).
 * Run on AWS after deploy: php scripts/fix_push_schema.php
 */
mysqli_report(MYSQLI_REPORT_OFF);
require_once __DIR__ . '/../config/db_connect.php';

if (!isset($conn) || $conn->connect_error) {
    fwrite(STDERR, "DB failed: " . $conn->connect_error . "\n");
    exit(1);
}

function columnExists(mysqli $conn, string $table, string $col): bool
{
    $r = $conn->query("SHOW COLUMNS FROM `{$table}` LIKE '" . $conn->real_escape_string($col) . "'");
    return $r && $r->num_rows > 0;
}

function indexExists(mysqli $conn, string $table, string $index): bool
{
    $r = $conn->query("SHOW INDEX FROM `{$table}` WHERE Key_name = '" . $conn->real_escape_string($index) . "'");
    return $r && $r->num_rows > 0;
}

function addColumn(mysqli $conn, string $table, string $sql, string $label): void
{
    if ($conn->query($sql)) {
        echo "  + {$label}\n";
    } else {
        echo "  {$label}: " . $conn->error . "\n";
    }
}

$table = 'push_subscriptions';
$exists = $conn->query("SHOW TABLES LIKE '{$table}'");
if (!$exists || $exists->num_rows === 0) {
    echo "Table missing — run: php scripts/setup_push.php\n";
    exit(1);
}

echo "Repairing {$table}...\n";

@$conn->query("ALTER TABLE `{$table}` MODIFY `user_id` int NULL DEFAULT NULL");

if (!columnExists($conn, $table, 'scope')) {
    addColumn($conn, $table, "ALTER TABLE `{$table}` ADD COLUMN `scope` enum('announcements','full') NOT NULL DEFAULT 'announcements' AFTER `user_id`", 'scope');
}

if (!columnExists($conn, $table, 'endpoint_hash')) {
    $after = columnExists($conn, $table, 'scope') ? 'scope' : 'user_id';
    addColumn($conn, $table, "ALTER TABLE `{$table}` ADD COLUMN `endpoint_hash` char(64) NULL AFTER `{$after}`", 'endpoint_hash');
}

if (!columnExists($conn, $table, 'endpoint')) {
    addColumn($conn, $table, "ALTER TABLE `{$table}` ADD COLUMN `endpoint` text NOT NULL AFTER `endpoint_hash`", 'endpoint');
}

if (!columnExists($conn, $table, 'p256dh')) {
    addColumn($conn, $table, "ALTER TABLE `{$table}` ADD COLUMN `p256dh` varchar(255) NOT NULL DEFAULT '' AFTER `endpoint`", 'p256dh');
}

if (!columnExists($conn, $table, 'auth')) {
    addColumn($conn, $table, "ALTER TABLE `{$table}` ADD COLUMN `auth` varchar(255) NOT NULL DEFAULT '' AFTER `p256dh`", 'auth');
}

if (!columnExists($conn, $table, 'user_agent')) {
    addColumn($conn, $table, "ALTER TABLE `{$table}` ADD COLUMN `user_agent` varchar(512) DEFAULT NULL", 'user_agent');
}

if (columnExists($conn, $table, 'endpoint') && columnExists($conn, $table, 'endpoint_hash')) {
    $conn->query("UPDATE `{$table}` SET `endpoint_hash` = SHA2(`endpoint`, 256) WHERE (`endpoint_hash` IS NULL OR `endpoint_hash` = '') AND `endpoint` IS NOT NULL AND `endpoint` != ''");
    if ($conn->affected_rows > 0) {
        echo "  backfilled endpoint_hash ({$conn->affected_rows} rows)\n";
    }
    @$conn->query("ALTER TABLE `{$table}` MODIFY `endpoint_hash` char(64) NOT NULL");
}

if (!indexExists($conn, $table, 'uq_endpoint_hash') && columnExists($conn, $table, 'endpoint_hash')) {
    @$conn->query("ALTER TABLE `{$table}` ADD UNIQUE KEY `uq_endpoint_hash` (`endpoint_hash`)");
    echo "  + index uq_endpoint_hash\n";
}

// Old FCM schema — these must allow NULL or Web Push INSERT fails
$legacyNullable = [
    'fcm_token'   => 'text NULL DEFAULT NULL',
    'student_id'  => 'varchar(50) NULL DEFAULT NULL',
    'device_type' => 'varchar(50) NULL DEFAULT NULL',
];
foreach ($legacyNullable as $col => $def) {
    if (columnExists($conn, $table, $col)) {
        if (@$conn->query("ALTER TABLE `{$table}` MODIFY `{$col}` {$def}")) {
            echo "  relaxed legacy column: {$col}\n";
        } else {
            echo "  {$col}: " . $conn->error . "\n";
        }
    }
}

$required = ['endpoint_hash', 'endpoint', 'p256dh', 'auth', 'scope'];
$missing = array_filter($required, fn($c) => !columnExists($conn, $table, $c));
if (!empty($missing)) {
    echo "\nStill missing: " . implode(', ', $missing) . "\n";
    echo "If this is an old FCM-only table, run in phpMyAdmin:\n";
    echo "  DROP TABLE push_subscriptions;\n";
    echo "Then: php scripts/setup_push.php\n";
    exit(1);
}

echo "\nColumns OK:\n";
$cols = $conn->query("SHOW COLUMNS FROM `{$table}`");
while ($c = $cols->fetch_assoc()) {
    echo "  - {$c['Field']}\n";
}
echo "\nDone. Retry Enable on your phone.\n";
