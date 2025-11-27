<?php
declare(strict_types=1);

/**
 * KlinFlow Bootstrap Kernel (PHP 7.2+ safe)
 * - PSR-4-ish autoloader for: Shared\, App\, Modules\
 * - Loads helpers (shared + app)
 * - .env loader (quoted values supported)
 * - Timezone & HTTPS (proxy-aware)
 * - Secure session cookies (domain-aware via COOKIE_DOMAIN)
 * - Error reporting from APP_DEBUG
 * - Optional remember-me autoLogin()
 * - Robust CSRF bridge for /tenant/login (namespaced "tenant")
 */

if (!defined('BASE_PATH')) {
    define('BASE_PATH', dirname(__DIR__));
}
$ROOT = BASE_PATH;

/* ------------------------------------------------------------------ */
/*  Polyfills for PHP 7.2                                             */
/* ------------------------------------------------------------------ */
if (!function_exists('str_starts_with')) {
    function str_starts_with(string $haystack, string $needle): bool {
        return $needle === '' || strpos($haystack, $needle) === 0;
    }
}
if (!function_exists('str_ends_with')) {
    function str_ends_with(string $haystack, string $needle): bool {
        if ($needle === '') return true;
        $len = strlen($needle);
        return substr($haystack, -$len) === $needle;
    }
}

/* ------------------------------------------------------------------ */
/*  Small helpers                                                     */
/* ------------------------------------------------------------------ */
if (!function_exists('kf_bool_env')) {
    function kf_bool_env(string $key, bool $default = false): bool {
        $v = getenv($key);
        if ($v === false) return $default;
        $v = strtolower(trim((string)$v));
        return ($v === '1' || $v === 'true' || $v === 'on' || $v === 'yes');
    }
}
if (!function_exists('kf_get_env')) {
    function kf_get_env(string $key, ?string $default = null): ?string {
        $v = getenv($key);
        return ($v === false) ? $default : (string)$v;
    }
}

/* ------------------------------------------------------------------ */
/*  Autoloader: Shared\, App\, Modules\                               */
/* ------------------------------------------------------------------ */
spl_autoload_register(function (string $class) use ($ROOT) {
    $class = ltrim($class, '\\');

    if (str_starts_with($class, 'Shared\\')) {
        $rel  = substr($class, 7);
        $file = $ROOT . '/shared/' . str_replace('\\', '/', $rel) . '.php';
        if (is_file($file)) { require $file; return; }
    }
    if (str_starts_with($class, 'App\\')) {
        $rel  = substr($class, 4);
        $file = $ROOT . '/app/' . str_replace('\\', '/', $rel) . '.php';
        if (is_file($file)) { require $file; return; }
    }
    if (str_starts_with($class, 'Modules\\')) {
        $rel = substr($class, 8);
        $direct = $ROOT . '/modules/' . str_replace('\\', '/', $rel) . '.php';
        if (is_file($direct)) { require $direct; return; }
        $parts = explode('\\', $rel, 2);
        if (count($parts) === 2) {
            $module = $parts[0];
            $rest   = $parts[1];
            $fileSrc = $ROOT . '/modules/' . $module . '/src/' . str_replace('\\', '/', $rest) . '.php';
            if (is_file($fileSrc)) { require $fileSrc; return; }
            $fileAlt = $ROOT . '/modules/' . $module . '/' . str_replace('\\', '/', $rest) . '.php';
            if (is_file($fileAlt)) { require $fileAlt; return; }
        }
    }
});

/* ------------------------------------------------------------------ */
/*  Helpers (functions)                                               */
/* ------------------------------------------------------------------ */
$helpers1 = $ROOT . '/shared/helpers.php';
if (is_file($helpers1)) require_once $helpers1;
$helpers2 = $ROOT . '/app/Helpers/helpers.php';
if (is_file($helpers2)) require_once $helpers2;

/* ------------------------------------------------------------------ */
/*  .env loader                                                       */
/* ------------------------------------------------------------------ */
(function () use ($ROOT) {
    $env = $ROOT . '/.env';
    if (!is_file($env)) return;
    $lines = @file($env, FILE_IGNORE_NEW_LINES);
    if (!$lines) return;
    foreach ($lines as $line) {
        if ($line === '' || $line === false) continue;
        $raw = ltrim($line);
        if ($raw === '' || $raw[0] === '#') continue;
        $pos = strpos($raw, '=');
        if ($pos === false) continue;
        $k = trim(substr($raw, 0, $pos)); if ($k === '') continue;
        $v = trim(substr($raw, $pos + 1));
        if ($v !== '' && ($v[0] === '"' || $v[0] === "'")) {
            $q = $v[0]; if (str_ends_with($v, $q)) $v = substr($v, 1, -1);
        }
        $_ENV[$k] = $v;
        if (function_exists('putenv')) { @putenv("$k=$v"); }
    }
})();

/* ------------------------------------------------------------------ */
/*  Encoding + Timezone                                               */
/* ------------------------------------------------------------------ */
@ini_set('default_charset', 'UTF-8');
if (function_exists('mb_internal_encoding')) { @mb_internal_encoding('UTF-8'); }
date_default_timezone_set(kf_get_env('APP_TZ', 'UTC') ?: 'UTC');

/* ------------------------------------------------------------------ */
/*  HTTPS / Host canonicalization                                     */
/* ------------------------------------------------------------------ */
$httpsOn = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on');
$xfp = strtolower($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '');
if ($xfp) $httpsOn = ($xfp === 'https');
$cfVisitor = $_SERVER['HTTP_CF_VISITOR'] ?? '';
if ($cfVisitor) { $j = json_decode($cfVisitor, true); if (is_array($j) && !empty($j['scheme'])) $httpsOn = (strtolower((string)$j['scheme']) === 'https'); }
if ($httpsOn && (empty($_SERVER['HTTPS']) || $_SERVER['HTTPS'] !== 'on')) $_SERVER['HTTPS'] = 'on';
if (kf_bool_env('FORCE_HTTPS', false) && !$httpsOn) {
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $uri  = $_SERVER['REQUEST_URI'] ?? '/';
    header('Location: https://' . $host . $uri, true, 301); exit;
}
$forceHost = kf_get_env('FORCE_HOST', '');
if ($forceHost !== '' && !empty($_SERVER['HTTP_HOST']) && strcasecmp($_SERVER['HTTP_HOST'], $forceHost) !== 0) {
    $scheme = $httpsOn ? 'https' : 'http';
    header('Location: '.$scheme.'://'.$forceHost.($_SERVER['REQUEST_URI'] ?? '/'), true, 301); exit;
}

/* ------------------------------------------------------------------ */
/*  APP_URL constant                                                  */
/* ------------------------------------------------------------------ */
if (!defined('APP_URL')) {
    $envUrl = rtrim((string)kf_get_env('APP_URL', ''), '/');
    if ($envUrl === '') {
        $scheme = $httpsOn ? 'https' : 'http';
        $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
        define('APP_URL', $scheme.'://'.$host);
    } else {
        define('APP_URL', $envUrl);
    }
}

/* ------------------------------------------------------------------ */
/*  Sessions (domain-aware cookie)                                    */
/* ------------------------------------------------------------------ */
$cookieDomain = kf_get_env('COOKIE_DOMAIN', ''); // e.g. .klinflow.com
$sessionName  = kf_get_env('SESSION_NAME', 'KLINFLOW_SESS') ?: 'KLINFLOW_SESS';
$secureCookie = $httpsOn;

@ini_set('session.use_strict_mode', '1');
@ini_set('session.use_cookies', '1');
@ini_set('session.use_only_cookies', '1');
@ini_set('session.cookie_httponly', '1');
@ini_set('session.cookie_samesite', 'Lax');
@ini_set('session.cache_limiter', 'nocache');

$params = [
    'lifetime' => 0,
    'path'     => '/',
    'domain'   => $cookieDomain ?: '',
    'secure'   => $secureCookie,
    'httponly' => true,
    'samesite' => 'Lax',
];
if (PHP_VERSION_ID >= 70300) {
    session_set_cookie_params($params);
} else {
    // PHP 7.2 legacy signature
    session_set_cookie_params(0, '/', $cookieDomain ?: '', $secureCookie, true);
}
session_name($sessionName);
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
    if (PHP_VERSION_ID < 70300 && $cookieDomain) {
        $c = session_name().'='.session_id().'; Path=/; HttpOnly'.($secureCookie?'; Secure':'').'; SameSite=Lax; Domain='.$cookieDomain;
        header('Set-Cookie: '.$c, false);
    }
}

/* ------------------------------------------------------------------ */
/*  >>> Robust CSRF bridge for /tenant/login                          */
/* ------------------------------------------------------------------ */
$__method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$__path   = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);

if ($__path === '/tenant/login') {
    // Always load Csrf
    if (!class_exists('\\Shared\\Csrf')) { require_once $ROOT.'/shared/Csrf.php'; }

    if ($__method === 'GET') {
        // Ensure token exists and emit BOTH cookie names, domain-aware
        $t = \Shared\Csrf::token('tenant');

        // Preferred helper (domain-aware if provided)
        $dom = $cookieDomain ?: null;
        if (method_exists('\\Shared\\Csrf', 'issueCookie')) {
            \Shared\Csrf::issueCookie('tenant', $dom, 3600);
        }

        // Also set generic cookie some middlewares expect
        $meta = [
            'expires'  => time()+3600,
            'path'     => '/',
            'secure'   => $secureCookie,
            'httponly' => false,
            'samesite' => 'Lax',
        ];
        if ($cookieDomain) $meta['domain'] = $cookieDomain;
        setcookie('XSRF-TOKEN', $t, $meta);

        // No-cache to prevent stale form
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Pragma: no-cache');
        header('Expires: 0');

    } elseif ($__method === 'POST') {
        // Normalize sources
        $posted = $_POST['_csrf'] ?? $_POST['_token'] ?? '';
        if ($posted === '' && isset($_SERVER['HTTP_X_CSRF_TOKEN'])) $posted = (string)$_SERVER['HTTP_X_CSRF_TOKEN'];
        if ($posted === '' && isset($_SERVER['HTTP_X_XSRF_TOKEN'])) $posted = (string)$_SERVER['HTTP_X_XSRF_TOKEN'];
        $posted = (string)$posted;

        // Gather cookies from both names
        $cookies = [];
        $nsName  = method_exists('\\Shared\\Csrf','cookieToken') ? \Shared\Csrf::cookieToken('tenant') : '';
        if ($nsName !== '') $cookies[] = $nsName;
        if (!empty($_COOKIE['XSRF-TOKEN-tenant'])) $cookies[] = (string)$_COOKIE['XSRF-TOKEN-tenant'];
        if (!empty($_COOKIE['XSRF-TOKEN']))        $cookies[] = (string)$_COOKIE['XSRF-TOKEN'];

        // 1) Session verify
        $okSession = ($posted !== '' && \Shared\Csrf::verify($posted, 'tenant'));

        // 2) Cookie verify (any of them)
        $okCookie = false;
        if ($posted !== '') {
            foreach ($cookies as $c) { if ($c !== '' && hash_equals($c, $posted)) { $okCookie = true; break; } }
        }

        // If cookie matched but session not, sync session token so downstream checks pass
        if ($okCookie && !$okSession) {
            $_SESSION['_csrf_tenant'] = $posted;
            $okSession = true;
        }

        if (!$okCookie && !$okSession) {
            http_response_code(419);
            echo 'CSRF token mismatch.';
            exit;
        }

        // Mark as accepted for any later middleware
        $_SERVER['KF_CSRF_TENANT_OK'] = '1';
        if (!defined('KF_CSRF_TENANT_OK')) { define('KF_CSRF_TENANT_OK', true); }
    }
}

/* ------------------------------------------------------------------ */
/*  Error reporting                                                   */
/* ------------------------------------------------------------------ */
$debug = kf_bool_env('APP_DEBUG', false);
if ($debug) {
    @ini_set('display_errors', '1');
    @ini_set('display_startup_errors', '1');
    error_reporting(E_ALL);
} else {
    @ini_set('display_errors', '0');
    error_reporting(E_ALL & ~E_NOTICE & ~E_DEPRECATED & ~E_STRICT);
}

/* ------------------------------------------------------------------ */
/*  Optional: remember-me auto login                                  */
/* ------------------------------------------------------------------ */
try {
    if (class_exists('\\Shared\\AuthRemember') && method_exists('\\Shared\\AuthRemember', 'autoLogin')) {
        \Shared\AuthRemember::autoLogin();
    }
} catch (\Throwable $e) {
    $logDir = $ROOT . '/storage/logs';
    if (!is_dir($logDir)) @mkdir($logDir, 0775, true);
    @file_put_contents($logDir . '/app.log', date('c') . " [autoLogin] " . $e . "\n", FILE_APPEND);
}

/* ------------------------------------------------------------------ */
/*  Optional security headers                                         */
/* ------------------------------------------------------------------ */
// header('Referrer-Policy: strict-origin-when-cross-origin');
// header('X-Frame-Options: SAMEORIGIN');
// header('X-Content-Type-Options: nosniff');
// header('Permissions-Policy: camera=(), microphone=(), geolocation=()');

/* ------------------------------------------------------------------ */
/*  Minimal fatal logger                                              */
/* ------------------------------------------------------------------ */
if (!isset($GLOBALS['__KF_FATAL_LOGGER_SET'])) {
    $GLOBALS['__KF_FATAL_LOGGER_SET'] = true;
    register_shutdown_function(function () use ($ROOT) {
        $err = error_get_last(); if (!$err) return;
        $fatalTypes = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR];
        if (!in_array($err['type'], $fatalTypes, true)) return;
        $logDir = $ROOT . '/storage/logs';
        if (!is_dir($logDir)) @mkdir($logDir, 0775, true);
        $line = sprintf("%s [fatal] %s in %s:%d UA=%s URI=%s\n",
            date('c'), $err['message'] ?? '', $err['file'] ?? 'n/a', (int)($err['line'] ?? 0),
            $_SERVER['HTTP_USER_AGENT'] ?? 'n/a', $_SERVER['REQUEST_URI'] ?? 'n/a');
        @file_put_contents($logDir . '/fatal.log', $line, FILE_APPEND);
    });
}

/* Kernel ready – front controller continues */