<?php
declare(strict_types=1);

namespace Modules\DMS\Controllers;

use PDO;

/**
 * Bank Accounts (list/create/edit/show/make-master)
 * - Auto-links GL account to bank if missing so the Show page always has data.
 * - Totals are calculated from all GL rows for the linked account.
 * - Transactions are paginated (default 50).
 */
final class BankAccountsController extends BaseController
{
    /* ============================================================
     * List
     * GET /bank-accounts
     * ============================================================ */
    public function index(array $ctx): void
    {
        $pdo   = $this->pdo();
        $orgId = $this->orgId($ctx);

        // If this org has no bank accounts, try to seed from COA once
        $this->seedFromCoaIfEmpty($pdo, $orgId);

        $q = trim((string)($_GET['q'] ?? ''));

        $sql = "
            SELECT
              id, code, bank_name, account_name, account_no,
              branch, routing_no,
              opening_balance, current_balance,
              is_master, status, created_at, updated_at, gl_account_id
            FROM dms_bank_accounts
            WHERE org_id = ?
        ";
        $params = [$orgId];
        if ($q !== '') {
            $sql .= " AND (account_name LIKE ? OR bank_name LIKE ? OR account_no LIKE ?)";
            $like = "%{$q}%";
            $params = [$orgId, $like, $like, $like];
        }
        $sql .= " ORDER BY is_master DESC, bank_name ASC, account_name ASC";

        $st = $pdo->prepare($sql);
        $st->execute($params);
        $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $this->view('accounts/bank-accounts/index', [
            'title'     => 'Bank Accounts',
            'rows'      => $rows,
            'q'         => $q,
            'active'    => 'accounts',
            'subactive' => 'accounts.bank',
        ], $ctx);
    }

    /* ============================================================
     * Create form
     * GET /bank-accounts/create
     * ============================================================ */
    public function create(array $ctx): void
    {
        $this->view('accounts/bank-accounts/create', [
            'title'     => 'New Bank Account',
            'today'     => date('Y-m-d'),
            'active'    => 'accounts',
            'subactive' => 'accounts.bank',
        ], $ctx);
    }

    /* ============================================================
     * Store (create)
     * POST /bank-accounts
     * ============================================================ */
    public function store(array $ctx): void
    {
        $pdo   = $this->pdo();
        $orgId = $this->orgId($ctx);

        $bankName = trim((string)($_POST['bank_name']    ?? ''));
        $accName  = trim((string)($_POST['account_name'] ?? ''));
        $accNo    = trim((string)($_POST['account_no']   ?? ''));
        $branch   = trim((string)($_POST['branch']       ?? ''));
        $routing  = trim((string)($_POST['routing_no']   ?? ''));
        $openBal  = (float)str_replace([','], [''], (string)($_POST['opening_balance'] ?? '0'));
        $status   = in_array(($_POST['status'] ?? 'active'), ['active','inactive'], true) ? $_POST['status'] : 'active';
        $isMaster = !empty($_POST['is_master']) ? 1 : 0;

        // Basic validation
        $errors = [];
        if ($bankName === '') $errors[] = 'Bank name is required.';
        if ($accName  === '') $errors[] = 'Account name is required.';
        if ($accNo    === '') $errors[] = 'Account number is required.';
        if ($openBal  <  0)   $errors[] = 'Opening balance cannot be negative.';

        if ($errors) {
            $_SESSION['flash_errors'] = $errors;
            $_SESSION['form_old']     = $_POST;
            $this->redirect($this->moduleBase($ctx) . '/bank-accounts/create');
            return;
        }

        // Generate code BA-YYYY-00001 style
        $code = $this->nextBankCode($pdo, $orgId);

        $pdo->beginTransaction();
        try {
            if ($isMaster) {
                // ensure only one master per org
                $pdo->prepare("UPDATE dms_bank_accounts SET is_master=0 WHERE org_id=? AND is_master=1")->execute([$orgId]);
            }

            $ins = $pdo->prepare("
                INSERT INTO dms_bank_accounts
                  (org_id, code, bank_name, account_name, account_no, branch, routing_no,
                   opening_balance, current_balance, is_master, status, created_at)
                VALUES (?,?,?,?,?,?,?,?,?,?,?, NOW())
            ");
            $ins->execute([
                $orgId, $code, $bankName, $accName, $accNo,
                $branch ?: null, $routing ?: null,
                round($openBal,2), round($openBal,2),
                $isMaster, $status
            ]);

            $pdo->commit();
            $this->redirect($this->moduleBase($ctx) . '/bank-accounts');
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $_SESSION['flash_errors'] = ['Failed to create bank account: '.$e->getMessage()];
            $_SESSION['form_old']     = $_POST;
            $this->redirect($this->moduleBase($ctx) . '/bank-accounts/create');
        }
    }

    /* ============================================================
     * Edit form
     * GET /bank-accounts/{id}/edit
     * ============================================================ */
    public function edit(array $ctx, int $id): void
    {
        $pdo   = $this->pdo();
        $orgId = $this->orgId($ctx);

        $st = $pdo->prepare("SELECT * FROM dms_bank_accounts WHERE org_id=? AND id=?");
        $st->execute([$orgId, $id]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        if (!$row) $this->abort404('Bank account not found.');

        $this->view('accounts/bank-accounts/edit', [
            'title'     => 'Edit Bank Account',
            'b'         => $row,
            'active'    => 'accounts',
            'subactive' => 'accounts.bank',
        ], $ctx);
    }

    /* ============================================================
     * Update (edit submit)
     * POST /bank-accounts/{id}
     * ============================================================ */
    public function update(array $ctx, int $id): void
    {
        $pdo   = $this->pdo();
        $orgId = $this->orgId($ctx);

        $bankName = trim((string)($_POST['bank_name']    ?? ''));
        $accName  = trim((string)($_POST['account_name'] ?? ''));
        $accNo    = trim((string)($_POST['account_no']   ?? ''));
        $branch   = trim((string)($_POST['branch']       ?? ''));
        $routing  = trim((string)($_POST['routing_no']   ?? ''));
        $status   = in_array(($_POST['status'] ?? 'active'), ['active','inactive'], true) ? $_POST['status'] : 'active';
        $isMaster = !empty($_POST['is_master']) ? 1 : 0;

        $errors = [];
        if ($bankName === '') $errors[] = 'Bank name is required.';
        if ($accName  === '') $errors[] = 'Account name is required.';
        if ($accNo    === '') $errors[] = 'Account number is required.';

        if ($errors) {
            $_SESSION['flash_errors'] = $errors;
            $_SESSION['form_old']     = $_POST;
            $this->redirect($this->moduleBase($ctx) . '/bank-accounts/'.$id.'/edit');
            return;
        }

        $pdo->beginTransaction();
        try {
            if ($isMaster) {
                $pdo->prepare("UPDATE dms_bank_accounts SET is_master=0 WHERE org_id=? AND is_master=1")->execute([$orgId]);
            }

            $u = $pdo->prepare("
                UPDATE dms_bank_accounts
                SET bank_name=?, account_name=?, account_no=?, branch=?, routing_no=?, is_master=?, status=?, updated_at=NOW()
                WHERE org_id=? AND id=?
            ");
            $u->execute([
                $bankName, $accName, $accNo, $branch ?: null, $routing ?: null,
                $isMaster, $status, $orgId, $id
            ]);

            $pdo->commit();
            $this->redirect($this->moduleBase($ctx) . '/bank-accounts');
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $_SESSION['flash_errors'] = ['Failed to update bank account: '.$e->getMessage()];
            $_SESSION['form_old']     = $_POST;
            $this->redirect($this->moduleBase($ctx) . '/bank-accounts/'.$id.'/edit');
        }
    }

    /* ============================================================
     * Show (detail + totals + transactions)
     * GET /bank-accounts/{id}
     * ============================================================ */
    public function show(array $ctx, int $id): void
    {
        $pdo   = $this->pdo();
        $orgId = $this->orgId($ctx);

        $st = $pdo->prepare("SELECT * FROM dms_bank_accounts WHERE org_id=? AND id=?");
        $st->execute([$orgId, $id]);
        $b = $st->fetch(PDO::FETCH_ASSOC);
        if (!$b) { $this->abort404('Bank account not found.'); }

        // Ensure a GL account is linked; persist mapping if missing
        $glId = $this->ensureBankGlLinked($pdo, $orgId, (int)$b['id'], (int)($b['gl_account_id'] ?? 0), (string)($b['bank_name'] ?? ''), (string)($b['account_name'] ?? ''));

        // Pagination (simple)
        $perPage = max(1, min((int)($_GET['per_page'] ?? 50), 200));
        $page    = max(1, (int)($_GET['page'] ?? 1));
        $offset  = ($page - 1) * $perPage;

        $recent = [];
        $totalRows = 0;
        $totals = ['deposits'=>0.0,'withdrawals'=>0.0,'count'=>0];

        if ($glId > 0) {
            // Totals (all-time) from GL
            $totals = $this->glTotals($pdo, $orgId, $glId);

            // Transactions (paged)
            $q = $pdo->prepare("
                SELECT j.jdate AS date,
                       j.jno   AS ref_no,
                       j.jtype AS type,
                       COALESCE(e.memo, j.memo) AS description,
                       (e.dr - e.cr) AS amount,
                       NULL AS balance_after
                FROM dms_gl_entries e
                JOIN dms_gl_journals j ON j.id=e.journal_id AND j.org_id=e.org_id
                WHERE e.org_id=? AND e.account_id=?
                ORDER BY j.jdate DESC, j.id DESC, e.id DESC
                LIMIT ? OFFSET ?
            ");
            $q->bindValue(1, $orgId, PDO::PARAM_INT);
            $q->bindValue(2, $glId,  PDO::PARAM_INT);
            $q->bindValue(3, $perPage, PDO::PARAM_INT);
            $q->bindValue(4, $offset,  PDO::PARAM_INT);
            $q->execute();
            $recent = $q->fetchAll(PDO::FETCH_ASSOC) ?: [];

            // Count for pagination
            $c = $pdo->prepare("SELECT COUNT(*) FROM dms_gl_entries WHERE org_id=? AND account_id=?");
            $c->execute([$orgId, $glId]);
            $totalRows = (int)$c->fetchColumn();
        }

        // Attach stats your view expects
        $b['_stat_tx_count']       = $totals['count'];
        $b['_stat_deposits']       = $totals['deposits'];
        $b['_stat_withdrawals']    = $totals['withdrawals'];

        $this->view('accounts/bank-accounts/show', [
            'title'        => 'Bank Account',
            // legacy keys
            'b'            => $b,
            'tx'           => $recent,
            // modern keys
            'account'      => $b,
            'recent_txns'  => $recent,
            'pagination'   => [
                'page'      => $page,
                'per_page'  => $perPage,
                'total'     => $totalRows,
                'pages'     => $perPage ? (int)ceil($totalRows / $perPage) : 1,
            ],
            'active'       => 'accounts',
            'subactive'    => 'accounts.bank',
        ], $ctx);
    }

    /* ============================================================
     * POST /bank-accounts/{id}/make-master
     * ============================================================ */
    public function makeMaster(array $ctx, int $id): void
    {
        $pdo   = $this->pdo();
        $orgId = $this->orgId($ctx);

        $pdo->beginTransaction();
        try {
            $pdo->prepare("UPDATE dms_bank_accounts SET is_master=0 WHERE org_id=? AND is_master=1")->execute([$orgId]);
            $pdo->prepare("UPDATE dms_bank_accounts SET is_master=1, updated_at=NOW() WHERE org_id=? AND id=?")->execute([$orgId,$id]);
            $pdo->commit();
            $this->redirect($this->moduleBase($ctx) . '/bank-accounts/'.$id);
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $this->abort500($e);
        }
    }

    /* ============================ Helpers ============================ */

    /** Seed bank accounts from COA once if none exist for the org */
    private function seedFromCoaIfEmpty(PDO $pdo, int $orgId): void
    {
        $has = (int)$pdo->query("SELECT COUNT(*) FROM dms_bank_accounts WHERE org_id=".(int)$orgId)->fetchColumn();
        if ($has > 0) return;

        $s = $pdo->prepare("
            SELECT id, code, name
            FROM dms_gl_accounts
            WHERE org_id=? AND LOWER(type) IN ('bank','cash at bank','bank account')
            ORDER BY code ASC
        ");
        $s->execute([$orgId]);
        $rows = $s->fetchAll(PDO::FETCH_ASSOC) ?: [];
        if (!$rows) return;

        $isFirst = true;
        foreach ($rows as $r) {
            $code = $this->nextBankCode($pdo, $orgId);
            $pdo->prepare("
                INSERT INTO dms_bank_accounts
                (org_id, code, bank_name, account_name, account_no, opening_balance,
                 current_balance, is_master, status, created_at)
                VALUES (?,?,?,?,?,0,0,?, 'active', NOW())
            ")->execute([
                $orgId, $code, $r['name'], $r['name'], null,
                $isFirst ? 1 : 0
            ]);
            $isFirst = false;
        }
    }

    /** Generate next BA-YYYY-00001 code safely based on last code */
    private function nextBankCode(PDO $pdo, int $orgId): string
    {
        $year   = date('Y');
        $prefix = "BA-{$year}-";
        $st = $pdo->prepare("
            SELECT code FROM dms_bank_accounts
            WHERE org_id=? AND code LIKE ?
            ORDER BY id DESC LIMIT 1
        ");
        $st->execute([$orgId, $prefix.'%']);
        $last = (string)($st->fetchColumn() ?: '');
        $seq  = 0;
        if ($last && preg_match('/^BA-'.$year.'-(\d{5})$/', $last, $m)) {
            $seq = (int)$m[1];
        }
        return $prefix . str_pad((string)($seq+1), 5, '0', STR_PAD_LEFT);
    }

    /**
     * Ensure a GL account is linked to the bank; if missing, find a sensible one and persist.
     * Returns the linked GL account id (0 if none could be found).
     */
    private function ensureBankGlLinked(PDO $pdo, int $orgId, int $bankId, int $glId, string $bankName, string $accountName): int
    {
        if ($glId > 0) return $glId;

        // 1) First by GL type
        $q = $pdo->prepare("SELECT id FROM dms_gl_accounts WHERE org_id=? AND LOWER(type) IN ('bank','cash at bank','bank account') ORDER BY code LIMIT 1");
        $q->execute([$orgId]);
        $glId = (int)($q->fetchColumn() ?: 0);

        // 2) Then by name match
        if (!$glId && ($bankName || $accountName)) {
            $needle = strtolower(trim($bankName ?: $accountName));
            if ($needle !== '') {
                $q = $pdo->prepare("SELECT id FROM dms_gl_accounts WHERE org_id=? AND LOWER(name) LIKE ? ORDER BY code LIMIT 1");
                $q->execute([$orgId, '%'.$needle.'%']);
                $glId = (int)($q->fetchColumn() ?: 0);
            }
        }

        // 3) Persist if found and column present
        if ($glId && $this->hasColumnSafe($pdo,'dms_bank_accounts','gl_account_id')) {
            $u = $pdo->prepare("UPDATE dms_bank_accounts SET gl_account_id=? WHERE org_id=? AND id=?");
            $u->execute([$glId, $orgId, $bankId]);
        }

        return (int)$glId;
    }

    /** Totals (all time) for a GL account: deposits(+) / withdrawals(-) / count */
    private function glTotals(PDO $pdo, int $orgId, int $glId): array
    {
        // Count
        $c = $pdo->prepare("SELECT COUNT(*) FROM dms_gl_entries WHERE org_id=? AND account_id=?");
        $c->execute([$orgId, $glId]);
        $count = (int)$c->fetchColumn();

        // Deposits (sum of positive dr-cr)
        $d = $pdo->prepare("
            SELECT COALESCE(SUM(amt),0) FROM (
              SELECT (dr - cr) AS amt
              FROM dms_gl_entries
              WHERE org_id=? AND account_id=?
            ) t WHERE t.amt > 0
        ");
        $d->execute([$orgId, $glId]);
        $deposits = (float)$d->fetchColumn();

        // Withdrawals (sum of negative, returned as positive magnitude)
        $w = $pdo->prepare("
            SELECT COALESCE(SUM(-amt),0) FROM (
              SELECT (dr - cr) AS amt
              FROM dms_gl_entries
              WHERE org_id=? AND account_id=?
            ) t WHERE t.amt < 0
        ");
        $w->execute([$orgId, $glId]);
        $withdrawals = (float)$w->fetchColumn();

        return ['deposits'=>$deposits, 'withdrawals'=>$withdrawals, 'count'=>$count];
    }

    private function hasColumnSafe(PDO $pdo, string $table, string $column): bool
    {
        $st = $pdo->prepare("
            SELECT COUNT(*) FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?
        ");
        $st->execute([$table, $column]);
        return ((int)$st->fetchColumn()) > 0;
    }
}