<?php
declare(strict_types=1);

namespace Modules\BizFlow\Controllers;

use PDO;
use Throwable;
use DateTimeImmutable;
use Modules\BizFlow\Services\InventoryService;

final class GrnController extends BaseController
{
    private ?InventoryService $inventory = null;

    /** Simple cache for SHOW COLUMNS results */
    private array $columnCache = [];

    /* ============================================================
     * Low-level helpers
     * ============================================================ */

    private function inv(): InventoryService
    {
        // Manual fallback loader because Services namespace may not be autoloaded
        if (!$this->inventory instanceof InventoryService) {

            if (!class_exists(InventoryService::class, false)) {
                $path = __DIR__ . '/../Services/InventoryService.php';
                if (is_file($path)) {
                    require_once $path;
                }
            }

            $this->inventory = new InventoryService();
        }

        return $this->inventory;
    }

    private function hasTable(PDO $pdo, string $table): bool
    {
        // Safety: only allow simple identifiers
        if (!preg_match('/^[A-Za-z0-9_]+$/', $table)) {
            return false;
        }

        try {
            $sql  = "SHOW TABLES LIKE " . $pdo->quote($table);
            $stmt = $pdo->query($sql);
            return (bool)$stmt->fetchColumn();
        } catch (Throwable $e) {
            return false;
        }
    }

    /** Are both GRN tables present? */
    private function grnStorageReady(PDO $pdo): bool
    {
        return $this->hasTable($pdo, 'biz_grn')
            && $this->hasTable($pdo, 'biz_grn_items');
    }

    private function flash(string $msg): void
    {
        if (\PHP_SESSION_ACTIVE !== \session_status()) {
            @\session_start();
        }
        $_SESSION['bizflow_grn_flash'] = $msg;
    }

    /**
     * Generic column list for a table (cached).
     */
    private function tableColumns(PDO $pdo, string $table): array
    {
        if (isset($this->columnCache[$table])) {
            return $this->columnCache[$table];
        }

        $cols = [];

        try {
            if (!$this->hasTable($pdo, $table)) {
                $this->columnCache[$table] = [];
                return [];
            }

            $sql  = "SHOW COLUMNS FROM `{$table}`";
            $stmt = $pdo->query($sql);

            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                if (!empty($row['Field'])) {
                    $cols[] = $row['Field'];
                }
            }
        } catch (Throwable $e) {
            $cols = [];
        }

        $this->columnCache[$table] = $cols;
        return $cols;
    }

    private function hasColumnLocal(array $cols, string $name): bool
    {
        return in_array($name, $cols, true);
    }

    /* ============================================================
     * Canonical purchase helpers (match PurchasesController)
     * ============================================================ */

    /** Header table for BizFlow purchases (canonical) */
    private function purchaseHeaderTable(PDO $pdo): ?string
    {
        // Hard-coded to keep behaviour stable
        return 'biz_purchase_orders';
    }

    /** Lines table for BizFlow purchases (canonical) */
    private function purchaseLinesTable(PDO $pdo): ?string
    {
        return 'biz_purchase_order_lines';
    }

    /* ============================================================
     * 1) Index: GRN register
     * ============================================================ */

    public function index(?array $ctx = null): void
    {
        try {
            $c     = $this->ctx($ctx ?? []);
            $org   = $c['org'] ?? [];
            $orgId = $this->requireOrg();
            $pdo   = $this->pdo();

            $q      = trim((string)($_GET['q']      ?? ''));
            $status = trim((string)($_GET['status'] ?? ''));
            $ref    = trim((string)($_GET['ref']    ?? ''));   // PO / LC / manual ref
            $from   = trim((string)($_GET['from']   ?? ''));
            $to     = trim((string)($_GET['to']     ?? ''));

            $storageReady = $this->grnStorageReady($pdo);
            $grns         = [];

            if ($storageReady) {
                $sql    = "SELECT * FROM biz_grn WHERE org_id = :org_id";
                $params = ['org_id' => $orgId];

                if ($q !== '') {
                    $sql .= " AND (grn_no LIKE :q OR supplier_name LIKE :q)";
                    $params['q'] = '%' . $q . '%';
                }
                if ($status !== '') {
                    $sql .= " AND status = :status";
                    $params['status'] = $status;
                }
                if ($ref !== '') {
                    $sql .= " AND (ref_no LIKE :ref OR ref_type LIKE :ref)";
                    $params['ref'] = '%' . $ref . '%';
                }
                if ($from !== '') {
                    $sql .= " AND grn_date >= :from";
                    $params['from'] = $from;
                }
                if ($to !== '') {
                    $sql .= " AND grn_date <= :to";
                    $params['to'] = $to;
                }

                $sql .= " ORDER BY grn_date DESC, id DESC LIMIT 500";

                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);
                $grns = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
            }

            // Metrics
            $metrics = [
                'total'     => 0,
                'posted'    => 0,
                'draft'     => 0,
                'cancelled' => 0,
                'today'     => (new DateTimeImmutable('now'))->format('Y-m-d'),
            ];

            foreach ($grns as $row) {
                $metrics['total']++;
                $st = strtolower((string)($row['status'] ?? 'draft'));
                if ($st === 'posted') {
                    $metrics['posted']++;
                } elseif ($st === 'cancelled') {
                    $metrics['cancelled']++;
                } else {
                    $metrics['draft']++;
                }
            }

            // Flash
            $flash = null;
            if (\PHP_SESSION_ACTIVE !== \session_status()) {
                @\session_start();
            }
            if (!empty($_SESSION['bizflow_grn_flash'])) {
                $flash = (string)$_SESSION['bizflow_grn_flash'];
                unset($_SESSION['bizflow_grn_flash']);
            }

            $this->view('grn/index', [
                'title'         => 'Goods Receipts (GRN)',
                'org'           => $org,
                'module_base'   => $c['module_base'] ?? '/apps/bizflow',
                'grns'          => $grns,
                'metrics'       => $metrics,
                'filters'       => [
                    'q'      => $q,
                    'status' => $status,
                    'ref'    => $ref,
                    'from'   => $from,
                    'to'     => $to,
                ],
                'storage_ready' => $storageReady,
                'flash'         => $flash,
            ], 'shell');

        } catch (Throwable $e) {
            $this->oops('GRN index failed', $e);
        }
    }

    /* ============================================================
     * 2) Create / Edit
     * ============================================================ */

    /**
     * GET /apps/bizflow/grn/create
     * GET /t/{slug}/apps/bizflow/grn/create
     *
     * - If ?purchase_id=XX is present: load purchase + lines
     *   and show "create from purchase" screen.
     * - Without purchase_id: fallback to manual GRN form.
     */
    public function create(?array $ctx = null): void
    {
        try {
            $c       = $this->ctx($ctx ?? []);
            $org     = $c['org'] ?? [];
            $orgId   = $this->requireOrg();
            $pdo     = $this->pdo();
            $base    = $c['module_base'] ?? '/apps/bizflow';
            $today   = (new DateTimeImmutable('now'))->format('Y-m-d');

            $purchaseId = null;
            if (isset($_GET['purchase_id']) && ctype_digit((string)$_GET['purchase_id'])) {
                $purchaseId = (int)$_GET['purchase_id'];
            }

            /* --------- Branch 1: Create GRN from purchase ---------- */
            if ($purchaseId !== null && $purchaseId > 0) {
                $headerTable = $this->purchaseHeaderTable($pdo);   // biz_purchase_orders
                $linesTable  = $this->purchaseLinesTable($pdo);    // biz_purchase_order_lines

                // 1) Load purchase header
                $sqlPurch = "
                    SELECT p.*
                      FROM `{$headerTable}` p
                     WHERE p.org_id = ?
                       AND p.id     = ?
                     LIMIT 1
                ";
                $purchase = $this->row($sqlPurch, [$orgId, $purchaseId]);

                if (!$purchase) {
                    http_response_code(404);
                    echo 'Purchase not found for GRN.';
                    return;
                }

                // 2) Load purchase lines
                $items = [];
                if ($linesTable === 'biz_purchase_order_lines') {
                    $sqlItems = "
                        SELECT
                            l.*,
                            it.code AS item_code,
                            it.name AS item_name
                          FROM `{$linesTable}` l
                     LEFT JOIN biz_items it
                            ON it.org_id = l.org_id
                           AND it.id     = l.item_id
                         WHERE l.org_id      = ?
                           AND l.purchase_id = ?
                      ORDER BY l.id
                    ";
                    $items = $this->rows($sqlItems, [$orgId, $purchaseId]);
                }

                // 3) Prefill GRN stub from purchase
                $poNo     = (string)($purchase['po_no'] ?? ('PO-' . $purchaseId));
                $currency = (string)($purchase['currency'] ?? 'BDT');

                $grn = [
                    'id'             => null,
                    'grn_no'         => '',
                    'grn_date'       => $today,
                    'ref_type'       => 'purchase',
                    'ref_no'         => $poNo,
                    'supplier_name'  => (string)($purchase['supplier_name'] ?? ''),
                    'warehouse_name' => '',
                    'currency'       => $currency,
                    'status'         => 'draft',
                    'notes'          => '',
                ];

                $this->view('grn/create_from_purchase', [
                    'title'       => 'New GRN from ' . $poNo,
                    'org'         => $org,
                    'module_base' => $base,
                    'grn'         => $grn,
                    'purchase'    => $purchase,
                    'items'       => $items,
                    'mode'        => 'create_from_purchase',
                ], 'shell');

                return;
            }

            /* --------- Branch 2: manual GRN (legacy form) ---------- */
            $grn = [
                'id'             => null,
                'grn_no'         => '',
                'grn_date'       => $today,
                'ref_type'       => 'manual',
                'ref_no'         => '',
                'supplier_name'  => $org['name'] ?? '',
                'warehouse_name' => '',
                'currency'       => 'BDT',
                'status'         => 'draft',
                'notes'          => '',
            ];

            $this->view('grn/create', [
                'title'       => 'New GRN',
                'org'         => $org,
                'module_base' => $base,
                'grn'         => $grn,
                'mode'        => 'create',
            ], 'shell');

        } catch (Throwable $e) {
            $this->oops('GRN create failed', $e);
        }
    }

    public function edit(?array $ctx, int $id): void
    {
        try {
            $c     = $this->ctx($ctx ?? []);
            $org   = $c['org'] ?? [];
            $orgId = $this->requireOrg();
            $pdo   = $this->pdo();

            if (!$this->hasTable($pdo, 'biz_grn')) {
                http_response_code(500);
                echo "GRN table 'biz_grn' not found.";
                return;
            }

            $stmt = $pdo->prepare(
                "SELECT * FROM biz_grn WHERE org_id = ? AND id = ? LIMIT 1"
            );
            $stmt->execute([$orgId, $id]);
            $grn = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;

            if (!$grn) {
                http_response_code(404);
                echo 'GRN not found.';
                return;
            }

            $this->view('grn/edit', [
                'title'       => 'Edit GRN',
                'org'         => $org,
                'module_base' => $c['module_base'] ?? '/apps/bizflow',
                'grn'         => $grn,
                'mode'        => 'edit',
            ], 'shell');

        } catch (Throwable $e) {
            $this->oops('GRN edit failed', $e);
        }
    }

    /* ============================================================
     * 3) Show — GRN + line items + posting status
     * ============================================================ */
    public function show(?array $ctx, int $id): void
    {
        try {
            $c     = $this->ctx($ctx ?? []);
            $org   = $c['org'] ?? [];
            $orgId = $this->requireOrg();
            $pdo   = $this->pdo();

            if (!$this->grnStorageReady($pdo)) {
                http_response_code(500);
                echo "GRN storage tables (biz_grn / biz_grn_items) not found.";
                return;
            }

            // Header
            $stmt = $pdo->prepare(
                "SELECT *
                   FROM biz_grn
                  WHERE org_id = ?
                    AND id     = ?
                  LIMIT 1"
            );
            $stmt->execute([$orgId, $id]);
            $grn = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;

            if (!$grn) {
                http_response_code(404);
                echo 'GRN not found.';
                return;
            }

            // Lines + item name/code from biz_items
            $stmt2 = $pdo->prepare(
                "SELECT gi.*,
                        it.code AS item_code,
                        it.name AS item_name
                   FROM biz_grn_items gi
              LEFT JOIN biz_items it
                     ON it.org_id = gi.org_id
                    AND it.id     = gi.item_id
                  WHERE gi.org_id = ?
                    AND gi.grn_id = ?
               ORDER BY gi.id"
            );
            $stmt2->execute([$orgId, $id]);
            $items = $stmt2->fetchAll(PDO::FETCH_ASSOC) ?: [];

            $this->view('grn/show', [
                'title'        => 'GRN details',
                'org'          => $org,
                'module_base'  => $c['module_base'] ?? '/apps/bizflow',
                'grn'          => $grn,
                'items'        => $items,
                'storage_ready'=> true,
            ], 'shell');

        } catch (Throwable $e) {
            $this->oops('GRN show failed', $e);
        }
    }

    /* ============================================================
     * 4) Store / Update (REAL persistence)
     * ============================================================ */

    /**
     * POST /apps/bizflow/grn
     *
     * Handles both:
     *  - GRN from purchase (create_from_purchase)
     *  - Manual GRN form
     */
    public function store(?array $ctx = null): void
{
    try {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            http_response_code(405);
            echo 'Method not allowed';
            return;
        }

        $c     = $this->ctx($ctx ?? []);
        $base  = $c['module_base'] ?? '/apps/bizflow';
        $orgId = $this->requireOrg();
        $pdo   = $this->pdo();

        if (!$this->grnStorageReady($pdo)) {
            $this->flash("GRN storage tables (biz_grn / biz_grn_items) not found.");
            if (!headers_sent()) {
                header('Location: ' . $base . '/grn');
            }
            exit;
        }

        $hdrCols  = $this->tableColumns($pdo, 'biz_grn');
        $lineCols = $this->tableColumns($pdo, 'biz_grn_items');

        $now    = (new DateTimeImmutable('now'))->format('Y-m-d H:i:s');
        $today  = (new DateTimeImmutable('now'))->format('Y-m-d');

        // Header fields
        $purchaseId = 0;
        foreach (['purchase_id', 'source_purchase_id', 'po_id'] as $key) {
            if (isset($_POST[$key]) && ctype_digit((string)$_POST[$key])) {
                $purchaseId = (int)$_POST[$key];
                break;
            }
        }

        $grnNo         = trim((string)($_POST['grn_no'] ?? ''));
        $grnDate       = (string)($_POST['grn_date'] ?? $today);
        $currency      = trim((string)($_POST['currency'] ?? ($_POST['currency_code'] ?? 'BDT')));
        $supplierName  = trim((string)($_POST['supplier_name'] ?? ''));
        $warehouseId   = isset($_POST['warehouse_id']) && ctype_digit((string)$_POST['warehouse_id'])
                         ? (int)$_POST['warehouse_id'] : null;
        $warehouseName = trim((string)($_POST['warehouse_name'] ?? ''));
        $notes         = trim((string)($_POST['notes'] ?? ''));
        $refType       = trim((string)($_POST['ref_type'] ?? 'purchase'));
        $refNo         = trim((string)($_POST['ref_no'] ?? ''));

        /* --------------------------------------------------------
         * Auto-generate GRN number if blank or already used
         * ------------------------------------------------------ */
        $exists = false;
        if ($grnNo !== '') {
            $checkGrn = $pdo->prepare(
                "SELECT COUNT(*) FROM biz_grn WHERE org_id = ? AND grn_no = ?"
            );
            $checkGrn->execute([$orgId, $grnNo]);
            $exists = ((int)$checkGrn->fetchColumn() > 0);
        } else {
            $exists = true; // force generation when empty
        }

        if ($exists) {
            // Find next sequence for this org based on max id
            $stmtNo = $pdo->prepare(
                "SELECT MAX(id) AS max_id
                   FROM biz_grn
                  WHERE org_id = ?"
            );
            $stmtNo->execute([$orgId]);
            $maxId = (int)($stmtNo->fetchColumn() ?: 0);
            $next  = $maxId + 1;

            // Format: YY-0001, YY-0002, ...
            $yearShort = date('y');
            $grnNo = $yearShort . '-' . str_pad((string)$next, 4, '0', STR_PAD_LEFT);
        }

        // Line items from form
        $itemsInput = $_POST['items'] ?? [];
        if (!is_array($itemsInput)) {
            $itemsInput = [];
        }

        $lines       = [];
        $totalQty    = 0.0;
        $totalAmount = 0.0;

        foreach ($itemsInput as $row) {
            $row = (array)$row;

            $itemId = (int)($row['item_id'] ?? 0);
            if ($itemId <= 0) {
                continue;
            }

            // Accept both qty / qty_this_grn from the form
            $qty = 0.0;
            if (isset($row['qty'])) {
                $qty = (float)$row['qty'];
            } elseif (isset($row['qty_this_grn'])) {
                $qty = (float)$row['qty_this_grn'];
            }

            if ($qty <= 0) {
                continue;
            }

            $uom       = trim((string)($row['unit'] ?? ($row['uom'] ?? '')));
            $unitCost  = (float)($row['unit_cost'] ?? ($row['unit_price'] ?? 0));
            $lineTotal = isset($row['line_total'])
                ? (float)$row['line_total']
                : $qty * $unitCost;

            $purchaseLineId = 0;
            foreach (['purchase_line_id', 'line_id'] as $k) {
                if (isset($row[$k]) && ctype_digit((string)$row[$k])) {
                    $purchaseLineId = (int)$row[$k];
                    break;
                }
            }

            $batchNo    = trim((string)($row['batch_no'] ?? ''));
            $expiryDate = trim((string)($row['expiry_date'] ?? ''));

            $lines[] = [
                'item_id'          => $itemId,
                'qty'              => $qty,
                'uom'              => $uom,
                'unit_cost'        => $unitCost,
                'line_total'       => $lineTotal,
                'purchase_line_id' => $purchaseLineId,
                'batch_no'         => $batchNo,
                'expiry_date'      => $expiryDate,
            ];

            $totalQty    += $qty;
            $totalAmount += $lineTotal;
        }

        if (empty($lines)) {
            $this->flash('No line items with quantity greater than zero. GRN was not saved.');
            if (!headers_sent()) {
                header('Location: ' . $base . '/grn');
            }
            exit;
        }

        // Build header payload based on existing columns
        $hdrData = [];

        if ($this->hasColumnLocal($hdrCols, 'org_id')) {
            $hdrData['org_id'] = $orgId;
        }
        if ($this->hasColumnLocal($hdrCols, 'purchase_id') && $purchaseId > 0) {
            $hdrData['purchase_id'] = $purchaseId;
        }
        if ($this->hasColumnLocal($hdrCols, 'ref_type')) {
            $hdrData['ref_type'] = $refType;
        }
        if ($this->hasColumnLocal($hdrCols, 'ref_no')) {
            $hdrData['ref_no'] = $refNo;
        }
        if ($this->hasColumnLocal($hdrCols, 'grn_no')) {
            $hdrData['grn_no'] = $grnNo;
        }
        if ($this->hasColumnLocal($hdrCols, 'grn_date')) {
            $hdrData['grn_date'] = $grnDate;
        }
        if ($this->hasColumnLocal($hdrCols, 'currency')) {
            $hdrData['currency'] = $currency;
        }
        if ($this->hasColumnLocal($hdrCols, 'supplier_name')) {
            $hdrData['supplier_name'] = $supplierName;
        }
        if ($this->hasColumnLocal($hdrCols, 'warehouse_id')) {
            $hdrData['warehouse_id'] = $warehouseId;
        }
        if ($this->hasColumnLocal($hdrCols, 'warehouse_name')) {
            $hdrData['warehouse_name'] = $warehouseName;
        }
        if ($this->hasColumnLocal($hdrCols, 'status')) {
            $hdrData['status'] = 'draft';
        }
        if ($this->hasColumnLocal($hdrCols, 'notes')) {
            $hdrData['notes'] = $notes;
        }
        if ($this->hasColumnLocal($hdrCols, 'total_qty')) {
            $hdrData['total_qty'] = $totalQty;
        }
        if ($this->hasColumnLocal($hdrCols, 'total_amount')) {
            $hdrData['total_amount'] = $totalAmount;
        }
        if ($this->hasColumnLocal($hdrCols, 'created_at')) {
            $hdrData['created_at'] = $now;
        }
        if ($this->hasColumnLocal($hdrCols, 'updated_at')) {
            $hdrData['updated_at'] = $now;
        }

        $pdo->beginTransaction();

        // Insert header
        $hdrColsUsed   = array_keys($hdrData);
        $hdrPlaceholds = array_map(
            static fn(string $c): string => ':' . $c,
            $hdrColsUsed
        );

        $sqlHdr = "INSERT INTO biz_grn (`"
            . implode('`,`', $hdrColsUsed)
            . "`) VALUES ("
            . implode(',', $hdrPlaceholds)
            . ")";

        $stmtHdr = $pdo->prepare($sqlHdr);
        $stmtHdr->execute($hdrData);

        $grnId = (int)$pdo->lastInsertId();

        // Insert lines
        foreach ($lines as $line) {
            $map = [
                'org_id'           => $orgId,
                'grn_id'           => $grnId,
                'item_id'          => $line['item_id'],
                'qty'              => $line['qty'],
                'qty_this_grn'     => $line['qty'],
                'unit'             => $line['uom'],
                'uom'              => $line['uom'],
                'unit_cost'        => $line['unit_cost'],
                'total_cost'       => $line['line_total'],
                'line_total'       => $line['line_total'],
                'purchase_id'      => $purchaseId,
                'purchase_line_id' => $line['purchase_line_id'],
                'batch_no'         => $line['batch_no'],
                'expiry_date'      => $line['expiry_date'],
                'created_at'       => $now,
                'updated_at'       => $now,
            ];

            $rowData = [];
            foreach ($lineCols as $colName) {
                if (array_key_exists($colName, $map)) {
                    $rowData[$colName] = $map[$colName];
                }
            }

            if (empty($rowData)) {
                continue;
            }

            $colsUsed   = array_keys($rowData);
            $placeholds = array_map(
                static fn(string $c): string => ':' . $c,
                $colsUsed
            );

            $sqlLine = "INSERT INTO biz_grn_items (`"
                . implode('`,`', $colsUsed)
                . "`) VALUES ("
                . implode(',', $placeholds)
                . ")";

            $stmtLine = $pdo->prepare($sqlLine);
            $stmtLine->execute($rowData);
        }

        $pdo->commit();

        $this->flash('GRN saved successfully. You can now post it to inventory.');

        if (!headers_sent()) {
            header('Location: ' . $base . '/grn');
        }
        exit;

    } catch (Throwable $e) {
        // Safety: roll back if needed
        try {
            if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
        } catch (Throwable $ignore) {
            // ignore rollback failure
        }

        // DIRECT plain-text error so you don't see just "Unexpected error"
        if (!headers_sent()) {
            http_response_code(500);
            header('Content-Type: text/plain; charset=UTF-8');
        }

        echo "BizFlow GRN store failed\n\n";
        echo "Message: " . $e->getMessage() . "\n";
        echo "File: " . $e->getFile() . ':' . $e->getLine() . "\n\n";
        echo $e->getTraceAsString();
        exit;
    }
}

    public function update(?array $ctx, int $id): void
    {
        // For now keep GRN updates simple; we can extend later if you want
        try {
            if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
                http_response_code(405);
                echo 'Method not allowed';
                return;
            }

            $c    = $this->ctx($ctx ?? []);
            $base = $c['module_base'] ?? '/apps/bizflow';

            $this->flash('GRN update endpoint is not wired yet. For now, edit by cancelling and recreating if needed.');

            if (!headers_sent()) {
                header('Location: ' . $base . '/grn/' . (int)$id);
            }
            exit;

        } catch (Throwable $e) {
            $this->oops('GRN update failed', $e);
        }
    }

    /* ============================================================
     * 5) Post GRN -> Inventory (uses InventoryService)
     * ============================================================ */
    public function post(?array $ctx, int $id): void
    {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        if (!in_array($method, ['GET', 'POST'], true)) {
            http_response_code(405);
            echo 'Method not allowed';
            return;
        }

        if ($id <= 0) {
            http_response_code(404);
            echo 'Invalid GRN id';
            return;
        }

        try {
            $c     = $this->ctx($ctx ?? []);
            $base  = $c['module_base'] ?? '/apps/bizflow';
            $orgId = $this->requireOrg();

            $this->inv()->postGrn($orgId, $id);
            $this->flash('GRN posted to inventory.');

            if (!headers_sent()) {
                header('Location: ' . $base . '/grn/' . (int)$id);
            }
            exit;
        } catch (Throwable $e) {
            $this->oops('GRN post failed', $e);
        }
    }
}