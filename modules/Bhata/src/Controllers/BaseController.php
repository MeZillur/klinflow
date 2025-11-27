<?php
declare(strict_types=1);

namespace Modules\Bhata\Controllers;

use Shared\View;
use Shared\DB;
use PDO;

/**
 * BhataFlow BaseController
 * -------------------------------------------
 * - Mirrors DMS BaseController for stability
 * - Handles tenant/org context resolution
 * - Provides safe module_base (/t/{slug}/apps/bhata)
 * - Includes helpers for view(), json(), redirect(), etc.
 */
abstract class BaseController
{
    private ?array $cachedCtx = null;

    /* ------------------------------------------------------------
     * Context resolution
     * ------------------------------------------------------------ */
    protected function ctx(?array $ctx = null): array
    {
        if ($this->cachedCtx !== null) {
            return $ctx ? ($this->cachedCtx + $ctx) : $this->cachedCtx;
        }

        if (session_status() !== PHP_SESSION_ACTIVE) {
            @session_start();
        }

        $ctx = $ctx ?? [];

        // 1️⃣ Org + slug
        $org  = (array)($ctx['org'] ?? ($_SESSION['tenant_org'] ?? []));
        $slug = (string)($ctx['slug'] ?? ($org['slug'] ?? $this->inferSlugFromUrl()));

        // Try TenantResolver if org missing
        if ((!isset($org['id']) || !(int)$org['id']) && class_exists(\App\Middleware\TenantResolver::class)) {
            try {
                if ($slug !== '' && method_exists(\App\Middleware\TenantResolver::class, 'applyFromSlug')) {
                    \App\Middleware\TenantResolver::applyFromSlug($slug);
                }
                if (method_exists(\App\Middleware\TenantResolver::class, 'ctx')) {
                    $rctx = (array)\App\Middleware\TenantResolver::ctx();
                    if (!empty($rctx['org_id'])) {
                        $org = [
                            'id'   => (int)$rctx['org_id'],
                            'slug' => (string)($rctx['slug'] ?? $slug),
                            'name' => (string)($rctx['org_name'] ?? ($org['name'] ?? 'Organization')),
                        ];
                        $_SESSION['tenant_org'] = $org;
                        $slug = (string)$org['slug'];
                    }
                }
            } catch (\Throwable $e) {
                // Fail softly
            }
        }

        $org = $org + [
            'id'   => (int)($org['id'] ?? 0),
            'slug' => (string)($org['slug'] ?? $slug),
        ];

        // 2️⃣ Module paths
        $moduleDir  = (string)($ctx['module_dir'] ?? realpath(\dirname(__DIR__)));
        $moduleBase = (string)($ctx['module_base'] ?? (
            $org['slug'] !== '' ? '/t/' . rawurlencode((string)$org['slug']) . '/apps/bhata' : '/apps/bhata'
        ));

        // 3️⃣ Layout + request
        $layoutFile = $ctx['layout'] ?? null;
        $scope      = (string)($ctx['scope'] ?? 'tenant');
        $method     = strtoupper($_SERVER['REQUEST_METHOD'] ?? ($ctx['method'] ?? 'GET'));

        // Memoize
        $this->cachedCtx = [
            'org'         => $org,
            'slug'        => (string)$org['slug'],
            'org_id'      => (int)$org['id'],
            'module_dir'  => $moduleDir,
            'module_base' => $moduleBase,
            'layout'      => $layoutFile,
            'scope'       => $scope,
            'method'      => $method,
        ];

        return $this->cachedCtx;
    }

    private function inferSlugFromUrl(): string
    {
        $uri = (string)($_SERVER['REQUEST_URI'] ?? '');
        if ($uri !== '' && preg_match('~^/t/([^/]+)~', $uri, $m)) {
            return strtolower((string)$m[1]);
        }
        return '';
    }

    /* ------------------------------------------------------------
     * DB helpers
     * ------------------------------------------------------------ */
    protected function pdo(): PDO { return DB::pdo(); }

    protected function tenantPdo(): PDO
    {
        if (method_exists(DB::class, 'tenant')) {
            try { return DB::tenant(); } catch (\Throwable $e) {}
        }
        return $this->pdo();
    }

    protected function orgId(?array $ctx = null): int
    {
        return (int)$this->ctx($ctx)['org_id'];
    }

    protected function moduleBase(?array $ctx = null): string
    {
        return (string)$this->ctx($ctx)['module_base'];
    }

    /* ------------------------------------------------------------
     * Rendering helpers
     * ------------------------------------------------------------ */
    protected function view(string $rel, array $data = [], ?array $ctx = null): void
    {
        $c = $this->ctx($ctx);
        $viewPath = rtrim($c['module_dir'], '/') . '/Views/' . ltrim($rel, '/') . '.php';
        if (!is_file($viewPath)) $this->abort404('View not found: ' . $rel);

        $vars = array_merge([
            'org'         => $c['org'],
            'slug'        => $c['slug'],
            'module_base' => $c['module_base'],
            'shell'       => 'module',
        ], $data);

        View::render($viewPath, $vars, $c['layout']);
    }

    protected function renderStandaloneFromModuleDir(string $moduleDir, string $relativeViewPath, array $vars = []): void
    {
        $base = rtrim($moduleDir, '/');
        $file = $base . '/Views/' . ltrim($relativeViewPath, '/');
        if (!is_file($file)) {
            if (!headers_sent()) header('Content-Type:text/plain; charset=utf-8', true, 500);
            echo "View not found: {$file}";
            return;
        }
        extract($vars, EXTR_SKIP);
        if (!headers_sent()) header('Content-Type:text/html; charset=utf-8');
        require $file;
    }

    /* ------------------------------------------------------------
     * Response helpers
     * ------------------------------------------------------------ */
    protected function json(mixed $payload, int $status = 200, array $headers = []): void
    {
        http_response_code($status);
        foreach ($headers as $k => $v) header($k . ': ' . $v);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    protected function redirect(string $to, ?array $ctx = null, int $status = 302): void
    {
        $c = $this->ctx($ctx);
        $isAbs = preg_match('#^https?://#i', $to) || str_starts_with($to, '/');
        $url = $isAbs ? $to : rtrim($c['module_base'], '/') . '/' . ltrim($to, '/');
        header('Location: ' . $url, true, $status);
        exit;
    }

    protected function abort404(string $msg = 'Not found.'): void
    {
        http_response_code(404);
        echo '<div style="padding:32px;font:15px/1.5 system-ui,-apple-system,Segoe UI,Roboto">'
           . '<h2 style="margin:0 0 10px;color:#111">404 Not Found</h2>'
           . '<p style="color:#6b7280">' . htmlspecialchars($msg, ENT_QUOTES, 'UTF-8') . '</p>'
           . '</div>';
        exit;
    }

    protected function abort500(string|\Throwable $err = 'Internal error.'): void
    {
        $msg = $err instanceof \Throwable ? $err->getMessage() : $err;
        http_response_code(500);
        echo '<div style="padding:32px;font:15px/1.5 system-ui,-apple-system,Segoe UI,Roboto">'
           . '<h2 style="margin:0 0 10px;color:#b91c1c">Server Error</h2>'
           . '<p style="color:#6b7280">' . htmlspecialchars($msg, ENT_QUOTES, 'UTF-8') . '</p>'
           . '</div>';
        exit;
    }
}