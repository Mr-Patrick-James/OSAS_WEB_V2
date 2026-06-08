<?php
/**
 * API Wrapper - Maintains backward compatibility
 * Routes to MVC Controller
 */
require_once __DIR__ . '/../app/core/Model.php';
require_once __DIR__ . '/../app/core/Controller.php';
require_once __DIR__ . '/../app/models/SectionModel.php';
require_once __DIR__ . '/../app/controllers/SectionController.php';

$controller = new SectionController();
$action = $_GET['action'] ?? '';

switch ($action) {
    case 'get':
    case '':
        $controller->index();
        break;
    case 'getByDepartment':
        $controller->getByDepartment();
        break;
    case 'add':
        $controller->create();
        break;
    case 'update':
        $controller->update();
        break;
    case 'archive':
        $controller->delete();
        break;
    case 'delete':
        $controller->destroy();
        break;
    case 'restore':
        $controller->restore();
        break;
    case 'stats':
        $controller->stats();
        break;
    default:
        if ($_SERVER['REQUEST_METHOD'] === 'GET') {
            $controller->index();
        } elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $controller->create();
        }
        break;
}
