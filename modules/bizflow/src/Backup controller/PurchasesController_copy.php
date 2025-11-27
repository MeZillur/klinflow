<?php
declare(strict_types=1);

namespace Modules\BizFlow\Controllers;

use PDO;
use Throwable;

/**
 * BizFlow PurchasesController
 *
 * Canonical schema:
 *   biz_purchase_orders       (header)
 *   biz_purchase_order_lines  (lines)
 *
 * Legacy biz_purchases / biz_purchase_items ar use korbo na.
 */
final class PurchasesController extends BaseController
{
    /** Cache for SHOW COLUMNS results */
    private array $columnCache = [];

    /* ============================================================
     * INDEX
     * ========================================================== */

    /**
     * GET /apps/bizflow/purchases
     * GET /t/{slug}/apps/bizflow/purchases
     */
    public function index(?array $ctx = null): void
    {
        try {
            $c     = $this->ctx($ctx ?? []);
            $orgId = $this->requireOrg();
            $pdo   = $this->pdo();

            // Canonical header table
            $headerTable = $this->purchaseHeaderTable($pdo); // 'biz_purchase_orders'
            $colsPurch   = $this->tableColumns($pdo, $headerTable);
            $hasSup      = $this->hasTable($pdo, 'biz_suppliers');

            $q         = trim((string)($_GET['q']      ?? ''));
            $status    = trim((string)($_GET['status'] ?? ''));
            $type      = trim((string)($_GET['type']   ?? ''));
            $invFilter = (string)($_GET['inv'] ?? ''); // '', 'inventory', 'no_inventory'

            $params = [$orgId];
            $where  = ['p.org_id = ?'];

            /* ---------------------------
             * Search filter
             * ------------------------- */
            if ($q !== '') {
                $like = '%' . $q . '%';
                $or   = [];

                // PO no
                if ($this->hasColumnLocal($colsPurch, 'po_no')) {
                    $or[]    = 'p.po_no LIKE ?';
                    $params[] = $like;
                }

                // External / LC references
                if ($this->hasColumnLocal($colsPurch, 'external_ref')) {
                    $or[]    = 'p.external_ref LIKE ?';
                    $params[] = $like;
                }

                // Supplier name in header
                if ($this->hasColumnLocal($colsPurch, 'supplier_name')) {
                    $or[]    = 'p.supplier_name LIKE ?';
                    $params[] = $like;
                }

                // Supplier lookup table
                $joinSupForSearch = $hasSup && $this->hasColumnLocal($colsPurch, 'supplier_id');
                if ($joinSupForSearch) {
                    $or[]    = 's.name LIKE ?';
                    $params[] = $like;
                    $or[]    = 's.code LIKE ?';
                    $params[] = $like;
                }

                if (!empty($or)) {
                    $where[] = '(' . implode(' OR ', $or) . ')';
                }
            }

            /* ---------------------------
             * Status filter
             * ------------------------- */
            if ($status !== '' && $status !== 'all' && $this->hasColumnLocal($colsPurch, 'status')) {
                $where[]  = 'p.status = ?';
                $params[] = $status;
            }

            /* ---------------------------
             * Purchase type filter
             * (if later you add purchase_type column)
             * ------------------------- */
            if ($type !== '' && $type !== 'all' && $this->hasColumnLocal($colsPurch, 'purchase_type')) {
                $where[]  = 'p.purchase_type = ?';
                $params[] = $type;
            }

            /* ---------------------------
             * Inventory impact filter
             * (if you add is_inventory_impact column later)
             * ------------------------- */
            if ($invFilter === 'inventory' && $this->hasColumnLocal($colsPurch, 'is_inventory_impact')) {
                $where[] = 'p.is_inventory_impact = 1';
            } elseif ($invFilter === 'no_inventory' && $this->hasColumnLocal($colsPurch, 'is_inventory_impact')) {
                $where[] = 'p.is_inventory_impact = 0';
            }

            /* ---------------------------
             * SELECT list
             * ------------------------- */
            $select = [
                'p.id',
                'p.org_id',
            ];

            $maybeCols = [
                'po_no',
                'award_id',
                'quote_id',
                'supplier_id',
                'supplier_name',
                'status',
                'date',
                'expected_date',
                'currency',
                'subtotal',
                'grand_total',
                'discount_total',
                'tax_total',
                'shipping_total',
                'external_ref',
                'created_at',
            ];
            foreach ($maybeCols as $col) {
                if ($this->hasColumnLocal($colsPurch, $col)) {
                    $select[] = "p.`{$col}`";
                }
            }

            // Supplier join (if we didn't already have supplier_name)
            $from   = "`{$headerTable}` p";
            $joins  = [];

            if (
                $hasSup &&
                $this->hasColumnLocal($colsPurch, 'supplier_id') &&
                !$this->hasColumnLocal($colsPurch, 'supplier_name')
            ) {
                $joins[] = 'LEFT JOIN biz_suppliers s ON s.id = p.supplier_id AND s.org_id = p.org_id';
                $select[] = 's.name AS supplier_name';
                $select[] = 's.code AS supplier_code';
            }

            /* ---------------------------
             * Receipt metrics (lines)
             * ------------------------- */
            $linesTable = $this->purchaseLinesTable($pdo); // biz_purchase_order_lines
            $qtyOrderedExpr  = '0';
            $qtyReceivedExpr = '0';

            if ($linesTable === 'biz_purchase_order_lines') {
                $colsLines = $this->tableColumns($pdo, $linesTable);

                if ($this->hasColumnLocal($colsLines, 'qty')) {
                    $qtyOrderedExpr = "
                        (SELECT COALESCE(SUM(l.qty),0)
                           FROM biz_purchase_order_lines l
                          WHERE l.org_id = p.org_id
                            AND l.purchase_id = p.id)
                    ";
                }
                if ($this->hasColumnLocal($colsLines, 'qty_received')) {
                    $qtyReceivedExpr = "
                        (SELECT COALESCE(SUM(l.qty_received),0)
                           FROM biz_purchase_order_lines l
                          WHERE l.org_id = p.org_id
                            AND l.purchase_id = p.id)
                    ";
                }
            }

            $select[] = "{$qtyOrderedExpr}  AS qty_ordered_total";
            $select[] = "{$qtyReceivedExpr} AS qty_received_total";

            /* ---------------------------
             * ORDER BY
             * ------------------------- */
            $orderParts = [];
            if ($this->hasColumnLocal($colsPurch, 'date')) {
                $orderParts[] = 'p.date DESC';
            } elseif ($this->hasColumnLocal($colsPurch, 'created_at')) {
                $orderParts[] = 'p.created_at DESC';
            }
            $orderParts[] = 'p.id DESC';

            $sql = "
                SELECT
                    " . implode(",\n                    ", $select) . "
                FROM {$from}
                " . implode("\n                ", $joins) . "
                WHERE " . implode(' AND ', $where) . "
                ORDER BY " . implode(', ', $orderParts) . "
                LIMIT 500
            ";

            $rows = $this->rows($sql, $params);

            /* ---------------------------
             * Header metrics
             * ------------------------- */
            $total            = count($rows);
            $open             = 0;
            $receiving        = 0;
            $noInventoryCount = 0;

            foreach ($rows as $r) {
                $st = (string)($r['status'] ?? '');

                if (in_array($st, [
                    'draft','approved','lc_open_pending','lc_opened',
                    'in_transit','receiving','partially_received'
                ], true)) {
                    $open++;
                }
                if (in_array($st, ['receiving','partially_received'], true)) {
                    $receiving++;
                }
                if ((int)($r['is_inventory_impact'] ?? 1) === 0) {
                    $noInventoryCount++;
                }
            }

            $this->view('purchases/index', [
                'title'            => 'Purchases',
                'org'              => $c['org'] ?? [],
                'module_base'      => $c['module_base'] ?? '/apps/bizflow',
                'purchases'        => $rows,
                'total_count'      => $total,
                'open_count'       => $open,
                'receiving_count'  => $receiving,
                'no_inventory_cnt' => $noInventoryCount,
                'search'           => $q,
                'filter_status'    => $status,
                'filter_type'      => $type,
                'filter_inv'       => $invFilter,
            ], 'shell');

        } catch (Throwable $e) {
            $this->oops('Purchases index failed', $e);
        }
    }

    /* ============================================================
     * CREATE SCREEN
     * ========================================================== */

    /**
     * GET /apps/bizflow/purchases/create
     * GET /t/{slug}/apps/bizflow/purchases/create
     */
    public function create(?array $ctx = null): void
    {
        try {
            $c     = $this->ctx($ctx ?? []);
            $orgId = $this->requireOrg();
            $pdo   = $this->pdo();

            // Fallback supplier list for simple <select>
            $suppliers = [];
            if ($this->hasTable($pdo, 'biz_suppliers')) {
                $suppliers = $this->rows(
                    "SELECT id, name, code, type
                       FROM biz_suppliers
                      WHERE org_id = ?
                      ORDER BY name
                      LIMIT 200",
                    [$orgId]
                );
            }

            $today = (new \DateTimeImmutable('now'))->format('Y-m-d');

            $this->view('purchases/create', [
                'title'       => 'New Purchase',
                'org'         => $c['org'] ?? [],
                'module_base' => $c['module_base'] ?? '/apps/bizflow',
                'suppliers'   => $suppliers,
                'today'       => $today,
            ], 'shell');

        } catch (Throwable $e) {
            $this->oops('Purchase create screen failed', $e);
        }
    }

    /* ============================================================
     * SHOW
     * ========================================================== */

    /**
     * GET /apps/bizflow/purchases/{id}
     * GET /t/{slug}/apps/bizflow/purchases/{id}
     */
    public function show(?array $ctx, int $id): void
    {
        try {
            $c     = $this->ctx($ctx ?? []);
            $orgId = $this->requireOrg();
            $pdo   = $this->pdo();

            $headerTable = $this->purchaseHeaderTable($pdo); // biz_purchase_orders
            $colsPurch   = $this->tableColumns($pdo, $headerTable);
            $hasSup      = $this->hasTable($pdo, 'biz_suppliers');

            /* 1) Purchase header + joins (supplier / users if columns exist) */
            $select = ['p.*'];
            $joins  = [];

            if (
                $hasSup &&
                $this->hasColumnLocal($colsPurch, 'supplier_id') &&
                !$this->hasColumnLocal($colsPurch, 'supplier_name')
            ) {
                $joins[] = 'LEFT JOIN biz_suppliers s ON s.org_id = p.org_id AND s.id = p.supplier_id';
                $select[] = 's.name AS supplier_name';
                $select[] = 's.code AS supplier_code';
                $select[] = 's.type AS supplier_type';
            }

            if ($this->hasColumnLocal($colsPurch, 'created_by')) {
                $joins[] = 'LEFT JOIN cp_users cu ON cu.id = p.created_by';
                $select[] = 'cu.name AS created_by_name';
            }
            if ($this->hasColumnLocal($colsPurch, 'approved_by')) {
                $joins[] = 'LEFT JOIN cp_users au ON au.id = p.approved_by';
                $select[] = 'au.name AS approved_by_name';
            }

            $sql = "
                SELECT
                    " . implode(",\n                    ", $select) . "
                FROM `{$headerTable}` p
                " . implode("\n                ", $joins) . "
                WHERE p.org_id = ?
                  AND p.id     = ?
                LIMIT 1
            ";

            $purchase = $this->row($sql, [$orgId, $id]);

            if (!$purchase) {
                http_response_code(404);
                echo 'Purchase not found.';
                return;
            }

            /* 2) Line items */
            $items      = [];
            $linesTable = $this->purchaseLinesTable($pdo); // biz_purchase_order_lines

            if ($linesTable === 'biz_purchase_order_lines') {
                $sqlItems = "
                    SELECT
                        l.*,
                        it.code AS item_code,
                        it.name AS item_name
                    FROM biz_purchase_order_lines l
                    LEFT JOIN biz_items it
                           ON it.org_id = l.org_id
                          AND it.id     = l.item_id
                    WHERE l.org_id      = ?
                      AND l.purchase_id = ?
                    ORDER BY l.id
                ";
                $items = $this->rows($sqlItems, [$orgId, $id]);
            }

            // derive totals if header doesn’t have them
            $qtyOrderedTotal  = (float)($purchase['qty_ordered_total']  ?? 0);
            $qtyReceivedTotal = (float)($purchase['qty_received_total'] ?? 0);
            $subtotal         = $purchase['subtotal'] ?? null;

            if ($qtyOrderedTotal === 0.0 || $qtyReceivedTotal === 0.0 || $subtotal === null) {
                $qO  = 0.0;
                $qR  = 0.0;
                $sub = 0.0;
                foreach ($items as $line) {
                    $qO  += (float)($line['qty']          ?? 0);
                    $qR  += (float)($line['qty_received'] ?? 0);
                    $sub += (float)($line['line_total']   ?? 0);
                }
                if ($qtyOrderedTotal === 0.0) {
                    $purchase['qty_ordered_total'] = $qO;
                }
                if ($qtyReceivedTotal === 0.0) {
                    $purchase['qty_received_total'] = $qR;
                }
                if ($subtotal === null) {
                    $purchase['subtotal'] = $sub;
                }
            }

            /* 3) GRNs for this purchase (optional) */
            $grns = [];
            if ($this->hasTable($pdo, 'biz_grns')) {
                $sqlGrn = "
                    SELECT
                        g.*,
                        w.code AS warehouse_code,
                        SUM(gi.qty_received) AS qty_total,
                        u.name AS posted_by_name
                    FROM biz_grns g
                    LEFT JOIN biz_grn_items gi
                           ON gi.org_id = g.org_id
                          AND gi.grn_id = g.id
                    LEFT JOIN biz_warehouses w
                           ON w.org_id = g.org_id
                          AND w.id     = g.warehouse_id
                    LEFT JOIN cp_users u
                           ON u.id = g.posted_by
                    WHERE g.org_id      = ?
                      AND g.purchase_id = ?
                    GROUP BY g.id
                    ORDER BY g.date, g.id
                ";
                $grns = $this->rows($sqlGrn, [$orgId, $id]);
            }

            /* 4) Inventory events (optional) */
            $inventoryEvents = [];
            if ($this->hasTable($pdo, 'biz_inventory_moves')) {
                $sqlInv = "
                    SELECT
                        m.*,
                        w.code AS warehouse_code,
                        it.name AS item_name,
                        it.code AS item_code
                    FROM biz_inventory_moves m
                    LEFT JOIN biz_warehouses w
                           ON w.org_id = m.org_id
                          AND w.id     = m.warehouse_id
                    LEFT JOIN biz_items it
                           ON it.org_id = m.org_id
                          AND it.id     = m.item_id
                    WHERE m.org_id      = ?
                      AND m.source_type = 'PURCHASE'
                      AND m.source_id   = ?
                    ORDER BY m.movement_date, m.id
                    LIMIT 500
                ";
                $inventoryEvents = $this->rows($sqlInv, [$orgId, $id]);
            }

            $titleNo = $purchase['po_no']
                ?? ('#' . $purchase['id']);

            $this->view('purchases/show', [
                'title'            => 'Purchase ' . $titleNo,
                'org'              => $c['org'] ?? [],
                'module_base'      => $c['module_base'] ?? '/apps/bizflow',
                'purchase'         => $purchase,
                'items'            => $items,
                'grns'             => $grns,
                'inventory_events' => $inventoryEvents,
            ], 'shell');

        } catch (Throwable $e) {
            $this->oops('Purchase show failed', $e);
        }
    }

      /* ============================================================
     * RECEIVE / GRN BRIDGE
     * ========================================================== */

    /**
     * GET /apps/bizflow/purchases/{id}/receive
     * GET /t/{slug}/apps/bizflow/purchases/{id}/receive
     *
     * Simple helper: redirect to GRN create screen for this purchase.
     */
    public function receive(?array $ctx, int $id): void
    {
        try {
            $c  = $this->ctx($ctx ?? []);
            $id = (int)$id;

            if ($id <= 0) {
                http_response_code(400);
                echo 'Invalid purchase id.';
                return;
            }

            // Same module_base used everywhere in BizFlow
            $base = $c['module_base'] ?? '/apps/bizflow';

            // GRN create page already exists and can read purchase_id from GET
            $url = rtrim($base, '/') . '/grn/create?purchase_id=' . $id;

            $this->redirect($url);

        } catch (Throwable $e) {
            $this->oops('Purchase receive redirect failed', $e);
        }
    }
  
  
    /* ============================================================
     * Local helpers (table / column detection)
     * ========================================================== */

    /**
     * Canonical header table for BizFlow purchases.
     */
    private function purchaseHeaderTable(PDO $pdo): ?string
    {
        // Hard-code canonical table to avoid flaky SHOW TABLES logic
        return 'biz_purchase_orders';
    }

    /**
     * Canonical lines table.
     */
    private function purchaseLinesTable(PDO $pdo): ?string
    {
        return 'biz_purchase_order_lines';
    }

    private function hasTable(PDO $pdo, string $table): bool
    {
        try {
            $stmt = $pdo->prepare('SHOW TABLES LIKE ?');
            $stmt->execute([$table]);
            return (bool)$stmt->fetchColumn();
        } catch (Throwable $e) {
            return false;
        }
    }

    /**
     * Return list of columns for a table (cached).
     */
    private function tableColumns(PDO $pdo, string $table): array
    {
        if (isset($this->columnCache[$table])) {
            return $this->columnCache[$table];
        }

        try {
            $stmt = $pdo->prepare("SHOW COLUMNS FROM `{$table}`");
            $stmt->execute();
            $cols = [];
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                if (isset($row['Field'])) {
                    $cols[] = $row['Field'];
                }
            }
            $this->columnCache[$table] = $cols;
            return $cols;
        } catch (Throwable $e) {
            $this->columnCache[$table] = [];
            return [];
        }
    }

    /**
     * Cheap helper that works off a pre-fetched column list.
     */
    private function hasColumnLocal(array $cols, string $name): bool
    {
        return in_array($name, $cols, true);
    }
}