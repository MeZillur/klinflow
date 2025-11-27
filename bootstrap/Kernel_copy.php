<?php
declare(strict_types=1);

/**
 * KlinFlow Bootstrap Kernel (PHP 7.2+ safe)
 * - PSR-4-ish autoloader for: Shared\, App\, Modules\
 * - Loads helpers (shared + app)
 * - .env loader (quoted values supported)
 * - Timezone & HTTPS (proxy-aware: X-Forwarded-Proto / CF-Visitor)
 * - Secure session cookies with cross-version compatibility
 * - Error reporting from APP_DEBUG
 * - Optional remember-me autoLogin()
 *
 * NOTE: This file keeps existing logic intact.
 * Additions are defensive-only and guarded to avoid breaking live modules.
 */

if (!defined('BASE_PATH')) {
    define('BASE_PATH', dirname(__DIR__));
}
$ROOT = BASE_PATH;

/* ------------------------------------------------------------------ */
/*  Tiny polyfills for PHP 7.2                                        */
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
/*  Small helpers (non-breaking)                                      */
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

    // Shared\*  -> /shared/*
    if (str_starts_with($class, 'Shared\\')) {
        $rel  = substr($class, 7);
        $file = $ROOT . '/shared/' . str_replace('\\', '/', $rel) . '.php';
        if (is_file($file)) { require $file; return; }
    }

    // App\*     -> /app/*
    if (str_starts_with($class, 'App\\')) {
        $rel  = substr($class, 4);
        $file = $ROOT . '/app/' . str_replace('\\', '/', $rel) . '.php';
        if (is_file($file)) { require $file; return; }
    }

    // Modules\* -> /modules/*
    // Supports BOTH:
    //   modules/<Module>/<Class>.php
    //   modules/<Module>/src/<Sub\Path\Class>.php
    if (str_starts_with($class, 'Modules\\')) {
        $rel = substr($class, 8);
        // 0) Direct flat
        $direct = $ROOT . '/modules/' . str_replace('\\', '/', $rel) . '.php';
        if (is_file($direct)) { require $direct; return; }

        // 1) Split into "<Module>" + "Rest\Of\Path"
        $parts = explode('\\', $rel, 2);
        if (count($parts) === 2) {
            $module = $parts[0];
            $rest   = $parts[1];

            // a) primary under src/
            $fileSrc = $ROOT . '/modules/' . $module . '/src/' . str_replace('\\', '/', $rest) . '.php';
            if (is_file($fileSrc)) { require $fileSrc; return; }

            // b) fallback flat
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
/*  .env loader (quoted values OK; comments respected)                */
/* ------------------------------------------------------------------ */
(function () use ($ROOT) {
    $env = $ROOT . '/.env';
    if (!is_file($env)) return;

    $lines = @file($env, FILE_IGNORE_NEW_LINES);
    if (!$lines) return;

    foreach ($lines as $line) {
        if ($line === '' || $line === false) continue;

        // Trim whitespace
        $raw = ltrim($line);

        // Full-line comment?
        if ($raw === '' || $raw[0] === '#') continue;

        // Split only on the first '='
        $pos = strpos($raw, '=');
        if ($pos === false) continue;

        $k = trim(substr($raw, 0, $pos));
        if ($k === '') continue;

        $v = trim(substr($raw, $pos + 1));

        // If quoted, strip matching quotes
        if ($v !== '' && ($v[0] === '"' || $v[0] === "'")) {
            $q = $v[0];
            if (str_ends_with($v, $q)) {
                $v = substr($v, 1, -1);
            }
        }

        // Export
        $_ENV[$k] = $v;
        // putenv may be disabled in some shared hosting—guard it:
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
/*  HTTPS force (proxy-aware: X-Forwarded-Proto / CF-Visitor)         */
/* ------------------------------------------------------------------ */
$httpsOn = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on');

// Honor X-Forwarded-Proto
$xfp = strtolower($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '');
if ($xfp) $httpsOn = ($xfp === 'https');

// Honor Cloudflare CF-Visitor (JSON)
$cfVisitor = $_SERVER['HTTP_CF_VISITOR'] ?? '';
if ($cfVisitor) {
    $j = json_decode($cfVisitor, true);
    if (is_array($j) && !empty($j['scheme'])) {
        $httpsOn = (strtolower((string)$j['scheme']) === 'https');
    }
}

// Normalize superglobal for downstream libs that check it
if ($httpsOn && (empty($_SERVER['HTTPS']) || $_SERVER['HTTPS'] !== 'on')) {
    $_SERVER['HTTPS'] = 'on';
}

// Optional forced HTTPS
$forceHttps = kf_bool_env('FORCE_HTTPS', false);
if ($forceHttps && !$httpsOn) {
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $uri  = $_SERVER['REQUEST_URI'] ?? '/';
    header('Location: https://' . $host . $uri, true, 301);
    exit;
}

/* ------------------------------------------------------------------ */
/*  App URL normalization (non-breaking; used by link builders)       */
/* ------------------------------------------------------------------ */
if (!defined('APP_URL')) {
    $envUrl = rtrim((string)kf_get_env('APP_URL', ''), '/');
    if ($envUrl === '') {
        $scheme = $httpsOn ? 'https' : 'http';
        $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $base   = $scheme . '://' . $host;
        define('APP_URL', $base);
    } else {
        define('APP_URL', $envUrl);
    }
}

/* ------------------------------------------------------------------ */
/*  Sessions (secure cookie + custom save_path, PHP 7.2–8.x)          */
/* ------------------------------------------------------------------ */
$sessionPath = $ROOT . '/storage/sessions';
if (!is_dir($sessionPath)) @mkdir($sessionPath, 0775, true);
@ini_set('session.save_path', $sessionPath);

// Extra defensive session flags (keeps existing behavior intact)
@ini_set('session.use_strict_mode', '1');
@ini_set('session.use_cookies', '1');
@ini_set('session.use_only_cookies', '1');
@ini_set('session.cookie_httponly', '1');
@ini_set('session.cache_limiter', 'nocache');

$secureCookie = $httpsOn;
$sessionName  = kf_get_env('SESSION_NAME', 'KLINFLOW_SESS') ?: 'KLINFLOW_SESS';

if (PHP_VERSION_ID >= 70300) {
    // Array signature supported from PHP 7.3+
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'domain'   => '',             // current host
        'secure'   => $secureCookie,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
} else {
    // PHP 7.2 legacy signature (no SameSite here)
    session_set_cookie_params(0, '/', '', $secureCookie, true);
    // SameSite=Lax best effort for 7.2 via header after session_start()
}

session_name($sessionName);
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
    // Best-effort SameSite for PHP 7.2
    if (PHP_VERSION_ID < 70300) {
        $params = session_get_cookie_params();
        $cookie = session_name() . '=' . session_id()
                . '; Path=' . ($params['path'] ?: '/')
                . '; HttpOnly'
                . ($secureCookie ? '; Secure' : '')
                . '; SameSite=Lax';
        header('Set-Cookie: ' . $cookie, false);
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
/*  Optional: remember-me auto login (non-fatal)                      */
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
/*  (Optional) Security headers                                       */
/* ------------------------------------------------------------------ */
// header('Referrer-Policy: strict-origin-when-cross-origin');
// header('X-Frame-Options: SAMEORIGIN');
// header('X-Content-Type-Options: nosniff');
// header('Permissions-Policy: camera=(), microphone=(), geolocation=()');

/* ------------------------------------------------------------------ */
/*  Optional: small fatal logger (does not change your controllers)   */
/*  Only logs to storage/logs/fatal.log and does NOT render HTML.     */
/* ------------------------------------------------------------------ */
if (!isset($GLOBALS['__KF_FATAL_LOGGER_SET'])) {
    $GLOBALS['__KF_FATAL_LOGGER_SET'] = true;
    register_shutdown_function(function () use ($debug, $ROOT) {
        $err = error_get_last();
        if (!$err) return;
        $fatalTypes = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR];
        if (!in_array($err['type'], $fatalTypes, true)) return;

        $logDir = $ROOT . '/storage/logs';
        if (!is_dir($logDir)) @mkdir($logDir, 0775, true);
        $line = sprintf(
            "%s [fatal] %s in %s:%d UA=%s URI=%s\n",
            date('c'),
            $err['message'] ?? '',
            $err['file'] ?? 'n/a',
            (int)($err['line'] ?? 0),
            $_SERVER['HTTP_USER_AGENT'] ?? 'n/a',
            $_SERVER['REQUEST_URI'] ?? 'n/a'
        );
        @file_put_contents($logDir . '/fatal.log', $line, FILE_APPEND);
        // The actual HTML banners are handled by your front controllers already.
    });
}

/* Kernel ready – front controller continues */