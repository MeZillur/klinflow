<?php
declare(strict_types=1);

namespace Modules\DMS\Controllers\Accounting;

use Modules\DMS\Controllers\BaseController;
use PDO;

final class AccountsController extends BaseController
{
    /* List */
    public function index(array $ctx): void
    {
        $pdo = $this->pdo(); $org = $this->orgId($ctx);

        $q   = trim((string)($_GET['q'] ?? ''));
        $typ = (string)($_GET['type'] ?? '');
        $sql = "SELECT * FROM dms_accounts WHERE org_id=? ";
        $args= [$org];
        if ($q !== '')   { $sql .= "AND (code LIKE ? OR name LIKE ?) "; $args[]="%$q%"; $args[]="%$q%"; }
        if ($typ !== '' && in_array($typ, ['asset','liability','equity','income','expense'], true)) {
            $sql .= "AND type=? "; $args[]=$typ;
        }
        $sql .= "ORDER BY type, code";
        $st = $pdo->prepare($sql); $st->execute($args);
        $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $this->view('accounting/accounts/index', [
            'title' => 'Chart of Accounts',
            'rows'  => $rows,
            'q'     => $q,
            'typ'   => $typ,
            'active'    => 'accounting',
            'subactive' => 'accounting.accounts',
        ], $ctx);
    }

    /* Create form */
    public function create(array $ctx): void
    {
        $pdo = $this->pdo(); $org = $this->orgId($ctx);
        $parents = $pdo->prepare("SELECT id, code, name FROM dms_accounts WHERE org_id=? AND is_active=1 ORDER BY code");
        $parents->execute([$org]);

        $this->view('accounting/accounts/form', [
            'title'   => 'Create Account',
            'parents' => $parents->fetchAll(PDO::FETCH_ASSOC) ?: [],
            'active'    => 'accounting',
            'subactive' => 'accounting.accounts',
        ], $ctx);
    }

    /* Store */
    public function store(array $ctx): void
    {
        $pdo = $this->pdo(); $org = $this->orgId($ctx);

        $code = trim((string)($_POST['code'] ?? ''));
        $name = trim((string)($_POST['name'] ?? ''));
        $type = (string)($_POST['type'] ?? 'asset');
        $parent_id = (int)($_POST['parent_id'] ?? 0) ?: null;

        if ($code === '' || $name === '' || !in_array($type, ['asset','liability','equity','income','expense'], true)) {
            $this->renderShell('Invalid', '<div class="p-4 text-rose-700">Code, name and valid type are required.</div>');
        }

        $ins = $pdo->prepare("INSERT INTO dms_accounts (org_id,code,name,type,parent_id,is_active,created_at,updated_at) VALUES (?,?,?,?,?,1,NOW(),NOW())");
        $ins->execute([$org, $code, $name, $type, $parent_id]);

        $this->redirect($this->moduleBase($ctx).'/accounting/accounts');
    }

    /* Show/Edit (single form) */
    public function show(array $ctx, int $id): void { $this->edit($ctx, $id); }

    public function edit(array $ctx, int $id): void
    {
        $pdo = $this->pdo(); $org = $this->orgId($ctx);
        $st = $pdo->prepare("SELECT * FROM dms_accounts WHERE org_id=? AND id=? LIMIT 1");
        $st->execute([$org, $id]);
        $acc = $st->fetch(PDO::FETCH_ASSOC);
        if (!$acc) $this->abort404('Account not found.');

        $parents = $pdo->prepare("SELECT id, code, name FROM dms_accounts WHERE org_id=? AND is_active=1 AND id<>? ORDER BY code");
        $parents->execute([$org, $id]);

        $this->view('accounting/accounts/form', [
            'title'   => 'Edit Account',
            'account' => $acc,
            'parents' => $parents->fetchAll(PDO::FETCH_ASSOC) ?: [],
            'active'    => 'accounting',
            'subactive' => 'accounting.accounts',
        ], $ctx);
    }

    public function update(array $ctx, int $id): void
    {
        $pdo = $this->pdo(); $org = $this->orgId($ctx);

        $code = trim((string)($_POST['code'] ?? ''));
        $name = trim((string)($_POST['name'] ?? ''));
        $type = (string)($_POST['type'] ?? 'asset');
        $parent_id = (int)($_POST['parent_id'] ?? 0) ?: null;
        $is_active = isset($_POST['is_active']) ? 1 : 0;

        $up = $pdo->prepare("UPDATE dms_accounts SET code=?, name=?, type=?, parent_id=?, is_active=?, updated_at=NOW() WHERE org_id=? AND id=?");
        $up->execute([$code, $name, $type, $parent_id, $is_active, $org, $id]);

        $this->redirect($this->moduleBase($ctx).'/accounting/accounts');
    }
}