<?php
/**
 * Base Controller Class
 */
class Controller {
    
    /**
     * Load a view
     */
    protected function view($viewName, $data = [], $layout = null) {
        require_once __DIR__ . '/View.php';
        View::render($viewName, $data, $layout);
    }
    
    /**
     * Include a partial
     */
    protected function partial($partialName, $data = []) {
        require_once __DIR__ . '/View.php';
        View::partial($partialName, $data);
    }

    /**
     * Return JSON response
     */
    protected function json($data, $statusCode = 200) {
        // Clear all output buffers
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        
        // Set headers (only if not already sent)
        if (!headers_sent()) {
            http_response_code($statusCode);
            header('Content-Type: application/json; charset=utf-8');
        } else {
            http_response_code($statusCode);
        }
        
        // Encode and output JSON
        $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        
        // Check for JSON encoding errors
        if ($json === false) {
            $error = json_last_error_msg();
            error_log("JSON encoding error: " . $error);
            if (!headers_sent()) {
                http_response_code(500);
            }
            echo json_encode([
                'status' => 'error',
                'message' => 'Failed to encode response: ' . $error
            ]);
        } else {
            echo $json;
        }
        
        exit;
    }

    /**
     * Return JSON success response
     */
    protected function success($message, $data = [], $statusCode = 200) {
        $this->json([
            'status' => 'success',
            'message' => $message,
            'data' => $data
        ], $statusCode);
    }

    /**
     * Return JSON error response
     */
    protected function error($message, $help = '', $statusCode = 400) {
        $this->json([
            'status' => 'error',
            'message' => $message,
            'data' => [],
            'help' => $help
        ], $statusCode);
    }

    /**
     * Check if user is logged in
     */
    protected function requireAuth() {
        session_start();
        if (!isset($_SESSION['user_id'])) {
            $this->error('Authentication required', 'Please login first', 401);
        }
    }

    /**
     * Check if user has admin role
     */
    protected function requireAdmin() {
        $this->requireAuth();
        if (!in_array($_SESSION['role'] ?? '', ['admin', 'OSAS Staff', 'CSC Officer', 'Officer', 'Faculty Member'])) {
            $this->error('Access denied', 'Admin privileges required', 403);
        }
    }

    /**
     * Get POST data
     */
    protected function getPost($key = null, $default = null) {
        if ($key === null) {
            return $_POST;
        }
        return $_POST[$key] ?? $default;
    }

    /**
     * Get GET data
     */
    protected function getGet($key = null, $default = null) {
        if ($key === null) {
            return $_GET;
        }
        return $_GET[$key] ?? $default;
    }

    /**
     * Sanitize input
     */
    protected function sanitize($input) {
        return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
    }
}

