<?php
declare(strict_types=1);

namespace Modules\DMS\Controllers;

use Shared\View;
use Shared\DB;
use PDO;

/**
 * DMS BaseController (refined, safe)
 * - Stable tenant context (memoized per request)
 * - Predictable module_base (/t/{slug}/apps/dms or /apps/dms)
 * - Tenant PDO helper that prefers DB::tenant() when available
 * - Gentle JSON/HTML error handling
 * - Optional requireTenant() guard for tenant-only pages
 */
abstract class BaseController
{
    /** @var array<string,mixed>|null */
    private ?array $cachedCtx = null;

    /* --------------------------------------------------------------------
     | Context resolution (memoized)
     |---------------------------------------------------------------------*/

    /**
     * Normalize/infer the tenant + module context.
     * Keys: org, slug, org_id, module_dir, module_base, layout, scope, method
     */
    protected function ctx(?array $ctx = null): array
    {
        if ($this->cachedCtx !== null) {
            // Merge any overrides the caller passed (layout, scope, etc.)
            return $ctx ? ($this->cachedCtx + $ctx) : $this->cachedCtx;
        }

        if (session_status() !== PHP_SESSION_ACTIVE) {
            @session_start();
        }

        $ctx = $ctx ?? [];

        // 1) Org + slug
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
                // Fail softly; controllers can still run with org_id=0
            }
        }

        // Normalize org record
        $org = $org + [
            'id'   => (int)($org['id'] ?? 0),
            'slug' => (string)($org['slug'] ?? $slug),
        ];

        // 2) Module paths
        $moduleDir  = (string)($ctx['module_dir'] ?? realpath(\dirname(__DIR__)));
        $moduleBase = (string)($ctx['module_base'] ?? ($org['slug'] !== '' ? '/t/' . rawurlencode((string)$org['slug']) . '/apps/dms' : '/apps/dms'));

        // 3) Layout + request info
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

    /** Infer slug from URL like /t/{slug}/... (kept lowercase for consistency) */
    private function inferSlugFromUrl(): string
    {
        $uri = (string)($_SERVER['REQUEST_URI'] ?? '');
        if ($uri !== '' && preg_match('~^/t/([^/]+)~', $uri, $m)) {
            return strtolower((string)$m[1]);
        }
        return '';
    }

    /* --------------------------------------------------------------------
     | DB / Tenant helpers
     |---------------------------------------------------------------------*/

    /** Global PDO (shared DB) */
    protected function pdo(): PDO
    {
        return DB::pdo();
    }

    /**
     * Tenant PDO
     * Uses DB::tenant() when available, otherwise falls back to global.
     * Safe for single-DB installs; harmless for split-DB (resolver selects).
     */
    protected function tenantPdo(): PDO
    {
        if (method_exists(DB::class, 'tenant')) {
            try { return DB::tenant(); } catch (\Throwable $e) { /* fall through */ }
        }
        return $this->pdo();
    }

    /** Current org id (0 if unresolved) */
    protected function orgId(?array $ctx = null): int
    {
        return (int)$this->ctx($ctx)['org_id'];
    }

    /** Base URL for this module */
    protected function moduleBase(?array $ctx = null): string
    {
        return (string)$this->ctx($ctx)['module_base'];
    }

    /**
     * Optional guard you can call at the top of tenant pages.
     * Sends 404 (or JSON 404) if org_id is missing.
     */
    protected function requireTenant(?array $ctx = null): void
    {
        if ($this->orgId($ctx) > 0) return;
        $this->abort404('Tenant not resolved for this request.');
    }

    /* --------------------------------------------------------------------
     | Rendering
     |---------------------------------------------------------------------*/

    /**
     * Render a module-local view (modules/DMS/Views/...).
     * Injects org/slug/module_base and exposes $overrideSidenav for shells that support it.
     */
    protected function view(string $rel, array $data = [], ?array $ctx = null): void
    {
        $c = $this->ctx($ctx);

        $viewPath = rtrim($c['module_dir'], '/') . '/Views/' . ltrim($rel, '/') . '.php';
        if (!is_file($viewPath)) {
            $this->abort404('View not found: ' . $rel);
        }

        // Module sidenav partial (if your shell supports $overrideSidenav)
        $sidenavFile   = rtrim($c['module_dir'], '/') . '/Views/shared/partials/sidenav.php';
        $moduleSidenav = is_file($sidenavFile) ? $sidenavFile : null;

        $vars = array_merge([
            'org'             => $c['org'],
            'slug'            => $c['slug'],
            'module_base'     => $c['module_base'],
            'shell'           => 'module',
            'moduleSidenav'   => $moduleSidenav,
            'overrideSidenav' => $moduleSidenav, // ← matches your shell’s expectation
            'head_includes'   => [],
            'foot_includes'   => [],
        ], $data);

        View::render($viewPath, $vars, $c['layout']);
    }

    protected function render(string $rel, array $data = [], ?array $ctx = null): void
    {
        $this->view($rel, $data, $ctx);
    }

    /* --------------------------------------------------------------------
     | Response helpers
     |---------------------------------------------------------------------*/

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
        $isAbs = $this->isAbsoluteUrl($to) || str_starts_with($to, '/');

        // Normalize relative paths (avoid "../" surprises)
        $url = $isAbs ? $to : rtrim($c['module_base'], '/') . '/' . ltrim(preg_replace('~^\./+~', '', $to), '/');

        header('Location: ' . $url, true, $status);
        exit;
    }

    protected function abort404(string $msg = 'Not found.'): void
    {
        if ($this->isJsonRequest()) {
            $this->json(['error' => 'not_found', 'message' => $msg], 404);
        }
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
        if ($this->isJsonRequest()) {
            $this->json(['error' => 'server_error', 'message' => $msg], 500);
        }
        http_response_code(500);
        echo '<div style="padding:32px;font:15px/1.5 system-ui,-apple-system,Segoe UI,Roboto">'
           . '<h2 style="margin:0 0 10px;color:#b91c1c">Server Error</h2>'
           . '<p style="color:#6b7280">' . htmlspecialchars($msg, ENT_QUOTES, 'UTF-8') . '</p>'
           . '</div>';
        exit;
    }

    /* --------------------------------------------------------------------
     | Utility
     |---------------------------------------------------------------------*/

    private function isAbsoluteUrl(string $url): bool
    {
        return (bool)preg_match('#^https?://#i', $url);
    }

    /** Simple JSON detection for XHR/Fetch or explicit Accept header */
    private function isJsonRequest(): bool
    {
        $accept = strtolower((string)($_SERVER['HTTP_ACCEPT'] ?? ''));
        $xhr    = strtolower((string)($_SERVER['HTTP_X_REQUESTED_WITH'] ?? ''));
        return str_contains($accept, 'application/json') || $xhr === 'xmlhttprequest';
    }

    protected function requireMethod(string $verb): void
    {
        if (strcasecmp($_SERVER['REQUEST_METHOD'] ?? '', $verb) !== 0) {
            http_response_code(405);
            header('Allow: ' . strtoupper($verb));
            echo 'Method Not Allowed';
            exit;
        }
    }

    protected function in(string $key, mixed $default = null): mixed
    {
        return $_POST[$key] ?? $_GET[$key] ?? $default;
    }
    protected function inInt(string $key, int $default = 0): int
    {
        return (int)($this->in($key, $default));
    }
    protected function inFloat(string $key, float $default = 0.0): float
    {
        return (float)($this->in($key, $default));
    }
    protected function inStr(string $key, string $default = ''): string
    {
        return (string)($this->in($key, $default));
    }
}