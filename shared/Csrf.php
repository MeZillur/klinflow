<?php
declare(strict_types=1);

namespace Shared;

/**
 * CSRF helper with:
 * - Namespaced tokens (per module/tenant).
 * - Session-backed token + optional double-submit cookie.
 * - Flexible verification: POST _csrf/_token or headers X-CSRF-TOKEN / X-XSRF-TOKEN.
 * - Cookie domain defaults to COOKIE_DOMAIN from .env when not passed.
 */
final class Csrf
{
    private const DEFAULT_FIELD = '_csrf';
    private const BASE_COOKIE   = 'XSRF-TOKEN';

    /* ---------- Public API ---------- */

    public static function token(string $namespace = ''): string
    {
        self::ensureSession();
        $key = self::slot($namespace);
        if (empty($_SESSION[$key]) || !is_string($_SESSION[$key])) {
            $_SESSION[$key] = bin2hex(random_bytes(32));
        }
        return $_SESSION[$key];
    }

    /**
     * Issue a SameSite=Lax cookie copy (double-submit).
     * If $domain is null, uses COOKIE_DOMAIN from env (e.g., ".klinflow.com").
     */
    public static function issueCookie(string $namespace = '', ?string $domain = null, int $ttlSeconds = 3600): void
    {
        $token = self::token($namespace);
        $name  = self::cookieName($namespace);
        $domain = $domain ?? self::cookieDomainFromEnv();

        $params = [
            'expires'  => time() + $ttlSeconds,
            'path'     => '/',
            'secure'   => self::isHttps(),
            'httponly' => false,
            'samesite' => 'Lax',
        ];
        if ($domain) $params['domain'] = $domain;

        setcookie($name, $token, $params);
    }

    /** Return cookie value for a namespace (if any). */
    public static function cookieToken(string $namespace = ''): string
    {
        return (string)($_COOKIE[self::cookieName($namespace)] ?? '');
    }

    /** Render hidden input field for the token (defaults to name "_csrf") */
    public static function field(string $namespace = '', string $fieldName = self::DEFAULT_FIELD): string
    {
        $t = self::token($namespace);
        $h = htmlspecialchars($t, ENT_QUOTES, 'UTF-8');
        $n = htmlspecialchars($fieldName, ENT_QUOTES, 'UTF-8');
        return '<input type="hidden" name="'.$n.'" value="'.$h.'">';
    }

    /** Verify session OR (double-submit) cookie. */
    public static function verify(?string $incoming, string $namespace = ''): bool
    {
        self::ensureSession();

        $val = (string)($incoming ?? '');
        if ($val === '' && isset($_POST[self::DEFAULT_FIELD])) $val = (string)$_POST[self::DEFAULT_FIELD];
        if ($val === '' && isset($_POST['_token']))            $val = (string)$_POST['_token'];
        if ($val === '' && isset($_SERVER['HTTP_X_CSRF_TOKEN']))  $val = (string)$_SERVER['HTTP_X_CSRF_TOKEN'];
        if ($val === '' && isset($_SERVER['HTTP_X_XSRF_TOKEN']))  $val = (string)$_SERVER['HTTP_X_XSRF_TOKEN'];
        if ($val === '') return false;

        $key    = self::slot($namespace);
        $stored = $_SESSION[$key] ?? null;
        if (is_string($stored) && hash_equals($stored, $val)) return true;

        $cookie = self::cookieToken($namespace);
        if ($cookie !== '' && hash_equals($cookie, $val)) return true;

        return false;
    }

    /** Rotate token and refresh cookie if present. */
    public static function rotate(string $namespace = '', ?string $domain = null, int $ttlSeconds = 3600): string
    {
        self::ensureSession();
        $key = self::slot($namespace);
        $_SESSION[$key] = bin2hex(random_bytes(32));

        $cookieName = self::cookieName($namespace);
        if (isset($_COOKIE[$cookieName])) {
            self::issueCookie($namespace, $domain ?? self::cookieDomainFromEnv(), $ttlSeconds);
        }
        return $_SESSION[$key];
    }

    /* ---------- Internals ---------- */

    private static function ensureSession(): void
    {
        if (\PHP_SESSION_ACTIVE === \session_status()) return;
        @\ini_set('session.use_only_cookies', '1');
        @\ini_set('session.use_strict_mode', '1');
        @\ini_set('session.cookie_httponly', '1');
        @\ini_set('session.cookie_samesite', 'Lax');
        if (self::isHttps()) @\ini_set('session.cookie_secure', '1');
        if (!@\session_start()) {
            http_response_code(500);
            echo 'Session could not be started.';
            exit;
        }
    }

    private static function isHttps(): bool
    {
        return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on')
            || (strtolower((string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https');
    }

    private static function slot(string $namespace): string
    {
        $ns = self::clean($namespace);
        return $ns === '' ? self::DEFAULT_FIELD : self::DEFAULT_FIELD.'_'.$ns;
    }

    private static function cookieName(string $namespace): string
    {
        $ns = self::clean($namespace);
        return $ns === '' ? self::BASE_COOKIE : self::BASE_COOKIE.'-'.$ns;
    }

    private static function clean(string $s): string
    {
        $s = trim($s);
        return preg_replace('~[^a-z0-9_]+~i', '', $s);
    }

    private static function cookieDomainFromEnv(): ?string
    {
        $d = (string)(getenv('COOKIE_DOMAIN') ?: '');
        $d = trim($d);
        return $d === '' ? null : $d; // e.g., ".klinflow.com"
    }
}