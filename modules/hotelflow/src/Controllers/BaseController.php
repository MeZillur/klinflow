<?php
declare(strict_types=1);

namespace Modules\hotelflow\Controllers;

use Shared\View;
use Shared\DB;
use PDO;

abstract class BaseController
{
    /**
     * Normalize controller context.
     *
     * Priority:
     *  - $ctx['org'] / $ctx['slug'] passed from routes.php
     *  - Fallback to $_SESSION['tenant_org']
     */
    protected function ctx(?array $ctx = null): array
    {
        if (\PHP_SESSION_ACTIVE !== \session_status()) {
            @\session_start();
        }

        $ctx = $ctx ?? [];

        $sessionOrg = (array)($_SESSION['tenant_org'] ?? []);

        // Use route org if present, otherwise session org
        $org  = (array)($ctx['org'] ?? $sessionOrg);
        $slug = (string)(
            $ctx['slug']
            ?? ($org['slug'] ?? ($sessionOrg['slug'] ?? ''))
        );

        $moduleDir  = (string)($ctx['module_dir']  ?? realpath(\dirname(__DIR__, 1)));
        $moduleBase = (string)(
            $ctx['module_base']
            ?? ($slug ? '/t/'.$slug.'/apps/hotelflow' : '/apps/hotelflow')
        );
        $layoutFile = $ctx['layout'] ?? null;

        return [
            'slug'        => $slug,
            'org'         => $org + ['slug' => $slug],
            'org_id'      => (int)($org['id'] ?? ($sessionOrg['id'] ?? 0)),
            'module_dir'  => $moduleDir,
            'module_base' => $moduleBase,
            'method'      => strtoupper($_SERVER['REQUEST_METHOD'] ?? (string)($ctx['method'] ?? 'GET')),
            'scope'       => (string)($ctx['scope'] ?? 'tenant'),
            'layout'      => $layoutFile,
        ];
    }

    protected function pdo(): PDO
    {
        return DB::pdo();
    }

    protected function tenantPdo(): PDO
    {
        return $this->pdo();
    }

    protected function orgId(?array $ctx = null): int
    {
        $c = $this->ctx($ctx);
        return (int)($c['org_id'] ?? 0);
    }

    protected function moduleBase(?array $ctx = null): string
    {
        $c = $this->ctx($ctx);
        return (string)($c['module_base'] ?? '/apps/hotelflow');
    }

    /**
     * Render a module view inside the HotelFlow shell.
     * Views must be content-only. Shell is provided by layout from routes.php.
     */
    protected function view(string $rel, array $data = [], ?array $ctx = null): void
    {
        $c = $this->ctx($ctx);
        $viewPath   = rtrim($c['module_dir'], '/') . '/Views/' . ltrim($rel, '/') . '.php';
        $layoutFile = $c['layout'];

        if (!is_file($viewPath)) {
            http_response_code(404);
            echo 'View not found: ' . htmlspecialchars($rel, ENT_QUOTES, 'UTF-8');
            return;
        }

        $vars = array_merge([
            'org'         => $c['org'],
            'slug'        => $c['slug'],
            'module_base' => $c['module_base'],
            'title'       => $data['title'] ?? 'HotelFlow — Dashboard',
        ], $data);

        View::render($viewPath, $vars, $layoutFile);
    }

    protected function json(mixed $payload, int $status = 200, array $headers = []): void
    {
        http_response_code($status);
        foreach ($headers as $k => $v) {
            header($k . ': ' . $v);
        }
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    protected function jsonOk(array $data = [], int $status = 200, array $extraHeaders = []): void
    {
        $h = array_merge([
            'Content-Type'  => 'application/json; charset=utf-8',
            'Cache-Control' => 'no-store, max-age=0',
        ], $extraHeaders);

        http_response_code($status);
        foreach ($h as $k => $v) {
            header($k . ': ' . $v);
        }

        echo json_encode(['ok' => true, 'data' => $data], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    protected function jsonErr(string $message, int $status = 400, array $extraHeaders = []): void
    {
        $h = array_merge([
            'Content-Type'  => 'application/json; charset=utf-8',
            'Cache-Control' => 'no-store, max-age=0',
        ], $extraHeaders);

        http_response_code($status);
        foreach ($h as $k => $v) {
            header($k . ': ' . $v);
        }

        echo json_encode(['ok' => false, 'error' => $message], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    protected function redirect(string $to, ?array $ctx = null, int $status = 302): void
    {
        $c = $this->ctx($ctx);
        $abs = (bool)preg_match('#^https?://#i', $to) || str_starts_with($to, '/');
        $url = $abs ? $to : rtrim($c['module_base'], '/') . '/' . ltrim($to, '/');

        http_response_code($status);
        header('Location: ' . $url);
        exit;
    }

    protected function abort404(string $msg = 'Not found.'): void
    {
        http_response_code(404);
        echo '<div style="padding:24px;font:14px/1.45 system-ui,-apple-system,Segoe UI,Roboto">'
           . '<h2 style="margin:0 0 8px">404</h2>'
           . '<div style="margin-bottom:16px;color:#6b7280">'
           . htmlspecialchars($msg, ENT_QUOTES, 'UTF-8')
           . '</div>'
           . '</div>';
        exit;
    }
}