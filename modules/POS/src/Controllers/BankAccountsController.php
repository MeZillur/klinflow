<?php
declare(strict_types=1);

namespace Modules\POS\Controllers;

use PDO;
use Throwable;

/**
 * HQ Bank & Cash accounts (master level)
 *
 * Table: pos_bank_accounts
 *   bank_account_id (PK, AI)
 *   org_id
 *   code
 *   name
 *   type ENUM('bank','cash','mobile_wallet')
 *   bank_name
 *   branch_name
 *   account_no
 *   currency
 *   gl_account_id
 *   opening_balance_cents
 *   current_balance_cents
 *   is_active
 *   notes
 *   created_at
 *   updated_at
 */
final class BankAccountsController extends BaseController
{
    /* ======================== Helpers ======================== */

    /**
     * Resolve context helpers:
     * [0] $c     → ctx()
     * [1] $org   → org array
     * [2] $base  → module base + /banking
     * [3] $orgId → org id (int)
     */
    private function env(array $ctx): array
    {
        $c   = $this->ctx($ctx);
        $org = $c['org'] ?? [];
        $orgId = (int)($org['id'] ?? $c['org_id'] ?? 0);
        $base  = rtrim((string)($c['module_base'] ?? '/apps/pos'), '/')
               . '/banking';

        return [$c, $org, $base, $orgId];
    }

    /** Ensure table exists (safe if already created) */
    private function ensureTable(PDO $pdo): void
    {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS pos_bank_accounts (
              bank_account_id        INT UNSIGNED NOT NULL AUTO_INCREMENT,
              org_id                 INT UNSIGNED NOT NULL,
              code                   VARCHAR(32) NOT NULL,
              name                   VARCHAR(160) NOT NULL,
              type                   ENUM('bank','cash','mobile_wallet') NOT NULL DEFAULT 'bank',
              bank_name              VARCHAR(160) NULL,
              branch_name            VARCHAR(160) NULL,
              account_no             VARCHAR(64)  NULL,
              currency               CHAR(3)      NOT NULL DEFAULT 'BDT',
              gl_account_id          INT UNSIGNED NULL,
              opening_balance_cents  BIGINT NOT NULL DEFAULT 0,
              current_balance_cents  BIGINT NOT NULL DEFAULT 0,
              is_active              TINYINT(1) NOT NULL DEFAULT 1,
              notes                  VARCHAR(255) NULL,
              created_at             TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
              updated_at             TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
                                       ON UPDATE CURRENT_TIMESTAMP,
              PRIMARY KEY (bank_account_id),
              KEY idx_pos_bank_accounts_org (org_id),
              UNIQUE KEY uq_pos_bank_accounts_org_code (org_id, code)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
    }

    /** Generate next BA-YYYY-00001 style code per org */
    private function nextCode(PDO $pdo, int $orgId): string
    {
        $year   = date('Y');
        $prefix = "BA-{$year}-";

        $st = $pdo->prepare("
            SELECT code
            FROM pos_bank_accounts
            WHERE org_id = ? AND code LIKE ?
            ORDER BY bank_account_id DESC
            LIMIT 1
        ");
        $st->execute([$orgId, $prefix.'%']);
        $last = (string)($st->fetchColumn() ?: '');

        $seq = 0;
        if ($last && preg_match('/^BA-'.$year.'-(\d{5})$/', $last, $m)) {
            $seq = (int)$m[1];
        }

        return $prefix . str_pad((string)($seq + 1), 5, '0', STR_PAD_LEFT);
    }

    /** Convert posted money string → integer cents */
    private function moneyToCents(string $raw): int
    {
        $clean = str_replace([',',' '], ['',''], $raw);
        if ($clean === '') return 0;
        return (int)round(((float)$clean) * 100);
    }

    /** Normalise one DB row for the views */
    private function norm(array $r): array
    {
        $openingCents = (int)($r['opening_balance_cents'] ?? 0);
        $currentCents = (int)($r['current_balance_cents'] ?? 0);

        return [
            'id'                    => (int)($r['id'] ?? $r['bank_account_id'] ?? 0),
            'code'                  => (string)($r['code'] ?? ''),
            'name'                  => (string)($r['name'] ?? ''),
            'type'                  => (string)($r['type'] ?? 'bank'),
            'bank_name'             => (string)($r['bank_name'] ?? ''),
            'branch_name'           => (string)($r['branch_name'] ?? ''),
            'account_no'            => (string)($r['account_no'] ?? ''),
            'currency'              => (string)($r['currency'] ?? 'BDT'),
            'gl_account_id'         => isset($r['gl_account_id']) ? (int)$r['gl_account_id'] : null,
            'opening_balance_cents' => $openingCents,
            'current_balance_cents' => $currentCents,
            'opening_balance'       => $openingCents / 100,
            'current_balance'       => $currentCents / 100,
            'is_active'             => isset($r['is_active']) ? (int)$r['is_active'] : 1,
            'notes'                 => $r['notes'] ?? null,
            'created_at'            => $r['created_at'] ?? null,
            'updated_at'            => $r['updated_at'] ?? null,
        ];
    }

    /* ======================== Screens ======================== */

    /**
     * Index: GET /banking/accounts   (also /banking)
     */
    public function index(array $ctx = []): void
    {
        try {
            [$c, , $base, $orgId] = $this->env($ctx);
            $pdo = $this->pdo();
            $this->ensureTable($pdo);

            $tbl = 'pos_bank_accounts';

            $cols = [
                'bank_account_id AS id',
                'code',
                'name',
                'type',
                'bank_name',
                'branch_name',
                'account_no',
                'currency',
                'gl_account_id',
                'opening_balance_cents',
                'current_balance_cents',
                'is_active',
                'notes',
                'created_at',
                'updated_at',
            ];

            $select = implode(', ', $cols);

            $q    = trim((string)($_GET['q'] ?? ''));
            $sql  = "SELECT {$select} FROM {$tbl} WHERE org_id = :o";
            $bind = [':o' => $orgId];

            if ($q !== '') {
                $whereLike = [];
                foreach (['name','bank_name','account_no','code'] as $col) {
                    $whereLike[] = "{$col} LIKE :q";
                }
                $sql .= ' AND (' . implode(' OR ', $whereLike) . ')';
                $bind[':q'] = '%' . $q . '%';
            }

            $sql .= ' ORDER BY bank_name ASC, name ASC, bank_account_id ASC';

            $raw  = $this->rows($sql, $bind);
            $rows = array_map([$this, 'norm'], $raw);

            $this->view($c['module_dir'].'/Views/banking/bank-accounts/index.php', [
                'title'  => 'HQ Bank & Cash Accounts',
                'base'   => $base,
                'rows'   => $rows,
                'search' => $q,
                'ctx'    => $c,
            ], 'shell');

        } catch (Throwable $e) {
            $this->oops('HQ bank accounts list failed', $e);
        }
    }

    /**
     * Create form: GET /banking/accounts/create
     */
    public function create(array $ctx = []): void
    {
        try {
            [$c, , $base, ] = $this->env($ctx);
            $this->ensureTable($this->pdo());

            $this->view($c['module_dir'].'/Views/banking/bank-accounts/create.php', [
                'title' => 'New HQ Bank Account',
                'base'  => $base,   // use $base.'/accounts' in the form
                'ctx'   => $c,
            ], 'shell');
        } catch (Throwable $e) {
            $this->oops('HQ bank account create form failed', $e);
        }
    }

    /**
     * Store: POST /banking/accounts
     */
    public function store(array $ctx = []): void
    {
        try {
            $this->postOnly();
            [$c, , $base, $orgId] = $this->env($ctx);
            $pdo = $this->pdo();
            $this->ensureTable($pdo);

            $bankName = trim((string)($_POST['bank_name']    ?? ''));
            $accName  = trim((string)($_POST['account_name'] ?? ''));
            $accNo    = trim((string)($_POST['account_no']   ?? ''));
            $openRaw  = (string)($_POST['opening_balance']   ?? '');
            $type     = 'bank'; // could be extended later
            $isActive = 1;

            if ($bankName === '') throw new \RuntimeException('Bank name is required');
            if ($accName  === '') throw new \RuntimeException('Account name is required');
            if ($accNo    === '') throw new \RuntimeException('Account number is required');

            $openingCents = $this->moneyToCents($openRaw);
            $code         = $this->nextCode($pdo, $orgId);

            $pdo->beginTransaction();

            $ins = $pdo->prepare("
                INSERT INTO pos_bank_accounts
                  (org_id, code, name, type, bank_name, branch_name,
                   account_no, opening_balance_cents, current_balance_cents,
                   is_active, created_at, updated_at)
                VALUES (?,?,?,?,?,?,?,?,?,?, NOW(), NOW())
            ");
            $ins->execute([
                $orgId,
                $code,
                $accName,
                $type,
                $bankName,
                null, // branch_name
                $accNo,
                $openingCents,
                $openingCents,
                $isActive,
            ]);

            $pdo->commit();
            $this->redirect($base.'/accounts');
        } catch (Throwable $e) {
            if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $this->oops('HQ bank account create failed', $e);
        }
    }

    /**
     * Edit form: GET /banking/accounts/{id}/edit
     */
    public function edit(array $ctx = [], int $id = 0): void
    {
        try {
            [$c, , $base, $orgId] = $this->env($ctx);
            $pdo = $this->pdo();
            $this->ensureTable($pdo);

            $row = $this->row(
                "SELECT * FROM pos_bank_accounts WHERE org_id = :o AND bank_account_id = :id",
                [':o'=>$orgId, ':id'=>$id]
            );
            if (!$row) {
                http_response_code(404);
                echo 'Bank account not found';
                return;
            }

            $this->view($c['module_dir'].'/Views/banking/bank-accounts/edit.php', [
                'title' => 'Edit HQ Bank Account',
                'base'  => $base,
                'b'     => $this->norm($row),
                'ctx'   => $c,
            ], 'shell');
        } catch (Throwable $e) {
            $this->oops('HQ bank account edit failed', $e);
        }
    }

    /**
     * Update: POST /banking/accounts/{id}
     */
    public function update(array $ctx = [], int $id = 0): void
    {
        try {
            $this->postOnly();
            [$c, , $base, $orgId] = $this->env($ctx);
            $pdo = $this->pdo();
            $this->ensureTable($pdo);

            $bankName = trim((string)($_POST['bank_name']    ?? ''));
            $accName  = trim((string)($_POST['account_name'] ?? ''));
            $accNo    = trim((string)($_POST['account_no']   ?? ''));
            $openRaw  = (string)($_POST['opening_balance']   ?? '');
            $isActive = !empty($_POST['is_active']) ? 1 : 1; // always active for now

            if ($bankName === '') throw new \RuntimeException('Bank name is required');
            if ($accName  === '') throw new \RuntimeException('Account name is required');
            if ($accNo    === '') throw new \RuntimeException('Account number is required');

            $openingCents = $this->moneyToCents($openRaw);

            $u = $pdo->prepare("
                UPDATE pos_bank_accounts
                   SET bank_name = :bn,
                       name      = :an,
                       account_no= :no,
                       opening_balance_cents = :ob,
                       is_active = :ia,
                       updated_at = NOW()
                 WHERE org_id = :o AND bank_account_id = :id
            ");
            $u->execute([
                ':bn' => $bankName,
                ':an' => $accName,
                ':no' => $accNo,
                ':ob' => $openingCents,
                ':ia' => $isActive,
                ':o'  => $orgId,
                ':id' => $id,
            ]);

            $this->redirect($base.'/accounts');
        } catch (Throwable $e) {
            $this->oops('HQ bank account update failed', $e);
        }
    }

    /**
     * Show (simple): GET /banking/accounts/{id}
     * For now just reuse edit view or a minimal detail.
     */
    public function show(array $ctx = [], int $id = 0): void
    {
        try {
            [$c, , $base, $orgId] = $this->env($ctx);
            $pdo = $this->pdo();
            $this->ensureTable($pdo);

            $row = $this->row(
                "SELECT * FROM pos_bank_accounts WHERE org_id = :o AND bank_account_id = :id",
                [':o'=>$orgId, ':id'=>$id]
            );
            if (!$row) {
                http_response_code(404);
                echo 'Bank account not found';
                return;
            }

            $this->view($c['module_dir'].'/Views/banking/bank-accounts/show.php', [
                'title' => 'Bank Account',
                'base'  => $base,
                'b'     => $this->norm($row),
                'ctx'   => $c,
            ], 'shell');
        } catch (Throwable $e) {
            $this->oops('HQ bank account show failed', $e);
        }
    }

    /**
     * Stub: POST /banking/accounts/{id}/make-master
     * DB schema has no is_master column yet, so just no-op + redirect.
     */
    public function makeMaster(array $ctx = [], int $id = 0): void
    {
        try {
            [$c, , $base, ] = $this->env($ctx);
            // TODO: implement when schema supports is_master
            $this->redirect($base.'/accounts');
        } catch (Throwable $e) {
            $this->oops('Make master bank account failed', $e);
        }
    }
}