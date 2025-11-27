<?php
declare(strict_types=1);

namespace Modules\POS\Controllers;

use Throwable;
use PDO;

final class AccountingController extends BaseController
{
    /* --------------------------- Table checks --------------------------- */
    private function ensureTables(PDO $pdo): void
    {
        // Chart of accounts
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS pos_accounts (
                id INT AUTO_INCREMENT PRIMARY KEY,
                org_id INT NOT NULL,
                code VARCHAR(32) NOT NULL,
                name VARCHAR(255) NOT NULL,
                type ENUM('asset','liability','equity','income','expense') DEFAULT 'asset',
                parent_id INT NULL,
                is_active TINYINT(1) DEFAULT 1,
                created_at TIMESTAMP NULL,
                updated_at TIMESTAMP NULL,
                INDEX (org_id), INDEX (code)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ");

        // Ledger entries
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS pos_ledger (
                id INT AUTO_INCREMENT PRIMARY KEY,
                org_id INT NOT NULL,
                account_id INT NOT NULL,
                ref_type VARCHAR(64) NULL,
                ref_id INT NULL,
                debit DECIMAL(14,2) DEFAULT 0,
                credit DECIMAL(14,2) DEFAULT 0,
                memo VARCHAR(255) NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX (org_id), INDEX (account_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ");
    }

    private function ctxOrg(array $ctx): array
    {
        $c   = $this->ctx($ctx);
        $org = $c['org'] ?? ($_SESSION['tenant_org'] ?? []);
        if (!isset($c['org']) && $org) $c['org'] = $org;
        $c['org_id'] = (int)($c['org']['id'] ?? $c['org_id'] ?? 0);
        $c['module_base'] ??= '/apps/pos';
        $c['module_dir']  ??= dirname(__DIR__, 2);
        return $c;
    }

    /* --------------------------- 1. Accounting overview --------------------------- */
    // GET /accounting
    public function index(array $ctx = []): void
    {
        try {
            $c   = $this->ctxOrg($ctx);
            $pdo = $this->pdo();
            $this->ensureTables($pdo);

            $org = (int)$c['org_id'];
            $totals = $this->row("
                SELECT 
                    SUM(CASE WHEN type='asset' THEN debit-credit ELSE 0 END) AS assets,
                    SUM(CASE WHEN type='liability' THEN credit-debit ELSE 0 END) AS liabilities,
                    SUM(CASE WHEN type='income' THEN credit-debit ELSE 0 END) AS income,
                    SUM(CASE WHEN type='expense' THEN debit-credit ELSE 0 END) AS expense
                FROM pos_ledger l
                JOIN pos_accounts a ON a.id=l.account_id AND a.org_id=l.org_id
                WHERE l.org_id=:o
            ", [':o'=>$org]) ?? [];

            $this->view($c['module_dir'].'/Views/accounting/index.php', [
                'title' => 'Accounting Overview',
                'ctx'   => $c,
                'totals'=> $totals,
            ], 'shell');
        } catch (Throwable $e) {
            $this->oops('Accounting overview failed', $e);
        }
    }

    /* --------------------------- 2. Chart of Accounts --------------------------- */
    // GET /accounts
    public function chart(array $ctx = []): void
    {
        try {
            $c   = $this->ctxOrg($ctx);
            $pdo = $this->pdo();
            $this->ensureTables($pdo);

            $rows = $this->rows("SELECT * FROM pos_accounts WHERE org_id=:o ORDER BY type,code", [':o'=>$c['org_id']]);
            $this->view($c['module_dir'].'/Views/accounting/chart.php', [
                'title' => 'Chart of Accounts',
                'ctx'   => $c,
                'accounts'=>$rows,
            ], 'shell');
        } catch (Throwable $e) {
            $this->oops('Accounts chart failed', $e);
        }
    }

    /* --------------------------- 3. Ledger --------------------------- */
    // GET /accounts/ledger
    public function ledger(array $ctx = []): void
    {
        try {
            $c = $this->ctxOrg($ctx);
            $pdo = $this->pdo();
            $this->ensureTables($pdo);

            $aid  = (int)($_GET['account_id'] ?? 0);
            $org  = (int)$c['org_id'];

            $accounts = $this->rows("SELECT id,code,name FROM pos_accounts WHERE org_id=:o ORDER BY code", [':o'=>$org]);
            $entries  = $aid > 0
                ? $this->rows("SELECT * FROM pos_ledger WHERE org_id=:o AND account_id=:a ORDER BY created_at DESC LIMIT 200", [':o'=>$org, ':a'=>$aid])
                : [];

            $this->view($c['module_dir'].'/Views/accounting/ledger.php', [
                'title'=>'Ledger',
                'ctx'=>$c,
                'accounts'=>$accounts,
                'entries'=>$entries,
                'selected'=>$aid,
            ], 'shell');
        } catch (Throwable $e) {
            $this->oops('Ledger view failed', $e);
        }
    }
}