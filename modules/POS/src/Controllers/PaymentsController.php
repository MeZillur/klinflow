<?php
declare(strict_types=1);

namespace Modules\POS\Controllers;

use PDO;
use Throwable;

final class PaymentsController extends BaseController
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
     * Detect primary key of pos_payments (payment_id or id)
     * -------------------------------------------------------- */
    private function paymentPk(PDO $pdo): string
    {
        if ($this->hasCol($pdo, 'pos_payments', 'payment_id')) {
            return 'payment_id';
        }
        if ($this->hasCol($pdo, 'pos_payments', 'id')) {
            return 'id';
        }
        // Fallback – should not happen but keeps SQL valid
        return 'payment_id';
    }

    /* --------------------------------------------------------
     * Ensure main + log tables
     * -------------------------------------------------------- */
    private function ensurePaymentsTable(PDO $pdo): void
    {
        // Check existence
        $st = $pdo->prepare("
            SELECT COUNT(*)
            FROM information_schema.TABLES
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME   = 'pos_payments'
        ");
        $st->execute();
        if ((int)$st->fetchColumn() > 0) {
            return;
        }

        // If table does not exist, create our new schema
        $pdo->exec("
            CREATE TABLE pos_payments (
              payment_id      BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
              org_id          BIGINT UNSIGNED NOT NULL,
              branch_id       BIGINT UNSIGNED NULL,
              ref_no          VARCHAR(40)     NOT NULL,
              payment_date    DATE            NOT NULL,
              type            VARCHAR(32)     NOT NULL,
              direction       ENUM('in','out') NOT NULL,
              party_type      VARCHAR(16)     NOT NULL,
              party_id        BIGINT UNSIGNED NULL,
              party_name      VARCHAR(160)    NULL,
              bank_account_id BIGINT UNSIGNED NULL,
              method          VARCHAR(32)     NOT NULL DEFAULT 'bank',
              amount_cents    BIGINT          NOT NULL DEFAULT 0,
              currency        CHAR(3)         NOT NULL DEFAULT 'BDT',
              status          VARCHAR(16)     NOT NULL DEFAULT 'posted',
              notes           TEXT            NULL,
              gl_journal_id   BIGINT UNSIGNED NULL,
              gl_posted_at    DATETIME        NULL,
              created_at      TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
              updated_at      TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP
                                              ON UPDATE CURRENT_TIMESTAMP,
              PRIMARY KEY (payment_id),
              UNIQUE KEY uq_org_ref_no (org_id, ref_no),
              KEY idx_org_date   (org_id, payment_date),
              KEY idx_org_type   (org_id, type),
              KEY idx_org_party  (org_id, party_type, party_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
    }

    private function ensurePaymentLogsTable(PDO $pdo): void
    {
        if ($this->hasTable($pdo, 'pos_payment_logs')) {
            return;
        }

        $pdo->exec("
            CREATE TABLE pos_payment_logs (
              log_id      BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
              org_id      BIGINT UNSIGNED NOT NULL,
              payment_id  BIGINT UNSIGNED NOT NULL,
              action      VARCHAR(32)     NOT NULL,  -- create|update|status_change etc.
              changed_by  BIGINT UNSIGNED NULL,
              before_json LONGTEXT        NULL,
              after_json  LONGTEXT        NULL,
              ip_address  VARCHAR(64)     NULL,
              user_agent  VARCHAR(255)    NULL,
              created_at  TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
              PRIMARY KEY (log_id),
              KEY idx_org_payment (org_id, payment_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
    }

    private function logPaymentChange(
        PDO $pdo,
        int $orgId,
        int $paymentId,
        string $action,
        array $before = [],
        array $after  = [],
        ?int $userId  = null
    ): void {
        $this->ensurePaymentLogsTable($pdo);

        $ip   = $_SERVER['REMOTE_ADDR']     ?? null;
        $ua   = $_SERVER['HTTP_USER_AGENT'] ?? null;
        $jsonBefore = $before ? json_encode($before, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) : null;
        $jsonAfter  = $after  ? json_encode($after,  JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) : null;

        $st = $pdo->prepare("
            INSERT INTO pos_payment_logs
              (org_id, payment_id, action, changed_by,
               before_json, after_json, ip_address, user_agent, created_at)
            VALUES
              (:o, :pid, :act, :uid, :bj, :aj, :ip, :ua, NOW())
        ");
        $st->execute([
            ':o'   => $orgId,
            ':pid' => $paymentId,
            ':act' => $action,
            ':uid' => $userId ?: null,
            ':bj'  => $jsonBefore,
            ':aj'  => $jsonAfter,
            ':ip'  => $ip,
            ':ua'  => $ua,
        ]);
    }

    private function nextPaymentRef(PDO $pdo, int $orgId): string
    {
        $year   = date('Y');
        $prefix = "PAY-{$year}-";

        $st = $pdo->prepare("
            SELECT ref_no
              FROM pos_payments
             WHERE org_id = ?
               AND ref_no LIKE ?
             ORDER BY ref_no DESC
             LIMIT 1
        ");
        $st->execute([$orgId, $prefix.'%']);
        $last = (string)($st->fetchColumn() ?: '');
        $seq  = 0;

        if ($last && preg_match('/^PAY-'.$year.'-(\d{5})$/', $last, $m)) {
            $seq = (int)$m[1];
        }

        return $prefix . str_pad((string)($seq + 1), 5, '0', STR_PAD_LEFT);
    }

  
  	/**
 * Create a balanced GL journal from a payment / receipt.
 *
 * We expect:
 *  - $drAccId and $crAccId are both > 0
 *  - $amount > 0
 *
 * Direction convention:
 *  - "out"  = money leaving (credit bank, debit expense / supplier etc.)
 *  - "in"   = money coming in (debit bank, credit revenue / customer etc.)
 *
 * BUT: we do not try to be too smart. We simply use the account IDs
 * the caller passes in, so UI / business rules decide the mapping.
 */
private function postPaymentToGl(
    PDO $pdo,
    int $orgId,
    int $paymentId,
    string $refNo,
    string $dateStr,
    int $drAccId,
    int $crAccId,
    float $amount,
    string $direction,
    string $type,
    string $memo
): void {
    if ($amount <= 0 || $drAccId <= 0 || $crAccId <= 0) {
        return;
    }

    if (
        !$this->hasTable($pdo, 'dms_gl_journals') ||
        !$this->hasTable($pdo, 'dms_gl_entries')  ||
        !$this->hasTable($pdo, 'dms_gl_accounts')
    ) {
        return;
    }

    $jtype  = strtoupper($direction) === 'IN' ? 'RCPT' : 'PAY';
    $srcTbl = 'pos_payments';
    $memo   = $memo !== '' ? $memo : "Payment {$refNo}";

    // ---------- Journal header ----------
    $cols = ['org_id','jdate','jno','jtype','memo','created_at'];
    $ph   = [':o',   ':dt',  ':jno',':jt', ':memo',':ca'];

    $params = [
        ':o'    => $orgId,
        ':dt'   => $dateStr,
        ':jno'  => $refNo,
        ':jt'   => $jtype,
        ':memo' => $memo,
        ':ca'   => date('Y-m-d H:i:s'),
    ];

    if ($this->hasCol($pdo, 'dms_gl_journals', 'source_module')) {
        $cols[] = 'source_module';
        $ph[]   = ':srcm';
        $params[':srcm'] = 'pos_payments';
    }
    if ($this->hasCol($pdo, 'dms_gl_journals', 'source_table')) {
        $cols[] = 'source_table';
        $ph[]   = ':srct';
        $params[':srct'] = $srcTbl;
    }
    if ($this->hasCol($pdo, 'dms_gl_journals', 'source_id')) {
        $cols[] = 'source_id';
        $ph[]   = ':srcid';
        $params[':srcid'] = $paymentId;
    }

    $sql = "INSERT INTO dms_gl_journals (".implode(',', $cols).")
            VALUES (".implode(',', $ph).")";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    $journalId = (int)$pdo->lastInsertId();
    if ($journalId <= 0) {
        return;
    }

    $dr = $amount;
    $cr = $amount;

    // Debit line
    $pdo->prepare("
        INSERT INTO dms_gl_entries
          (org_id, journal_id, account_id, dr, cr, memo)
        VALUES
          (:o, :j, :acc, :dr, 0, :memo)
    ")->execute([
        ':o'   => $orgId,
        ':j'   => $journalId,
        ':acc' => $drAccId,
        ':dr'  => $dr,
        ':memo'=> 'Payment debit',
    ]);

    // Credit line
    $pdo->prepare("
        INSERT INTO dms_gl_entries
          (org_id, journal_id, account_id, dr, cr, memo)
        VALUES
          (:o, :j, :acc, 0, :cr, :memo)
    ")->execute([
        ':o'   => $orgId,
        ':j'   => $journalId,
        ':acc' => $crAccId,
        ':cr'  => $cr,
        ':memo'=> 'Payment credit',
    ]);

    // Link back
    if ($this->hasCol($pdo, 'pos_payments', 'gl_journal_id') && $this->hasCol($pdo, 'pos_payments', 'gl_posted_at')) {
        $pdo->prepare("
            UPDATE pos_payments
               SET gl_journal_id = :jid,
                   gl_posted_at  = NOW()
             WHERE id           = :id
               AND org_id       = :o
        ")->execute([
            ':jid' => $journalId,
            ':id'  => $paymentId,
            ':o'   => $orgId,
        ]);
    }
}
  		
  
  
    /* ============================================================
     * GET /payments
     * ============================================================ */
    public function index(array $ctx = []): void
    {
        try {
            [$c, $base, $orgId, $pdo] = $this->env($ctx);
            $this->ensurePaymentsTable($pdo);

            $pk = $this->paymentPk($pdo);

            $q        = trim((string)($_GET['q']        ?? ''));
            $type     = trim((string)($_GET['type']     ?? ''));
            $direction= trim((string)($_GET['direction']?? ''));
            $status   = trim((string)($_GET['status']   ?? ''));
            $from     = trim((string)($_GET['from']     ?? ''));
            $to       = trim((string)($_GET['to']       ?? ''));
            $branchId = (int)($_GET['branch_id']        ?? 0);

            $page    = max(1, (int)($_GET['page'] ?? 1));
            $perPage = 25;
            $offset  = ($page - 1) * $perPage;

            $sql = "
                SELECT
                  p.{$pk} AS payment_id,
                  p.ref_no,
                  p.payment_date,
                  p.type,
                  p.direction,
                  p.party_type,
                  p.party_name,
                  p.method,
                  p.amount_cents,
                  p.status,
                  p.branch_id,
                  b.name AS branch_name
                FROM pos_payments p
                LEFT JOIN pos_branches b
                  ON b.org_id = p.org_id
                 AND b.id     = p.branch_id
                WHERE p.org_id = :o
            ";
            $bind = [':o' => $orgId];

            if ($q !== '') {
                $sql .= " AND (p.ref_no LIKE :q OR p.party_name LIKE :q OR p.notes LIKE :q)";
                $bind[':q'] = '%'.$q.'%';
            }
            if ($type !== '') {
                $sql .= " AND p.type = :t";
                $bind[':t'] = $type;
            }
            if ($direction !== '') {
                $sql .= " AND p.direction = :d";
                $bind[':d'] = $direction;
            }
            if ($status !== '') {
                $sql .= " AND p.status = :s";
                $bind[':s'] = $status;
            }
            if ($branchId > 0) {
                $sql .= " AND p.branch_id = :b";
                $bind[':b'] = $branchId;
            }
            if ($from !== '') {
                $sql .= " AND p.payment_date >= :from";
                $bind[':from'] = $from;
            }
            if ($to !== '') {
                $sql .= " AND p.payment_date <= :to";
                $bind[':to'] = $to;
            }

            $countSql = "SELECT COUNT(*) FROM ({$sql}) x";
            $total    = (int)$this->val($countSql, $bind);

            $sql .= " ORDER BY p.payment_date DESC, p.{$pk} DESC
                      LIMIT {$perPage} OFFSET {$offset}";

            $rows = $this->rows($sql, $bind);

            // summary
            $cards = [
                'in'  => (int)$this->val("
                    SELECT COALESCE(SUM(amount_cents),0)
                      FROM pos_payments
                     WHERE org_id = :o AND direction = 'in'
                ", [':o'=>$orgId]),
                'out' => (int)$this->val("
                    SELECT COALESCE(SUM(amount_cents),0)
                      FROM pos_payments
                     WHERE org_id = :o AND direction = 'out'
                ", [':o'=>$orgId]),
            ];

            $branches = [];
            if ($this->hasTable($pdo, 'pos_branches')) {
                $branches = $this->rows("
                    SELECT id, name
                      FROM pos_branches
                     WHERE org_id = :o
                     ORDER BY name ASC
                ", [':o'=>$orgId]);
            }

            $money = fn(int $cents): string => number_format($cents / 100, 2);
            $pages = max(1, (int)ceil($total / $perPage));

            $this->view(
                'payments/index',
                [
                    'title'    => 'Payments',
                    'base'     => $base,
                    'rows'     => $rows,
                    'page'     => $page,
                    'pages'    => $pages,
                    'total'    => $total,
                    'q'        => $q,
                    'type'     => $type,
                    'direction'=> $direction,
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
            $this->oops('Payments index failed', $e);
        }
    }

    /* ============================================================
     * GET /payments/create
     * ============================================================ */
    public function create(array $ctx = []): void
    {
        try {
            [$c, $base, $orgId, $pdo] = $this->env($ctx);
            $this->ensurePaymentsTable($pdo);

            $branchId = (int)($c['branch_id'] ?? 0);

            // branches
            $branches = [];
            if ($this->hasTable($pdo, 'pos_branches')) {
                $branches = $this->rows("
                    SELECT id, name
                      FROM pos_branches
                     WHERE org_id = :o
                     ORDER BY name ASC
                ", [':o' => $orgId]);
            }

            // Customers
            $customers = [];
            if ($this->hasTable($pdo, 'pos_customers')) {
                $customers = $this->rows("
                    SELECT id, name
                      FROM pos_customers
                     WHERE org_id = :o
                     ORDER BY name ASC
                ", [':o'=>$orgId]);
            }

            // Suppliers
            $suppliers = [];
            if ($this->hasTable($pdo, 'pos_suppliers')) {
                $suppliers = $this->rows("
                    SELECT id, name
                      FROM pos_suppliers
                     WHERE org_id = :o
                     ORDER BY name ASC
                ", [':o'=>$orgId]);
            }

            // Bank accounts
            $bankAccounts = [];
            if ($this->hasTable($pdo, 'pos_bank_accounts')) {
                $pk = $this->hasCol($pdo, 'pos_bank_accounts', 'bank_account_id')
                    ? 'bank_account_id'
                    : ($this->hasCol($pdo, 'pos_bank_accounts', 'id') ? 'id' : null);

                if ($pk) {
                    $cols = ["{$pk} AS id", "name", "bank_name", "account_no"];
                    if ($this->hasCol($pdo, 'pos_bank_accounts', 'code')) {
                        $cols[] = "code";
                    }
                    if ($this->hasCol($pdo, 'pos_bank_accounts', 'is_master')) {
                        $cols[] = "is_master";
                    }

                    $sql = "
                        SELECT ".implode(',', $cols)."
                          FROM pos_bank_accounts
                         WHERE org_id = :o
                    ";
                    if ($this->hasCol($pdo, 'pos_bank_accounts', 'is_active')) {
                        $sql .= " AND is_active = 1";
                    }
                    if ($this->hasCol($pdo, 'pos_bank_accounts', 'is_master')) {
                        $sql .= " ORDER BY is_master DESC, name ASC";
                    } else {
                        $sql .= " ORDER BY name ASC";
                    }

                    $bankAccounts = $this->rows($sql, [':o'=>$orgId]);
                }
            }

            $this->view(
                'payments/create',
                [
                    'title'        => 'New Payment',
                    'base'         => $base,
                    'branches'     => $branches,
                    'branchId'     => $branchId,
                    'customers'    => $customers,
                    'suppliers'    => $suppliers,
                    'bankAccounts' => $bankAccounts,
                    'today'        => date('Y-m-d'),
                ],
                'shell'
            );
        } catch (Throwable $e) {
            $this->oops('Payment create form failed', $e);
        }
    }

    /* ============================================================
 * POST /payments  (create)
 * ============================================================ */
public function store(array $ctx = []): void
{
    try {
        $this->postOnly();
        [$c, $base, $orgId, $pdo] = $this->env($ctx);
        $this->ensurePaymentsTable($pdo);

        $userId   = (int)($c['user_id']   ?? 0);
        $branchId = (int)($_POST['branch_id'] ?? ($c['branch_id'] ?? 0));

        $type      = (string)($_POST['type']       ?? 'customer_receipt'); // customer_receipt|supplier_payment|other_in|other_out
        $partyType = (string)($_POST['party_type'] ?? 'customer');         // customer|supplier|other
        $partyId   = (int)($_POST['party_id']      ?? 0);
        $partyName = trim((string)($_POST['party_name'] ?? ''));

        $bankId    = (int)($_POST['bank_account_id'] ?? 0);
        $method    = trim((string)($_POST['method'] ?? 'bank'));
        $dateStr   = trim((string)($_POST['payment_date'] ?? date('Y-m-d')));

        $amount    = (float)str_replace([','], [''], (string)($_POST['amount'] ?? '0'));
        $status    = trim((string)($_POST['status'] ?? 'posted'));
        $notes     = trim((string)($_POST['notes']  ?? ''));

        // GL: explicit debit/credit accounts from form (if provided)
        $glDebitAccId  = (int)($_POST['gl_debit_account_id']  ?? 0);
        $glCreditAccId = (int)($_POST['gl_credit_account_id'] ?? 0);

        // Direction: cash/bank in or out
        $direction = in_array($type, ['customer_receipt','other_in'], true) ? 'in' : 'out';

        // Basic validation
        if ($amount <= 0) {
            throw new \RuntimeException('Amount must be greater than zero.');
        }
        if ($bankId <= 0) {
            throw new \RuntimeException('Bank account is required.');
        }
        if ($dateStr === '') {
            $dateStr = date('Y-m-d');
        }
        if ($status === '') {
            $status = 'posted';
        }

        // Resolve party name from master tables if only ID is given
        if ($partyName === '' && $partyId > 0) {
            if ($partyType === 'customer' && $this->hasTable($pdo,'pos_customers')) {
                $partyName = (string)($this->val("
                    SELECT name
                      FROM pos_customers
                     WHERE org_id = :o AND id = :id
                     LIMIT 1
                ", [':o' => $orgId, ':id' => $partyId]) ?? '');
            } elseif ($partyType === 'supplier' && $this->hasTable($pdo,'pos_suppliers')) {
                $partyName = (string)($this->val("
                    SELECT name
                      FROM pos_suppliers
                     WHERE org_id = :o AND id = :id
                     LIMIT 1
                ", [':o' => $orgId, ':id' => $partyId]) ?? '');
            }
        }

        // Generate reference code
        $refNo = $this->nextPaymentRef($pdo, $orgId);

        // Store main payment
        $ins = $pdo->prepare("
            INSERT INTO pos_payments
              (org_id, branch_id,
               ref_no, payment_date,
               type, direction,
               party_type, party_id, party_name,
               bank_account_id, method,
               amount_cents, currency,
               status, notes,
               created_at, updated_at)
            VALUES
              (:o, :b,
               :ref, :dt,
               :type, :dir,
               :pty, :pid, :pname,
               :bank, :method,
               :amt, 'BDT',
               :status, :notes,
               NOW(), NOW())
        ");
        $ins->execute([
            ':o'      => $orgId,
            ':b'      => $branchId ?: null,
            ':ref'    => $refNo,
            ':dt'     => $dateStr,
            ':type'   => $type,
            ':dir'    => $direction,
            ':pty'    => $partyType,
            ':pid'    => $partyId ?: null,
            ':pname'  => $partyName ?: null,
            ':bank'   => $bankId,
            ':method' => $method !== '' ? $method : 'bank',
            ':amt'    => (int)round($amount * 100),
            ':status' => $status,
            ':notes'  => $notes !== '' ? $notes : null,
        ]);

        // PK may be "id" or "payment_id"
        $pkCol     = $this->paymentPk($pdo);
        $paymentId = (int)$pdo->lastInsertId();

        // Reload for logging
        $after = $this->row("
            SELECT *
              FROM pos_payments
             WHERE org_id = :o
               AND {$pkCol} = :id
        ", [':o' => $orgId, ':id' => $paymentId]) ?? [];

        $this->logPaymentChange($pdo, $orgId, $paymentId, 'create', [], $after, $userId);

        // Optional GL posting
        if (
            in_array($status, ['posted','cleared'], true) &&
            $this->hasTable($pdo,'dms_gl_journals') &&
            $this->hasTable($pdo,'dms_gl_entries')  &&
            $this->hasTable($pdo,'dms_gl_accounts') &&
            $glDebitAccId > 0 &&
            $glCreditAccId > 0
        ) {
            // Amount in BDT (not cents)
            $this->postPaymentToGl(
                $pdo,
                $orgId,
                $paymentId,
                $refNo,
                $dateStr,
                $glDebitAccId,
                $glCreditAccId,
                $amount,
                $direction,
                $type,
                $notes !== '' ? $notes : ($partyName ?: '')
            );
        }

        $this->redirect($base.'/payments/'.$paymentId);
    } catch (Throwable $e) {
        $this->oops('Payment store failed', $e);
    }
}

    

    /* ============================================================
     * GET /payments/{id}
     * ============================================================ */
    public function show(array $ctx = [], int $id = 0): void
    {
        try {
            [$c, $base, $orgId, $pdo] = $this->env($ctx);
            $this->ensurePaymentsTable($pdo);

            $pk = $this->paymentPk($pdo);

            $row = $this->row("
                SELECT
                  p.*,
                  b.name       AS branch_name,
                  ba.name      AS bank_account_name,
                  ba.bank_name AS bank_name
                FROM pos_payments p
                LEFT JOIN pos_branches b
                  ON b.org_id = p.org_id AND b.id = p.branch_id
                LEFT JOIN pos_bank_accounts ba
                  ON ba.org_id = p.org_id
                 AND (ba.bank_account_id = p.bank_account_id OR ba.id = p.bank_account_id)
                WHERE p.org_id = :o AND p.{$pk} = :id
            ", [':o'=>$orgId, ':id'=>$id]);

            if (!$row) {
                http_response_code(404);
                echo 'Payment not found';
                return;
            }

            $logs = [];
            if ($this->hasTable($pdo, 'pos_payment_logs')) {
                $logs = $this->rows("
                    SELECT *
                      FROM pos_payment_logs
                     WHERE org_id = :o AND payment_id = :id
                     ORDER BY log_id DESC
                ", [':o'=>$orgId, ':id'=>$id]);
            }

            $money = fn(int $cents): string => '৳'.number_format($cents / 100, 2);

            $this->view(
                'payments/show',
                [
                    'title'   => 'Payment Details',
                    'base'    => $base,
                    'payment' => $row,
                    'logs'    => $logs,
                    'money'   => $money,
                ],
                'shell'
            );
        } catch (Throwable $e) {
            $this->oops('Payment detail failed', $e);
        }
    }

    /* ============================================================
     * GET /payments/{id}/edit
     * ============================================================ */
    public function edit(array $ctx = [], int $id = 0): void
    {
        try {
            [$c, $base, $orgId, $pdo] = $this->env($ctx);
            $this->ensurePaymentsTable($pdo);

            $pk  = $this->paymentPk($pdo);
            $row = $this->row("
                SELECT * FROM pos_payments
                 WHERE org_id = :o AND {$pk} = :id
            ", [':o'=>$orgId, ':id'=>$id]);

            if (!$row) {
                http_response_code(404);
                echo 'Payment not found';
                return;
            }

            $branchId = (int)($row['branch_id'] ?? ($c['branch_id'] ?? 0));

            // Branches, customers, suppliers, bankAccounts
            // (same as create() – you can reuse that code here)
            // ...
            // For brevity I’m not pasting again; structurally it stays same.
        } catch (Throwable $e) {
            $this->oops('Payment edit form failed', $e);
        }
    }

    /* ============================================================
     * POST /payments/{id} (update)
     * ============================================================ */
    public function update(array $ctx = [], int $id = 0): void
    {
        try {
            $this->postOnly();
            [$c, $base, $orgId, $pdo] = $this->env($ctx);
            $this->ensurePaymentsTable($pdo);
            $this->ensurePaymentLogsTable($pdo);

            $userId = (int)($c['user_id'] ?? 0);
            $pk     = $this->paymentPk($pdo);

            $before = $this->row("
                SELECT * FROM pos_payments
                 WHERE org_id = :o AND {$pk} = :id
            ", [':o'=>$orgId, ':id'=>$id]);

            if (!$before) {
                http_response_code(404);
                echo 'Payment not found';
                return;
            }

            // ... same logic as previous update(), but
            // in the UPDATE statement we use {$pk} = :id
            // and when reloading "after" we again filter by {$pk}.
        } catch (Throwable $e) {
            $this->oops('Payment update failed', $e);
        }
    }

    // NO delete() – we never physically delete accounting records.
}