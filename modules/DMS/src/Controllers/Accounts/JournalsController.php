<?php
declare(strict_types=1);

namespace Modules\DMS\Controllers\Accounts;

use Modules\DMS\Controllers\BaseController;
use PDO;
use Throwable;

final class JournalsController extends BaseController
{
    /* ---------------- small helpers ---------------- */

    private function hasTable(PDO $pdo, string $table): bool
    {
        $st = $pdo->prepare("
            SELECT 1
            FROM INFORMATION_SCHEMA.TABLES
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?
            LIMIT 1
        ");
        $st->execute([$table]);
        return (bool)$st->fetchColumn();
    }

    private function hasAny(PDO $pdo, string $table): bool
    {
        if (!$this->hasTable($pdo, $table)) return false;
        $q = $pdo->query("SELECT 1 FROM `$table` LIMIT 1");
        return (bool)($q?->fetchColumn());
    }

    private function orgIdSafe(array $ctx): int
    {
        return (int)($ctx['org']['id'] ?? ($_SESSION['tenant_org']['id'] ?? 0));
    }

    private function baseUrl(array $ctx): string
    {
        return (string)($ctx['module_base'] ?? '/apps/dms');
    }

    /* ============================================================
     * GET /accounts/journals  → list
     * ============================================================ */
    public function index(array $ctx): void
    {
        $pdo   = $this->pdo();
        $orgId = $this->orgIdSafe($ctx);
        $rows  = [];

        $hasJ = $this->hasAny($pdo, 'dms_gl_journals');
        $hasE = $this->hasTable($pdo, 'dms_gl_entries');

        if ($hasJ && $hasE) {
            // Normalized path
            $st = $pdo->prepare("
                SELECT j.id, j.jno, j.jdate, j.jtype, j.memo,
                       ROUND(COALESCE(SUM(e.dr),0),2) AS t_debit,
                       ROUND(COALESCE(SUM(e.cr),0),2) AS t_credit
                FROM dms_gl_journals j
                LEFT JOIN dms_gl_entries e
                  ON e.org_id=j.org_id AND e.journal_id=j.id
                WHERE j.org_id=?
                GROUP BY j.id, j.jno, j.jdate, j.jtype, j.memo
                ORDER BY j.jdate DESC, j.id DESC
                LIMIT 200
            ");
            $st->execute([$orgId]);
            $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
            // Clear any legacy cache
            unset($_SESSION['gl_legacy_index'][$orgId]);
        } else {
            // Legacy fallback: group dms_gl into “pseudo journals”
            $sql = "
                SELECT
                  entry_date   AS jdate,
                  ref_no       AS jno,
                  ref_table    AS jtype,
                  MIN(memo)    AS memo,
                  ROUND(COALESCE(SUM(dr),0),2) AS t_debit,
                  ROUND(COALESCE(SUM(cr),0),2) AS t_credit
                FROM dms_gl
                WHERE org_id=?
                GROUP BY entry_date, ref_no, ref_table
                ORDER BY entry_date DESC, ref_no DESC
                LIMIT 200
            ";
            $st = $pdo->prepare($sql);
            $st->execute([$orgId]);
            $tmp = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];

            // Create stable surrogate ids for links
            $rows = [];
            $_SESSION['gl_legacy_index'][$orgId] = []; // reset map
            $k = 1;
            foreach ($tmp as $r) {
                $sid = -1 * $k; // negative id to avoid collision with real ids
                $_SESSION['gl_legacy_index'][$orgId][$sid] = [
                    'jdate' => (string)$r['jdate'],
                    'jno'   => (string)$r['jno'],
                    'jtype' => (string)$r['jtype'],
                ];
                $rows[] = [
                    'id'       => $sid,
                    'jno'      => $r['jno'],
                    'jdate'    => $r['jdate'],
                    'jtype'    => $r['jtype'],
                    'memo'     => $r['memo'],
                    't_debit'  => $r['t_debit'],
                    't_credit' => $r['t_credit'],
                ];
                $k++;
            }
        }

        $this->view('accounts/journals/index', [
            'title'     => 'Journals',
            'rows'      => $rows,
            'active'    => 'accounts',
            'subactive' => 'accounts.journals',
        ], $ctx);
    }

    /* ============================================================
     * GET /accounts/journals/{id} → show (works for real & legacy)
     * ============================================================ */
    public function show(array $ctx, int $id): void
    {
        $pdo   = $this->pdo();
        $orgId = $this->orgIdSafe($ctx);

        $hasJ = $this->hasTable($pdo, 'dms_gl_journals');
        $hasE = $this->hasTable($pdo, 'dms_gl_entries');

        if ($id > 0 && $hasJ && $hasE) {
            // Real journal
            $h = $pdo->prepare("SELECT * FROM dms_gl_journals WHERE org_id=? AND id=?");
            $h->execute([$orgId, $id]);
            $j = $h->fetch(PDO::FETCH_ASSOC);
            if (!$j) { $this->abort404('Journal not found.'); }

            $q = $pdo->prepare("
                SELECT e.id, e.account_id, a.code, a.name,
                       ROUND(e.dr,2) dr, ROUND(e.cr,2) cr, e.memo
                FROM dms_gl_entries e
                JOIN dms_gl_accounts a ON a.org_id=e.org_id AND a.id=e.account_id
                WHERE e.org_id=? AND e.journal_id=?
                ORDER BY e.id
            ");
            $q->execute([$orgId, $id]);
            $lines = $q->fetchAll(PDO::FETCH_ASSOC) ?: [];

            $this->view('accounts/journals/show', [
                'title'  => 'Journal '.$j['jno'],
                'head'   => $j,
                'lines'  => $lines,
                'active' => 'accounts',
                'subactive' => 'accounts.journals',
            ], $ctx);
            return;
        }

        // Legacy pseudo journal via session map
        $key = $_SESSION['gl_legacy_index'][$orgId][$id] ?? null;
        if (!$key) { $this->abort404('Legacy journal not in index or session expired.'); }

        $refNo   = (string)$key['jno'];
        $refDate = (string)$key['jdate'];
        $refType = (string)$key['jtype'];

        $q = $pdo->prepare("
            SELECT id, entry_date AS jdate, ref_no AS jno, ref_table AS jtype,
                   account_code, memo, ROUND(dr,2) dr, ROUND(cr,2) cr
            FROM dms_gl
            WHERE org_id=? AND ref_no=? AND ref_table=? AND entry_date=?
            ORDER BY id
        ");
        $q->execute([$orgId, $refNo, $refType, $refDate]);
        $rows = $q->fetchAll(PDO::FETCH_ASSOC) ?: [];
        if (!$rows) { $this->abort404('Legacy journal rows not found.'); }

        // Synth head
        $head = [
            'id'    => $id,
            'jno'   => $refNo,
            'jdate' => $refDate,
            'jtype' => $refType,
            'memo'  => $rows[0]['memo'] ?? '',
        ];

        // Try to enrich with account names when possible
        $accMap = [];
        if ($this->hasTable($pdo, 'dms_gl_accounts')) {
            $codes = array_values(array_unique(array_map(fn($r)=> (string)$r['account_code'], $rows)));
            if ($codes) {
                $in = implode(',', array_fill(0, count($codes), '?'));
                $st = $pdo->prepare("SELECT code, name FROM dms_gl_accounts WHERE org_id=? AND code IN ($in)");
                $st->execute(array_merge([$orgId], $codes));
                foreach ($st->fetchAll(PDO::FETCH_ASSOC) ?: [] as $r) {
                    $accMap[(string)$r['code']] = (string)$r['name'];
                }
            }
        }

        $lines = [];
        foreach ($rows as $r) {
            $lines[] = [
                'id'         => (int)$r['id'],
                'account_id' => null,
                'code'       => $r['account_code'],
                'name'       => $accMap[$r['account_code']] ?? $r['account_code'],
                'dr'         => (float)$r['dr'],
                'cr'         => (float)$r['cr'],
                'memo'       => $r['memo'],
            ];
        }

        $this->view('accounts/journals/show', [
            'title'  => 'Journal '.$head['jno'],
            'head'   => $head,
            'lines'  => $lines,
            'active' => 'accounts',
            'subactive' => 'accounts.journals',
        ], $ctx);
    }

    /* ============================================================
     * GET /accounts/journals/create
     * ============================================================ */
    public function create(array $ctx): void
    {
        $pdo   = $this->pdo();
        $orgId = $this->orgIdSafe($ctx);

        // accounts for dropdown
        $st = $pdo->prepare("
            SELECT id, code, name, type
            FROM dms_gl_accounts
            WHERE org_id = ?
            ORDER BY code
        ");
        $st->execute([$orgId]);
        $accounts = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $this->view('accounts/journals/create', [
            'title'       => 'Post Manual Journal',
            'accounts'    => $accounts,
            'today'       => date('Y-m-d'),
            'active'      => 'accounts',
            'subactive'   => 'accounts.journals',
        ], $ctx);
    }

    /* ============================================================
     * POST /accounts/journals  → create (normalized tables)
     * ============================================================ */
    public function store(array $ctx): void
    {
        $pdo   = $this->pdo();
        $orgId = $this->orgIdSafe($ctx);

        // If normalized tables are missing, block with a friendly error
        if (!$this->hasTable($pdo,'dms_gl_journals') || !$this->hasTable($pdo,'dms_gl_entries')) {
            $_SESSION['flash_errors'] = ['Manual journals require dms_gl_journals & dms_gl_entries tables.'];
            $_SESSION['form_old']     = $_POST;
            header('Location: '.$this->baseUrl($ctx).'/accounts/journals/create');
            return;
        }

        // ------- Read & sanitize -------
        $jdate     = trim($_POST['jdate'] ?? '');
        $jtype     = trim($_POST['jtype'] ?? 'GENERAL');
        $memo      = trim($_POST['memo']  ?? '');
        $refTable  = trim($_POST['ref_table'] ?? '');
        $refId     = (string)trim($_POST['ref_id'] ?? '');

        $accIds    = $_POST['line_account_id'] ?? [];
        $memos     = $_POST['line_memo']       ?? [];
        $debits    = $_POST['line_debit']      ?? [];
        $credits   = $_POST['line_credit']     ?? [];

        // ------- Validate header -------
        $errors = [];
        if (!$this->isDate($jdate))                { $errors[] = 'Invalid date.'; }
        if ($jtype === '')                         { $jtype = 'GENERAL'; }
        if (count($accIds) === 0)                  { $errors[] = 'Add at least one line.'; }

        // ------- Validate lines & totals -------
        $lines = [];
        $tDr = 0.0; $tCr = 0.0;
        $n = max(count($accIds), count($debits), count($credits));
        for ($i = 0; $i < $n; $i++) {
            $aid = (int)($accIds[$i] ?? 0);
            $lm  = trim((string)($memos[$i]   ?? ''));
            $dr  = (float)str_replace([','], [''], (string)($debits[$i]  ?? '0'));
            $cr  = (float)str_replace([','], [''], (string)($credits[$i] ?? '0'));

            if ($aid <= 0)                         { continue; }
            if ($dr <= 0 && $cr <= 0)              { continue; }
            if ($dr > 0 && $cr > 0)                { $errors[] = "Line ".($i+1).": debit and credit both > 0."; }

            $lines[] = ['account_id'=>$aid, 'memo'=>$lm, 'dr'=>$dr, 'cr'=>$cr];
            $tDr += $dr; $tCr += $cr;
        }
        if (empty($lines))                         { $errors[] = 'No valid lines.'; }
        if (round($tDr,2) !== round($tCr,2))       { $errors[] = 'Journal not balanced (DR ≠ CR).'; }
        if ($tDr <= 0)                             { $errors[] = 'Total must be > 0.'; }

        if (!empty($errors)) {
            $_SESSION['flash_errors'] = $errors;
            $_SESSION['form_old']     = $_POST;
            header('Location: '.$this->baseUrl($ctx).'/accounts/journals/create');
            return;
        }

        // ------- Persist -------
        try {
            $pdo->beginTransaction();

            // Simple jno: J-YYYY-xxxxx
            $jno = $this->nextJournalNo($pdo, $orgId);

            $insJ = $pdo->prepare("
                INSERT INTO dms_gl_journals(org_id, jno, jdate, jtype, memo, ref_table, ref_id)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            $insJ->execute([$orgId, $jno, $jdate, $jtype, $memo, ($refTable ?: null), ($refId ?: null)]);
            $jid = (int)$pdo->lastInsertId();

            $insL = $pdo->prepare("
                INSERT INTO dms_gl_entries (org_id, journal_id, account_id, dr, cr, memo)
                VALUES (?, ?, ?, ?, ?, ?)
            ");

            foreach ($lines as $ln) {
                $insL->execute([
                    $orgId, $jid, (int)$ln['account_id'],
                    round((float)$ln['dr'],2), round((float)$ln['cr'],2),
                    ($ln['memo'] ?: null),
                ]);
            }

            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) { $pdo->rollBack(); }
            $_SESSION['flash_errors'] = ['Failed to save journal: '.$e->getMessage()];
            $_SESSION['form_old']     = $_POST;
            header('Location: '.$this->baseUrl($ctx).'/accounts/journals/create');
            return;
        }

        header('Location: '.$this->baseUrl($ctx).'/accounts/journals/'.$jid);
    }

    private function isDate(string $d): bool
    {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $d)) return false;
        [$y,$m,$day] = array_map('intval', explode('-', $d));
        return checkdate($m,$day,$y);
    }

    private function nextJournalNo(PDO $pdo, int $orgId): string
    {
        $y = date('Y');
        $st = $pdo->prepare("
            SELECT jno FROM dms_gl_journals
            WHERE org_id=? AND jno LIKE CONCAT('J-', ?, '-%')
            ORDER BY id DESC LIMIT 1
        ");
        $st->execute([$orgId, $y]);
        $last = (string)($st->fetchColumn() ?: '');
        $seq  = 0;
        if (preg_match('/^J-\d{4}-(\d{5})$/', $last, $m)) { $seq = (int)$m[1]; }
        return sprintf('J-%s-%05d', $y, $seq+1);
    }
}