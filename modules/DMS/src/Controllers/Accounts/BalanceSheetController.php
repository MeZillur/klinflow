<?php
declare(strict_types=1);

namespace Modules\DMS\Controllers\Accounts;

use Modules\DMS\Controllers\BaseController;
use PDO;

final class BalanceSheetController extends BaseController
{
    /** GET /accounts/balance-sheet */
    public function index(?array $ctx = null): void
    {
        $c     = $this->ctx($ctx);
        $pdo   = $this->pdo() ?? $this->tenantPdo();              // be liberal in getting PDO
        $orgId = $this->resolveOrgId($c, $pdo);                    // robust org resolver

        $asOf = $this->dateParam('as_of') ?? date('Y-m-d');

        // Choose source (normalized vs legacy)
        $hasNorm = $this->hasAny($pdo, 'dms_gl_journals') && $this->hasTable($pdo, 'dms_gl_entries');
        $hasGL   = $this->hasAny($pdo, 'dms_gl');
        $hasAcc  = $this->hasTable($pdo, 'dms_gl_accounts');

        $rows = [];
        if ($hasNorm && $hasAcc) {
            $rows = $this->balancesFromNormalized($pdo, $orgId, $asOf);
        } elseif ($hasGL && $hasAcc) {
            $rows = $this->balancesFromLegacyWithCoA($pdo, $orgId, $asOf);
        } elseif ($hasGL) {
            $rows = $this->balancesFromLegacySynth($pdo, $orgId, $asOf);
        } elseif ($hasAcc) {
            $rows = $this->zeroFromCoA($pdo, $orgId);
        }

        // Partition & compute retained earnings from P&L
        $assets = $liabilities = $equity = [];
        $plNet = 0.0;

        foreach ($rows as $r) {
            $type = strtolower((string)($r['type'] ?? ''));
            $net  = (float)($r['net'] ?? 0.0);

            if (in_array($type, ['income','revenue','expense'], true)) { $plNet += $net; continue; }
            if (in_array($type, ['asset','assets'], true))      { $assets[] = $r; }
            elseif (in_array($type, ['liability','liabilities'], true)) { $liabilities[] = $r; }
            else { $equity[] = $r; }
        }

        $retained        = -1.0 * $plNet; // move P&L into equity
        $totAssets       = $this->sumNet($assets);
        $totLiabilities  = $this->sumNet($liabilities);
        $totEquity       = $this->sumNet($equity) + $retained;

        $this->view('accounts/balance_sheet', [
            'title'         => 'Balance Sheet',
            'module_base'   => $this->moduleBase($c) ?? '/apps/dms',
            'data'          => [
                'as_of'           => $asOf,
                'assets'          => $assets,
                'liabilities'     => $liabilities,
                'equity'          => $equity,
                'retained'        => round($retained, 2),
                'tot_assets'      => round($totAssets, 2),
                'tot_liabilities' => round($totLiabilities, 2),
                'tot_equity'      => round($totEquity, 2),
                'balanced'        => $this->nearZero($totAssets - ($totLiabilities + $totEquity)),
            ],
            'active'        => 'accounts',
            'subactive'     => 'accounts.balance_sheet',
        ], $c);
    }

    /* ---------------- source readers ---------------- */

    private function balancesFromNormalized(PDO $pdo, int $orgId, string $asOf): array
    {
        $sql = "
            SELECT
                a.id, a.code, a.name, LOWER(a.type) AS type,
                ROUND(COALESCE(SUM(CASE WHEN j.jdate <= :asof THEN (e.dr - e.cr) ELSE 0 END),0),2) AS net
            FROM dms_gl_accounts a
            LEFT JOIN dms_gl_entries e
              ON e.org_id=a.org_id AND e.account_id=a.id
            LEFT JOIN dms_gl_journals j
              ON j.org_id=e.org_id AND j.id=e.journal_id
            WHERE a.org_id=:org
            GROUP BY a.id, a.code, a.name, a.type
            ORDER BY a.code
        ";
        $st = $pdo->prepare($sql);
        $st->execute([':org'=>$orgId, ':asof'=>$asOf]);
        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    private function balancesFromLegacyWithCoA(PDO $pdo, int $orgId, string $asOf): array
    {
        $sql = "
            SELECT
                a.id, a.code, a.name, LOWER(a.type) AS type,
                ROUND(COALESCE(SUM(CASE WHEN g.entry_date <= :asof THEN (g.dr - g.cr) ELSE 0 END),0),2) AS net
            FROM dms_gl_accounts a
            LEFT JOIN dms_gl g
              ON g.org_id=a.org_id AND g.account_code=a.code
            WHERE a.org_id=:org
            GROUP BY a.id, a.code, a.name, a.type
            ORDER BY a.code
        ";
        $st = $pdo->prepare($sql);
        $st->execute([':org'=>$orgId, ':asof'=>$asOf]);
        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    private function balancesFromLegacySynth(PDO $pdo, int $orgId, string $asOf): array
    {
        $sql = "
            SELECT
                NULL AS id,
                g.account_code AS code,
                g.account_code AS name,
                LOWER(
                    CASE
                        WHEN LEFT(g.account_code,1)='1' THEN 'asset'
                        WHEN LEFT(g.account_code,1)='2' THEN 'liability'
                        WHEN LEFT(g.account_code,1)='3' THEN 'equity'
                        WHEN LEFT(g.account_code,1)='4' THEN 'revenue'
                        WHEN LEFT(g.account_code,1)='5' THEN 'expense'
                        ELSE 'equity'
                    END
                ) AS type,
                ROUND(COALESCE(SUM(CASE WHEN g.entry_date <= :asof THEN (g.dr - g.cr) ELSE 0 END),0),2) AS net
            FROM dms_gl g
            WHERE g.org_id=:org
            GROUP BY g.account_code
            ORDER BY g.account_code
        ";
        $st = $pdo->prepare($sql);
        $st->execute([':org'=>$orgId, ':asof'=>$asOf]);
        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    private function zeroFromCoA(PDO $pdo, int $orgId): array
    {
        $st = $pdo->prepare("
            SELECT a.id, a.code, a.name, LOWER(a.type) AS type, 0.00 AS net
            FROM dms_gl_accounts a
            WHERE a.org_id=?
            ORDER BY a.code
        ");
        $st->execute([$orgId]);
        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /* ---------------- utilities ---------------- */

    private function hasTable(PDO $pdo, string $name): bool
    {
        $st = $pdo->prepare("
            SELECT 1 FROM INFORMATION_SCHEMA.TABLES
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?
            LIMIT 1
        ");
        $st->execute([$name]);
        return (bool)$st->fetchColumn();
    }
    private function hasAny(PDO $pdo, string $name): bool
    {
        if (!$this->hasTable($pdo, $name)) return false;
        $q = $pdo->query("SELECT 1 FROM `$name` LIMIT 1");
        return (bool)($q?->fetchColumn());
    }

    private function sumNet(array $rows): float
    {
        $s = 0.0; foreach ($rows as $r) $s += (float)($r['net'] ?? 0); return round($s,2);
    }
    private function nearZero(float $n, float $eps = 0.01): bool
    {
        return abs($n) <= $eps;
    }
    private function dateParam(string $k): ?string
    {
        $v = trim((string)($_GET[$k] ?? $_POST[$k] ?? ''));
        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $v) ? $v : null;
    }

    /**
     * Robust org resolver (mirrors SalesController style)
     */
    private function resolveOrgId(array &$ctx, PDO $pdo): int
    {
        // 1) From ctx
        $id = (int)($ctx['org']['id'] ?? 0);
        if ($id > 0) return $id;

        // 2) From session
        $id = (int)($_SESSION['tenant_org']['id'] ?? 0);
        if ($id > 0) { $ctx['org']['id'] = $id; return $id; }

        // 3) From slug in URL
        $slug = (string)($ctx['slug'] ?? '');
        if ($slug === '' || $slug === '_') {
            if (!empty($_SERVER['REQUEST_URI']) &&
                preg_match('#^/t/([^/]+)/apps/dms#', (string)$_SERVER['REQUEST_URI'], $m)) {
                $slug = $m[1];
                $ctx['slug'] = $slug;
            }
        }
        if ($slug !== '' && $this->hasTable($pdo, 'cp_organizations')) {
            $st = $pdo->prepare("SELECT id FROM cp_organizations WHERE slug=? LIMIT 1");
            $st->execute([$slug]);
            $id = (int)($st->fetchColumn() ?: 0);
            if ($id > 0) { $ctx['org']['id'] = $id; return $id; }
        }

        // 4) Fallback to first org (dev)
        if ($this->hasTable($pdo, 'cp_organizations')) {
            $st = $pdo->query("SELECT id FROM cp_organizations ORDER BY id LIMIT 1");
            $id = (int)($st?->fetchColumn() ?: 0);
            if ($id > 0) { $ctx['org']['id'] = $id; return $id; }
        }

        return 0;
    }
}