<?php
declare(strict_types=1);

namespace Modules\DMS\Controllers\Accounts;

use Modules\DMS\Controllers\BaseController;
use PDO;

final class ProfitLossController extends BaseController
{
    public function index(?array $ctx = null): void
    {
        $c      = $this->ctx($ctx);
        $pdo    = $this->pdo();
        $orgId  = $this->orgId($c);
        $base   = $this->moduleBase($c);

        $from = (string)($_GET['from'] ?? date('Y-m-01'));
        $to   = (string)($_GET['to']   ?? date('Y-m-d'));
        $wantPrint = isset($_GET['print']) && $_GET['print'] === '1';
        $showAll   = isset($_GET['show']) && strtolower((string)$_GET['show']) === 'all';

        // ---- Detect usable source -----------------------------------------
        // Only choose views if BOTH the view exists AND it actually exposes
        // the columns we need (`jdate`, `account_id`, `dr`, `cr`).
        $useViews = $this->hasTable($pdo, 'ac_v_gl_entries')
                   && $this->hasCols($pdo, 'ac_v_gl_entries', ['jdate','account_id','dr','cr']);

        // ---- Main P&L query (per-account period net) -----------------------
        if ($useViews) {
            $sql = "
                SELECT a.id, a.code, a.name, LOWER(a.type) AS type,
                       COALESCE(SUM(e.dr - e.cr), 0) AS period_net
                FROM dms_gl_accounts a
                LEFT JOIN ac_v_gl_entries e
                  ON e.org_id=a.org_id
                 AND e.account_id=a.id
                 AND e.jdate BETWEEN :from AND :to
                WHERE a.org_id=:org
                GROUP BY a.id, a.code, a.name, a.type
                ORDER BY a.code
            ";
            $params = ['org'=>$orgId, 'from'=>$from, 'to'=>$to];
        } else {
            // Legacy tables
            $sql = "
                SELECT a.id, a.code, a.name, LOWER(a.type) AS type,
                       COALESCE(SUM(e.dr - e.cr), 0) AS period_net
                FROM dms_gl_accounts a
                LEFT JOIN dms_gl_entries e
                  ON e.org_id=a.org_id AND e.account_id=a.id
                LEFT JOIN dms_gl_journals j
                  ON j.id=e.journal_id AND j.org_id=e.org_id
                WHERE a.org_id=:org
                  AND (j.jdate BETWEEN :from AND :to OR j.id IS NULL)
                GROUP BY a.id, a.code, a.name, a.type
                ORDER BY a.code
            ";
            $params = ['org'=>$orgId, 'from'=>$from, 'to'=>$to];
        }

        $st = $pdo->prepare($sql);
        $st->execute($params);

        $rows = [];
        $totalIncome = 0.0;
        $totalExpense = 0.0;

        while ($r = $st->fetch(PDO::FETCH_ASSOC)) {
            $type = strtolower((string)($r['type'] ?? ''));
            $net  = (float)$r['period_net'];

            // Only revenue/expense in P&L, unless Show All is toggled
            $isPL = in_array($type, ['revenue','income','expense'], true);
            if (!$isPL && !$showAll) continue;

            $income  = ($type === 'revenue' || $type === 'income') ? max(-$net, 0.0) : 0.0; // credits → positive
            $expense = ($type === 'expense') ? max($net, 0.0) : 0.0;

            if (!$showAll && ($income == 0.0 && $expense == 0.0)) continue;

            $rows[] = [
                'code'    => (string)$r['code'],
                'name'    => (string)$r['name'],
                'income'  => $income,
                'expense' => $expense,
                'type'    => $type,
            ];
            $totalIncome  += $income;
            $totalExpense += $expense;
        }

        $netProfit = $totalIncome - $totalExpense;

        // If everything zero and not showing all → show the CoA anyway (context)
        if (empty($rows) && !$showAll) {
            $showAll = true;
            $st->execute($params);
            while ($r = $st->fetch(PDO::FETCH_ASSOC)) {
                $rows[] = [
                    'code'    => (string)$r['code'],
                    'name'    => (string)$r['name'],
                    'income'  => 0.0,
                    'expense' => 0.0,
                    'type'    => strtolower((string)($r['type'] ?? '')),
                ];
            }
        }

        // ---- Daily Net Profit series for Chart.js --------------------------
        // net_day = SUM over accounts:
        //   - (dr - cr) for expense
        //   + (cr - dr) for revenue/income  → equivalently: -(dr - cr)
        if ($useViews) {
            $sqlSeries = "
                SELECT e.jdate AS d,
                       ROUND(SUM(CASE
                           WHEN LOWER(a.type) IN ('revenue','income') THEN (e.cr - e.dr)
                           WHEN LOWER(a.type) = 'expense'             THEN (e.dr - e.cr)
                           ELSE 0
                       END), 2) AS net
                FROM ac_v_gl_entries e
                JOIN dms_gl_accounts a
                  ON a.org_id=e.org_id AND a.id=e.account_id
                WHERE e.org_id=:org AND e.jdate BETWEEN :from AND :to
                GROUP BY e.jdate
                ORDER BY e.jdate
            ";
            $paramsSeries = ['org'=>$orgId, 'from'=>$from, 'to'=>$to];
        } else {
            $sqlSeries = "
                SELECT j.jdate AS d,
                       ROUND(SUM(CASE
                           WHEN LOWER(a.type) IN ('revenue','income') THEN (e.cr - e.dr)
                           WHEN LOWER(a.type) = 'expense'             THEN (e.dr - e.cr)
                           ELSE 0
                       END), 2) AS net
                FROM dms_gl_entries e
                JOIN dms_gl_journals j
                  ON j.id=e.journal_id AND j.org_id=e.org_id
                JOIN dms_gl_accounts a
                  ON a.org_id=e.org_id AND a.id=e.account_id
                WHERE e.org_id=:org AND j.jdate BETWEEN :from AND :to
                GROUP BY j.jdate
                ORDER BY j.jdate
            ";
            $paramsSeries = ['org'=>$orgId, 'from'=>$from, 'to'=>$to];
        }

        $series = $this->dailyRange($from, $to);   // seed all dates with 0.00
        $ss = $pdo->prepare($sqlSeries);
        $ss->execute($paramsSeries);
        foreach ($ss->fetchAll(PDO::FETCH_ASSOC) ?: [] as $r) {
            $d = (string)$r['d'];
            if (isset($series[$d])) $series[$d] = (float)$r['net'];
        }

        $vars = [
            'title'         => 'Profit & Loss',
            'from'          => $from,
            'to'            => $to,
            'rows'          => $rows,
            'totalIncome'   => $totalIncome,
            'totalExpense'  => $totalExpense,
            'netProfit'     => $netProfit,
            'module_base'   => $base,
            'show_all'      => $showAll,
            // chart payload
            'chart_labels'  => array_keys($series),
            'chart_net'     => array_values($series),
        ];

        $this->view(
            $wantPrint ? 'accounts/pl/print' : 'accounts/pl/index',
            $vars,
            $c
        );
    }

    /* ---------- helpers ---------- */

    private function hasTable(PDO $pdo, string $table): bool
    {
        $s = $pdo->prepare("
            SELECT 1 FROM INFORMATION_SCHEMA.TABLES
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1
        ");
        $s->execute([$table]);
        return (bool)$s->fetchColumn();
    }

    private function hasCols(PDO $pdo, string $table, array $cols): bool
    {
        if (!$cols) return true;
        $in = implode(',', array_fill(0, count($cols), '?'));
        $sql = "
          SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
          WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?
            AND COLUMN_NAME IN ($in)
        ";
        $st = $pdo->prepare($sql);
        $st->execute(array_merge([$table], $cols));
        return (int)$st->fetchColumn() === count($cols);
    }

    /** Return ['YYYY-MM-DD' => 0.0, ...] for inclusive date range */
    private function dailyRange(string $from, string $to): array
    {
        $out = [];
        $d = new \DateTimeImmutable($from);
        $end = new \DateTimeImmutable($to);
        while ($d <= $end) {
            $out[$d->format('Y-m-d')] = 0.0;
            $d = $d->modify('+1 day');
        }
        return $out;
    }
}