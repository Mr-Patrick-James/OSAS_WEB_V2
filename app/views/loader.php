<?php
/**
 * View Loader
 * Loads views from app/views/ directory
 * Usage: app/views/loader.php?view=admin/dashcontent
 */

// Start output buffering
ob_start();

// Error reporting
error_reporting(E_ALL);
$isProduction = false;
ini_set('display_errors', $isProduction ? 0 : 1);
ini_set('log_errors', 1);

// Get view path from query parameter
$viewPath = $_GET['view'] ?? '';

if (empty($viewPath)) {
    http_response_code(404);
    echo "View path not specified";
    exit;
}

// Security: Prevent directory traversal
$viewPath = str_replace(['..', '\\'], '', $viewPath);
$viewPath = trim($viewPath, '/');

// Map old paths to new view paths for backward compatibility
$pathMap = [
    'admin_page/dashcontent' => 'admin/dashcontent',
    'admin_page/Department' => 'admin/department',
    'admin_page/Sections' => 'admin/sections',
    'admin_page/Students' => 'admin/students',
    'admin_page/Violations' => 'admin/violations',
    'admin_page/Reports' => 'admin/reports',
    'admin_page/Announcements' => 'admin/Announcements',
    'admin_page/settings' => 'admin/settings',
    'admin_page/Settings' => 'admin/settings',
    'user-page/user_dashcontent' => 'user/dashcontent',
    'user-page/my_violations' => 'user/my_violations',
    'user-page/announcements' => 'user/announcements',
];

// Check if we need to map the path
if (isset($pathMap[$viewPath])) {
    $viewPath = $pathMap[$viewPath];
}

// Build full path to view file
$viewFile = __DIR__ . '/' . $viewPath . '.php';

// Check if view file exists
if (!file_exists($viewFile)) {
    http_response_code(404);
    echo "View not found: $viewPath (tried: $viewFile)";
    exit;
}

// Debug: Log which file is being loaded
error_log("Loading view: $viewPath from file: $viewFile");

// Load the view
require_once __DIR__ . '/../core/View.php';
require $viewFile;
