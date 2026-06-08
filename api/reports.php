<?php
/**
 * Reports API Endpoint
 * Routes to MVC Controller
 */

// Error reporting
error_reporting(E_ALL);
$isProduction = false;
ini_set('display_errors', $isProduction ? 0 : 1);
ini_set('log_errors', 1);

// Start output buffering to catch any errors/warnings
while (ob_get_level() > 0) {
    ob_end_clean();
}
ob_start();

require_once __DIR__ . '/../app/core/Model.php';
require_once __DIR__ . '/../app/core/Controller.php';
require_once __DIR__ . '/../app/models/ReportModel.php';
require_once __DIR__ . '/../app/controllers/ReportController.php';

try {
    $controller = new ReportController();
    $method = $_SERVER['REQUEST_METHOD'];

    switch ($method) {
        case 'GET':
            $controller->index();
            break;
        default:
            // Output error JSON directly
            while (ob_get_level() > 0) {
                ob_end_clean();
            }
            header('Content-Type: application/json');
            http_response_code(405);
            echo json_encode([
                'status' => 'error',
                'message' => 'Method not allowed. Only GET requests are supported.',
                'data' => []
            ]);
            exit;
            break;
    }
} catch (Throwable $e) {
    // Catch any unhandled exceptions
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    
    error_log("Reports API Error: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    
    header('Content-Type: application/json');
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Server error: ' . $e->getMessage(),
        'data' => [],
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ]);
    exit;
}

