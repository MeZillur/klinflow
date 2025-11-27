<?php
declare(strict_types=1);

namespace Modules\POS\Controllers;

final class BankingController extends BaseController
{
    public function index(array $ctx = []): void { $this->accounts($ctx); }

    /** GET /banking/accounts */
    public function accounts(array $ctx = []): void
    {
        try {
            $c=$this->ctx($ctx); $org=$this->orgId($c);
            $rows = $this->hasTable('pos_bank_accounts')
                ? $this->rows("SELECT bank_account_id AS id, code, name, type, currency, current_balance_cents FROM pos_bank_accounts WHERE org_id=:o ORDER BY name", [':o'=>$org])
                : [];
            $this->view($c['module_dir'].'/Views/banking/accounts.php', [
                'title'=>'Bank Accounts','base'=>$c['module_base'],'rows'=>$rows
            ], 'shell');
        } catch (\Throwable $e) { $this->oops('Bank accounts failed', $e); }
    }

    /** GET /banking/reconcile */
    public function reconcile(array $ctx = []): void
    {
        try {
            $c=$this->ctx($ctx);
            $this->view($c['module_dir'].'/Views/banking/reconcile.php', [
                'title'=>'Bank Reconciliation','base'=>$c['module_base']
            ], 'shell');
        } catch (\Throwable $e) { $this->oops('Bank reconcile failed', $e); }
    }

    /** POST /banking/reconcile */
    public function reconcilePost(array $ctx = []): void
    {
        try { /* accept file / statement lines later */ $c=$this->ctx($ctx); $this->redirect($c['module_base'].'/banking/reconcile'); }
        catch (\Throwable $e) { $this->oops('Reconcile submit failed', $e); }
    }
}