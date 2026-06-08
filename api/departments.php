<?php
/**
 * API Wrapper - Maintains backward compatibility
 * Routes to MVC Controller
 */
require_once __DIR__ . '/../app/core/Model.php';
require_once __DIR__ . '/../app/core/Controller.php';
require_once __DIR__ . '/../app/models/DepartmentModel.php';
require_once __DIR__ . '/../app/controllers/DepartmentController.php';

$controller = new DepartmentController();
$action = $_GET['action'] ?? '';

switch ($action) {
    case 'get':
        $controller->index();
        break;
    case 'stats':
        $controller->stats();
        break;
    case 'add':
        $controller->create();
        break;
    case 'update':
        $controller->update();
        break;
    case 'delete':
    case 'archive':
        $controller->delete();
        break;
    case 'restore':
        $controller->restore();
        break;
    default:
        // Default to dropdown for backward compatibility
        if ($_SERVER['REQUEST_METHOD'] === 'GET' && empty($action)) {
            $controller->dropdown();
        } elseif ($_SERVER['REQUEST_METHOD'] === 'GET') {
            $controller->index();
        } elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $controller->create();
        }
        break;
}
