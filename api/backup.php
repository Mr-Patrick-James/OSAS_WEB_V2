<?php
session_start();

// Check if user is logged in and is an admin
// Adjust role check based on your actual role values (e.g., 'Admin', 'admin', 'OSAS Staff' if allowed)
// Based on dashboard.js, roles can be 'Admin', 'OSAS Staff', etc.
// Usually only super admins should export DB.
// Let's assume 'admin' or 'Admin' is required.
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    header('HTTP/1.1 403 Forbidden');
    die('Access denied. Login required.');
}

// You might want to restrict this to only 'admin' role
$allowed_roles = ['admin', 'Admin'];
if (!in_array($_SESSION['role'], $allowed_roles)) {
    // If strict admin only
    // header('HTTP/1.1 403 Forbidden');
    // die('Access denied. Admin privileges required.');
}

require_once __DIR__ . '/../config/db_connect.php';

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Set headers for download
$filename = 'osas_backup_' . date('Y-m-d_H-i-s') . '.sql';
header('Content-Type: application/sql');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Pragma: no-cache');
header('Expires: 0');

// Disable output buffering to save memory
if (ob_get_level()) {
    ob_end_clean();
}

echo "-- OSAS Database Backup\n";
echo "-- Generated: " . date('Y-m-d H:i:s') . "\n";
echo "-- Host: " . $host . "\n";
echo "-- Database: " . $dbname . "\n\n";
echo "SET FOREIGN_KEY_CHECKS=0;\n";
echo "SET SQL_MODE = \"NO_AUTO_VALUE_ON_ZERO\";\n";
echo "SET time_zone = \"+00:00\";\n\n";

// Get all tables
$tables = [];
$result = $conn->query("SHOW TABLES");
while ($row = $result->fetch_row()) {
    $tables[] = $row[0];
}

foreach ($tables as $table) {
    echo "\n-- --------------------------------------------------------\n";
    echo "-- Structure for table `$table`\n--\n\n";
    
    echo "DROP TABLE IF EXISTS `$table`;\n";
    
    $result = $conn->query("SHOW CREATE TABLE `$table`");
    $row = $result->fetch_row();
    echo $row[1] . ";\n\n";
    
    // Data
    echo "-- Dumping data for table `$table`\n--\n\n";
    
    $result = $conn->query("SELECT * FROM `$table`");
    $num_fields = $result->field_count;
    
    if ($result->num_rows > 0) {
        $first = true;
        $batchSize = 100;
        $count = 0;
        
        while ($row = $result->fetch_row()) {
            if ($count % $batchSize == 0) {
                if ($count > 0) {
                    echo ";\n";
                }
                echo "INSERT INTO `$table` VALUES ";
                $first = true;
            }
            
            if (!$first) {
                echo ", \n";
            }
            
            echo "(";
            for ($j = 0; $j < $num_fields; $j++) {
                if (is_null($row[$j])) {
                    echo "NULL";
                } else {
                    $row[$j] = $conn->real_escape_string($row[$j]);
                    // Handle newlines in data
                    $row[$j] = str_replace("\n", "\\n", $row[$j]);
                    $row[$j] = str_replace("\r", "\\r", $row[$j]);
                    echo '"' . $row[$j] . '"';
                }
                if ($j < ($num_fields - 1)) {
                    echo ",";
                }
            }
            echo ")";
            
            $first = false;
            $count++;
        }
        echo ";\n";
    }
}

echo "\nSET FOREIGN_KEY_CHECKS=1;\n";
exit;
?>