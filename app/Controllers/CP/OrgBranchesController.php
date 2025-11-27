<?php
declare(strict_types=1);

namespace App\Controllers\CP;

use Shared\DB;
use Shared\View;
use Shared\Csrf;
use App\Services\Validation;

final class OrgBranchesController
{
    /* -----------------------------------------------------------------
     * Small helpers
     * ----------------------------------------------------------------- */

    private function flash(string $k, string $v): void
    {
        $_SESSION[$k] = $v;
    }

    private function take(string $k): ?string
    {
        $v = $_SESSION[$k] ?? null;
        unset($_SESSION[$k]);
        return $v;
    }

    private function redirect(string $to): void
    {
        header('Location: ' . $to, true, 302);
        exit;
    }

    /**
     * Accept both ['org_id'=>] and ['id'=>] so shims/routes are forgiving.
     */
    private function orgIdFrom(array $params): int
    {
        return (int)($params['org_id'] ?? $params['id'] ?? 0);
    }

    private function pdo(): \PDO
    {
        return DB::pdo();
    }

    /**
     * Load org row or render CP 404 inside shell (never blank/white).
     */
    private function loadOrgOr404(int $orgId): array
    {
        if ($orgId <= 0) {
            http_response_code(404);
            View::render('cp/errors/404', [
                'layout' => 'cp-shell',
                'scope'  => 'cp',
                'title'  => 'Organization not found',
                'path'   => $_SERVER['REQUEST_URI'] ?? '',
            ]);
            exit;
        }

        $pdo = $this->pdo();
        $stmt = $pdo->prepare("
            SELECT id, name, slug, plan, status, owner_email
            FROM cp_organizations
            WHERE id = ?
            LIMIT 1
        ");
        $stmt->execute([$orgId]);
        $org = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$org) {
            http_response_code(404);
            View::render('cp/errors/404', [
                'layout' => 'cp-shell',
                'scope'  => 'cp',
                'title'  => 'Organization not found',
                'path'   => $_SERVER['REQUEST_URI'] ?? '',
            ]);
            exit;
        }

        return $org;
    }

    /* -----------------------------------------------------------------
     * LIST: GET /cp/organizations/{orgId}/branches
     * ----------------------------------------------------------------- */
    public function index(array $params): void
    {
        $orgId = $this->orgIdFrom($params);
        $org   = $this->loadOrgOr404($orgId);

        $pdo = $this->pdo();
        $stmt = $pdo->prepare("
            SELECT id, name, code, is_main, is_active, created_at
            FROM cp_org_branches
            WHERE org_id = ?
            ORDER BY is_main DESC, name ASC
        ");
        $stmt->execute([$orgId]);
        $branches = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        View::render('cp/org_branches/index', [
            'layout'   => 'cp-shell',
            'scope'    => 'cp',
            'title'    => 'Branches — ' . ($org['name'] ?? ''),
            'org'      => $org,
            'branches' => $branches,
            'csrf'     => Csrf::token(),
            'error'    => $this->take('_err'),
        ]);
    }

    /* -----------------------------------------------------------------
     * CREATE FORM: GET /cp/organizations/{orgId}/branches/create
     * ----------------------------------------------------------------- */
    public function createForm(array $params): void
    {
        $orgId = $this->orgIdFrom($params);
        $org   = $this->loadOrgOr404($orgId);

        View::render('cp/org_branches/create', [
            'layout' => 'cp-shell',
            'scope'  => 'cp',
            'title'  => 'Add branch — ' . ($org['name'] ?? ''),
            'org'    => $org,
            'csrf'   => Csrf::token(),
            'error'  => $this->take('_err'),
            'old'    => $_SESSION['_old'] ?? [],
        ]);

        unset($_SESSION['_old']);
    }

    /* -----------------------------------------------------------------
     * STORE: POST /cp/organizations/{orgId}/branches
     * ----------------------------------------------------------------- */
    public function store(array $params): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            $this->redirect('/cp/organizations');
        }
        if (!Csrf::verify($_POST['_csrf'] ?? '')) {
            $this->flash('_err', 'Session expired. Please try again.');
            $this->redirect('/cp/organizations');
        }

        $orgId = $this->orgIdFrom($params);
        $org   = $this->loadOrgOr404($orgId); // also ensures orgId > 0

        $name   = trim((string)($_POST['name'] ?? ''));
        $code   = trim((string)($_POST['code'] ?? ''));
        $active = !empty($_POST['is_active']) ? 1 : 0;

        $_SESSION['_old'] = [
            'name'      => $name,
            'code'      => $code,
            'is_active' => $active,
        ];

        $v = new Validation();
        $v->required($name, 'Branch name is required.');
        $v->length($name, 2, 190, 'Branch name must be 2–190 characters.');
        if ($code !== '') {
            $v->length($code, 2, 32, 'Branch code must be 2–32 characters.');
        }
        if ($v->fails()) {
            $this->flash('_err', implode("\n", $v->errors()));
            $this->redirect("/cp/organizations/{$orgId}/branches/create");
        }

        $pdo = $this->pdo();

        // Unique code per org (if provided)
        if ($code !== '') {
            $chk = $pdo->prepare("
                SELECT 1 FROM cp_org_branches
                WHERE org_id = ? AND code = ?
                LIMIT 1
            ");
            $chk->execute([$orgId, $code]);
            if ($chk->fetch()) {
                $this->flash('_err', 'Branch code already used for this organization.');
                $this->redirect("/cp/organizations/{$orgId}/branches/create");
            }
        }

        $ins = $pdo->prepare("
            INSERT INTO cp_org_branches
                (org_id, name, code, is_main, is_active, created_at, updated_at)
            VALUES
                (?, ?, ?, 0, ?, NOW(), NOW())
        ");
        $ins->execute([$orgId, $name, $code !== '' ? $code : null, $active]);

        unset($_SESSION['_old']);
        $this->redirect("/cp/organizations/{$orgId}/branches");
    }

    /* ======================= EDIT / UPDATE / DELETE (stubs) ====== */

    public function editForm(array $params): void
    {
        // We’ll implement this later once table is finalised
        $orgId = $this->orgIdFrom($params);
        $this->redirect("/cp/organizations/{$orgId}/branches");
    }

    public function update(array $params): void
    {
        $orgId = $this->orgIdFrom($params);
        $this->redirect("/cp/organizations/{$orgId}/branches");
    }

    public function destroy(array $params): void
    {
        $orgId = $this->orgIdFrom($params);
        $this->redirect("/cp/organizations/{$orgId}/branches");
    }
}