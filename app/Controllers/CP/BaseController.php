<?php
declare(strict_types=1);

namespace App\Controllers\CP;

/**
 * Lightweight base controller for CP (owner area).
 * - No sidenav
 * - Renders via CP shell with $slot
 */
abstract class BaseController
{
    /**
     * Render a CP view inside the CP shell.
     *
     * @param string $view e.g. 'cp/dashboard/index'
     * @param array  $data variables for the view
     */
    protected function render(string $view, array $data = []): void
    {
        // Resolve paths (adjust if your Views folder is different)
        $root      = dirname(__DIR__, 2); // /app
        $viewsRoot = $root . '/Views';
        $viewPath  = $viewsRoot . '/' . ltrim($view, '/') . '.php';
        $shellPath = $viewsRoot . '/cp/layouts/shell.php';

        if (!is_file($viewPath)) {
            http_response_code(500);
            echo "CP view not found: " . htmlspecialchars($viewPath, ENT_QUOTES, 'UTF-8');
            return;
        }
        if (!is_file($shellPath)) {
            http_response_code(500);
            echo "CP shell not found: " . htmlspecialchars($shellPath, ENT_QUOTES, 'UTF-8');
            return;
        }

        // Make $data variables available to the view
        $ctx = $data;

        // Render inner view into $slot
        $slot = (function (string $__viewPath, array $__vars) {
            extract($__vars, EXTR_SKIP);
            ob_start();
            require $__viewPath;
            return ob_get_clean();
        })($viewPath, $ctx);

        // Title (optional)
        $title = $ctx['title'] ?? 'Control Panel';

        // Important: CP shell **must not** include any sidenav
        require $shellPath;
    }
}