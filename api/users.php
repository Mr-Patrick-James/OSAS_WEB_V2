<?php
// Prevent any unwanted output
ob_start();

require_once __DIR__ . '/../app/core/Model.php';
require_once __DIR__ . '/../app/core/Controller.php';
require_once __DIR__ . '/../app/models/UserModel.php';
require_once __DIR__ . '/../app/controllers/UserController.php';

// Clean any output from includes
while (ob_get_level() > 0) {
    ob_end_clean();
}

try {
    $controller = new UserController();
    $action = $_GET['action'] ?? '';
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

    if ($method === 'GET') {
        switch ($action) {
            case 'admins':
            case '':
                $controller->listAdmins();
                break;
            case 'users':
                $controller->listUsers();
                break;
            case 'archived':
                $controller->listArchived();
                break;
            case 'profile':
                $controller->getProfile();
                break;
            default:
                throw new Exception('Invalid GET action');
        }
    } elseif ($method === 'POST') {
        switch ($action) {
            case 'addAdmin':
                $controller->createAdmin();
                break;
            case 'deleteAdmin':
                $controller->deleteAdmin();
                break;
            case 'deleteUser':
                $controller->deleteUser();
                break;
            case 'restoreUser':
                $controller->restoreUser();
                break;
            case 'permanentDelete':
                $controller->permanentDelete();
                break;
            case 'updateStatus':
                $controller->updateStatus();
                break;
            case 'resetPassword':
                $controller->resetPassword();
                break;
            case 'updateProfile':
                $controller->updateProfile();
                break;
            default:
                throw new Exception('Invalid POST action');
        }
    } else {
        header('Content-Type: application/json');
        http_response_code(405);
        echo json_encode(['status' => 'error', 'message' => 'Method not allowed']);
    }
} catch (Exception $e) {
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    header('Content-Type: application/json');
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
exit;
