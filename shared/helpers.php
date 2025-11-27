<?php
declare(strict_types=1);

use Shared\View;
use Shared\Csrf;

if (!function_exists('view')) {
    /** Render a view by slug (cp/login, public/home, tenant/dashboard) */
    function view(string $slug, array $data = []): void {
        View::render($slug, $data);
    }
}

if (!function_exists('csrf_token')) {
    function csrf_token(): string {
        return Csrf::token();
    }
}

if (!function_exists('redirect')) {
    function redirect(string $path, int $status = 302): void {
        header('Location: ' . $path, true, $status);
        exit;
    }
}

if (!function_exists('asset')) {
    /** Basic asset helper; in future can add versioning */
    function asset(string $path): string {
        $path = '/' . ltrim($path, '/');
        return $path;
    }
}