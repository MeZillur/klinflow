<?php
declare(strict_types=1);

namespace Shared\Debug;

final class ErrorHandler
{
    /** @var self|null */
    private static $instance = null;

    /** @var bool */
    private static $debug = false;

    /** @var string */
    private static $logFile = '/tmp/klinflow.log';

    /**
     * Boot the global error handler.
     *
     * Compatible signatures:
     *  - boot(bool $debug, ?string $logFile = null)
     *  - boot(array $opts) where $opts = [
     *        'debug'       => bool,        // preferred
     *        'production'  => bool,        // alt: sets debug = !production
     *        'log_file'    => string|null, // absolute path
     *    ]
     *
     * Returns the singleton instance (so callers may keep a ref if desired).
     */
    public static function boot($debugOrOpts = false, ?string $logFile = null): self
    {
        // Normalize inputs
        $debug = false;
        $log   = $logFile ?? self::$logFile;

        if (is_array($debugOrOpts)) {
            $opts  = $debugOrOpts;
            $hasD  = array_key_exists('debug', $opts);
            $hasP  = array_key_exists('production', $opts);

            if ($hasD)      { $debug = (bool)$opts['debug']; }
            elseif ($hasP)  { $debug = !(bool)$opts['production']; }
            else            { $debug = false; }

            if (!empty($opts['log_file']) && is_string($opts['log_file'])) {
                $log = $opts['log_file'];
            }
        } else {
            $debug = (bool)$debugOrOpts;
        }

        self::$debug  = $debug;
        self::$logFile = $log ?: self::$logFile;

        // PHP error reporting
        error_reporting(E_ALL);
        ini_set('display_errors', $debug ? '1' : '0');
        ini_set('display_startup_errors', $debug ? '1' : '0');
        ini_set('log_errors', '1');
        ini_set('error_log', self::$logFile);

        // Convert warnings/notices to exceptions (respect @ operator)
        set_error_handler(function ($severity, $message, $file, $line) {
            if (!(error_reporting() & $severity)) {
                return false;
            }
            throw new \ErrorException($message, 0, $severity, $file, $line);
        });

        // Central throwable handler
        set_exception_handler([self::class, 'handleThrowable']);

        // Fatal shutdown handler (no white screens)
        register_shutdown_function(function () {
            $e = error_get_last();
            if ($e && in_array($e['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR], true)) {
                $t = new \ErrorException($e['message'] ?? 'Fatal error', 0, $e['type'], $e['file'] ?? 'unknown', (int)($e['line'] ?? 0));
                self::handleThrowable($t);
            }
        });

        // Build and memoize instance
        if (!self::$instance) self::$instance = new self();
        return self::$instance;
    }

    /** Render any Throwable (HTML or JSON). Safe in production. */
    public static function handleThrowable(\Throwable $e): void
    {
        self::rotateIfLarge(self::$logFile);
        self::log($e);

        // Ensure a status
        if (http_response_code() < 400) {
            http_response_code(500);
        }

        $wantsJson = self::wantsJson();
        if ($wantsJson) {
            self::emitHeader('Content-Type', 'application/json; charset=utf-8');
            echo json_encode(self::jsonPayload($e, self::$debug), JSON_UNESCAPED_SLASHES);
        } else {
            self::emitHeader('Content-Type', 'text/html; charset=utf-8');
            echo self::htmlPage($e, self::$debug);
        }

        if (function_exists('fastcgi_finish_request')) { @fastcgi_finish_request(); }
    }

    /* ---------- helpers ---------- */

    private static function wantsJson(): bool
    {
        $accept = strtolower($_SERVER['HTTP_ACCEPT'] ?? '');
        $xhr    = strtolower($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '');
        $path   = (string)parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
        $ctype  = strtolower($_SERVER['CONTENT_TYPE'] ?? '');
        return (strpos($accept, 'application/json') !== false)
            || (strpos($accept, 'text/json') !== false)
            || ($xhr === 'xmlhttprequest')
            || (substr($path, -5) === '.json')
            || (strpos($ctype, 'application/json') !== false);
    }

    private static function emitHeader(string $k, string $v): void
    {
        // Don’t explode if headers already sent
        if (!headers_sent()) {
            header($k . ': ' . $v);
        }
    }

    private static function jsonPayload(\Throwable $e, bool $debug): array
    {
        $id = bin2hex(random_bytes(6));
        $base = ['ok' => false, 'error' => 'Internal Server Error', 'id' => $id];
        if ($debug) {
            $base['exception'] = [
                'type'    => get_class($e),
                'message' => $e->getMessage(),
                'file'    => $e->getFile(),
                'line'    => $e->getLine(),
                'trace'   => explode("\n", $e->getTraceAsString()),
            ];
            $base['request'] = [
                'method' => $_SERVER['REQUEST_METHOD'] ?? 'GET',
                'uri'    => $_SERVER['REQUEST_URI'] ?? '/',
                'query'  => $_GET ?? [],
            ];
        }
        return $base;
    }

    /** Red-flag page in debug; quiet card in prod. */
    private static function htmlPage(\Throwable $e, bool $debug): string
    {
        $id = bin2hex(random_bytes(6));
        if (!$debug) {
            $safe = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
            return <<<HTML
<!doctype html><meta charset="utf-8">
<title>Unexpected error</title>
<style>
  :root{--red:#b91c1c;--bg:#fff1f2}
  body{font-family:system-ui,-apple-system,Segoe UI,Roboto,Inter,sans-serif;background:#f8fafc;color:#111;margin:0;padding:24px}
  .card{max-width:840px;margin:auto;background:#fff;border:1px solid #e5e7eb;border-radius:14px;padding:20px;box-shadow:0 6px 16px rgba(0,0,0,.06)}
  .flag{display:inline-flex;align-items:center;gap:8px;background:var(--bg);border:1px solid var(--red);color:var(--red);font-weight:700;border-radius:9999px;padding:6px 10px}
  small{color:#6b7280}
</style>
<div class="card">
  <div class="flag">⚑ Error</div>
  <h1 style="margin:.6rem 0 0">Something went wrong</h1>
  <p>Please try again. If the problem persists, contact support and mention ID: <b>{$safe($id)}</b>.</p>
  <small>500 — Unexpected error</small>
</div>
HTML;
        }

        $esc   = fn($s)=>htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
        $trace = nl2br($esc($e->getTraceAsString()));
        $file  = $esc($e->getFile());
        $msg   = $esc($e->getMessage());
        $line  = (int)$e->getLine();
        $cls   = $esc(get_class($e));
        $method= $esc($_SERVER['REQUEST_METHOD'] ?? 'GET');
        $uri   = $esc($_SERVER['REQUEST_URI'] ?? '/');

        return <<<HTML
<!doctype html><meta charset="utf-8">
<title>⚑ {$cls} — {$msg}</title>
<style>
  :root{--red:#b91c1c;--bg:#fff1f2;--ink:#0f172a}
  body{font-family:system-ui,-apple-system,Segoe UI,Roboto,Inter,sans-serif;background:#f1f5f9;color:var(--ink);margin:0;padding:24px}
  .wrap{max-width:1100px;margin:auto}
  .card{background:#fff;border:1px solid #e5e7eb;border-radius:14px;padding:16px;margin:0 0 14px;box-shadow:0 6px 16px rgba(0,0,0,.06)}
  .flag{display:inline-flex;align-items:center;gap:8px;background:var(--bg);border:1px solid var(--red);color:var(--red);font-weight:800;border-radius:9999px;padding:6px 12px}
  .meta{display:grid;grid-template-columns:160px 1fr;gap:8px;color:#334155}
  pre{white-space:pre-wrap;word-break:break-word;background:#0b1020;color:#e2e8f0;padding:12px;border-radius:10px;overflow:auto}
  small{color:#64748b}
</style>
<div class="wrap">
  <div class="card">
    <div class="flag">⚑ Exception</div>
    <h2 style="margin:.6rem 0 0">{$msg}</h2>
    <div class="meta" style="margin-top:10px">
      <div>Type</div><div>{$cls}</div>
      <div>File</div><div>{$file}</div>
      <div>Line</div><div>{$line}</div>
      <div>Request</div><div>{$method} {$uri}</div>
    </div>
  </div>

  <div class="card">
    <strong>Stack trace</strong>
    <pre>{$trace}</pre>
  </div>

  <div class="card">
    <details open><summary><b>\$_GET</b></summary><pre>{$esc(print_r($_GET ?? [], true))}</pre></details>
    <details open style="margin-top:8px"><summary><b>\$_POST</b></summary><pre>{$esc(print_r($_POST ?? [], true))}</pre></details>
    <details open style="margin-top:8px"><summary><b>\$_SESSION</b></summary><pre>{$esc(print_r($_SESSION ?? [], true))}</pre></details>
  </div>

  <small>Debug is ON. Set APP_DEBUG=false in .env for production-safe output.</small>
</div>
HTML;
    }

    private static function log(\Throwable $e): void
    {
        $line = sprintf("[%s] %s: %s in %s:%d\n%s\n\n",
            date('Y-m-d H:i:s'),
            get_class($e),
            $e->getMessage(),
            $e->getFile(),
            $e->getLine(),
            $e->getTraceAsString()
        );
        try { error_log($line); } catch (\Throwable $ignored) {}
    }

    private static function rotateIfLarge(string $file, int $maxBytes = 10 * 1024 * 1024): void
    {
        try { if (@filesize($file) > $maxBytes) { @rename($file, $file.'.'.date('Ymd_His')); } }
        catch (\Throwable $ignored) {}
    }
}