<?php

error_reporting(E_ALL);
$isProduction = false;
ini_set('display_errors', $isProduction ? 0 : 1);
ini_set('log_errors', 1);

// Clean output buffer
while (ob_get_level() > 0) {
    ob_end_clean();
}
ob_start();

require_once __DIR__ . '/../app/core/Model.php';
require_once __DIR__ . '/../app/core/Controller.php';
require_once __DIR__ . '/../app/models/ViolationModel.php';
require_once __DIR__ . '/../app/controllers/ViolationController.php';

try {
    $controller = new ViolationController();
    $method = $_SERVER['REQUEST_METHOD'];

    switch ($method) {
        case 'GET':
            $controller->index();
            break;

        case 'POST':
            // Check for action in query string even for POST
            $action = $_GET['action'] ?? '';
            if (in_array($action, [
                'archive', 'request_slip', 'approve_slip', 'deny_slip',
                'create_type', 'update_type', 'delete_type', 'restore_type',
                'create_level', 'update_level', 'delete_level', 'restore_level',
                'create_status', 'update_status', 'delete_status', 'restore_status',
                'mark_as_read', 'mark_all_read'
            ], true)) {
                $controller->index();
            } else {
                $controller->create();
            }
            break;

        case 'PUT':
            $action = $_GET['action'] ?? '';
            if (in_array($action, ['update_type', 'update_level', 'update_status'], true)) {
                $controller->index();
            } else {
                $controller->update();
            }
            break;

        case 'DELETE':
            $action = $_GET['action'] ?? '';
            if (in_array($action, ['delete_type', 'delete_level', 'delete_status'], true)) {
                $controller->index();
            } else {
                $controller->delete();
            }
            break;

        default:
            header('Content-Type: application/json');
            http_response_code(405);
            echo json_encode([
                'status' => 'error',
                'message' => 'Method not allowed',
                'data' => []
            ]);
            exit;
    }

} catch (Throwable $e) {
    while (ob_get_level() > 0) {
        ob_end_clean();
    }

    header('Content-Type: application/json');
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Server error',
        'error' => $e->getMessage()
    ]);
    exit;
}
