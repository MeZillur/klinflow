<?php
declare(strict_types=1);

namespace Modules\POS\Controllers;

use PDO;

abstract class BaseController
{
    protected array $ctx;

    public function __construct(array $ctx = [])
    {
        $this->ctx = $ctx;
    }

    /* ---------- context + paths ---------- */
    protected function basePath(): string
    {
        return (string)($this->ctx['module_base'] ?? '/apps/pos');
    }

    protected function moduleDir(): string
    {
        return (string)($this->ctx['module_dir'] ?? dirname(__DIR__));
    }

    protected function orgIdSafe(): int
    {
        $org = (array)($this->ctx['org'] ?? []);
        return (int)($org['id'] ?? $org['org_id'] ?? 0);
    }

    /* ---------- DB helpers (safe no-DB) ---------- */
    protected function pdoOrNull(): ?PDO
    {
        // Prefer tenant PDO if available, else shared PDO, else null
        if (class_exists('\Shared\DB')) {
            try {
                if (method_exists(\Shared\DB::class, 'tenant')) return \Shared\DB::tenant();
                if (method_exists(\Shared\DB::class, 'pdo'))    return \Shared\DB::pdo();
            } catch (\Throwable $e) { /* noop */ }
        }
        return null;
    }

    protected function hasTbl(?PDO $pdo, string $table): bool
    {
        if (!$pdo) return false;
        try {
            $st = $pdo->prepare("
                SELECT 1
                FROM INFORMATION_SCHEMA.TABLES
                WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?
                LIMIT 1
            ");
            $st->execute([$table]);
            return (bool)$st->fetchColumn();
        } catch (\Throwable $e) {
            return false;
        }
    }

    protected function safeScalar(?PDO $pdo, string $sql, array $params = [], $default = null)
    {
        if (!$pdo) return $default;
        try {
            $st = $pdo->prepare($sql);
            $st->execute($params);
            $v = $st->fetchColumn();
            return $v !== false ? $v : $default;
        } catch (\Throwable $e) {
            return $default;
        }
    }

    /* ---------- rendering ---------- */
    protected function render(string $relView, array $vars = []): void
    {
        $viewFile = rtrim($this->moduleDir(), '/') . '/Views/' . ltrim($relView, '/') . '.php';

        // If Shared\View is available, use the shell (like DMS)
        if (class_exists('\Shared\View')) {
            $layout = null; // POS can add a manifest later; null = raw include
            \Shared\View::render($viewFile, array_merge($this->ctx, $vars), $layout);
            return;
        }

        // Fallback: include the view directly
        if (!is_file($viewFile)) {
            http_response_code(404);
            echo 'View not found: ' . htmlspecialchars($relView);
            return;
        }
        // Make $vars available to the view
        extract($vars, EXTR_SKIP);
        $ctx = $this->ctx; // some views expect $ctx
        require $viewFile;
    }

    /* ---------- logging ---------- */
    protected function logError(string $msg, \Throwable $e): void
    {
        error_log('[POS] ' . $msg . ': ' . $e->getMessage());
    }
}