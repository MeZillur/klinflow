<?php
declare(strict_types=1);

namespace Modules\DMS\Controllers;

use PDO;

final class PurchasesController extends BaseController
{
    /** Cached logical→physical column map for dms_purchases */
    private static array $COLS = [];

    /* ─────────────────────────── helpers ─────────────────────────── */

    /** Backtick-quote an identifier safely */
    private function ident(string $name): string
    {
        return '`' . str_replace('`', '``', $name) . '`';
    }

    /** Does table have column? (cached) */
    private function hasCol(PDO $pdo, string $table, string $col): bool
    {
        static $cache = [];
        $key = strtolower($table.'.'.$col);
        if (array_key_exists($key, $cache)) return $cache[$key];

        $q = $pdo->prepare("
            SELECT 1
              FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME   = ?
               AND COLUMN_NAME  = ?
             LIMIT 1
        ");
        $q->execute([$table, $col]);
        return $cache[$key] = (bool)$q->fetchColumn();
    }

    /** First existing column from a list, else null */
    private function pickCol(PDO $pdo, string $table, array $cands): ?string
    {
        foreach ($cands as $c) {
            if ($this->hasCol($pdo, $table, $c)) return $c;
        }
        return null;
    }

    /** Tolerant column map for dms_purchase_items */
    private function itemColMap(PDO $pdo): array
    {
        return [
            'product_id'   => $this->pickCol($pdo,'dms_purchase_items',['product_id','pid','item_id','product']),
            'product_name' => $this->pickCol($pdo,'dms_purchase_items',['product_name','name','item_name','title']),
            'qty'          => $this->pickCol($pdo,'dms_purchase_items',['qty','quantity']),
            'unit_price'   => $this->pickCol($pdo,'dms_purchase_items',['unit_price','price','rate','cost']),
            'line_total'   => $this->pickCol($pdo,'dms_purchase_items',['line_total','amount','total']),
            'created_at'   => $this->hasCol($pdo,'dms_purchase_items','created_at') ? 'created_at' : null,
        ];
    }

    /* ───────────────────── purchases column map ──────────────────── */

    /** Map logical → physical columns by inspecting INFORMATION_SCHEMA once */
    private function cols(PDO $pdo): array
    {
        if (self::$COLS) return self::$COLS;

        $want = [
            'no'          => ['bill_no','purchase_no','ref_no','invoice_no','doc_no','number','code'],
            'date'        => ['bill_date','purchase_date','date','doc_date','created_at'],
            'supplier_id' => ['supplier_id','vendor_id','party_id'],
            'supplier'    => ['supplier_name','vendor_name','party_name','name'],
            'grand'       => ['grand_total','total','amount','net_total','payable'],
            'status'      => ['status','state'],
            'notes'       => ['notes','remarks','comment'],
            'created'     => ['created_at','createdon','created'],
            'updated'     => ['updated_at','updatedon','updated'],
        ];

        $cols = [];
        $st = $pdo->query("
            SELECT COLUMN_NAME
              FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME   = 'dms_purchases'
        ");
        foreach (($st->fetchAll(PDO::FETCH_COLUMN) ?: []) as $c) {
            $cols[strtolower($c)] = $c;
        }

        $map = [];
        foreach ($want as $logical => $cands) {
            foreach ($cands as $cand) {
                $lc = strtolower($cand);
                if (isset($cols[$lc])) { $map[$logical] = $cols[$lc]; break; }
            }
        }

        // safe defaults
        $map += [
            'no'          => 'id',
            'date'        => 'created_at',
            'supplier_id' => 'supplier_id',
            'supplier'    => 'supplier_name',
            'grand'       => 'grand_total',
            'status'      => 'status',
            'notes'       => 'notes',
            'created'     => 'created_at',
            'updated'     => 'updated_at',
        ];

        return self::$COLS = $map;
    }

    /** Generate next purchase number: PUR-YYYY-MM-0001 per org+YYYY-MM */
    private function nextPurchaseNo(PDO $pdo, int $orgId, string $noCol): string
    {
        // Guard: only attempt when the column exists and isn’t "id"
        if ($noCol === 'id' || !$this->hasCol($pdo,'dms_purchases',$noCol)) {
            return '';
        }

        $col = $this->ident($noCol);
        $ym     = date('Y-m');
        $prefix = "PUR-{$ym}-";
        $like   = $prefix.'%';

        $sql = "
            SELECT MAX(CAST(SUBSTRING($col, LENGTH(?) + 1) AS UNSIGNED)) AS mx
              FROM dms_purchases
             WHERE org_id = ? AND $col LIKE ?
        ";
        $st = $pdo->prepare($sql);
        $st->execute([$prefix, $orgId, $like]);
        $mx  = (int)($st->fetchColumn() ?: 0);

        return $prefix . str_pad((string)($mx + 1), 4, '0', STR_PAD_LEFT);
    }

    /* ────────────── lookups ────────────── */

    /** GET /purchases.suppliers.lookup.json?q=... */
    public function suppliersLookup(array $ctx): void
    {
        header('Content-Type: application/json; charset=utf-8');
        $pdo   = $this->pdo();
        $orgId = $this->orgId($ctx);

        $roleCol = $this->hasCol($pdo,'dms_stakeholders','type') ? 'type'
                  : ($this->hasCol($pdo,'dms_stakeholders','kind') ? 'kind' : null);

        $q   = trim((string)($_GET['q'] ?? ''));
        $sql = "SELECT id, COALESCE(code,'') AS code, COALESCE(name,'') AS name, COALESCE(phone,'') AS phone, COALESCE(email,'') AS email
                  FROM dms_stakeholders
                 WHERE org_id=? ";
        $args = [$orgId];
        if ($roleCol) { $sql .= "AND {$roleCol}='supplier' "; }

        if ($q !== '') {
            $like = "%{$q}%";
            $sql .= "AND (name LIKE ? OR COALESCE(code,'') LIKE ? OR COALESCE(phone,'') LIKE ? OR COALESCE(email,'') LIKE ?)";
            array_push($args, $like, $like, $like, $like);
        }
        $sql .= " ORDER BY name ASC LIMIT 50";
        $st = $pdo->prepare($sql); $st->execute($args);
        echo json_encode(['items'=>$st->fetchAll(PDO::FETCH_ASSOC) ?: []], JSON_UNESCAPED_UNICODE);
        exit;
    }

    /** GET /purchases.products.lookup.json?q=... */
    public function productsLookup(array $ctx): void
    {
        header('Content-Type: application/json; charset=utf-8');
        $pdo   = $this->pdo();
        $orgId = $this->orgId($ctx);

        $q     = trim((string)($_GET['q'] ?? ''));
        $limit = max(1, min(50, (int)($_GET['limit'] ?? 20)));

        // tolerant aliases on dms_products
        $name  = "COALESCE(p.name, p.product_name, p.title)";
        $sku   = "COALESCE(p.sku, p.product_sku, p.code, p.product_code, p.pid)";
        $bar   = "COALESCE(p.barcode, p.ean, p.upc)";
        $unit  = "COALESCE(p.unit, p.uom_name, p.uom)";
        $base  = "COALESCE(p.cost_price, p.purchase_price, p.unit_cost, p.unit_price, p.price, 0.0)";

        $sql = "
          SELECT p.id,
                 {$name} AS name,
                 {$sku}  AS product_code,
                 {$bar}  AS barcode,
                 {$unit} AS unit,
                 COALESCE(b.unit_price, {$base}) AS price,
                 b.supplier_id
            FROM dms_products p
            LEFT JOIN v_best_product_price_today b
              ON b.org_id=p.org_id AND b.product_id=p.id
           WHERE p.org_id=?
        ";
        $args = [$orgId];
        if ($q !== '') {
            $like = "%{$q}%";
            $sql .= " AND ( {$name} LIKE ? OR {$sku} LIKE ? OR {$bar} LIKE ?)";
            array_push($args, $like, $like, $like);
        }
        $sql .= " ORDER BY p.id DESC LIMIT {$limit}";

        try {
            $st = $pdo->prepare($sql); $st->execute($args);
        } catch (\Throwable $e) {
            // fallback without the view
            $fallback = "
              SELECT p.id,
                     {$name} AS name,
                     {$sku}  AS product_code,
                     {$bar}  AS barcode,
                     {$unit} AS unit,
                     {$base} AS price,
                     NULL AS supplier_id
                FROM dms_products p
               WHERE p.org_id=?
            ";
            $args = [$orgId];
            if ($q !== '') {
                $like = "%{$q}%";
                $fallback .= " AND ( {$name} LIKE ? OR {$sku} LIKE ? OR {$bar} LIKE ?)";
                array_push($args, $like, $like, $like);
            }
            $fallback .= " ORDER BY p.id DESC LIMIT {$limit}";
            $st = $pdo->prepare($fallback); $st->execute($args);
        }

        $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $items = [];
        foreach ($rows as $r) {
            $items[] = [
                'id'           => (int)$r['id'],
                'name'         => (string)($r['name'] ?? ''),
                'product_code' => (string)($r['product_code'] ?? ''),
                'barcode'      => (string)($r['barcode'] ?? ''),
                'unit'         => (string)($r['unit'] ?? 'PCS'),
                'price'        => (float)($r['price'] ?? 0),
                'supplier_id'  => isset($r['supplier_id']) ? (int)$r['supplier_id'] : null,
            ];
        }

        echo json_encode(['items'=>$items], JSON_UNESCAPED_UNICODE);
        exit;
    }

    /* ───────────────────────────── List ───────────────────────────── */

    /** GET /purchases */
    public function index(array $ctx): void
    {
        $pdo   = $this->pdo();
        $orgId = $this->orgId($ctx);
        $c     = $this->cols($pdo);
        $has   = fn(string $col) => $this->hasCol($pdo, 'dms_purchases', $col);

        $noExpr       = ($c['no']       && $has($c['no']))       ? $this->ident($c['no'])       : $this->ident('id');
        $dateExpr     = ($c['date']     && $has($c['date']))     ? $this->ident($c['date'])     : $this->ident('created_at');
        $supplierExpr = ($c['supplier'] && $has($c['supplier'])) ? $this->ident($c['supplier']) : 'NULL';
        $grandExpr    = ($c['grand']    && $has($c['grand']))    ? $this->ident($c['grand'])    : '0';
        $statusExpr   = ($c['status']   && $has($c['status']))   ? $this->ident($c['status'])   : "'draft'";

        $sql = "
            SELECT id,
                   {$noExpr}       AS bill_no,
                   {$dateExpr}     AS bill_date,
                   {$supplierExpr} AS supplier_name,
                   {$grandExpr}    AS grand_total,
                   {$statusExpr}   AS status
              FROM dms_purchases
             WHERE org_id = ?
             ORDER BY {$dateExpr} DESC, id DESC
             LIMIT 100
        ";
        $st = $pdo->prepare($sql);
        $st->execute([$orgId]);
        $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $this->view('purchases/index', [
            'title'  => 'Purchases',
            'rows'   => $rows,
            'active' => 'purchases',
        ], $ctx);
    }

    /* ───────────────────────────── Create ─────────────────────────── */

    /** GET /purchases/create */
    public function create(array $ctx): void
    {
        $pdo   = $this->pdo();
        $orgId = $this->orgId($ctx);
        $c     = $this->cols($pdo);

        $noCol       = $c['no'] ?: '';
        $canStoreNo  = ($noCol !== '' && $noCol !== 'id' && $this->hasCol($pdo,'dms_purchases',$noCol));
        $prefillNo   = $canStoreNo ? $this->nextPurchaseNo($pdo, $orgId, $noCol) : '';
        $base        = $this->moduleBase($ctx);

        // suppliers (type|kind)
        $roleCol = $this->hasCol($pdo,'dms_stakeholders','type') ? 'type'
                  : ($this->hasCol($pdo,'dms_stakeholders','kind') ? 'kind' : null);
        $suppliers = [];
        try {
            $sql = "SELECT id, COALESCE(code,'') AS code, COALESCE(name,'') AS name
                      FROM dms_stakeholders
                     WHERE org_id=? ";
            $args = [$orgId];
            if ($roleCol) { $sql .= " AND {$roleCol}='supplier'"; }
            $sql .= " ORDER BY name ASC LIMIT 300";
            $s = $pdo->prepare($sql); $s->execute($args);
            $suppliers = $s->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (\Throwable) {}

        // products (prefer best-price view)
        $products = [];
        try {
            $name  = "COALESCE(p.name, p.product_name, p.title)";
            $sku   = "COALESCE(p.sku, p.product_sku, p.code, p.product_code, p.pid)";
            $bar   = "COALESCE(p.barcode, p.ean, p.upc)";
            $unit  = "COALESCE(p.unit, p.uom_name, p.uom)";
            $baseP = "COALESCE(p.cost_price, p.purchase_price, p.unit_cost, p.unit_price, p.price, 0.0)";
            $sql = "
              SELECT p.id, {$name} AS name, {$sku} AS product_code, {$bar} AS barcode, {$unit} AS unit,
                     COALESCE(b.unit_price, {$baseP}) AS price
                FROM dms_products p
                LEFT JOIN v_best_product_price_today b
                  ON b.org_id=p.org_id AND b.product_id=p.id
               WHERE p.org_id=?
               ORDER BY p.id DESC
               LIMIT 200
            ";
            try { $p = $pdo->prepare($sql); $p->execute([$orgId]); }
            catch (\Throwable) {
                $p = $pdo->prepare("
                    SELECT p.id, {$name} AS name, {$sku} AS product_code, {$bar} AS barcode, {$unit} AS unit,
                           {$baseP} AS price
                      FROM dms_products p
                     WHERE p.org_id=?
                     ORDER BY p.id DESC
                     LIMIT 200
                ");
                $p->execute([$orgId]);
            }
            foreach ($p->fetchAll(PDO::FETCH_ASSOC) ?: [] as $r) {
                $products[] = [
                    'id'           => (int)$r['id'],
                    'name'         => (string)($r['name'] ?? ''),
                    'product_code' => (string)($r['product_code'] ?? ''),
                    'barcode'      => (string)($r['barcode'] ?? ''),
                    'unit'         => (string)($r['unit'] ?? 'PCS'),
                    'price'        => (float)($r['price'] ?? 0),
                ];
            }
        } catch (\Throwable) {}

        $this->view('purchases/create', [
            'title'       => 'New Purchase',
            'active'      => 'purchases',
            'next_no'     => $prefillNo,
            'today'       => date('Y-m-d'),
            'module_base' => $base,
            'endpoints'   => [
                'suppliers' => $base . '/purchases.suppliers.lookup.json',
                'products'  => $base . '/purchases.products.lookup.json',
            ],
            'prefetch'    => ['suppliers'=>$suppliers, 'products'=>$products],
        ], $ctx);
    }

    /* ────────────────────────── product ensure/enrich ───────────────────────── */

    /**
     * Ensure or create product with enrichment.
     * Merge priority:
     *   1) product_code (exact) OR barcode (exact)
     *   2) same supplier + same name (case-insensitive)
     *   3) same name
     * Creates new row with known unit/price/etc. Fills only missing fields on existing product.
     */
    private function ensureProduct(PDO $pdo, int $orgId, ?int $maybeId, string $name, array $ctx): int
    {
        $name         = trim($name);
        $sku          = trim((string)($ctx['sku'] ?? ''));
        $barcode      = trim((string)($ctx['barcode'] ?? ''));
        $productCode  = trim((string)($ctx['product_code'] ?? ''));
        $categoryCode = trim((string)($ctx['category_code'] ?? ''));
        $unitVal      = trim((string)($ctx['unit'] ?? 'pcs'));
        $priceVal     = (float)($ctx['price'] ?? 0);
        $supplierId   = $ctx['supplier_id'] ?? null;

        // columns we may use
        $colUnit   = $this->pickCol($pdo,'dms_products',['unit','uom_name','uom']);
        $colPrice  = $this->pickCol($pdo,'dms_products',['cost_price','purchase_price','unit_cost','unit_price','price']);
        $colSupp   = $this->pickCol($pdo,'dms_products',['supplier_id','vendor_id','party_id']);
        $colBar    = $this->pickCol($pdo,'dms_products',['barcode','ean','upc']);
        $colPcode  = $this->pickCol($pdo,'dms_products',['product_code','code']);
        $colCcode  = $this->pickCol($pdo,'dms_products',['category_code','cat_code','category']);
        $colSku    = $this->pickCol($pdo,'dms_products',['sku','product_sku','pid']);
        $colName   = $this->pickCol($pdo,'dms_products',['name','product_name','title']);
        $colStatus = $this->pickCol($pdo,'dms_products',['status','state']);

        // by id
        if ($maybeId && $maybeId > 0) {
            $s = $pdo->prepare("SELECT id FROM dms_products WHERE org_id=? AND id=?");
            $s->execute([$orgId, $maybeId]);
            if ((int)$s->fetchColumn() > 0) {
                $pid = (int)$maybeId;
                $this->softFillProduct($pdo, $orgId, $pid, $colUnit, $unitVal, $colPrice, $priceVal, $colSupp, $supplierId, $colBar, $barcode, $colPcode, $productCode, $colCcode, $categoryCode);
                return $pid;
            }
        }

        // product_code exact
        if ($productCode !== '' && $colPcode) {
            $s = $pdo->prepare("SELECT id FROM dms_products WHERE org_id=? AND {$colPcode}=? LIMIT 1");
            $s->execute([$orgId, $productCode]);
            if ($pid = (int)$s->fetchColumn()) {
                $this->softFillProduct($pdo, $orgId, $pid, $colUnit, $unitVal, $colPrice, $priceVal, $colSupp, $supplierId, $colBar, $barcode, $colPcode, $productCode, $colCcode, $categoryCode);
                return $pid;
            }
        }
        // barcode exact
        if ($barcode !== '' && $colBar) {
            $s = $pdo->prepare("SELECT id FROM dms_products WHERE org_id=? AND {$colBar}=? LIMIT 1");
            $s->execute([$orgId, $barcode]);
            if ($pid = (int)$s->fetchColumn()) {
                $this->softFillProduct($pdo, $orgId, $pid, $colUnit, $unitVal, $colPrice, $priceVal, $colSupp, $supplierId, $colBar, $barcode, $colPcode, $productCode, $colCcode, $categoryCode);
                return $pid;
            }
        }
        // supplier + name
        if ($supplierId && $name !== '' && $colName && $colSupp) {
            $s = $pdo->prepare("SELECT id FROM dms_products WHERE org_id=? AND {$colSupp} <=> ? AND LOWER({$colName})=LOWER(?) LIMIT 1");
            $s->execute([$orgId, $supplierId, $name]);
            if ($pid = (int)$s->fetchColumn()) {
                $this->softFillProduct($pdo, $orgId, $pid, $colUnit, $unitVal, $colPrice, $priceVal, $colSupp, $supplierId, $colBar, $barcode, $colPcode, $productCode, $colCcode, $categoryCode);
                return $pid;
            }
        }
        // name only
        if ($name !== '' && $colName) {
            $s = $pdo->prepare("SELECT id FROM dms_products WHERE org_id=? AND LOWER({$colName})=LOWER(?) LIMIT 1");
            $s->execute([$orgId, $name]);
            if ($pid = (int)$s->fetchColumn()) {
                $this->softFillProduct($pdo, $orgId, $pid, $colUnit, $unitVal, $colPrice, $priceVal, $colSupp, $supplierId, $colBar, $barcode, $colPcode, $productCode, $colCcode, $categoryCode);
                return $pid;
            }
        }

        // create new (insert only columns that exist)
        $skuNew = $sku ?: (strtoupper(substr(preg_replace('/[^a-z0-9]/i','',$name ?: 'PRD'),0,3)).'-'.date('ymd').'-'.substr((string)mt_rand(100,999),-3));

        $cols = ['org_id'];
        $vals = [$orgId];
        if ($colName)   { $cols[]=$colName;   $vals[] = ($name ?: $skuNew); }
        if ($colSku)    { $cols[]=$colSku;    $vals[] = $skuNew; }
        if ($colUnit)   { $cols[]=$colUnit;   $vals[] = $unitVal ?: 'pcs'; }
        if ($colPrice)  { $cols[]=$colPrice;  $vals[] = $priceVal; }
        if ($colSupp)   { $cols[]=$colSupp;   $vals[] = $supplierId; }
        if ($colBar)    { $cols[]=$colBar;    $vals[] = $barcode ?: null; }
        if ($colPcode)  { $cols[]=$colPcode;  $vals[] = $productCode ?: null; }
        if ($colCcode)  { $cols[]=$colCcode;  $vals[] = $categoryCode ?: null; }
        if ($this->hasCol($pdo,'dms_products','status'))     { $cols[]='status';     $vals[] = 'active'; }
        if ($this->hasCol($pdo,'dms_products','created_at')) { $cols[]='created_at'; $vals[] = date('Y-m-d H:i:s'); }
        if ($this->hasCol($pdo,'dms_products','updated_at')) { $cols[]='updated_at'; $vals[] = date('Y-m-d H:i:s'); }

        $placeholders = implode(',', array_fill(0, count($vals), '?'));
        $sql = "INSERT INTO dms_products (".implode(',', $cols).") VALUES ({$placeholders})";
        $pdo->prepare($sql)->execute($vals);
        return (int)$pdo->lastInsertId();
    }

    /** Fill only missing/blank fields on an existing product (tolerant) */
    private function softFillProduct(PDO $pdo, int $orgId, int $productId,
        ?string $colUnit, ?string $unitVal,
        ?string $colPrice, float $priceVal,
        ?string $colSupp, ?int $supplierId,
        ?string $colBar, ?string $barcode,
        ?string $colPcode, ?string $productCode,
        ?string $colCcode, ?string $categoryCode
    ): void {
        $set = [];
        $args = [];
        if ($colUnit)  { $set[] = "{$colUnit} = COALESCE(NULLIF(?,''), {$colUnit})"; $args[] = $unitVal; }
        if ($colPrice) { $set[] = "{$colPrice} = CASE WHEN ({$colPrice} IS NULL OR {$colPrice}=0) AND ? > 0 THEN ? ELSE {$colPrice} END"; $args[]=$priceVal; $args[]=$priceVal; }
        if ($colSupp)  { $set[] = "{$colSupp} = COALESCE({$colSupp}, ?)"; $args[] = $supplierId; }
        if ($colBar)   { $set[] = "{$colBar} = COALESCE(NULLIF(?,''), {$colBar})"; $args[] = $barcode; }
        if ($colPcode) { $set[] = "{$colPcode} = COALESCE(NULLIF(?,''), {$colPcode})"; $args[] = $productCode; }
        if ($colCcode) { $set[] = "{$colCcode} = COALESCE(NULLIF(?,''), {$colCcode})"; $args[] = $categoryCode; }
        if ($this->hasCol($pdo,'dms_products','updated_at')) { $set[]='updated_at = NOW()'; }

        if (!$set) return;
        $sql = "UPDATE dms_products SET ".implode(', ',$set)." WHERE org_id=? AND id=? LIMIT 1";
        $args[] = $orgId; $args[] = $productId;
        $pdo->prepare($sql)->execute($args);
    }

    /* ───────────────────────────── Store ───────────────────────────── */

    public function store(array $ctx): void
    {
        $pdo   = $this->pdo();
        $orgId = $this->orgId($ctx);
        $c     = $this->cols($pdo);

        $noCol      = $c['no'] ?: '';
        $canStoreNo = ($noCol !== '' && $noCol !== 'id' && $this->hasCol($pdo,'dms_purchases',$noCol));
        $billNo     = $canStoreNo ? $this->nextPurchaseNo($pdo, $orgId, $noCol) : null;
        $billDate   = (string)($_POST['bill_date'] ?? date('Y-m-d'));

        $supplierId = (int)($_POST['supplier_id'] ?? 0) ?: null;
        $supplierNm = trim((string)($_POST['supplier_name'] ?? '')) ?: null;

        $statusIn = (string)($_POST['status'] ?? 'draft');
        $status   = in_array($statusIn, ['draft','confirmed','cancelled'], true) ? $statusIn : 'draft';
        $notes    = (string)($_POST['notes'] ?? '');
        $itemsIn  = $_POST['items'] ?? [];

        if (!is_array($itemsIn) || !count($itemsIn)) {
            $_SESSION['flash_err'] = 'Please add at least one line item.';
            $this->redirect($this->moduleBase($ctx).'/purchases/create');
        }

        // Normalize + compute grand
        $items = []; $grand = 0.0;
        foreach ($itemsIn as $row) {
            $name  = trim((string)($row['product_name'] ?? $row['name'] ?? ''));
            $qty   = (float)($row['qty'] ?? 0);
            $price = (float)($row['unit_price'] ?? $row['price'] ?? 0);
            if (($name !== '') && $qty > 0) {
                $line   = round($qty * $price, 2);
                $grand += $line;
                $items[] = [
                    'maybe_id'      => isset($row['product_id']) && $row['product_id'] !== '' ? (int)$row['product_id'] : null,
                    'name'          => $name,
                    'qty'           => $qty,
                    'price'         => $price,
                    'unit'          => trim((string)($row['unit'] ?? '')),
                    'barcode'       => trim((string)($row['barcode'] ?? '')),
                    'product_code'  => trim((string)($row['product_code'] ?? '')),
                    'category_code' => trim((string)($row['category_code'] ?? '')),
                ];
            }
        }
        if (!$items) {
            $_SESSION['flash_err'] = 'Please add at least one valid line.';
            $this->redirect($this->moduleBase($ctx).'/purchases/create');
        }

        $pdo->beginTransaction();
        try {
            // header insert (include only columns that actually exist)
            $fields = ['org_id'];
            $place  = ['?'];
            $args   = [$orgId];

            if ($this->hasCol($pdo,'dms_purchases',$c['supplier_id'])) { $fields[]=$c['supplier_id']; $place[]='?'; $args[]=$supplierId; }
            if ($this->hasCol($pdo,'dms_purchases',$c['supplier']))   { $fields[]=$c['supplier'];   $place[]='?'; $args[]=$supplierNm; }
            if ($canStoreNo)                                          { $fields[]=$noCol;           $place[]='?'; $args[]=$billNo; }
            if ($this->hasCol($pdo,'dms_purchases',$c['date']))       { $fields[]=$c['date'];       $place[]='?'; $args[]=$billDate; }
            if ($this->hasCol($pdo,'dms_purchases',$c['grand']))      { $fields[]=$c['grand'];      $place[]='?'; $args[]=$grand; }
            if ($this->hasCol($pdo,'dms_purchases',$c['status']))     { $fields[]=$c['status'];     $place[]='?'; $args[]=$status; }
            if ($this->hasCol($pdo,'dms_purchases',$c['notes']))      { $fields[]=$c['notes'];      $place[]='?'; $args[]=$notes; }
            if ($this->hasCol($pdo,'dms_purchases','created_at'))     { $fields[]='created_at';     $place[]='NOW()'; }
            if ($this->hasCol($pdo,'dms_purchases','updated_at'))     { $fields[]='updated_at';     $place[]='NOW()'; }

            $sqlH = "INSERT INTO dms_purchases (".implode(',',$fields).") VALUES (".implode(',',$place).")";
            $pdo->prepare($sqlH)->execute($args);
            $purchaseId = (int)$pdo->lastInsertId();

            // items (tolerant columns)
            $cm = $this->itemColMap($pdo);
            $cols = array_filter(['org_id','purchase_id',$cm['product_id'],$cm['product_name'],$cm['qty'],$cm['unit_price'],$cm['line_total'],$cm['created_at']]);
            $qs   = [];
            foreach ($cols as $cname) { $qs[] = ($cname==='created_at' ? 'NOW()' : '?'); }
            $sqlI = "INSERT INTO dms_purchase_items (".implode(',',$cols).") VALUES (".implode(',',$qs).")";
            $i = $pdo->prepare($sqlI);

            foreach ($items as $ln) {
                $pid = $this->ensureProduct(
                    $pdo, $orgId, $ln['maybe_id'], $ln['name'],
                    [
                        'unit'          => $ln['unit'],
                        'price'         => $ln['price'],
                        'supplier_id'   => $supplierId,
                        'barcode'       => $ln['barcode'],
                        'product_code'  => $ln['product_code'],
                        'category_code' => $ln['category_code'],
                    ]
                );
                if ($pid <= 0) throw new \RuntimeException('Invalid product: '.$ln['name']);

                $vals = [];
                foreach ($cols as $cname) {
                    if     ($cname==='org_id')            $vals[]=$orgId;
                    elseif ($cname==='purchase_id')       $vals[]=$purchaseId;
                    elseif ($cname===$cm['product_id'])   $vals[]=$pid;
                    elseif ($cname===$cm['product_name']) $vals[]=$ln['name'];
                    elseif ($cname===$cm['qty'])          $vals[]=$ln['qty'];
                    elseif ($cname===$cm['unit_price'])   $vals[]=$ln['price'];
                    elseif ($cname===$cm['line_total'])   $vals[]=$ln['qty'] * $ln['price'];
                    elseif ($cname==='created_at')        { /* NOW() */ }
                }
                $i->execute($vals);
            }

            if ($status === 'confirmed') {
                try { $pdo->prepare("CALL dms_post_purchase(?, ?)")->execute([$orgId, $purchaseId]); } catch (\Throwable $ignore) {}
            }

            $pdo->commit();
            $this->redirect($this->moduleBase($ctx).'/purchases');
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $_SESSION['flash_err'] = 'Purchase save failed: '.$e->getMessage();
            $this->redirect($this->moduleBase($ctx).'/purchases/create');
        }
    }

    /* ───────────────────────────── Show / Edit ───────────────────────────── */

    /** GET /purchases/{id} */
    public function show(array $ctx, int $id): void
    {
        $pdo   = $this->pdo();
        $orgId = $this->orgId($ctx);
        $c     = $this->cols($pdo);

        $h = $pdo->prepare("SELECT * FROM dms_purchases WHERE org_id=? AND id=?");
        $h->execute([$orgId, $id]);
        $hdr = $h->fetch(PDO::FETCH_ASSOC);
        if (!$hdr) { $this->abort404('Purchase not found.'); }

        $i = $pdo->prepare("SELECT * FROM dms_purchase_items WHERE org_id=? AND purchase_id=? ORDER BY id");
        $i->execute([$orgId, $id]);
        $items = $i->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $titleNo = $hdr[$c['no']] ?? $id;

        $this->view('purchases/show', [
            'title'    => 'Purchase '.$titleNo,
            'purchase' => $hdr,
            'items'    => $items,
            'active'   => 'purchases',
        ], $ctx);
    }

    /** GET /purchases/{id}/edit */
    public function edit(array $ctx, int $id): void
    {
        $pdo   = $this->pdo();
        $orgId = $this->orgId($ctx);

        $h = $pdo->prepare("SELECT * FROM dms_purchases WHERE org_id=? AND id=?");
        $h->execute([$orgId, $id]);
        $hdr = $h->fetch(PDO::FETCH_ASSOC);
        if (!$hdr) { $this->abort404('Purchase not found.'); }

        $i = $pdo->prepare("SELECT * FROM dms_purchase_items WHERE org_id=? AND purchase_id=? ORDER BY id");
        $i->execute([$orgId, $id]);
        $items = $i->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $this->view('purchases/edit', [
            'title'    => 'Edit Purchase',
            'purchase' => $hdr,
            'items'    => $items,
            'active'   => 'purchases',
        ], $ctx);
    }

    /* ───────────────────────────── Update ───────────────────────────── */

    public function update(array $ctx, int $id): void
    {
        $pdo   = $this->pdo();
        $orgId = $this->orgId($ctx);
        $c     = $this->cols($pdo);

        $itemsIn   = $_POST['items'] ?? [];
        $billNo    = trim((string)($_POST['bill_no'] ?? ''));
        $billDate  = (string)($_POST['bill_date'] ?? date('Y-m-d'));

        $supplierId = (int)($_POST['supplier_id'] ?? 0) ?: null;
        $supplierNm = trim((string)($_POST['supplier_name'] ?? '')) ?: null;

        $statusIn = (string)($_POST['status'] ?? 'draft');
        $status   = in_array($statusIn, ['draft','confirmed','cancelled'], true) ? $statusIn : 'draft';
        $notes    = (string)($_POST['notes'] ?? '');

        $items = []; $grand = 0.0;
        foreach ($itemsIn as $row) {
            $name  = trim((string)($row['product_name'] ?? $row['name'] ?? ''));
            $qty   = (float)($row['qty'] ?? 0);
            $price = (float)($row['unit_price'] ?? $row['price'] ?? 0);
            if ($name !== '' && $qty > 0) {
                $line   = round($qty * $price, 2);
                $grand += $line;
                $items[] = [
                    'maybe_id'      => isset($row['product_id']) && $row['product_id'] !== '' ? (int)$row['product_id'] : null,
                    'name'          => $name,
                    'qty'           => $qty,
                    'price'         => $price,
                    'unit'          => trim((string)($row['unit'] ?? '')),
                    'barcode'       => trim((string)($row['barcode'] ?? '')),
                    'product_code'  => trim((string)($row['product_code'] ?? '')),
                    'category_code' => trim((string)($row['category_code'] ?? '')),
                ];
            }
        }

        $pdo->beginTransaction();
        try {
            $sets = []; $args = [];

            if ($this->hasCol($pdo,'dms_purchases',$c['supplier_id'])) { $sets[]="{$c['supplier_id']}=?"; $args[]=$supplierId; }
            if ($this->hasCol($pdo,'dms_purchases',$c['supplier']))   { $sets[]="{$c['supplier']}=?";   $args[]=$supplierNm; }
            if ($c['no'] && $c['no']!=='id' && $this->hasCol($pdo,'dms_purchases',$c['no'])) { $sets[]="{$c['no']}=?"; $args[]=$billNo; }
            if ($this->hasCol($pdo,'dms_purchases',$c['date']))       { $sets[]="{$c['date']}=?";      $args[]=$billDate; }
            if ($this->hasCol($pdo,'dms_purchases',$c['grand']))      { $sets[]="{$c['grand']}=?";     $args[]=$grand; }
            if ($this->hasCol($pdo,'dms_purchases',$c['status']))     { $sets[]="{$c['status']}=?";    $args[]=$status; }
            if ($this->hasCol($pdo,'dms_purchases',$c['notes']))      { $sets[]="{$c['notes']}=?";     $args[]=$notes; }
            if ($this->hasCol($pdo,'dms_purchases','updated_at'))     { $sets[]="updated_at=NOW()"; }

            if ($sets) {
                $sql = "UPDATE dms_purchases SET ".implode(', ',$sets)." WHERE org_id=? AND id=?";
                $args[]=$orgId; $args[]=$id;
                $pdo->prepare($sql)->execute($args);
            }

            // replace items
            $pdo->prepare("DELETE FROM dms_purchase_items WHERE org_id=? AND purchase_id=?")->execute([$orgId, $id]);

            if ($items) {
                $cm = $this->itemColMap($pdo);
                $cols = array_filter(['org_id','purchase_id',$cm['product_id'],$cm['product_name'],$cm['qty'],$cm['unit_price'],$cm['line_total'],$cm['created_at']]);
                $qs   = [];
                foreach ($cols as $cname) { $qs[] = ($cname==='created_at' ? 'NOW()' : '?'); }
                $sqlI = "INSERT INTO dms_purchase_items (".implode(',',$cols).") VALUES (".implode(',',$qs).")";
                $i = $pdo->prepare($sqlI);

                foreach ($items as $ln) {
                    $pid = $this->ensureProduct(
                        $pdo, $orgId, $ln['maybe_id'], $ln['name'],
                        [
                            'unit'          => $ln['unit'],
                            'price'         => $ln['price'],
                            'supplier_id'   => $supplierId,
                            'barcode'       => $ln['barcode'],
                            'product_code'  => $ln['product_code'],
                            'category_code' => $ln['category_code'],
                        ]
                    );
                    if ($pid <= 0) throw new \RuntimeException('Invalid product: '.$ln['name']);

                    $vals = [];
                    foreach ($cols as $cname) {
                        if     ($cname==='org_id')            $vals[]=$orgId;
                        elseif ($cname==='purchase_id')       $vals[]=$id;
                        elseif ($cname===$cm['product_id'])   $vals[]=$pid;
                        elseif ($cname===$cm['product_name']) $vals[]=$ln['name'];
                        elseif ($cname===$cm['qty'])          $vals[]=$ln['qty'];
                        elseif ($cname===$cm['unit_price'])   $vals[]=$ln['price'];
                        elseif ($cname===$cm['line_total'])   $vals[]=$ln['qty'] * $ln['price'];
                        elseif ($cname==='created_at')        { /* NOW() */ }
                    }
                    $i->execute($vals);
                }
            }

            if ($status === 'confirmed') {
                try { $pdo->prepare("CALL dms_post_purchase(?, ?)")->execute([$orgId, $id]); } catch (\Throwable $ignore) {}
            }

            $pdo->commit();
            $this->redirect($this->moduleBase($ctx).'/purchases/'.$id);
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $_SESSION['flash_err'] = 'Update failed: '.$e->getMessage();
            $this->redirect($this->moduleBase($ctx)."/purchases/{$id}/edit");
        }
    }

    /* ───────────────────────────── Destroy ───────────────────────────── */

    /** POST /purchases/{id}/delete */
    public function destroy(array $ctx, int $id): void
    {
        $pdo   = $this->pdo();
        $orgId = $this->orgId($ctx);

        $pdo->beginTransaction();
        try {
            $pdo->prepare("DELETE FROM dms_purchase_items WHERE org_id=? AND purchase_id=?")->execute([$orgId, $id]);
            $pdo->prepare("DELETE FROM dms_purchases      WHERE org_id=? AND id=?")->execute([$orgId, $id]);
            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $this->renderShell('Error', '<pre class="p-4">'.htmlspecialchars($e->getMessage()).'</pre>');
            return;
        }
        $this->redirect($this->moduleBase($ctx).'/purchases');
    }
}