<?php
/**
 * Run migrations/alter_violations_location.sql on local DB.
 * Usage: php scripts/run_location_migration.php
 */
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

require_once __DIR__ . '/../config/db_connect.php';

if (!isset($conn) || $conn->connect_error) {
    fwrite(STDERR, 'DB connection failed: ' . ($conn->connect_error ?? 'unknown') . "\n");
    exit(1);
}

$sqlFile = __DIR__ . '/../migrations/alter_violations_location.sql';
if (!file_exists($sqlFile)) {
    fwrite(STDERR, "Migration file not found: $sqlFile\n");
    exit(1);
}

$sql = file_get_contents($sqlFile);
// Strip comments and split on semicolons
$statements = array_filter(array_map('trim', preg_split('/;\s*\n/', $sql)), function ($s) {
    $s = trim($s);
    return $s !== '' && !preg_match('/^--/', $s);
});

echo "Database: {$dbname}\n";

foreach ($statements as $stmt) {
    $preview = preg_replace('/\s+/', ' ', substr($stmt, 0, 80));
    echo "Running: {$preview}...\n";
    $conn->query($stmt);
    echo "  OK (affected rows: {$conn->affected_rows})\n";
}

$r = $conn->query("SHOW COLUMNS FROM violations LIKE 'location'");
$row = $r->fetch_assoc();
echo "\nlocation column: {$row['Type']}\n";
echo "Default: {$row['Default']}\n";
echo "Migration completed successfully.\n";
