<?php
declare(strict_types=1);

namespace Modules\BizFlow\Controllers;

use PDO;
use Throwable;

/**
 * BizFlow BaseController (refined)
 *
 * - Centralized error handling (registered once)
 * - Tenant / org context helpers
 * - PDO helpers with sane default attributes
 * - Safe JSON output helper and typed input helpers
 * - Session read helper (returns session data and can close session to avoid locking)
 */
abstract class BaseController
{
    protected array $ctx = [];
    protected ?PDO $pdoInstance = null;

    // Ensure handlers are registered only once per process
    private static bool $handlersRegistered = false;

    public function __construct()
    {
        if ($this->debugOn()) {
            ini_set('display_errors', '1');
            ini_set('display_startup_errors', '1');
            error_reporting(E_ALL);
        }

        // Register global handlers once
        if (!self::$handlersRegistered) {
            $this->registerErrorHandlers();
            self::$handlersRegistered = true;
        }
    }

    /* ============================================================
       ERROR HANDLER REGISTRATION
    ============================================================ */

    private function registerErrorHandlers(): void
    {
        // Centralized exception handler
        set_exception_handler(function (Throwable $e) {
            $this->oops('Uncaught exception', $e);
        });

        // Centralized PHP error handler
        set_error_handler(function (int $severity, string $message, string $file, int $line) {
            // Respect @-silence
            if (!(error_reporting() & $severity)) {
                return false;
            }
            $this->oops(
                'PHP error',
                new \ErrorException($message, 0, $severity, $file, $line)
            );
            // returning true prevents the PHP internal handler from running
            return true;
        });

        // Fatal error handler
        register_shutdown_function(function () {
            $err = error_get_last();
            if ($err && in_array($err['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
                $this->oops(
                    'Fatal error',
                    new \ErrorException($err['message'], 0, $err['type'], $err['file'], $err['line'])
                );
            }
        });
    }

    /* ============================================================
       DEBUG + LOGGING + ERROR OUTPUT
    ============================================================ */

    protected function debugOn(): bool
    {
        if (getenv('APP_DEBUG') === '1' || getenv('APP_ENV') === 'local') return true;
        if (!empty($_GET['_debug'])) return true;
        if (!empty($_SESSION['_biz_debug'])) return true;
        if (($_SERVER['HTTP_X_BIZFLOW_DEBUG'] ?? '') === '1') return true;
        return false;
    }

    /**
     * Determine whether request prefers JSON:
     * - Accept header containing application/json
     * - URL with .json
     * - X-Requested-With: XMLHttpRequest
     */
    protected function wantsJSON(): bool
    {
        $uri = $_SERVER['REQUEST_URI'] ?? '';
        if (stripos($uri, '.json') !== false) return true;

        $accept = strtolower($_SERVER['HTTP_ACCEPT'] ?? '');
        if (strpos($accept, 'application/json') !== false) return true;

        if (strtolower($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'xmlhttprequest') return true;

        return false;
    }

    /**
     * Log to storage/logs/bizflow.log if writable; otherwise fallback to sys temp dir or error_log.
     */
    protected function logLine(string $line): void
    {
        $base = $_SERVER['DOCUMENT_ROOT'] ?? dirname(__DIR__, 4);
        $log  = rtrim($base, '/') . '/storage/logs/bizflow.log';
        $payload = '[' . date('Y-m-d H:i:s') . "] {$line}\n";

        try {
            if (@is_dir(dirname($log)) && is_writable(dirname($log))) {
                @file_put_contents($log, $payload, FILE_APPEND | LOCK_EX);
                return;
            }
            // fallback to sys temp dir
            $tmpLog = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'bizflow.log';
            @file_put_contents($tmpLog, $payload, FILE_APPEND | LOCK_EX);
        } catch (Throwable $e) {
            // last-resort fallback
            error_log('[BizFlow] ' . $line);
        }
    }

    /**
     * Safe JSON encoder used by controllers. Ensures header and fallbacks on encoding failure.
     */
    protected function safeJson($data, int $code = 200): void
    {
        http_response_code($code);
        header('Content-Type: application/json; charset=utf-8');

        $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            // Attempt to convert problematic data to UTF-8 strings
            $this->logLine('JSON encode failed: ' . json_last_error_msg());
            $safe = ['error' => 'Internal encoding error'];
            echo json_encode($safe, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            return;
        }
        echo $json;
    }

    /**
     * Emit error safely to JSON clients. Avoid leaking sensitive info in headers in prod.
     */
    protected function emitJsonError(string $msg, Throwable $e): void
    {
        // Log full details server-side
        $this->logLine($msg . ': ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());

        // Include limited info in headers only when debug enabled
        if ($this->debugOn()) {
            header('X-BizFlow-Error: ' . substr($e->getMessage() ?: $msg, 0, 180));
        }

        // Compose response
        $payload = $this->debugOn()
            ? [
                'error'   => $msg,
                'message' => $e->getMessage(),
                'file'    => $e->getFile(),
                'line'    => $e->getLine(),
                'trace'   => explode("\n", $e->getTraceAsString()),
            ]
            : ['error' => 'Unexpected error.'];

        $this->safeJson($payload, 500);
    }

    protected function emitHtmlError(string $msg, Throwable $e): void
    {
        http_response_code(500);
        if ($this->debugOn()) {
            header('X-BizFlow-Error: ' . substr($e->getMessage() ?: $msg, 0, 180));
            echo "<pre style='white-space:pre-wrap;font:14px/1.45 monospace;padding:16px;'>"
               . "BizFlow Error: {$msg}\n\n"
               . $e->getMessage() . "\n"
               . $e->getFile() . ':' . $e->getLine() . "\n\n"
               . $e->getTraceAsString()
               . "</pre>";
        } else {
            echo 'Unexpected error.';
        }
    }

    /**
     * Final crash handler used by all controllers.
     */
    protected function oops(string $msg, Throwable $e): void
    {
        // Log first
        $this->logLine($msg . ': ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());

        if (php_sapi_name() !== 'cli') {
            if ($this->wantsJSON()) {
                $this->emitJsonError($msg, $e);
            } else {
                $this->emitHtmlError($msg, $e);
            }
        } else {
            fwrite(STDERR, $msg . PHP_EOL . $e . PHP_EOL);
        }
        exit;
    }

    /* ============================================================
       TENANT / CONTEXT (ORG, SLUG, BASE URL)
    ============================================================ */

    /**
     * Normalise context.
     * Controllers should usually call:
     *   $ctx = $this->ensureBase($ctx);
     *
     * Note: ctx() reads session data but does NOT close session automatically.
     * If you only need session values and don't write, consider using readSession(true)
     * to avoid session file locking for concurrent AJAX requests.
     */
    public function ctx(array $incoming = []): array
    {
        if ($this->ctx) return $this->ctx;

        if (\PHP_SESSION_ACTIVE !== \session_status()) {
            @\session_start();
        }

        $orgArr = is_array($incoming['org'] ?? null)
            ? $incoming['org']
            : ($_SESSION['tenant_org'] ?? []);

        $slug = (string)($incoming['slug'] ?? ($orgArr['slug'] ?? ''));

        // Auto-detect /t/{slug}/apps/bizflow from URL when not explicitly passed
        if ($slug === '') {
            $path = (string)parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
            if (preg_match('#^/t/([^/]+)/apps/bizflow#i', $path, $m)) {
                $slug = $m[1];
            }
        }

        // module_dir comes from front.php ctx; fallback to modules/bizflow/src
        $moduleDir = rtrim((string)($incoming['module_dir'] ?? dirname(__DIR__, 1)), '/');

        $moduleBase = (string)(
            $incoming['module_base']
            ?? $incoming['base']
            ?? ($slug !== ''
                ? '/t/' . rawurlencode($slug) . '/apps/bizflow'
                : '/apps/bizflow')
        );

        $orgId  = $incoming['org_id']  ?? ($orgArr['id'] ?? ($_SESSION['org_id'] ?? null));
        $userId = $incoming['user_id'] ?? ($_SESSION['user_id'] ?? null);

        $this->ctx = [
            'slug'        => $slug,
            'org'         => $orgArr ?: [],
            'org_id'      => is_numeric($orgId)  ? (int)$orgId  : (int)($orgId ?? 0),
            'user_id'     => is_numeric($userId) ? (int)$userId : $userId,
            'module_dir'  => $moduleDir,
            'module_base' => $moduleBase,
            'base'        => $moduleBase,
        ];

        if ($this->ctx['slug'] === '' || $this->ctx['org_id'] <= 0) {
            $this->logLine('ctx(): incomplete tenant context: ' . json_encode([
                'slug'   => $this->ctx['slug'],
                'org_id' => $this->ctx['org_id'],
                'path'   => ($_SERVER['REQUEST_URI'] ?? ''),
            ]));
        }

        return $this->ctx;
    }

    /** POS-style alias */
    protected function ensureBase(array $ctx = []): array
    {
        return $this->ctx($ctx);
    }

    protected function requireOrg(): int
    {
        $c = $this->ctx();
        if (empty($c['org_id'])) {
            throw new \RuntimeException('Tenant (org_id) missing in context');
        }
        return (int)$c['org_id'];
    }

    /**
     * Read session contents and optionally close the session to avoid file locks.
     * Usage:
     *   $session = $this->readSession(true); // read and close
     */
    protected function readSession(bool $close = false): array
    {
        if (\PHP_SESSION_ACTIVE !== \session_status()) {
            @\session_start();
        }
        $s = $_SESSION ?? [];
        if ($close) {
            try { @\session_write_close(); } catch (Throwable $e) { /* ignore */ }
        }
        return is_array($s) ? $s : [];
    }

    /* ============================================================
       VIEW RENDERING
    ============================================================ */

    protected function resolveViewPath(string $viewFile): string
    {
        if (str_starts_with($viewFile, '/') || preg_match('#^[A-Za-z]:[\\\\/]#', $viewFile)) {
            return $viewFile;
        }

        $c        = $this->ctx();
        $viewsDir = rtrim($c['module_dir'] . '/Views', '/');

        $candidates = [
            "{$viewsDir}/{$viewFile}.php",
            "{$viewsDir}/{$viewFile}",
        ];

        foreach ($candidates as $p) {
            if (is_file($p)) return $p;
        }

        return $viewFile;
    }

    protected function view(string $viewFile, array $vars = [], ?string $layout = null): void
    {
        try {
            $c   = $this->ctx();
            $esc = static fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');

            $content_view = $this->resolveViewPath($viewFile);
            if (!is_file($content_view)) {
                throw new \RuntimeException("View missing: {$viewFile} → {$content_view}");
            }

            if ($layout === 'shell') {
                $viewsDir = rtrim($c['module_dir'] . '/Views', '/');
                $shell    = null;

                foreach ([
                    $viewsDir . '/shared/layouts/shell.php',
                    $viewsDir . '/layouts/shell.php',
                ] as $cand) {
                    if (is_file($cand)) {
                        $shell = $cand;
                        break;
                    }
                }
                if (!$shell) {
                    throw new \RuntimeException("Shell missing under {$viewsDir}");
                }

                (function (array $__vars, string $__shell, string $__content, array $__ctx, $esc) {
                    extract($__vars, EXTR_SKIP);
                    $ctx      = $__ctx;
                    $base     = $__ctx['module_base'] ?? '/apps/bizflow';
                    $h        = $esc;
                    $_content = $__content;
                    require $__shell;
                })(
                    array_merge($vars, ['base' => $c['module_base']]),
                    $shell,
                    $content_view,
                    $c,
                    $esc
                );
                return;
            }

            (function (array $__vars, string $__view, array $__ctx) {
                extract($__vars, EXTR_SKIP);
                $ctx  = $__ctx;
                $base = $__ctx['module_base'] ?? '/apps/bizflow';
                require $__view;
            })(
                array_merge($vars, ['base' => $c['module_base']]),
                $content_view,
                $c
            );
        } catch (Throwable $e) {
            $this->oops('View render failed', $e);
        }
    }

    /* ============================================================
       DB / PDO SETUP
    ============================================================ */

    protected function pdo(): PDO
    {
        if ($this->pdoInstance instanceof PDO) return $this->pdoInstance;

        // Shared\DB integration (if present)
        if (class_exists('\\Shared\\DB')) {
            try {
                $pdo = method_exists('\\Shared\\DB', 'tenant')
                    ? \Shared\DB::tenant()
                    : (method_exists('\\Shared\\DB', 'pdo') ? \Shared\DB::pdo() : null);

                if ($pdo instanceof PDO) {
                    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
                    // Note: do not change emulate_prepares here; Shared\DB expected to set its own options
                    return $this->pdoInstance = $pdo;
                }
            } catch (Throwable $e) {
                $this->logLine('Shared\\DB unavailable: ' . $e->getMessage());
            }
        }

        // Global tenant PDO
        if (isset($GLOBALS['TENANT_PDO']) && $GLOBALS['TENANT_PDO'] instanceof PDO) {
            return $this->pdoInstance = $GLOBALS['TENANT_PDO'];
        }

        // App helper
        if (function_exists('app_pdo')) {
            $pdo = app_pdo();
            if ($pdo instanceof PDO) {
                return $this->pdoInstance = $pdo;
            }
        }

        // Legacy global
        if (isset($GLOBALS['PDO']) && $GLOBALS['PDO'] instanceof PDO) {
            return $this->pdoInstance = $GLOBALS['PDO'];
        }

        // Env fallback (multi-tenant DSN or single DB)
        $dsn  = getenv('DB_TENANT_DSN') ?: '';
        $user = getenv('DB_TENANT_USER') ?: '';
        $pass = getenv('DB_TENANT_PASS') ?: '';

        if ($dsn === '') {
            $host = getenv('DB_HOST') ?: '127.0.0.1';
            $port = getenv('DB_PORT') ?: '3306';
            $name = getenv('DB_NAME') ?: '';
            $user = $user ?: (getenv('DB_USER') ?: '');
            $pass = $pass ?: (getenv('DB_PASS') ?: '');
            if ($name !== '' && $user !== '') {
                $dsn = "mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4";
            }
        }

        if ($dsn !== '' && $user !== '') {
            try {
                $pdo = new PDO($dsn, $user, $pass, [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    // Use native prepares where possible; keep emulation off for correct typing.
                    PDO::ATTR_EMULATE_PREPARES  => false,
                ]);
                // If MySQL, ensure session SQL mode and charset are correct (optional)
                try {
                    $pdo->exec("SET NAMES 'utf8mb4'");
                } catch (Throwable $e) { /* ignore */ }

                return $this->pdoInstance = $pdo;
            } catch (Throwable $e) {
                $this->logLine('PDO connect failed: ' . $e->getMessage());
                throw $e;
            }
        }

        throw new \RuntimeException('Tenant PDO not configured');
    }

    /* ============================================================
       SMALL SQL HELPERS
    ============================================================ */

    protected function exec(string $sql, array $bind = []): int
    {
        $st = $this->pdo()->prepare($sql);
        $st->execute($bind);
        return (int)$st->rowCount();
    }

    protected function row(string $sql, array $bind = []): ?array
    {
        $st = $this->pdo()->prepare($sql);
        $st->execute($bind);
        $r = $st->fetch();
        return $r === false ? null : $r;
    }

    protected function rows(string $sql, array $bind = []): array
    {
        $st = $this->pdo()->prepare($sql);
        $st->execute($bind);
        return $st->fetchAll();
    }

    protected function col(string $sql, array $bind = []): array
    {
        $st = $this->pdo()->prepare($sql);
        $st->execute($bind);
        return $st->fetchAll(PDO::FETCH_COLUMN, 0);
    }

    protected function val(string $sql, array $bind = [])
    {
        $st = $this->pdo()->prepare($sql);
        $st->execute($bind);
        $r = $st->fetch(PDO::FETCH_NUM);
        return $r === false ? null : $r[0];
    }

    /* ============================================================
       WEB HELPERS
    ============================================================ */

    protected function method(): string
    {
        return strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
    }

    protected function redirect(string $url, int $code = 302): void
    {
        header('Location: ' . $url, true, $code);
        exit;
    }

    /** Use safeJson wrapper */
    protected function json($data, int $code = 200): void
    {
        $this->safeJson($data, $code);
    }

    protected function postOnly(): void
    {
        if ($this->method() !== 'POST') {
            http_response_code(405);
            echo 'Method Not Allowed';
            exit;
        }
    }

    /** Raw input helper (legacy) */
    protected function input(string $key, $default = null)
    {
        return $_POST[$key] ?? $_GET[$key] ?? $default;
    }

    /** Typed input helpers */
    protected function inputInt(string $key, int $default = 0): int
    {
        $v = $this->input($key, null);
        if ($v === null) return $default;
        return (int)$v;
    }

    protected function inputString(string $key, string $default = ''): string
    {
        $v = $this->input($key, null);
        if ($v === null) return $default;
        return (string)$v;
    }

    protected function inputBool(string $key, bool $default = false): bool
    {
        $v = $this->input($key, null);
        if ($v === null) return $default;
        $s = strtolower((string)$v);
        return in_array($s, ['1', 'true', 'yes', 'on'], true);
    }
}