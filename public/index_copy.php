<?php
declare(strict_types=1);

/**
 * KlinFlow — Front Controller (module-local shells only)
 * - Output buffer + fatal guard to avoid blank pages
 * - Tenant + module dispatch; no global shell/layout coupling
 * - Prefers modules/<KEY>/front.php (single entry for each module)
 */

/* ============================================================================
 | 0) Constants & Root
 *===========================================================================*/
if (!defined('BASE_PATH')) define('BASE_PATH', dirname(__DIR__));
$root = BASE_PATH;

/* ============================================================================
 | 1) Runtime Basics — Timezone & Output Buffer
 *===========================================================================*/
if (!ini_get('date.timezone')) { date_default_timezone_set('UTC'); }
ob_start();

/* ============================================================================
 | 2) Debug Switches (URL override via ?_debug=1)
 *===========================================================================*/
$__KF_DEBUG = (getenv('APP_DEBUG') === 'true' || getenv('APP_DEBUG') === '1');
if (isset($_GET['_debug']) && $_GET['_debug'] === '1') { $__KF_DEBUG = true; }

/* Consistent PHP error settings */
error_reporting(E_ALL);
ini_set('display_errors', $__KF_DEBUG ? '1' : '0');
ini_set('log_errors', '1');

/* ============================================================================
 | 3) Response Helpers (JSON detection + JSON error emitter)
 *===========================================================================*/
function kf_is_json_request(): bool {
    $accept = strtolower($_SERVER['HTTP_ACCEPT'] ?? '');
    $xhr    = strtolower($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '');
    $ctype  = strtolower($_SERVER['CONTENT_TYPE'] ?? '');
    return (
        strpos($accept, 'application/json') !== false ||
        strpos($accept, 'text/json') !== false ||
        $xhr === 'xmlhttprequest' ||
        strpos($ctype, 'application/json') !== false
    );
}
function kf_emit_json_error(int $code, string $msg, ?array $debug = null): void {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    $out = ['ok' => false, 'error' => $msg];
    if ($debug) $out['debug'] = $debug;
    echo json_encode($out);
}

/* ============================================================================
 | 4) Global Error Handler (optional) — falls back to local banners
 *===========================================================================*/
$__KF_HANDLER = null;
if (class_exists('\Shared\Debug\ErrorHandler')) {
    try {
        // boot(ErrorHandlerOptions)
        $__KF_HANDLER = \Shared\Debug\ErrorHandler::boot([
            'production' => !$__KF_DEBUG,
            // You may add: 'log_file' => BASE_PATH . '/storage/logs/app.log'
        ]);
    } catch (\Throwable $e) {
        // If custom handler fails to boot, continue with local banners.
    }
}

/* ============================================================================
 | 5) Exception Banner (fallback when no Shared\Debug\ErrorHandler)
 *===========================================================================*/
set_exception_handler(function (Throwable $e) use ($__KF_DEBUG, $__KF_HANDLER) {
    if ($__KF_HANDLER && method_exists($__KF_HANDLER, 'handleThrowable')) {
        $__KF_HANDLER->handleThrowable($e);
        return;
    }

    $msg  = htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
    $file = htmlspecialchars($e->getFile(), ENT_QUOTES, 'UTF-8');
    $line = (int)$e->getLine();
    $trace= htmlspecialchars($e->getTraceAsString(), ENT_QUOTES, 'UTF-8');
    $cls  = htmlspecialchars(get_class($e), ENT_QUOTES, 'UTF-8');
    $time = date('Y-m-d H:i:s');

    if (!$__KF_DEBUG) {
        if (kf_is_json_request()) {
            kf_emit_json_error(500, 'Unexpected error.');
        } else {
            http_response_code(500);
            echo "Unexpected error.";
        }
        return;
    }

    if (kf_is_json_request()) {
        kf_emit_json_error(500, $e->getMessage(), [
            'type'  => $cls,
            'file'  => $e->getFile(),
            'line'  => $e->getLine(),
            'trace' => $e->getTrace(),
            'time'  => $time,
        ]);
        return;
    }

    http_response_code(500);
    $title = "⚠️ Uncaught Exception — {$cls}";
    echo <<<HTML
<!doctype html><meta charset="utf-8">
<div style="font-family:system-ui,-apple-system,Segoe UI,Roboto,Inter,Arial;
            line-height:1.45;background:#FEF3C7;color:#1F2937;
            border:1px solid #F59E0B;border-radius:10px;
            margin:20px;padding:16px;max-width:1000px">
  <div style="font-size:17px;font-weight:700;margin-bottom:4px">{$title}</div>
  <div style="font-size:14px;color:#6B7280;margin-bottom:12px">
    at <code>{$file}</code>:{$line} — <span>{$time}</span>
  </div>
  <div style="padding:10px 14px;background:#FFF;border-radius:8px;
              border:1px solid #FCD34D;color:#1F2937;margin-bottom:12px">
    {$msg}
  </div>
  <details open style="margin-top:8px">
    <summary style="cursor:pointer;font-weight:600;
                     color:#92400E;margin-bottom:6px">Stack trace</summary>
    <pre style="white-space:pre-wrap;background:#0B0F19;color:#E5E7EB;
                padding:12px;border-radius:8px;overflow-x:auto;
                font-size:13px;">{$trace}</pre>
  </details>
</div>
HTML;
});

/* ============================================================================
 | 6) Fatal Guard (no white screen of death)
 *===========================================================================*/
register_shutdown_function(function () use ($__KF_DEBUG, $__KF_HANDLER) {
    $err = error_get_last();
    if ($err && in_array($err['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        while (ob_get_level()) @ob_end_clean();

        $msg  = htmlspecialchars($err['message'], ENT_QUOTES, 'UTF-8');
        $file = htmlspecialchars($err['file'], ENT_QUOTES, 'UTF-8');
        $line = (int)$err['line'];
        $time = date('Y-m-d H:i:s');

        if (!$__KF_DEBUG) {
            if (kf_is_json_request()) {
                kf_emit_json_error(500, 'Unexpected error.');
            } else {
                http_response_code(500);
                echo "Unexpected error.";
            }
            return;
        }

        if (kf_is_json_request()) {
            kf_emit_json_error(500, 'Fatal error', [
                'file' => $err['file'] ?? null,
                'line' => $err['line'] ?? null,
                'msg'  => $err['message'] ?? null,
                'time' => $time,
            ]);
            return;
        }

        http_response_code(500);
        echo <<<HTML
<!doctype html><meta charset="utf-8">
<div style="font-family:system-ui,-apple-system,Segoe UI,Roboto,Inter,Arial;
            line-height:1.45;background:#FEE2E2;color:#1F2937;
            border:1px solid #DC2626;border-radius:10px;
            margin:20px;padding:16px;max-width:1000px">
  <div style="font-size:17px;font-weight:700;margin-bottom:4px">💥 Fatal Error</div>
  <div style="font-size:14px;color:#6B7280;margin-bottom:12px">
    at <code>{$file}</code>:{$line} — <span>{$time}</span>
  </div>
  <div style="padding:10px 14px;background:#FFF;border-radius:8px;
              border:1px solid #FCA5A5;color:#991B1B;margin-bottom:12px">
    {$msg}
  </div>
</div>
HTML;
    } else {
        // Flush any remaining buffer safely
        while (ob_get_level() > 1) @ob_end_flush();
        if (ob_get_level()) @ob_end_flush();
    }
});

/* ============================================================================
 | 7) App Bootstrap (autoloaders, env, sessions, helpers)
 *===========================================================================*/
require_once $root.'/bootstrap/Kernel.php';

/* ============================================================================
 | 8) Imports (controllers, services, router)
 *===========================================================================*/
use Shared\Router;
use Shared\DB;
use App\Middleware\TenantResolver;
use App\Services\ModuleAccess;

use App\Controllers\Tenant\DashboardController;
use App\Controllers\Tenant\SettingsController;
use App\Controllers\Tenant\UsersController;

use App\Controllers\CP\OrganizationsController;
use App\Controllers\CP\UsersController as CPUsersController;
use App\Controllers\CP\OrgBranchesController;
use App\Controllers\CP\OrgBranchUsersController;

/* ============================================================================
 | 9) Misc Helpers (debug flag + friendly 404 page)
 *===========================================================================*/
function kf_debug_on(): bool { return (getenv('APP_DEBUG') === 'true' || getenv('APP_DEBUG') === '1'); }

function friendly404(string $message = 'Page not found.', string $home = '/'): void {
    if (kf_is_json_request()) {
        kf_emit_json_error(404, $message);
        exit;
    }
    http_response_code(404);
    ?>
    <!DOCTYPE html><html lang="en"><head>
      <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
      <title>404 Not Found</title>
      <style>
        :root{--brand:#2563eb}
        body{font-family:system-ui,-apple-system,Segoe UI,Roboto,Inter,Arial,sans-serif;background:#f9fafb;color:#111827;margin:0;min-height:100vh;display:grid;place-items:center}
        .card{text-align:center;padding:32px 24px}
        h1{margin:0 0 8px;font-size:48px;color:var(--brand);line-height:1}
        p{margin:0 0 20px;color:#6b7280}
        a{display:inline-block;padding:10px 16px;border-radius:10px;background:var(--brand);color:#fff;text-decoration:none;font-weight:600}
        a:hover{filter:brightness(.95)}
      </style>
    </head><body><div class="card">
      <h1>404</h1>
      <p><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?></p>
      <a href="<?= htmlspecialchars($home, ENT_QUOTES, 'UTF-8') ?>">← Back to Home</a>
    </div></body></html>
    <?php
    exit;
}

/* ============================================================================
 | 10) Request Normalization (path, base dir, method)
 *===========================================================================*/
$rawPath   = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$scriptDir = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');
$baseDir   = ($scriptDir !== '/' && $scriptDir !== '\\') ? $scriptDir : '';
$path      = urldecode($rawPath);
if ($baseDir !== '' && strpos($path, $baseDir) === 0) $path = substr($path, strlen($baseDir));
$path      = '/' . ltrim(preg_replace('#/+#', '/', $path), '/');
$method    = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if ($method === 'HEAD') $method = 'GET';

/* ============================================================================
 | 11) Health Check
 *===========================================================================*/
if ($path === '/_ping') { header('Content-Type: text/plain; charset=utf-8'); echo "OK"; exit; }

/* ============================================================================
 | 12) Public Tenant-Invite (GET form + POST accept)
 *===========================================================================*/
if ($path === '/tenant/invite/accept' && $method === 'GET')  { (new UsersController())->acceptForm(); exit; }
if ($path === '/tenant/invite/accept' && $method === 'POST') { (new UsersController())->accept();     exit; }

/* ============================================================================
 | 13) Tenant Space Router (/t/{slug}/…)
 *===========================================================================*/
if (preg_match('#^/t/([A-Za-z0-9_-]+)(?:/(.*))?$#', $path, $m)) {
    $slug = $m[1];
    $tail = trim((string)($m[2] ?? ''), '/'); // normalize once
    $seg1 = $tail === '' ? '' : explode('/', $tail, 2)[0];
    $seg2 = $tail === '' ? '' : (explode('/', $tail, 3)[1] ?? '');
    $seg12= $seg1 . ($seg2 ? '/'.$seg2 : '');

    // 13.1) Resolve tenant; 404 if unknown/inactive
    if (!\App\Middleware\TenantResolver::applyFromSlug($slug)) {
        friendly404('Organization not found or inactive.', '/');
    }

    // 13.2) Hard-claim /t/{slug}/users routes (before modules)
    if ($seg1 === 'users') {
        $UC = new \App\Controllers\Tenant\UsersController();

        // /t/{slug}/users
        if ($tail === 'users' && $method === 'GET') { $UC->index(['slug'=>$slug]); exit; }

        // /t/{slug}/users/invite (GET/POST)
        if ($seg12 === 'users/invite') {
            if ($method === 'POST') { $UC->sendInvite(['slug'=>$slug]); exit; }
            if ($method === 'GET')  { $UC->inviteForm(['slug'=>$slug]);  exit; }
        }

        // /t/{slug}/users/me (GET/POST)
        if ($seg12 === 'users/me') {
            if ($method === 'POST') { $UC->updateProfile(['slug'=>$slug]); exit; }
            if ($method === 'GET')  { $UC->profile(['slug'=>$slug]);       exit; }
        }

        // Unknown users subroute -> 404
        friendly404('Unknown Users route.', "/t/{$slug}/dashboard");
    }

    // 13.3) Mounted modules: /t/{slug}/apps/{key}/…
    $tryModule = function (string $slug, string $tail) use ($method) {
        if ($tail === '') return false;
        if (!preg_match('#^(apps|modules)/([A-Za-z0-9_-]+)(?:/(.*))?$#', $tail, $mm)) return false;

        $moduleKey = \App\Services\ModuleAccess::sanitizeKey($mm[2]);
        $after     = trim((string)($mm[3] ?? ''), '/');

        $ctx   = \App\Middleware\TenantResolver::ctx() ?: [];
        $orgId = (int)($ctx['org_id'] ?? 0);

        if ($moduleKey === '' || $orgId <= 0 || !\App\Services\ModuleAccess::isEnabledForOrg($orgId, $moduleKey)) {
            friendly404('Module not enabled for this organization.', "/t/{$slug}/dashboard");
        }

        $modDir = \App\Services\ModuleAccess::moduleDir($moduleKey);
        if (!$modDir) friendly404('Module directory not found.', "/t/{$slug}/dashboard");

        $front = $modDir . '/front.php';
        if (!is_file($front)) friendly404('Module entry file missing (front.php).', "/t/{$slug}/dashboard");

        $__KF_MODULE__ = [
            'org'         => $ctx,
            'slug'        => $slug,
            'method'      => $method,
            'tail'        => $after,
            'module_key'  => $moduleKey,
            'module_dir'  => $modDir,
            'module_base' => "/t/{$slug}/apps/{$moduleKey}",
        ];

        require $front; // module renders itself (own shell/layout)
        return true;
    };

    if ($tryModule($slug, $tail)) exit;

    // 13.4) Core tenant pages (non-module)
    // /t/{slug} → default module or dashboard
    if ($tail === '' && $method === 'GET') {
        try { $def = \App\Services\ModuleAccess::defaultModuleFor($slug); } catch (\Throwable $e) { $def = null; }
        if ($def) { header('Location: "/t/'.rawurlencode($slug).'/apps/'.rawurlencode($def).'"', true, 302); }
        else      { header('Location: "/t/'.rawurlencode($slug).'/dashboard"',                 true, 302); }
        exit;
    }

    // /t/{slug}/dashboard
    if ($tail === 'dashboard' && $method === 'GET') {
        (new \App\Controllers\Tenant\DashboardController())->index(['slug'=>$slug]); exit;
    }

    // /t/{slug}/settings (GET/POST)
    if ($tail === 'settings') {
        if     ($method === 'GET')  { (new \App\Controllers\Tenant\SettingsController())->index(['slug'=>$slug]);  exit; }
        elseif ($method === 'POST') { (new \App\Controllers\Tenant\SettingsController())->update(['slug'=>$slug]); exit; }
    }

    // /t/{slug}/_dbcheck (quick DB context peek)
    if ($tail === '_dbcheck' && $method === 'GET') {
        header('Content-Type: application/json; charset=utf-8');
        $ctx = \App\Middleware\TenantResolver::ctx() ?: [];
        $tenantPdo = method_exists(\Shared\DB::class, 'tenant') ? \Shared\DB::tenant() : \Shared\DB::pdo();
        $dbName = 'n/a';
        try { $dbName = $tenantPdo->query('SELECT DATABASE()')->fetchColumn() ?: 'n/a'; } catch (\Throwable $e) {}
        echo json_encode([
            'ok'       => true,
            'org_id'   => $ctx['org_id'] ?? null,
            'slug'     => $ctx['slug']   ?? $slug,
            'database' => $dbName,
        ]);
        exit;
    }

    // 13.5) Legacy redirect: /t/{slug}/{module} → /t/{slug}/apps/{module}
    //      (Skip reserved tenant paths so they’re not hijacked.)
    if ($tail !== '' && preg_match('#^([A-Za-z0-9_-]+)(?:/(.*))?$#', $tail, $mm)) {
        $maybe = \App\Services\ModuleAccess::sanitizeKey($mm[1] ?? '');
        $rest  = trim((string)($mm[2] ?? ''), '/');
        $reserved = ['users','dashboard','settings','_dbcheck','me'];
        if ($maybe && !in_array($maybe, $reserved, true)) {
            $ctx   = \App\Middleware\TenantResolver::ctx() ?: [];
            $orgId = (int)($ctx['org_id'] ?? 0);
            if ($orgId > 0 && \App\Services\ModuleAccess::isEnabledForOrg($orgId, $maybe)) {
                $to = '/t/' . rawurlencode($slug) . '/apps/' . rawurlencode($maybe) . ($rest ? '/'.$rest : '');
                header('Location: ' . $to, true, 301);
                exit;
            }
        }
    }

    // 13.6) Tenant fallback
    friendly404('Page not found.', "/t/{$slug}/dashboard");
}

/* ============================================================================
 | 14) Control Panel Shims (/cp/…)
 *===========================================================================*/
if ($method === 'GET'  && preg_match('#^/cp/organizations/(\d+)/edit/?$#', $path, $m)) { (new OrganizationsController())->editForm(['id'=>(int)$m[1]]); exit; }
if ($method === 'POST' && preg_match('#^/cp/organizations/(\d+)$#',       $path, $m)) { (new OrganizationsController())->update(['id'=>(int)$m[1]]);   exit; }
if ($method === 'GET'  && preg_match('#^/cp/users/(\d+)/edit/?$#',        $path, $m)) { (new CPUsersController())->editForm(['id'=>(int)$m[1]]);      exit; }
if ($method === 'POST' && preg_match('#^/cp/users/(\d+)$#',                $path, $m)) { (new CPUsersController())->update(['id'=>(int)$m[1]]);       exit; }

// --- CP: Organization branches (id-based) ---
if ($method === 'GET' && preg_match('#^/cp/organizations/(\d+)/branches/?$#', $path, $m)) {
    (new OrgBranchesController())->index(['org_id' => (int)$m[1]]);
    exit;
}

if ($method === 'GET' && preg_match('#^/cp/organizations/(\d+)/branches/create/?$#', $path, $m)) {
    (new OrgBranchesController())->createForm(['org_id' => (int)$m[1]]);
    exit;
}

if ($method === 'POST' && preg_match('#^/cp/organizations/(\d+)/branches/?$#', $path, $m)) {
    (new OrgBranchesController())->store(['org_id' => (int)$m[1]]);
    exit;
}

// Optional (you can keep them as stubs for now)
if ($method === 'GET' && preg_match('#^/cp/organizations/(\d+)/branches/(\d+)/edit/?$#', $path, $m)) {
    (new OrgBranchesController())->editForm(['org_id' => (int)$m[1], 'id' => (int)$m[2]]);
    exit;
}

if ($method === 'POST' && preg_match('#^/cp/organizations/(\d+)/branches/(\d+)/update$#', $path, $m)) {
    (new OrgBranchesController())->update(['org_id' => (int)$m[1], 'id' => (int)$m[2]]);
    exit;
}

if ($method === 'POST' && preg_match('#^/cp/organizations/(\d+)/branches/(\d+)/delete$#', $path, $m)) {
    (new OrgBranchesController())->destroy(['org_id' => (int)$m[1], 'id' => (int)$m[2]]);
    exit;
}

// --- CP: Org branch users (per org) ---
if ($method === 'GET' && preg_match('#^/cp/organizations/(\d+)/users/?$#', $path, $m)) {
    (new OrgBranchUsersController())->index(['org_id' => (int)$m[1]]);
    exit;
}

if ($method === 'GET' && preg_match('#^/cp/organizations/(\d+)/users/create/?$#', $path, $m)) {
    (new OrgBranchUsersController())->createForm(['org_id' => (int)$m[1]]);
    exit;
}

if ($method === 'POST' && preg_match('#^/cp/organizations/(\d+)/users/?$#', $path, $m)) {
    (new OrgBranchUsersController())->store(['org_id' => (int)$m[1]]);
    exit;
}

/* ============================================================================
 | 15) Array Router Fallback (public pages)
 *===========================================================================*/
$routes = [];
foreach (['/routes/web.php','/routes/cp.php','/routes/tenant.php'] as $rel) {
    $f = $root.$rel;
    if (is_file($f)) {
        $r = require $f;
        if (is_array($r)) { $routes = array_merge($routes, $r); }
    }
}
Shared\Router::dispatch($routes, $method, $path);
if (http_response_code() === 404) { friendly404('Page not found.'); }