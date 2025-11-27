<?php
declare(strict_types=1);

namespace Modules\POS\Controllers;

use PDO;
use Throwable;

class BranchesController extends BaseController
{
    /* ============================================================
       Helpers
    ============================================================ */

    /** Resolve branches table name. */
    protected function branchesTable(PDO $pdo): string
    {
        foreach (['pos_branches', 'branches'] as $t) {
            if ($this->hasTable($pdo, $t)) {
                return $t;
            }
        }
        throw new \RuntimeException('Branches table not found');
    }

    /** Normalized ctx helper (same pattern as SalesController). */
    protected function ensureBase(array $ctx = []): array
    {
        return $this->ctx($ctx);
    }

    /** Load single branch for this org (or fail). */
    protected function requireBranch(PDO $pdo, int $orgId, int $id): array
    {
        $tbl = $this->branchesTable($pdo);

        $where = 'id = ?';
        $bind  = [$id];

        if ($this->hasCol($pdo, $tbl, 'org_id')) {
            $where .= ' AND org_id = ?';
            $bind[] = $orgId;
        }

        $row = $this->row("SELECT * FROM {$tbl} WHERE {$where} LIMIT 1", $bind);
        if (!$row) {
            throw new \RuntimeException('Branch not found');
        }
        return $row;
    }

    /** Find which column (if any) marks the “main” branch. */
    protected function mainFlagColumn(PDO $pdo, string $tbl): ?string
    {
        foreach (['is_main', 'is_primary', 'is_head_office', 'is_default'] as $c) {
            if ($this->hasCol($pdo, $tbl, $c)) {
                return $c;
            }
        }
        return null;
    }

    /** Auto-generate a branch code if empty (BRN-XXX-### style). */
    protected function generateCode(PDO $pdo, string $tbl, int $orgId, string $name): string
    {
        $base = strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $name), 0, 3));
        if ($base === '') {
            $base = 'BRN';
        }
        $prefix = $base . '-';

        // Scope by org if org_id column exists
        $where = 'code LIKE ?';
        $bind  = [$prefix.'%'];

        if ($this->hasCol($pdo, $tbl, 'org_id')) {
            $where = 'org_id = ? AND ' . $where;
            array_unshift($bind, $orgId);
        }

        $row = $this->row(
            "SELECT code FROM {$tbl} WHERE {$where} ORDER BY code DESC LIMIT 1",
            $bind
        );

        $seq = 1;
        if ($row && preg_match('/(\d+)$/', (string)$row['code'], $m)) {
            $seq = (int)$m[1] + 1;
        }

        return $prefix . sprintf('%03d', $seq);
    }

    /* ============================================================
       List branches
    ============================================================ */
    public function index(array $ctx = []): void
    {
        try {
            $c     = $this->ensureBase($ctx);
            $pdo   = $this->pdo();
            $orgId = $this->requireOrg();
            $tbl   = $this->branchesTable($pdo);

            // Build column list dynamically
            $cols = ['id', 'name'];

            foreach (['code', 'address', 'phone', 'email', 'is_active', 'sort_order'] as $col) {
                if ($this->hasCol($pdo, $tbl, $col)) {
                    $cols[] = $col;
                }
            }

            // Include main-branch flag column if exists
            if ($mainCol = $this->mainFlagColumn($pdo, $tbl)) {
                if (!in_array($mainCol, $cols, true)) {
                    $cols[] = $mainCol;
                }
            }

            $sql   = 'SELECT '.implode(',', $cols).' FROM '.$tbl;
            $where = [];
            $bind  = [];

            if ($this->hasCol($pdo, $tbl, 'org_id')) {
                $where[] = 'org_id = ?';
                $bind[]  = $orgId;
            }

            if ($where) {
                $sql .= ' WHERE '.implode(' AND ', $where);
            }

            // ORDER BY: sort_order if present, else name
            $orderParts = [];
            if ($this->hasCol($pdo, $tbl, 'sort_order')) {
                $orderParts[] = 'sort_order IS NULL';
                $orderParts[] = 'sort_order';
            }
            $orderParts[] = 'name';
            $sql .= ' ORDER BY '.implode(',', $orderParts);

            $rows = $this->rows($sql, $bind);

            $this->view($c['module_dir'].'/Views/branches/index.php', [
                'title'    => 'Branches',
                'branches' => $rows,
                'base'     => $c['module_base'],
            ], 'shell');

        } catch (Throwable $e) {
            $this->oops('Branch list failed', $e);
        }
    }

    /* ============================================================
       New form
    ============================================================ */
    public function create(array $ctx = []): void
    {
        $c = $this->ensureBase($ctx);

        $this->view($c['module_dir'].'/Views/branches/create.php', [
            'title' => 'Add Branch',
            'base'  => $c['module_base'],
        ], 'shell');
    }

    /* ============================================================
       Store
    ============================================================ */
    public function store(array $ctx = []): void
    {
        try {
            $this->postOnly();

            $pdo   = $this->pdo();
            $orgId = $this->requireOrg();
            $tbl   = $this->branchesTable($pdo);

            $name    = trim((string)($_POST['name'] ?? ''));
            $code    = trim((string)($_POST['code'] ?? ''));
            $address = trim((string)($_POST['address'] ?? ''));
            $phone   = trim((string)($_POST['phone'] ?? ''));
            $email   = trim((string)($_POST['email'] ?? ''));
            $active  = isset($_POST['is_active']) ? (int)$_POST['is_active'] : 1;
            $isMainInput = !empty($_POST['is_main']); // checkbox from form (optional)

            if ($name === '') {
                throw new \RuntimeException('Name is required');
            }

            $mainCol = $this->mainFlagColumn($pdo, $tbl);

            $cols = [];
            $qs   = [];
            $vals = [];

            // org_id if exists
            if ($this->hasCol($pdo, $tbl, 'org_id')) {
                $cols[] = 'org_id';
                $qs[]   = '?';
                $vals[] = $orgId;
            }

            // required name
            $cols[] = 'name';
            $qs[]   = '?';
            $vals[] = $name;

            // code (always write something if column exists)
            if ($this->hasCol($pdo, $tbl, 'code')) {
                if ($code === '') {
                    $code = $this->generateCode($pdo, $tbl, $orgId, $name);
                }
                $cols[] = 'code';
                $qs[]   = '?';
                $vals[] = $code;
            }

            // optional cols
            if ($this->hasCol($pdo, $tbl, 'address')) {
                $cols[] = 'address';
                $qs[]   = '?';
                $vals[] = $address;
            }
            if ($this->hasCol($pdo, $tbl, 'phone')) {
                $cols[] = 'phone';
                $qs[]   = '?';
                $vals[] = $phone;
            }
            if ($this->hasCol($pdo, $tbl, 'email')) {
                $cols[] = 'email';
                $qs[]   = '?';
                $vals[] = $email;
            }
            if ($this->hasCol($pdo, $tbl, 'is_active')) {
                $cols[] = 'is_active';
                $qs[]   = '?';
                $vals[] = $active;
            }

            if ($mainCol !== null) {
                $cols[] = $mainCol;
                $qs[]   = '?';
                $vals[] = $isMainInput ? 1 : 0;
            }

            // timestamps
            if ($this->hasCol($pdo, $tbl, 'created_at')) {
                $cols[] = 'created_at';
                $qs[]   = 'NOW()';
            }
            if ($this->hasCol($pdo, $tbl, 'updated_at')) {
                $cols[] = 'updated_at';
                $qs[]   = 'NOW()';
            }

            $sql = "INSERT INTO {$tbl} (".implode(',', $cols).") VALUES (".implode(',', $qs).")";

            $this->begin();
            $this->exec($sql, $vals);
            $branchId = (int)$this->pdo()->lastInsertId();

            // If this is marked main, clear the flag on all other branches of this org
            if ($mainCol !== null && $isMainInput) {
                $whereOrg = $this->hasCol($pdo, $tbl, 'org_id') ? 'org_id = ? AND ' : '';
                $bindMain = $this->hasCol($pdo, $tbl, 'org_id') ? [$orgId, $branchId] : [$branchId];

                $this->exec(
                    "UPDATE {$tbl}
                     SET {$mainCol} = 0
                     WHERE {$whereOrg} id <> ?",
                    $bindMain
                );
            }

            $this->commit();

            $this->redirect($_POST['_return'] ?? './branches');

        } catch (Throwable $e) {
            $this->rollBack();
            $this->oops('Create branch failed', $e);
        }
    }

    /* ============================================================
       Edit
    ============================================================ */
    public function edit(array $ctx = [], int $id = 0): void
    {
        try {
            $c     = $this->ensureBase($ctx);
            $pdo   = $this->pdo();
            $orgId = $this->requireOrg();

            $row = $this->requireBranch($pdo, $orgId, $id);

            $this->view($c['module_dir'].'/Views/branches/edit.php', [
                'title'   => 'Edit Branch',
                'branch'  => $row,
                'base'    => $c['module_base'],
            ], 'shell');

        } catch (Throwable $e) {
            $this->oops('Edit branch failed', $e);
        }
    }

    /* ============================================================
       Update
    ============================================================ */
    public function update(array $ctx = [], int $id = 0): void
    {
        try {
            $this->postOnly();

            $pdo   = $this->pdo();
            $orgId = $this->requireOrg();
            $tbl   = $this->branchesTable($pdo);

            // ensure branch exists & belongs to org
            $this->requireBranch($pdo, $orgId, $id);

            $name    = trim((string)($_POST['name'] ?? ''));
            $code    = trim((string)($_POST['code'] ?? ''));
            $address = trim((string)($_POST['address'] ?? ''));
            $phone   = trim((string)($_POST['phone'] ?? ''));
            $email   = trim((string)($_POST['email'] ?? ''));
            $active  = isset($_POST['is_active']) ? (int)$_POST['is_active'] : 1;
            $isMainInput = !empty($_POST['is_main']);

            if ($name === '') {
                throw new \RuntimeException('Name is required');
            }

            $mainCol = $this->mainFlagColumn($pdo, $tbl);

            $sets = [];
            $vals = [];

            $sets[] = 'name = ?';
            $vals[] = $name;

            if ($this->hasCol($pdo, $tbl, 'code')) {
                // If user left code empty, auto-generate one
                if ($code === '') {
                    $code = $this->generateCode($pdo, $tbl, $orgId, $name);
                }
                $sets[] = 'code = ?';
                $vals[] = $code;
            }
            if ($this->hasCol($pdo, $tbl, 'address')) {
                $sets[] = 'address = ?';
                $vals[] = $address;
            }
            if ($this->hasCol($pdo, $tbl, 'phone')) {
                $sets[] = 'phone = ?';
                $vals[] = $phone;
            }
            if ($this->hasCol($pdo, $tbl, 'email')) {
                $sets[] = 'email = ?';
                $vals[] = $email;
            }
            if ($this->hasCol($pdo, $tbl, 'is_active')) {
                $sets[] = 'is_active = ?';
                $vals[] = $active;
            }

            if ($mainCol !== null) {
                $sets[] = "{$mainCol} = ?";
                $vals[] = $isMainInput ? 1 : 0;
            }

            if ($this->hasCol($pdo, $tbl, 'updated_at')) {
                $sets[] = 'updated_at = NOW()';
            }

            $where = 'id = ?';
            $vals[] = $id;

            if ($this->hasCol($pdo, $tbl, 'org_id')) {
                $where .= ' AND org_id = ?';
                $vals[] = $orgId;
            }

            $sql = "UPDATE {$tbl} SET ".implode(', ', $sets)." WHERE {$where}";

            $this->begin();
            $this->exec($sql, $vals);

            // Enforce single main branch per org
            if ($mainCol !== null && $isMainInput) {
                $whereOrg = $this->hasCol($pdo, $tbl, 'org_id') ? 'org_id = ? AND ' : '';
                $bindMain = $this->hasCol($pdo, $tbl, 'org_id') ? [$orgId, $id] : [$id];

                $this->exec(
                    "UPDATE {$tbl}
                     SET {$mainCol} = 0
                     WHERE {$whereOrg} id <> ?",
                    $bindMain
                );
            }

            $this->commit();

            $this->redirect($_POST['_return'] ?? '../branches');

        } catch (Throwable $e) {
            $this->rollBack();
            $this->oops('Update branch failed', $e);
        }
    }

    /* ============================================================
       Branch Switch (sets session)
    ============================================================ */
    public function switchBranch(array $ctx = [], int $branchId = 0, ?string $redirectTo = null): void
{
    try {
        $c     = $this->ctx($ctx);
        $base  = (string)($c['module_base'] ?? '/apps/pos');
        $orgId = (int)($c['org']['id'] ?? $c['org_id'] ?? 0);
        $pdo   = $this->pdo();

        if ($branchId <= 0) {
            throw new \RuntimeException('No branch selected.');
        }

        // confirm branch belongs to this org
        if ($this->hasTable($pdo, 'pos_branches')) {
            $exists = (int)$this->val("
                SELECT COUNT(*) 
                  FROM pos_branches
                 WHERE org_id = :o AND id = :id
            ", [':o' => $orgId, ':id' => $branchId]);

            if ($exists === 0) {
                throw new \RuntimeException('Branch not found.');
            }
        }

        // store in session
        if (session_status() !== PHP_SESSION_ACTIVE) {
            @session_start();
        }
        $_SESSION['pos_branch_id'] = $branchId;
        $_SESSION['branch_id']     = $branchId;   // in case other code uses this

        // compute redirect target
        if ($redirectTo === null || $redirectTo === '') {
            // ?r=/custom/path wins, then Referer, then sales register
            $redirectTo = (string)($_GET['r'] ?? $_POST['r'] ?? '');
            if ($redirectTo === '') {
                $redirectTo = $_SERVER['HTTP_REFERER'] ?? '';
            }
            if ($redirectTo === '') {
                $redirectTo = rtrim($base, '/') . '/sales/register';
            }
        }

        $this->redirect($redirectTo);
    } catch (Throwable $e) {
        $this->oops('Branch switch failed', $e);
    }
}
}