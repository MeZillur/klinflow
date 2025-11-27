<?php
declare(strict_types=1);

namespace Modules\DMS\Controllers;

use Modules\POS\Controllers\BaseController as POSBaseController;

final class HealthController extends POSBaseController
{
    /* ───────────────────────────── helpers ───────────────────────────── */

    /** Enable verbose error page with ?_debug=1 */
    private function dbg(): bool
    {
        return isset($_GET['_debug']) && $_GET['_debug'] === '1';
    }

    /** Build a tolerant context: org_id, slug, module_base, org[] */
    private function safeCtx(array $ctx): array
    {
        $c = $this->ctx($ctx);

        // org_id
        $orgId = (int)($c['org_id'] ?? ($_SESSION['org_id'] ?? 0));

        // slug (from ctx org, or session)
        $slug = $c['org']['slug'] ?? ($_SESSION['org_slug'] ?? null);

        // module_base: prefer ctx, else derive from slug, else fallback
        $moduleBase = $c['module_base'] ?? ($slug ? '/t/' . rawurlencode($slug) . '/apps/dms' : '/apps/dms');

        // org array (for shell)
        $orgArr = $c['org'] ?? ['id' => $orgId, 'slug' => $slug];

        return [
            'org_id'      => $orgId,
            'slug'        => $slug,
            'module_base' => $moduleBase,
            'org'         => $orgArr,
        ];
    }

    /* ───────────────────────────── actions ───────────────────────────── */

    /** GET …/reports/health */
    public function index(array $ctx = []): void
    {
        $cx  = $this->safeCtx($ctx);
        $pdo = $this->pdo();

        try {
            $unbalanced = (int)$pdo->query("
                SELECT COUNT(*) FROM v_dms_gl_journal_balance
                WHERE org_id = {$cx['org_id']} AND imbalance <> 0
            ")->fetchColumn();

            $negStock = (int)$pdo->query("
                SELECT COUNT(*) FROM v_dms_stock_on_hand
                WHERE org_id = {$cx['org_id']} AND on_hand < 0
            ")->fetchColumn();

            // Portable (no CTE) query for missing required map keys
            $missingMap = $this->rows("
                SELECT k.map_key
                FROM (
                  SELECT DISTINCT org_id FROM dms_account_map
                  UNION
                  SELECT DISTINCT org_id FROM dms_gl_accounts
                ) o
                CROSS JOIN (
                  SELECT 'cash' AS map_key UNION ALL
                  SELECT 'bank' UNION ALL
                  SELECT 'mobile' UNION ALL
                  SELECT 'ar' UNION ALL
                  SELECT 'ap' UNION ALL
                  SELECT 'revenue' UNION ALL
                  SELECT 'cogs' UNION ALL
                  SELECT 'inventory'
                ) k
                LEFT JOIN dms_account_map m
                  ON m.org_id = o.org_id AND m.map_key = k.map_key
                WHERE o.org_id = :org AND m.account_id IS NULL
                ORDER BY k.map_key
            ", [':org' => $cx['org_id']]);

            $bankDaily = $this->rows("
                SELECT account_code, account_name, tx_date, inflow, outflow, net_change
                FROM v_dms_bank_book_daily
                WHERE org_id = :org
                ORDER BY tx_date DESC, account_code
                LIMIT 10
            ", [':org' => $cx['org_id']]);

            $this->view('modules/DMS/Views/health/index.php', [
                'title'       => 'DMS Health',
                'unbalanced'  => $unbalanced,
                'negStock'    => $negStock,
                'missingMap'  => $missingMap,
                'bankDaily'   => $bankDaily,
                'module_base' => $cx['module_base'],
                'org'         => $cx['org'],
            ]);
        } catch (\Throwable $e) {
            if ($this->dbg()) {
                http_response_code(500);
                header('Content-Type: text/plain; charset=UTF-8');
                echo "HealthController::index failed\n{$e->getMessage()}\n";
                echo "in {$e->getFile()}:{$e->getLine()}\n\n{$e->getTraceAsString()}";
                return;
            }
            throw $e;
        }
    }

    /** GET …/reports/health/gl */
    public function gl(array $ctx = []): void
    {
        $cx = $this->safeCtx($ctx);

        try {
            $rows = $this->rows("
                SELECT j.org_id, j.id AS journal_id, j.jno, j.jtype, j.memo, j.posted_at,
                       ROUND(SUM(e.dr),2) AS total_dr,
                       ROUND(SUM(e.cr),2) AS total_cr,
                       ROUND(SUM(e.dr - e.cr),2) AS imbalance
                FROM dms_gl_journals j
                JOIN dms_gl_entries e ON e.journal_id = j.id AND e.org_id = j.org_id
                WHERE j.org_id = :org
                GROUP BY j.org_id, j.id, j.jno, j.jtype, j.memo, j.posted_at
                HAVING ROUND(SUM(e.dr - e.cr),2) <> 0
                ORDER BY j.posted_at DESC
                LIMIT 200
            ", [':org' => $cx['org_id']]);

            $this->view('modules/DMS/Views/health/gl.php', [
                'title'       => 'Unbalanced Journals',
                'rows'        => $rows,
                'module_base' => $cx['module_base'],
                'org'         => $cx['org'],
            ]);
        } catch (\Throwable $e) {
            if ($this->dbg()) {
                http_response_code(500);
                header('Content-Type: text/plain; charset=UTF-8');
                echo "HealthController::gl failed\n{$e->getMessage()}\n";
                echo "in {$e->getFile()}:{$e->getLine()}\n\n{$e->getTraceAsString()}";
                return;
            }
            throw $e;
        }
    }

    /** GET …/reports/health/stock */
    public function stock(array $ctx = []): void
    {
        $cx = $this->safeCtx($ctx);

        try {
            $rows = $this->rows("
                SELECT product_id, on_hand, last_moved_at
                FROM v_dms_stock_on_hand
                WHERE org_id = :org AND on_hand < 0
                ORDER BY product_id
                LIMIT 500
            ", [':org' => $cx['org_id']]);

            $this->view('modules/DMS/Views/health/stock.php', [
                'title'       => 'Negative Stock Items',
                'rows'        => $rows,
                'module_base' => $cx['module_base'],
                'org'         => $cx['org'],
            ]);
        } catch (\Throwable $e) {
            if ($this->dbg()) {
                http_response_code(500);
                header('Content-Type: text/plain; charset=UTF-8');
                echo "HealthController::stock failed\n{$e->getMessage()}\n";
                echo "in {$e->getFile()}:{$e->getLine()}\n\n{$e->getTraceAsString()}";
                return;
            }
            throw $e;
        }
    }

    /** GET …/reports/health/map-keys */
    public function mapKeys(array $ctx = []): void
    {
        $cx = $this->safeCtx($ctx);

        try {
            // Portable (no CTE) variant
            $missing = $this->rows("
                SELECT k.map_key
                FROM (
                  SELECT DISTINCT org_id FROM dms_account_map
                  UNION
                  SELECT DISTINCT org_id FROM dms_gl_accounts
                ) o
                CROSS JOIN (
                  SELECT 'cash' AS map_key UNION ALL
                  SELECT 'bank' UNION ALL
                  SELECT 'mobile' UNION ALL
                  SELECT 'ar' UNION ALL
                  SELECT 'ap' UNION ALL
                  SELECT 'revenue' UNION ALL
                  SELECT 'cogs' UNION ALL
                  SELECT 'inventory'
                ) k
                LEFT JOIN dms_account_map m
                  ON m.org_id = o.org_id AND m.map_key = k.map_key
                WHERE o.org_id = :org AND m.account_id IS NULL
                ORDER BY k.map_key
            ", [':org' => $cx['org_id']]);

            $this->view('modules/DMS/Views/health/map.php', [
                'title'       => 'Missing Account Map Keys',
                'missing'     => $missing,
                'module_base' => $cx['module_base'],
                'org'         => $cx['org'],
            ]);
        } catch (\Throwable $e) {
            if ($this->dbg()) {
                http_response_code(500);
                header('Content-Type: text/plain; charset=UTF-8');
                echo "HealthController::mapKeys failed\n{$e->getMessage()}\n";
                echo "in {$e->getFile()}:{$e->getLine()}\n\n{$e->getTraceAsString()}";
                return;
            }
            throw $e;
        }
    }
}