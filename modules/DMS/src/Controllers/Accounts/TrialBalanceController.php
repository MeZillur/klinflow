<?php
declare(strict_types=1);

namespace Modules\DMS\Controllers\Accounts;

use Modules\DMS\Controllers\BaseController;
use PDO;

final class TrialBalanceController extends BaseController
{
    public function index(?array $ctx = null): void
    {
        $c      = $this->ctx($ctx);
        $pdo    = $this->pdo();
        $orgId  = $this->orgId($c);
        $base   = $this->moduleBase($c);

        // Dates
        $from = (string)($_GET['from'] ?? date('Y-m-01'));
        $to   = (string)($_GET['to']   ?? date('Y-m-d'));
        if (!$this->isDate($from)) $from = date('Y-m-01');
        if (!$this->isDate($to))   $to   = date('Y-m-d');

        $wantPrint = isset($_GET['print']) && $_GET['print'] === '1';
        $wantCsv   = isset($_GET['csv'])   && $_GET['csv']   === '1';
        $showAllRq = isset($_GET['show'])  && strtolower((string)$_GET['show']) === 'all';

        // Chart of accounts
        $acctStmt = $pdo->prepare("
            SELECT id, code, name
            FROM dms_gl_accounts
            WHERE org_id = ?
            ORDER BY code, name
        ");
        $acctStmt->execute([$orgId]);
        $accounts = $acctStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        // Opening + period aggregates (journal-dated)
        $sql = "
            WITH
            opening AS (
                SELECT e.account_id, COALESCE(SUM(e.dr - e.cr), 0) AS opening_net
                FROM dms_gl_entries e
                JOIN dms_gl_journals j
                  ON j.id = e.journal_id AND j.org_id = e.org_id
                WHERE e.org_id = ? AND j.jdate < ?
                GROUP BY e.account_id
            ),
            period AS (
                SELECT e.account_id,
                       COALESCE(SUM(e.dr), 0) AS period_dr,
                       COALESCE(SUM(e.cr), 0) AS period_cr
                FROM dms_gl_entries e
                JOIN dms_gl_journals j
                  ON j.id = e.journal_id AND j.org_id = e.org_id
                WHERE e.org_id = ? AND j.jdate BETWEEN ? AND ?
                GROUP BY e.account_id
            )
            SELECT
                a.id AS account_id,
                COALESCE(o.opening_net, 0) AS opening_net,
                COALESCE(p.period_dr, 0)   AS period_dr,
                COALESCE(p.period_cr, 0)   AS period_cr
            FROM dms_gl_accounts a
            LEFT JOIN opening o ON o.account_id = a.id
            LEFT JOIN period  p ON p.account_id = a.id
            WHERE a.org_id = ?
        ";
        $st = $pdo->prepare($sql);
        $st->execute([$orgId, $from, $orgId, $from, $to, $orgId]);

        $agg = [];
        while ($r = $st->fetch(PDO::FETCH_ASSOC)) {
            $aid = (int)$r['account_id'];
            $agg[$aid] = [
                'opening' => (float)$r['opening_net'],
                'pdr'     => (float)$r['period_dr'],
                'pcr'     => (float)$r['period_cr'],
            ];
        }

        // Build rows
        $rowsActive = [];
        $rowsAll    = [];
        $totActive  = [
            'opening_dr'=>0.0,'opening_cr'=>0.0,
            'period_dr'=>0.0,'period_cr'=>0.0,
            'closing_dr'=>0.0,'closing_cr'=>0.0,
        ];

        foreach ($accounts as $a) {
            $id   = (int)$a['id'];
            $code = (string)$a['code'];
            $name = (string)$a['name'];

            $open  = $agg[$id]['opening'] ?? 0.0;
            $pdr   = $agg[$id]['pdr']     ?? 0.0;
            $pcr   = $agg[$id]['pcr']     ?? 0.0;
            $close = $open + $pdr - $pcr;

            $row = [
                'code'       => $code,
                'name'       => $name,
                'opening_dr' => $open >= 0 ? $open : 0.0,
                'opening_cr' => $open <  0 ? abs($open) : 0.0,
                'period_dr'  => $pdr,
                'period_cr'  => $pcr,
                'closing_dr' => $close >= 0 ? $close : 0.0,
                'closing_cr' => $close <  0 ? abs($close) : 0.0,
            ];

            $numericTotal =
                $row['opening_dr'] + $row['opening_cr'] +
                $row['period_dr']  + $row['period_cr']  +
                $row['closing_dr'] + $row['closing_cr'];

            $rowsAll[] = $row;

            if ($numericTotal > 0.0) {
                foreach ($totActive as $k => $_) { $totActive[$k] += $row[$k]; }
                $rowsActive[] = $row;
            }
        }

        // Decide which set to show
        $rows = $rowsActive;
        $tot  = $totActive;
        $showAll = $showAllRq;

        // If page would be empty, auto-switch to show all
        if (!$showAllRq && empty($rowsActive) && !empty($rowsAll)) {
            $rows    = $rowsAll;
            $tot     = ['opening_dr'=>0,'opening_cr'=>0,'period_dr'=>0,'period_cr'=>0,'closing_dr'=>0,'closing_cr'=>0];
            foreach ($rowsAll as $r) { foreach ($tot as $k => $_) $tot[$k] += $r[$k]; }
            $showAll = true; // UI can reflect this state
        }

        // CSV?
        if ($wantCsv) {
            $this->csvResponse("trial_balance_{$from}_to_{$to}.csv", $rows, $tot);
            return;
        }

        // Optional light debug counts (comment out if not needed)
        $debugCounts = $this->quickCounts($pdo, $orgId, $from, $to);

        $vars = [
            'title'       => 'Trial Balance',
            'from'        => $from,
            'to'          => $to,
            'rows'        => $rows,
            'tot'         => $tot,
            'module_base' => $base,
            'show_all'    => $showAll,
            'debug'       => $debugCounts, // use in view if helpful
        ];

        $this->view(
            $wantPrint ? 'accounts/trial-balance/print' : 'accounts/trial-balance/index',
            $vars,
            $c
        );
    }

    private function isDate(string $d): bool
    {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $d)) return false;
        [$y,$m,$day] = array_map('intval', explode('-', $d));
        return checkdate($m,$day,$y);
    }

    private function csvResponse(string $filename, array $rows, array $tot): void
    {
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="'.$filename.'"');
        $out = fopen('php://output', 'w');
        fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF));

        fputcsv($out, ['Account','Name','Opening Dr','Opening Cr','Period Dr','Period Cr','Closing Dr','Closing Cr']);
        foreach ($rows as $r) {
            fputcsv($out, [
                $r['code'], $r['name'],
                number_format((float)$r['opening_dr'], 2, '.', ''),
                number_format((float)$r['opening_cr'], 2, '.', ''),
                number_format((float)$r['period_dr'],  2, '.', ''),
                number_format((float)$r['period_cr'],  2, '.', ''),
                number_format((float)$r['closing_dr'], 2, '.', ''),
                number_format((float)$r['closing_cr'], 2, '.', ''),
            ]);
        }
        fputcsv($out, [
            'Totals','',
            number_format((float)$tot['opening_dr'], 2, '.', ''),
            number_format((float)$tot['opening_cr'], 2, '.', ''),
            number_format((float)$tot['period_dr'],  2, '.', ''),
            number_format((float)$tot['period_cr'],  2, '.', ''),
            number_format((float)$tot['closing_dr'], 2, '.', ''),
            number_format((float)$tot['closing_cr'], 2, '.', ''),
        ]);

        fclose($out);
    }

    private function quickCounts(PDO $pdo, int $orgId, string $from, string $to): array
    {
        // Journals in range
        $a = $pdo->prepare("SELECT COUNT(*) FROM dms_gl_journals WHERE org_id=? AND jdate BETWEEN ? AND ?");
        $a->execute([$orgId, $from, $to]);
        $jr = (int)$a->fetchColumn();

        // Entries joined to those journals
        $b = $pdo->prepare("
            SELECT COUNT(*) 
            FROM dms_gl_entries e
            JOIN dms_gl_journals j ON j.id=e.journal_id AND j.org_id=e.org_id
            WHERE e.org_id=? AND j.jdate BETWEEN ? AND ?
        ");
        $b->execute([$orgId, $from, $to]);
        $er = (int)$b->fetchColumn();

        return ['journals_in_range'=>$jr, 'entries_in_range'=>$er];
    }
}