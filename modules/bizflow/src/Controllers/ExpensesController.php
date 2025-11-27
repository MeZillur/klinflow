<?php
declare(strict_types=1);

namespace Modules\BizFlow\Controllers;

use PDO;
use Throwable;
use DateTimeImmutable;

final class LtasController extends BaseController
{
    /* -------------------------------------------------------------
     * Small helpers
     * ----------------------------------------------------------- */

    /**
     * Resolve ctx + org_id + pdo + module_base
     * (same pattern idea as other BizFlow controllers)
     */
    private function base(?array $ctx = null): array
    {
        $c        = $this->ctx($ctx ?? []);
        $orgId    = $this->requireOrg();
        $pdo      = $this->pdo();
        $module   = $c['module_base'] ?? '/apps/bizflow';

        return [$c, $orgId, $pdo, $module];
    }

    /** Safe table-existence check so UI can load before schema is live */
    private function hasTable(PDO $pdo, string $table): bool
    {
        try {
            $sql = "SELECT 1
                      FROM information_schema.tables
                     WHERE table_schema = DATABASE()
                       AND table_name   = :t
                     LIMIT 1";
            $st = $pdo->prepare($sql);
            $st->execute(['t' => $table]);
            return (bool)$st->fetchColumn();
        } catch (Throwable $e) {
            return false;
        }
    }

    /** Very small code → human label map for statuses */
    private function statusLabel(string $status): string
    {
        return match ($status) {
            'active'    => 'Active',
            'expired'   => 'Expired',
            'closed'    => 'Closed',
            'draft'     => 'Draft',
            'suspended' => 'Suspended',
            default     => ucfirst($status ?: 'Draft'),
        };
    }

    /* =============================================================
     * GET /ltas → index list
     * =========================================================== */
    public function index(?array $ctx = null): void
    {
        try {
            [$c, $orgId, $pdo, $moduleBase] = $this->base($ctx);

            $q          = trim((string)($_GET['q'] ?? ''));
            $status     = trim((string)($_GET['status'] ?? ''));
            $showActive = isset($_GET['only_active']) && $_GET['only_active'] === '1';
            $supplierQ  = trim((string)($_GET['supplier'] ?? '')); // text search on supplier name

            $where  = ['l.org_id = ?'];
            $params = [$orgId];

            if ($q !== '') {
                $like = '%' . $q . '%';
                // search by LTA no, title and supplier_name snapshot
                $where[]  = '(l.lta_no LIKE ? OR l.title LIKE ? OR l.supplier_name LIKE ?)';
                $params[] = $like;
                $params[] = $like;
                $params[] = $like;
            }

            if ($supplierQ !== '') {
                $where[]  = 'l.supplier_name LIKE ?';
                $params[] = '%' . $supplierQ . '%';
            }

            if ($status !== '') {
                $where[]  = 'l.status = ?';
                $params[] = $status;
            }

            if ($showActive) {
                $where[] = "l.status = 'active'";
            }

            $sql = "SELECT l.*
                      FROM biz_ltas l
                     WHERE " . implode(' AND ', $where) . "
                     ORDER BY l.start_date DESC, l.id DESC
                     LIMIT 100";

            $ltas = $this->rows($sql, $params);

            // Decorate with status_label + simple used%
            foreach ($ltas as &$row) {
                $row['status_label'] = $this->statusLabel((string)($row['status'] ?? 'draft'));

                $ceiling = (float)($row['ceiling_total'] ?? 0);
                $used    = (float)($row['used_total'] ?? 0);
                $row['usage_pct'] = $ceiling > 0
                    ? max(0, min(100, ($used / $ceiling) * 100))
                    : 0;
            }
            unset($row);

            $this->view('ltas/index', [
                'title'       => 'Framework contracts overview',
                'module_base' => $moduleBase,
                'org'         => $c['org'] ?? [],
                'filters'     => [
                    'q'          => $q,
                    'status'     => $status,
                    'onlyActive' => $showActive,
                    'supplier'   => $supplierQ,
                ],
                'ltas'        => $ltas,
            ], 'shell');

        } catch (Throwable $e) {
            $this->oops('LTA index failed', $e);
        }
    }

    /* =============================================================
     * GET /ltas/create → new LTA form
     * =========================================================== */
    public function create(?array $ctx = null): void
    {
        try {
            [$c, $orgId, $pdo, $moduleBase] = $this->base($ctx);

            // Supplier dropdown / autocomplete seed
            $suppliers = [];
            if ($this->hasTable($pdo, 'biz_suppliers')) {
                $suppliers = $this->rows(
                    "SELECT id, name, code
                       FROM biz_suppliers
                      WHERE org_id = ?
                      ORDER BY name
                      LIMIT 200",
                    [$orgId]
                );
            }

            $today = (new DateTimeImmutable('now'))->format('Y-m-d');
            // Placeholder LTA number – real number will be generated when saving
            $placeholderNo = 'LTA-' . date('Y') . '-XXXXX';

            $this->view('ltas/create', [
                'title'       => 'New Long-Term Agreement',
                'org'         => $c['org'] ?? [],
                'module_base' => $moduleBase,
                'suppliers'   => $suppliers,
                'today'       => $today,
                'lta_no'      => $placeholderNo,
                'csrf'        => $c['csrf'] ?? '',
            ], 'shell');

        } catch (Throwable $e) {
            $this->oops('LTA create failed', $e);
        }
    }

    /* =============================================================
     * POST /ltas → store new LTA
     * =========================================================== */
    public function store(?array $ctx = null): void
    {
        try {
            if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
                http_response_code(405);
                echo 'Method not allowed';
                return;
            }

            [$c, $orgId, $pdo, $moduleBase] = $this->base($ctx);
            $base = rtrim($moduleBase, '/');

            if (!$this->hasTable($pdo, 'biz_ltas')) {
                $this->json(['ok' => false, 'error' => 'LTA storage tables are not ready yet.'], 500);
                return;
            }

            $supplierId = (int)($_POST['supplier_id'] ?? 0);
            $title      = trim((string)($_POST['title'] ?? ''));
            $refNo      = trim((string)($_POST['reference_no'] ?? ''));
            $startDate  = (string)($_POST['start_date'] ?? '');
            $endDate    = (string)($_POST['end_date'] ?? '');
            $currency   = trim((string)($_POST['currency'] ?? 'BDT'));
            $ceiling    = (float)($_POST['ceiling_total'] ?? 0);
            $framework  = trim((string)($_POST['framework_type'] ?? 'lta')); // lta / bpa / framework
            $status     = trim((string)($_POST['status'] ?? 'active'));

            if ($supplierId <= 0) {
                $this->json(['ok' => false, 'error' => 'Supplier is required.'], 422);
                return;
            }
            if ($title === '') {
                $this->json(['ok' => false, 'error' => 'Title is required.'], 422);
                return;
            }

            $meta = [
                'framework_type' => $framework,
                'customer_name'  => trim((string)($_POST['customer_name'] ?? '')),
                'notes'          => trim((string)($_POST['notes'] ?? '')),
            ];
            $metaJson = json_encode($meta, JSON_UNESCAPED_UNICODE) ?: null;

            // Generate LTA number if not given: LTA-YYYY-00001
            $now       = new DateTimeImmutable('now');
            $dateForNo = $startDate !== '' ? $startDate : $now->format('Y-m-d');
            $year      = substr($dateForNo, 0, 4);
            if (!ctype_digit($year)) {
                $year = $now->format('Y');
            }
            $prefix = 'LTA-' . $year . '-';

            $last = $this->val(
                "SELECT lta_no
                   FROM biz_ltas
                  WHERE org_id = ? AND lta_no LIKE ?
                  ORDER BY lta_no DESC
                  LIMIT 1",
                [$orgId, $prefix . '%']
            );

            $next = 1;
            if ($last && preg_match('/^' . preg_quote($prefix, '/') . '(\d{5})$/', (string)$last, $m)) {
                $next = (int)$m[1] + 1;
            }
            $ltaNo = $prefix . str_pad((string)$next, 5, '0', STR_PAD_LEFT);

            $sql = "INSERT INTO biz_ltas (
                        org_id,
                        supplier_id,
                        lta_no,
                        title,
                        reference_no,
                        start_date,
                        end_date,
                        currency,
                        ceiling_total,
                        used_total,
                        status,
                        meta_json
                    ) VALUES (
                        :org_id,
                        :supplier_id,
                        :lta_no,
                        :title,
                        :reference_no,
                        :start_date,
                        :end_date,
                        :currency,
                        :ceiling_total,
                        0,
                        :status,
                        :meta_json
                    )";

            $st = $pdo->prepare($sql);
            $ok = $st->execute([
                'org_id'        => $orgId,
                'supplier_id'   => $supplierId,
                'lta_no'        => $ltaNo,
                'title'         => $title,
                'reference_no'  => $refNo !== '' ? $refNo : null,
                'start_date'    => $startDate !== '' ? $startDate : $now->format('Y-m-d'),
                'end_date'      => $endDate !== '' ? $endDate : null,
                'currency'      => $currency !== '' ? $currency : 'BDT',
                'ceiling_total' => $ceiling,
                'status'        => $status !== '' ? $status : 'active',
                'meta_json'     => $metaJson,
            ]);

            if (!$ok) {
                $this->json(['ok' => false, 'error' => 'Failed to save LTA.'], 500);
                return;
            }

            $id       = (int)$pdo->lastInsertId();
            $redirect = $base . '/ltas/' . $id;

            $this->json(['ok' => true, 'id' => $id, 'redirect' => $redirect]);

        } catch (Throwable $e) {
            $this->oops('LTA store failed', $e);
            $this->json(['ok' => false, 'error' => 'LTA store failed.'], 500);
        }
    }

    /* =============================================================
     * GET /ltas/{id} → details
     * =========================================================== */
    public function show(?array $ctx, int $id): void
    {
        try {
            [$c, $orgId, $pdo, $moduleBase] = $this->base($ctx);
            $org = $c['org'] ?? [];
            $id  = (int)$id;

            if (!$this->hasTable($pdo, 'biz_ltas')) {
                http_response_code(404);
                echo 'LTA storage not found.';
                return;
            }

            $lta = $this->row(
                "SELECT
                     l.*,
                     s.name AS supplier_name,
                     s.code AS supplier_code
                   FROM biz_ltas l
              LEFT JOIN biz_suppliers s
                     ON s.org_id = l.org_id
                    AND s.id     = l.supplier_id
                  WHERE l.org_id = ? AND l.id = ?
                  LIMIT 1",
                [$orgId, $id]
            );

            if (!$lta) {
                http_response_code(404);
                echo 'LTA not found.';
                return;
            }

            $lta['status_label'] = $this->statusLabel((string)($lta['status'] ?? 'draft'));

            $meta = [];
            if (!empty($lta['meta_json'])) {
                $tmp = json_decode((string)$lta['meta_json'], true);
                if (is_array($tmp)) {
                    $meta = $tmp;
                }
            }

            $items = [];
            if ($this->hasTable($pdo, 'biz_lta_items')) {
                $items = $this->rows(
                    "SELECT
                         li.*,
                         i.name AS item_name,
                         i.code AS item_code
                       FROM biz_lta_items li
                  LEFT JOIN biz_items i
                         ON i.org_id = li.org_id
                        AND i.id     = li.item_id
                      WHERE li.org_id = ?
                        AND li.lta_id = ?
                      ORDER BY li.line_no, li.id",
                    [$orgId, $id]
                );
            }

            $this->view('ltas/show', [
                'title'       => 'LTA details',
                'org'         => $org,
                'module_base' => $moduleBase,
                'lta'         => $lta,
                'meta'        => $meta,
                'items'       => $items,
            ], 'shell');

        } catch (Throwable $e) {
            $this->oops('LTA show failed', $e);
        }
    }

    /* =============================================================
     * GET /ltas/{id}/edit → edit form
     * =========================================================== */
    public function edit(?array $ctx, int $id): void
    {
        try {
            [$c, $orgId, $pdo, $moduleBase] = $this->base($ctx);
            $org = $c['org'] ?? [];
            $id  = (int)$id;

            if (!$this->hasTable($pdo, 'biz_ltas')) {
                http_response_code(404);
                echo 'LTA storage not found.';
                return;
            }

            $lta = $this->row(
                "SELECT
                     l.*,
                     s.name AS supplier_name,
                     s.code AS supplier_code
                   FROM biz_ltas l
              LEFT JOIN biz_suppliers s
                     ON s.org_id = l.org_id
                    AND s.id     = l.supplier_id
                  WHERE l.org_id = ? AND l.id = ?
                  LIMIT 1",
                [$orgId, $id]
            );

            if (!$lta) {
                http_response_code(404);
                echo 'LTA not found.';
                return;
            }

            $meta = [];
            if (!empty($lta['meta_json'])) {
                $tmp = json_decode((string)$lta['meta_json'], true);
                if (is_array($tmp)) {
                    $meta = $tmp;
                }
            }

            $suppliers = [];
            if ($this->hasTable($pdo, 'biz_suppliers')) {
                $suppliers = $this->rows(
                    "SELECT id, name, code
                       FROM biz_suppliers
                      WHERE org_id = ?
                      ORDER BY name ASC
                      LIMIT 200",
                    [$orgId]
                );
            }

            $this->view('ltas/edit', [
                'title'       => 'Edit LTA',
                'org'         => $org,
                'module_base' => $moduleBase,
                'lta'         => $lta,
                'meta'        => $meta,
                'suppliers'   => $suppliers,
            ], 'shell');

        } catch (Throwable $e) {
            $this->oops('LTA edit failed', $e);
        }
    }

    /* =============================================================
     * POST /ltas/{id}/update → update header only (for now)
     * =========================================================== */
    public function update(?array $ctx, int $id): void
    {
        try {
            if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
                http_response_code(405);
                echo 'Method not allowed';
                return;
            }

            [$c, $orgId, $pdo, $moduleBase] = $this->base($ctx);
            $base = rtrim($moduleBase, '/');
            $id   = (int)$id;

            if (!$this->hasTable($pdo, 'biz_ltas')) {
                $this->json(['ok' => false, 'error' => 'LTA storage tables not ready.'], 500);
                return;
            }

            $supplierId = (int)($_POST['supplier_id'] ?? 0);
            $title      = trim((string)($_POST['title'] ?? ''));
            $refNo      = trim((string)($_POST['reference_no'] ?? ''));
            $startDate  = (string)($_POST['start_date'] ?? '');
            $endDate    = (string)($_POST['end_date'] ?? '');
            $currency   = trim((string)($_POST['currency'] ?? 'BDT'));
            $ceiling    = (float)($_POST['ceiling_total'] ?? 0);
            $framework  = trim((string)($_POST['framework_type'] ?? 'lta'));
            $status     = trim((string)($_POST['status'] ?? 'active'));

            if ($supplierId <= 0) {
                $this->json(['ok' => false, 'error' => 'Supplier is required.'], 422);
                return;
            }
            if ($title === '') {
                $this->json(['ok' => false, 'error' => 'Title is required.'], 422);
                return;
            }

            // Load old meta to merge
            $old = $this->row(
                "SELECT meta_json
                   FROM biz_ltas
                  WHERE org_id = ? AND id = ?
                  LIMIT 1",
                [$orgId, $id]
            );
            if (!$old) {
                $this->json(['ok' => false, 'error' => 'LTA not found.'], 404);
                return;
            }

            $meta = [];
            if (!empty($old['meta_json'])) {
                $tmp = json_decode((string)$old['meta_json'], true);
                if (is_array($tmp)) {
                    $meta = $tmp;
                }
            }

            $meta['framework_type'] = $framework;
            $meta['customer_name']  = trim((string)($_POST['customer_name'] ?? ($meta['customer_name'] ?? '')));
            $meta['notes']          = trim((string)($_POST['notes'] ?? ($meta['notes'] ?? '')));

            $metaJson = json_encode($meta, JSON_UNESCAPED_UNICODE) ?: null;

            $sql = "UPDATE biz_ltas
                       SET supplier_id   = :supplier_id,
                           title         = :title,
                           reference_no  = :reference_no,
                           start_date    = :start_date,
                           end_date      = :end_date,
                           currency      = :currency,
                           ceiling_total = :ceiling_total,
                           status        = :status,
                           meta_json     = :meta_json
                     WHERE org_id = :org_id AND id = :id
                     LIMIT 1";

            $st = $pdo->prepare($sql);
            $st->execute([
                'supplier_id'   => $supplierId,
                'title'         => $title,
                'reference_no'  => $refNo !== '' ? $refNo : null,
                'start_date'    => $startDate !== '' ? $startDate : null,
                'end_date'      => $endDate !== '' ? $endDate : null,
                'currency'      => $currency !== '' ? $currency : 'BDT',
                'ceiling_total' => $ceiling,
                'status'        => $status !== '' ? $status : 'active',
                'meta_json'     => $metaJson,
                'org_id'        => $orgId,
                'id'            => $id,
            ]);

            $redirect = $base . '/ltas/' . $id;
            $this->json(['ok' => true, 'id' => $id, 'redirect' => $redirect]);

        } catch (Throwable $e) {
            $this->oops('LTA update failed', $e);
            $this->json(['ok' => false, 'error' => 'LTA update failed.'], 500);
        }
    }

    /* =============================================================
     * POST /ltas/{id}/status → quick pill change on index
     * =========================================================== */
    public function updateStatus(?array $ctx, int $id): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            http_response_code(405);
            $this->json(['ok' => false, 'error' => 'Method not allowed'], 405);
            return;
        }

        try {
            [$c, $orgId, $pdo] = $this->base($ctx);
            $id = (int)$id;

            $status = strtolower(trim((string)($_POST['status'] ?? '')));

            $allowed = ['draft', 'active', 'suspended', 'closed', 'expired'];
            if (!in_array($status, $allowed, true)) {
                $this->json([
                    'ok'    => false,
                    'error' => 'Invalid status value',
                    'value' => $status,
                ], 422);
                return;
            }

            if (!$this->hasTable($pdo, 'biz_ltas')) {
                $this->json(['ok' => false, 'error' => 'LTA storage not ready'], 500);
                return;
            }

            $st = $pdo->prepare(
                "UPDATE biz_ltas
                    SET status = :status, updated_at = NOW()
                  WHERE org_id = :org_id AND id = :id
                  LIMIT 1"
            );
            $st->execute([
                ':status' => $status,
                ':org_id' => $orgId,
                ':id'     => $id,
            ]);

            if ($st->rowCount() < 1) {
                $this->json([
                    'ok'    => false,
                    'error' => 'LTA not found for this organisation',
                ], 404);
                return;
            }

            $this->json([
                'ok'     => true,
                'status' => $status,
                'id'     => $id,
            ]);

        } catch (Throwable $e) {
            $this->oops('LTA status update failed', $e);
            $this->json([
                'ok'    => false,
                'error' => 'Failed to update status',
            ], 500);
        }
    }
}