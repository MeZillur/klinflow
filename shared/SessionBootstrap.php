<?php
declare(strict_types=1);

namespace Shared;

/**
 * Reliable session starter for Nginx/PHP-FPM on a single VPS.
 * - Picks a writable save_path that respects open_basedir.
 * - Creates the directory if missing.
 * - Applies safe defaults (httponly, samesite=Lax, strict_mode).
 * - Logs clear diagnostics instead of failing silently.
 */
final class SessionBootstrap
{
    /** Set this to a project-local sessions dir if you want a fixed path */
    private const FALLBACK_DIR_NAME = 'storage/sessions';   // relative under project/docroot
    private const APP_COOKIE_NAME   = 'PHPSESSID';

    public static function ensureStarted(): void
    {
        if (\PHP_SESSION_ACTIVE === \session_status()) return;

        // Base flags (safe on single VPS; also fine with Redis if you switch later)
        @\ini_set('session.use_only_cookies', '1');
        @\ini_set('session.use_strict_mode', '1');
        @\ini_set('session.cookie_httponly', '1');
        @\ini_set('session.cookie_samesite', 'Lax');
        if (self::isHttps()) @\ini_set('session.cookie_secure', '1');

        \session_name(self::APP_COOKIE_NAME);

        // Try current save_path first; otherwise compute candidates
        $candidates = self::collectCandidatePaths();
        $errors = [];

        foreach ($candidates as $dir) {
            if (!$dir) continue;
            // Ensure directory exists & writable (respect open_basedir)
            if (!self::withinOpenBaseDir($dir)) { $errors[] = "blocked by open_basedir: $dir"; continue; }
            if (!is_dir($dir)) { @mkdir($dir, 0770, true); }
            if (!is_dir($dir) || !is_writable($dir)) { $errors[] = "not writable: $dir"; continue; }

            @\ini_set('session.save_path', $dir);
            if (@\session_start()) {
                // Success — done
                return;
            }
            $errors[] = "session_start failed on $dir";
        }

        // Last resort: tell operator exactly what to fix, but don’t leak paths to users
        error_log('[SessionBootstrap] Unable to start session. Tried: '.implode(' | ', $errors).
                  '; open_basedir='.(\ini_get('open_basedir') ?: '(none)').
                  '; current save_path='.(\ini_get('session.save_path') ?: '(empty)'));
        http_response_code(500);
        echo 'Session could not be started (server session storage misconfigured).';
        exit;
    }

    /** Find plausible writable directories, ordered by likelihood of success */
    private static function collectCandidatePaths(): array
    {
        $cur = (string)\ini_get('session.save_path');
        $doc = rtrim((string)($_SERVER['DOCUMENT_ROOT'] ?? ''), '/');
        $base = \dirname(__DIR__, 2); // project base (adjust if needed)

        // Explicit env var overrides everything if set
        $env = (string)($_ENV['SESSION_SAVE_PATH'] ?? $_SERVER['SESSION_SAVE_PATH'] ?? '');

        $projectLocal  = $base ? $base.'/'.self::FALLBACK_DIR_NAME : '';
        $docrootLocal  = $doc  ? $doc .'/'.self::FALLBACK_DIR_NAME : '';
        $tmpLocal      = \sys_get_temp_dir().'/php_sessions';

        // On Debian/Ubuntu, this exists; may be blocked by open_basedir on shared hosts
        $debianDefault = '/var/lib/php/sessions';

        // De-duplicate while preserving order
        $paths = array_values(array_unique(array_filter([
            $cur ?: null,
            $env ?: null,
            $projectLocal ?: null,
            $docrootLocal ?: null,
            $tmpLocal,
            $debianDefault,
        ])));

        return $paths;
    }

    private static function withinOpenBaseDir(string $path): bool
    {
        $ob = (string)\ini_get('open_basedir');
        if ($ob === '') return true;
        $allowed = array_filter(array_map('trim', preg_split('/[:;]+/', $ob)));
        // If directory doesn’t exist yet, we approximate without realpath
        $candidate = \realpath($path) ?: $path;
        foreach ($allowed as $base) {
            $baseReal = \realpath($base) ?: $base;
            if (strpos($candidate, rtrim($baseReal, '/').'/') === 0 || $candidate === $baseReal) {
                return true;
            }
        }
        return false;
    }

    private static function isHttps(): bool
    {
        return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on')
            || (strtolower((string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https');
    }
}