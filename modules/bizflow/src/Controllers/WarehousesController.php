<?php
declare(strict_types=1);

namespace Modules\BizFlow\Controllers;

use PDO;
use Throwable;

final class WarehousesController extends BaseController
{
  
  
  /** Auto-generate a unique warehouse code if user leaves it blank. */
private function generateWarehouseCode(PDO $pdo, int $orgId, string $name, string $city = ''): string
{
    // Prefer city for prefix, otherwise name
    $source = $city !== '' ? $city : $name;
    $source = strtoupper(preg_replace('/[^A-Z]/', '', $source));

    if ($source === '') {
        $source = 'WH';
    }

    // Take first 3 letters, e.g. DHA → DHA01, DHA02...
    $prefix = substr($source, 0, 3);

    for ($i = 1; $i <= 99; $i++) {
        $code = sprintf('%s%02d', $prefix, $i);
        $exists = (int)$this->val(
            "SELECT COUNT(*) FROM biz_warehouses WHERE org_id = ? AND code = ?",
            [$orgId, $code]
        );
        if ($exists === 0) {
            return $code;
        }
    }

    // Fallback: something still unique
    return $prefix . strtoupper(dechex(random_int(0, 255)));
}
  
    /**
     * GET /apps/bizflow/warehouses
     * GET /t/{slug}/apps/bizflow/warehouses
     */
    public function index(?array $ctx = null): void
    {
        try {
            $c     = $this->ctx($ctx ?? []);
            $orgId = $this->requireOrg();

            $q      = trim((string)($_GET['q'] ?? ''));
            $type   = trim((string)($_GET['type'] ?? ''));
            $status = trim((string)($_GET['status'] ?? '')); // '', active, inactive

            $sql = "
                SELECT
                    id,
                    org_id,
                    code,
                    name,
                    type,
                    is_default,
                    is_active,
                    city,
                    district,
                    country,
                    created_at,
                    updated_at
                FROM biz_warehouses
                WHERE org_id = :org_id
            ";
            $bind = ['org_id' => $orgId];

            if ($q !== '') {
                $sql .= " AND (
                              code    LIKE :q
                           OR name    LIKE :q
                           OR city    LIKE :q
                           OR district LIKE :q
                           OR country LIKE :q
                         )";
                $bind['q'] = '%'.$q.'%';
            }

            if ($type !== '') {
                $sql .= " AND type = :type";
                $bind['type'] = $type;
            }

            if ($status === 'active') {
                $sql .= " AND is_active = 1";
            } elseif ($status === 'inactive') {
                $sql .= " AND is_active = 0";
            }

            // IMPORTANT: only use columns that actually exist in the table
            $sql .= " ORDER BY is_default DESC, name ASC, id ASC LIMIT 500";

            $rows = $this->rows($sql, $bind);

            // Simple metrics for the top cards
            $metrics = [
                'total'      => count($rows),
                'active'     => 0,
                'stores'     => 0,
                'shops'      => 0,
                'in_transit' => 0,
                'virtual'    => 0,
            ];

            foreach ($rows as $w) {
                if ((int)($w['is_active'] ?? 0) === 1) {
                    $metrics['active']++;
                }
                switch ((string)($w['type'] ?? '')) {
                    case 'store':      $metrics['stores']++; break;
                    case 'shop':       $metrics['shops']++; break;
                    case 'in_transit': $metrics['in_transit']++; break;
                    case 'virtual':    $metrics['virtual']++; break;
                }
            }

            $this->view('warehouses/index', [
                'title'         => 'Warehouses',
                'org'           => $c['org'] ?? [],
                'module_base'   => $c['module_base'] ?? '/apps/bizflow',
                'warehouses'    => $rows,
                'metrics'       => $metrics,
                'search'        => $q,
                'filter_type'   => $type,
                'filter_status' => $status,
            ], 'shell');

        } catch (Throwable $e) {
            $this->oops('Warehouses index failed', $e);
        }
    }

    /**
     * GET /apps/bizflow/warehouses/create
     */
    public function create(?array $ctx = null): void
    {
        try {
            $c = $this->ctx($ctx ?? []);
            $this->requireOrg();

            $this->view('warehouses/create', [
                'title'       => 'New warehouse',
                'org'         => $c['org'] ?? [],
                'module_base' => $c['module_base'] ?? '/apps/bizflow',
                'errors'      => [],
                'old'         => [],
            ], 'shell');

        } catch (Throwable $e) {
            $this->oops('Warehouse create failed', $e);
        }
    }

    /**
     * POST /apps/bizflow/warehouses
     */
    public function store(?array $ctx = null): void
{
    try {
        $this->postOnly();

        $c     = $this->ctx($ctx ?? []);
        $orgId = $this->requireOrg();
        $pdo   = $this->pdo();

        $name     = trim((string)($_POST['name'] ?? ''));
        $code     = strtoupper(trim((string)($_POST['code'] ?? '')));
        $type     = (string)($_POST['type'] ?? 'store');
        $city     = trim((string)($_POST['city'] ?? ''));
        $district = trim((string)($_POST['district'] ?? ''));
        $country  = trim((string)($_POST['country'] ?? ''));
        $notes    = trim((string)($_POST['notes'] ?? ''));

        $isDefault = !empty($_POST['is_default']) ? 1 : 0;
        $isActive  = array_key_exists('is_active', $_POST)
            ? (!empty($_POST['is_active']) ? 1 : 0)
            : 1;

        $errors = [];

        if ($name === '') {
            $errors[] = 'Name is required.';
        }

        if (!in_array($type, ['store','shop','in_transit','virtual'], true)) {
            $type = 'store';
        }

        // 🔹 Auto-generate code if blank
        if ($code === '' && $name !== '') {
            $code = $this->generateWarehouseCode($pdo, $orgId, $name, $city);
        }

        // Still blank? then error (extreme edge case)
        if ($code === '') {
            $errors[] = 'Code is required.';
        }

        // Uniqueness check
        if ($code !== '') {
            $exists = (int)$this->val(
                "SELECT COUNT(*) FROM biz_warehouses WHERE org_id = ? AND code = ?",
                [$orgId, $code]
            );
            if ($exists > 0) {
                $errors[] = 'This code is already used for another warehouse.';
            }
        }

        if ($errors) {
            $types = [
                'store'      => 'Store / central warehouse',
                'shop'       => 'Shop / outlet',
                'in_transit' => 'In-transit location',
                'virtual'    => 'Virtual / logical location',
            ];

            $this->view('warehouses/create', [
                'title'       => 'New warehouse',
                'org'         => $c['org'] ?? [],
                'module_base' => $c['module_base'] ?? '/apps/bizflow',
                'types'       => $types,
                'errors'      => $errors,
                'old'         => $_POST,
            ], 'shell');

            return;
        }

        // 🔹 Insert
        $pdo->beginTransaction();

        $stmt = $pdo->prepare("
            INSERT INTO biz_warehouses
                (org_id, code, name, type,
                 is_default, is_active,
                 city, district, country, notes,
                 created_at, updated_at)
            VALUES
                (:org_id, :code, :name, :type,
                 :is_default, :is_active,
                 :city, :district, :country, :notes,
                 NOW(), NOW())
        ");

        $stmt->execute([
            'org_id'     => $orgId,
            'code'       => $code,
            'name'       => $name,
            'type'       => $type,
            'is_default' => $isDefault,
            'is_active'  => $isActive,
            'city'       => $city !== '' ? $city : null,
            'district'   => $district !== '' ? $district : null,
            'country'    => $country !== '' ? $country : null,
            'notes'      => $notes !== '' ? $notes : null,
        ]);

        $id = (int)$pdo->lastInsertId();

        // If marked default, clear others for this org
        if ($isDefault === 1) {
            $pdo->prepare("
                UPDATE biz_warehouses
                   SET is_default = 0
                 WHERE org_id = ? AND id <> ?
            ")->execute([$orgId, $id]);
        }

        $pdo->commit();

        $base = $c['module_base'] ?? '/apps/bizflow';
        if (!headers_sent()) {
            header('Location: '.$base.'/warehouse', true, 302);
        }
        exit;

    } catch (Throwable $e) {
        if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $this->oops('Warehouses store failed', $e);
    }
}

    /**
     * GET /apps/bizflow/warehouses/{id}/edit
     */
    public function edit(?array $ctx, int $id): void
    {
        try {
            $c     = $this->ctx($ctx ?? []);
            $orgId = $this->requireOrg();

            $wh = $this->row(
                "SELECT *
                   FROM biz_warehouses
                  WHERE org_id = ? AND id = ?
                  LIMIT 1",
                [$orgId, $id]
            );

            if (!$wh) {
                http_response_code(404);
                echo 'Warehouse not found.';
                return;
            }

            $this->view('warehouses/edit', [
                'title'       => 'Edit warehouse',
                'org'         => $c['org'] ?? [],
                'module_base' => $c['module_base'] ?? '/apps/bizflow',
                'warehouse'   => $wh,
                'errors'      => [],
            ], 'shell');

        } catch (Throwable $e) {
            $this->oops('Warehouse edit failed', $e);
        }
    }

    /**
     * POST /apps/bizflow/warehouses/{id}
     */
    public function update(?array $ctx, int $id): void
    {
        try {
            $this->postOnly();

            $c     = $this->ctx($ctx ?? []);
            $orgId = $this->requireOrg();
            $pdo   = $this->pdo();

            $code      = trim((string)($_POST['code'] ?? ''));
            $name      = trim((string)($_POST['name'] ?? ''));
            $type      = trim((string)($_POST['type'] ?? 'store'));
            $city      = trim((string)($_POST['city'] ?? ''));
            $district  = trim((string)($_POST['district'] ?? ''));
            $country   = trim((string)($_POST['country'] ?? ''));
            $notes     = trim((string)($_POST['notes'] ?? ''));
            $isDefault = isset($_POST['is_default']) ? 1 : 0;
            $isActive  = isset($_POST['is_active']) ? 1 : 0;

            $wh = $this->row(
                "SELECT *
                   FROM biz_warehouses
                  WHERE org_id = ? AND id = ?
                  LIMIT 1",
                [$orgId, $id]
            );
            if (!$wh) {
                http_response_code(404);
                echo 'Warehouse not found.';
                return;
            }

            $errors = [];
            if ($code === '') $errors[] = 'Code is required.';
            if ($name === '') $errors[] = 'Name is required.';

            $rerender = function (array $errors) use ($c, $wh): void {
                $this->view('warehouses/edit', [
                    'title'       => 'Edit warehouse',
                    'org'         => $c['org'] ?? [],
                    'module_base' => $c['module_base'] ?? '/apps/bizflow',
                    'warehouse'   => $wh,
                    'errors'      => $errors,
                ], 'shell');
            };

            if ($errors) {
                $rerender($errors);
                return;
            }

            if ($isDefault === 1) {
                $pdo->prepare("UPDATE biz_warehouses SET is_default = 0 WHERE org_id = ? AND id <> ?")
                    ->execute([$orgId, $id]);
            }

            $stmt = $pdo->prepare("
                UPDATE biz_warehouses
                   SET code       = :code,
                       name       = :name,
                       type       = :type,
                       is_default = :is_default,
                       is_active  = :is_active,
                       city       = :city,
                       district   = :district,
                       country    = :country,
                       notes      = :notes,
                       updated_at = NOW()
                 WHERE org_id    = :org_id
                   AND id        = :id
            ");

            $stmt->execute([
                'code'       => $code,
                'name'       => $name,
                'type'       => in_array($type, ['store','shop','in_transit','virtual'], true) ? $type : 'store',
                'is_default' => $isDefault,
                'is_active'  => $isActive,
                'city'       => $city !== '' ? $city : null,
                'district'   => $district !== '' ? $district : null,
                'country'    => $country !== '' ? $country : null,
                'notes'      => $notes !== '' ? $notes : null,
                'org_id'     => $orgId,
                'id'         => $id,
            ]);

            $base = $c['module_base'] ?? '/apps/bizflow';
            $this->redirect($base.'/warehouses');

        } catch (Throwable $e) {
            $this->oops('Warehouse update failed', $e);
        }
    }

    /**
     * GET /apps/bizflow/warehouses/{id}
     */
    public function show(?array $ctx, int $id): void
    {
        try {
            $c     = $this->ctx($ctx ?? []);
            $orgId = $this->requireOrg();

            $wh = $this->row(
                "SELECT *
                   FROM biz_warehouses
                  WHERE org_id = ? AND id = ?
                  LIMIT 1",
                [$orgId, $id]
            );
            if (!$wh) {
                http_response_code(404);
                echo 'Warehouse not found.';
                return;
            }

            $this->view('warehouses/show', [
                'title'       => 'Warehouse — '.($wh['name'] ?? 'Details'),
                'org'         => $c['org'] ?? [],
                'module_base' => $c['module_base'] ?? '/apps/bizflow',
                'warehouse'   => $wh,
            ], 'shell');

        } catch (Throwable $e) {
            $this->oops('Warehouse show failed', $e);
        }
    }
}