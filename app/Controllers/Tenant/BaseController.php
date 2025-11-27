<?php
declare(strict_types=1);

namespace App\Controllers\Tenant;

use Shared\View;

/**
 * Tenant BaseController (module-agnostic)
 * - Renders views inside the shell selected by UiFrame middleware.
 * - Resolves org/user/slug from session or URL; plays nice with TenantResolver.
 * - Provides handy redirect/json/flash utilities.
 */
abstract class BaseController
{
    /**
     * Render any tenant view using the shell picked by UiFrame.
     * Example $viewId: 'tenant/users/index' -> apps/Tenant/Views/tenant/users/index.php
     */
    protected function render(string $viewId, array $data = []): void
    {
        $ui     = $this->sharedUi();
        $layout = (isset($ui['shell']) && is_string($ui['shell']) && $ui['shell'] !== '') ? $ui['shell'] : null;

        // Ensure org + slug present for shells/partials
        [$org, , $slug] = $this->tenantGuard($data['slug'] ?? null, false);
        $data['org']  = $data['org']  ?? $org;
        $data['slug'] = $data['slug'] ?? $slug;

        // Your Shared\View supports optional 3rd param for layout
        View::render($viewId, $data, $layout);
    }

    /** Legacy alias */
    protected function shellRender(string $viewId, array $data = []): void
    {
        $this->render($viewId, $data);
    }

    /**
     * Strict tenant context guard.
     * Returns [org, user, slug]. Set $requireAuth=true to enforce login.
     */
    protected function tenantGuard(?string $slugFromRoute = null, bool $requireAuth = false): array
    {
        // Ensure session before touching $_SESSION
        if (session_status() !== PHP_SESSION_ACTIVE) {
            @session_start();
        }

        $slug = $slugFromRoute ?: $this->inferSlugFromSessionOrUrl();

        $org  = $_SESSION['tenant_org']  ?? null;
        $user = $_SESSION['tenant_user'] ?? null;

        // Optionally refine via TenantResolver if available
        if (class_exists(\App\Middleware\TenantResolver::class)) {
            try {
                if (!$org || !is_array($org)) {
                    if ($slug !== '' && method_exists(\App\Middleware\TenantResolver::class, 'applyFromSlug')) {
                        \App\Middleware\TenantResolver::applyFromSlug($slug);
                    }
                    $ctx = method_exists(\App\Middleware\TenantResolver::class, 'ctx')
                        ? (\App\Middleware\TenantResolver::ctx() ?: [])
                        : [];

                    if ($ctx) {
                        $org = [
                            'id'   => $ctx['org_id']   ?? ($ctx['id'] ?? null),
                            'slug' => $ctx['slug']     ?? $slug,
                            'name' => $ctx['org_name'] ?? ($ctx['name'] ?? 'Organization'),
                        ];
                        $_SESSION['tenant_org'] = $_SESSION['tenant_org'] ?? $org;
                    }
                }
            } catch (\Throwable $e) {
                // ignore; fall back to session/inferred slug
            }
        }

        if (!is_array($org)) {
            $org = ['id' => null, 'slug' => ($slug ?: ''), 'name' => 'Organization'];
        } else {
            $org['slug'] = (string)($org['slug'] ?? $slug);
        }
        $slug = (string)$org['slug'];

        if ($requireAuth && (!$user || !is_array($user))) {
            $this->redirect($slug ? "/t/{$slug}/login" : '/tenant/login');
        }

        return [$org, $user, $slug];
    }

    /* ------------------------ Utilities ------------------------ */

    protected function redirect(string $url, int $code = 302): void
    {
        if (!headers_sent()) {
            header('Location: '.$url, true, $code);
            exit;
        }
        echo '<script>location.replace('.json_encode($url).');</script>';
        exit;
    }

    protected function json($payload, int $code = 200): void
    {
        if (!headers_sent()) {
            http_response_code($code);
            header('Content-Type: application/json; charset=utf-8');
        }
        echo json_encode($payload);
        exit;
    }

    /** Flash helpers using session */
    protected function flash(string $key, $value): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) @session_start();
        $_SESSION['_flash'][$key] = $value;
    }

    protected function take(string $key, $default = null)
    {
        if (session_status() !== PHP_SESSION_ACTIVE) @session_start();
        $val = $_SESSION['_flash'][$key] ?? $default;
        if (isset($_SESSION['_flash'][$key])) unset($_SESSION['_flash'][$key]);
        return $val;
    }

    protected function method(): string
    {
        return strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
    }

    protected function param(string $key, $default = null)
    {
        if (isset($_POST[$key])) return $_POST[$key];
        if (isset($_GET[$key]))  return $_GET[$key];
        return $default;
    }

    /** Infer /t/{slug} from session or request URI */
    private function inferSlugFromSessionOrUrl(): string
    {
        if (session_status() !== PHP_SESSION_ACTIVE) @session_start();
        $slug = (string)($_SESSION['tenant_org']['slug'] ?? '');
        if ($slug !== '') return $slug;

        $uri = (string)($_SERVER['REQUEST_URI'] ?? '');
        if ($uri !== '' && preg_match('~^/t/([^/]+)~', $uri, $m)) {
            return (string)$m[1];
        }
        return '';
    }

    /**
     * Obtain the shared $ui array safely.
     * Uses Shared\View::shared('ui') when available; otherwise falls back
     * to a global slot some stacks populate. Never calls a zero-arg view().
     */
    private function sharedUi(): array
    {
        if (class_exists(\Shared\View::class) && method_exists(\Shared\View::class, 'shared')) {
            try {
                $shared = \Shared\View::shared('ui');
                if (is_array($shared)) {
                    return $shared;
                }
            } catch (\Throwable $e) {
                // swallow and fallback
            }
        }
        return is_array($GLOBALS['__view_shared']['ui'] ?? null)
            ? $GLOBALS['__view_shared']['ui']
            : [];
    }
}