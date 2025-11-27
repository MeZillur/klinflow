<?php
declare(strict_types=1);

namespace Modules\DMS\Controllers;

use PDO;

final class ProductsController extends BaseController
{
    /* =========================================================================
 * SEGMENT A: Helpers
 * ========================================================================= */
private function org(array $ctx): int { return (int)$this->orgId($ctx); }
private function base(array $ctx): string { return $this->moduleBase($ctx); }
protected function pdo(): PDO { return parent::pdo(); }

private function normalizeJson(mixed $val): ?string
{
    $s = is_string($val) ? trim($val) : '';
    if ($s === '') return null;
    $decoded = json_decode($s, true);
    if (json_last_error() === JSON_ERROR_NONE)
        return json_encode($decoded, JSON_UNESCAPED_UNICODE);
    return json_encode(['note' => $s], JSON_UNESCAPED_UNICODE);
}

/**
 * Check whether a table exists in the current schema.
 */
private function tableExists(PDO $pdo, string $table): bool
{
    $q = $pdo->prepare("
        SELECT 1
          FROM information_schema.tables
         WHERE table_schema = DATABASE()
           AND table_name = ?
         LIMIT 1
    ");
    $q->execute([$table]);
    return (bool)$q->fetchColumn();
}

/**
 * Check whether a column exists within a given table.
 */
private function colExists(PDO $pdo, string $table, string $col): bool
{
    $q = $pdo->prepare("
        SELECT 1
          FROM information_schema.columns
         WHERE table_schema = DATABASE()
           AND table_name   = ?
           AND column_name  = ?
         LIMIT 1
    ");
    $q->execute([$table, $col]);
    return (bool)$q->fetchColumn();
}

/**
 * Ensure there is a row in dms_stock_balances for (org_id, product_id),
 * setting qty/avg/last. Uses ON DUPLICATE KEY UPDATE.
 */
private function ensureBalance(PDO $pdo, int $orgId, int $productId, float $qty, float $cost): void
{
    if (!$this->tableExists($pdo, 'dms_stock_balances')) return;

    $stmt = $pdo->prepare("
        INSERT INTO dms_stock_balances
            (org_id, product_id, qty_on_hand, avg_cost, last_cost)
        VALUES (?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
            qty_on_hand = VALUES(qty_on_hand),
            avg_cost    = VALUES(avg_cost),
            last_cost   = VALUES(last_cost)
    ");
    $stmt->execute([$orgId, $productId, $qty, $cost, $cost]);
}

/**
 * Record an initial movement if a ledger/moves table exists (best-effort).
 * We simply create an “opening” type entry with qty_in = initial_qty.
 */
private function recordInitialMove(PDO $pdo, int $orgId, int $productId, float $qty, float $unitCost): void
{
    if ($qty <= 0) return;

    if ($this->tableExists($pdo, 'dms_stock_ledger')) {
        $pdo->prepare("
            INSERT INTO dms_stock_ledger
                (org_id, product_id, wh_id, txn_type, ref_table, ref_id, ref_line_id,
                 qty_in, qty_out, unit_cost, cost_total, memo, created_at, posted_at)
            VALUES
                (?, ?, NULL, 'opening', 'products', ?, NULL,
                 ?, 0, ?, ?*?, 'Opening balance', NOW(), NOW())
        ")->execute([$orgId, $productId, $productId, $qty, $unitCost, $qty, $unitCost]);
        return;
    }

    if ($this->tableExists($pdo, 'dms_stock_moves')) {
        $pdo->prepare("
            INSERT INTO dms_stock_moves
                (org_id, product_id, wh_id, qty_in, qty_out, unit_cost, move_at, memo)
            VALUES (?, ?, NULL, ?, 0, ?, NOW(), 'Opening balance')
        ")->execute([$orgId, $productId, $qty, $unitCost]);
        return;
    }

    if ($this->tableExists($pdo, 'dms_inventory_moves')) {
        $pdo->prepare("
            INSERT INTO dms_inventory_moves
                (org_id, product_id, move_type, in_qty, out_qty, unit_cost,
                 note, created_at, updated_at, posted_at)
            VALUES (?, ?, 'opening', ?, 0, ?, 'Opening balance', NOW(), NOW(), NOW())
        ")->execute([$orgId, $productId, $qty, $unitCost]);
    }
}

/**
 * Return the first existing column name in $cands for $table.
 * If found, returns it backticked (“`col`”), else returns $fallback
 * (literal like 'NULL' or '0').
 */
private function pickCol(PDO $pdo, string $table, array $cands, string $fallback = 'NULL'): string
{
    foreach ($cands as $c) {
        if ($this->colExists($pdo, $table, $c)) {
            return "`{$c}`";
        }
    }
    return $fallback;
}

/**
 * If $picked is a real (backticked) column, qualify with $alias;
 * otherwise return as-is (for literals such as 'NULL' or '0').
 */
private function withAlias(string $alias, string $picked): string
{
    return ($picked !== '' && $picked[0] === '`') ? "{$alias}.{$picked}" : $picked;
}
  

    /* =========================================================================
     * SEGMENT B: Typeahead / lookup
     * ========================================================================= */
    public function taProducts(array $ctx): void
    {
        $pdo = $this->pdo(); $orgId = $this->org($ctx);
        $q   = trim((string)($_GET['q'] ?? ''));
        $lim = max(1, min(100, (int)($_GET['limit'] ?? 30)));

        $where = ["p.org_id=?"]; $args = [$orgId];
        if ($q !== '') {
            $like = "%{$q}%";
            $where[] = "(p.name LIKE ? OR p.code LIKE ? OR p.barcode LIKE ? OR p.brand LIKE ? OR p.model LIKE ?)";
            array_push($args, $like, $like, $like, $like, $like);
        }

        $sql = "
            SELECT
              p.id, p.code, p.name, p.barcode, p.category_id, p.uom_name,
              COALESCE(v.final_price, p.unit_price) AS unit_price
            FROM dms_products p
            LEFT JOIN vw_dms_supplier_prices v
              ON v.org_id = p.org_id AND v.product_id = p.id
            WHERE ".implode(' AND ', $where)."
            ORDER BY p.name ASC, p.id DESC
            LIMIT {$lim}";
        $st = $pdo->prepare($sql); $st->execute($args);

        $this->json(['items' => $st->fetchAll(PDO::FETCH_ASSOC) ?: []]);
    }
    public function lookup(array $ctx): void { $this->taProducts($ctx); }

    /* =========================================================================
     * SEGMENT C: Index (with stock + current price)
     * ========================================================================= */
    public function index(array $ctx): void
    {
        $pdo   = $this->pdo();
        $orgId = $this->org($ctx);
        $q     = trim((string)($_GET['q'] ?? ''));

        $hasSup = $this->tableExists($pdo, 'dms_suppliers');
        $hasCat = $this->tableExists($pdo, 'dms_categories');

        $select = [
            "p.id",
            "COALESCE(p.code, CONCAT('PID-', LPAD(p.id, 4, '0'))) AS sku",
            "COALESCE(p.name_canonical, p.name) AS name",
            $hasSup ? "s.name AS supplier_name" : "NULL AS supplier_name",
            $hasCat
                ? ($this->colExists($pdo, 'dms_categories', 'name') ? "c.name" : "c.code") . " AS category_name"
                : "NULL AS category_name",
            // current price from tier (fallback to unit_price)
            "(SELECT t.base_price
               FROM dms_price_tiers t
              WHERE t.org_id=p.org_id AND t.product_id=p.id AND t.state='published'
              ORDER BY t.effective_from DESC, t.priority DESC, t.id DESC
              LIMIT 1) AS price",
            // stock
            "COALESCE(sb.qty_on_hand, 0.0) AS stock_qty",
            // status
            "CASE
                WHEN p.active IS NOT NULL THEN CASE WHEN p.active=1 THEN 'active' ELSE 'inactive' END
                WHEN p.status IS NOT NULL THEN p.status
                ELSE 'active'
             END AS status"
        ];

        $from = "FROM dms_products p";
        $joins = [];
        if ($hasSup) $joins[] = "LEFT JOIN dms_suppliers s ON s.id = p.supplier_id";
        if ($hasCat) $joins[] = "LEFT JOIN dms_categories c ON c.id = p.category_id";
        $joins[] = "LEFT JOIN dms_stock_balances sb ON sb.org_id=p.org_id AND sb.product_id=p.id";

        $where = ["p.org_id=?"]; $args = [$orgId];
        if ($q !== '') {
            $like = "%{$q}%";
            $where[] = "(COALESCE(p.name_canonical,p.name) LIKE ? OR p.code LIKE ? OR p.barcode LIKE ?)";
            array_push($args, $like, $like, $like);
        }

        $sql = "SELECT ".implode(",\n       ", $select)."\n"
             . $from."\n".implode("\n", $joins)."\n"
             . "WHERE ".implode(' AND ', $where)."\n"
             . "ORDER BY p.id DESC LIMIT 200";

        try {
            $st = $pdo->prepare($sql); $st->execute($args);
            $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (\Throwable $e) {
            $this->abort500('Failed to load products: '.$e->getMessage());
            return;
        }

        $this->view('products/index', [
            'title'       => 'Products',
            'rows'        => $rows,
            'q'           => $q,
            'module_base' => $this->base($ctx),
            'active'      => 'products',
            'subactive'   => 'products.index',
        ], $ctx);
    }

    /* =========================================================================
     * SEGMENT D: Create (same UI, robust dropdown data)
     * ========================================================================= */
    public function create(array $ctx): void
    {
        $pdo   = $this->pdo();
        $orgId = $this->org($ctx);

        $hasTbl  = fn(string $t)         => $this->tableExists($pdo, $t);
        $hasCol  = fn(string $t,string $c)=> $this->colExists($pdo, $t, $c);

        /* Categories */
        $categories = [];
        if ($hasTbl('dms_categories')) {
            $nameExpr = $hasCol('dms_categories','name') ? 'name' : 'COALESCE(code,"")';
            $codeExpr = $hasCol('dms_categories','code') ? 'code' : 'NULL';
            $where    = 'org_id = ?';
            if     ($hasCol('dms_categories','is_active')) $where .= ' AND is_active = 1';
            elseif ($hasCol('dms_categories','status'))    $where .= " AND status = 'active'";

            $sql = "SELECT id, {$nameExpr} AS name, {$codeExpr} AS code
                      FROM dms_categories
                     WHERE {$where}
                     ORDER BY name ASC, id ASC";
            $st = $pdo->prepare($sql); $st->execute([$orgId]);
            $categories = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
        }

        /* UOMs */
        $uoms = [];
        if ($hasTbl('dms_uoms')) {
            $nameExpr = $hasCol('dms_uoms','name') ? 'name' : 'COALESCE(code,"")';
            $codeExpr = $hasCol('dms_uoms','code') ? 'code' : 'NULL';
            $where    = 'org_id = ?';
            if     ($hasCol('dms_uoms','is_active')) $where .= ' AND is_active = 1';
            elseif ($hasCol('dms_uoms','status'))    $where .= " AND status = 'active'";

            $sql = "SELECT id, {$nameExpr} AS name, {$codeExpr} AS code
                      FROM dms_uoms
                     WHERE {$where}
                     ORDER BY name ASC, id ASC";
            $st = $pdo->prepare($sql); $st->execute([$orgId]);
            $uoms = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
        }

        /* Suppliers */
        $suppliers = [];
        if ($hasTbl('dms_suppliers')) {
            $cols = [];
            try { $cols = $pdo->query("SHOW COLUMNS FROM dms_suppliers")->fetchAll(PDO::FETCH_COLUMN) ?: []; } catch (\Throwable $e) {}

            $nameCandidates = ['name','company','business_name','company_name','full_name','contact_name'];
            $codeCandidates = ['supplier_code','code','sr_code','reg_no'];

            $nameParts = [];
            foreach ($nameCandidates as $c) if (in_array($c, $cols, true)) $nameParts[] = "NULLIF(TRIM($c),'')";
            $codeParts = [];
            foreach ($codeCandidates as $c) if (in_array($c, $cols, true)) $codeParts[] = "NULLIF(TRIM($c),'')";

            $nameExpr = $nameParts ? ('COALESCE('.implode(',', $nameParts).')') : "NULL";
            $codeExpr = $codeParts ? ('COALESCE('.implode(',', $codeParts).')') : "NULL";

            $where = 'org_id = ?';
            if     ($hasCol('dms_suppliers','is_active')) $where .= ' AND is_active = 1';
            elseif ($hasCol('dms_suppliers','status'))    $where .= " AND status = 'active'";

            $sql = "SELECT id, {$nameExpr} AS name, {$codeExpr} AS code
                      FROM dms_suppliers
                     WHERE {$where}
                     ORDER BY name ASC, code ASC, id ASC";
            $st = $pdo->prepare($sql); $st->execute([$orgId]);
            $suppliers = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
            foreach ($suppliers as &$s) { $s['display'] = trim((string)($s['name'] ?? '')); }
            unset($s);
        }

        /* Optional quick picks */
        $productsQuick = [];
        if ($hasTbl('dms_products')) {
            $cols = ['id'];
            $cols[] = $hasCol('dms_products','name_canonical') ? 'name_canonical AS name' : 'name';
            if ($hasCol('dms_products','code'))        $cols[] = 'code';
            if ($hasCol('dms_products','unit_price'))  $cols[] = 'unit_price';
            if ($hasCol('dms_products','category_id')) $cols[] = 'category_id';
            if ($hasCol('dms_products','uom_name'))    $cols[] = 'uom_name';

            $sql = "SELECT ".implode(',', $cols)."
                      FROM dms_products
                     WHERE org_id = ?
                     ORDER BY id DESC
                     LIMIT 80";
            $st = $pdo->prepare($sql); $st->execute([$orgId]);
            $productsQuick = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
        }

        $this->view('products/create', [
            'title'         => 'Add Product',
            'categories'    => $categories,
            'uoms'          => $uoms,
            'suppliers'     => $suppliers,
            'productsQuick' => $productsQuick,
            'module_base'   => $this->base($ctx),
            'active'        => 'products',
            'subactive'     => 'products.create',
        ], $ctx);
    }
  
  
         /* =========================================================================
     * SEGMENT J: Bulk import (from /products/create bulk tab)
     * ========================================================================= */
    public function bulk(array $ctx): void
    {
        // POST only
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            $this->abort405('POST only');
            return;
        }

        $pdo   = $this->pdo();
        $orgId = $this->org($ctx);

        $raw = trim((string)($_POST['bulk_json'] ?? ''));
        if ($raw === '') {
            $this->abort400('No bulk data received.');
            return;
        }

        $rows = json_decode($raw, true);
        if (!is_array($rows)) {
            $this->abort400('Bulk payload is not valid JSON.');
            return;
        }

        $hasCol = fn(string $t, string $c) => $this->colExists($pdo, $t, $c);

        $pdo->beginTransaction();
        try {
            foreach ($rows as $row) {
                if (!is_array($row)) continue;

                $name = trim((string)($row['name'] ?? ''));
                if ($name === '') {
                    // skip completely invalid / empty rows
                    continue;
                }

                $code          = trim((string)($row['code'] ?? ''));
                $categoryId    = isset($row['category_id']) && $row['category_id'] !== '' ? (int)$row['category_id'] : null;
                $uomId         = isset($row['uom_id']) && $row['uom_id'] !== '' ? (int)$row['uom_id'] : null;

                // NEW: optional purchase_price from bulk; if absent, 0.0
                $purchasePrice = isset($row['purchase_price']) && $row['purchase_price'] !== ''
                    ? (float)$row['purchase_price'] : 0.0;

                $salesPrice    = isset($row['unit_price']) && $row['unit_price'] !== ''
                    ? (float)$row['unit_price'] : null;

                $initialQty    = isset($row['initial_qty']) && $row['initial_qty'] !== ''
                    ? (float)$row['initial_qty'] : 0.0;
                $barcode       = trim((string)($row['barcode'] ?? ''));
                $arrivalDate   = trim((string)($row['arrival_date'] ?? '')) ?: null;
                $expiryDate    = trim((string)($row['expiry_date'] ?? '')) ?: null;

                $status = (string)($row['status'] ?? 'active');
                $status = $status === 'inactive' ? 'inactive' : 'active';

                // -------- supplier resolution by name (schema-safe) --------
                $supplierName = trim((string)($row['supplier_name'] ?? ''));
                $supplierId   = null;
                if ($supplierName !== '' && $this->tableExists($pdo, 'dms_suppliers')) {
                    $cols = [];
                    try {
                        $cols = $pdo->query("SHOW COLUMNS FROM dms_suppliers")
                                    ->fetchAll(PDO::FETCH_COLUMN) ?: [];
                    } catch (\Throwable $e) {
                        $cols = [];
                    }

                    $nameCandidates = ['name','company','business_name','company_name','full_name','contact_name'];
                    $whereParts     = [];
                    $params         = [$orgId];

                    foreach ($nameCandidates as $c) {
                        if (in_array($c, $cols, true)) {
                            $whereParts[] = "$c = ?";
                            $params[]     = $supplierName;
                        }
                    }

                    if ($whereParts) {
                        $sqlSup = "SELECT id
                                     FROM dms_suppliers
                                    WHERE org_id = ?
                                      AND (" . implode(' OR ', $whereParts) . ")
                                    ORDER BY id ASC
                                    LIMIT 1";
                        $q = $pdo->prepare($sqlSup);
                        $q->execute($params);
                        $sid = $q->fetchColumn();
                        if ($sid !== false) {
                            $supplierId = (int)$sid;
                        }
                    }
                }

                // ----- INSERT into dms_products (schema-safe, like store()) -----
                $cols = [];
                $vals = [];
                $args = [];

                if ($hasCol('dms_products', 'org_id'))          { $cols[] = 'org_id';          $vals[] = '?';  $args[] = $orgId; }
                if ($hasCol('dms_products', 'code'))            { $cols[] = 'code';            $vals[] = '?';  $args[] = $code !== '' ? $code : null; }
                if ($hasCol('dms_products', 'name_canonical'))  { $cols[] = 'name_canonical';  $vals[] = '?';  $args[] = $name; }
                elseif ($hasCol('dms_products', 'name'))        { $cols[] = 'name';            $vals[] = '?';  $args[] = $name; }

                if ($hasCol('dms_products', 'supplier_id'))     { $cols[] = 'supplier_id';     $vals[] = '?';  $args[] = $supplierId; }
                if ($hasCol('dms_products', 'supplier_name'))   { $cols[] = 'supplier_name';   $vals[] = '?';  $args[] = $supplierName !== '' ? $supplierName : null; }

                if ($hasCol('dms_products', 'category_id'))     { $cols[] = 'category_id';     $vals[] = '?';  $args[] = $categoryId; }
                if ($hasCol('dms_products', 'uom_id'))          { $cols[] = 'uom_id';          $vals[] = '?';  $args[] = $uomId; }
                if ($hasCol('dms_products', 'uom'))             { $cols[] = 'uom';             $vals[] = '?';  $args[] = null; } // no free-text unit from bulk UI (yet)

                if ($hasCol('dms_products', 'purchase_price'))  { $cols[] = 'purchase_price';  $vals[] = '?';  $args[] = $purchasePrice; }
                if ($hasCol('dms_products', 'unit_price'))      { $cols[] = 'unit_price';      $vals[] = '?';  $args[] = $salesPrice; }
                if ($hasCol('dms_products', 'initial_qty'))     { $cols[] = 'initial_qty';     $vals[] = '?';  $args[] = $initialQty; }

                if ($hasCol('dms_products', 'arrival_date'))    { $cols[] = 'arrival_date';    $vals[] = '?';  $args[] = $arrivalDate; }
                if ($hasCol('dms_products', 'expiry_date'))     { $cols[] = 'expiry_date';     $vals[] = '?';  $args[] = $expiryDate; }
                if ($hasCol('dms_products', 'barcode'))         { $cols[] = 'barcode';         $vals[] = '?';  $args[] = $barcode !== '' ? $barcode : null; }

                if ($hasCol('dms_products', 'status'))          { $cols[] = 'status';          $vals[] = '?';  $args[] = $status; }
                if ($hasCol('dms_products', 'active'))          { $cols[] = 'active';          $vals[] = '?';  $args[] = $status === 'active' ? 1 : 0; }

                if ($hasCol('dms_products', 'created_at'))      { $cols[] = 'created_at';      $vals[] = 'NOW()'; }
                if ($hasCol('dms_products', 'updated_at'))      { $cols[] = 'updated_at';      $vals[] = 'NOW()'; }

                if (!$cols) {
                    // safety: if schema is weird, skip this row
                    continue;
                }

                $sqlIns = "INSERT INTO dms_products (".implode(',', $cols).") VALUES (".implode(',', $vals).")";
                $pdo->prepare($sqlIns)->execute($args);

                $productId = (int)$pdo->lastInsertId();

                // ----- seed stock balance + opening movement -----
                $this->ensureBalance($pdo, $orgId, $productId, $initialQty, $purchasePrice);
                $this->recordInitialMove($pdo, $orgId, $productId, $initialQty, $purchasePrice);

                // ----- publish price tier from Sales Price (BDT) -----
                if ($salesPrice !== null && $this->tableExists($pdo, 'dms_price_tiers')) {
                    $pdo->prepare("
                        INSERT INTO dms_price_tiers
                          (org_id, product_id, currency, channel, customer_segment,
                           effective_from, effective_to, min_qty, max_qty,
                           base_price, priority, state, created_at, updated_at)
                        VALUES
                          (?, ?, 'BDT', 'default', 'default',
                           NOW(), NULL, 1, NULL,
                           ?, 10, 'published', NOW(), NOW())
                    ")->execute([$orgId, $productId, $salesPrice]);
                }
            }

            $pdo->commit();
            $this->redirect($this->base($ctx).'/products');

        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $this->abort500('Bulk import failed: '.$e->getMessage());
        }
    }
  
  
  
  
      /* =========================================================================
     * SEGMENT E: Store (create product, seed stock balance, publish price tier)
     * ========================================================================= */
    public function store(array $ctx): void
    {
        $pdo   = $this->pdo();
        $orgId = $this->org($ctx);

        // ---- form inputs from your create page ----
        $name          = trim((string)($_POST['name'] ?? ''));
        $code          = trim((string)($_POST['code'] ?? ''));
        $supplierId    = (int)($_POST['supplier_id'] ?? 0) ?: null;
        $categoryId    = (int)($_POST['category_id'] ?? 0) ?: null;
        $uomId         = (int)($_POST['uom_id'] ?? 0) ?: null;
        $uomName       = trim((string)($_POST['uom_name'] ?? ''));

        $purchasePrice = (float)($_POST['purchase_price'] ?? 0);
        $salesPrice    = ($_POST['unit_price'] ?? '') === '' ? null : (float)$_POST['unit_price'];
        $marginPct     = (float)($_POST['margin_pct'] ?? 0);
        $initialQty    = (float)($_POST['initial_qty'] ?? 0);

        $arrivalDate   = !empty($_POST['arrival_date']) ? $_POST['arrival_date'] : null;
        $expiryDate    = !empty($_POST['expiry_date'])  ? $_POST['expiry_date']  : null;
        $barcode       = trim((string)($_POST['barcode'] ?? ''));
        $specJson      = $this->normalizeJson($_POST['spec_json'] ?? '');
        $status        = (string)($_POST['status'] ?? 'active');
        $status        = $status === 'inactive' ? 'inactive' : 'active';

        if ($name === '') $this->abort400('Product name is required.');

        $hasCol = fn(string $t,string $c)=>$this->colExists($pdo,$t,$c);

        $pdo->beginTransaction();
        try {
            // ---- insert product (robust to schema) ----
            $cols = []; $vals = []; $args = [];

            if ($hasCol('dms_products','org_id'))          { $cols[]='org_id';          $vals[]='?'; $args[]=$orgId; }
            if ($hasCol('dms_products','code'))            { $cols[]='code';            $vals[]='?'; $args[]=$code ?: null; }
            if     ($hasCol('dms_products','name_canonical')) { $cols[]='name_canonical'; $vals[]='?'; $args[]=$name; }
            elseif ($hasCol('dms_products','name'))           { $cols[]='name';           $vals[]='?'; $args[]=$name; }
            if ($hasCol('dms_products','supplier_id'))     { $cols[]='supplier_id';     $vals[]='?'; $args[]=$supplierId; }
            if ($hasCol('dms_products','category_id'))     { $cols[]='category_id';     $vals[]='?'; $args[]=$categoryId; }
            if ($hasCol('dms_products','uom'))             { $cols[]='uom';             $vals[]='?'; $args[]=$uomName !== '' ? $uomName : null; }
            if ($hasCol('dms_products','uom_id'))          { $cols[]='uom_id';          $vals[]='?'; $args[]=$uomId ?: null; }
            if ($hasCol('dms_products','purchase_price'))  { $cols[]='purchase_price';  $vals[]='?'; $args[]=$purchasePrice; }
            if ($hasCol('dms_products','unit_price'))      { $cols[]='unit_price';      $vals[]='?'; $args[]=$salesPrice; }
            if ($hasCol('dms_products','margin_pct'))      { $cols[]='margin_pct';      $vals[]='?'; $args[]=$marginPct; }
            if ($hasCol('dms_products','initial_qty'))     { $cols[]='initial_qty';     $vals[]='?'; $args[]=$initialQty; }
            if ($hasCol('dms_products','arrival_date'))    { $cols[]='arrival_date';    $vals[]='?'; $args[]=$arrivalDate; }
            if ($hasCol('dms_products','expiry_date'))     { $cols[]='expiry_date';     $vals[]='?'; $args[]=$expiryDate; }
            if ($hasCol('dms_products','barcode'))         { $cols[]='barcode';         $vals[]='?'; $args[]=$barcode ?: null; }
            if ($hasCol('dms_products','attributes'))      { $cols[]='attributes';      $vals[]='?'; $args[]=$specJson; }
            elseif ($hasCol('dms_products','spec_json'))   { $cols[]='spec_json';       $vals[]='?'; $args[]=$specJson; }
            if ($hasCol('dms_products','active'))          { $cols[]='active';          $vals[]='?'; $args[] = ($status==='active')?1:0; }
            if ($hasCol('dms_products','status'))          { $cols[]='status';          $vals[]='?'; $args[] = $status; }
            if ($hasCol('dms_products','created_at'))      { $cols[]='created_at';      $vals[]='NOW()'; }
            if ($hasCol('dms_products','updated_at'))      { $cols[]='updated_at';      $vals[]='NOW()'; }

            if (!$cols) throw new \RuntimeException('No compatible columns in dms_products');

            $sql = "INSERT INTO dms_products (".implode(',', $cols).") VALUES (".implode(',', $vals).")";
            $pdo->prepare($sql)->execute($args);

            $productId = (int)$pdo->lastInsertId();

            // ---- seed stock balance + initial movement (this was the missing piece) ----
            $this->ensureBalance($pdo, $orgId, $productId, $initialQty, $purchasePrice);
            $this->recordInitialMove($pdo, $orgId, $productId, $initialQty, $purchasePrice);

            // ---- initial published price tier from the Sales Price field ----
            if ($salesPrice !== null && $this->tableExists($pdo, 'dms_price_tiers')) {
                $pdo->prepare("
                    INSERT INTO dms_price_tiers
                      (org_id, product_id, currency, channel, customer_segment,
                       effective_from, effective_to, min_qty, max_qty,
                       base_price, discount_pct, discount_abs, commission_pct, commission_abs,
                       tax_included, priority, state, created_at, updated_at)
                    VALUES
                      (?, ?, 'BDT', 'default', 'default',
                       NOW(), NULL, 1, NULL,
                       ?, NULL, NULL, NULL, NULL,
                       0, 10, 'published', NOW(), NOW())
                ")->execute([$orgId, $productId, $salesPrice]);
            }

            $pdo->commit();

            // go to tiers page (as before)
            $this->redirect($this->base($ctx).'/products/'.$productId.'/tiers');

        } catch (\Throwable $e) {
            $pdo->rollBack();
            $this->abort500('Save failed: '.$e->getMessage());
        }
    }

    /* =========================================================================
 * SEGMENT F: Show (details + current price + movements + purchases + tiers)
 * ========================================================================= */
public function show(array $ctx, int $id): void
{
    $pdo   = $this->pdo();
    $orgId = $this->org($ctx);

    /* -------- 1) Base product row -------- */
    $st = $pdo->prepare("SELECT * FROM dms_products WHERE org_id=? AND id=? LIMIT 1");
    $st->execute([$orgId, $id]);
    $p = $st->fetch(PDO::FETCH_ASSOC);
    if (!$p) { $this->abort404('Product not found.'); return; }

    /* -------- 2) Enrich pretty fields (supplier/category/uom) -------- */
    if (empty($p['supplier_name']) && !empty($p['supplier_id']) && $this->tableExists($pdo,'dms_suppliers')) {
        $nameCol = $this->colExists($pdo,'dms_suppliers','name')
            ? 'name'
            : 'COALESCE(company,company_name,full_name,contact_name)';
        $s = $pdo->prepare("SELECT {$nameCol} AS name FROM dms_suppliers WHERE id=? AND org_id=? LIMIT 1");
        $s->execute([(int)$p['supplier_id'], $orgId]);
        if ($v = $s->fetchColumn()) $p['supplier_name'] = (string)$v;
    }

    if (empty($p['category_name']) && !empty($p['category_id']) && $this->tableExists($pdo,'dms_categories')) {
        $cname = $this->colExists($pdo,'dms_categories','name') ? 'name' : 'code';
        $c = $pdo->prepare("SELECT {$cname} FROM dms_categories WHERE id=? AND org_id=? LIMIT 1");
        $c->execute([(int)$p['category_id'], $orgId]);
        if ($v = $c->fetchColumn()) $p['category_name'] = (string)$v;
    }

    if (empty($p['uom_name'])) {
        if (!empty($p['uom'])) $p['uom_name'] = (string)$p['uom'];
        elseif (!empty($p['uom_id']) && $this->tableExists($pdo,'dms_uoms')) {
            $uname = $this->colExists($pdo,'dms_uoms','name') ? 'name' : 'code';
            $u = $pdo->prepare("SELECT {$uname} FROM dms_uoms WHERE id=? AND org_id=? LIMIT 1");
            $u->execute([(int)$p['uom_id'], $orgId]);
            if ($v = $u->fetchColumn()) $p['uom_name'] = (string)$v;
        }
    }

    /* -------- 3) Pricing: current + upcoming tier -------- */
    $price = null; $nextTier = null;
    if ($this->tableExists($pdo,'dms_price_tiers')) {
        $sqlCur = "SELECT base_price, discount_pct, discount_abs, commission_pct, commission_abs,
                          effective_from, priority,
                          COALESCE(base_price,0) as final_price
                     FROM dms_price_tiers
                    WHERE org_id=? AND product_id=? AND state='published' AND effective_from <= NOW()
                 ORDER BY effective_from DESC, priority DESC, id DESC
                    LIMIT 1";
        $s = $pdo->prepare($sqlCur); $s->execute([$orgId, $id]);
        $row = $s->fetch(PDO::FETCH_ASSOC);
        if ($row) $price = (float)($row['final_price'] ?? $row['base_price'] ?? 0);

        $sqlNext = "SELECT id, effective_from, base_price,
                           COALESCE(base_price,0) as final_price
                      FROM dms_price_tiers
                     WHERE org_id=? AND product_id=? AND state='published' AND effective_from > NOW()
                  ORDER BY effective_from ASC, priority DESC, id DESC
                     LIMIT 1";
        $s = $pdo->prepare($sqlNext); $s->execute([$orgId, $id]);
        $nextTier = $s->fetch(PDO::FETCH_ASSOC) ?: null;
        if ($nextTier) $p['next_tier'] = $nextTier;
    }
    if ($price === null) $price = isset($p['unit_price']) ? (float)$p['unit_price'] : null;
    $p['price'] = $price;

    /* -------- 4) Stock balance -------- */
    $sbQty = 0.0;
    if ($this->tableExists($pdo, 'dms_stock_balances')) {
        $q = $pdo->prepare("SELECT qty_on_hand FROM dms_stock_balances WHERE org_id=? AND product_id=? LIMIT 1");
        $q->execute([$orgId, $id]);
        $v = $q->fetchColumn();
        if ($v !== false) $sbQty = (float)$v;
    }

    /* -------- 5) Movements (normalised + column-safe) -------- */
    $moves = [];
    if ($this->tableExists($pdo, 'dms_stock_ledger')) {
        $refCol = $this->colExists($pdo, 'dms_stock_ledger', 'ref_no')
            ? 'ref_no'
            : ($this->colExists($pdo, 'dms_stock_ledger', 'ref')
                ? 'ref'
                : ($this->colExists($pdo, 'dms_stock_ledger', 'txn_ref') ? 'txn_ref' : 'NULL'));
        $sql = "SELECT created_at AS created_at,
                       txn_type  AS move_type,
                       qty_in    AS in_qty,
                       qty_out   AS out_qty,
                       {$refCol} AS ref_no,
                       memo      AS note
                  FROM dms_stock_ledger
                 WHERE org_id=? AND product_id=?
              ORDER BY id DESC
                 LIMIT 200";
        $s = $pdo->prepare($sql); $s->execute([$orgId, $id]);
        $moves = $s->fetchAll(PDO::FETCH_ASSOC) ?: [];

    } elseif ($this->tableExists($pdo, 'dms_stock_moves')) {
        $refCol = $this->colExists($pdo, 'dms_stock_moves', 'ref_no')
            ? 'ref_no'
            : ($this->colExists($pdo, 'dms_stock_moves', 'ref') ? 'ref' : 'NULL');
        $sql = "SELECT move_at   AS created_at,
                       'move'     AS move_type,
                       qty_in     AS in_qty,
                       qty_out    AS out_qty,
                       {$refCol}  AS ref_no,
                       memo       AS note
                  FROM dms_stock_moves
                 WHERE org_id=? AND product_id=?
              ORDER BY id DESC
                 LIMIT 200";
        $s = $pdo->prepare($sql); $s->execute([$orgId, $id]);
        $moves = $s->fetchAll(PDO::FETCH_ASSOC) ?: [];

    } elseif ($this->tableExists($pdo, 'dms_inventory_moves')) {
        $refCol = $this->colExists($pdo, 'dms_inventory_moves', 'ref_no')
            ? 'ref_no'
            : ($this->colExists($pdo, 'dms_inventory_moves', 'ref') ? 'ref' : 'NULL');
        $sql = "SELECT created_at AS created_at,
                       move_type  AS move_type,
                       in_qty     AS in_qty,
                       out_qty    AS out_qty,
                       {$refCol}  AS ref_no,
                       note       AS note
                  FROM dms_inventory_moves
                 WHERE org_id=? AND product_id=?
              ORDER BY id DESC
                 LIMIT 200";
        $s = $pdo->prepare($sql); $s->execute([$orgId, $id]);
        $moves = $s->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /* -------- 6) Purchases (column-safe across schemas) -------- */
    $purchases = [];
    if ($this->tableExists($pdo, 'dms_purchase_items') && $this->tableExists($pdo, 'dms_purchases')) {
        $supJoin = $this->tableExists($pdo, 'dms_suppliers') ? "LEFT JOIN dms_suppliers s ON s.id = pu.supplier_id" : "";
        $supName = $this->tableExists($pdo, 'dms_suppliers')
            ? ($this->colExists($pdo, 'dms_suppliers', 'name')
                ? 's.name'
                : 'COALESCE(s.company,s.company_name,s.full_name,s.contact_name)')
            : "NULL";

        // discover items columns
        $qtyPick  = $this->pickCol($pdo, 'dms_purchase_items', ['qty','quantity','qty_in'], '0');
        $costPick = $this->pickCol($pdo, 'dms_purchase_items', ['unit_cost','cost','unit_price','price','rate','unit_rate'], '0');
        $notePick = $this->pickCol($pdo, 'dms_purchase_items', ['note','notes','memo','remark'], 'NULL');

        // qualify only when they are real columns (backticked)
        $qtyExpr  = $this->withAlias('it', $qtyPick);
        $costExpr = $this->withAlias('it', $costPick);
        $noteExpr = $this->withAlias('it', $notePick);

        // reference column on header
        $refPick  = $this->pickCol($pdo, 'dms_purchases', ['ref_no','ref','reference','voucher_no','invoice_no'], 'NULL');
        $refExpr  = $this->withAlias('pu', $refPick);

        $sql = "SELECT pu.created_at,
                       {$supName} AS supplier_name,
                       {$qtyExpr}   AS qty,
                       {$costExpr}  AS unit_cost,
                       ({$qtyExpr} * {$costExpr}) AS total,
                       {$refExpr}   AS ref_no,
                       {$noteExpr}  AS note
                  FROM dms_purchase_items it
                  JOIN dms_purchases pu ON pu.id = it.purchase_id AND pu.org_id = it.org_id
                  {$supJoin}
                 WHERE it.org_id=? AND it.product_id=?
              ORDER BY it.id DESC
                 LIMIT 200";
        $s = $pdo->prepare($sql); $s->execute([$orgId, $id]);
        $purchases = $s->fetchAll(PDO::FETCH_ASSOC) ?: [];

    } elseif ($this->tableExists($pdo, 'dms_inventory_moves')) {
        $qtyPick  = $this->pickCol($pdo, 'dms_inventory_moves', ['in_qty','qty','quantity'], '0');
        $costPick = $this->pickCol($pdo, 'dms_inventory_moves', ['unit_cost','cost','avg_cost','price','rate'], '0');
        $refPick  = $this->pickCol($pdo, 'dms_inventory_moves', ['ref_no','ref','reference','voucher_no','invoice_no'], 'NULL');
        $notePick = $this->pickCol($pdo, 'dms_inventory_moves', ['note','notes','memo','remark'], 'NULL');

        // here we don’t need aliasing since we select from a single table
        $sql = "SELECT created_at,
                       {$qtyPick}  AS qty,
                       {$costPick} AS unit_cost,
                       ({$qtyPick} * {$costPick}) AS total,
                       {$refPick}  AS ref_no,
                       {$notePick} AS note,
                       NULL        AS supplier_name
                  FROM dms_inventory_moves
                 WHERE org_id=? AND product_id=? AND move_type='purchase'
              ORDER BY id DESC
                 LIMIT 200";
        $s = $pdo->prepare($sql); $s->execute([$orgId, $id]);
        $purchases = $s->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /* -------- 7) Recent tiers (for Pricing tab) -------- */
    $recentTiers = [];
    if ($this->tableExists($pdo,'dms_price_tiers')) {
        $sql = "SELECT state, effective_from, min_qty, max_qty,
                       base_price, discount_pct, commission_pct, tax_included, priority
                  FROM dms_price_tiers
                 WHERE org_id=? AND product_id=?
              ORDER BY effective_from DESC, priority DESC, id DESC
                 LIMIT 20";
        $s = $pdo->prepare($sql); $s->execute([$orgId,$id]);
        $recentTiers = $s->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /* -------- 8) Render -------- */
    $this->view('products/show', [
        'title'         => 'Product Details',
        'product'       => $p,
        'moves'         => $moves,
        'purchases'     => $purchases,
        'recent_tiers'  => $recentTiers,
        'stock_qty'     => $sbQty,
        'module_base'   => $this->base($ctx),
        'active'        => 'products',
        'subactive'     => 'products.view',
    ], $ctx);
}
    /* =========================================================================
     * SEGMENT G: Edit (simple fetch + reuse the create view or your edit view)
     * ========================================================================= */
    public function edit(array $ctx, int $id): void
    {
        $pdo = $this->pdo(); $orgId = $this->org($ctx);

        $st = $pdo->prepare("SELECT * FROM dms_products WHERE org_id=? AND id=? LIMIT 1");
        $st->execute([$orgId, $id]); $p = $st->fetch(PDO::FETCH_ASSOC);
        if (!$p) { $this->abort404('Product not found.'); return; }

        // you can have a dedicated 'products/edit' view; here I reuse 'products/create' data loaders:
        // for brevity, we’ll just open a simple 'show' page with an Edit button wired already.
        $this->view('products/show', [
            'title'       => 'Product Details',
            'product'     => $p,
            'moves'       => [],
            'stock_qty'   => 0,
            'module_base' => $this->base($ctx),
            'active'      => 'products',
            'subactive'   => 'products.edit',
        ], $ctx);
    }

    /* =========================================================================
     * SEGMENT H: Update (metadata + optional tier draft->publish)
     * ========================================================================= */
    public function update(array $ctx, int $id): void
    {
        $pdo = $this->pdo(); $orgId = $this->org($ctx);

        $code        = trim((string)($_POST['code'] ?? ''));
        $name        = trim((string)($_POST['name'] ?? ''));
        $brand       = trim((string)($_POST['brand'] ?? ''));
        $model       = trim((string)($_POST['model'] ?? ''));
        $category    = (int)($_POST['category_id'] ?? 0) ?: null;

        $supplierId  = (int)($_POST['supplier_id'] ?? 0) ?: null;
        $supplierName= trim((string)($_POST['supplier_name'] ?? ''));

        $uomId       = (int)($_POST['uom_id'] ?? 0) ?: null;
        $uomName     = trim((string)($_POST['uom_name'] ?? ''));

        $salesPrice  = ($_POST['unit_price'] ?? '') === '' ? null : (float)$_POST['unit_price'];
        $barcode     = trim((string)($_POST['barcode'] ?? ''));
        $specJson    = $this->normalizeJson($_POST['spec_json'] ?? '');

        $arrival     = trim((string)($_POST['arrival_date'] ?? '')) ?: null;
        $expiry      = trim((string)($_POST['expiry_date'] ?? ''))  ?: null;

        $status      = (string)($_POST['status'] ?? 'active');
        $status      = $status === 'inactive' ? 'inactive' : 'active';

        if ($name === '') $this->abort400('Product name is required.');

        $pdo->beginTransaction();
        try {
            $sql = "UPDATE dms_products
                       SET code=?, name=?, name_canonical=?, brand=?, model=?, category_id=?,
                           supplier_id=?, supplier_name=?,
                           uom_id=?, uom_name=?,
                           unit_price=?, barcode=?, spec_json=?,
                           arrival_date=?, expiry_date=?,
                           status=?, updated_at=NOW()
                     WHERE org_id=? AND id=? LIMIT 1";
            $pdo->prepare($sql)->execute([
                $code, $name, $name, $brand, $model, $category,
                $supplierId ?: null, ($supplierName !== '' ? $supplierName : null),
                $uomId ?: null, ($uomName !== '' ? $uomName : null),
                $salesPrice, $barcode, $specJson,
                $arrival, $expiry,
                $status, $orgId, $id
            ]);

            if ($salesPrice !== null && $this->tableExists($pdo, 'dms_price_tiers')) {
                // create a draft tier and publish (if you have a procedure); otherwise publish directly
                $pdo->prepare("
                    INSERT INTO dms_price_tiers
                    (org_id, product_id, currency, channel, customer_segment,
                     effective_from, effective_to, min_qty, max_qty,
                     base_price, priority, state, created_by, created_at, updated_at)
                    VALUES (?, ?, 'BDT', 'default', 'default',
                            NOW(), NULL, 1, NULL,
                            ?, 10, 'published', 'products.update', NOW(), NOW())
                ")->execute([$orgId, $id, $salesPrice]);
            }

            $pdo->commit();
            $this->redirect($this->base($ctx).'/products/'.$id);
        } catch (\Throwable $e) {
            $pdo->rollBack();
            $this->abort500('Update failed: '.$e->getMessage());
        }
    }

    /* =========================================================================
     * SEGMENT I: Destroy
     * ========================================================================= */
    public function destroy(array $ctx, int $id): void
    {
        $pdo = $this->pdo(); $orgId = $this->org($ctx);
        try {
            $d = $pdo->prepare("DELETE FROM dms_products WHERE org_id=? AND id=? LIMIT 1");
            $d->execute([$orgId, $id]);
            $this->redirect($this->base($ctx).'/products');
        } catch (\Throwable $e) {
            $this->abort500('Delete failed: '.$e->getMessage());
        }
    }
}