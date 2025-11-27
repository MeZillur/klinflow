<?php
declare(strict_types=1);

namespace Modules\DMS\Controllers\Accounts;

use Modules\DMS\Controllers\BaseController;
use PDO;

final class CoaController extends BaseController
{
    /** GET /accounts/coa */
    public function index(array $ctx = []): void
    {
        $pdo   = $this->pdo();
        $orgId = $this->orgId($ctx);

        // list
        $st = $pdo->prepare("
            SELECT id, code, name, type, COALESCE(parent_id,0) AS parent_id, COALESCE(is_active,1) AS is_active
            FROM dms_gl_accounts
            WHERE org_id = ?
            ORDER BY code ASC
        ");
        $st->execute([$orgId]);
        $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];

        // parent lookup for display
        $parents = [];
        foreach ($rows as $r) { $parents[(int)$r['id']] = $r['name']; }

        // edit prefill if ?edit={id}
        $editId = isset($_GET['edit']) && ctype_digit((string)$_GET['edit']) ? (int)$_GET['edit'] : 0;
        $editRow = null;
        if ($editId > 0) {
            $s2 = $pdo->prepare("
                SELECT id, code, name, type, COALESCE(parent_id,0) AS parent_id, COALESCE(is_active,1) AS is_active
                FROM dms_gl_accounts
                WHERE org_id=? AND id=? LIMIT 1
            ");
            $s2->execute([$orgId, $editId]);
            $editRow = $s2->fetch(PDO::FETCH_ASSOC) ?: null;
        }

        $this->view('accounts/coa/index', [
            'title'       => 'Chart of Accounts',
            'accounts'    => $rows,
            'parents'     => $parents,
            'editRow'     => $editRow,
            'types'       => ['asset'=>'Asset','liability'=>'Liability','equity'=>'Equity','income'=>'Income','expense'=>'Expense'],
            'brandColor'  => '#0a936b', // your Apply green
            'active'      => 'accounts',
            'subactive'   => 'accounts.coa',
        ], $ctx);
    }

    /** POST /accounts/coa (create or update based on action field) */
    public function store(array $ctx = []): void
    {
        $pdo   = $this->pdo();
        $orgId = $this->orgId($ctx);
        $base  = ($ctx['module_base'] ?? '/apps/dms').'/accounts/coa';

        $action = $_POST['action'] ?? 'create';
        $code   = trim((string)($_POST['code'] ?? ''));
        $name   = trim((string)($_POST['name'] ?? ''));
        $type   = trim((string)($_POST['type'] ?? 'asset'));
        $parent = isset($_POST['parent_id']) && $_POST['parent_id'] !== '' ? (int)$_POST['parent_id'] : null;
        $active = isset($_POST['is_active']) ? 1 : 0;

        if ($code === '' || $name === '' || !in_array($type, ['asset','liability','equity','income','expense'], true)) {
            // minimal guard; redirect back
            $this->redirect($base);
            return;
        }

        if ($action === 'update' && isset($_POST['id']) && ctype_digit((string)$_POST['id'])) {
            $id = (int)$_POST['id'];
            $st = $pdo->prepare("
                UPDATE dms_gl_accounts
                SET code=?, name=?, type=?, parent_id=?, is_active=?, updated_at=NOW()
                WHERE org_id=? AND id=? LIMIT 1
            ");
            $st->execute([$code, $name, $type, $parent, $active, $orgId, $id]);
            $this->redirect($base);
            return;
        }

        // default: create
        $ins = $pdo->prepare("
            INSERT INTO dms_gl_accounts (org_id, code, name, type, parent_id, is_active, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())
        ");
        $ins->execute([$orgId, $code, $name, $type, $parent, $active]);
        $this->redirect($base);
    }
}