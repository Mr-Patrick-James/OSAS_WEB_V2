<?php
/**
 * TEST SCRIPT: Simulate Monthly Reset
 * 
 * This archives ALL current violations (simulating what happens when a new month starts).
 * Use ?action=reset to archive, ?action=undo to restore.
 * 
 * DELETE THIS FILE AFTER TESTING.
 */

session_start();
require_once __DIR__ . '/../app/config/db_connect.php';

// Only allow admin
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    die('Admin access required. Please login first.');
}

$action = $_GET['action'] ?? '';

header('Content-Type: text/html; charset=utf-8');
echo "<h2>🧪 Monthly Reset Test Tool</h2>";
echo "<p><strong>Current date:</strong> " . date('Y-m-d H:i:s') . "</p>";

if ($action === 'reset') {
    // Archive ALL non-archived violations (simulates new month)
    $stmt = $conn->prepare("UPDATE violations SET is_archived = 1 WHERE is_archived = 0");
    $stmt->execute();
    $affected = $stmt->affected_rows;
    $stmt->close();
    
    echo "<p style='color:green;font-size:1.2em;'>✅ Archived <strong>$affected</strong> violations (simulating new month reset).</p>";
    echo "<p>Now go check:</p>";
    echo "<ul>";
    echo "<li><strong>Admin Violations page</strong> — should show 0 active violations</li>";
    echo "<li><strong>User Dashboard</strong> — Total card should still show all-time count, Permitted/Warning = 0</li>";
    echo "<li><strong>User My Violations</strong> — 'This Month' = empty, 'All History' = shows archived records</li>";
    echo "</ul>";
    echo "<br><a href='?action=undo' style='background:#ef4444;color:white;padding:10px 20px;border-radius:8px;text-decoration:none;'>↩️ Undo (Restore All)</a>";
    
} elseif ($action === 'undo') {
    // Restore ALL archived violations back to active
    $stmt = $conn->prepare("UPDATE violations SET is_archived = 0 WHERE is_archived = 1");
    $stmt->execute();
    $affected = $stmt->affected_rows;
    $stmt->close();
    
    echo "<p style='color:blue;font-size:1.2em;'>↩️ Restored <strong>$affected</strong> violations back to active.</p>";
    echo "<p>Everything is back to normal.</p>";
    echo "<br><a href='?action=reset' style='background:#10b981;color:white;padding:10px 20px;border-radius:8px;text-decoration:none;'>🔄 Simulate Reset Again</a>";
    
} else {
    // Show current status
    $result = $conn->query("SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN is_archived = 0 THEN 1 ELSE 0 END) as active,
        SUM(CASE WHEN is_archived = 1 THEN 1 ELSE 0 END) as archived
        FROM violations");
    $row = $result->fetch_assoc();
    
    echo "<table border='1' cellpadding='10' style='border-collapse:collapse;margin:20px 0;'>";
    echo "<tr><th>Total in DB</th><th>Active (is_archived=0)</th><th>Archived (is_archived=1)</th></tr>";
    echo "<tr><td>{$row['total']}</td><td><strong>{$row['active']}</strong></td><td>{$row['archived']}</td></tr>";
    echo "</table>";
    
    echo "<p>Choose an action:</p>";
    echo "<a href='?action=reset' style='background:#10b981;color:white;padding:10px 20px;border-radius:8px;text-decoration:none;margin-right:10px;'>🔄 Simulate Monthly Reset</a>";
    echo "<a href='?action=undo' style='background:#3b82f6;color:white;padding:10px 20px;border-radius:8px;text-decoration:none;'>↩️ Undo / Restore All</a>";
}

echo "<hr style='margin-top:30px;'>";
echo "<p style='color:#666;font-size:0.85em;'>⚠️ Delete this file after testing: <code>scripts/test_monthly_reset.php</code></p>";
?>
