<?php
declare(strict_types=1);

if (!defined('BASE_PATH')) define('BASE_PATH', dirname(__DIR__));
$root = BASE_PATH;

/* -----------------------------------------------------------------------------
 | Bootstrap (autoloaders, env, sessions, etc.)
 *----------------------------------------------------------------------------*/
require_once $root . '/bootstrap/Kernel.php';

/* -----------------------------------------------------------------------------
 | Debug handler (red-flag page in dev, quiet page in prod) + logging
 *----------------------------------------------------------------------------*/
$__KF_DEBUG = (getenv('APP_DEBUG') === 'true' || getenv('APP_DEBUG') === '1');
if (isset($_GET['_debug']) && $_GET['_debug'] === '1') { $__KF_DEBUG = true; }

error_reporting(E_ALL);
ini_set('display_errors', $__KF_DEBUG ? '1' : '0');
ini_set('log_errors', '1');

$logDir  = $root . '/storage/logs';
if (!is_dir($logDir)) { @mkdir($logDir, 0775, true); }
$logFile = $logDir . '/app.log';

if (class_exists('\Shared\Debug\ErrorHandler')) {
    \Shared\Debug\ErrorHandler::boot([
        'debug'    => $__KF_DEBUG,
        'log_file' => $logFile,
    ]);
}

/* -----------------------------------------------------------------------------
 | Uses
 *----------------------------------------------------------------------------*/
use App\Middleware\TenantResolver;
use App\Services\ModuleAccess;

/* -----------------------------------------------------------------------------
 | Small responders (JSON aware)
 *----------------------------------------------------------------------------*/
function kf_is_json_request(): bool {
    $accept = strtolower($_SERVER['HTTP_ACCEPT'] ?? '');
    $xhr    = strtolower($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '');
    $ctype  = strtolower($_SERVER['CONTENT_TYPE'] ?? '');
    return (
        strpos($accept,'application/json')!==false ||
        strpos($accept,'text/json')!==false ||
        $xhr === 'xmlhttprequest' ||
        strpos($ctype,'application/json')!==false
    );
}
function kf_emit_json(int $code, array $payload): void {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
}
function kf_404(string $msg='Not found.'): never {
    if (kf_is_json_request()) {
        kf_emit_json(404, ['ok'=>false,'error'=>$msg]);
        exit;
    }
    http_response_code(404);
    header('Content-Type: text/html; charset=utf-8');
    $safe = htmlspecialchars($msg, ENT_QUOTES, 'UTF-8');
    echo <<<HTML
<!doctype html><meta charset="utf-8">
<style>
  body{font-family:system-ui,-apple-system,Segoe UI,Roboto,Inter,Arial;background:#f9fafb;color:#111827;margin:0;min-height:100vh;display:grid;place-items:center}
  .card{max-width:640px;padding:32px 24px;text-align:center;background:#fff;border:1px solid #e5e7eb;border-radius:14px;box-shadow:0 6px 16px rgba(0,0,0,.06)}
  h1{margin:0 0 8px;font-size:42px;color:#2563eb}
  p{margin:0 0 18px;color:#6b7280}
  a{display:inline-block;padding:10px 16px;border-radius:10px;background:#2563eb;color:#fff;text-decoration:none;font-weight:600}
  a:hover{filter:brightness(.95)}
</style>
<div class="card">
  <h1>404</h1>
  <p>{$safe}</p>
  <a href="/">← Back to Home</a>
</div>
HTML;
    exit;
}

/* -----------------------------------------------------------------------------
 | Normalize path & method
 *----------------------------------------------------------------------------*/
$raw    = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$path   = '/' . ltrim(preg_replace('#/+#', '/', $raw), '/');
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if ($method === 'HEAD') $method = 'GET';

/* -----------------------------------------------------------------------------
 | Match /t/{slug}/(apps|modules)/{key}/(tail..)
 *----------------------------------------------------------------------------*/
if (!preg_match('#^/t/([A-Za-z0-9_-]+)/(apps|modules)/([A-Za-z0-9_-]+)(?:/(.*))?$#', $path, $m)) {
    kf_404('Not found.');
}

$slug      = $m[1];
$area      = strtolower($m[2]);               // apps|modules (accept both)
$moduleKey = ModuleAccess::sanitizeKey($m[3]);
$tail      = trim((string)($m[4] ?? ''), '/');

/* -----------------------------------------------------------------------------
 | Resolve tenant
 *----------------------------------------------------------------------------*/
if (!TenantResolver::applyFromSlug($slug)) {
    kf_404('Organization not found or inactive.');
}
$ctx   = TenantResolver::ctx() ?? [];
$orgId = (int)($ctx['org_id'] ?? 0);

/* -----------------------------------------------------------------------------
 | Ensure module is enabled for org
 *----------------------------------------------------------------------------*/
if ($orgId <= 0 || $moduleKey === '' || !ModuleAccess::isEnabledForOrg($orgId, $moduleKey)) {
    kf_404('Module not enabled for this organization.');
}

/* -----------------------------------------------------------------------------
 | Locate module front controller
 *----------------------------------------------------------------------------*/
$modDir = ModuleAccess::moduleDir($moduleKey);
$front  = $modDir ? ($modDir . '/front.php') : null;

if (!$modDir || !is_file($front)) {
    kf_404('Module front controller not found.');
}

/* -----------------------------------------------------------------------------
 | Canonicalize: redirect /modules → /apps
 *----------------------------------------------------------------------------*/
if ($area === 'modules') {
    $dst = "/t/{$slug}/apps/{$moduleKey}" . ($tail !== '' ? '/' . $tail : '');
    if (!headers_sent()) {
        header('Location: ' . $dst, true, 301);
    } else {
        echo '<script>location.replace(' . json_encode($dst) . ');</script>';
    }
    exit;
}

/* -----------------------------------------------------------------------------
 | Build module context and hand off
 *----------------------------------------------------------------------------*/
$__KF_MODULE__ = [
    'org'         => $ctx,
    'slug'        => $slug,
    'method'      => $method,
    'tail'        => $tail,
    'module_key'  => $moduleKey,
    'module_dir'  => $modDir,
    'module_base' => '/t/' . $slug . '/apps/' . $moduleKey,
];

require $front;
exit;