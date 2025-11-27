<?php
declare(strict_types=1);

namespace Shared;

final class Router
{
    /**
     * Dispatch an array of routes for a request method+path.
     *
     * Route shape:
     *   ['GET','/path', callable|[ClassName::class,'method'], ['auth','csrf']]
     */
    public static function dispatch(array $routes, string $method, string $path): void
    {
        $method = strtoupper($method);
        $path   = self::normalizePath($path);

        foreach ($routes as $r) {
            if (!is_array($r) || count($r) < 3) continue;
            [$m, $p, $h] = $r;
            $m = strtoupper((string)$m);
            $p = self::normalizePath((string)$p);
            $mw = $r[3] ?? [];

            if ($m !== $method || $p !== $path) continue;

            // Middleware gates
            if (!self::runMiddleware($mw)) return;

            self::invoke($h);
            return;
        }

        // 404
        http_response_code(404);
        echo "404 Not Found";
    }

    /** Call the handler: closure or [Class,'method'] */
    public static function invoke($handler): void
    {
        if (is_callable($handler)) {
            $handler();
            return;
        }

        if (is_array($handler) && count($handler) === 2) {
            [$class, $method] = $handler;
            if (is_string($class) && is_string($method)) {
                if (!class_exists($class)) {
                    http_response_code(500);
                    echo "Handler class not found: {$class}";
                    return;
                }
                $obj = new $class();
                if (!method_exists($obj, $method)) {
                    http_response_code(500);
                    echo "Handler method not found: {$class}::{$method}";
                    return;
                }
                $obj->{$method}();
                return;
            }
        }

        http_response_code(500);
        echo "Invalid route handler.";
    }

    /** Normalize paths: collapse slashes, ensure leading slash, no trailing slash (except root) */
    private static function normalizePath(string $p): string
    {
        $p = '/' . ltrim(preg_replace('#/+#', '/', $p ?: '/'), '/');
        if ($p !== '/' && str_ends_with($p, '/')) $p = rtrim($p, '/');
        return $p;
    }

    /** Basic middleware hooks: 'csrf' and 'auth' (CP) */
    private static function runMiddleware(array $mw): bool
    {
        if (!$mw) return true;

        foreach ($mw as $tag) {
            switch ($tag) {
                case 'csrf':
                    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                        $ok = \Shared\Csrf::verify($_POST['_csrf'] ?? '');
                        if (!$ok) {
                            http_response_code(419);
                            echo "CSRF token mismatch.";
                            return false;
                        }
                    }
                    break;

                case 'auth':
                    if (empty($_SESSION['cp_user'])) {
                        header('Location: /cp/login', true, 302);
                        return false;
                    }
                    break;

                default:
                    // Unknown tag: ignore gracefully
                    break;
            }
        }

        return true;
    }
}