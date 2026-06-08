<?php
/**
 * Simple Router Class
 */
class Router {
    private $routes = [];
    private $middlewares = [];

    /**
     * Add a route
     */
    public function add($method, $path, $handler) {
        $this->routes[] = [
            'method' => $method,
            'path' => $path,
            'handler' => $handler
        ];
    }

    /**
     * Add GET route
     */
    public function get($path, $handler) {
        $this->add('GET', $path, $handler);
    }

    /**
     * Add POST route
     */
    public function post($path, $handler) {
        $this->add('POST', $path, $handler);
    }

    /**
     * Add PUT route
     */
    public function put($path, $handler) {
        $this->add('PUT', $path, $handler);
    }

    /**
     * Add DELETE route
     */
    public function delete($path, $handler) {
        $this->add('DELETE', $path, $handler);
    }

    /**
     * Dispatch the request
     */
    public function dispatch() {
        $method = $_SERVER['REQUEST_METHOD'];
        $path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        
        // Remove base path if exists
        $basePath = str_replace($_SERVER['DOCUMENT_ROOT'], '', __DIR__ . '/../..');
        $basePath = str_replace('\\', '/', $basePath);
        if (strpos($path, $basePath) === 0) {
            $path = substr($path, strlen($basePath));
        }
        
        // Remove leading/trailing slashes
        $path = trim($path, '/');
        if (empty($path)) {
            $path = '/';
        } else {
            $path = '/' . $path;
        }

        foreach ($this->routes as $route) {
            if ($route['method'] === $method && $this->matchPath($route['path'], $path)) {
                $handler = $route['handler'];
                
                // Extract parameters
                $params = $this->extractParams($route['path'], $path);
                
                // Call handler
                if (is_array($handler) && count($handler) === 2) {
                    $controller = new $handler[0]();
                    $method = $handler[1];
                    call_user_func_array([$controller, $method], $params);
                } else if (is_callable($handler)) {
                    call_user_func_array($handler, $params);
                }
                
                return;
            }
        }

        // 404 Not Found
        http_response_code(404);
        header('Content-Type: application/json');
        echo json_encode([
            'status' => 'error',
            'message' => 'Route not found',
            'path' => $path
        ]);
    }

    /**
     * Match path with route pattern
     */
    private function matchPath($pattern, $path) {
        // Convert route pattern to regex
        $pattern = preg_replace('/\{(\w+)\}/', '([^/]+)', $pattern);
        $pattern = '#^' . $pattern . '$#';
        
        return preg_match($pattern, $path);
    }

    /**
     * Extract parameters from path
     */
    private function extractParams($pattern, $path) {
        $params = [];
        $patternParts = explode('/', trim($pattern, '/'));
        $pathParts = explode('/', trim($path, '/'));
        
        foreach ($patternParts as $index => $part) {
            if (preg_match('/\{(\w+)\}/', $part, $matches)) {
                $paramName = $matches[1];
                $params[$paramName] = $pathParts[$index] ?? null;
            }
        }
        
        return array_values($params);
    }
}

