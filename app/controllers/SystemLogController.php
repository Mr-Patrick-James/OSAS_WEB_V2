<?php
require_once __DIR__ . '/../core/Controller.php';
require_once __DIR__ . '/../models/SystemLogModel.php';

class SystemLogController extends Controller {
    private $model;

    public function __construct() {
        ob_start();
        header('Content-Type: application/json');
        
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }
        
        try {
            $this->model = new SystemLogModel();
        } catch (Exception $e) {
            $this->error('Failed to initialize logs: ' . $e->getMessage());
        }
    }

    public function listLogs() {
        try {
            $this->requireAdmin();
            
            // Check if user is main admin
            $mainAdmins = ['admin_demo', 'adminOsas@colegio.edu', 'adminOsas'];
            $username = $_SESSION['username'] ?? '';
            
            if (!in_array($username, $mainAdmins)) {
                $this->error('Access denied', 'Only main admins can view system logs', 403);
            }

            $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 100;
            $offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;

            $logs = $this->model->getLogs($limit, $offset);
            
            $this->success('System logs retrieved successfully', ['logs' => $logs]);
        } catch (Exception $e) {
            $this->error('Failed to load system logs: ' . $e->getMessage());
        }
    }
}
