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
     * Same pattern as other BizFlow controllers.
     */
    private function base(?array $ctx = null): array
    {
        $c = $this->ctx($ctx ?? []);

        $orgId = $this->requireOrg();
        $pdo   = $this->pdo();

        $moduleBase = $c['module_base'] ?? (
            isset($c['org']) ? $this->moduleBase($c['org']) : '/apps/bizflow'
        );

        return [$c, $orgId, $pdo, $moduleBase];
    }

    /** Safe table existence check */
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

    /** Status → label */
    private function statusLabel(string $status): string
    {
        return match (strtolower($status)) {
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
        // Normal base context (same style as InvoicesController)
        [$c, $orgId, $pdo, $moduleBase] = $this->base($ctx);

        // ------- Filters from query string -------
        $q          = trim((string)($_GET['q'] ?? ''));
        $status     = trim((string)($_GET['status'] ?? ''));
        $activeOnly = isset($_GET['active_only']) && $_GET['active_only'] === '1';

        // We keep the parameter name "supplier" for now because your view
        // already uses ?supplier=... — but in meaning this is the CUSTOMER who issued the LTA
        $supplierQ  = trim((string)($_GET['supplier'] ?? ''));

        $where  = ['l.org_id = ?'];
        $params = [$orgId];

        // Text search: LTA no, title, customer name
        if ($q !== '') {
            $like = '%' . $q . '%';
            $where[] = '(l.lta_no LIKE ? OR l.title LIKE ? OR c.name LIKE ?)';
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }

        // "Supplier" filter (really CUSTOMER name now)
        if ($supplierQ !== '') {
            $where[] = 'c.name LIKE ?';
            $params[] = '%' . $supplierQ . '%';
        }

        // Status filter
        if ($status !== '') {
            $where[] = 'l.status = ?';
            $params[] = $status;
        }

        // Active only checkbox
        if ($activeOnly) {
            $where[] = "l.status = 'active'";
        }

        // ------- Main query -------
        // Note:
        //  - We join biz_customers as the awarding customer
        //  - We alias ceiling_total/used_total as max_value/used_value
        //    so your existing index view can keep using $lta['max_value'] / $lta['used_value']
        $sql =
            "SELECT
                 l.*,
                 c.name AS customer_name,
                 c.code AS customer_code,
                 l.ceiling_total AS max_value,
                 l.used_total    AS used_value
               FROM biz_ltas l
          LEFT JOIN biz_customers c
                 ON c.org_id = l.org_id
                AND c.id     = l.customer_id
              WHERE " . implode(' AND ', $where) . "
              ORDER BY l.start_date DESC, l.id DESC
              LIMIT 100";

        $ltas = $this->rows($sql, $params);

        // ------- Render via BizFlow shell -------
        $this->view('ltas/index', [
            'title'       => 'Framework contracts overview',
            'module_base' => $moduleBase,
            'org'         => $c['org'] ?? [],
            'filters'     => [
                'q'           => $q,
                'status'      => $status,
                'active_only' => $activeOnly,
                // keep key name "supplier" because your view uses $filters['supplier']
                'supplier'    => $supplierQ,
            ],
            'ltas'        => $ltas,
        ], 'shell');

    } catch (Throwable $e) {
        $this->oops('LTA index failed', $e);
    }
}

    /* =============================================================
     * GET /ltas/create → new LTA form
     * (UI is already working; just ensure shell + suppliers)
     * =========================================================== */
    public function create(?array $ctx = null): void
    {
        try {
            [$c, $orgId, $pdo, $moduleBase] = $this->base($ctx);

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

            $today  = (new DateTimeImmutable('now'))->format('Y-m-d');
            $ltaNoPlaceholder = 'LTA-'.date('Y').'-XXXXX';

            $this->view('ltas/create', [
                'title'       => 'New long-term agreement',
                'org'         => $c['org'] ?? [],
                'module_base' => $moduleBase,
                'suppliers'   => $suppliers,
                'today'       => $today,
                'lta_no'      => $ltaNoPlaceholder,
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

            $c      = $this->ctx($ctx ?? []);
            $pdo    = $this->pdo();
            $base   = rtrim((string)($c['module_base'] ?? '/apps/bizflow'), '/');
            $orgId  = $this->requireOrg();

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

            $meta = [
                'framework_type'  => $framework,
                'customer_name'   => trim((string)($_POST['customer_name'] ?? '')),
                'calloff_policy'  => trim((string)($_POST['calloff_policy'] ?? '')),
                'notes'           => trim((string)($_POST['notes'] ?? '')),
            ];
            $metaJson = json_encode($meta, JSON_UNESCAPED_UNICODE) ?: null;

            // Generate LTA number: LTA-YYYY-00001
            $dateForNo = $startDate !== '' ? $startDate : (new DateTimeImmutable('now'))->format('Y-m-d');
            $year      = substr($dateForNo, 0, 4);
            if (!ctype_digit($year)) {
                $year = (new DateTimeImmutable('now'))->format('Y');
            }
            $prefix = 'LTA-'.$year.'-';

            $last = $this->val(
                "SELECT lta_no
                   FROM biz_ltas
                  WHERE org_id = ? AND lta_no LIKE ?
                  ORDER BY lta_no DESC
                  LIMIT 1",
                [$orgId, $prefix.'%']
            );

            $next = 1;
            if ($last && preg_match('/^'.preg_quote($prefix, '/').'(\d{5})$/', (string)$last, $m)) {
                $next = (int)$m[1] + 1;
            }
            $ltaNo = $prefix.str_pad((string)$next, 5, '0', STR_PAD_LEFT);

            $sql = "INSERT INTO biz_ltas (
                        org_id,
                        supplier_id,
                        award_id,
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
                        :award_id,
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
                'award_id'      => null,
                'lta_no'        => $ltaNo,
                'title'         => $title,
                'reference_no'  => $refNo !== '' ? $refNo : null,
                'start_date'    => $startDate !== '' ? $startDate : (new DateTimeImmutable('now'))->format('Y-m-d'),
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
            $redirect = $base.'/ltas/'.$id;

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
        $id = (int)$id;

        if ($id <= 0) {
            http_response_code(400);
            echo 'Invalid LTA id.';
            return;
        }

        if (!$this->hasTable($pdo, 'biz_ltas')) {
            http_response_code(404);
            echo 'LTA storage not found.';
            return;
        }

        /* ---------------------------------------------------------
         * 1) LTA header + issuing customer
         * ------------------------------------------------------- */
        $lta = $this->row(
            "SELECT
                 l.*,
                 cust.name AS customer_name,
                 cust.code AS customer_code
               FROM biz_ltas l
          LEFT JOIN biz_customers cust
                 ON cust.org_id = l.org_id
                AND cust.id     = l.customer_id
              WHERE l.org_id = ?
                AND l.id     = ?
              LIMIT 1",
            [$orgId, $id]
        );

        if (!$lta) {
            http_response_code(404);
            echo 'LTA not found.';
            return;
        }

        $lta['status_label'] = $this->statusLabel((string)($lta['status'] ?? 'draft'));

        // Meta
        $meta = [];
        if (!empty($lta['meta_json'])) {
            $tmp = json_decode((string)$lta['meta_json'], true);
            if (is_array($tmp)) {
                $meta = $tmp;
            }
        }

        /* ---------------------------------------------------------
         * 2) Items
         * ------------------------------------------------------- */
        $items = [];
        if ($this->hasTable($pdo, 'biz_lta_items')) {
            $items = $this->rows(
                "SELECT
                     li.*,
                     itm.name AS item_name_master,
                     itm.code AS item_code_master
                   FROM biz_lta_items li
              LEFT JOIN biz_items itm
                     ON itm.org_id = li.org_id
                    AND itm.id     = li.item_id
                  WHERE li.org_id = ?
                    AND li.lta_id = ?
                  ORDER BY li.line_no, li.id",
                [$orgId, $id]
            );
        }

        /* ---------------------------------------------------------
         * 3) Call-off summary (overall + per item)
         * ------------------------------------------------------- */
        $calloffSummary = [
            'total_calloffs' => 0,
            'po_count'       => 0,
            'invoice_count'  => 0,
            'qty_total'      => 0.0,
            'amount_total'   => 0.0,
        ];
        $calloffsByItem = [];

        if ($this->hasTable($pdo, 'biz_lta_calloffs')) {
            $rows = $this->rows(
                "SELECT
                     lc.lta_item_id,
                     lc.source_kind,
                     COUNT(*)                      AS calloff_count,
                     COALESCE(SUM(lc.qty), 0)      AS qty_sum,
                     COALESCE(SUM(lc.amount), 0.0) AS amount_sum
                   FROM biz_lta_calloffs lc
                  WHERE lc.org_id = ?
                    AND lc.lta_id = ?
                  GROUP BY lc.lta_item_id, lc.source_kind",
                [$orgId, $id]
            );

            foreach ($rows as $r) {
                $itemId = (int)($r['lta_item_id'] ?? 0);
                $kind   = (string)($r['source_kind'] ?? '');
                $count  = (int)($r['calloff_count'] ?? 0);
                $qty    = (float)($r['qty_sum'] ?? 0);
                $amt    = (float)($r['amount_sum'] ?? 0);

                // global summary
                $calloffSummary['total_calloffs'] += $count;
                $calloffSummary['qty_total']      += $qty;
                $calloffSummary['amount_total']   += $amt;

                if ($kind === 'po') {
                    $calloffSummary['po_count'] += $count;
                } elseif ($kind === 'invoice') {
                    $calloffSummary['invoice_count'] += $count;
                }

                // per-item summary
                if (!isset($calloffsByItem[$itemId])) {
                    $calloffsByItem[$itemId] = [
                        'qty_total'     => 0.0,
                        'amount_total'  => 0.0,
                        'po_count'      => 0,
                        'invoice_count' => 0,
                    ];
                }

                $calloffsByItem[$itemId]['qty_total']    += $qty;
                $calloffsByItem[$itemId]['amount_total'] += $amt;

                if ($kind === 'po') {
                    $calloffsByItem[$itemId]['po_count'] += $count;
                } elseif ($kind === 'invoice') {
                    $calloffsByItem[$itemId]['invoice_count'] += $count;
                }
            }
        }

        /* ---------------------------------------------------------
         * 4) Orders (pending delivery vs served)
         *    via biz_lta_calloffs → biz_purchases
         * ------------------------------------------------------- */
        $orderStats = [
            'total_orders'            => 0,
            'served_orders'           => 0,
            'pending_delivery_orders' => 0,
            'total_order_amount'      => 0.0,
            'pending_delivery_amount' => 0.0,
        ];

        if ($this->hasTable($pdo, 'biz_lta_calloffs') && $this->hasTable($pdo, 'biz_purchases')) {
            $orders = $this->rows(
                "SELECT
                     p.id,
                     p.status,
                     COALESCE(SUM(lc.amount), 0.0) AS amount_sum
                   FROM biz_lta_calloffs lc
              INNER JOIN biz_purchases p
                     ON p.org_id = lc.org_id
                    AND p.id     = lc.source_id
                  WHERE lc.org_id     = ?
                    AND lc.lta_id     = ?
                    AND lc.source_kind = 'po'
                  GROUP BY p.id, p.status",
                [$orgId, $id]
            );

            $servedStatuses  = ['received', 'closed'];
            $pendingStatuses = ['draft', 'sent', 'confirmed', 'partial'];

            foreach ($orders as $o) {
                $orderStats['total_orders']++;
                $amount = (float)($o['amount_sum'] ?? 0);
                $orderStats['total_order_amount'] += $amount;

                $st = strtolower((string)($o['status'] ?? ''));

                if (in_array($st, $servedStatuses, true)) {
                    $orderStats['served_orders']++;
                } elseif (in_array($st, $pendingStatuses, true)) {
                    $orderStats['pending_delivery_orders']++;
                    $orderStats['pending_delivery_amount'] += $amount;
                }
            }
        }

        /* ---------------------------------------------------------
         * 5) Invoices (pending payment vs paid)
         *    via biz_lta_calloffs → biz_invoices
         * ------------------------------------------------------- */
        $invoiceStats = [
            'total_invoices'        => 0,
            'invoiced_amount'       => 0.0,
            'paid_amount'           => 0.0,
            'pending_payment_amount'=> 0.0,
            'pending_invoices'      => 0,
        ];

        if ($this->hasTable($pdo, 'biz_lta_calloffs') && $this->hasTable($pdo, 'biz_invoices')) {
            $invoices = $this->rows(
                "SELECT
                     inv.id,
                     inv.status,
                     COALESCE(SUM(lc.amount), 0.0) AS amount_sum
                   FROM biz_lta_calloffs lc
              INNER JOIN biz_invoices inv
                     ON inv.org_id = lc.org_id
                    AND inv.id     = lc.source_id
                  WHERE lc.org_id      = ?
                    AND lc.lta_id      = ?
                    AND lc.source_kind = 'invoice'
                  GROUP BY inv.id, inv.status",
                [$orgId, $id]
            );

            foreach ($invoices as $iv) {
                $invoiceStats['total_invoices']++;
                $amount = (float)($iv['amount_sum'] ?? 0);
                $invoiceStats['invoiced_amount'] += $amount;

                $st = strtolower((string)($iv['status'] ?? ''));

                if ($st === 'paid') {
                    $invoiceStats['paid_amount'] += $amount;
                } elseif (!in_array($st, ['cancelled', 'void'], true)) {
                    $invoiceStats['pending_invoices']++;
                    $invoiceStats['pending_payment_amount'] += $amount;
                }
            }
        }

        /* ---------------------------------------------------------
         * 6) Inventory snapshot per item (on-hand stock)
         *    via biz_inventory_moves
         * ------------------------------------------------------- */
        $inventoryByItem = [];

        if ($items && $this->hasTable($pdo, 'biz_inventory_moves')) {
            $itemIds = [];
            foreach ($items as $row) {
                $iid = (int)($row['item_id'] ?? 0);
                if ($iid > 0) {
                    $itemIds[$iid] = true;
                }
            }
            $itemIds = array_keys($itemIds);

            if ($itemIds) {
                // Build IN clause
                $placeholders = implode(',', array_fill(0, count($itemIds), '?'));
                $params       = array_merge([$orgId], $itemIds);

                $invRows = $this->rows(
                    "SELECT
                         m.item_id,
                         COALESCE(SUM(m.qty_in - m.qty_out), 0) AS stock_qty
                       FROM biz_inventory_moves m
                      WHERE m.org_id = ?
                        AND m.item_id IN ($placeholders)
                      GROUP BY m.item_id",
                    $params
                );

                foreach ($invRows as $r) {
                    $iid = (int)($r['item_id'] ?? 0);
                    $inventoryByItem[$iid] = (float)($r['stock_qty'] ?? 0);
                }
            }
        }

        /* ---------------------------------------------------------
         * 7) Render
         * ------------------------------------------------------- */
        $this->view('ltas/show', [
            'title'            => 'LTA details',
            'org'              => $c['org'] ?? [],
            'module_base'      => $moduleBase,
            'lta'              => $lta,
            'meta'             => $meta,
            'items'            => $items,
            'calloff_summary'  => $calloffSummary,
            'calloffs_by_item' => $calloffsByItem,
            'order_stats'      => $orderStats,
            'invoice_stats'    => $invoiceStats,
            'inventory'        => $inventoryByItem,
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
                      ORDER BY name
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
     * POST /ltas/{id}/update → update header only
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
            $meta['calloff_policy'] = trim((string)($_POST['calloff_policy'] ?? ($meta['calloff_policy'] ?? '')));
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

            $redirect = $moduleBase.'/ltas/'.$id;
            $this->json(['ok' => true, 'id' => $id, 'redirect' => $redirect]);

        } catch (Throwable $e) {
            $this->oops('LTA update failed', $e);
            $this->json(['ok' => false, 'error' => 'LTA update failed.'], 500);
        }
    }

    /* =============================================================
     * POST /ltas/{id}/status → quick pill change on index
     * =========================================================== */
    public function updateStatus(array $ctx, int $id): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            http_response_code(405);
            $this->json(['ok' => false, 'error' => 'Method not allowed'], 405);
            return;
        }

        $status = strtolower(trim($_POST['status'] ?? ''));

        $allowed = ['draft', 'active', 'suspended', 'closed', 'expired'];
        if (!in_array($status, $allowed, true)) {
            $this->json([
                'ok'    => false,
                'error' => 'Invalid status value',
                'value' => $status,
            ], 422);
            return;
        }

        $orgId = 0;
        if (isset($ctx['org_id'])) {
            $orgId = (int)$ctx['org_id'];
        } elseif (isset($ctx['org']['id'])) {
            $orgId = (int)$ctx['org']['id'];
        }

        if ($orgId <= 0) {
            $this->json(['ok' => false, 'error' => 'Missing organisation context'], 403);
            return;
        }

        $pdo = $this->pdo();

        if (!$this->hasTable($pdo, 'biz_ltas')) {
            $this->json(['ok' => false, 'error' => 'LTA storage not ready'], 500);
            return;
        }

        try {
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