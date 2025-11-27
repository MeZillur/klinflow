<?php
declare(strict_types=1);

namespace Modules\POS\Controllers;

use PDO;
use Throwable;

/**
 * BaseController
 *  - Central error handling
 *  - Tenant / org context
 *  - Optional branding (logo, business info)
 *  - Optional branch helpers (multi-shop)
 *  - PDO + small SQL helpers
 *  - Simple web helpers (JSON / redirect / input)
 */
abstract class BaseController
{
    protected array $ctx = [];
    protected ?PDO $pdoInstance = null;

    /* ============================================================
       CONSTRUCTOR + GLOBAL ERROR HANDLERS
    ============================================================ */
    public function __construct()
    {
        // Turn on PHP error display in debug mode (DMS-style feel)
        if ($this->debugOn()) {
            ini_set('display_errors', '1');
            ini_set('display_startup_errors', '1');
            error_reporting(E_ALL);
        }

        // Centralized exception handler
        set_exception_handler(function (Throwable $e) {
            $this->oops('Uncaught exception', $e);
        });

        // Centralized PHP error handler
        set_error_handler(function ($severity, $message, $file, $line) {
            // Respect @-silence
            if (!(error_reporting() & $severity)) return false;
            $this->oops(
                'PHP error',
                new \ErrorException($message, 0, $severity, $file, $line)
            );
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
        if (!empty($_SESSION['_pos_debug'])) return true;
        if (($_SERVER['HTTP_X_POS_DEBUG'] ?? '') === '1') return true;
        return false;
    }

    protected function wantsJSON(): bool
    {
        $uri = $_SERVER['REQUEST_URI'] ?? '';
        if (stripos($uri, '.json') !== false) return true;
        $accept = strtolower($_SERVER['HTTP_ACCEPT'] ?? '');
        return strpos($accept, 'application/json') !== false;
    }

    protected function logLine(string $line): void
    {
        $base = $_SERVER['DOCUMENT_ROOT'] ?? dirname(__DIR__, 4);
        $log  = rtrim($base, '/').'/storage/logs/pos.log';
        $payload = '['.date('Y-m-d H:i:s')."] {$line}\n";

        if (@is_dir(dirname($log))) {
            @file_put_contents($log, $payload, FILE_APPEND);
        } else {
            error_log("[POS] ".$line);
        }
    }

    protected function emitJsonError(string $msg, Throwable $e): void
    {
        http_response_code(500);
        header('Content-Type: application/json; charset=utf-8');
        header('X-POS-Error: '.substr($e->getMessage() ?: $msg, 0, 180));

        echo json_encode(
            $this->debugOn()
                ? [
                    'error'   => $msg,
                    'message' => $e->getMessage(),
                    'file'    => $e->getFile(),
                    'line'    => $e->getLine(),
                    'trace'   => explode("\n", $e->getTraceAsString()),
                ]
                : ['error' => 'Unexpected error.'],
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        );
    }

    protected function emitHtmlError(string $msg, Throwable $e): void
    {
        http_response_code(500);
        header('X-POS-Error: '.substr($e->getMessage() ?: $msg, 0, 180));

        if ($this->debugOn()) {
            echo "<pre style='white-space:pre-wrap;font:14px/1.45 monospace; padding:16px;'>"
               . "POS Error: {$msg}\n\n"
               . $e->getMessage()."\n"
               . $e->getFile().':'.$e->getLine()."\n\n"
               . $e->getTraceAsString()
               . "</pre>";
        } else {
            echo 'Unexpected error.';
        }
    }

    /**
     * Final crash handler used by all controllers.
     * In CLI: prints to STDERR.
     * In web: simple HTML + optional trace (only in debug).
     */
    protected function oops(string $msg, Throwable $e): void
    {
        // Log to PHP error log
        error_log($msg . ': ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());

        if (php_sapi_name() !== 'cli') {
            http_response_code(500);
            echo '<h1>' . htmlspecialchars($msg, ENT_QUOTES, 'UTF-8') . '</h1>';
            echo '<p><strong>' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') . '</strong></p>';
            echo '<p><code>' . htmlspecialchars($e->getFile(), ENT_QUOTES, 'UTF-8') . ':' . $e->getLine() . '</code></p>';

            if ($this->debugOn()) {
                echo '<pre>' . htmlspecialchars($e->getTraceAsString(), ENT_QUOTES, 'UTF-8') . '</pre>';
            }
        } else {
            // CLI
            fwrite(STDERR, $msg . PHP_EOL . $e . PHP_EOL);
        }
        exit;
    }

    /* ============================================================
       BRANDING (BUSINESS NAME / LOGO / CONTACT)
       Stored in $_SESSION['pos_branding']
    ============================================================ */

    protected function branding(): array
    {
        if (session_status() !== PHP_SESSION_ACTIVE) @session_start();
        return $_SESSION['pos_branding'] ?? [];
    }

    /* ============================================================
       GENERIC TABLE / COLUMN HELPERS
       (Shared by Sales / Inventory / Branches)
    ============================================================ */

    /** Does a table exist in current DB? */
    protected function hasTable(PDO $pdo, string $table): bool
    {
        $st = $pdo->prepare(
            "SELECT 1
             FROM information_schema.tables
             WHERE table_schema = DATABASE()
               AND table_name = ?
             LIMIT 1"
        );
        $st->execute([$table]);
        return (bool)$st->fetchColumn();
    }

    /** Does a column exist on a given table? */
    protected function hasCol(PDO $pdo, string $table, string $col): bool
    {
        $st = $pdo->prepare(
            "SELECT 1
             FROM information_schema.columns
             WHERE table_schema = DATABASE()
               AND table_name = ?
               AND column_name = ?
             LIMIT 1"
        );
        $st->execute([$table, $col]);
        return (bool)$st->fetchColumn();
    }

    /** Pick first available table from candidates or throw. */
    protected function pickTable(PDO $pdo, array $candidates, string $err): string
    {
        foreach ($candidates as $t) {
            if ($this->hasTable($pdo, $t)) {
                return $t;
            }
        }
        throw new \RuntimeException($err);
    }

    /** Optional inventory table (per-branch stock). */
    protected function inventoryTable(PDO $pdo): ?string
    {
        foreach (['pos_inventory', 'pos_stock', 'inventory', 'stocks'] as $t) {
            if ($this->hasTable($pdo, $t)) return $t;
        }
        return null;
    }

    /** Convenience: normalize ctx from controller entrypoints. */
    protected function ensureBase(array $ctx = []): array
    {
        return $this->ctx($ctx);
    }

    /* ============================================================
       BRANCH HELPERS (MULTI-SHOP)
    ============================================================ */

    /**
     * Current branch id from session. 0 = no branch selected.
     */
    protected function currentBranchId(): int
    {
        if (session_status() !== PHP_SESSION_ACTIVE) @session_start();
        return isset($_SESSION['pos_branch_id']) ? (int)$_SESSION['pos_branch_id'] : 0;
    }

    /**
     * Persist currently selected branch id into session.
     */
    protected function setCurrentBranchId(?int $branchId): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) @session_start();
        $_SESSION['pos_branch_id'] = $branchId ? (int)$branchId : 0;
    }

    /**
     * Require branch (for areas where branch is mandatory).
     */
    protected function requireBranchId(): int
    {
        $id = $this->currentBranchId();
        if ($id <= 0) {
            throw new \RuntimeException('POS branch not selected');
        }
        return $id;
    }

    /**
     * Simple branch list for dropdown in header.
     * Safe against missing columns like sort_order / is_active.
     *
     * Returns rows: [id, name, code, (optional) address, ...]
     */
    protected function listBranches(PDO $pdo, int $orgId): array
    {
        if (!$this->hasTable($pdo, 'pos_branches')) {
            return [];
        }

        // Select core columns, plus optional ones if present
        $cols = ['id', 'name'];

        if ($this->hasCol($pdo, 'pos_branches', 'code')) {
            $cols[] = 'code';
        }
        if ($this->hasCol($pdo, 'pos_branches', 'address')) {
            $cols[] = 'address';
        }

        $sql = 'SELECT '.implode(',', $cols).' FROM pos_branches WHERE org_id = ?';

        // Optional active flag
        if ($this->hasCol($pdo, 'pos_branches', 'is_active')) {
            $sql .= ' AND (is_active = 1 OR is_active IS NULL)';
        }

        // ORDER BY with graceful fallback
        $order = [];
        if ($this->hasCol($pdo, 'pos_branches', 'sort_order')) {
            $order[] = 'sort_order IS NULL';
            $order[] = 'sort_order';
        }
        $order[] = 'name';

        $sql .= ' ORDER BY '.implode(',', $order);

        $st = $pdo->prepare($sql);
        $st->execute([$orgId]);

        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Older name kept for backwards compatibility.
     * Alias to listBranches().
     */
    protected function branches(PDO $pdo, int $orgId): array
    {
        return $this->listBranches($pdo, $orgId);
    }

    /* ============================================================
       TENANT / CONTEXT (ORG, SLUG, BASE URL)
    ============================================================ */

    public function ctx(array $incoming = []): array
    {
        if ($this->ctx) return $this->ctx;

        $orgArr = is_array($incoming['org'] ?? null)
            ? $incoming['org']
            : ($_SESSION['tenant_org'] ?? []);

        $slug = (string)($incoming['slug'] ?? $orgArr['slug'] ?? '');

        // Auto-detect /t/{slug}/apps/pos from URL when not explicitly passed
        if ($slug === '') {
            $path = (string)parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
            if (preg_match('#^/t/([^/]+)/apps/pos#i', $path, $m)) {
                $slug = $m[1];
            }
        }

        // modules/POS/src/Controllers → modules/POS
        $moduleDir = rtrim((string)($incoming['module_dir'] ?? dirname(__DIR__, 1)), '/');

        $moduleBase = (string)(
            $incoming['module_base']
            ?? $incoming['base']
            ?? ($slug !== '' ? '/t/'.rawurlencode($slug).'/apps/pos' : '/apps/pos')
        );

        $orgId    = $incoming['org_id']    ?? ($orgArr['id'] ?? ($_SESSION['org_id'] ?? null));
        $userId   = $incoming['user_id']   ?? ($_SESSION['user_id'] ?? null);
        $branchId = $incoming['branch_id'] ?? ($_SESSION['pos_branch_id'] ?? null);

        $this->ctx = [
            'slug'        => $slug,
            'org'         => $orgArr ?: [],
            'org_id'      => is_numeric($orgId)    ? (int)$orgId    : (int)($orgId ?? 0),
            'user_id'     => is_numeric($userId)   ? (int)$userId   : $userId,
            'branch_id'   => is_numeric($branchId) ? (int)$branchId : (int)($branchId ?? 0),
            'module_dir'  => $moduleDir,
            'module_base' => $moduleBase,
            'base'        => $moduleBase,
        ];

        if ($this->ctx['slug'] === '' || $this->ctx['org_id'] <= 0) {
            $this->logLine('ctx(): incomplete tenant context: '.json_encode([
                'slug'   => $this->ctx['slug'],
                'org_id' => $this->ctx['org_id'],
                'path'   => ($_SERVER['REQUEST_URI'] ?? ''),
            ]));
        }

        return $this->ctx;
    }

    protected function requireOrg(): int
    {
        $c = $this->ctx();
        if (empty($c['org_id'])) {
            throw new \RuntimeException('Tenant (org_id) missing in context');
        }
        return (int)$c['org_id'];
    }

    /* ============================================================
       VIEW RENDERING
    ============================================================ */

    /** Resolve a view key or file path to a concrete PHP file. */
    protected function resolveViewPath(string $viewFile): string
    {
        // Absolute path: use as-is
        if (str_starts_with($viewFile, '/') || preg_match('#^[A-Za-z]:[\\\\/]#', $viewFile)) {
            return $viewFile;
        }

        $c        = $this->ctx();
        $viewsDir = rtrim($c['module_dir'].'/Views', '/');

        $candidates = [
            "{$viewsDir}/{$viewFile}.php",
            "{$viewsDir}/{$viewFile}",
        ];

        foreach ($candidates as $p) {
            if (is_file($p)) return $p;
        }

        // Last resort: treat provided as absolute
        return $viewFile;
    }

    /**
     * Controllers should call:
     *   $this->view('sales/index', $vars, 'shell');
     * or:
     *   $this->view('sales/print_a4.php', $vars); // bare
     */
    protected function view(string $viewFile, array $vars = [], ?string $layout = null): void
    {
        try {
            $c   = $this->ctx();
            $esc = static fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');

            $content_view = $this->resolveViewPath($viewFile);
            if (!is_file($content_view)) {
                throw new \RuntimeException("View missing: {$viewFile} → {$content_view}");
            }

            // Full shell layout
            if ($layout === 'shell') {
                $viewsDir = rtrim($c['module_dir'].'/Views', '/');
                $shell    = null;

                foreach ([
                    $viewsDir.'/shared/layouts/shell.php',
                    $viewsDir.'/layouts/shell.php',
                ] as $cand) {
                    if (is_file($cand)) { $shell = $cand; break; }
                }
                if (!$shell) {
                    throw new \RuntimeException("Shell missing under {$viewsDir}");
                }

                (function(array $__vars, string $__shell, string $__content, array $__ctx, $esc) {
                    extract($__vars, EXTR_SKIP);
                    $ctx      = $__ctx;
                    $base     = $__ctx['module_base'] ?? '/apps/pos';
                    $h        = $esc;
                    $_content = $__content;   // shell includes this
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

            // Bare include (print pages, mini views, etc.)
            (function(array $__vars, string $__view, array $__ctx) {
                extract($__vars, EXTR_SKIP);
                $ctx  = $__ctx;
                $base = $__ctx['module_base'] ?? '/apps/pos';
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
                    return $this->pdoInstance = $pdo;
                }
            } catch (Throwable $e) {
                $this->logLine('Shared\\DB unavailable: '.$e->getMessage());
            }
        }

        // Global tenant PDO
        if (isset($GLOBALS['TENANT_PDO']) && $GLOBALS['TENANT_PDO'] instanceof PDO) {
            return $this->pdoInstance = $GLOBALS['TENANT_PDO'];
        }

        // App helper
        if (function_exists('app_pdo')) {
            $pdo = app_pdo();
            if ($pdo instanceof PDO) return $this->pdoInstance = $pdo;
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
            $pdo = new PDO($dsn, $user, $pass, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);
            return $this->pdoInstance = $pdo;
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

    protected function begin(): void
    {
        $this->pdo()->beginTransaction();
    }

    protected function commit(): void
    {
        $this->pdo()->commit();
    }

    protected function rollBack(): void
    {
        if ($this->pdo()->inTransaction()) {
            $this->pdo()->rollBack();
        }
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
        header('Location: '.$url, true, $code);
        exit;
    }

    protected function json($data, int $code = 200): void
    {
        http_response_code($code);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    protected function postOnly(): void
    {
        if ($this->method() !== 'POST') {
            http_response_code(405);
            echo 'Method Not Allowed';
            exit;
        }
    }

    protected function input(string $key, $default = null)
    {
        return $_POST[$key] ?? $_GET[$key] ?? $default;
    }
}