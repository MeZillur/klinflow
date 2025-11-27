<?php
declare(strict_types=1);

namespace Modules\BizFlow\Controllers;

use Throwable;

/**
 * BizFlow UomsController
 * - 2035-style UoM master (like Odoo but simpler)
 * - Index = list + inline edit
 * - Create / Update with strong validation
 */
final class UomsController extends BaseController
{
    /**
     * GET /apps/bizflow/uoms
     * GET /t/{slug}/apps/bizflow/uoms
     *
     * Shows all UoMs with inline-edit rows.
     */
    public function index(?array $ctx = null): void
    {
        try {
            $c     = $this->ctx($ctx ?? []);
            $orgId = $this->requireOrg();

            $search     = trim((string)($_GET['q'] ?? ''));
            $onlyActive = isset($_GET['active']) && $_GET['active'] === '1';

            $where = ['org_id = ?'];
            $bind  = [$orgId];

            if ($search !== '') {
                $where[] = '(name LIKE ? OR code LIKE ?)';
                $like    = '%'.$search.'%';
                $bind[]  = $like;
                $bind[]  = $like;
            }

            if ($onlyActive) {
                $where[] = '(is_active = 1)';
            }

            // We query directly; if table is missing, migration must be run.
            $sql = "
                SELECT id, name, code, decimals, is_active, notes,
                       created_at, updated_at
                  FROM biz_uoms
                 WHERE " . implode(' AND ', $where) . "
                 ORDER BY name ASC, code ASC, id ASC
                 LIMIT 500
            ";

            $uoms = $this->rows($sql, $bind);

            $this->view('uoms/index', [
                'title'       => 'Units of measure',
                'org'         => $c['org'] ?? [],
                'module_base' => $c['module_base'] ?? '/apps/bizflow',
                'uoms'        => $uoms,
                'search'      => $search,
                'only_active' => $onlyActive,
            ], 'shell');

        } catch (Throwable $e) {
            $this->oops('UoMs index failed', $e);
        }
    }

    /**
     * GET /apps/bizflow/uoms/create
     * (Used if you want a separate “New UoM” form, but not required for inline.)
     */
    public function create(?array $ctx = null): void
    {
        try {
            $c = $this->ctx($ctx ?? []);
            $this->requireOrg();

            $this->view('uoms/create', [
                'title'       => 'New unit of measure',
                'org'         => $c['org'] ?? [],
                'module_base' => $c['module_base'] ?? '/apps/bizflow',
                'mode'        => 'create',
                'uom'         => [
                    'name'      => '',
                    'code'      => '',
                    'decimals'  => 0,
                    'is_active' => 1,
                    'notes'     => '',
                ],
                'errors'      => [],
            ], 'shell');

        } catch (Throwable $e) {
            $this->oops('UoM create failed', $e);
        }
    }

    /**
     * POST /apps/bizflow/uoms
     * (Create from dedicated form; inline add could also POST here.)
     */
    public function store(?array $ctx = null): void
    {
        try {
            $this->postOnly();
            $c     = $this->ctx($ctx ?? []);
            $orgId = $this->requireOrg();

            $name      = trim((string)($_POST['name'] ?? ''));
            $code      = strtoupper(trim((string)($_POST['code'] ?? '')));
            $decimals  = (int)($_POST['decimals'] ?? 0);
            $isActive  = isset($_POST['is_active']) ? 1 : 0;
            $notes     = trim((string)($_POST['notes'] ?? ''));

            $errors = [];

            if ($name === '') {
                $errors['name'] = 'Name is required.';
            }

            if ($code === '') {
                $errors['code'] = 'Code is required.';
            } elseif (!preg_match('/^[A-Z0-9_.-]{1,32}$/', $code)) {
                $errors['code'] = 'Use A–Z, 0–9, dot, dash or underscore only (max 32).';
            }

            if ($decimals < 0 || $decimals > 6) {
                $errors['decimals'] = 'Decimals must be between 0 and 6.';
            }

            // Duplicate code within same org
            $exists = $this->val(
                "SELECT id
                   FROM biz_uoms
                  WHERE org_id = ? AND code = ?
                  LIMIT 1",
                [$orgId, $code]
            );
            if ($exists) {
                $errors['code'] = 'This code already exists for this organisation.';
            }

            if ($errors) {
                $this->view('uoms/create', [
                    'title'       => 'New unit of measure',
                    'org'         => $c['org'] ?? [],
                    'module_base' => $c['module_base'] ?? '/apps/bizflow',
                    'mode'        => 'create',
                    'uom'         => [
                        'name'      => $name,
                        'code'      => $code,
                        'decimals'  => $decimals,
                        'is_active' => $isActive,
                        'notes'     => $notes,
                    ],
                    'errors'      => $errors,
                ], 'shell');
                return;
            }

            $this->exec(
                "INSERT INTO biz_uoms (org_id, name, code, decimals, is_active, notes)
                 VALUES (?, ?, ?, ?, ?, ?)",
                [$orgId, $name, $code, $decimals, $isActive, $notes !== '' ? $notes : null]
            );

            $base = $c['module_base'] ?? '/apps/bizflow';
            $this->redirect(rtrim($base, '/').'/uoms');

        } catch (Throwable $e) {
            $this->oops('UoM store failed', $e);
        }
    }

    /**
     * POST /apps/bizflow/uoms/{id}
     * Used by inline “Save” on the index list.
     */
    public function update(?array $ctx, int $id): void
    {
        try {
            $this->postOnly();
            $c     = $this->ctx($ctx ?? []);
            $orgId = $this->requireOrg();

            $name      = trim((string)($_POST['name'] ?? ''));
            $code      = strtoupper(trim((string)($_POST['code'] ?? '')));
            $decimals  = (int)($_POST['decimals'] ?? 0);
            $isActive  = isset($_POST['is_active']) ? 1 : 0;
            $notes     = trim((string)($_POST['notes'] ?? ''));

            $errors = [];

            if ($name === '') {
                $errors['name'] = 'Name is required.';
            }

            if ($code === '') {
                $errors['code'] = 'Code is required.';
            } elseif (!preg_match('/^[A-Z0-9_.-]{1,32}$/', $code)) {
                $errors['code'] = 'Use A–Z, 0–9, dot, dash or underscore only (max 32).';
            }

            if ($decimals < 0 || $decimals > 6) {
                $errors['decimals'] = 'Decimals must be between 0 and 6.';
            }

            // Duplicate check (excluding self)
            $exists = $this->val(
                "SELECT id
                   FROM biz_uoms
                  WHERE org_id = ? AND code = ? AND id <> ?
                  LIMIT 1",
                [$orgId, $code, $id]
            );
            if ($exists) {
                $errors['code'] = 'This code already exists for this organisation.';
            }

            if ($errors) {
                // For inline errors we just re-render index;
                // simplest way: redirect back with flash later.
                // For now: show basic error response.
                http_response_code(400);
                echo 'Validation failed: '.htmlspecialchars(json_encode($errors), ENT_QUOTES, 'UTF-8');
                return;
            }

            $this->exec(
                "UPDATE biz_uoms
                    SET name = ?, code = ?, decimals = ?, is_active = ?, notes = ?
                  WHERE org_id = ? AND id = ?
                  LIMIT 1",
                [$name, $code, $decimals, $isActive, $notes !== '' ? $notes : null, $orgId, $id]
            );

            $base = $c['module_base'] ?? '/apps/bizflow';
            $this->redirect(rtrim($base, '/').'/uoms');

        } catch (Throwable $e) {
            $this->oops('UoM update failed', $e);
        }
    }

    /**
     * Optional stub: we don’t need a dedicated edit page if you’re
     * happy with inline editing. Leaving it as a safe 501.
     */
    public function edit(?array $ctx, int $id): void
    {
        http_response_code(501);
        echo 'Inline edit from index; separate edit page not used.';
    }
}