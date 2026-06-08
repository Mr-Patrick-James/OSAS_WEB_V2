<?php
/**
 * Session Manager
 * Handles session configuration and initialization
 * Fixes WAMP permission issues by using project-specific session directory
 */

class Session {
    private static $initialized = false;
    
    /**
     * Initialize session with proper configuration
     */
    public static function start() {
        if (self::$initialized) {
            return;
        }
        
        // Set custom session save path (project-specific directory)
        $sessionPath = __DIR__ . '/../../storage/sessions';
        
        // Create directory if it doesn't exist
        if (!file_exists($sessionPath)) {
            @mkdir($sessionPath, 0755, true);
        }
        
        // Set session save path if directory exists and is writable
        if (file_exists($sessionPath) && is_writable($sessionPath)) {
            ini_set('session.save_path', $sessionPath);
        }
        
        // Additional session configuration
        ini_set('session.gc_maxlifetime', 86400); // 24 hours
        ini_set('session.cookie_httponly', 1);
        ini_set('session.use_only_cookies', 1);
        
        // Start session (suppress warnings if directory issues persist)
        if (!session_id()) {
            @session_start();
        }
        
        self::$initialized = true;
    }
    
    /**
     * Get session value
     */
    public static function get($key, $default = null) {
        self::start();
        return $_SESSION[$key] ?? $default;
    }
    
    /**
     * Set session value
     */
    public static function set($key, $value) {
        self::start();
        $_SESSION[$key] = $value;
    }
    
    /**
     * Check if session key exists
     */
    public static function has($key) {
        self::start();
        return isset($_SESSION[$key]);
    }
    
    /**
     * Remove session value
     */
    public static function remove($key) {
        self::start();
        unset($_SESSION[$key]);
    }
    
    /**
     * Destroy session
     */
    public static function destroy() {
        self::start();
        $_SESSION = [];
        if (session_id()) {
            @session_destroy();
        }
        self::$initialized = false;
    }
}

