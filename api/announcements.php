<?php
/**
 * API Wrapper - Announcements
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

// Don't start output buffer here - let controller's json() method handle it
// The controller's json() method will set headers and output JSON

try {
    require_once __DIR__ . '/../app/core/Model.php';
    require_once __DIR__ . '/../app/core/Controller.php';
    require_once __DIR__ . '/../app/models/AnnouncementModel.php';
    require_once __DIR__ . '/../app/controllers/AnnouncementController.php';

    $controller = new AnnouncementController();
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
    error_log("Announcements API initialization error: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine());
    exit;
}

try {
    // Controller methods handle their own output and exit
    // We don't need to capture output here since json() method exits
    switch ($action) {
        case 'get':
            if ($method === 'GET') {
                if (!empty($_GET['id'])) {
                    $controller->show();
                } else {
                    $controller->index();
                }
            } else {
                $controller->create();
            }
            break;
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
        case 'archive':
            $controller->archive();
            break;
        case 'restore':
            $controller->restore();
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
    // This means json() wasn't called or didn't exit properly
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
    error_log("Announcements API error: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine());
    error_log("Stack trace: " . $e->getTraceAsString());
    exit;
}

