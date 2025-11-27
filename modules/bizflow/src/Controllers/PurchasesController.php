<?php
declare(strict_types=1);

namespace Modules\BizFlow\Controllers;

use PDO;
use Throwable;
use Dompdf\Dompdf;
use Dompdf\Options;
use DateTimeImmutable;


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
    private array $columnCache   = [];

    /** Small caches to avoid repeated lookups */
    private array $supplierCache = [];
    private array $itemCache     = [];
  
  
      /** Next purchase number for this org: PO-YYYY-00001 */
    private function nextPurchaseNo(PDO $pdo, int $orgId): string
    {
        $year = (new DateTimeImmutable('now'))->format('Y');

        $st = $pdo->prepare(
            "SELECT COALESCE(MAX(id), 0) + 1 AS seq
             FROM biz_purchase_orders
             WHERE org_id = ?"
        );
        $st->execute([$orgId]);
        $seq = (int)$st->fetchColumn();

        return sprintf('PO-%s-%05d', $year, $seq);
    }

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
                    $or[]     = 'p.po_no LIKE ?';
                    $params[] = $like;
                }

                // External / LC references
                if ($this->hasColumnLocal($colsPurch, 'external_ref')) {
                    $or[]     = 'p.external_ref LIKE ?';
                    $params[] = $like;
                }

                // Supplier name in header
                if ($this->hasColumnLocal($colsPurch, 'supplier_name')) {
                    $or[]     = 'p.supplier_name LIKE ?';
                    $params[] = $like;
                }

                // Supplier lookup table
                $joinSupForSearch = $hasSup && $this->hasColumnLocal($colsPurch, 'supplier_id');
                if ($joinSupForSearch) {
                    $or[]     = 's.name LIKE ?';
                    $params[] = $like;
                    $or[]     = 's.code LIKE ?';
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
                'is_inventory_impact',
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
                $joins[]  = 'LEFT JOIN biz_suppliers s ON s.id = p.supplier_id AND s.org_id = p.org_id';
                $select[] = 's.name AS supplier_name';
                $select[] = 's.code AS supplier_code';
            }

            /* ---------------------------
             * Receipt metrics (lines)
             * ------------------------- */
            $linesTable       = $this->purchaseLinesTable($pdo); // biz_purchase_order_lines
            $qtyOrderedExpr   = '0';
            $qtyReceivedExpr  = '0';

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
       /* =============================================================
     * GET /purchases/create
     * =========================================================== */
    public function create(?array $ctx = null): void
    {
        try {
            $c     = $this->ctx($ctx ?? []);
            $org   = $c['org'] ?? [];
            $base  = $c['module_base'] ?? '/apps/bizflow';
            $pdo   = $this->pdo();
            $orgId = $this->requireOrg();

            $today = (new DateTimeImmutable('now'))->format('Y-m-d');

            /* -----------------------------
             * Supplier master
             * --------------------------- */
            $hasSupplierMaster = $this->hasTable($pdo, 'biz_suppliers');
            $suppliers         = [];

            if ($hasSupplierMaster) {
                $sql = "
                    SELECT
                        id,
                        code,
                        name,
                        type,
                        is_active
                    FROM biz_suppliers
                    WHERE org_id = :org
                      AND (is_active = 1 OR is_active IS NULL)
                    ORDER BY name ASC
                    LIMIT 1000
                ";
                $st = $pdo->prepare($sql);
                $st->execute(['org' => $orgId]);
                $suppliers = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
            }

            /* -----------------------------
             * Item master
             * --------------------------- */
            $hasItemMaster = $this->hasTable($pdo, 'biz_items');
            $items         = [];

            if ($hasItemMaster) {
                $sql = "
                    SELECT
                        id,
                        code,
                        name,
                        unit,
                        uom_id,
                        item_type,
                        purchase_price,
                        sale_price,
                        description,
                        is_active
                    FROM biz_items
                    WHERE org_id = :org
                      AND (is_active = 1 OR is_active IS NULL)
                      AND (item_type = 'stock' OR item_type IS NULL)
                    ORDER BY name ASC
                    LIMIT 1000
                ";
                $st = $pdo->prepare($sql);
                $st->execute(['org' => $orgId]);
                $items = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
            }

            /* -----------------------------
             * Render view
             * --------------------------- */
            $this->view('purchases/create', [
                'title'              => 'New purchase',
                'org'                => $org,
                'module_base'        => $base,
                'today'              => $today,

                // masters
                'suppliers'          => $suppliers,
                'items'              => $items,
                'has_supplier_master'=> $hasSupplierMaster,
                'has_item_master'    => $hasItemMaster,
            ], 'shell');

        } catch (Throwable $e) {
            $this->oops('Purchase create failed', $e);
        }
    }

  
  	      /* ============================================================
     * STORE (create new purchase)
     *   POST /apps/bizflow/purchases
     *   POST /t/{slug}/apps/bizflow/purchases
     * ========================================================== */
    public function store(?array $ctx = null): void
    {
        $pdo = null;

        try {
            if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
                http_response_code(405);
                echo 'POST only';
                return;
            }

            // NOTE: we are **not** doing a strict CSRF check here.
            // Other BizFlow screens (like Items) also work without
            // an explicit hidden csrf field, so we follow that pattern.
            // If later you want, we can add csrfVerifyPostTenant()
            // once we know exactly how global CSRF is wired here.

            $c     = $this->ctx($ctx ?? []);
            $orgId = $this->requireOrg();
            $pdo   = $this->pdo();

            $base = $c['module_base'] ?? '/apps/bizflow';

            /* -----------------------------
             * Read + validate basic fields
             * --------------------------- */
            $supplierId = (int)($_POST['supplier_id'] ?? 0);
            $poNo       = trim((string)($_POST['po_no'] ?? ''));
            $date       = trim((string)($_POST['date'] ?? ''));
            $expected   = trim((string)($_POST['expected_date'] ?? '')) ?: null;
            $external   = trim((string)($_POST['external_ref'] ?? ''));
            $currency   = trim((string)($_POST['currency'] ?? 'BDT')) ?: 'BDT';

            $notes      = trim((string)($_POST['notes'] ?? ''));
            $termsGen   = trim((string)($_POST['terms_general'] ?? ''));
            $termsDel   = trim((string)($_POST['terms_delivery'] ?? ''));

            $purchaseType    = trim((string)($_POST['purchase_type'] ?? 'local'));
            $isInvImpact     = isset($_POST['is_inventory_impact']) ? 1 : 0;
            $isExpenseOnly   = isset($_POST['is_expense_only']) ? 1 : 0;
            $fxRate          = (float)($_POST['fx_rate'] ?? 1.0);

            $itemsPost = $_POST['items'] ?? [];
            if (!is_array($itemsPost)) {
                $itemsPost = [];
            }

            if ($supplierId <= 0 || $date === '') {
                http_response_code(422);
                echo 'Supplier and PO date are required.';
                return;
            }

            /* -----------------------------
             * Build line items from POST
             * --------------------------- */
            $lines    = [];
            $subtotal = 0.0;

            foreach ($itemsPost as $row) {
                if (!is_array($row)) {
                    continue;
                }

                $itemId   = isset($row['item_id']) && $row['item_id'] !== '' ? (int)$row['item_id'] : null;
                $uom      = trim((string)($row['uom'] ?? 'pcs')) ?: 'pcs';
                $desc     = trim((string)($row['description'] ?? ''));
                $qty      = (float)($row['qty_ordered'] ?? 0);
                $unit     = (float)($row['unit_price'] ?? 0);
                $invFlag  = isset($row['is_inventory_item']) ? 1 : 0;

                if ($qty <= 0 || $unit < 0) {
                    continue; // skip completely empty / invalid lines
                }

                $lineTotal = $qty * $unit;
                $subtotal += $lineTotal;

                $lines[] = [
                    'item_id'          => $itemId,
                    'unit'             => $uom,
                    'description'      => $desc,
                    'qty'              => $qty,
                    'unit_price'       => $unit,
                    'line_total'       => $lineTotal,
                    'is_inventory_item'=> $invFlag,
                ];
            }

            if (empty($lines)) {
                http_response_code(422);
                echo 'At least one valid line item is required.';
                return;
            }

            /* -----------------------------
             * Supplier name lookup
             * --------------------------- */
            $supplierName = 'Supplier #' . $supplierId;
            if ($this->hasTable($pdo, 'biz_suppliers')) {
                $st = $pdo->prepare(
                    "SELECT name
                       FROM biz_suppliers
                      WHERE org_id = :org AND id = :id
                      LIMIT 1"
                );
                $st->execute(['org' => $orgId, 'id' => $supplierId]);
                $sup = $st->fetch(PDO::FETCH_ASSOC);
                if ($sup && !empty($sup['name'])) {
                    $supplierName = (string)$sup['name'];
                }
            }

            /* -----------------------------
             * Auto-generate PO number if empty
             * Pattern: PO-YYYY-00001 per org
             * --------------------------- */
            if ($poNo === '') {
                $prefix = 'PO-' . date('Y') . '-';

                $st = $pdo->prepare(
                    "SELECT po_no
                       FROM biz_purchase_orders
                      WHERE org_id = :org
                        AND po_no LIKE :prefix
                      ORDER BY id DESC
                      LIMIT 1"
                );
                $st->execute([
                    'org'    => $orgId,
                    'prefix' => $prefix . '%',
                ]);

                $last = (string)$st->fetchColumn();
                $seq  = 1;
                if ($last !== '' && preg_match('/(\d+)\s*$/', $last, $m)) {
                    $seq = (int)$m[1] + 1;
                }
                $poNo = $prefix . str_pad((string)$seq, 5, '0', STR_PAD_LEFT);
            }

            /* -----------------------------
             * Totals
             * --------------------------- */
            $discountTotal = 0.0;
            $taxTotal      = 0.0;
            $shippingTotal = 0.0;
            $grandTotal    = $subtotal - $discountTotal + $taxTotal + $shippingTotal;

            /* -----------------------------
             * Meta JSON (long-lived fields)
             * --------------------------- */
            $meta = [
                'fx_rate'            => $fxRate,
                'purchase_type'      => $purchaseType,
                'is_inventory_impact'=> $isInvImpact,
                'is_expense_only'    => $isExpenseOnly,
                'terms_general'      => $termsGen,
                'terms_delivery'     => $termsDel,
            ];
            $metaJson = json_encode($meta, JSON_UNESCAPED_UNICODE);

            /* -----------------------------
             * Insert header + lines
             * --------------------------- */
            $pdo->beginTransaction();

            $sqlHeader = "
                INSERT INTO biz_purchase_orders
                (
                    org_id,
                    award_id,
                    quote_id,
                    po_no,
                    external_ref,
                    supplier_id,
                    supplier_name,
                    supplier_contact,
                    supplier_ref,
                    currency,
                    date,
                    expected_date,
                    subtotal,
                    discount_total,
                    tax_total,
                    shipping_total,
                    grand_total,
                    status,
                    notes,
                    meta_json
                )
                VALUES
                (
                    :org_id,
                    NULL,
                    NULL,
                    :po_no,
                    :external_ref,
                    :supplier_id,
                    :supplier_name,
                    NULL,
                    NULL,
                    :currency,
                    :date,
                    :expected_date,
                    :subtotal,
                    :discount_total,
                    :tax_total,
                    :shipping_total,
                    :grand_total,
                    :status,
                    :notes,
                    :meta_json
                )
            ";

            $sth = $pdo->prepare($sqlHeader);
            $sth->execute([
                'org_id'         => $orgId,
                'po_no'          => $poNo,
                'external_ref'   => $external,
                'supplier_id'    => $supplierId,
                'supplier_name'  => $supplierName,
                'currency'       => $currency,
                'date'           => $date,
                'expected_date'  => $expected,
                'subtotal'       => $subtotal,
                'discount_total' => $discountTotal,
                'tax_total'      => $taxTotal,
                'shipping_total' => $shippingTotal,
                'grand_total'    => $grandTotal,
                'status'         => 'draft',
                'notes'          => $notes,
                'meta_json'      => $metaJson,
            ]);

            $purchaseId = (int)$pdo->lastInsertId();

            // Preload item names/codes for masters
            $itemIds = [];
            foreach ($lines as $l) {
                if (!empty($l['item_id'])) {
                    $itemIds[] = (int)$l['item_id'];
                }
            }
            $itemIds = array_values(array_unique($itemIds));
            $itemMap = [];

            if (!empty($itemIds) && $this->hasTable($pdo, 'biz_items')) {
                $ph  = implode(',', array_fill(0, count($itemIds), '?'));
                $sql = "SELECT id, name, code
                          FROM biz_items
                         WHERE org_id = ?
                           AND id IN ($ph)";
                $st = $pdo->prepare($sql);
                $st->execute(array_merge([$orgId], $itemIds));
                foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $it) {
                    $itemMap[(int)$it['id']] = $it;
                }
            }

            $sqlLine = "
                INSERT INTO biz_purchase_order_lines
                (
                    org_id,
                    purchase_id,
                    award_line_id,
                    item_id,
                    item_name,
                    item_code,
                    description,
                    unit,
                    qty,
                    unit_price,
                    discount_pct,
                    line_total,
                    meta_json
                )
                VALUES
                (
                    :org_id,
                    :purchase_id,
                    NULL,
                    :item_id,
                    :item_name,
                    :item_code,
                    :description,
                    :unit,
                    :qty,
                    :unit_price,
                    :discount_pct,
                    :line_total,
                    :meta_json
                )
            ";

            $stLine = $pdo->prepare($sqlLine);

            foreach ($lines as $l) {
                $itemId   = $l['item_id'];
                $itemName = trim((string)$l['description']);
                $itemCode = null;

                if ($itemId && isset($itemMap[$itemId])) {
                    $itemName = (string)($itemMap[$itemId]['name'] ?? $itemName);
                    $itemCode = (string)($itemMap[$itemId]['code'] ?? '');
                    if ($itemCode === '') {
                        $itemCode = null;
                    }
                }

                if ($itemName === '') {
                    $itemName = 'Line item';
                }

                $metaLine = [
                    'is_inventory_item' => (int)$l['is_inventory_item'],
                ];

                $stLine->execute([
                    'org_id'       => $orgId,
                    'purchase_id'  => $purchaseId,
                    'item_id'      => $itemId,
                    'item_name'    => $itemName,
                    'item_code'    => $itemCode,
                    'description'  => $l['description'],
                    'unit'         => $l['unit'],
                    'qty'          => $l['qty'],
                    'unit_price'   => $l['unit_price'],
                    'discount_pct' => 0.0,
                    'line_total'   => $l['line_total'],
                    'meta_json'    => json_encode($metaLine, JSON_UNESCAPED_UNICODE),
                ]);
            }

            $pdo->commit();

            // Redirect to the new purchase detail page
            $this->redirect(rtrim($base, '/') . '/purchases/' . $purchaseId);

        } catch (Throwable $e) {
            if ($pdo instanceof PDO && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $this->oops('Purchase store failed', $e);
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
                $joins[]  = 'LEFT JOIN biz_suppliers s ON s.org_id = p.org_id AND s.id = p.supplier_id';
                $select[] = 's.name AS supplier_name';
                $select[] = 's.code AS supplier_code';
                $select[] = 's.type AS supplier_type';
            }

            if ($this->hasColumnLocal($colsPurch, 'created_by')) {
                $joins[]  = 'LEFT JOIN cp_users cu ON cu.id = p.created_by';
                $select[] = 'cu.name AS created_by_name';
            }
            if ($this->hasColumnLocal($colsPurch, 'approved_by')) {
                $joins[]  = 'LEFT JOIN cp_users au ON au.id = p.approved_by';
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

                    /* 4) Inventory events (optional, schema-safe) */
        $inventoryEvents = [];
        if ($this->hasTable($pdo, 'biz_inventory_moves')) {
            // Check what columns actually exist on this database
            $colsMoves     = $this->tableColumns($pdo, 'biz_inventory_moves');
            $hasSourceCols = $this->hasColumnLocal($colsMoves, 'source_type')
                           && $this->hasColumnLocal($colsMoves, 'source_id');

            // Only run the filtered query if both columns exist
            if ($hasSourceCols) {
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
            // If source_type/source_id are missing (older schema), we simply
            // leave $inventoryEvents as [] so the page still renders cleanly.
        }

            $titleNo = $purchase['po_no'] ?? ('#' . $purchase['id']);

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
 * PRINT (HTML, clean A4 page, auto window.print)
 *   GET /apps/bizflow/purchases/{id}/print
 *   GET /t/{slug}/apps/bizflow/purchases/{id}/print
 * ========================================================== */
public function print(?array $ctx, int $id): void
{
    try {
        $id = (int)$id;
        if ($id <= 0) {
            http_response_code(400);
            echo 'Invalid purchase id.';
            return;
        }

        // Use existing helpers (no ->base())
        $c     = $this->ctx($ctx ?? []);
        $orgId = $this->requireOrg();
        $pdo   = $this->pdo();

        // Unified loader: header + lines (with meta_json fallback)
        [$purchase, $lines] = $this->loadPurchaseForPdf($pdo, $orgId, $id);
        if (!$purchase) {
            http_response_code(404);
            echo 'Purchase not found.';
            return;
        }

        $org         = (array)($c['org'] ?? []);
        $module_base = $c['module_base'] ?? '/apps/bizflow';

        // Identity from JSON settings (with cp_organizations fallback)
        $identityValues = $this->currentIdentityValuesForOrg($orgId, $org);
        $identity = [
            'name'    => $identityValues['name']    ?? '',
            'address' => $identityValues['address'] ?? '',
            'phone'   => $identityValues['phone']   ?? '',
            'email'   => $identityValues['email']   ?? '',
        ];

        // Logo as data: URL if available, else web URL, else default
        $logoInfo = $this->currentLogoInfoForOrg($orgId);
        $logoUrl  = $logoInfo['data_url']
            ?? ($logoInfo['url'] ?? '/assets/brand/logo.png');

        $logo = ['url' => $logoUrl];

        // Raw print template (NO shell, like invoices)
        $viewFile = dirname(__DIR__, 2) . '/Views/purchases/print.php';
        if (!is_file($viewFile)) {
            http_response_code(500);
            echo 'Purchase print template (Views/purchases/print.php) not found.';
            return;
        }

        // Template accepts $items or $lines; give it $items
        $items = $lines;

        /** @var array  $org */
        /** @var array  $purchase */
        /** @var array  $items */
        /** @var array  $identity */
        /** @var array  $logo */
        /** @var string $module_base */
        require $viewFile;

    } catch (\Throwable $e) {
        $this->oops('Purchase print failed', $e);
    }
}

/* ============================================================
 * PDF DOWNLOAD (Dompdf)
 *   GET /apps/bizflow/purchases/{id}/pdf
 *   GET /t/{slug}/apps/bizflow/purchases/{id}/pdf
 * ========================================================== */
public function pdf(?array $ctx = null, int $id = 0): void
{
    $id = (int)$id;
    if ($id <= 0) {
        http_response_code(400);
        echo 'Invalid purchase id.';
        return;
    }

    try {
        // Ensure Dompdf is really available
        if (!class_exists(Dompdf::class) || !class_exists(Options::class)) {
            http_response_code(500);
            echo 'PDF engine not configured. Install "dompdf/dompdf" and require vendor/autoload.php.';
            return;
        }

        $c     = $this->ctx($ctx ?? []);
        $orgId = $this->requireOrg();
        $pdo   = $this->pdo();

        [$purchase, $lines] = $this->loadPurchaseForPdf($pdo, $orgId, $id);
        if (!$purchase) {
            http_response_code(404);
            echo 'Purchase not found.';
            return;
        }

        $org         = (array)($c['org'] ?? []);
        $module_base = $c['module_base'] ?? '/apps/bizflow';

        // Identity + logo same as print()
        $identityValues = $this->currentIdentityValuesForOrg($orgId, $org);
        $identity = [
            'name'    => $identityValues['name']    ?? '',
            'address' => $identityValues['address'] ?? '',
            'phone'   => $identityValues['phone']   ?? '',
            'email'   => $identityValues['email']   ?? '',
        ];

        $logoInfo = $this->currentLogoInfoForOrg($orgId);
        $logoUrl  = $logoInfo['data_url']
            ?? ($logoInfo['url'] ?? '/assets/brand/logo.png');

        $logo = ['url' => $logoUrl];

        // Render raw PDF layout (no shell) to string
        $viewFile = dirname(__DIR__, 2) . '/Views/purchases/pdf.php';
        if (!is_file($viewFile)) {
            http_response_code(500);
            echo 'Purchase PDF template (Views/purchases/pdf.php) not found.';
            return;
        }

        $viewData = [
            'org'         => $org,
            'module_base' => $module_base,
            'purchase'    => $purchase,
            'lines'       => $lines,
            'identity'    => $identity,
            'logo'        => $logo,
        ];

        $html = $this->renderViewToString($viewFile, $viewData);

        // Dompdf options
        $options = new Options();
        $options->set('isRemoteEnabled', true);
        $options->set('isHtml5ParserEnabled', true);
        $options->set('defaultFont', 'DejaVu Sans');

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        $poNo     = trim((string)($purchase['po_no'] ?? ''));
        $fileName = $poNo !== '' ? $poNo : ('purchase-' . $purchase['id']);
        $fileName .= '.pdf';

        if (!headers_sent()) {
            header('Content-Type: application/pdf');
            header('Content-Disposition: attachment; filename="' . $fileName . '"');
            header('Content-Transfer-Encoding: binary');
            header('Cache-Control: private, must-revalidate');
            header('Pragma: public');
        }

        echo $dompdf->output();
        exit;

    } catch (Throwable $e) {
        $this->oops('Purchase PDF download failed', $e);
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
     * Helpers reused from Quotes (logo + identity + meta lines)
     * ========================================================== */

        /** Simple CSRF check against $_SESSION['csrf'] used in the form */
    private function verifyCsrfToken(): bool
    {
        if (\PHP_SESSION_ACTIVE !== \session_status()) {
            @\session_start();
        }

        $posted = (string)($_POST['csrf'] ?? '');
        $sess   = (string)($_SESSION['csrf'] ?? '');

        if ($posted === '' || $sess === '') {
            return false;
        }

        return hash_equals($sess, $posted);
    }

    /** Next PO number for this org: PO-YYYY-00001 (similar to QuotesController::nextQuoteNo) */
    private function nextPoNo(PDO $pdo, int $orgId): string
    {
        $year = (new DateTimeImmutable('now'))->format('Y');

        $st = $pdo->prepare(
            "SELECT COALESCE(MAX(id), 0) + 1 AS seq
               FROM biz_purchase_orders
              WHERE org_id = ?"
        );
        $st->execute([$orgId]);
        $seq = (int)$st->fetchColumn();

        return sprintf('PO-%s-%05d', $year, $seq);
    }

    /**
     * Load supplier header info for purchase (name / contact / reference).
     *
     * Returns:
     *  [
     *    'supplier_name'    => string,
     *    'supplier_contact' => ?string,
     *    'supplier_ref'     => ?string,
     *  ]
     */
    private function loadSupplierHeaderInfo(PDO $pdo, int $orgId, int $supplierId): array
    {
        $key = $orgId . ':' . $supplierId;
        if (isset($this->supplierCache[$key])) {
            return $this->supplierCache[$key];
        }

        $info = [
            'supplier_name'    => 'Supplier #' . $supplierId,
            'supplier_contact' => null,
            'supplier_ref'     => null,
        ];

        try {
            $st = $pdo->prepare(
                "SELECT *
                   FROM biz_suppliers
                  WHERE org_id = ?
                    AND id     = ?
                  LIMIT 1"
            );
            $st->execute([$orgId, $supplierId]);
            $sup = $st->fetch(PDO::FETCH_ASSOC);

            if ($sup) {
                $name = trim((string)($sup['name'] ?? ''));
                if ($name !== '') {
                    $info['supplier_name'] = $name;
                }

                $contact = trim((string)(
                    $sup['contact_name']
                    ?? $sup['contact_person']
                    ?? $sup['phone']
                    ?? ''
                ));
                if ($contact !== '') {
                    $info['supplier_contact'] = $contact;
                }

                $ref = trim((string)(
                    $sup['reference']
                    ?? $sup['ref_no']
                    ?? $sup['code']
                    ?? ''
                ));
                if ($ref !== '') {
                    $info['supplier_ref'] = $ref;
                }
            }
        } catch (Throwable $e) {
            // fail soft – keep defaults
        }

        return $this->supplierCache[$key] = $info;
    }

    /**
     * Load item name + code for line items, with small cache.
     *
     * @return array{item_name:string,item_code:?string}
     */
    private function loadItemInfo(PDO $pdo, int $orgId, int $itemId, int $lineNo): array
    {
        $key = $orgId . ':' . $itemId;
        if (isset($this->itemCache[$key])) {
            return $this->itemCache[$key];
        }

        $info = [
            'item_name' => 'Line ' . $lineNo,
            'item_code' => null,
        ];

        try {
            $st = $pdo->prepare(
                "SELECT name, code
                   FROM biz_items
                  WHERE org_id = ?
                    AND id     = ?
                  LIMIT 1"
            );
            $st->execute([$orgId, $itemId]);
            $it = $st->fetch(PDO::FETCH_ASSOC);

            if ($it) {
                $name = trim((string)($it['name'] ?? ''));
                if ($name !== '') {
                    $info['item_name'] = $name;
                }

                $code = trim((string)($it['code'] ?? ''));
                if ($code !== '') {
                    $info['item_code'] = $code;
                }
            }
        } catch (Throwable $e) {
            // ignore, keep defaults
        }

        return $this->itemCache[$key] = $info;
    }
  
  
  
  
  /**
     * Build purchase line items from meta_json->rows when DB lines are empty.
     *
     * @param string|null $metaJson
     * @return array<int,array<string,mixed>>
     */
    private function buildLinesFromMetaJson(?string $metaJson): array
    {
        if ($metaJson === null || $metaJson === '') {
            return [];
        }

        $decoded = json_decode((string)$metaJson, true);
        if (!is_array($decoded)) {
            return [];
        }

        // allow either 'rows' or 'lines'
        $rows = $decoded['rows'] ?? ($decoded['lines'] ?? null);
        if (!is_array($rows)) {
            return [];
        }

        $lines = [];

        foreach ($rows as $i => $row) {
            if (!is_array($row)) {
                continue;
            }

            $lines[] = [
                'line_no'     => $row['line_no']     ?? ($i + 1),
                'item_name'   => $row['item_name']
                    ?? ($row['name'] ?? ($row['product_name'] ?? '')),
                'item_code'   => $row['item_code']
                    ?? ($row['code'] ?? ($row['product_code'] ?? '')),
                'description' => $row['description'] ?? '',
                'qty'         => (float)($row['qty'] ?? 0),
                'unit'        => (string)($row['unit'] ?? 'pcs'),
                'unit_price'  => (float)($row['unit_price'] ?? 0),
                'discount_pct'=> (float)($row['discount_pct'] ?? 0),
                // frontend may use "total" or "line_total"
                'line_total'  => (float)($row['line_total'] ?? ($row['total'] ?? 0)),
            ];
        }

        return $lines;
    }

    /**
     * Simple helper to render a raw PHP view file (no shell) to string.
     */
    private function renderViewToString(string $file, array $data): string
    {
        extract($data, EXTR_SKIP);
        ob_start();
        require $file;
        return (string)ob_get_clean();
    }

        /* -------------------------------------------------------------
     * Shared helpers (logo + identity) reused from Quotes/Invoices
     * ----------------------------------------------------------- */

    /** Base dir where all logos live: modules/bizflow/Assets/brand/logo */
    private function logoBaseDir(): string
    {
        return dirname(__DIR__, 2) . '/Assets/brand/logo';
    }

    /** Base dir where per-tenant identity JSON lives */
    private function identityBaseDir(): string
    {
        return dirname(__DIR__, 2) . '/Assets/settings';
    }

    /** Ensure a directory exists (used by logo + identity) */
    private function ensureDir(string $dir): void
    {
        if (!is_dir($dir)) {
            @mkdir($dir, 0770, true);
        }
    }

    /**
     * Find current logo for this org.
     *
     * Returns:
     * [
     *   'dir'      => filesystem dir,
     *   'path'     => full filesystem path to logo.* or null,
     *   'url'      => web URL (/modules/…/logo.ext) or null,
     *   'data_url' => base64 data: URL for inline use (print/PDF), or null,
     *   'exists'   => bool
     * ]
     */
    private function currentLogoInfoForOrg(int $orgId): array
    {
        $baseDir = $this->logoBaseDir();
        $orgKey  = 'org_' . $orgId;
        $dir     = $baseDir . '/' . $orgKey;

        $this->ensureDir($dir);

        $candidates = ['logo.png', 'logo.jpg', 'logo.jpeg', 'logo.webp', 'logo.svg'];

        $filePath = null;
        $fileUrl  = null;
        $dataUrl  = null;

        foreach ($candidates as $file) {
            $p = $dir . '/' . $file;
            if (is_file($p)) {
                $filePath = $p;

                // HTTP path (if /modules is web-reachable)
                $fileUrl  = '/modules/bizflow/Assets/brand/logo/' . $orgKey . '/' . $file;

                // Also build a data: URL so print/PDF works even if /modules is not public
                $ext  = strtolower(pathinfo($file, PATHINFO_EXTENSION));
                $mime = 'image/png';
                if ($ext === 'jpg' || $ext === 'jpeg') {
                    $mime = 'image/jpeg';
                } elseif ($ext === 'webp') {
                    $mime = 'image/webp';
                } elseif ($ext === 'svg') {
                    $mime = 'image/svg+xml';
                }

                $raw = @file_get_contents($p);
                if ($raw !== false) {
                    $dataUrl = 'data:' . $mime . ';base64,' . base64_encode($raw);
                }

                break;
            }
        }

        return [
            'dir'      => $dir,
            'path'     => $filePath,
            'url'      => $fileUrl,
            'data_url' => $dataUrl,
            'exists'   => $filePath !== null,
        ];
    }

    /**
     * Load identity (name, address, phone, email) for this org from JSON,
     * falling back to cp_organizations values if JSON is missing/partial.
     */
    private function currentIdentityValuesForOrg(int $orgId, array $org): array
    {
        $baseDir = $this->identityBaseDir();
        $orgKey  = 'org_' . $orgId;
        $dir     = $baseDir . '/' . $orgKey;

        $this->ensureDir($dir);

        $file   = $dir . '/identity.json';
        $values = [
            'name'    => trim((string)($org['name'] ?? '')),
            'address' => trim((string)($org['address'] ?? '')),
            'phone'   => trim((string)($org['phone'] ?? '')),
            'email'   => trim((string)($org['email'] ?? '')),
        ];

        if (is_file($file)) {
            $raw  = @file_get_contents($file);
            $data = json_decode((string)$raw, true);
            if (is_array($data)) {
                foreach (['name', 'address', 'phone', 'email'] as $k) {
                    if (isset($data[$k]) && is_string($data[$k]) && $data[$k] !== '') {
                        $values[$k] = $data[$k];
                    }
                }
            }
        }

        return $values;
    }

    /* ============================================================
     * Local helpers (table / column detection + loader)
     * ========================================================== */

    private function loadPurchaseForPdf(PDO $pdo, int $orgId, int $id): array
    {
        $headerTable = $this->purchaseHeaderTable($pdo); // 'biz_purchase_orders'
        $colsPurch   = $this->tableColumns($pdo, $headerTable);
        $hasSup      = $this->hasTable($pdo, 'biz_suppliers');

        // ---- header select (similar to show()) ----
        $select = ['p.*'];
        $joins  = [];

        if (
            $hasSup &&
            $this->hasColumnLocal($colsPurch, 'supplier_id') &&
            !$this->hasColumnLocal($colsPurch, 'supplier_name')
        ) {
            $joins[]  = 'LEFT JOIN biz_suppliers s ON s.org_id = p.org_id AND s.id = p.supplier_id';
            $select[] = 's.name AS supplier_name';
            $select[] = 's.code AS supplier_code';
            $select[] = 's.type AS supplier_type';
        }

        if ($this->hasColumnLocal($colsPurch, 'created_by')) {
            $joins[]  = 'LEFT JOIN cp_users cu ON cu.id = p.created_by';
            $select[] = 'cu.name AS created_by_name';
        }
        if ($this->hasColumnLocal($colsPurch, 'approved_by')) {
            $joins[]  = 'LEFT JOIN cp_users au ON au.id = p.approved_by';
            $select[] = 'au.name AS approved_by_name';
        }

        $sql = "
            SELECT
                " . implode(",\n                ", $select) . "
            FROM `{$headerTable}` p
            " . implode("\n            ", $joins) . "
            WHERE p.org_id = ?
              AND p.id     = ?
            LIMIT 1
        ";

        $purchase = $this->row($sql, [$orgId, $id]);
        if (!$purchase) {
            return [null, []];
        }

        // ---- lines (similar to show()) ----
        $linesTable = $this->purchaseLinesTable($pdo); // 'biz_purchase_order_lines'
        $items      = [];

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

        // Fallback: meta_json->rows for very early purchases
        if (!$items && isset($purchase['meta_json']) && is_string($purchase['meta_json'])) {
            $items = $this->buildLinesFromMetaJson($purchase['meta_json']);
        }

        // Derive basic subtotal if header is missing it
        $subtotal = $purchase['subtotal'] ?? null;
        if ($subtotal === null) {
            $sub = 0.0;
            foreach ($items as $line) {
                $sub += (float)($line['line_total'] ?? 0);
            }
            $purchase['subtotal'] = $sub;
        }

        return [$purchase, $items];
    }

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
    $sql = "SELECT 1
            FROM information_schema.tables
            WHERE table_schema = DATABASE()
              AND table_name = :t
            LIMIT 1";

    try {
        $st = $pdo->prepare($sql);
        $st->execute(['t' => $table]);
        return (bool)$st->fetchColumn();
    } catch (\Throwable $e) {
        // If anything goes wrong, fail safe and say "no"
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