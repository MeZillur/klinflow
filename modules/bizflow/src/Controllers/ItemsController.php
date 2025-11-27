<?php
declare(strict_types=1);

namespace Modules\BizFlow\Controllers;

use PDO;
use Throwable;

final class ItemsController extends BaseController
{
    /**
     * GET /apps/bizflow/items
     * GET /t/{slug}/apps/bizflow/items
     */
    public function index(?array $ctx = null): void
    {
        try {
            $c     = $this->ctx($ctx ?? []);
            $orgId = $this->requireOrg();

            $q      = trim((string)($_GET['q'] ?? ''));
            $type   = trim((string)($_GET['type'] ?? ''));   // all|product|service
            $status = trim((string)($_GET['status'] ?? '')); // all|active|inactive

            $where = ['i.org_id = ?'];
            $bind  = [$orgId];

            if ($q !== '') {
                $where[] = '(i.name LIKE ? OR i.code LIKE ? OR i.barcode LIKE ?)';
                $like    = '%'.$q.'%';
                $bind[]  = $like;
                $bind[]  = $like;
                $bind[]  = $like;
            }

            if ($type === 'product') {
                $where[] = "i.item_type = 'stock'";
            } elseif ($type === 'service') {
                $where[] = "i.item_type = 'service'";
            }

            if ($status === 'active') {
                $where[] = 'i.is_active = 1';
            } elseif ($status === 'inactive') {
                $where[] = 'i.is_active = 0';
            }

            $sql = "
                SELECT
                    i.id,
                    i.name,
                    i.code,
                    i.item_type,
                    i.unit,
                    COALESCE(i.selling_price, i.sale_price, 0.00) AS price_bdt,
                    i.margin_percent,
                    i.track_inventory,
                    i.is_active,
                    i.updated_at,
                    c.name AS category_name
                FROM biz_items i
                LEFT JOIN biz_item_categories c
                  ON c.id = i.category_id
                 AND c.org_id = i.org_id
                WHERE " . implode(' AND ', $where) . "
                ORDER BY i.name ASC, i.id ASC
                LIMIT 500
            ";

            $items = $this->rows($sql, $bind);

            $this->view('items/index', [
                'title'       => 'Items',
                'org'         => $c['org'] ?? [],
                'module_base' => $c['module_base'] ?? '/apps/bizflow',
                'items'       => $items,
                'search'      => $q,
                'type'        => $type,
                'status'      => $status,
            ], 'shell');

        } catch (Throwable $e) {
            $this->oops('Items index failed', $e);
        }
    }

    /**
     * GET /apps/bizflow/items/create
     * GET /t/{slug}/apps/bizflow/items/create
     */
    public function create(?array $ctx = null): void
    {
        try {
            $c     = $this->ctx($ctx ?? []);
            $orgId = $this->requireOrg();

            // Categories
            $categories = $this->rows(
                "SELECT id, name, code
                   FROM biz_item_categories
                  WHERE org_id = ?
                  ORDER BY name",
                [$orgId]
            );

            // Units of measure
            $uoms = $this->rows(
                "SELECT id, name, code
                   FROM biz_uoms
                  WHERE org_id = ?
                    AND (is_active = 1 OR is_active IS NULL)
                  ORDER BY name",
                [$orgId]
            );

            $this->view('items/create', [
                'title'       => 'New item',
                'org'         => $c['org'] ?? [],
                'module_base' => $c['module_base'] ?? '/apps/bizflow',
                'categories'  => $categories,
                'uoms'        => $uoms,
                'item'        => null,
            ], 'shell');

        } catch (Throwable $e) {
            $this->oops('Items create screen failed', $e);
        }
    }

    /**
     * GET /apps/bizflow/items/{id}/edit
     * GET /t/{slug}/apps/bizflow/items/{id}/edit
     */
    public function edit(?array $ctx = null, int $id = 0): void
    {
        try {
            $c     = $this->ctx($ctx ?? []);
            $orgId = $this->requireOrg();

            $item = $this->row(
                "SELECT *
                   FROM biz_items
                  WHERE org_id = ? AND id = ?
                  LIMIT 1",
                [$orgId, $id]
            );

            if (!$item) {
                http_response_code(404);
                echo 'Item not found';
                return;
            }

            $categories = $this->rows(
                "SELECT id, name, code
                   FROM biz_item_categories
                  WHERE org_id = ?
                  ORDER BY name",
                [$orgId]
            );

            $uoms = $this->rows(
                "SELECT id, name, code
                   FROM biz_uoms
                  WHERE org_id = ?
                    AND (is_active = 1 OR is_active IS NULL)
                  ORDER BY name",
                [$orgId]
            );

            $this->view('items/create', [
                'title'       => 'Edit item',
                'org'         => $c['org'] ?? [],
                'module_base' => $c['module_base'] ?? '/apps/bizflow',
                'categories'  => $categories,
                'uoms'        => $uoms,
                'item'        => $item,
            ], 'shell');

        } catch (Throwable $e) {
            $this->oops('Items edit screen failed', $e);
        }
    }

    /**
     * POST /apps/bizflow/items
     */
    public function store(?array $ctx = null): void
    {
        try {
            $this->postOnly();

            $c     = $this->ctx($ctx ?? []);
            $orgId = $this->requireOrg();
            $pdo   = $this->pdo();

            // ---------- Basic fields from your form ----------
            $name        = trim((string)($_POST['name'] ?? ''));
            $codeInput   = trim((string)($_POST['code'] ?? ''));
            $description = trim((string)($_POST['description'] ?? ''));

            $rawType  = (string)($_POST['item_type'] ?? 'product'); // product|service from UI
            // DB uses stock|service, keep mapping
            $itemType = $rawType === 'service' ? 'service' : 'stock';

            if ($name === '') {
                throw new \RuntimeException('Item name is required.');
            }
            if ($description === '') {
                throw new \RuntimeException('Specification / description is required.');
            }

            $categoryId = isset($_POST['category_id']) && ctype_digit((string)$_POST['category_id'])
                ? (int)$_POST['category_id'] : null;

            $uomId = isset($_POST['uom_id']) && ctype_digit((string)$_POST['uom_id'])
                ? (int)$_POST['uom_id'] : null;

            $barcode = trim((string)($_POST['barcode'] ?? ''));

            $purchaseRaw = (string)($_POST['purchase_price'] ?? '');
            $sellRaw     = (string)($_POST['selling_price']  ?? '');
            $marginRaw   = (string)($_POST['margin_percent'] ?? '');

            $purchase = $purchaseRaw !== '' ? (float)$purchaseRaw : 0.0;
            $sell     = $sellRaw     !== '' ? (float)$sellRaw     : null;
            $margin   = $marginRaw   !== '' ? (float)$marginRaw   : null;

            $trackInv = isset($_POST['is_stocked']) ? 1 : 0;
            $isActive = isset($_POST['is_active'])  ? 1 : 0;

            // These may not be in the form yet – handle safely
            $reorderLevelRaw = (string)($_POST['reorder_level'] ?? '');
            $reorderQtyRaw   = (string)($_POST['reorder_qty']   ?? '');

            $reorderLevel = $reorderLevelRaw !== '' ? (float)$reorderLevelRaw : null;
            $reorderQty   = $reorderQtyRaw   !== '' ? (float)$reorderQtyRaw   : null;

            // ---------- Code / SKU ----------
            $code = $codeInput !== '' ? $codeInput : $this->generateSku($pdo, $orgId, $name);

            // ---------- Selling price from margin ----------
            if (($sell === null || $sell <= 0) && $purchase > 0 && $margin !== null) {
                $sell = round($purchase * (1 + $margin / 100), 2);
            }
            if ($sell === null) {
                $sell = 0.00;
            }

            $salePrice     = $sell;     // sync for now
            $lastCostPrice = $purchase; // seed last_cost_price

            // ---------- Unit text mirrored from UoM ----------
            $unitText = 'pcs';
            if ($uomId) {
                $u = $this->row(
                    "SELECT code, name
                       FROM biz_uoms
                      WHERE org_id = ? AND id = ?
                      LIMIT 1",
                    [$orgId, $uomId]
                );
                if ($u) {
                    $unitText = (string)($u['code'] ?? $u['name'] ?? $unitText);
                }
            }

            // ---------- Exact INSERT for biz_items ----------
            $sql = "
                INSERT INTO biz_items (
                    org_id,
                    category_id,
                    code,
                    name,
                    description,
                    item_type,
                    uom_id,
                    unit,
                    barcode,
                    sale_price,
                    purchase_price,
                    selling_price,
                    margin_percent,
                    last_cost_price,
                    track_inventory,
                    is_active,
                    reorder_level,
                    reorder_qty
                ) VALUES (
                    ?, ?, ?, ?, ?, ?,
                    ?, ?, ?, ?, ?,
                    ?, ?, ?, ?, ?,
                    ?, ?
                )
            ";

            $params = [
                $orgId,                             // org_id
                $categoryId,                        // category_id
                $code,                              // code
                $name,                              // name
                $description,                       // description (required)
                $itemType,                          // item_type
                $uomId,                             // uom_id
                $unitText,                          // unit
                $barcode !== '' ? $barcode : null,  // barcode
                $salePrice,                         // sale_price
                $purchase,                          // purchase_price
                $sell,                              // selling_price
                $margin,                            // margin_percent
                $lastCostPrice,                     // last_cost_price
                $trackInv,                          // track_inventory
                $isActive,                          // is_active
                $reorderLevel,                      // reorder_level
                $reorderQty                         // reorder_qty
            ];

            $this->logLine(
                'BizFlow Items INSERT: ' . $sql . ' | ' .
                json_encode($params, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
            );

            try {
                $this->exec($sql, $params);
            } catch (\PDOException $db) {
                $errNo = (int)($db->errorInfo[1] ?? 0);
                if ($errNo === 1062) {
                    throw new \RuntimeException('This item code is already used for this organization.');
                }
                throw $db;
            }

            $base = $c['module_base'] ?? '/apps/bizflow';
            $this->redirect(rtrim($base, '/') . '/items');

        } catch (Throwable $e) {
            $this->oops('Items store failed', $e);
        }
    }

    /**
     * POST /apps/bizflow/items/{id}/update
     */
    public function update(?array $ctx = null, int $id = 0): void
    {
        try {
            $this->postOnly();

            $c     = $this->ctx($ctx ?? []);
            $orgId = $this->requireOrg();

            $exists = $this->row(
                "SELECT * FROM biz_items WHERE org_id = ? AND id = ? LIMIT 1",
                [$orgId, $id]
            );
            if (!$exists) {
                http_response_code(404);
                echo 'Item not found';
                return;
            }

            $name        = trim((string)($_POST['name'] ?? ''));
            $codeInput   = trim((string)($_POST['code'] ?? ''));
            $description = trim((string)($_POST['description'] ?? ''));

            $rawType  = (string)($_POST['item_type'] ?? 'product');
            $itemType = $rawType === 'service' ? 'service' : 'stock';

            if ($name === '') {
                throw new \RuntimeException('Item name is required.');
            }
            if ($description === '') {
                throw new \RuntimeException('Specification / description is required.');
            }

            $categoryId = isset($_POST['category_id']) && ctype_digit((string)$_POST['category_id'])
                ? (int)$_POST['category_id'] : null;

            $uomId = isset($_POST['uom_id']) && ctype_digit((string)$_POST['uom_id'])
                ? (int)$_POST['uom_id'] : null;

            $barcode = trim((string)($_POST['barcode'] ?? ''));

            $purchaseRaw = (string)($_POST['purchase_price'] ?? '');
            $sellRaw     = (string)($_POST['selling_price']  ?? '');
            $marginRaw   = (string)($_POST['margin_percent'] ?? '');

            $purchase = $purchaseRaw !== '' ? (float)$purchaseRaw : 0.0;
            $sell     = $sellRaw     !== '' ? (float)$sellRaw     : null;
            $margin   = $marginRaw   !== '' ? (float)$marginRaw   : null;

            $trackInv = isset($_POST['is_stocked']) ? 1 : 0;
            $isActive = isset($_POST['is_active'])  ? 1 : 0;

            $reorderLevelRaw = (string)($_POST['reorder_level'] ?? '');
            $reorderQtyRaw   = (string)($_POST['reorder_qty']   ?? '');

            $reorderLevel = $reorderLevelRaw !== '' ? (float)$reorderLevelRaw : null;
            $reorderQty   = $reorderQtyRaw   !== '' ? (float)$reorderQtyRaw   : null;

            $code = $codeInput !== '' ? $codeInput : $exists['code'];

            if (($sell === null || $sell <= 0) && $purchase > 0 && $margin !== null) {
                $sell = round($purchase * (1 + $margin / 100), 2);
            }
            if ($sell === null) {
                $sell = 0.00;
            }

            $salePrice     = $sell;
            $lastCostPrice = $purchase;

            $unitText = 'pcs';
            if ($uomId) {
                $u = $this->row(
                    "SELECT code, name
                       FROM biz_uoms
                      WHERE org_id = ? AND id = ?
                      LIMIT 1",
                    [$orgId, $uomId]
                );
                if ($u) {
                    $unitText = (string)($u['code'] ?? $u['name'] ?? $unitText);
                }
            }

            $sql = "
                UPDATE biz_items
                   SET category_id     = ?,
                       code            = ?,
                       name            = ?,
                       description     = ?,
                       item_type       = ?,
                       uom_id          = ?,
                       unit            = ?,
                       barcode         = ?,
                       sale_price      = ?,
                       purchase_price  = ?,
                       selling_price   = ?,
                       margin_percent  = ?,
                       last_cost_price = ?,
                       track_inventory = ?,
                       is_active       = ?,
                       reorder_level   = ?,
                       reorder_qty     = ?
                 WHERE org_id = ? AND id = ?
            ";

            $params = [
                $categoryId,
                $code,
                $name,
                $description,
                $itemType,
                $uomId,
                $unitText,
                $barcode !== '' ? $barcode : null,
                $salePrice,
                $purchase,
                $sell,
                $margin,
                $lastCostPrice,
                $trackInv,
                $isActive,
                $reorderLevel,
                $reorderQty,
                $orgId,
                $id,
            ];

            try {
                $this->exec($sql, $params);
            } catch (\PDOException $db) {
                $errNo = (int)($db->errorInfo[1] ?? 0);
                if ($errNo === 1062) {
                    throw new \RuntimeException('This item code is already used for this organization.');
                }
                throw $db;
            }

            $base = $c['module_base'] ?? '/apps/bizflow';
            $this->redirect(rtrim($base, '/') . '/items');

        } catch (Throwable $e) {
            $this->oops('Items update failed', $e);
        }
    }

    /**
     * GET /apps/bizflow/items/bulk-template.csv
     * GET /t/{slug}/apps/bizflow/items/bulk-template.csv
     *
     * Downloadable CSV format for bulk item upload.
     */
    public function bulkTemplate(?array $ctx = null): void
    {
        try {
            $this->requireOrg(); // just to ensure tenant

            if (!headers_sent()) {
                header('Content-Type: text/csv; charset=UTF-8');
                header('Content-Disposition: attachment; filename="bizflow-items-template.csv"');
            }

            $out = fopen('php://output', 'wb');
            if ($out === false) {
                throw new \RuntimeException('Unable to open output stream for CSV.');
            }

            // Header row – must match what JS expects
            $header = [
                'name',
                'code',
                'item_type',
                'unit',
                'purchase_price',
                'selling_price',
                'category',
            ];
            fputcsv($out, $header);

            // Sample rows
            fputcsv($out, [
                'A4 Printing Paper 80gsm',
                'PAPER-A4-80',
                'product',
                'ream',
                '450.00',
                '550.00',
                'Stationery',
            ]);

            fputcsv($out, [
                'On-site installation support',
                '',                // auto SKU
                'service',
                'hour',
                '',                // purchase price optional
                '1500.00',
                'Implementation Services',
            ]);

            fclose($out);
            exit;

        } catch (Throwable $e) {
            $this->oops('Bulk items CSV template failed', $e);
        }
    }

    /**
     * POST /apps/bizflow/items/bulk-preview
     * POST /t/{slug}/apps/bizflow/items/bulk-preview
     *
     * Parses CSV, validates rows, builds preview structure + session stash.
     */
    public function bulkPreview(?array $ctx = null): void
    {
        try {
            $c     = $this->ctx($ctx ?? []);
            $orgId = $this->requireOrg();

            if ($this->method() !== 'POST') {
                http_response_code(405);
                echo 'Method Not Allowed';
                return;
            }

            if (empty($_FILES['items_file']) || $_FILES['items_file']['error'] !== UPLOAD_ERR_OK) {
                http_response_code(400);
                echo 'Upload failed.';
                return;
            }

            $tmp  = $_FILES['items_file']['tmp_name'];
            $name = $_FILES['items_file']['name'];

            $fh = fopen($tmp, 'r');
            if ($fh === false) {
                http_response_code(400);
                echo 'Could not open uploaded file.';
                return;
            }

            $headerRow = fgetcsv($fh, 0, ',');
            if ($headerRow === false) {
                fclose($fh);
                http_response_code(400);
                echo 'Empty file.';
                return;
            }

            $header = [];
            foreach ($headerRow as $idx => $col) {
                $key = strtolower(trim((string)$col));
                if ($key !== '') {
                    $header[$key] = $idx;
                }
            }

            $required = ['name', 'unit', 'selling_price'];
            $missing  = [];
            foreach ($required as $col) {
                if (!array_key_exists($col, $header)) {
                    $missing[] = $col;
                }
            }
            if ($missing) {
                fclose($fh);
                http_response_code(400);
                echo 'Missing required columns: ' . implode(', ', $missing);
                return;
            }

            $get = function(array $cols, string $key) use ($header): string {
                if (!isset($header[$key])) return '';
                $idx = $header[$key];
                return isset($cols[$idx]) ? trim((string)$cols[$idx]) : '';
            };

            $rows  = [];
            $stats = ['total' => 0, 'ok' => 0, 'warning' => 0, 'error' => 0];

            $lineNo = 1;
            while (($cols = fgetcsv($fh, 0, ',')) !== false) {
                $lineNo++;

                // Skip fully empty rows
                if (count(array_filter($cols, fn($v) => trim((string)$v) !== '')) === 0) {
                    continue;
                }

                $stats['total']++;

                $nameVal   = $get($cols, 'name');
                $codeVal   = $get($cols, 'code');
                $typeVal   = strtolower($get($cols, 'item_type'));
                $unitVal   = $get($cols, 'unit');
                $catName   = $get($cols, 'category');
                $purRaw    = $get($cols, 'purchase_price');
                $sellRaw   = $get($cols, 'selling_price');

                $purchase  = $purRaw !== '' ? (float)$purRaw : null;
                $selling   = $sellRaw !== '' ? (float)$sellRaw : null;

                $messages = [];
                $status   = 'ok';

                if ($nameVal === '') {
                    $messages[] = 'Name is required.';
                    $status = 'error';
                }

                if ($unitVal === '') {
                    $messages[] = 'Unit is required.';
                    $status = 'error';
                }

                if ($selling === null || !is_finite($selling) || $selling <= 0) {
                    $messages[] = 'Selling price must be greater than 0.';
                    $status = 'error';
                }

                if ($typeVal !== '' && !in_array($typeVal, ['product', 'service'], true)) {
                    $messages[] = "item_type '{$typeVal}' is invalid; using product.";
                    $status = $status === 'error' ? 'error' : 'warning';
                    $typeVal = 'product';
                }

                if ($typeVal === '') {
                    $messages[] = 'item_type empty -> defaults to product.';
                    if ($status !== 'error') {
                        $status = 'warning';
                    }
                    $typeVal = 'product';
                }

                if ($purchase !== null && $purchase < 0) {
                    $messages[] = 'Purchase price cannot be negative.';
                    $status = 'error';
                }

                if ($catName !== '') {
                    $messages[] = "Category will be matched by name: '{$catName}'.";
                    if ($status !== 'error') {
                        $status = 'warning';
                    }
                }

                if ($status === 'ok') {
                    $stats['ok']++;
                } elseif ($status === 'warning') {
                    $stats['warning']++;
                } else {
                    $stats['error']++;
                }

                $rows[] = [
                    'line'     => $lineNo,
                    'status'   => $status,
                    'messages' => $messages,
                    'data'     => [
                        'name'           => $nameVal,
                        'code'           => $codeVal,
                        'category_name'  => $catName,
                        'item_type'      => $typeVal === 'service' ? 'service' : 'product',
                        'unit'           => $unitVal,
                        'purchase_price' => $purchase,
                        'selling_price'  => $selling,
                        'track_inventory'=> $typeVal === 'service' ? 0 : 1,
                        'is_active'      => 1,
                    ],
                ];
            }

            fclose($fh);

            // Stash OK+warning rows in session for commit
            if (\PHP_SESSION_ACTIVE !== \session_status()) {
                @\session_start();
            }
            $token = bin2hex(random_bytes(16));

            $okRowsForCommit = [];
            foreach ($rows as $r) {
                if ($r['status'] === 'error') {
                    continue;
                }
                $okRowsForCommit[] = $r;
            }

            $_SESSION['bizflow_items_bulk'] ??= [];
            $_SESSION['bizflow_items_bulk'][$token] = [
                'org_id' => $orgId,
                'rows'   => $okRowsForCommit,
            ];

            $this->view('items/bulk_preview', [
                'title'       => 'Bulk item import — Preview',
                'org'         => $c['org'] ?? [],
                'module_base' => $c['module_base'] ?? '/apps/bizflow',
                'filename'    => $name,
                'rows'        => $rows,
                'stats'       => $stats,
                'bulk_token'  => $token,
            ], 'shell');

        } catch (Throwable $e) {
            $this->oops('Items bulk preview failed', $e);
        }
    }

    /**
     * POST /apps/bizflow/items/bulk-commit
     * POST /t/{slug}/apps/bizflow/items/bulk-commit
     *
     * Imports OK rows from the preview token.
     */
    public function bulkCommit(?array $ctx = null): void
    {
        $pdo = null;
        try {
            $this->postOnly();

            $c     = $this->ctx($ctx ?? []);
            $orgId = $this->requireOrg();
            $pdo   = $this->pdo();

            $token = trim((string)($_POST['bulk_token'] ?? ''));
            if ($token === '') {
                http_response_code(400);
                echo 'Missing bulk token.';
                return;
            }

            if (\PHP_SESSION_ACTIVE !== \session_status()) {
                @\session_start();
            }

            $stashAll = $_SESSION['bizflow_items_bulk'] ?? [];
            $stash    = $stashAll[$token] ?? null;

            if (!$stash || (int)($stash['org_id'] ?? 0) !== $orgId) {
                http_response_code(404);
                echo 'Bulk import session expired or not found.';
                return;
            }

            unset($_SESSION['bizflow_items_bulk'][$token]);

            $rows = $stash['rows'] ?? [];
            if (!$rows) {
                $base = $c['module_base'] ?? '/apps/bizflow';
                $this->redirect(rtrim($base, '/') . '/items');
                return;
            }

            $pdo->beginTransaction();

            foreach ($rows as $row) {
    		$status = strtolower((string)($row['status'] ?? 'ok'));

    		// NEW LOGIC: OK + warning import, only error skip
    		if ($status === 'error') {
        		continue;
    			}

    		$data = $row['data'] ?? [];
    			if (!is_array($data)) {
        		continue;
    		}

                $name       = trim((string)($data['name'] ?? ''));
                if ($name === '') {
                    continue;
                }

                $code       = trim((string)($data['code'] ?? ''));
                $itemTypeUi = strtolower((string)($data['item_type'] ?? 'product'));
                $itemType   = $itemTypeUi === 'service' ? 'service' : 'stock';

                $unit       = trim((string)($data['unit'] ?? 'pcs'));
                $purchase   = $data['purchase_price'] ?? null;
                $selling    = $data['selling_price']  ?? null;
                $catName    = trim((string)($data['category_name'] ?? ''));

                $trackInv   = (int)($data['track_inventory'] ?? ($itemType === 'service' ? 0 : 1));
                $isActive   = (int)($data['is_active'] ?? 1);

                if ($selling === null || !is_finite((float)$selling)) {
                    $selling = 0.00;
                }

                $purchase   = $purchase !== null ? (float)$purchase : 0.0;
                $margin     = null;
                if ($purchase > 0) {
                    $margin = (($selling - $purchase) / $purchase) * 100.0;
                }

                // Resolve category by name if provided
                $categoryId = null;
                if ($catName !== '') {
                    $categoryId = $this->val(
                        "SELECT id FROM biz_item_categories WHERE org_id = ? AND name = ? LIMIT 1",
                        [$orgId, $catName]
                    );
                    if ($categoryId !== null) {
                        $categoryId = (int)$categoryId;
                    }
                }

                // Text unit only for now (no hard link to biz_uoms in bulk)
                $uomId    = null;
                $unitText = $unit !== '' ? $unit : 'pcs';

                // Auto code if empty
                if ($code === '') {
                    $code = $this->generateSku($pdo, $orgId, $name);
                }

                $sql = "
                    INSERT INTO biz_items (
                        org_id,
                        category_id,
                        code,
                        name,
                        description,
                        item_type,
                        uom_id,
                        unit,
                        barcode,
                        sale_price,
                        purchase_price,
                        selling_price,
                        margin_percent,
                        last_cost_price,
                        track_inventory,
                        is_active
                    ) VALUES (
                        ?, ?, ?, ?, ?, ?,
                        ?, ?, ?, ?, ?,
                        ?, ?, ?, ?, ?
                    )
                ";

                $params = [
                    $orgId,
                    $categoryId,
                    $code,
                    $name,
                    null,                 // description left empty for bulk; user can fill later
                    $itemType,
                    $uomId,
                    $unitText,
                    null,
                    $selling,
                    $purchase,
                    $selling,
                    $margin,
                    $purchase,
                    $trackInv,
                    $isActive,
                ];

                $this->exec($sql, $params);
            }

            $pdo->commit();

            $base = $c['module_base'] ?? '/apps/bizflow';
            $this->redirect(rtrim($base, '/') . '/items');

        } catch (Throwable $e) {
            if ($pdo && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $this->oops('Items bulk commit failed', $e);
        }
    }

    
   
    private function generateSku(PDO $pdo, int $orgId, string $name): string
{
    $letters = preg_replace('/[^A-Za-z]/', '', $name);
    $letters = strtoupper($letters ?? '');

    if ($letters === '') {
        $letters = 'PRD';
    }

    $base = substr($letters, 0, 3);
    if ($base === '' || $base === false) {
        $base = 'PRD';
    }

    $year   = date('Y');
    $prefix = $base . '-' . $year . '-';  // e.g. APP-2025-

    $last = $this->val(
        "SELECT code
           FROM biz_items
          WHERE org_id = ? AND code LIKE ?
          ORDER BY code DESC
          LIMIT 1",
        [$orgId, $prefix.'%']
    );

    $next = 1;
    if ($last && preg_match('/^'.preg_quote($prefix, '/').'(\d{6})$/', (string)$last, $m)) {
        $next = (int)$m[1] + 1;
    }

    return $prefix . str_pad((string)$next, 6, '0', STR_PAD_LEFT);
}
}