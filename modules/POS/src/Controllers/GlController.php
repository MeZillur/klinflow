<?php
declare(strict_types=1);

namespace Modules\POS\Controllers;

use PDO;
use Throwable;

final class GlController extends BaseController
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
     * Ensure POS GL tables exist (POS only, no dms_ mix)
     * -------------------------------------------------------- */
    private function ensurePosGlTables(PDO $pdo): void
    {
        if (!$this->hasTable($pdo, 'pos_journals')) {
            $pdo->exec("
                CREATE TABLE pos_journals (
                  id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                  org_id      BIGINT UNSIGNED NOT NULL,
                  entry_no    VARCHAR(40)     NOT NULL,
                  memo        VARCHAR(255)    NULL,
                  entry_date  DATE            NOT NULL,
                  created_at  TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
                  updated_at  TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP
                                              ON UPDATE CURRENT_TIMESTAMP,
                  PRIMARY KEY (id),
                  KEY idx_org_date (org_id, entry_date)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            ");
        }

        if (!$this->hasTable($pdo, 'pos_journal_entries')) {
            $pdo->exec("
                CREATE TABLE pos_journal_entries (
                  id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                  org_id       BIGINT UNSIGNED NOT NULL,
                  journal_id   BIGINT UNSIGNED NOT NULL,
                  account_code VARCHAR(40)     NOT NULL,
                  account_name VARCHAR(160)    NULL,
                  dr           DECIMAL(18,2)   NOT NULL DEFAULT 0,
                  cr           DECIMAL(18,2)   NOT NULL DEFAULT 0,
                  memo         VARCHAR(255)    NULL,
                  created_at   TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
                  updated_at   TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP
                                               ON UPDATE CURRENT_TIMESTAMP,
                  PRIMARY KEY (id),
                  KEY idx_org_acc     (org_id, account_code),
                  KEY idx_org_journal (org_id, journal_id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            ");
        }
    }
  
  
      /* --------------------------------------------------------
     * POS Chart of Accounts table (pos_accounts)
     * -------------------------------------------------------- */
    private function ensurePosAccountsTable(PDO $pdo): void
    {
        // If table already exists, nothing to do
        if ($this->hasTable($pdo, 'pos_accounts')) {
            return;
        }

        // Create a very simple COA table, POS-only
        $pdo->exec("
            CREATE TABLE pos_accounts (
              id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
              org_id      BIGINT UNSIGNED NOT NULL,
              code        VARCHAR(40)     NOT NULL,
              name        VARCHAR(160)    NOT NULL,
              type        VARCHAR(16)     NOT NULL DEFAULT 'asset', 
              parent_code VARCHAR(40)     NULL,
              is_active   TINYINT(1)      NOT NULL DEFAULT 1,
              created_at  TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
              updated_at  TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP
                                            ON UPDATE CURRENT_TIMESTAMP,
              PRIMARY KEY (id),
              UNIQUE KEY uq_org_code (org_id, code),
              KEY idx_org_type (org_id, type)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ");
    }

    /* ============================================================
     * GET /gl/journals  → Journals index
     * ============================================================ */
    public function journals(array $ctx = []): void
    {
        try {
            [$c, $base, $orgId, $pdo] = $this->env($ctx);
            $this->ensurePosGlTables($pdo);

            $q      = trim((string)($_GET['q']    ?? ''));
            $from   = trim((string)($_GET['from'] ?? ''));
            $to     = trim((string)($_GET['to']   ?? ''));
            $page   = max(1, (int)($_GET['page'] ?? 1));
            $limit  = 25;
            $offset = ($page - 1) * $limit;

            /* ---------- COUNT (no GROUP BY issue) ---------- */
            $countSql = "
                SELECT COUNT(*)
                  FROM pos_journals j
                 WHERE j.org_id = :o
            ";
            $countBind = [':o' => $orgId];

            if ($q !== '') {
                $countSql .= " AND (j.entry_no LIKE :q OR j.memo LIKE :q)";
                $countBind[':q'] = '%'.$q.'%';
            }
            if ($from !== '') {
                $countSql .= " AND j.entry_date >= :from";
                $countBind[':from'] = $from;
            }
            if ($to !== '') {
                $countSql .= " AND j.entry_date <= :to";
                $countBind[':to'] = $to;
            }

            $total = (int)$this->val($countSql, $countBind);

            /* ---------- MAIN LIST WITH TOTALS ---------- */
            $sql = "
    SELECT 
        j.id AS journal_id,
        j.entry_no,
        j.memo,
        j.entry_date,
        COALESCE(SUM(e.dr),0) AS dr_total,
        COALESCE(SUM(e.cr),0) AS cr_total
    FROM pos_journals j
    LEFT JOIN pos_journal_entries e
      ON e.org_id    = j.org_id
     AND e.journal_id = j.id
    WHERE j.org_id = :o
";
            $bind = [':o' => $orgId];

            if ($q !== '') {
                $sql       .= " AND (j.entry_no LIKE :q OR j.memo LIKE :q)";
                $bind[':q'] = '%'.$q.'%';
            }
            if ($from !== '') {
                $sql          .= " AND j.entry_date >= :from";
                $bind[':from'] = $from;
            }
            if ($to !== '') {
                $sql        .= " AND j.entry_date <= :to";
                $bind[':to'] = $to;
            }

            // FULL GROUP BY safe: group by all non-aggregated columns
            $sql .= "
                GROUP BY
                    j.id,
                    j.entry_no,
                    j.memo,
                    j.entry_date
                ORDER BY j.entry_date DESC, j.id DESC
                LIMIT {$limit} OFFSET {$offset}
            ";

            $rows = $this->rows($sql, $bind);

            /* ---------- SUMMARY CARDS (org-wide totals) ---------- */
            $sumDr = (float)$this->val("
                SELECT COALESCE(SUM(dr),0)
                  FROM pos_journal_entries
                 WHERE org_id = :o
            ", [':o' => $orgId]);

            $sumCr = (float)$this->val("
                SELECT COALESCE(SUM(cr),0)
                  FROM pos_journal_entries
                 WHERE org_id = :o
            ", [':o' => $orgId]);

            $summary = [
                'dr' => $sumDr,
                'cr' => $sumCr,
            ];

            $money = function (float $amount): string {
                return number_format($amount, 2);
            };

            $pages = max(1, (int)ceil($total / $limit));

            $this->view(
                'gl/journals',
                [
                    'title'   => 'GL Journals',
                    'base'    => $base,
                    'rows'    => $rows,
                    'summary' => $summary,
                    'total'   => $total,
                    'page'    => $page,
                    'pages'   => $pages,
                    'q'       => $q,
                    'from'    => $from,
                    'to'      => $to,
                    'money'   => $money,
                ],
                'shell'
            );
        } catch (Throwable $e) {
            $this->oops('GL journals failed', $e);
        }
    }

    /* ============================================================
     * GET /gl/journals/{id} → Single journal
     * ============================================================ */
    public function journalShow(array $ctx = [], int $id = 0): void
    {
        try {
            [$c, $base, $orgId, $pdo] = $this->env($ctx);
            $this->ensurePosGlTables($pdo);

            $journal = $this->row("
                SELECT *
                  FROM pos_journals
                 WHERE org_id = :o
                   AND id     = :id
            ", [':o' => $orgId, ':id' => $id]);

            if (!$journal) {
                http_response_code(404);
                echo 'Journal not found';
                return;
            }

            $entries = $this->rows("
                SELECT *
                  FROM pos_journal_entries
                 WHERE org_id    = :o
                   AND journal_id = :j
                 ORDER BY id ASC
            ", [':o' => $orgId, ':j' => $id]);

            $totalDr = 0.0;
            $totalCr = 0.0;
            foreach ($entries as $line) {
                $totalDr += (float)($line['dr'] ?? 0);
                $totalCr += (float)($line['cr'] ?? 0);
            }

            $money = function (float $amount): string {
                return number_format($amount, 2);
            };

            $this->view(
                'gl/journal_show',
                [
                    'title'   => 'Journal Details',
                    'base'    => $base,
                    'journal' => $journal,
                    'entries' => $entries,
                    'totalDr' => $totalDr,
                    'totalCr' => $totalCr,
                    'money'   => $money,
                ],
                'shell'
            );
        } catch (Throwable $e) {
            $this->oops('GL journal detail failed', $e);
        }
    }

    /* ============================================================
     * GET /gl/ledger → Account-wise ledger (summary + optional detail)
     * ============================================================ */
    public function ledger(array $ctx = []): void
    {
        try {
            [$c, $base, $orgId, $pdo] = $this->env($ctx);
            $this->ensurePosGlTables($pdo);

            $from   = trim((string)($_GET['from']    ?? ''));
            $to     = trim((string)($_GET['to']      ?? ''));
            $code   = trim((string)($_GET['account'] ?? '')); // account_code filter
            $page   = max(1, (int)($_GET['page'] ?? 1));
            $limit  = 50;
            $offset = ($page - 1) * $limit;

            /* ---------- SUMMARY BY ACCOUNT ---------- */
            $sumSql = "
                FROM pos_journal_entries e
                INNER JOIN pos_journals j
                        ON j.id    = e.journal_id
                       AND j.org_id = e.org_id
               WHERE e.org_id = :o
            ";
            $bind = [':o' => $orgId];

            if ($from !== '') {
                $sumSql        .= " AND j.entry_date >= :from";
                $bind[':from']  = $from;
            }
            if ($to !== '') {
                $sumSql      .= " AND j.entry_date <= :to";
                $bind[':to']  = $to;
            }
            if ($code !== '') {
                $sumSql       .= " AND e.account_code LIKE :code";
                $bind[':code'] = $code.'%';
            }

            $countSql = "SELECT COUNT(DISTINCT e.account_code) ".$sumSql;
            $totalAccounts = (int)$this->val($countSql, $bind);

            $sql = "
                SELECT 
                    e.account_code,
                    MAX(e.account_name) AS account_name,
                    COALESCE(SUM(e.dr),0) AS dr_total,
                    COALESCE(SUM(e.cr),0) AS cr_total
                ".$sumSql."
                GROUP BY e.account_code
                ORDER BY e.account_code ASC
                LIMIT {$limit} OFFSET {$offset}
            ";
            $rows = $this->rows($sql, $bind);

            /* ---------- Optional detailed lines for one account ---------- */
            $entries     = [];
            $entryTotals = ['dr' => 0.0, 'cr' => 0.0];

            if ($code !== '') {
                $entrySql = "
                    SELECT 
                        j.entry_date,
                        j.entry_no,
                        j.memo AS journal_memo,
                        e.dr,
                        e.cr,
                        e.memo AS line_memo
                    FROM pos_journal_entries e
                    INNER JOIN pos_journals j
                            ON j.id     = e.journal_id
                           AND j.org_id = e.org_id
                   WHERE e.org_id = :o
                     AND e.account_code = :code
                ";
                $entryBind = [':o' => $orgId, ':code' => $code];

                if ($from !== '') {
                    $entrySql        .= " AND j.entry_date >= :from";
                    $entryBind[':from'] = $from;
                }
                if ($to !== '') {
                    $entrySql      .= " AND j.entry_date <= :to";
                    $entryBind[':to'] = $to;
                }

                $entrySql .= " ORDER BY j.entry_date ASC, j.id ASC, e.id ASC";

                $entries = $this->rows($entrySql, $entryBind);
                foreach ($entries as $line) {
                    $entryTotals['dr'] += (float)($line['dr'] ?? 0);
                    $entryTotals['cr'] += (float)($line['cr'] ?? 0);
                }
            }

            $money = function (float $amount): string {
                return number_format($amount, 2);
            };

            $pages = max(1, (int)ceil($totalAccounts / $limit));

            $this->view(
                'gl/ledger',
                [
                    'title'        => 'General Ledger',
                    'base'         => $base,
                    'rows'         => $rows,
                    'entries'      => $entries,
                    'entryTotals'  => $entryTotals,
                    'accountCode'  => $code,
                    'from'         => $from,
                    'to'           => $to,
                    'page'         => $page,
                    'pages'        => $pages,
                    'totalAccounts'=> $totalAccounts,
                    'money'        => $money,
                ],
                'shell'
            );
        } catch (Throwable $e) {
            $this->oops('GL ledger failed', $e);
        }
    }

  	    /* ============================================================
     * GET /gl/chart → Chart of Accounts
     * ============================================================ */
    public function chart(array $ctx = []): void
    {
        try {
            [$c, $base, $orgId, $pdo] = $this->env($ctx);

            // make sure table exists
            $this->ensurePosAccountsTable($pdo);

            $q    = trim((string)($_GET['q']    ?? ''));
            $type = trim((string)($_GET['type'] ?? ''));

            $sql  = "SELECT * FROM pos_accounts WHERE org_id = :o";
            $bind = [':o' => $orgId];

            if ($q !== '') {
                $sql .= " AND (code LIKE :q OR name LIKE :q)";
                $bind[':q'] = '%'.$q.'%';
            }
            if ($type !== '') {
                $sql .= " AND type = :t";
                $bind[':t'] = $type;
            }

            $sql .= " ORDER BY code ASC";

            $rows = $this->rows($sql, $bind);

            $types = [
                'asset'     => 'Asset',
                'liability' => 'Liability',
                'equity'    => 'Equity',
                'income'    => 'Income',
                'expense'   => 'Expense',
                'other'     => 'Other',
            ];

            $this->view(
                'gl/chart',
                [
                    'title'  => 'Chart of Accounts',
                    'base'   => $base,
                    'rows'   => $rows,
                    'q'      => $q,
                    'type'   => $type,
                    'types'  => $types,
                ],
                'shell'
            );
        } catch (Throwable $e) {
            $this->oops('GL chart of accounts failed', $e);
        }
    }
  
  
  		/* ============================================================
 * GET /gl/chart/create  → New account form
 * ============================================================ */
public function chartCreate(array $ctx = []): void
{
    try {
        [$c, $base, $orgId, $pdo] = $this->env($ctx);
        $this->ensurePosAccountsTable($pdo);

        $types = [
            'asset'     => 'Asset',
            'liability' => 'Liability',
            'equity'    => 'Equity',
            'income'    => 'Income',
            'expense'   => 'Expense',
            'other'     => 'Other',
        ];

        $account = [
            'id'          => 0,
            'code'        => '',
            'name'        => '',
            'type'        => 'asset',
            'parent_code' => '',
            'is_active'   => 1,
            'sort_order'  => null,
        ];

        $this->view(
            'gl/chart_form',
            [
                'title'   => 'Add Account',
                'base'    => $base,
                'types'   => $types,
                'account' => $account,
                'mode'    => 'create',
            ],
            'shell'
        );
    } catch (Throwable $e) {
        $this->oops('Chart of accounts create failed', $e);
    }
}

/* ============================================================
 * POST /gl/chart/create  → Save new account
 * ============================================================ */
public function chartStore(array $ctx = []): void
{
    try {
        $this->postOnly();

        [$c, $base, $orgId, $pdo] = $this->env($ctx);
        $this->ensurePosAccountsTable($pdo);

        $code        = trim((string)($_POST['code']        ?? ''));
        $name        = trim((string)($_POST['name']        ?? ''));
        $type        = strtolower(trim((string)($_POST['type'] ?? '')));
        $parentCode  = trim((string)($_POST['parent_code'] ?? ''));
        $isActive    = isset($_POST['is_active']) ? 1 : 0;
        $sortOrder   = trim((string)($_POST['sort_order']  ?? ''));

        if ($code === '' || $name === '') {
            throw new \RuntimeException('Code and name are required.');
        }

        $validTypes = ['asset','liability','equity','income','expense','other'];
        if (!in_array($type, $validTypes, true)) {
            $type = 'other';
        }

        $sql = "
            INSERT INTO pos_accounts
              (org_id, code, name, type, parent_code, is_active, sort_order, created_at, updated_at)
            VALUES
              (:o, :code, :name, :type, :parent, :active, :sort, NOW(), NOW())
        ";

        $this->exec($sql, [
            ':o'      => $orgId,
            ':code'   => $code,
            ':name'   => $name,
            ':type'   => $type,
            ':parent' => $parentCode !== '' ? $parentCode : null,
            ':active' => $isActive,
            ':sort'   => $sortOrder !== '' ? (int)$sortOrder : null,
        ]);

        $this->redirect($base.'/gl/chart');
    } catch (Throwable $e) {
        $this->oops('Chart of accounts store failed', $e);
    }
}

/* ============================================================
 * GET /gl/chart/{id}/edit  → Edit account form
 * ============================================================ */
public function chartEdit(array $ctx = [], int $id = 0): void
{
    try {
        [$c, $base, $orgId, $pdo] = $this->env($ctx);
        $this->ensurePosAccountsTable($pdo);

        $row = $this->row("
            SELECT *
              FROM pos_accounts
             WHERE org_id = :o
               AND id     = :id
        ", [':o' => $orgId, ':id' => $id]);

        if (!$row) {
            http_response_code(404);
            echo 'Account not found';
            return;
        }

        $types = [
            'asset'     => 'Asset',
            'liability' => 'Liability',
            'equity'    => 'Equity',
            'income'    => 'Income',
            'expense'   => 'Expense',
            'other'     => 'Other',
        ];

        $this->view(
            'gl/chart_form',
            [
                'title'   => 'Edit Account',
                'base'    => $base,
                'types'   => $types,
                'account' => $row,
                'mode'    => 'edit',
            ],
            'shell'
        );
    } catch (Throwable $e) {
        $this->oops('Chart of accounts edit failed', $e);
    }
}

/* ============================================================
 * POST /gl/chart/{id}/edit  → Update account
 * ============================================================ */
public function chartUpdate(array $ctx = [], int $id = 0): void
{
    try {
        $this->postOnly();

        [$c, $base, $orgId, $pdo] = $this->env($ctx);
        $this->ensurePosAccountsTable($pdo);

        // ensure it exists
        $exists = (int)$this->val("
            SELECT COUNT(*) FROM pos_accounts
             WHERE org_id = :o AND id = :id
        ", [':o' => $orgId, ':id' => $id]);

        if ($exists === 0) {
            http_response_code(404);
            echo 'Account not found';
            return;
        }

        $code        = trim((string)($_POST['code']        ?? ''));
        $name        = trim((string)($_POST['name']        ?? ''));
        $type        = strtolower(trim((string)($_POST['type'] ?? '')));
        $parentCode  = trim((string)($_POST['parent_code'] ?? ''));
        $isActive    = isset($_POST['is_active']) ? 1 : 0;
        $sortOrder   = trim((string)($_POST['sort_order']  ?? ''));

        if ($code === '' || $name === '') {
            throw new \RuntimeException('Code and name are required.');
        }

        $validTypes = ['asset','liability','equity','income','expense','other'];
        if (!in_array($type, $validTypes, true)) {
            $type = 'other';
        }

        $sql = "
            UPDATE pos_accounts
               SET code        = :code,
                   name        = :name,
                   type        = :type,
                   parent_code = :parent,
                   is_active   = :active,
                   sort_order  = :sort,
                   updated_at  = NOW()
             WHERE org_id = :o
               AND id     = :id
        ";

        $this->exec($sql, [
            ':code'   => $code,
            ':name'   => $name,
            ':type'   => $type,
            ':parent' => $parentCode !== '' ? $parentCode : null,
            ':active' => $isActive,
            ':sort'   => $sortOrder !== '' ? (int)$sortOrder : null,
            ':o'      => $orgId,
            ':id'     => $id,
        ]);

        $this->redirect($base.'/gl/chart');
    } catch (Throwable $e) {
        $this->oops('Chart of accounts update failed', $e);
    }
}
  
    /* ============================================================
     * GET /gl/trial-balance → Trial balance (per account)
     * ============================================================ */
    public function trialBalance(array $ctx = []): void
    {
        try {
            [$c, $base, $orgId, $pdo] = $this->env($ctx);
            $this->ensurePosGlTables($pdo);

            $from = trim((string)($_GET['from'] ?? ''));
            $to   = trim((string)($_GET['to']   ?? ''));

            $sql = "
                SELECT 
                    e.account_code,
                    MAX(e.account_name) AS account_name,
                    COALESCE(SUM(e.dr),0) AS dr_total,
                    COALESCE(SUM(e.cr),0) AS cr_total
                FROM pos_journal_entries e
                INNER JOIN pos_journals j
                        ON j.id     = e.journal_id
                       AND j.org_id = e.org_id
               WHERE e.org_id = :o
            ";
            $bind = [':o' => $orgId];

            if ($from !== '') {
                $sql         .= " AND j.entry_date >= :from";
                $bind[':from'] = $from;
            }
            if ($to !== '') {
                $sql       .= " AND j.entry_date <= :to";
                $bind[':to'] = $to;
            }

            $sql .= "
                GROUP BY e.account_code
                ORDER BY e.account_code ASC
            ";

            $rows = $this->rows($sql, $bind);

            $totals = [
                'dr' => 0.0,
                'cr' => 0.0,
            ];
            foreach ($rows as &$row) {
                $row['dr_total'] = (float)$row['dr_total'];
                $row['cr_total'] = (float)$row['cr_total'];
                $row['balance']  = $row['dr_total'] - $row['cr_total'];

                $totals['dr'] += $row['dr_total'];
                $totals['cr'] += $row['cr_total'];
            }
            unset($row);

            $money = function (float $amount): string {
                return number_format($amount, 2);
            };

            $this->view(
                'gl/trial-balance',
                [
                    'title'  => 'Trial Balance',
                    'base'   => $base,
                    'rows'   => $rows,
                    'from'   => $from,
                    'to'     => $to,
                    'totals' => $totals,
                    'money'  => $money,
                ],
                'shell'
            );
        } catch (Throwable $e) {
            $this->oops('Trial balance failed', $e);
        }
    }
}