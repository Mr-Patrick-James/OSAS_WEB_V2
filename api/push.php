<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);
while (ob_get_level() > 0) ob_end_clean();

require_once __DIR__ . '/../app/core/Model.php';
require_once __DIR__ . '/../app/core/Controller.php';
require_once __DIR__ . '/../app/controllers/PushController.php';

try {
    $c = new PushController();
    $action = $_GET['action'] ?? '';
    switch ($action) {
        case 'vapid':
        case 'vapid-key':
            $c->vapidPublicKey();
            break;
        case 'subscribe':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); exit; }
            $c->subscribe();
            break;
        case 'upgrade':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); exit; }
            $c->upgrade();
            break;
        case 'unsubscribe':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); exit; }
            $c->unsubscribe();
            break;
        default:
            http_response_code(400);
            header('Content-Type: application/json');
            echo json_encode(['status' => 'error', 'message' => 'Unknown action']);
    }
} catch (Throwable $e) {
    header('Content-Type: application/json');
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
