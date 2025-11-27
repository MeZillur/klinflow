<?php
declare(strict_types=1);

namespace App\Middleware;

use Shared\DB;

/**
 * TenantResolver
 * - Resolves org by slug (cp_organizations)
 * - Sets $_SESSION['tenant_ctx'] = { org_id, slug, timezone, plan }
 * - In db_per_org mode, optionally ensures & switches tenant DB
 * - Returns bool; never fatals. Use lastError() to see why it failed.
 *
 * ENV helpers:
 *   ISOLATION_MODE=row_guard | db_per_org
 *   ALLOW_FALLBACK_ROW_GUARD=1  (stay on global DB if switch fails)
 */
final class TenantResolver
{
    private static ?string $lastError = null;

    public static function lastError(): ?string { return self::$lastError; }

    public static function applyFromSlug(?string $slug): bool
    {
        self::$lastError = null;
        $_SESSION['tenant_ctx_err'] = null;

        $slug = trim((string)$slug);
        if ($slug === '') {
            unset($_SESSION['tenant_ctx']);
            return self::fail('empty-slug');
        }

        try {
            // Global (control) DB
            $pdo = DB::pdo();

            $stmt = $pdo->prepare(
                "SELECT id, slug, plan, status
                   FROM cp_organizations
                  WHERE slug=? LIMIT 1"
            );
            $stmt->execute([$slug]);
            $org = $stmt->fetch(\PDO::FETCH_ASSOC);

            if (!$org)                      return self::fail('org-not-found');
            $status = strtolower((string)($org['status'] ?? ''));
            if (!in_array($status, ['active','trial'], true))
                                           return self::fail('org-inactive');

            // Optional timezone column
            $tz = 'UTC';
            try {
                $tz = (string)($pdo->query(
                    "SELECT timezone
                       FROM cp_organizations
                      WHERE slug=".$pdo->quote($slug)." LIMIT 1"
                )->fetchColumn() ?: 'UTC');
            } catch (\Throwable $e) { /* column may not exist; ignore */ }

            $_SESSION['tenant_ctx'] = [
                'org_id'   => (int)$org['id'],
                'slug'     => (string)$org['slug'],
                'timezone' => $tz ?: 'UTC',
                'plan'     => (string)($org['plan'] ?? ''),
            ];
            @date_default_timezone_set($_SESSION['tenant_ctx']['timezone']);

            // Switch DB if requested
            $mode = method_exists(DB::class, 'mode')
                ? DB::mode()
                : (getenv('ISOLATION_MODE') ?: 'row_guard');

            if ($mode === 'db_per_org') {
                try {
                    // These are optional helpers – only run if present
                    if (method_exists(DB::class, 'ensureTenantDatabase')) {
                        DB::ensureTenantDatabase((int)$org['id'], (string)$org['slug']);
                    }
                    if (method_exists(DB::class, 'switchTenant')) {
                        DB::switchTenant((int)$org['id'], (string)$org['slug']);
                    }
                } catch (\Throwable $e) {
                    $fallback = (getenv('ALLOW_FALLBACK_ROW_GUARD') === '1');
                    if ($fallback) {
                        // Stay on global DB, but record why
                        self::fail('db-switch-failed: '.$e->getMessage(), false);
                    } else {
                        return self::fail('db-switch-failed: '.$e->getMessage());
                    }
                }
            }

            return true;

        } catch (\Throwable $e) {
            return self::fail('exception: '.$e->getMessage());
        }
    }

    private static function fail(string $reason, bool $returnFalse = true): bool
    {
        self::$lastError = $reason;
        $_SESSION['tenant_ctx_err'] = $reason;
        if ($returnFalse) unset($_SESSION['tenant_ctx']);
        return !$returnFalse;
    }

    public static function ctx(): ?array
    {
        return $_SESSION['tenant_ctx'] ?? null;
    }

    public static function orgId(): ?int
    {
        return isset($_SESSION['tenant_ctx']['org_id']) ? (int)$_SESSION['tenant_ctx']['org_id'] : null;
    }
}