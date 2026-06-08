<?php
/**
 * API Wrapper - Dashcontents
 * Routes to MVC Controller
 */

// Error handling - set first
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Clear any previous output
while (ob_get_level() > 0) {
    ob_end_clean();
}

try {
    require_once __DIR__ . '/../app/core/Model.php';
    require_once __DIR__ . '/../app/core/Controller.php';
    require_once __DIR__ . '/../app/models/DashcontentModel.php';
    require_once __DIR__ . '/../app/controllers/DashcontentController.php';

    $controller = new DashcontentController();
    $action = $_GET['action'] ?? '';
    $method = $_SERVER['REQUEST_METHOD'];
} catch (Throwable $e) {
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'status' => 'error',
        'message' => 'Failed to initialize: ' . $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'data' => []
    ]);
    error_log("Dashcontents API initialization error: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine());
    exit;
}

try {
    // Controller methods handle their own output and exit
    switch ($action) {
        case 'get':
        case '':
            if ($method === 'GET') {
                $controller->index();
            } else {
                $controller->create();
            }
            break;
        case 'active':
            $controller->getActive();
            break;
        case 'add':
        case 'create':
            $controller->create();
            break;
        case 'update':
        case 'edit':
            $controller->update();
            break;
        case 'delete':
            $controller->delete();
            break;
        default:
            if ($method === 'GET') {
                $controller->index();
            } elseif ($method === 'POST') {
                $controller->create();
            } elseif ($method === 'PUT') {
                $controller->update();
            } elseif ($method === 'DELETE') {
                $controller->delete();
            }
            break;
    }
    
    // If we get here, the controller method didn't exit (shouldn't happen)
    ob_end_clean();
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Controller method completed without output',
        'data' => []
    ]);
} catch (Throwable $e) {
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'status' => 'error',
        'message' => 'API Error: ' . $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'data' => []
    ]);
    error_log("Dashcontents API error: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine());
    error_log("Stack trace: " . $e->getTraceAsString());
    exit;
}

