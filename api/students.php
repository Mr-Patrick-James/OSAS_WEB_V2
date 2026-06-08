<?php
/**
 * API Wrapper - Maintains backward compatibility
 * Routes to MVC Controller
 */
require_once __DIR__ . '/../app/core/Model.php';
require_once __DIR__ . '/../app/core/Controller.php';
require_once __DIR__ . '/../app/models/StudentModel.php';
require_once __DIR__ . '/../app/controllers/StudentController.php';

$controller = new StudentController();
$action = $_GET['action'] ?? '';

switch ($action) {
    case 'get':
    case '':
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
        $controller->delete();
        break;
    case 'restore':
        $controller->restore();
        break;
    case 'import':
        $controller->import();
        break;
    case 'listAssets':
        $controller->listAssets();
        break;
    case 'importFromAsset':
        $controller->importFromAsset();
        break;
    case 'deleteAll':
        $controller->deleteAll();
        break;
    default:
        if ($_SERVER['REQUEST_METHOD'] === 'GET') {
            $controller->index();
        } elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $controller->create();
        }
        break;
}
