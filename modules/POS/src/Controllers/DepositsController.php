<?php
declare(strict_types=1);

namespace Modules\POS\Controllers;

use PDO;
use Throwable;

/**
 * DepositsController
 *
 * Branch cash -> HQ bank deposits.
 */
final class DepositsController extends BaseController
{
    /* ============================================================
     * Small helper: environment + ensure table
     * ============================================================ */
    private function env(array $ctx = []): array
    {
        $c     = $this->ctx($ctx);
        $base  = (string)($c['module_base'] ?? '/apps/pos');
        $orgId = (int)($c['org']['id'] ?? $c['org_id'] ?? 0);
        $pdo   = $this->pdo();

        $this->ensureDepositsTable($pdo);

        return [$c, $base, $orgId, $pdo];
    }

    /**
     * Create pos_deposits if missing.
     * (Does not alter an existing table.)
     */
    private function ensureDepositsTable(PDO $pdo): void
    {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS pos_deposits (
              deposit_id       BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
              org_id           BIGINT UNSIGNED NOT NULL,
              branch_id        BIGINT UNSIGNED NULL,
              cash_register_id BIGINT UNSIGNED NULL,
              bank_account_id  BIGINT UNSIGNED NOT NULL,
              amount           DECIMAL(18,2)  NOT NULL DEFAULT 0,
              currency         CHAR(3)        NOT NULL DEFAULT 'BDT',
              method           VARCHAR(32)    NOT NULL DEFAULT 'Cash',
              reference        VARCHAR(64)    NULL,
              deposited_at     DATE           NOT NULL,
              notes            TEXT           NULL,
              status           VARCHAR(24)    NOT NULL DEFAULT 'posted',
              created_at       TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP,
              updated_at       TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP
                                             ON UPDATE CURRENT_TIMESTAMP,
              PRIMARY KEY (deposit_id),
              KEY idx_pos_dep_org_date   (org_id, deposited_at),
              KEY idx_pos_dep_org_status (org_id, status),
              KEY idx_pos_dep_org_bank   (org_id, bank_account_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
    }

    /* ============================================================
     * GET /banking/deposits
     * ============================================================ */
    public function index(array $ctx = []): void
    {
        try {
            [$c, $base, $orgId, $pdo] = $this->env($ctx);
            $branchId = (int)($c['branch_id'] ?? 0);

            $q   = trim((string)($_GET['q'] ?? ''));
            $tbl = 'pos_deposits';

            // Support both "deposited_at" and legacy "deposit_date"
            $dateCol = null;
            if ($this->hasCol($pdo, $tbl, 'deposited_at')) {
                $dateCol = 'deposited_at';
            } elseif ($this->hasCol($pdo, $tbl, 'deposit_date')) {
                $dateCol = 'deposit_date';
            }

            $cols = [
                'd.deposit_id AS id',
                'd.amount',
                'd.status',
                'd.reference',
            ];
            if ($dateCol) {
                $cols[] = "d.{$dateCol} AS deposited_at";
            }

            $joins = [];

            // Branch join (optional)
            if ($this->hasTable($pdo, 'pos_branches')) {
                $branchPk = $this->hasCol($pdo, 'pos_branches', 'id') ? 'id' : null;
                if ($branchPk) {
                    $joins[] = "LEFT JOIN pos_branches br
                                   ON br.{$branchPk} = d.branch_id";
                    if ($this->hasCol($pdo, 'pos_branches', 'name')) {
                        $cols[] = 'br.name AS branch_name';
                    }
                }
            }

            // Cash registers (optional)
            if ($this->hasTable($pdo, 'pos_cash_registers')) {
                $regPk = null;
                if ($this->hasCol($pdo, 'pos_cash_registers', 'register_id')) {
                    $regPk = 'register_id';
                } elseif ($this->hasCol($pdo, 'pos_cash_registers', 'id')) {
                    $regPk = 'id';
                }

                if ($regPk) {
                    $joins[] = "LEFT JOIN pos_cash_registers cr
                                   ON cr.{$regPk} = d.cash_register_id";
                    if ($this->hasCol($pdo, 'pos_cash_registers', 'name')) {
                        $cols[] = 'cr.name AS register_name';
                    }
                }
            }

            // Bank accounts (optional)
            if ($this->hasTable($pdo, 'pos_bank_accounts')) {
                $bankPk = null;
                if ($this->hasCol($pdo, 'pos_bank_accounts', 'bank_account_id')) {
                    $bankPk = 'bank_account_id';
                } elseif ($this->hasCol($pdo, 'pos_bank_accounts', 'id')) {
                    $bankPk = 'id';
                }

                if ($bankPk) {
                    $joins[] = "LEFT JOIN pos_bank_accounts ba
                                   ON ba.{$bankPk} = d.bank_account_id";
                    if ($this->hasCol($pdo, 'pos_bank_accounts', 'name')) {
                        $cols[] = 'ba.name AS bank_account_name';
                    } elseif ($this->hasCol($pdo, 'pos_bank_accounts', 'bank_name')) {
                        $cols[] = 'ba.bank_name AS bank_account_name';
                    }
                }
            }

            $select = implode(', ', $cols);
            $sql    = "SELECT {$select}
                         FROM {$tbl} d
                        " . implode("\n                        ", $joins) . "
                        WHERE d.org_id = :o";

            $bind = [':o' => $orgId];

            // Branch restriction for non-HQ users
            if ($branchId > 0 && $this->hasCol($pdo, $tbl, 'branch_id')) {
                $sql        .= " AND d.branch_id = :b";
                $bind[':b'] = $branchId;
            }

            // Search
            if ($q !== '') {
                $like = ['d.reference LIKE :q'];

                if ($this->hasTable($pdo, 'pos_branches')
                    && $this->hasCol($pdo, 'pos_branches', 'name')) {
                    $like[] = 'br.name LIKE :q';
                }
                if ($this->hasTable($pdo, 'pos_bank_accounts')) {
                    if ($this->hasCol($pdo, 'pos_bank_accounts', 'name')) {
                        $like[] = 'ba.name LIKE :q';
                    } elseif ($this->hasCol($pdo, 'pos_bank_accounts', 'bank_name')) {
                        $like[] = 'ba.bank_name LIKE :q';
                    }
                }

                $sql          .= ' AND (' . implode(' OR ', $like) . ')';
                $bind[':q']    = '%'.$q.'%';
            }

            // Order newest first
            if ($dateCol) {
                $sql .= " ORDER BY d.{$dateCol} DESC, d.deposit_id DESC";
            } else {
                $sql .= " ORDER BY d.deposit_id DESC";
            }

            $rows = $this->rows($sql, $bind);

            // IMPORTANT: use relative view key, let BaseController resolve full path
            $this->view(
                'banking/deposits/index',
                [
                    'title'  => 'Deposits',
                    'base'   => $base,
                    'rows'   => $rows,
                    'search' => $q,
                    'ctx'    => $c,
                ],
                'shell'
            );
        } catch (Throwable $e) {
            $this->oops('Deposits index failed', $e);
        }
    }

    /* ============================================================
     * GET /banking/deposits/create
     * ============================================================ */
    public function create(array $ctx = []): void
{
    try {
        // Resolve context and PDO
        [$c, $base, $orgId, $pdo] = $this->env($ctx);
        $branchId = (int)($c['branch_id'] ?? 0);

        /* -------------------------------------------------
         * 1) HQ bank accounts (safe, column-aware)
         * ------------------------------------------------- */
        $bankAccounts = [];

        if ($this->hasTable($pdo, 'pos_bank_accounts')) {
            // Detect primary key to expose as "id"
            $bankPk = $this->hasCol($pdo, 'pos_bank_accounts', 'bank_account_id')
                ? 'bank_account_id'
                : ($this->hasCol($pdo, 'pos_bank_accounts', 'id') ? 'id' : null);

            if ($bankPk !== null) {
                $cols = ["ba.{$bankPk} AS id"];

                if ($this->hasCol($pdo, 'pos_bank_accounts', 'name')) {
                    $cols[] = 'ba.name';
                }
                if ($this->hasCol($pdo, 'pos_bank_accounts', 'bank_name')) {
                    $cols[] = 'ba.bank_name';
                }
                if ($this->hasCol($pdo, 'pos_bank_accounts', 'account_no')) {
                    $cols[] = 'ba.account_no';
                }
                if ($this->hasCol($pdo, 'pos_bank_accounts', 'code')) {
                    $cols[] = 'ba.code';
                }
                if ($this->hasCol($pdo, 'pos_bank_accounts', 'is_master')) {
                    $cols[] = 'ba.is_master';
                }

                // Fallback if somehow nothing was added
                if (empty($cols)) {
                    $cols[] = "ba.{$bankPk} AS id";
                }

                $sql = "SELECT " . implode(', ', $cols) . "
                          FROM pos_bank_accounts ba
                         WHERE ba.org_id = :o";

                // Optional active flag
                if ($this->hasCol($pdo, 'pos_bank_accounts', 'is_active')) {
                    $sql .= " AND ba.is_active = 1";
                }

                // Try to sort master first, then by name if available
                $orderParts = [];
                if ($this->hasCol($pdo, 'pos_bank_accounts', 'is_master')) {
                    $orderParts[] = 'ba.is_master DESC';
                }
                if ($this->hasCol($pdo, 'pos_bank_accounts', 'name')) {
                    $orderParts[] = 'ba.name ASC';
                } elseif ($this->hasCol($pdo, 'pos_bank_accounts', 'bank_name')) {
                    $orderParts[] = 'ba.bank_name ASC';
                }

                if ($orderParts) {
                    $sql .= ' ORDER BY ' . implode(', ', $orderParts);
                }

                $bankAccounts = $this->rows($sql, [':o' => $orgId]);
            }
        }

        /* -------------------------------------------------
         * 2) Open cash registers for this branch
         * ------------------------------------------------- */
        $cashRegisters = [];

        if ($this->hasTable($pdo, 'pos_cash_registers')) {
            $regTbl = 'pos_cash_registers';

            // Detect PK
            $regPk = null;
            if ($this->hasCol($pdo, $regTbl, 'register_id')) {
                $regPk = 'register_id';
            } elseif ($this->hasCol($pdo, $regTbl, 'cash_register_id')) {
                $regPk = 'cash_register_id';
            } elseif ($this->hasCol($pdo, $regTbl, 'id')) {
                $regPk = 'id';
            }

            if ($regPk !== null) {
                $cols = ["r.{$regPk} AS id"];

                if ($this->hasCol($pdo, $regTbl, 'name')) {
                    $cols[] = 'r.name';
                }
                if ($this->hasCol($pdo, $regTbl, 'status')) {
                    $cols[] = 'r.status';
                }

                $sql = "SELECT " . implode(', ', $cols) . "
                          FROM {$regTbl} r
                         WHERE r.org_id = :o";

                $bind = [':o' => $orgId];

                // Branch filter if branch_id exists
                if ($branchId > 0 && $this->hasCol($pdo, $regTbl, 'branch_id')) {
                    $sql        .= " AND r.branch_id = :b";
                    $bind[':b'] = $branchId;
                }

                // Only open registers if status column exists
                if ($this->hasCol($pdo, $regTbl, 'status')) {
                    $sql .= " AND r.status = 'open'";
                }

                if ($this->hasCol($pdo, $regTbl, 'name')) {
                    $sql .= " ORDER BY r.name ASC";
                }

                $cashRegisters = $this->rows($sql, $bind);
            }
        }

        /* -------------------------------------------------
         * 3) Render view
         * ------------------------------------------------- */
        $this->view(
            'banking/deposits/create',
            [
                'title'         => 'New Deposit',
                'base'          => $base,
                'bankAccounts'  => $bankAccounts,
                'cashRegisters' => $cashRegisters,
                'today'         => date('Y-m-d'),
                'ctx'           => $c,
            ],
            'shell'
        );
    } catch (Throwable $e) {
        $this->oops('Deposit create form failed', $e);
    }
}

    /* ============================================================
     * POST /banking/deposits
     * ============================================================ */
    public function store(array $ctx = []): void
    {
        try {
            $this->postOnly();
            [$c, $base, $orgId, $pdo] = $this->env($ctx);
            $branchId = (int)($c['branch_id'] ?? 0);

            $bankId  = (int)($_POST['bank_account_id'] ?? 0);
            $regId   = (int)($_POST['cash_register_id'] ?? 0);
            $amount  = (float)str_replace([','], [''], (string)($_POST['amount'] ?? '0'));
            $method  = trim((string)($_POST['method'] ?? 'Cash'));
            $ref     = trim((string)($_POST['reference'] ?? ''));
            $dateStr = trim((string)($_POST['deposited_at'] ?? date('Y-m-d')));
            $notes   = trim((string)($_POST['notes'] ?? ''));

            if ($bankId <= 0) {
                throw new \RuntimeException('Bank account is required');
            }
            if ($amount <= 0) {
                throw new \RuntimeException('Amount must be greater than zero');
            }
            if ($dateStr === '') {
                $dateStr = date('Y-m-d');
            }

            $ins = $pdo->prepare("
                INSERT INTO pos_deposits
                  (org_id, branch_id, cash_register_id, bank_account_id,
                   amount, currency, method, reference, deposited_at, notes,
                   status, created_at, updated_at)
                VALUES
                  (:o, :b, :r, :bank,
                   :amt, 'BDT', :method, :ref, :d, :notes,
                   'posted', NOW(), NOW())
            ");

            $ins->execute([
                ':o'      => $orgId,
                ':b'      => $branchId ?: null,
                ':r'      => $regId ?: null,
                ':bank'   => $bankId,
                ':amt'    => round($amount, 2),
                ':method' => $method !== '' ? $method : 'Cash',
                ':ref'    => $ref !== '' ? $ref : null,
                ':d'      => $dateStr,
                ':notes'  => $notes !== '' ? $notes : null,
            ]);

            $this->redirect($base.'/banking/deposits');
        } catch (Throwable $e) {
            $this->oops('Deposit create failed', $e);
        }
    }

    /* ============================================================
     * GET /banking/deposits/{id}
     * ============================================================ */
    public function show(array $ctx = [], int $id = 0): void
    {
        try {
            [$c, $base, $orgId, $pdo] = $this->env($ctx);

            $sql = "
                SELECT
                  d.deposit_id AS id,
                  d.org_id,
                  d.branch_id,
                  d.cash_register_id,
                  d.bank_account_id,
                  d.amount,
                  d.currency,
                  d.method,
                  d.reference,
                  d.deposited_at,
                  d.notes,
                  d.status,
                  d.created_at,
                  d.updated_at,
                  ba.name      AS bank_account_name,
                  ba.bank_name AS bank_name,
                  cr.name      AS register_name,
                  br.name      AS branch_name
                FROM pos_deposits d
                LEFT JOIN pos_bank_accounts ba
                       ON ba.org_id = d.org_id
                      AND (
                            (ba.bank_account_id = d.bank_account_id)
                         OR (ba.id = d.bank_account_id)
                          )
                LEFT JOIN pos_cash_registers cr
                       ON cr.org_id = d.org_id
                      AND (
                            (cr.register_id = d.cash_register_id)
                         OR (cr.id = d.cash_register_id)
                          )
                LEFT JOIN pos_branches br
                       ON br.org_id = d.org_id
                      AND br.id = d.branch_id
                WHERE d.org_id = :o AND d.deposit_id = :id
                LIMIT 1
            ";

            $row = $this->row($sql, [':o' => $orgId, ':id' => $id]);

            if (!$row) {
                http_response_code(404);
                echo 'Deposit not found';
                return;
            }

            $this->view(
                'banking/deposits/show',
                [
                    'title'   => 'Deposit Details',
                    'base'    => $base,
                    'deposit' => $row,
                    'ctx'     => $c,
                ],
                'shell'
            );
        } catch (Throwable $e) {
            $this->oops('Deposit detail failed', $e);
        }
    }
}