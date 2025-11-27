<?php
declare(strict_types=1);

namespace Modules\POS\Controllers;

use PDO;
use Throwable;

final class ExpensesController extends BaseController
{
    /* --------------------------------------------------------
     * Small helper: resolve ctx → [$c, $base, $orgId, $pdo]
     * -------------------------------------------------------- */
    private function env(array $ctx): array
    {
        $c     = $this->ctx($ctx);
        $base  = (string)($c['module_base'] ?? '/apps/pos');
        $orgId = (int)($c['org']['id'] ?? $c['org_id'] ?? 0);
        $pdo   = $this->pdo();
        return [$c, $base, $orgId, $pdo];
    }

    /* --------------------------------------------------------
     * Ensure pos_expenses table
     * -------------------------------------------------------- */
    private function ensureExpensesTable(PDO $pdo): void
    {
        // Only create if table missing
        $st = $pdo->prepare("
            SELECT COUNT(*)
            FROM information_schema.TABLES
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME   = 'pos_expenses'
        ");
        $st->execute();
        if ((int)$st->fetchColumn() > 0) return;

        $pdo->exec("
            CREATE TABLE pos_expenses (
              expense_id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
              org_id              BIGINT UNSIGNED NOT NULL,
              branch_id           BIGINT UNSIGNED NULL,
              expense_no          VARCHAR(40)     NOT NULL,
              expense_date        DATE            NOT NULL,
              payee               VARCHAR(160)    NULL,
              reference           VARCHAR(80)     NULL,
              description         TEXT            NULL,
              gl_account_id       BIGINT UNSIGNED NULL,
              gl_subaccount_id    BIGINT UNSIGNED NULL,
              bank_account_id     BIGINT UNSIGNED NULL,
              method              VARCHAR(32)     NOT NULL DEFAULT 'Cash',
              total_cents         BIGINT          NOT NULL DEFAULT 0,
              paid_amount_cents   BIGINT          NOT NULL DEFAULT 0,
              status              VARCHAR(16)     NOT NULL DEFAULT 'approved',
              gl_journal_id       BIGINT UNSIGNED NULL,
              gl_posted_at        DATETIME        NULL,
              created_at          TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
              updated_at          TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP
                                                  ON UPDATE CURRENT_TIMESTAMP,
              PRIMARY KEY (expense_id),
              UNIQUE KEY uq_org_expense_no (org_id, expense_no),
              KEY idx_org_date   (org_id, expense_date),
              KEY idx_org_status (org_id, status)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
    }

    private function nextExpenseNo(PDO $pdo, int $orgId): string
    {
        $year   = date('Y');
        $prefix = "EXP-{$year}-";

        $st = $pdo->prepare("
            SELECT expense_no
              FROM pos_expenses
             WHERE org_id = ?
               AND expense_no LIKE ?
             ORDER BY expense_id DESC
             LIMIT 1
        ");
        $st->execute([$orgId, $prefix.'%']);
        $last = (string)($st->fetchColumn() ?: '');
        $seq  = 0;

        if ($last && preg_match('/^EXP-'.$year.'-(\d{5})$/', $last, $m)) {
            $seq = (int)$m[1];
        }
        return $prefix . str_pad((string)($seq + 1), 5, '0', STR_PAD_LEFT);
    }
  
  
  
  		/**
 * Create a balanced GL journal for a single–line expense.
 *
 * - Debit  : expense GL account (mandatory)
 * - Credit : cash / bank GL account (derived from bank or generic cash)
 *
 * This is deliberately defensive: if we cannot safely determine both
 * sides, we simply skip the GL post instead of breaking.
 */
private function postExpenseToGl(
    PDO $pdo,
    int $orgId,
    int $expenseId,
    string $expenseNo,
    string $dateStr,
    int $expenseAccId,
    ?int $subAccId,
    float $amount,
    ?int $bankId,
    string $method,
    string $description
): void {
    // Basic guard rails
    if ($amount <= 0 || $expenseAccId <= 0) {
        return;
    }

    // All GL stuff must exist
    if (
        !$this->hasTable($pdo, 'dms_gl_journals') ||
        !$this->hasTable($pdo, 'dms_gl_entries')  ||
        !$this->hasTable($pdo, 'dms_gl_accounts')
    ) {
        return;
    }

    // ---------- Determine CREDIT account (cash / bank) ----------
    $creditAccId = 0;

    // If payment is from a bank account, try mapping via pos_bank_accounts.gl_account_id
    if ($bankId && $this->hasTable($pdo, 'pos_bank_accounts') && $this->hasCol($pdo, 'pos_bank_accounts', 'gl_account_id')) {
        $creditAccId = (int)($this->val("
            SELECT gl_account_id
              FROM pos_bank_accounts
             WHERE org_id = :o
               AND (bank_account_id = :id OR id = :id)
             LIMIT 1
        ", [':o' => $orgId, ':id' => $bankId]) ?? 0);
    }

    // Fallback: first cash-type account
    if ($creditAccId <= 0) {
        $creditAccId = (int)($this->val("
            SELECT id
              FROM dms_gl_accounts
             WHERE org_id = :o
               AND LOWER(type) IN ('cash','cash in hand','petty cash')
             ORDER BY code ASC
             LIMIT 1
        ", [':o' => $orgId]) ?? 0);
    }

    if ($creditAccId <= 0) {
        // Still no credit side → do not post half a journal
        return;
    }

    // ---------- Create journal header ----------
    $jtype  = 'EXP';
    $memo   = "Expense {$expenseNo}" . ($description !== '' ? " - {$description}" : '');
    $srcTbl = 'pos_expenses';

    $cols = ['org_id','jdate','jno','jtype','memo','created_at'];
    $ph   = [':o',   ':dt',  ':jno',':jt', ':memo',':ca'];

    $params = [
        ':o'    => $orgId,
        ':dt'   => $dateStr,
        ':jno'  => $expenseNo,
        ':jt'   => $jtype,
        ':memo' => $memo,
        ':ca'   => date('Y-m-d H:i:s'),
    ];

    // Optional metadata columns on dms_gl_journals
    if ($this->hasCol($pdo, 'dms_gl_journals', 'source_module')) {
        $cols[] = 'source_module';
        $ph[]   = ':srcm';
        $params[':srcm'] = 'pos_expenses';
    }
    if ($this->hasCol($pdo, 'dms_gl_journals', 'source_table')) {
        $cols[] = 'source_table';
        $ph[]   = ':srct';
        $params[':srct'] = $srcTbl;
    }
    if ($this->hasCol($pdo, 'dms_gl_journals', 'source_id')) {
        $cols[] = 'source_id';
        $ph[]   = ':srcid';
        $params[':srcid'] = $expenseId;
    }

    $sql = "INSERT INTO dms_gl_journals (".implode(',', $cols).")
            VALUES (".implode(',', $ph).")";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    $journalId = (int)$pdo->lastInsertId();
    if ($journalId <= 0) {
        return;
    }

    // ---------- Insert lines (balanced) ----------
    $drAmt = $amount;
    $crAmt = $amount;

    // Debit expense
    $pdo->prepare("
        INSERT INTO dms_gl_entries
          (org_id, journal_id, account_id, dr, cr, memo)
        VALUES
          (:o, :j, :acc, :dr, 0, :memo)
    ")->execute([
        ':o'   => $orgId,
        ':j'   => $journalId,
        ':acc' => $expenseAccId,
        ':dr'  => $drAmt,
        ':memo'=> 'Expense debit',
    ]);

    // Credit cash / bank
    $pdo->prepare("
        INSERT INTO dms_gl_entries
          (org_id, journal_id, account_id, dr, cr, memo)
        VALUES
          (:o, :j, :acc, 0, :cr, :memo)
    ")->execute([
        ':o'   => $orgId,
        ':j'   => $journalId,
        ':acc' => $creditAccId,
        ':cr'  => $crAmt,
        ':memo'=> 'Expense payment',
    ]);

    // ---------- Link back to expense ----------
    $pdo->prepare("
        UPDATE pos_expenses
           SET gl_journal_id = :jid,
               gl_posted_at  = NOW()
         WHERE expense_id    = :id
           AND org_id        = :o
    ")->execute([
        ':jid' => $journalId,
        ':id'  => $expenseId,
        ':o'   => $orgId,
    ]);
}
  

        /* ============================================================
     * GET /expenses
     * ============================================================ */
    public function index(array $ctx = []): void
    {
        try {
            [$c, $base, $orgId, $pdo] = $this->env($ctx);
            $this->ensureExpensesTable($pdo);

            $q        = trim((string)($_GET['q']       ?? ''));
            $status   = trim((string)($_GET['status']  ?? ''));
            $from     = trim((string)($_GET['from']    ?? ''));
            $to       = trim((string)($_GET['to']      ?? ''));
            $branchId = (int)($_GET['branch_id']       ?? 0);

            $page    = max(1, (int)($_GET['page'] ?? 1));
            $perPage = 25;
            $offset  = ($page - 1) * $perPage;

            $tbl = 'pos_expenses';

            // ---------- build column list safely ----------
            $cols = [
                'e.expense_id',
                'e.expense_no',
                'e.expense_date',
                'e.status',
                'e.total_cents',
                'e.paid_amount_cents',
                'e.method',
                'e.branch_id',
            ];

            if ($this->hasCol($pdo, $tbl, 'payee')) {
                $cols[] = 'e.payee';
            }
            if ($this->hasCol($pdo, $tbl, 'reference')) {
                $cols[] = 'e.reference';
            }

            $select = implode(', ', $cols);

            $sql = "
                SELECT
                  {$select},
                  b.name AS branch_name
                FROM {$tbl} e
                LEFT JOIN pos_branches b
                  ON b.org_id = e.org_id
                 AND b.id     = e.branch_id
                WHERE e.org_id = :o
            ";
            $bind = [':o' => $orgId];

            // ---------- search block (only existing cols) ----------
            if ($q !== '') {
                $parts = ['e.expense_no LIKE :q'];

                if ($this->hasCol($pdo, $tbl, 'payee')) {
                    $parts[] = 'e.payee LIKE :q';
                }
                if ($this->hasCol($pdo, $tbl, 'reference')) {
                    $parts[] = 'e.reference LIKE :q';
                }
                if ($this->hasCol($pdo, $tbl, 'description')) {
                    $parts[] = 'e.description LIKE :q';
                }

                $sql      .= ' AND (' . implode(' OR ', $parts) . ')';
                $bind[':q'] = '%'.$q.'%';
            }

            if ($status !== '') {
                $sql      .= " AND e.status = :s";
                $bind[':s'] = $status;
            }
            if ($branchId > 0) {
                $sql      .= " AND e.branch_id = :b";
                $bind[':b'] = $branchId;
            }
            if ($from !== '') {
                $sql         .= " AND e.expense_date >= :from";
                $bind[':from'] = $from;
            }
            if ($to !== '') {
                $sql       .= " AND e.expense_date <= :to";
                $bind[':to'] = $to;
            }

            // ---------- total count ----------
            $countSql = "SELECT COUNT(*) FROM ({$sql}) x";
            $total    = (int)$this->val($countSql, $bind);

            // ---------- data page ----------
            $sql .= " ORDER BY e.expense_date DESC, e.expense_id DESC
                      LIMIT {$perPage} OFFSET {$offset}";

            $rows = $this->rows($sql, $bind);

            // summary cards
            $cards = [
                'approved' => (int)$this->val("
                    SELECT COALESCE(SUM(total_cents),0)
                      FROM {$tbl}
                     WHERE org_id = :o AND status = 'approved'
                ", [':o' => $orgId]),
                'paid' => (int)$this->val("
                    SELECT COALESCE(SUM(total_cents),0)
                      FROM {$tbl}
                     WHERE org_id = :o AND status = 'paid'
                ", [':o' => $orgId]),
            ];

            // branches for filter
            $branches = [];
            if ($this->hasTable($pdo, 'pos_branches')) {
                $branches = $this->rows("
                    SELECT id, name
                      FROM pos_branches
                     WHERE org_id = :o
                     ORDER BY name ASC
                ", [':o' => $orgId]);
            }

            $money = function (int $cents): string {
                return number_format($cents / 100, 2);
            };

            $pages = max(1, (int)ceil($total / $perPage));

            $this->view(
                'expenses/index',
                [
                    'title'    => 'Expenses',
                    'base'     => $base,
                    'rows'     => $rows,
                    'page'     => $page,
                    'pages'    => $pages,
                    'total'    => $total,
                    'q'        => $q,
                    'status'   => $status,
                    'from'     => $from,
                    'to'       => $to,
                    'branchId' => $branchId,
                    'branches' => $branches,
                    'cards'    => $cards,
                    'money'    => $money,
                ],
                'shell'
            );
        } catch (Throwable $e) {
            $this->oops('Expenses index failed', $e);
        }
    }

    /* ============================================================
     * GET /expenses/create
     * ============================================================ */
    public function create(array $ctx = []): void
    {
        try {
            [$c, $base, $orgId, $pdo] = $this->env($ctx);
            $this->ensureExpensesTable($pdo);

            $branchId = (int)($c['branch_id'] ?? 0);

            // Branches
            $branches = [];
            if ($this->hasTable($pdo, 'pos_branches')) {
                $branches = $this->rows("
                    SELECT id, name
                      FROM pos_branches
                     WHERE org_id = :o
                     ORDER BY name ASC
                ", [':o' => $orgId]);
            }

            // Expense GL accounts (core accounting)
            $expenseAccounts = [];
            if ($this->hasTable($pdo, 'dms_gl_accounts')) {
                $expenseAccounts = $this->rows("
                    SELECT id, code, name
                      FROM dms_gl_accounts
                     WHERE org_id = :o
                       AND LOWER(type) IN ('expense','operating expense','admin expense','expenses')
                     ORDER BY code ASC
                ", [':o' => $orgId]);
            }

            // Optional sub-accounts
            $expenseSubAccounts = [];
            if ($this->hasTable($pdo, 'dms_gl_subaccounts')) {
                $expenseSubAccounts = $this->rows("
                    SELECT id, code, name, account_id
                      FROM dms_gl_subaccounts
                     WHERE org_id = :o
                     ORDER BY code ASC
                ", [':o' => $orgId]);
            }

                        // Bank accounts (HQ)
            $bankAccounts = [];
            if ($this->hasTable($pdo, 'pos_bank_accounts')) {
                $bankTbl = 'pos_bank_accounts';

                $pk = $this->hasCol($pdo, $bankTbl, 'bank_account_id')
                    ? 'bank_account_id'
                    : ($this->hasCol($pdo, $bankTbl, 'id') ? 'id' : null);

                if ($pk) {
                    // Build SELECT list only with existing columns
                    $select = ["{$pk} AS id"];

                    if ($this->hasCol($pdo, $bankTbl, 'name')) {
                        $select[] = 'name';
                    }
                    if ($this->hasCol($pdo, $bankTbl, 'bank_name')) {
                        $select[] = 'bank_name';
                    }
                    if ($this->hasCol($pdo, $bankTbl, 'account_no')) {
                        $select[] = 'account_no';
                    }
                    if ($this->hasCol($pdo, $bankTbl, 'code')) {
                        $select[] = 'code';
                    }
                    if ($this->hasCol($pdo, $bankTbl, 'is_master')) {
                        $select[] = 'is_master';
                    }

                    $sql = 'SELECT '.implode(', ', $select)
                         ." FROM {$bankTbl} WHERE org_id = :o";

                    if ($this->hasCol($pdo, $bankTbl, 'is_active')) {
                        $sql .= " AND is_active = 1";
                    }

                    // Safe ORDER BY
                    $order = [];
                    if ($this->hasCol($pdo, $bankTbl, 'is_master')) {
                        $order[] = 'is_master DESC';
                    }
                    if ($this->hasCol($pdo, $bankTbl, 'name')) {
                        $order[] = 'name ASC';
                    }
                    if (!$order) {
                        $order[] = "{$pk} ASC";
                    }
                    $sql .= ' ORDER BY '.implode(', ', $order);

                    $bankAccounts = $this->rows($sql, [':o' => $orgId]);
                }
            }

            $this->view(
                'expenses/create',
                [
                    'title'              => 'New Expense',
                    'base'               => $base,
                    'branches'           => $branches,
                    'branchId'           => $branchId,
                    'expenseAccounts'    => $expenseAccounts,
                    'expenseSubAccounts' => $expenseSubAccounts,
                    'bankAccounts'       => $bankAccounts,
                    'today'              => date('Y-m-d'),
                ],
                'shell'
            );
        } catch (Throwable $e) {
            $this->oops('Expense create form failed', $e);
        }
    }

    /* ============================================================
     * POST /expenses
     * ============================================================ */
    public function store(array $ctx = []): void
    {
        try {
            $this->postOnly();
            [$c, $base, $orgId, $pdo] = $this->env($ctx);
            $this->ensureExpensesTable($pdo);

            $branchId   = (int)($_POST['branch_id']        ?? 0);
            $accId      = (int)($_POST['gl_account_id']    ?? 0);
            $subAccId   = (int)($_POST['gl_subaccount_id'] ?? 0);
            $bankId     = (int)($_POST['bank_account_id']  ?? 0);

            $method      = trim((string)($_POST['method'] ?? 'Cash'));
            $dateStr     = trim((string)($_POST['expense_date'] ?? date('Y-m-d')));
            $payee       = trim((string)($_POST['payee'] ?? ''));
            $reference   = trim((string)($_POST['reference'] ?? ''));
            $description = trim((string)($_POST['description'] ?? ''));
            $status      = trim((string)($_POST['status'] ?? 'approved'));

            $amount = (float)str_replace([','], [''], (string)($_POST['amount'] ?? '0'));

            if ($amount <= 0) {
                throw new \RuntimeException('Amount must be greater than zero');
            }
            if ($accId <= 0) {
                throw new \RuntimeException('Expense GL account is required');
            }
            if ($dateStr === '') {
                $dateStr = date('Y-m-d');
            }
            if ($status === '') {
                $status = 'approved';
            }

            $expenseNo = $this->nextExpenseNo($pdo, $orgId);

            $ins = $pdo->prepare("
                INSERT INTO pos_expenses
                  (org_id, branch_id,
                   expense_no, expense_date,
                   payee, reference, description,
                   gl_account_id, gl_subaccount_id,
                   bank_account_id, method,
                   total_cents, paid_amount_cents,
                   status, created_at, updated_at)
                VALUES
                  (:o, :b,
                   :no, :dt,
                   :payee, :ref, :desc,
                   :acc, :sub,
                   :bank, :method,
                   :total, 0,
                   :status, NOW(), NOW())
            ");
            $ins->execute([
                ':o'     => $orgId,
                ':b'     => $branchId ?: null,
                ':no'    => $expenseNo,
                ':dt'    => $dateStr,
                ':payee' => $payee !== '' ? $payee : null,
                ':ref'   => $reference !== '' ? $reference : null,
                ':desc'  => $description !== '' ? $description : null,
                ':acc'   => $accId,
                ':sub'   => $subAccId ?: null,
                ':bank'  => $bankId ?: null,
                ':method'=> $method !== '' ? $method : 'Cash',
                ':total' => (int)round($amount * 100),
                ':status'=> $status,
            ]);

            $expenseId = (int)$pdo->lastInsertId();

            // Optional GL posting
            if (in_array($status, ['approved', 'paid'], true)
                && $this->hasTable($pdo, 'dms_gl_journals')
                && $this->hasTable($pdo, 'dms_gl_entries')
                && $this->hasTable($pdo, 'dms_gl_accounts')
            ) {
                $this->postExpenseToGl(
                    $pdo,
                    $orgId,
                    $expenseId,
                    $expenseNo,
                    $dateStr,
                    $accId,
                    $subAccId ?: null,
                    $amount,
                    $bankId ?: null,
                    $method,
                    $description
                );
            }

            $this->redirect($base.'/expenses/'.$expenseId);
        } catch (Throwable $e) {
            $this->oops('Expense store failed', $e);
        }
    }

   

    /* ============================================================
     * GET /expenses/{id}
     * ============================================================ */
    public function show(array $ctx = [], int $id = 0): void
    {
        try {
            [$c, $base, $orgId, $pdo] = $this->env($ctx);
            $this->ensureExpensesTable($pdo);

            $row = $this->row("
                SELECT
                  e.*,
                  b.name AS branch_name,
                  ba.name      AS bank_account_name,
                  ba.bank_name AS bank_name,
                  ga.code      AS gl_code,
                  ga.name      AS gl_name
                FROM pos_expenses e
                LEFT JOIN pos_branches      b  ON b.org_id = e.org_id AND b.id = e.branch_id
                LEFT JOIN pos_bank_accounts ba ON ba.org_id = e.org_id
                    AND (ba.bank_account_id = e.bank_account_id OR ba.id = e.bank_account_id)
                LEFT JOIN dms_gl_accounts   ga ON ga.org_id = e.org_id AND ga.id = e.gl_account_id
                WHERE e.org_id = :o AND e.expense_id = :id
            ", [':o' => $orgId, ':id' => $id]);

            if (!$row) {
                http_response_code(404);
                echo 'Expense not found';
                return;
            }

            $money = function (int $cents): string {
                return '৳'.number_format($cents / 100, 2);
            };

            $this->view(
                'expenses/show',
                [
                    'title'   => 'Expense Details',
                    'base'    => $base,
                    'expense' => $row,
                    'money'   => $money,
                ],
                'shell'
            );
        } catch (Throwable $e) {
            $this->oops('Expense detail failed', $e);
        }
    }

    /* ============================================================
     * GET /expenses/{id}/edit
     * ============================================================ */
    public function edit(array $ctx = [], int $id = 0): void
    {
        try {
            [$c, $base, $orgId, $pdo] = $this->env($ctx);
            $this->ensureExpensesTable($pdo);

            $expense = $this->row("
                SELECT *
                  FROM pos_expenses
                 WHERE org_id = :o AND expense_id = :id
            ", [':o' => $orgId, ':id' => $id]);

            if (!$expense) {
                http_response_code(404);
                echo 'Expense not found';
                return;
            }

            // Branches
            $branches = [];
            if ($this->hasTable($pdo, 'pos_branches')) {
                $branches = $this->rows("
                    SELECT id, name
                      FROM pos_branches
                     WHERE org_id = :o
                     ORDER BY name ASC
                ", [':o' => $orgId]);
            }

            // Expense accounts
            $expenseAccounts = [];
            if ($this->hasTable($pdo, 'dms_gl_accounts')) {
                $expenseAccounts = $this->rows("
                    SELECT id, code, name
                      FROM dms_gl_accounts
                     WHERE org_id = :o
                       AND LOWER(type) IN ('expense','operating expense','admin expense','expenses')
                     ORDER BY code ASC
                ", [':o' => $orgId]);
            }

            // Sub-accounts
            $expenseSubAccounts = [];
            if ($this->hasTable($pdo, 'dms_gl_subaccounts')) {
                $expenseSubAccounts = $this->rows("
                    SELECT id, code, name, account_id
                      FROM dms_gl_subaccounts
                     WHERE org_id = :o
                     ORDER BY code ASC
                ", [':o' => $orgId]);
            }

            // Bank accounts
            $bankAccounts = [];
            if ($this->hasTable($pdo, 'pos_bank_accounts')) {
                $pk = $this->hasCol($pdo, 'pos_bank_accounts', 'bank_account_id')
                    ? 'bank_account_id'
                    : ($this->hasCol($pdo, 'pos_bank_accounts', 'id') ? 'id' : null);

                if ($pk) {
                    $sql = "
                        SELECT {$pk} AS id, name, bank_name, account_no, is_master
                          FROM pos_bank_accounts
                         WHERE org_id = :o
                    ";
                    if ($this->hasCol($pdo, 'pos_bank_accounts', 'is_active')) {
                        $sql .= " AND is_active = 1";
                    }
                    $sql .= " ORDER BY is_master DESC, name ASC";
                    $bankAccounts = $this->rows($sql, [':o' => $orgId]);
                }
            }

            $this->view(
                'expenses/edit',
                [
                    'title'              => 'Edit Expense',
                    'base'               => $base,
                    'expense'            => $expense,
                    'branches'           => $branches,
                    'expenseAccounts'    => $expenseAccounts,
                    'expenseSubAccounts' => $expenseSubAccounts,
                    'bankAccounts'       => $bankAccounts,
                ],
                'shell'
            );
        } catch (Throwable $e) {
            $this->oops('Expense edit failed', $e);
        }
    }

    /* ============================================================
     * POST /expenses/{id}
     * (simple update, does not re-post GL)
     * ============================================================ */
    public function update(array $ctx = [], int $id = 0): void
    {
        try {
            $this->postOnly();
            [$c, $base, $orgId, $pdo] = $this->env($ctx);
            $this->ensureExpensesTable($pdo);

            $exists = $this->val("
                SELECT COUNT(*)
                  FROM pos_expenses
                 WHERE org_id = :o AND expense_id = :id
            ", [':o' => $orgId, ':id' => $id]);
            if ((int)$exists === 0) {
                http_response_code(404);
                echo 'Expense not found';
                return;
            }

            $branchId   = (int)($_POST['branch_id']        ?? 0);
            $accId      = (int)($_POST['gl_account_id']    ?? 0);
            $subAccId   = (int)($_POST['gl_subaccount_id'] ?? 0);
            $bankId     = (int)($_POST['bank_account_id']  ?? 0);

            $method      = trim((string)($_POST['method'] ?? 'Cash'));
            $dateStr     = trim((string)($_POST['expense_date'] ?? date('Y-m-d')));
            $payee       = trim((string)($_POST['payee'] ?? ''));
            $reference   = trim((string)($_POST['reference'] ?? ''));
            $description = trim((string)($_POST['description'] ?? ''));
            $status      = trim((string)($_POST['status'] ?? 'approved'));
            $amount      = (float)str_replace([','], [''], (string)($_POST['amount'] ?? '0'));

            if ($amount <= 0) {
                throw new \RuntimeException('Amount must be greater than zero');
            }
            if ($accId <= 0) {
                throw new \RuntimeException('Expense GL account is required');
            }

            $upd = $pdo->prepare("
                UPDATE pos_expenses
                   SET branch_id         = :b,
                       expense_date      = :dt,
                       payee             = :payee,
                       reference         = :ref,
                       description       = :desc,
                       gl_account_id     = :acc,
                       gl_subaccount_id  = :sub,
                       bank_account_id   = :bank,
                       method            = :method,
                       total_cents       = :total,
                       status            = :status,
                       updated_at        = NOW()
                 WHERE org_id           = :o
                   AND expense_id       = :id
            ");
            $upd->execute([
                ':b'     => $branchId ?: null,
                ':dt'    => $dateStr,
                ':payee' => $payee !== '' ? $payee : null,
                ':ref'   => $reference !== '' ? $reference : null,
                ':desc'  => $description !== '' ? $description : null,
                ':acc'   => $accId,
                ':sub'   => $subAccId ?: null,
                ':bank'  => $bankId ?: null,
                ':method'=> $method !== '' ? $method : 'Cash',
                ':total' => (int)round($amount * 100),
                ':status'=> $status !== '' ? $status : 'approved',
                ':o'     => $orgId,
                ':id'    => $id,
            ]);

            $this->redirect($base.'/expenses/'.$id);
        } catch (Throwable $e) {
            $this->oops('Expense update failed', $e);
        }
    }

    /* ============================================================
     * POST /expenses/{id}/delete
     * ============================================================ */
    public function destroy(array $ctx = [], int $id = 0): void
    {
        try {
            $this->postOnly();
            [$c, $base, $orgId, $pdo] = $this->env($ctx);
            $this->ensureExpensesTable($pdo);

            $pdo->prepare("
                DELETE FROM pos_expenses
                 WHERE org_id = :o AND expense_id = :id
            ")->execute([
                ':o' => $orgId,
                ':id'=> $id,
            ]);

            $this->redirect($base.'/expenses');
        } catch (Throwable $e) {
            $this->oops('Expense delete failed', $e);
        }
    }
}