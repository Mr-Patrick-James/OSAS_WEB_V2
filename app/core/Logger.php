<?php
require_once __DIR__ . '/../models/SystemLogModel.php';

class Logger {
    private static $model;

    private static function getModel() {
        if (!self::$model) {
            try {
                self::$model = new SystemLogModel();
            } catch (Exception $e) {
                error_log("Logger Error: " . $e->getMessage());
                return null;
            }
        }
        return self::$model;
    }

    public static function log($action, $details = null) {
        // Start session if not started
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }

        $userId = $_SESSION['user_id'] ?? 0;
        $username = $_SESSION['username'] ?? 'guest';
        
        $model = self::getModel();
        if ($model) {
            return $model->log($userId, $username, $action, $details);
        }
        return false;
    }
}
