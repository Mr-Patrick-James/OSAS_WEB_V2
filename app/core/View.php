<?php
/**
 * View Helper Class
 * Works on both:
 *   - AWS root:      https://eosas.duckdns.org/  (SCRIPT_NAME = /includes/dashboard.php)
 *   - Local subfolder: http://localhost/OSAS_WEB/ (SCRIPT_NAME = /OSAS_WEB/includes/dashboard.php)
 */
class View {
    private static $basePath;
    private static $assetPath;

    public static function init() {
        self::$basePath  = dirname(__DIR__) . '/views';
        self::$assetPath = dirname(dirname(__DIR__)) . '/assets';
    }

    /**
     * Detect the URL prefix for the project root.
     * Returns '' when deployed at server root, '/SUBFOLDER' when in a subfolder.
     * Never returns an app-internal directory like /app or /includes.
     */
    private static function getPrefix(): string {
        // Directories that are part of the app structure, NOT a subfolder name
        $appDirs = ['app', 'api', 'includes', 'assets', 'public', 'index.php'];

        $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
        if ($scriptName && $scriptName !== '/') {
            $parts = explode('/', trim($scriptName, '/'));
            if (!empty($parts[0]) && !in_array($parts[0], $appDirs)) {
                return '/' . $parts[0];
            }
        }
        return '';
    }

    /** URL to an asset file, e.g. View::asset('styles/dashboard.css') */
    public static function asset(string $path): string {
        $path = ltrim($path, '/');
        // Strip redundant prefixes
        if (str_starts_with($path, 'app/assets/'))  $path = substr($path, 11);
        elseif (str_starts_with($path, 'assets/'))  $path = substr($path, 7);
        return self::getPrefix() . '/app/assets/' . $path;
    }

    /** Absolute URL from project root, e.g. View::url('api/students.php') */
    public static function url(string $path): string {
        return self::getPrefix() . '/' . ltrim($path, '/');
    }

    /** Base URL of the project root */
    public static function baseUrl(string $path = ''): string {
        $base = self::getPrefix();
        return $path === '' ? ($base ?: '/') : $base . '/' . ltrim($path, '/');
    }

    /** Include a partial view */
    public static function partial(string $partialName, array $data = []): void {
        extract($data);
        $partialFile = self::$basePath . '/partials/' . $partialName . '.php';
        if (!file_exists($partialFile)) {
            error_log("Partial not found: $partialName");
            return;
        }
        require $partialFile;
    }

    /** Render a view with optional layout */
    public static function render(string $viewName, array $data = [], ?string $layout = null): void {
        extract($data);
        ob_start();
        $viewFile = self::$basePath . '/' . $viewName . '.php';
        if (!file_exists($viewFile)) die("View not found: $viewName");
        require $viewFile;
        $content = ob_get_clean();
        if ($layout) {
            $layoutFile = self::$basePath . '/layouts/' . $layout . '.php';
            if (!file_exists($layoutFile)) die("Layout not found: $layout");
            require $layoutFile;
        } else {
            echo $content;
        }
    }

    public static function e(string $string): string {
        return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
    }
}

View::init();
