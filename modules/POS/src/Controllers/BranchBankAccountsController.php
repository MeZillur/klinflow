<?php
declare(strict_types=1);

namespace Modules\POS\Controllers;

use PDO;
use Throwable;

/**
 * BranchBankAccountsController
 *
 * Outlet-level mapping to HQ bank/cash accounts.
 * Routes (under /banking):
 *  - GET  /banking/branches
 *  - GET  /banking/branches/create
 *  - POST /banking/branches
 *  - GET  /banking/branches/{id}/edit
 *  - POST /banking/branches/{id}
 */
final class BranchBankAccountsController extends BaseController
{
    /* ============================ Infra ============================ */

    private function env(array $ctx): array
    {
        $c       = $this->ctx($ctx);
        $orgId   = (int)($c['org']['id'] ?? $c['org_id'] ?? 0);
        $basePos = (string)($c['module_base'] ?? '/apps/pos');
        $base    = $basePos . '/banking';
        $branchId   = (int)($c['branch']['id'] ?? 0);
        $branchName = (string)($c['branch']['name'] ?? '');

        return [$c, $base, $orgId, $branchId, $branchName];
    }

    private function ensureTable(PDO $pdo): void
    {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS pos_branch_bank_accounts (
              id                   BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
              org_id               INT NOT NULL,
              branch_id            INT NOT NULL,
              branch_name          VARCHAR(160) NULL,
              hq_bank_account_id   INT NOT NULL,
              alias_name           VARCHAR(160) NULL,
              is_default           TINYINT(1) NOT NULL DEFAULT 0,
              is_active            TINYINT(1) NOT NULL DEFAULT 1,
              created_at           TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
              updated_at           TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
                                       ON UPDATE CURRENT_TIMESTAMP,
              PRIMARY KEY (id),
              KEY idx_org_branch (org_id, branch_id),
              KEY idx_org_hq (org_id, hq_bank_account_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
    }

    /** normalize one row for views */
    private function norm(array $r): array
    {
        return [
            'id'                 => (int)($r['id'] ?? 0),
            'branch_id'          => (int)($r['branch_id'] ?? 0),
            'branch_name'        => (string)($r['branch_name'] ?? ''),
            'hq_bank_account_id' => (int)($r['hq_bank_account_id'] ?? 0),
            'alias_name'         => (string)($r['alias_name'] ?? ''),
            'is_default'         => (int)($r['is_default'] ?? 0),
            'is_active'          => (int)($r['is_active'] ?? 1),
            'created_at'         => $r['created_at'] ?? null,
            'updated_at'         => $r['updated_at'] ?? null,
        ];
    }

    /* ============================ Screens ============================ */

    // GET /banking/branches
    public function index(array $ctx = []): void
    {
        try {
            [$c, $base, $orgId, $branchId, $branchName] = $this->env($ctx);
            $pdo = $this->pdo();
            $this->ensureTable($pdo);

            $needsBranch = $branchId <= 0;

            $rows    = [];
            $hqMap   = [];
            $search  = trim((string)($_GET['q'] ?? ''));

            // HQ bank accounts (for display / filter)
            $hqRows = $this->rows("
                SELECT bank_account_id, name, bank_name, account_no
                  FROM pos_bank_accounts
                 WHERE org_id = :o
                 ORDER BY bank_name, name
            ", [':o' => $orgId]);

            foreach ($hqRows as $r) {
                $hqMap[(int)$r['bank_account_id']] = [
                    'id'         => (int)$r['bank_account_id'],
                    'label'      => trim(($r['bank_name'] ?? '') . ' — ' . ($r['name'] ?? '')),
                    'account_no' => (string)($r['account_no'] ?? ''),
                ];
            }

            if (!$needsBranch) {
                $sql  = "SELECT * FROM pos_branch_bank_accounts
                          WHERE org_id = :o AND branch_id = :b";
                $bind = [':o' => $orgId, ':b' => $branchId];

                if ($search !== '') {
                    $sql .= " AND (alias_name LIKE :q OR branch_name LIKE :q)";
                    $bind[':q'] = '%' . $search . '%';
                }

                $sql .= " ORDER BY is_default DESC, alias_name ASC, id ASC";

                $rows = array_map([$this, 'norm'], $this->rows($sql, $bind));
            }

            $this->view($c['module_dir'] . '/Views/banking/branch-accounts/index.php', [
                'title'       => 'Outlet Bank & Cash Accounts',
                'base'        => $base,
                'rows'        => $rows,
                'hqMap'       => $hqMap,
                'search'      => $search,
                'branchId'    => $branchId,
                'branchName'  => $branchName,
                'needsBranch' => $needsBranch,
                'ctx'         => $c,
            ], 'shell');

        } catch (Throwable $e) {
            $this->oops('Branch bank accounts list failed', $e);
        }
    }

    // GET /banking/branches/create
    public function create(array $ctx = []): void
    {
        try {
            [$c, $base, $orgId, $branchId, $branchName] = $this->env($ctx);
            if ($branchId <= 0) {
                throw new \RuntimeException('Please select a branch from the top bar first.');
            }

            $pdo = $this->pdo();
            $this->ensureTable($pdo);

            $hqRows = $this->rows("
                SELECT bank_account_id, name, bank_name, account_no
                  FROM pos_bank_accounts
                 WHERE org_id = :o
                 ORDER BY bank_name, name
            ", [':o' => $orgId]);

            $this->view($c['module_dir'] . '/Views/banking/branch-accounts/create.php', [
                'title'      => 'New Outlet Bank Account',
                'base'       => $base,
                'branchId'   => $branchId,
                'branchName' => $branchName,
                'hqAccounts' => $hqRows,
                'ctx'        => $c,
            ], 'shell');

        } catch (Throwable $e) {
            $this->oops('Branch bank account create form failed', $e);
        }
    }

    // POST /banking/branches
    public function store(array $ctx = []): void
    {
        try {
            $this->postOnly();
            [$c, $base, $orgId, $branchId, $branchName] = $this->env($ctx);
            if ($branchId <= 0) {
                throw new \RuntimeException('Branch context missing; select a branch first.');
            }

            $pdo = $this->pdo();
            $this->ensureTable($pdo);

            $hqId   = (int)($_POST['hq_bank_account_id'] ?? 0);
            $alias  = trim((string)($_POST['alias_name'] ?? ''));
            $isDef  = !empty($_POST['is_default']) ? 1 : 0;
            $isAct  = !empty($_POST['is_active']) ? 1 : 0;

            if ($hqId <= 0) {
                throw new \RuntimeException('HQ bank account is required.');
            }

            $hq = $this->row("
                SELECT bank_account_id, name, bank_name
                  FROM pos_bank_accounts
                 WHERE org_id = :o AND bank_account_id = :id
            ", [':o' => $orgId, ':id' => $hqId]);

            if (!$hq) {
                throw new \RuntimeException('Selected HQ bank account not found.');
            }

            if ($alias === '') {
                $alias = (string)($hq['name'] ?? 'Branch account');
            }
            if ($branchName === '') {
                $branchName = 'Branch ' . $branchId;
            }

            $pdo->beginTransaction();

            if ($isDef) {
                $pdo->prepare("
                    UPDATE pos_branch_bank_accounts
                       SET is_default = 0
                     WHERE org_id = ? AND branch_id = ?
                ")->execute([$orgId, $branchId]);
            }

            $ins = $pdo->prepare("
                INSERT INTO pos_branch_bank_accounts
                    (org_id, branch_id, branch_name,
                     hq_bank_account_id, alias_name,
                     is_default, is_active, created_at, updated_at)
                VALUES (?,?,?,?,?,?,?, NOW(), NOW())
            ");
            $ins->execute([
                $orgId,
                $branchId,
                $branchName,
                $hqId,
                $alias,
                $isDef,
                $isAct,
            ]);

            $pdo->commit();
            $this->redirect($base . '/branches');

        } catch (Throwable $e) {
            if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $this->oops('Branch bank account create failed', $e);
        }
    }

    // GET /banking/branches/{id}/edit
    public function edit(array $ctx = [], int $id = 0): void
    {
        try {
            [$c, $base, $orgId, $branchId, $branchName] = $this->env($ctx);
            $pdo = $this->pdo();
            $this->ensureTable($pdo);

            $row = $this->row("
                SELECT * FROM pos_branch_bank_accounts
                 WHERE org_id = :o AND id = :i
            ", [':o' => $orgId, ':i' => $id]);

            if (!$row) {
                http_response_code(404);
                echo 'Branch bank account not found';
                return;
            }

            $hqRows = $this->rows("
                SELECT bank_account_id, name, bank_name, account_no
                  FROM pos_bank_accounts
                 WHERE org_id = :o
                 ORDER BY bank_name, name
            ", [':o' => $orgId]);

            $this->view($c['module_dir'] . '/Views/banking/branch-accounts/edit.php', [
                'title'      => 'Edit Outlet Bank Account',
                'base'       => $base,
                'branchId'   => $branchId,
                'branchName' => $branchName ?: (string)($row['branch_name'] ?? ''),
                'b'          => $this->norm($row),
                'hqAccounts' => $hqRows,
                'ctx'        => $c,
            ], 'shell');

        } catch (Throwable $e) {
            $this->oops('Branch bank account edit failed', $e);
        }
    }

    // POST /banking/branches/{id}
    public function update(array $ctx = [], int $id = 0): void
    {
        try {
            $this->postOnly();
            [$c, $base, $orgId, $branchId, $branchName] = $this->env($ctx);
            $pdo = $this->pdo();
            $this->ensureTable($pdo);

            $hqId  = (int)($_POST['hq_bank_account_id'] ?? 0);
            $alias = trim((string)($_POST['alias_name'] ?? ''));
            $isDef = !empty($_POST['is_default']) ? 1 : 0;
            $isAct = !empty($_POST['is_active']) ? 1 : 0;

            if ($hqId <= 0) {
                throw new \RuntimeException('HQ bank account is required.');
            }

            $hq = $this->row("
                SELECT bank_account_id, name
                  FROM pos_bank_accounts
                 WHERE org_id = :o AND bank_account_id = :id
            ", [':o' => $orgId, ':id' => $hqId]);

            if (!$hq) {
                throw new \RuntimeException('Selected HQ bank account not found.');
            }

            if ($alias === '') {
                $alias = (string)($hq['name'] ?? 'Branch account');
            }

            $pdo->beginTransaction();

            if ($isDef && $branchId > 0) {
                $pdo->prepare("
                    UPDATE pos_branch_bank_accounts
                       SET is_default = 0
                     WHERE org_id = ? AND branch_id = ?
                ")->execute([$orgId, $branchId]);
            }

            $u = $pdo->prepare("
                UPDATE pos_branch_bank_accounts
                   SET hq_bank_account_id = :h,
                       alias_name         = :a,
                       is_default         = :d,
                       is_active          = :s,
                       updated_at         = NOW()
                 WHERE org_id = :o AND id = :i
            ");
            $u->execute([
                ':h' => $hqId,
                ':a' => $alias,
                ':d' => $isDef,
                ':s' => $isAct,
                ':o' => $orgId,
                ':i' => $id,
            ]);

            $pdo->commit();
            $this->redirect($base . '/branches');

        } catch (Throwable $e) {
            if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $this->oops('Branch bank account update failed', $e);
        }
    }
}