<?php
declare(strict_types=1);

namespace Modules\POS\Controllers;

use PDO;
use Throwable;

/**
 * POS MoneyController
 *
 * Lightweight accounting / banking layer for POS.
 * - Records INCOME / EXPENSE rows per org + branch + bank.
 * - Reads bank accounts from DMS:dms_bank_accounts (no duplication).
 * - Safe to use now; GL integration can be wired later.
 *
 * Routes (suggested):
 *   GET  /money                 → index() (dashboard)
 *   GET  /money/income/create   → createIncome()
 *   GET  /money/expense/create  → createExpense()
 *   POST /money                 → store()
 */
final class MoneyController extends BaseController
{
    /* ===================== infra: tables ===================== */

    private function ensureTables(PDO $pdo): void
    {
        // 1) Categories: income / expense
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS pos_money_categories (
              id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
              org_id       BIGINT UNSIGNED NOT NULL,
              type         ENUM('income','expense') NOT NULL,
              name         VARCHAR(190) NOT NULL,
              code         VARCHAR(64)  NULL,
              is_active    TINYINT(1) NOT NULL DEFAULT 1,
              sort_order   INT NULL,
              created_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
              updated_at   TIMESTAMP NULL DEFAULT NULL,
              PRIMARY KEY (id),
              KEY idx_pos_money_categories_org_type (org_id,type),
              KEY idx_pos_money_categories_org_name (org_id,name)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");

        // 2) Entries: each money movement
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS pos_money_entries (
              id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
              org_id          BIGINT UNSIGNED NOT NULL,
              branch_id       BIGINT UNSIGNED NULL,
              bank_account_id BIGINT UNSIGNED NULL, -- dms_bank_accounts.id
              type            ENUM('income','expense') NOT NULL,
              category_id     BIGINT UNSIGNED NULL,
              entry_date      DATE NOT NULL,
              amount          DECIMAL(16,2) NOT NULL,
              ref_no          VARCHAR(100) NULL,
              description     VARCHAR(255) NULL,
              payment_method  VARCHAR(60) NULL,
              created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
              created_by      BIGINT UNSIGNED NULL,
              PRIMARY KEY (id),
              KEY idx_pos_money_entries_org_type_date (org_id,type,entry_date),
              KEY idx_pos_money_entries_org_bank (org_id,bank_account_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
    }

    /* ===================== dashboard ===================== */

    /** GET /money */
    public function index(array $ctx = []): void
    {
        try {
            $c   = $this->ctx($ctx);
            $pdo = $this->pdo();
            $this->ensureTables($pdo);

            $orgId   = (int)($c['org']['id'] ?? $c['org_id'] ?? 0);
            $branchId= (int)($c['branch_id'] ?? 0);
            $base    = $this->moduleBase($c);
            $brand   = '#228B22';

            $today = date('Y-m-d');
            $monthStart = date('Y-m-01');

            // Today / month totals per type
            $totSql = "
                SELECT type,
                       SUM(CASE WHEN entry_date = :today THEN amount ELSE 0 END) AS today_total,
                       SUM(CASE WHEN entry_date >= :month_start THEN amount ELSE 0 END) AS month_total
                FROM pos_money_entries
                WHERE org_id = :org
                  AND ( :branch = 0 OR branch_id = :branch )
                GROUP BY type
            ";
            $totals = ['income'=>['today'=>0,'month'=>0],'expense'=>['today'=>0,'month'=>0]];
            $st = $pdo->prepare($totSql);
            $st->execute([
                ':today'       => $today,
                ':month_start' => $monthStart,
                ':org'         => $orgId,
                ':branch'      => $branchId,
            ]);
            foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
                $t = $r['type'] ?? '';
                if (!isset($totals[$t])) continue;
                $totals[$t]['today'] = (float)$r['today_total'];
                $totals[$t]['month'] = (float)$r['month_total'];
            }

            // Recent entries (last 10)
            $qRecent = $pdo->prepare("
                SELECT e.*, c.name AS category_name, b.bank_name, b.account_name
                FROM pos_money_entries e
                LEFT JOIN pos_money_categories c
                  ON c.id = e.category_id
                LEFT JOIN dms_bank_accounts b
                  ON b.id = e.bank_account_id
                 AND b.org_id = e.org_id
                WHERE e.org_id = :org
                  AND ( :branch = 0 OR e.branch_id = :branch )
                ORDER BY e.entry_date DESC, e.id DESC
                LIMIT 10
            ");
            $qRecent->execute([':org'=>$orgId, ':branch'=>$branchId]);
            $recent = $qRecent->fetchAll(PDO::FETCH_ASSOC) ?: [];

            // Bank summary (current balance + this-month movement)
            $bankSql = "
                SELECT
                  b.id,
                  b.bank_name,
                  b.account_name,
                  b.account_no,
                  b.current_balance,
                  COALESCE(SUM(
                    CASE WHEN e.type='income' THEN e.amount
                         WHEN e.type='expense' THEN -e.amount
                         ELSE 0 END
                  ),0) AS movement_month
                FROM dms_bank_accounts b
                LEFT JOIN pos_money_entries e
                  ON e.bank_account_id = b.id
                 AND e.org_id = b.org_id
                 AND e.entry_date >= :month_start
                WHERE b.org_id = :org
                GROUP BY b.id, b.bank_name, b.account_name, b.account_no, b.current_balance
                ORDER BY b.is_master DESC, b.bank_name, b.account_name
            ";
            $bs = $pdo->prepare($bankSql);
            $bs->execute([':month_start'=>$monthStart, ':org'=>$orgId]);
            $banks = $bs->fetchAll(PDO::FETCH_ASSOC) ?: [];

            $this->view($c['module_dir'].'/Views/money/index.php', [
                'title'   => 'Money & Banking',
                'base'    => $base,
                'brand'   => $brand,
                'totals'  => $totals,
                'recent'  => $recent,
                'banks'   => $banks,
                'ctx'     => $c,
            ], 'shell');
        } catch (Throwable $e) {
            $this->oops('Money dashboard failed', $e);
        }
    }

    /* ===================== create forms ===================== */

    /** GET /money/income/create */
    public function createIncome(array $ctx = []): void
    {
        $this->createForm($ctx, 'income');
    }

    /** GET /money/expense/create */
    public function createExpense(array $ctx = []): void
    {
        $this->createForm($ctx, 'expense');
    }

    private function createForm(array $ctx, string $type): void
    {
        try {
            $c   = $this->ctx($ctx);
            $pdo = $this->pdo();
            $this->ensureTables($pdo);

            $orgId = (int)($c['org']['id'] ?? $c['org_id'] ?? 0);
            $base  = $this->moduleBase($c);

            // Categories for that type
            $cat = $pdo->prepare("
                SELECT id, name
                FROM pos_money_categories
                WHERE org_id = ? AND type = ? AND is_active = 1
                ORDER BY sort_order IS NULL, sort_order, name
            ");
            $cat->execute([$orgId, $type]);
            $categories = $cat->fetchAll(PDO::FETCH_ASSOC) ?: [];

            // Bank accounts from DMS
            $bq = $pdo->prepare("
                SELECT id, bank_name, account_name, account_no, is_master
                FROM dms_bank_accounts
                WHERE org_id = ?
                ORDER BY is_master DESC, bank_name, account_name
            ");
            $bq->execute([$orgId]);
            $banks = $bq->fetchAll(PDO::FETCH_ASSOC) ?: [];

            $this->view($c['module_dir'].'/Views/money/form.php', [
                'title'      => $type === 'income' ? 'Record Income' : 'Record Expense',
                'base'       => $base,
                'type'       => $type,
                'categories' => $categories,
                'banks'      => $banks,
                'today'      => date('Y-m-d'),
                'ctx'        => $c,
            ], 'shell');
        } catch (Throwable $e) {
            $this->oops('Money form failed', $e);
        }
    }

    /* ===================== store ===================== */

    /** POST /money (handles both income + expense) */
    public function store(array $ctx = []): void
    {
        try {
            $this->postOnly();
            $c   = $this->ctx($ctx);
            $pdo = $this->pdo();
            $this->ensureTables($pdo);

            $orgId    = (int)($c['org']['id'] ?? $c['org_id'] ?? 0);
            $branchId = (int)($c['branch_id'] ?? 0);
            $base     = $this->moduleBase($c);

            $type   = $_POST['type'] === 'expense' ? 'expense' : 'income';
            $date   = trim((string)($_POST['entry_date'] ?? date('Y-m-d')));
            $amount = (float)str_replace([','], [''], (string)($_POST['amount'] ?? '0'));
            $bankId = (int)($_POST['bank_account_id'] ?? 0) ?: null;
            $catId  = (int)($_POST['category_id'] ?? 0) ?: null;
            $ref    = trim((string)($_POST['ref_no'] ?? ''));
            $desc   = trim((string)($_POST['description'] ?? ''));
            $paym   = trim((string)($_POST['payment_method'] ?? ''));

            $errors = [];
            if (!$date)   $errors[] = 'Date is required.';
            if ($amount <= 0) $errors[] = 'Amount must be positive.';

            if ($errors) {
                $_SESSION['flash_errors'] = $errors;
                $_SESSION['form_old']     = $_POST;
                $this->redirect($base.'/money/'.($type === 'income' ? 'income' : 'expense').'/create');
                return;
            }

            $ins = $pdo->prepare("
                INSERT INTO pos_money_entries
                  (org_id, branch_id, bank_account_id, type, category_id,
                   entry_date, amount, ref_no, description, payment_method, created_at)
                VALUES
                  (:org, :branch, :bank, :type, :cat, :date, :amt, :ref, :desc, :pm, NOW())
            ");
            $ins->execute([
                ':org'    => $orgId,
                ':branch' => $branchId ?: null,
                ':bank'   => $bankId,
                ':type'   => $type,
                ':cat'    => $catId,
                ':date'   => $date,
                ':amt'    => round($amount, 2),
                ':ref'    => $ref !== '' ? $ref : null,
                ':desc'   => $desc !== '' ? $desc : null,
                ':pm'     => $paym !== '' ? $paym : null,
            ]);

            // TODO later: call DMS/GL to post journal entry based on mapping

            $_SESSION['flash_success'] = 'Entry recorded successfully.';
            $this->redirect($base.'/money');
        } catch (Throwable $e) {
            $this->oops('Money store failed', $e);
        }
    }
}