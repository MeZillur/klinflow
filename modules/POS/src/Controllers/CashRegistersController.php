<?php
declare(strict_types=1);

namespace Modules\POS\Controllers;

use PDO;
use Throwable;

/**
 * CashRegistersController
 *
 * - HQ user (branch_id = 0) sees all registers.
 * - Branch user (branch_id > 0) sees only that branch's registers.
 * - Simple open / close lifecycle.
 */
final class CashRegistersController extends BaseController
{
    /* ===================== Infra ===================== */

    private function ensureTable(PDO $pdo): void
    {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS pos_cash_registers (
              register_id       BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
              org_id            BIGINT UNSIGNED NOT NULL,
              branch_id         BIGINT UNSIGNED NULL,
              name              VARCHAR(150) NOT NULL,
              code              VARCHAR(50)  NULL,
              status            ENUM('open','closed','inactive') NOT NULL DEFAULT 'open',
              opening_float     DECIMAL(12,2) NOT NULL DEFAULT 0,
              closing_cash      DECIMAL(12,2) NULL,
              opened_at         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
              closed_at         DATETIME NULL,
              notes             TEXT NULL,
              created_at        TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
              updated_at        TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
              created_by        BIGINT UNSIGNED NULL,
              closed_by         BIGINT UNSIGNED NULL,
              PRIMARY KEY (register_id),
              KEY idx_pos_cr_org_branch (org_id, branch_id),
              KEY idx_pos_cr_status (status)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
    }

    /* ===================== Screens ===================== */

    /**
     * GET /banking/cash-registers
     */
    public function index(array $ctx = []): void
    {
        try {
            $c       = $this->ctx($ctx);
            $pdo     = $this->pdo();
            $this->ensureTable($pdo);

            $orgId   = (int)($c['org']['id'] ?? $c['org_id'] ?? 0);
            $branchId= (int)($c['branch_id']    ?? 0);
            $base    = (string)($c['module_base'] ?? '/apps/pos');
            $q       = trim((string)($_GET['q'] ?? ''));

            $sql  = "
                SELECT
                  register_id AS id,
                  name,
                  code,
                  status,
                  opening_float,
                  closing_cash,
                  opened_at,
                  closed_at,
                  branch_id
                FROM pos_cash_registers
                WHERE org_id = :o
            ";
            $bind = [':o' => $orgId];

            // branch user → only own branch
            if ($branchId > 0) {
                $sql .= " AND branch_id = :b";
                $bind[':b'] = $branchId;
            }

            if ($q !== '') {
                $sql .= " AND (name LIKE :q OR code LIKE :q)";
                $bind[':q'] = '%'.$q.'%';
            }

            $sql .= " ORDER BY opened_at DESC, register_id DESC";

            $rows = $this->rows($sql, $bind);

            $this->view($c['module_dir'].'/Views/banking/cash-registers/index.php', [
                'title'      => 'Cash Registers',
                'base'       => $base,
                'rows'       => $rows,
                'search'     => $q,
                'ctx'        => $c,
            ], 'shell');
        } catch (Throwable $e) {
            $this->oops('Cash registers list failed', $e);
        }
    }

    /**
     * GET /banking/cash-registers/create
     */
    public function create(array $ctx = []): void
    {
        try {
            $c    = $this->ctx($ctx);
            $pdo  = $this->pdo();
            $this->ensureTable($pdo);

            $base = (string)($c['module_base'] ?? '/apps/pos');

            $this->view($c['module_dir'].'/Views/banking/cash-registers/create.php', [
                'title' => 'New Cash Register',
                'base'  => $base,
                'ctx'   => $c,
            ], 'shell');
        } catch (Throwable $e) {
            $this->oops('Cash register create form failed', $e);
        }
    }

    /**
     * POST /banking/cash-registers
     */
    public function store(array $ctx = []): void
    {
        try {
            $this->postOnly();
            $c       = $this->ctx($ctx);
            $pdo     = $this->pdo();
            $this->ensureTable($pdo);

            $orgId   = (int)($c['org']['id'] ?? $c['org_id'] ?? 0);
            $branchId= (int)($c['branch_id']    ?? 0);
            $base    = (string)($c['module_base'] ?? '/apps/pos');

            $name    = trim((string)($_POST['name']  ?? ''));
            $code    = trim((string)($_POST['code']  ?? ''));
            $status  = (string)($_POST['status'] ?? 'open');
            $openStr = trim((string)($_POST['opened_at'] ?? ''));
            $float   = (float)str_replace([','], [''], (string)($_POST['opening_float'] ?? '0'));
            $notes   = trim((string)($_POST['notes'] ?? ''));

            if ($name === '') {
                throw new \RuntimeException('Register name is required');
            }
            if (!in_array($status, ['open','closed','inactive'], true)) {
                $status = 'open';
            }

            $openedAt = $openStr !== '' ? ($openStr.' 00:00:00') : date('Y-m-d H:i:s');

            $ins = $pdo->prepare("
                INSERT INTO pos_cash_registers
                  (org_id, branch_id, name, code, status, opening_float,
                   opened_at, notes, created_at, updated_at)
                VALUES
                  (:o, :b, :name, :code, :status, :float, :opened_at, :notes, NOW(), NOW())
            ");
            $ins->execute([
                ':o'          => $orgId,
                ':b'          => $branchId ?: null,
                ':name'       => $name,
                ':code'       => $code !== '' ? $code : null,
                ':status'     => $status,
                ':float'      => round($float, 2),
                ':opened_at'  => $openedAt,
                ':notes'      => $notes !== '' ? $notes : null,
            ]);

            $this->redirect($base.'/banking/cash-registers');
        } catch (Throwable $e) {
            $this->oops('Cash register create failed', $e);
        }
    }

    /**
     * GET /banking/cash-registers/{id}/edit
     */
    public function edit(array $ctx = [], int $id = 0): void
    {
        try {
            $c       = $this->ctx($ctx);
            $pdo     = $this->pdo();
            $this->ensureTable($pdo);

            $orgId   = (int)($c['org']['id'] ?? $c['org_id'] ?? 0);
            $base    = (string)($c['module_base'] ?? '/apps/pos');

            $row = $this->row("
                SELECT register_id AS id, org_id, branch_id, name, code, status,
                       opening_float, closing_cash, opened_at, closed_at, notes
                  FROM pos_cash_registers
                 WHERE org_id = :o AND register_id = :r
            ", [':o'=>$orgId, ':r'=>$id]);

            if (!$row) {
                http_response_code(404);
                echo 'Cash register not found';
                return;
            }

            $this->view($c['module_dir'].'/Views/banking/cash-registers/edit.php', [
                'title' => 'Edit Cash Register',
                'base'  => $base,
                'reg'   => $row,
                'ctx'   => $c,
            ], 'shell');
        } catch (Throwable $e) {
            $this->oops('Cash register edit failed', $e);
        }
    }

    /**
     * POST /banking/cash-registers/{id}
     */
    public function update(array $ctx = [], int $id = 0): void
    {
        try {
            $this->postOnly();
            $c       = $this->ctx($ctx);
            $pdo     = $this->pdo();
            $this->ensureTable($pdo);

            $orgId   = (int)($c['org']['id'] ?? $c['org_id'] ?? 0);
            $base    = (string)($c['module_base'] ?? '/apps/pos');

            $name    = trim((string)($_POST['name']  ?? ''));
            $code    = trim((string)($_POST['code']  ?? ''));
            $status  = (string)($_POST['status'] ?? 'open');
            $notes   = trim((string)($_POST['notes'] ?? ''));

            if ($name === '') {
                throw new \RuntimeException('Register name is required');
            }
            if (!in_array($status, ['open','closed','inactive'], true)) {
                $status = 'open';
            }

            $upd = $pdo->prepare("
                UPDATE pos_cash_registers
                   SET name = :name,
                       code = :code,
                       status = :status,
                       notes = :notes,
                       updated_at = NOW()
                 WHERE org_id = :o AND register_id = :r
            ");
            $upd->execute([
                ':name'   => $name,
                ':code'   => $code !== '' ? $code : null,
                ':status' => $status,
                ':notes'  => $notes !== '' ? $notes : null,
                ':o'      => $orgId,
                ':r'      => $id,
            ]);

            $this->redirect($base.'/banking/cash-registers');
        } catch (Throwable $e) {
            $this->oops('Cash register update failed', $e);
        }
    }

    /**
     * POST /banking/cash-registers/{id}/close
     */
    public function close(array $ctx = [], int $id = 0): void
    {
        try {
            $this->postOnly();
            $c       = $this->ctx($ctx);
            $pdo     = $this->pdo();
            $this->ensureTable($pdo);

            $orgId   = (int)($c['org']['id'] ?? $c['org_id'] ?? 0);
            $base    = (string)($c['module_base'] ?? '/apps/pos');

            $pdo->prepare("
                UPDATE pos_cash_registers
                   SET status = 'closed',
                       closed_at = NOW(),
                       updated_at = NOW()
                 WHERE org_id = ? AND register_id = ?
            ")->execute([$orgId, $id]);

            $this->redirect($base.'/banking/cash-registers');
        } catch (Throwable $e) {
            $this->oops('Cash register close failed', $e);
        }
    }
}