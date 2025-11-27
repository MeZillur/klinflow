<?php
declare(strict_types=1);

namespace Modules\DMS\Controllers\Accounts;

use Modules\DMS\Controllers\BaseController;
use PDO;

final class LedgerController extends BaseController
{
    /* ---------- tiny helpers ---------- */

    private function hasTable(PDO $pdo, string $name): bool
    {
        $st = $pdo->prepare("
            SELECT 1
            FROM INFORMATION_SCHEMA.TABLES
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

    private function orgIdSafe(array $ctx): int
    {
        return (int)($ctx['org']['id'] ?? ($_SESSION['tenant_org']['id'] ?? 0));
    }

    /* ---------- controller ---------- */

    /** GET /accounts/ledger */
    public function index(?array $ctx = null): void
    {
        $c      = $this->ctx($ctx);
        $pdo    = $this->pdo();
        $orgId  = $this->orgIdSafe($c);
        $base   = $this->moduleBase($c);

        // Inputs (defaults: current month)
        $from    = (string)($_GET['from'] ?? date('Y-m-01'));
        $to      = (string)($_GET['to']   ?? date('Y-m-d'));
        $isPrint = isset($_GET['print']) && $_GET['print'] === '1';

        // We support two modes:
        //  A) normalized (journals+entries)  B) legacy (flat dms_gl)
        $hasNorm = $this->hasAny($pdo, 'dms_gl_journals') && $this->hasTable($pdo, 'dms_gl_entries');
        $hasGL   = $this->hasAny($pdo, 'dms_gl');

        // Build account list for the selector
        [$accounts, $selectedId, $selectedCode] = $this->resolveAccountsAndSelection($pdo, $orgId, $hasNorm, $hasGL);

        // If nothing to show, render an empty page gracefully
        if (!$accounts) {
            $this->view($isPrint ? 'accounts/ledger/print' : 'accounts/ledger/index', [
                'title'       => 'General Ledger',
                'accounts'    => [],
                'account'     => null,
                'account_id'  => 0,
                'from'        => $from,
                'to'          => $to,
                'opening'     => 0.0,
                'rows'        => [],
                'module_base' => $base,
            ], $c);
            return;
        }

        // If user passed an account_id, respect it; otherwise keep auto-picked one
        $requested = (int)($_GET['account_id'] ?? 0);
        if ($requested > 0) {
            foreach ($accounts as $acc) {
                if ((int)$acc['id'] === $requested) {
                    $selectedId   = $requested;
                    $selectedCode = (string)($acc['code'] ?? '');
                    break;
                }
            }
        }

        // Find the meta row for view
        $acct = null;
        foreach ($accounts as $acc) {
            if ((int)$acc['id'] === $selectedId) { $acct = $acc; break; }
        }

        // Pull opening & rows based on source
        $opening = 0.0;
        $rows    = [];

        if ($hasNorm) {
            // Opening (sum before from)
            $op = $pdo->prepare("
                SELECT COALESCE(SUM(e.dr - e.cr),0)
                FROM dms_gl_entries e
                JOIN dms_gl_journals j
                  ON j.id=e.journal_id AND j.org_id=e.org_id
                WHERE e.org_id=? AND e.account_id=? AND j.jdate < ?
            ");
            $op->execute([$orgId, $selectedId, $from]);
            $opening = (float)$op->fetchColumn();

            // Period rows
            $st = $pdo->prepare("
                SELECT
                  j.id         AS journal_id,
                  j.jno        AS jno,
                  j.jdate      AS jdate,
                  j.jtype      AS jtype,
                  j.ref_table  AS ref_table,
                  j.ref_id     AS ref_id,
                  COALESCE(e.memo, j.memo) AS memo,
                  ROUND(e.dr,2) AS dr,
                  ROUND(e.cr,2) AS cr
                FROM dms_gl_entries e
                JOIN dms_gl_journals j
                  ON j.id=e.journal_id AND j.org_id=e.org_id
                WHERE e.org_id=? AND e.account_id=? AND j.jdate BETWEEN ? AND ?
                ORDER BY j.jdate, j.id, e.id
            ");
            $st->execute([$orgId, $selectedId, $from, $to]);

            $run = $opening;
            while ($r = $st->fetch(PDO::FETCH_ASSOC)) {
                $r['dr'] = (float)$r['dr'];
                $r['cr'] = (float)$r['cr'];
                $run += ($r['dr'] - $r['cr']);
                $r['running'] = round($run, 2);
                $rows[] = $r;
            }
        } elseif ($hasGL) {
            // Legacy by account_code
            // Opening
            $op = $pdo->prepare("
                SELECT COALESCE(SUM(dr - cr),0)
                FROM dms_gl
                WHERE org_id=? AND account_code=? AND entry_date < ?
            ");
            $op->execute([$orgId, $selectedCode, $from]);
            $opening = (float)$op->fetchColumn();

            // Period rows
            $st = $pdo->prepare("
                SELECT
                  entry_date   AS jdate,
                  ref_no       AS jno,
                  ref_table    AS jtype,
                  NULL         AS ref_table_detail,
                  NULL         AS ref_id,
                  memo,
                  ROUND(dr,2)  AS dr,
                  ROUND(cr,2)  AS cr
                FROM dms_gl
                WHERE org_id=? AND account_code=? AND entry_date BETWEEN ? AND ?
                ORDER BY entry_date, id
            ");
            $st->execute([$orgId, $selectedCode, $from, $to]);

            $run = $opening;
            while ($r = $st->fetch(PDO::FETCH_ASSOC)) {
                $r['dr'] = (float)$r['dr'];
                $r['cr'] = (float)$r['cr'];
                $run += ($r['dr'] - $r['cr']);
                $r['running'] = round($run, 2);
                // normalize keys for view compatibility
                $rows[] = [
                    'journal_id' => null,
                    'jno'        => $r['jno'],
                    'jdate'      => $r['jdate'],
                    'jtype'      => $r['jtype'],
                    'ref_table'  => null,
                    'ref_id'     => null,
                    'memo'       => $r['memo'],
                    'dr'         => $r['dr'],
                    'cr'         => $r['cr'],
                    'running'    => $r['running'],
                ];
            }
        }

        $this->view($isPrint ? 'accounts/ledger/print' : 'accounts/ledger/index', [
            'title'       => 'General Ledger',
            'accounts'    => $accounts,
            'account'     => $acct,
            'account_id'  => $selectedId,
            'from'        => $from,
            'to'          => $to,
            'opening'     => round($opening, 2),
            'rows'        => $rows,
            'module_base' => $base,
        ], $c);
    }

    /**
     * Build the account dropdown and select a default.
     * Returns: [accounts[], selectedId, selectedCode]
     *  - accounts[] each item: ['id'=>int,'code'=>string,'name'=>string]
     *  - selectedCode used when querying legacy dms_gl by account_code
     */
    private function resolveAccountsAndSelection(PDO $pdo, int $orgId, bool $hasNorm, bool $hasGL): array
    {
        $accounts = [];

        // Prefer dms_gl_accounts if present (names + codes)
        if ($this->hasTable($pdo, 'dms_gl_accounts')) {
            $accStmt = $pdo->prepare("
                SELECT id, code, name
                FROM dms_gl_accounts
                WHERE org_id=? AND COALESCE(code,'') <> ''
                ORDER BY code, name
            ");
            $accStmt->execute([$orgId]);
            $accounts = $accStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        }

        // If still empty but we have legacy GL, synthesize from distinct codes
        if (!$accounts && $hasGL) {
            $q = $pdo->prepare("
                SELECT account_code AS code, MIN(memo) AS name_hint
                FROM dms_gl
                WHERE org_id=?
                GROUP BY account_code
                ORDER BY account_code
                LIMIT 500
            ");
            $q->execute([$orgId]);
            $tmp = $q->fetchAll(PDO::FETCH_ASSOC) ?: [];
            $k = 1;
            foreach ($tmp as $r) {
                $accounts[] = [
                    // negative virtual id to avoid clashing with real ids
                    'id'   => -$k++,
                    'code' => (string)$r['code'],
                    'name' => (string)($r['code'].' '.$r['name_hint']),
                ];
            }
        }

        // Select default
        $selectedId   = 0;
        $selectedCode = '';
        if ($accounts) {
            $selectedId   = (int)$accounts[0]['id'];
            $selectedCode = (string)($accounts[0]['code'] ?? '');
        }
        return [$accounts, $selectedId, $selectedCode];
    }
}