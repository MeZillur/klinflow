<?php
declare(strict_types=1);

namespace Modules\DMS\Controllers;

use PDO;
use Throwable;

/**
 * OrdersController (A→Z, refined)
 * - Multi-tenant (org_id)
 * - Stable redirects (no REQUEST_URI guessing)
 * - Server-authoritative totals
 * - Defensive DB helpers
 * - Minimal dependencies (works without framework sugar)
 */
final class OrdersController extends BaseController
{
    /* ===============================================================
     * SEGMENT A — SMALL HELPERS (base URL, debug, user, counters)
     * =============================================================== */

    /** Canonical module base like /t/{slug}/apps/dms */
    private function base(array $ctx = []): string
    {
        return method_exists($this, 'moduleBase')
            ? rtrim((string)$this->moduleBase($ctx), '/')
            : '/apps/dms';
    }

    /** Stable list URL: {base}/orders */
    private function listUrl(array $ctx = []): string
    {
        return $this->base($ctx) . '/orders';
    }

    /** Collection path resolver: e.g. /t/{slug}/apps/dms/orders */
    private function ordersPath(array $ctx = []): string
    {
        return $this->base($ctx) . '/orders';
    }

    private function like(string $q): string
    {
        return '%'.str_replace(['%','_'], ['\\%','\\_'], $q).'%';
    }

    private function clampLimit($v, int $min=1, int $max=100, int $def=30): int
    {
        $n=(int)$v; return $n<$min?$def:($n>$max?$max:$n);
    }

    private function normalizeStatus(string $s): string
    {
        $s = strtolower(trim($s));
        return in_array($s, ['draft','confirmed','issued','cancelled'], true) ? $s : 'draft';
    }

    private function debugMode(): bool
    {
        return (($_GET['_debug'] ?? $_POST['_debug'] ?? '') === '1');
    }

    private function dbg($ok, $data = [], int $code = 200): void
    {
        if (!headers_sent()) header('Content-Type: application/json; charset=utf-8', true, $code);
        echo json_encode(['ok'=>$ok] + (is_array($data)?$data:['msg'=>(string)$data]), JSON_UNESCAPED_UNICODE);
        exit;
    }

    private function currentUserId(array $ctx): ?int
    {
        $u = $ctx['user'] ?? ($_SESSION['tenant_user'] ?? ($_SESSION['user'] ?? []));
        $id = (int)($u['id'] ?? 0);
        return $id > 0 ? $id : null;
    }

    /** Minimal JSON helper used by API endpoints in this controller */
    protected function json(mixed $payload, int $status = 200, array $headers = []): void
{
    if (!headers_sent()) {
        header('Content-Type: application/json; charset=utf-8', true, $status);
        foreach ($headers as $k => $v) header("$k: $v", true);
    }
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

    /** Document counters per org/year (ORD-2025-00001 style) */
    private function nextDocNo(PDO $pdo, int $orgId, string $name, string $prefix): string
    {
        $y = (int)date('Y');
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS dms_counters (
              org_id INT UNSIGNED NOT NULL,
              name   VARCHAR(64) NOT NULL,
              y      INT NOT NULL,
              seq    BIGINT UNSIGNED NOT NULL DEFAULT 0,
              PRIMARY KEY (org_id, name, y)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        $st = $pdo->prepare("
            INSERT INTO dms_counters (org_id, name, y, seq)
            VALUES (?,?,?,0)
            ON DUPLICATE KEY UPDATE seq = LAST_INSERT_ID(seq + 1)
        ");
        $st->execute([$orgId, $name, $y]);
        $seq = (int)$pdo->lastInsertId();
        if ($seq <= 0) $seq = 1;
        return sprintf('%s-%d-%05d', strtoupper($prefix), $y, $seq);
    }

    /* ======================================================================
     * ITEM MAPPER — adapt to whatever columns exist on dms_order_items
     * ====================================================================== */

    private function itemColumns(PDO $pdo): array
    {
        static $cache = null;
        if ($cache !== null) return $cache;

        $cols = [];
        try {
            $q = $pdo->prepare("
                SELECT column_name
                  FROM information_schema.columns
                 WHERE table_schema = DATABASE()
                   AND table_name   = 'dms_order_items'
            ");
            $q->execute();
            $cols = array_map('strtolower', $q->fetchAll(PDO::FETCH_COLUMN, 0) ?: []);
        } catch (\Throwable) {}

        if (!$cols) {
            try {
                $q = $pdo->query("DESCRIBE `dms_order_items`");
                $rows = $q->fetchAll(PDO::FETCH_ASSOC) ?: [];
                $cols = array_map(fn($r)=> strtolower((string)$r['Field']), $rows);
            } catch (\Throwable) {}
        }

        $cache = array_flip($cols); // fast isset() map
        return $cache;
    }

    /** Build one insert row for dms_order_items based on available columns */
    private function buildItemRow(PDO $pdo, int $orgId, int $orderId, array $src): array
    {
        $C = $this->itemColumns($pdo);
        $dst = [];

        if (isset($C['org_id']))   $dst['org_id']   = $orgId;
        if (isset($C['order_id'])) $dst['order_id'] = $orderId;

        $pid  = (int)($src['product_id'] ?? 0) ?: null;
        $pnam = trim((string)($src['product_name'] ?? ''));

        if ($pid !== null) {
            if (isset($C['product_id']))   $dst['product_id']   = $pid;
            elseif (isset($C['pid']))      $dst['pid']          = $pid;
        }
        if ($pnam !== '') {
            if (isset($C['product_name']))  $dst['product_name']  = $pnam;
            elseif (isset($C['name']))      $dst['name']          = $pnam;
            elseif (isset($C['item_name'])) $dst['item_name']     = $pnam;
        }

        $qty  = (float)($src['qty'] ?? 0);
        if (isset($C['qty']))          $dst['qty']      = $qty;
        elseif (isset($C['quantity'])) $dst['quantity'] = $qty;

        $rate = (float)($src['unit_price'] ?? ($src['price'] ?? ($src['rate'] ?? 0)));
        if (isset($C['unit_price'])) $dst['unit_price'] = $rate;
        elseif (isset($C['price']))  $dst['price']      = $rate;
        elseif (isset($C['rate']))   $dst['rate']       = $rate;

        $lt   = (float)($src['line_total'] ?? ($qty * $rate));
        if (isset($C['line_total'])) $dst['line_total'] = $lt;
        elseif (isset($C['total']))  $dst['total']      = $lt;
        elseif (isset($C['amount'])) $dst['amount']     = $lt;

        if (isset($C['created_at'])) $dst['created_at'] = date('Y-m-d H:i:s');

        return $dst;
    }

    /* ===============================================================
     * SEGMENT B — DB UTILITIES (schema presence & smart insert/update)
     * =============================================================== */

    private function tableExists(PDO $pdo, string $t): bool
    {
        $q = $pdo->prepare("
            SELECT 1 FROM information_schema.tables
             WHERE table_schema = DATABASE()
               AND table_name   = ?
             LIMIT 1
        ");
        $q->execute([$t]);
        return (bool)$q->fetchColumn();
    }

    /** Ensure minimal schema for orders & items (non-destructive) */
    private function ensureOrdersSchema(PDO $pdo): void
    {
        if (!$this->tableExists($pdo, 'dms_orders')) {
            $pdo->exec("
                CREATE TABLE dms_orders (
                  id             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                  org_id         BIGINT UNSIGNED NOT NULL,
                  order_no       VARCHAR(64)  NOT NULL,
                  order_date     DATE         NULL,
                  delivery_date  DATE         NULL,
                  customer_id    BIGINT UNSIGNED NULL,
                  customer_name  VARCHAR(190) NULL,
                  supplier_id    BIGINT UNSIGNED NULL,
                  supplier_name  VARCHAR(190) NULL,
                  sr_user_id     BIGINT UNSIGNED NULL,
                  dsr_user_id    BIGINT UNSIGNED NULL,
                  discount_type  ENUM('percent','amount') NULL,
                  discount_value DECIMAL(14,2) NOT NULL DEFAULT 0.00,
                  grand_total    DECIMAL(15,2) NOT NULL DEFAULT 0.00,
                  status         VARCHAR(32)  NOT NULL DEFAULT 'draft',
                  notes          TEXT NULL,
                  created_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                  updated_at     DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
                  PRIMARY KEY (id),
                  UNIQUE KEY uq_orders_org_no (org_id, order_no),
                  KEY idx_orders_org_date   (org_id, order_date),
                  KEY idx_orders_org_status (org_id, status)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");
        }
        if (!$this->tableExists($pdo, 'dms_order_items')) {
            $pdo->exec("
                CREATE TABLE dms_order_items (
                  id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                  org_id       BIGINT UNSIGNED NOT NULL,
                  order_id     BIGINT UNSIGNED NOT NULL,
                  product_id   BIGINT UNSIGNED NULL,
                  product_name VARCHAR(255) NULL,
                  qty          DECIMAL(16,4) NOT NULL DEFAULT 0.0000,
                  unit_price   DECIMAL(16,4) NOT NULL DEFAULT 0.0000,
                  line_total   DECIMAL(16,4) NOT NULL DEFAULT 0.0000,
                  created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                  PRIMARY KEY (id),
                  KEY idx_head (org_id, order_id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");
        }
    }

    /** Case-insensitive, resilient column set (information_schema + DESCRIBE fallback) */
    private array $__colCache = [];

    private function tableCols(PDO $pdo, string $table): array
    {
        if (isset($this->__colCache[$table])) return $this->__colCache[$table];

        $cols = [];
        try {
            $q = $pdo->prepare("
                SELECT column_name
                  FROM information_schema.columns
                 WHERE table_schema = DATABASE()
                   AND table_name   = :t
            ");
            $q->execute([':t' => $table]);
            $cols = $q->fetchAll(PDO::FETCH_COLUMN, 0) ?: [];
        } catch (\Throwable) {}

        // Fallback if information_schema returned nothing
        if (!$cols) {
            try {
                $q = $pdo->query("DESCRIBE `{$table}`");
                $rows = $q->fetchAll(PDO::FETCH_ASSOC) ?: [];
                $cols = array_map(fn($r) => (string)$r['Field'], $rows);
            } catch (\Throwable) {}
        }

        // Build case-insensitive set
        $set = [];
        foreach ($cols as $c) {
            $set[$c] = true;
            $set[strtolower($c)] = true;
            $set[strtoupper($c)] = true;
        }
        return $this->__colCache[$table] = $set;
    }

    /** Insert only existing cols; auto created_at when present (robust) */
    private function insertSmart(PDO $pdo, string $table, array $data): int
    {
        $set = $this->tableCols($pdo, $table);
        $row = [];

        foreach ($data as $k => $v) {
            if (isset($set[$k]) || isset($set[strtolower($k)]) || isset($set[strtoupper($k)])) {
                $row[$k] = $v;
            }
        }

        if (isset($set['created_at']) && !array_key_exists('created_at', $row)) {
            $row['created_at'] = date('Y-m-d H:i:s');
        }
        if (!$row) throw new \RuntimeException("No insertable columns for {$table}");

        $cols = array_keys($row);
        $ph   = array_map(fn($c)=>":$c", $cols);
        $sql  = "INSERT INTO {$table} (".implode(',', $cols).") VALUES (".implode(',', $ph).")";
        $st   = $pdo->prepare($sql);
        foreach ($row as $k => $v) $st->bindValue(":{$k}", $v);
        $st->execute();

        return (int)$pdo->lastInsertId();
    }

    /** Update only existing cols; auto updated_at when present (robust) */
    /** Update only existing cols; auto updated_at when present (robust, all-named) */
private function updateSmart(PDO $pdo, string $table, array $data, string $whereSql, array $whereArgs): void
{
    $set = $this->tableCols($pdo, $table);
    $row = [];

    foreach ($data as $k => $v) {
        if (isset($set[$k]) || isset($set[strtolower($k)]) || isset($set[strtoupper($k)])) {
            $row[$k] = $v;
        }
    }

    if (isset($set['updated_at'])) {
        $row['updated_at'] = date('Y-m-d H:i:s');
    }
    if (!$row) return;

    // build named placeholders for SET
    $assign = [];
    foreach (array_keys($row) as $c) {
        $assign[] = "{$c} = :__set_{$c}";
    }

    // ensure WHERE also uses *named* placeholders
    // you may pass ':w1' ':w2' etc from call site
    $sql = "UPDATE {$table} SET " . implode(', ', $assign) . " WHERE {$whereSql}";
    $st  = $pdo->prepare($sql);

    // bind SET
    foreach ($row as $k => $v) {
        $st->bindValue(":__set_{$k}", $v);
    }
    // bind WHERE (expects named keys like ':w1'=>..., ':id'=>...)
    foreach ($whereArgs as $k => $v) {
        // allow both with/without leading colon
        $ph = $k[0] === ':' ? $k : (':'.$k);
        $st->bindValue($ph, $v);
    }

    $st->execute();
}

    // --- DEBUG: /orders/smoke-insert ---
    // Inserts a minimal draft order to prove inserts + redirects work.
    public function smokeInsert(array $ctx): void
    {
        $pdo   = $this->tenantPdo();
        $orgId = (int)$this->orgId($ctx);
        if ($orgId <= 0) $this->abort500('No org_id in context.');

        $this->ensureOrdersSchema($pdo);

        $no  = 'SMOKE-'.date('Ymd-His');
        $od  = date('Y-m-d');
        $dd  = $od;

        try {
            $pdo->prepare("
                INSERT INTO dms_orders
                  (org_id, order_no, order_date, delivery_date,
                   customer_id, customer_name,
                   discount_type, discount_value, grand_total,
                   status, notes, created_at, updated_at)
                VALUES
                  (:o, :no, :od, :dd,
                   NULL, :cname,
                   'amount', 0.00, 0.00,
                   'draft', 'smoke test', NOW(), NOW())
            ")->execute([
                ':o'     => $orgId,
                ':no'    => $no,
                ':od'    => $od,
                ':dd'    => $dd,
                ':cname' => 'Smoke Customer'
            ]);

            $id = (int)$pdo->lastInsertId();

            header('Content-Type: text/plain; charset=utf-8');
            echo "OK: inserted order id={$id} no={$no} for org_id={$orgId}\n";
            echo "Browse: ".$this->base($ctx)."/orders/{$id}\n";
            exit;
        } catch (\Throwable $e) {
            header('Content-Type: text/plain; charset=utf-8', true, 500);
            echo "FAIL: ".$e->getMessage()."\n";
            exit;
        }
    }

    /* ===============================================================
     * SEGMENT C — LOOKUPS (products, customers, suppliers)
     * =============================================================== */

    public function apiLookupProducts(array $ctx): void {
        $pdo=$this->tenantPdo(); $orgId=$this->orgId($ctx); if ($orgId<=0) $this->json(['items'=>[]]);
        $q=trim((string)($_GET['q']??'')); $limit=$this->clampLimit($_GET['limit']??20);
        $price = 'COALESCE(p.unit_price,p.price,p.sale_price,p.selling_price,p.mrp,p.rate,0.0)';
        $w=['p.org_id=?']; $a=[$orgId];
        if ($q!==''){ $like='%'.$q.'%'; $w[]='(p.name LIKE ? OR p.code LIKE ? OR p.barcode LIKE ? OR p.sku LIKE ?)'; array_push($a,$like,$like,$like,$like); }
        $sql="SELECT p.id, p.name, COALESCE(p.code,p.product_code,'') AS code, {$price} AS price
              FROM dms_products p WHERE ".implode(' AND ',$w)." ORDER BY p.id DESC LIMIT {$limit}";
        try{ $st=$pdo->prepare($sql); $st->execute($a);
            $rows=$st->fetchAll(PDO::FETCH_ASSOC) ?: [];
            foreach($rows as &$r){ $r['label']=trim((string)$r['name'].' '.(($r['code']??'')?('('.$r['code'].') '):'').'— '.number_format((float)($r['price']??0),2)); } unset($r);
            $this->json(['items'=>$rows]);
        } catch (Throwable) { $this->json(['items'=>[]]); }
    }

    public function apiLookupCustomers(array $ctx): void {
        $pdo=$this->tenantPdo(); $orgId=$this->orgId($ctx); if ($orgId<=0) $this->json(['items'=>[]]);
        $q=trim((string)($_GET['q']??'')); $limit=$this->clampLimit($_GET['limit']??20);
        $w=['org_id=?']; $a=[$orgId];
        if ($q!==''){ $like=$this->like($q); $w[]="(name LIKE ? ESCAPE '\\\\' OR COALESCE(code,'') LIKE ? ESCAPE '\\\\' OR COALESCE(phone,'') LIKE ? ESCAPE '\\\\')"; array_push($a,$like,$like,$like); }
        try{ $st=$pdo->prepare("SELECT id,name,COALESCE(code,'') AS code,COALESCE(phone,'') AS phone FROM dms_customers WHERE ".implode(' AND ',$w)." ORDER BY id DESC LIMIT {$limit}");
            $st->execute($a); $rows=$st->fetchAll(PDO::FETCH_ASSOC) ?: [];
            foreach($rows as &$r){ $r['label']=trim($r['name'].' '.($r['code']?"({$r['code']})":'').($r['phone']?" — {$r['phone']}":'')); } unset($r);
            $this->json(['items'=>$rows]);
        } catch(Throwable){ $this->json(['items'=>[]]); }
    }

    public function apiLookupSuppliers(array $ctx): void {
        $pdo=$this->tenantPdo(); $orgId=$this->orgId($ctx); if ($orgId<=0) $this->json(['items'=>[]]);
        $q=trim((string)($_GET['q']??'')); $limit=$this->clampLimit($_GET['limit']??20);
        $w=['org_id=?',"kind='supplier'"]; $a=[$orgId];
        if ($q!==''){ $like='%'.$q.'%'; $w[]='(name LIKE ? OR COALESCE(code, \'\') LIKE ?)'; array_push($a,$like,$like); }
        try{ $st=$pdo->prepare("SELECT id,name,COALESCE(code,'') AS code FROM dms_stakeholders WHERE ".implode(' AND ',$w)." ORDER BY name ASC, id DESC LIMIT {$limit}");
            $st->execute($a); $rows=$st->fetchAll(PDO::FETCH_ASSOC) ?: [];
            foreach($rows as &$r){ $r['label']=trim($r['name'].' '.($r['code']?"({$r['code']})":'')); } unset($r);
            $this->json(['items'=>$rows]);
        } catch(Throwable){ $this->json(['items'=>[]]); }
    }

    /* ===============================================================
     * SEGMENT D — VIEWS (index, create, edit, show)
     * =============================================================== */

    public function index(array $ctx): void
{
    $pdo = $this->tenantPdo();
    $this->ensureOrdersSchema($pdo);

    $orgId = (int)$this->orgId($ctx);

    // ---- filters
    $from        = trim((string)($_GET['from'] ?? ''));
    $to          = trim((string)($_GET['to'] ?? ''));
    $q           = trim((string)($_GET['q'] ?? ''));
    $status      = trim((string)($_GET['status'] ?? ''));
    $srId        = (string)($_GET['sr_id'] ?? '');
    $supplierId  = (string)($_GET['supplier_id'] ?? ($_GET['dealer_id'] ?? ''));
    $export      = strtolower(trim((string)($_GET['export'] ?? '')));

    // ---- detect optional columns on dms_orders
    $cols = $this->tableCols($pdo, 'dms_orders');
    $hasSupplierName = isset($cols['supplier_name']);

    // Build select list safely
    $select = "
        o.id, o.order_no, o.order_date, o.customer_name,
        ".($hasSupplierName ? "o.supplier_name" : "'' AS supplier_name").",
        o.grand_total, o.status
    ";

    // ---- where
    $w = ['o.org_id = ?'];
    $a = [$orgId];

    if ($from !== '') { $w[] = 'o.order_date >= ?'; $a[] = substr($from, 0, 10); }
    if ($to   !== '') { $w[] = 'o.order_date <= ?'; $a[] = substr($to,   0, 10); }

    if ($status !== '') { $w[] = 'o.status = ?'; $a[] = strtolower($status); }

    if ($supplierId !== '' && ctype_digit($supplierId)) {
        $w[] = 'o.supplier_id = ?';
        $a[] = (int)$supplierId;
    }

    if ($srId !== '' && ctype_digit($srId)) {
        $w[] = '(o.sr_user_id = ? OR o.dsr_user_id = ?)';
        $a[] = (int)$srId;
        $a[] = (int)$srId;
    }

    if ($q !== '') {
        $like = $this->like($q);
        $clause = "(o.order_no LIKE ? ESCAPE '\\\\' OR COALESCE(o.customer_name,'') LIKE ? ESCAPE '\\\\'";
        $a[] = $like; $a[] = $like;
        if ($hasSupplierName) {
            $clause .= " OR COALESCE(o.supplier_name,'') LIKE ? ESCAPE '\\\\'";
            $a[] = $like;
        }
        $clause .= ")";
        $w[] = $clause;
    
    }
    $where = implode(' AND ', $w);
    $sql = "SELECT {$select} FROM dms_orders o WHERE {$where} ORDER BY o.id DESC LIMIT 500";

    $st = $pdo->prepare($sql);
    $st->execute($a);
    $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];

    $count = count($rows);
    $sum   = 0.0; foreach ($rows as $r) { $sum += (float)($r['grand_total'] ?? 0); }

    // ---- CSV export (server-side)
    if ($export === 'csv') {
        if (!headers_sent()) {
            header('Content-Type: text/csv; charset=UTF-8');
            header('Content-Disposition: attachment; filename="orders-export-'.date('Ymd').'.csv"');
            header('X-Content-Type-Options: nosniff');
        }
        $out = fopen('php://output', 'w');
        fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF)); // BOM for Excel
        fputcsv($out, ['ID','Order No','Date','Customer','Supplier','Status','Grand Total']);
        foreach ($rows as $r) {
            fputcsv($out, [
                (int)($r['id'] ?? 0),
                (string)($r['order_no'] ?? ''),
                substr((string)($r['order_date'] ?? ''), 0, 10),
                (string)($r['customer_name'] ?? ''),
                (string)($r['supplier_name'] ?? ''),  // safe alias even if column missing
                strtoupper((string)($r['status'] ?? '')),
                number_format((float)($r['grand_total'] ?? 0), 2, '.', '')
            ]);
        }
        fputcsv($out, ['', '', '', '', 'TOTAL', '', number_format($sum, 2, '.', '')]);
        fclose($out);
        exit;
    }

    // ---- render
    $this->view('orders/index', [
        'title'   => 'Orders',
        'rows'    => $rows,
        'stats'   => ['count' => $count, 'sum' => $sum],
        'filters' => [
            'from'=>$from,'to'=>$to,'q'=>$q,'status'=>$status,
            'supplier_id'=>$supplierId,'sr_id'=>$srId,
        ],
        'active'=>'orders','subactive'=>'orders.index',
    ], $ctx);
}

    public function create(array $ctx): void
    {
        $pdo  = $this->tenantPdo();
        $this->ensureOrdersSchema($pdo);

        // Resolve bases/paths
        $base = $this->base($ctx);              // e.g. /t/{slug}/apps/dms
        $post = $this->ordersPath($ctx);        // e.g. /t/{slug}/apps/dms/orders

        // Flash + CSRF
        if (session_status() !== PHP_SESSION_ACTIVE) @session_start();
        $flashErrors = $_SESSION['flash_errors'] ?? [];
        $old         = $_SESSION['form_old']     ?? [];
        unset($_SESSION['flash_errors'], $_SESSION['form_old']);

        if (empty($_SESSION['_csrf'])) {
            $_SESSION['_csrf'] = bin2hex(random_bytes(16));
        }
        $csrf = (string)$_SESSION['_csrf'];

        // Default form state
        $form = $old ?: [
            'order_date'     => date('Y-m-d'),
            'delivery_date'  => date('Y-m-d'),
            'discount_type'  => 'amount',
            'discount_value' => 0,
            'status'         => 'draft',
            'items'          => [],
        ];

        $this->view('orders/create', [
            'title'       => 'Create Order',
            'active'      => 'orders',
            'subactive'   => 'orders.create',
            'module_base' => $base,
            'form'        => $form,
            'flashErrors' => $flashErrors,
            'csrf'        => $csrf,                 // <— pass token to view
            'endpoints'   => [
                'postUrl'         => $post,         // <— exact collection URL
                'lookupProducts'  => $base.'/api/lookup/products',
                'lookupCustomers' => $base.'/api/lookup/customers',
                'lookupSuppliers' => $base.'/api/lookup/suppliers',
                'nextOrderNo'     => $base.'/api/orders/next-no',   // <— for live next no
                'createCustomer'  => $base.'/api/customers',        // <— for "Save New Customer"
                'hydrateUrl'      => null,
            ],
        ], $ctx);
    }

    public function edit(array $ctx, int $id): void {
        $pdo=$this->tenantPdo(); $this->ensureOrdersSchema($pdo);
        $orgId=$this->orgId($ctx); $base=$this->base($ctx);

        $h=$pdo->prepare("SELECT * FROM dms_orders WHERE org_id=? AND id=?");
        $h->execute([$orgId,$id]); $order=$h->fetch(PDO::FETCH_ASSOC);
        if (!$order) $this->abort404('Order not found.');

        $it=$pdo->prepare("SELECT id,product_id,product_name,qty,unit_price,line_total FROM dms_order_items WHERE org_id=? AND order_id=? ORDER BY id");
        $it->execute([$orgId,$id]); $order['items']=$it->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $this->view('orders/create', [
            'title'=>'Edit Order','order'=>$order,'active'=>'orders','subactive'=>'orders.edit',
            'module_base'=>$base,'hydrateUrl'=>null,
            'endpoints'=>[
                'postUrl'=>rtrim($base,'/').'/orders/'.$id,
                'lookupProducts'  => $base.'/api/lookup/products',
                'lookupCustomers' => $base.'/api/lookup/customers',
                'lookupSuppliers' => $base.'/api/lookup/suppliers',
                'nextOrderNo'     => $base.'/api/orders/next-no',
                'createCustomer'  => $base.'/api/customers',
            ],
        ], $ctx);
    }

    public function show(array $ctx, int $id): void {
        $pdo=$this->tenantPdo(); $this->ensureOrdersSchema($pdo);
        $orgId=$this->orgId($ctx);

        $h=$pdo->prepare("SELECT * FROM dms_orders WHERE org_id=? AND id=?");
        $h->execute([$orgId,$id]); $order=$h->fetch(PDO::FETCH_ASSOC);
        if (!$order) $this->abort404('Order not found.');

        $i=$pdo->prepare("SELECT id,product_id,product_name,qty,unit_price,line_total FROM dms_order_items WHERE org_id=? AND order_id=? ORDER BY id");
        $i->execute([$orgId,$id]); $items=$i->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $this->view('orders/show', [
            'title'=>'Order #'.((string)($order['order_no'] ?? '')),'order'=>$order,'items'=>$items,
            'active'=>'orders','subactive'=>'orders.view',
        ], $ctx);
    }

    /* ===============================================================
     * SEGMENT E — STORE (POST /orders)
     * =============================================================== */
    public function store(array $ctx): void
    {
        $pdo   = $this->tenantPdo();
        $this->ensureOrdersSchema($pdo);

        $orgId = (int)($this->orgId($ctx) ?: 0);
        if ($orgId <= 0) $this->abort500('Missing tenant org_id.');

        /* ---------------------------------------------------------------
         * Normalize incoming form data
         * ------------------------------------------------------------- */
        $order_no      = trim((string)($_POST['order_no'] ?? ''));
        $order_date    = trim((string)($_POST['order_date'] ?? date('Y-m-d')));
        $delivery_date = trim((string)($_POST['delivery_date'] ?? $order_date));

        $customer_id   = (int)($_POST['customer_id'] ?? 0) ?: null;
        $customer_name = trim((string)($_POST['customer_name'] ?? ''));

        $supplier_id   = (int)($_POST['supplier_id'] ?? 0) ?: null;
        $supplier_name = trim((string)($_POST['supplier_name'] ?? ''));

        $sr_user_id    = (int)($_POST['sr_user_id']  ?? 0) ?: null;
        $dsr_user_id   = (int)($_POST['dsr_user_id'] ?? 0) ?: null;

        $discount_type = in_array(($_POST['discount_type'] ?? 'amount'), ['amount','percent'], true)
            ? (string)$_POST['discount_type'] : 'amount';
        $discount_value = (float)($_POST['discount_value'] ?? 0);

        $status = $this->normalizeStatus((string)($_POST['status'] ?? 'draft'));
        $notes  = trim((string)($_POST['notes'] ?? ''));

        /* ---------------------------------------------------------------
         * Item parsing & totals
         * ------------------------------------------------------------- */
        $rawItems = is_array($_POST['items'] ?? null) ? $_POST['items'] : [];
        $items = [];
        $subtotal = 0.0;

        foreach ($rawItems as $r) {
            $pid  = (int)($r['product_id'] ?? 0) ?: null;
            $name = trim((string)($r['product_name'] ?? ''));
            $qty  = (float)($r['qty'] ?? 0);
            $rate = (float)($r['unit_price'] ?? $r['price'] ?? 0);

            if ($name !== '' && $qty > 0) {
                $line = [
                    'product_id'   => $pid,
                    'product_name' => $name,
                    'qty'          => $qty,
                    'unit_price'   => $rate,
                    'line_total'   => $qty * $rate,
                ];
                $items[] = $line;
                $subtotal += $line['line_total'];
            }
        }

        $discountAmt = ($discount_type === 'percent')
            ? min($subtotal, $subtotal * ($discount_value / 100))
            : min($subtotal, $discount_value);

        $grand_total = max(0, $subtotal - $discountAmt);

        /* ---------------------------------------------------------------
         * Allocate/fix order number if missing or duplicate
         * ------------------------------------------------------------- */
        $attempt = 0;
        do {
            if ($order_no === '') {
                $order_no = $this->nextDocNo($pdo, $orgId, 'orders', 'ORD');
            } else {
                $chk = $pdo->prepare("SELECT 1 FROM dms_orders WHERE org_id = ? AND order_no = ? LIMIT 1");
                $chk->execute([$orgId, $order_no]);
                if ($chk->fetchColumn()) {
                    $order_no = '';
                }
            }
            $attempt++;
        } while ($order_no === '' && $attempt < 4);

        /* ---------------------------------------------------------------
         * Insert order + items within a transaction
         * ------------------------------------------------------------- */
        $pdo->beginTransaction();
        try {
            $orderId = $this->insertSmart($pdo, 'dms_orders', [
                'org_id'         => $orgId,
                'order_no'       => $order_no,
                'order_date'     => $order_date,
                'delivery_date'  => $delivery_date,
                'customer_id'    => $customer_id,
                'customer_name'  => $customer_name,
                'supplier_id'    => $supplier_id,
                'supplier_name'  => $supplier_name,
                'sr_user_id'     => $sr_user_id,
                'dsr_user_id'    => $dsr_user_id,
                'discount_type'  => $discount_type,
                'discount_value' => $discount_value,
                'grand_total'    => $grand_total,
                'status'         => $status,
                'notes'          => $notes,
            ]);

            foreach ($items as $row) {
                $this->insertSmart($pdo, 'dms_order_items', [
                    'org_id'       => $orgId,
                    'order_id'     => $orderId,
                    'product_id'   => $row['product_id'],
                    'product_name' => $row['product_name'],
                    'qty'          => $row['qty'],
                    'unit_price'   => $row['unit_price'],
                    'line_total'   => $row['line_total'],
                ]);
            }

            $pdo->commit();

            if (session_status() !== PHP_SESSION_ACTIVE) @session_start();
            $_SESSION['_ok'] = "Order {$order_no} created successfully.";
            header('Location: '.$this->ordersPath($ctx), true, 303);
            exit;
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            if (session_status() !== PHP_SESSION_ACTIVE) @session_start();
            $_SESSION['flash_errors'] = ['Order save failed: '.$e->getMessage()];
            $_SESSION['form_old']     = $_POST;
            header('Location: '.$this->ordersPath($ctx).'/create', true, 302);
            exit;
        }
    }

   /* ===============================================================
 * SEGMENT F — UPDATE (POST /orders/{id})
 * =============================================================== */
public function update(array $ctx, int $id): void
{
    $pdo = $this->tenantPdo();
    $this->ensureOrdersSchema($pdo);

    $orgId = (int)($this->orgId($ctx) ?: 0);
    if ($orgId <= 0) $this->abort500('Missing tenant org_id.');

    $order_no      = trim((string)($_POST['order_no'] ?? ''));
    $order_date    = (string)($_POST['order_date'] ?? date('Y-m-d'));
    $delivery_date = (string)($_POST['delivery_date'] ?? $order_date);

    $customer_id   = (int)($_POST['customer_id'] ?? 0) ?: null;
    $customer_name = trim((string)($_POST['customer_name'] ?? ''));

    // supplier (soft nullable)
    $supplier_id = (function($v){ $v=trim((string)$v); return (ctype_digit($v) && (int)$v>0) ? (int)$v : null; })($_POST['supplier_id'] ?? '');
    $supplier_name = trim((string)($_POST['supplier_name'] ?? ''));

    $discType = (string)($_POST['discount_type'] ?? 'amount');
    if (!in_array($discType, ['amount','percent'], true)) $discType = 'amount';
    $discVal  = (float)($_POST['discount_value'] ?? 0);

    $status = $this->normalizeStatus((string)($_POST['status'] ?? 'draft'));
    $notes  = (string)($_POST['notes'] ?? '');

    /* ------------ Items & totals (server authoritative) ------------ */
    $raw   = is_array($_POST['items'] ?? null) ? $_POST['items'] : [];
    $items = [];

    foreach ($raw as $r) {
        $pid  = (int)($r['product_id'] ?? 0) ?: null;
        $name = trim((string)($r['product_name'] ?? ''));
        $qty  = (float)($r['qty'] ?? 0);
        $rate = (float)($r['unit_price'] ?? ($r['price'] ?? 0));

        // If only product_id provided, try to hydrate name
        if ($name === '' && $pid) {
            try {
                $q = $pdo->prepare("SELECT name FROM dms_products WHERE org_id = :o AND id = :p");
                $q->execute([':o'=>$orgId, ':p'=>$pid]);
                $name = (string)($q->fetchColumn() ?: ("PID-{$pid}"));
            } catch (\Throwable) {}
        }

        if ($name !== '' && $qty > 0) {
            $items[] = [
                'product_id'   => $pid,
                'product_name' => $name,
                'qty'          => $qty,
                'unit_price'   => $rate,
                'line_total'   => $qty * $rate,
            ];
        }
    }

    $subtotal = 0.0; foreach ($items as $ln) $subtotal += (float)$ln['line_total'];
    $discount = ($discType === 'percent')
        ? round($subtotal * max(0, min(100, $discVal)) / 100, 2)
        : min($subtotal, max(0, $discVal));
    $grand = max(0, $subtotal - $discount);

    /* ------------ Order number (dedupe per org) ------------ */
    if ($order_no !== '') {
        $chk = $pdo->prepare("SELECT 1 FROM dms_orders WHERE org_id = :o AND order_no = :no AND id <> :id LIMIT 1");
        $chk->execute([':o'=>$orgId, ':no'=>$order_no, ':id'=>$id]);
        if ($chk->fetchColumn()) {
            // allocate a fresh one from the counter
            $order_no = $this->nextDocNo($pdo, $orgId, 'orders', 'ORD');
        }
    } else {
        $order_no = $this->nextDocNo($pdo, $orgId, 'orders', 'ORD');
    }

    /* ------------ Persist (all named placeholders) ------------ */
    $pdo->beginTransaction();
    try {
        $this->updateSmart(
            $pdo,
            'dms_orders',
            [
                'order_no'       => $order_no,
                'order_date'     => $order_date,
                'delivery_date'  => $delivery_date,
                'customer_id'    => $customer_id,
                'customer_name'  => $customer_name,
                'supplier_id'    => $supplier_id,
                'supplier_name'  => $supplier_name,
                'discount_type'  => $discType,
                'discount_value' => $discVal,
                'grand_total'    => $grand,
                'status'         => $status,
                'notes'          => $notes,
            ],
            // ↓↓↓ IMPORTANT: named placeholders (no mixing)
            'org_id = :w1 AND id = :w2',
            [':w1' => $orgId, ':w2' => $id]
        );

        // Rebuild items
        $del = $pdo->prepare("DELETE FROM dms_order_items WHERE org_id = :o AND order_id = :id");
        $del->execute([':o'=>$orgId, ':id'=>$id]);

        foreach ($items as $row) {
            $payload = $this->buildItemRow($pdo, $orgId, $id, $row);
            if ($payload) {
                $this->insertSmart($pdo, 'dms_order_items', $payload);
            }
        }

        $pdo->commit();

        if (ob_get_length()) @ob_end_clean();
        header('Location: '.$this->listUrl($ctx), true, 303);
        exit;
    } catch (\Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $this->abort500('Update failed: '.$e->getMessage());
    }
}

    /* ===============================================================
 * SEGMENT G — SMALL APIs NEEDED BY THE VIEW (no features dropped)
 * =============================================================== */

/** GET {base}/api/orders/next-no -> { ok:true, next_no:"..." } */
public function apiNextOrderNo(array $ctx): void
{
    $pdo   = $this->tenantPdo();
    $orgId = (int)$this->orgId($ctx);
    if ($orgId <= 0) $this->json(['ok'=>false,'error'=>'Missing org.'], 400);

    try {
        $next = $this->nextDocNo($pdo, $orgId, 'orders', 'ORD');
        $this->json(['ok'=>true,'next_no'=>$next]);
    } catch (Throwable $e) {
        $this->json(['ok'=>false,'error'=>'Failed to compute next number.'], 500);
    }
}

/**
 * POST {base}/api/customers
 * Body: _csrf, name, phone?, address?
 * Returns: { ok:true, customer:{ id, name, code? } }
 */
public function apiCreateCustomer(array $ctx): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') $this->json(['ok'=>false,'error'=>'POST required.'], 405);
    if (!\App\Support\Csrf::verify($_POST['_csrf'] ?? '')) $this->json(['ok'=>false,'error'=>'Session expired.'], 419);

    $pdo   = $this->tenantPdo();
    $orgId = (int)$this->orgId($ctx);
    if ($orgId <= 0) $this->json(['ok'=>false,'error'=>'Missing org.'], 400);

    $name  = trim((string)($_POST['name'] ?? ''));
    $phone = trim((string)($_POST['phone'] ?? ''));
    $addr  = trim((string)($_POST['address'] ?? ''));
    if ($name === '') $this->json(['ok'=>false,'error'=>'Name is required.'], 422);

    try {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS dms_customer_seq (
                org_id  INT UNSIGNED NOT NULL PRIMARY KEY,
                last_no INT UNSIGNED NOT NULL DEFAULT 0
            ) ENGINE=InnoDB
        ");
        $pdo->beginTransaction();
        $sel = $pdo->prepare("SELECT last_no FROM dms_customer_seq WHERE org_id=:o FOR UPDATE");
        $sel->execute([':o'=>$orgId]);
        $row  = $sel->fetch(PDO::FETCH_ASSOC);
        $last = $row ? (int)$row['last_no'] : 0;
        if (!$row) {
            $pdo->prepare("INSERT INTO dms_customer_seq(org_id,last_no) VALUES (:o,0)")
                ->execute([':o'=>$orgId]);
        }
        $next = $last + 1;
        $pdo->prepare("UPDATE dms_customer_seq SET last_no=:n WHERE org_id=:o")
            ->execute([':n'=>$next, ':o'=>$orgId]);
        $code = 'CID-'.str_pad((string)$next, 6, '0', STR_PAD_LEFT);

        $ins = $pdo->prepare("
            INSERT INTO dms_customers (org_id,name,phone,address,code,created_at)
            VALUES (:o,:n,:p,:a,:c,NOW())
        ");
        $ins->execute([':o'=>$orgId, ':n'=>$name, ':p'=>$phone, ':a'=>$addr, ':c'=>$code]);
        $id = (int)$pdo->lastInsertId();
        $pdo->commit();

        $this->json(['ok'=>true,'customer'=>['id'=>$id,'name'=>$name,'code'=>$code]]);
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        // Fallback insert without code
        try {
            $ins = $pdo->prepare("
                INSERT INTO dms_customers (org_id,name,phone,address,code,created_at)
                VALUES (:o,:n,:p,:a,'',NOW())
            ");
            $ins->execute([':o'=>$orgId, ':n'=>$name, ':p'=>$phone, ':a'=>$addr]);
            $id = (int)$pdo->lastInsertId();
            $this->json(['ok'=>true,'customer'=>['id'=>$id,'name'=>$name,'code'=>'']]);
        } catch (Throwable) {
            $this->json(['ok'=>false,'error'=>'Failed to create customer.'], 500);
        }
    }
}   // <<< this closes apiCreateCustomer()


/* ===============================================================
 * SEGMENT H — ISSUE INVOICE (GET→redirect or POST→create)
 * =============================================================== */
public function issueInvoice(array $ctx, int $orderId): void
{
    $pdo  = $this->tenantPdo();
    $this->ensureOrdersSchema($pdo);

    $base   = $this->base($ctx);
    $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
    $ctxOrg = (int)($this->orgId($ctx) ?: 0);

    $h = $ctxOrg > 0
        ? $pdo->prepare("SELECT id, org_id, order_no, order_date, customer_id, customer_name, grand_total, notes, status FROM dms_orders WHERE org_id=? AND id=? FOR UPDATE")
        : $pdo->prepare("SELECT id, org_id, order_no, order_date, customer_id, customer_name, grand_total, notes, status FROM dms_orders WHERE id=? FOR UPDATE");
    $ctxOrg > 0 ? $h->execute([$ctxOrg, $orderId]) : $h->execute([$orderId]);
    $order = $h->fetch(PDO::FETCH_ASSOC);
    if (!$order) $this->abort404('Order not found.');

    $orgId = (int)($order['org_id'] ?? 0);
    if ($orgId <= 0 && $ctxOrg > 0) $orgId = $ctxOrg;
    if ($orgId <= 0) $this->abort500('Order has no org_id.');

    $it = $pdo->prepare("
        SELECT product_id, product_name, qty, unit_price, line_total
        FROM dms_order_items
        WHERE org_id=? AND order_id=?
        ORDER BY id
    ");
    $it->execute([$orgId, $orderId]);
    $items = $it->fetchAll(PDO::FETCH_ASSOC) ?: [];

    if ($method === 'GET') {
        header('Location: '.$base.'/sales/create?order_id='.(int)$orderId, true, 302);
        exit;
    }
    if (!$items) $this->abort500('Cannot issue invoice: no items on this order.');

    // Avoid duplicates
    $c = $pdo->prepare("SELECT id FROM dms_sales WHERE org_id=? AND order_id=? LIMIT 1");
    $c->execute([$orgId, $orderId]);
    if ($existing = (int)$c->fetchColumn()) { $this->redirect($base.'/sales/'.$existing); return; }

    $saleNo     = $this->nextDocNo($pdo, $orgId, 'invoice', 'INV');
    $saleDate   = (string)($order['order_date'] ?? date('Y-m-d'));
    $customerId = $order['customer_id'] ?? null;
    $customerNm = (string)($order['customer_name'] ?? '');
    $grand      = (float)($order['grand_total'] ?? 0.0);
    $notes      = (string)($order['notes'] ?? '');

    $pdo->beginTransaction();
    try {
        $saleId = 0; $attempt = 0;
        do {
            try {
                $q = $pdo->prepare("
                    INSERT INTO dms_sales
                      (org_id, order_id, sale_no, sale_date, customer_id, customer_name,
                       discount_type, discount_value, grand_total,
                       status, invoice_status, notes, created_at, updated_at)
                    VALUES
                      (?,?,?,?,?,?, 'amount',0.0, ?, 'confirmed','issued', ?, NOW(), NOW())
                ");
                $q->execute([$orgId, $orderId, $saleNo, $saleDate, $customerId, $customerNm, $grand, $notes]);
                $saleId = (int)$pdo->lastInsertId();
                break;
            } catch (\PDOException $e) {
                if ((int)($e->errorInfo[1] ?? 0) === 1062 && $attempt < 3) {
                    $saleNo = $this->nextDocNo($pdo, $orgId, 'invoice', 'INV'); $attempt++; continue;
                }
                throw $e;
            }
        } while ($attempt < 4);

        $li = $pdo->prepare("
            INSERT INTO dms_sale_items
              (org_id, sale_id, product_id, product_name, qty, unit_price, line_total)
            VALUES
              (?,?,?,?,?,?,?)
        ");
        foreach ($items as $r) {
            $li->execute([
                $orgId, $saleId,
                (int)($r['product_id'] ?? 0) ?: null,
                (string)($r['product_name'] ?? ''),
                (float)($r['qty'] ?? 0),
                (float)($r['unit_price'] ?? 0),
                (float)($r['line_total'] ?? 0),
            ]);
        }

        $pdo->prepare("UPDATE dms_orders SET status='issued', updated_at=NOW() WHERE org_id=? AND id=?")
            ->execute([$orgId, $orderId]);

        $pdo->commit();
        $this->redirect($base.'/sales/'.$saleId);
    } catch (Throwable $e) {
        $pdo->rollBack();
        $this->abort500('Failed to issue invoice: '.$e->getMessage());
    }
}


/* ===============================================================
 * SEGMENT I — PRINT & SHARE HELPERS
 * =============================================================== */

/* ===============================================================
 * PRINT (route target) — delegates to printOrder()
 * =============================================================== */
public function print(array $ctx, int $id): void
{
    // If your implementation is named printOrder():
    if (method_exists($this, 'printOrder')) {
        $this->printOrder($ctx, $id);
        return;
    }

    // Fallback: full print logic (kept short, calls the existing view)
    $pdo = $this->tenantPdo();
    $this->ensureOrdersSchema($pdo);

    $orgId = (int)$this->orgId($ctx);
    $h = $pdo->prepare("SELECT * FROM dms_orders WHERE org_id=? AND id=?");
    $h->execute([$orgId, $id]);
    $order = $h->fetch(\PDO::FETCH_ASSOC);
    if (!$order) $this->abort404('Order not found.');

    $i = $pdo->prepare("
        SELECT * FROM dms_order_items
        WHERE org_id=? AND order_id=?
        ORDER BY id
    ");
    $i->execute([$orgId, $id]);
    $items = $i->fetchAll(\PDO::FETCH_ASSOC) ?: [];

    header('X-Robots-Tag: noindex, nofollow');
    $this->view('orders/print', [
        'title'       => 'Order ' . ($order['order_no'] ?: ('#'.$id)),
        'order'       => $order,
        'items'       => $items,
        'org'         => $this->orgBranding($pdo, $orgId),
        'module_base' => $this->base($ctx),
    ], $ctx);
}

// in OrdersController
private function orgBranding(PDO $pdo, int $orgId): array {
    $row = ['name'=>'', 'phone'=>'', 'email'=>'', 'address'=>'', 'logo_url'=>''];
    try {
        $q = $pdo->prepare("
            SELECT COALESCE(name,'')               AS name,
                   COALESCE(owner_mobile,company_phone,'') AS phone,
                   COALESCE(owner_email, support_email,'') AS email,
                   COALESCE(company_address,'')    AS address,
                   COALESCE(logo_path,'')          AS db_logo
            FROM cp_organizations WHERE id=? LIMIT 1");
        $q->execute([$orgId]);
        if ($r = $q->fetch(PDO::FETCH_ASSOC)) {
            $row['name']    = (string)$r['name'];
            $row['phone']   = (string)$r['phone'];
            $row['email']   = (string)$r['email'];
            $row['address'] = (string)$r['address'];
            $logo = trim((string)$r['db_logo']);
            if ($logo !== '') {
                if ($logo[0] !== '/') $logo = '/'.ltrim($logo,'/');
                $row['logo_url'] = $logo;
            }
        }
    } catch (\Throwable) {}
    return $row;
}

private function tenantContact(PDO $pdo, int $orgId): array
{
    $row = ['email'=>'', 'phone'=>''];
    try {
        $q = $pdo->prepare("
            SELECT COALESCE(owner_email, support_email) AS email,
                   COALESCE(owner_mobile, company_phone) AS phone
            FROM cp_organizations WHERE id=? LIMIT 1
        ");
        $q->execute([$orgId]);
        if ($r = $q->fetch(PDO::FETCH_ASSOC)) {
            $row['email'] = trim((string)($r['email'] ?? ''));
            $row['phone'] = preg_replace('~\D+~','',(string)($r['phone'] ?? ''));
        }
    } catch (Throwable) {}
    return $row;
}

private function shareText(array $order, array $items, array $org): string
{
    $no   = (string)($order['order_no'] ?? ('#'.$order['id']));
    $date = (string)substr((string)($order['order_date'] ?? ''),0,10);
    $cust = (string)($order['customer_name'] ?? '');
    $sum  = number_format((float)($order['grand_total'] ?? 0), 2);
    $lines = [];
    foreach (array_slice($items, 0, 4) as $ln) {
        $nm = (string)($ln['product_name'] ?? '');
        $q  = (float)($ln['qty'] ?? 0);
        $p  = (float)($ln['unit_price'] ?? 0);
        $lines[] = "• {$nm} — {$q} × ".number_format($p,2);
    }
    if (count($items) > 4) $lines[] = '• …';
    $orgName = trim((string)($org['name'] ?? ''));
    $base = rtrim($this->base(),'/');
    $link = $base.'/orders/'.$order['id'].'/print';

    $txt  = $orgName ? ($orgName."\n") : '';
    $txt .= "Order: {$no}  |  Date: {$date}\n";
    $txt .= ($cust ? "Customer: {$cust}\n" : "");
    if ($lines) $txt .= implode("\n", $lines)."\n";
    $txt .= "Total: ৳ {$sum}\n";
    $txt .= "View/Print: ".(isset($_SERVER['HTTP_HOST']) ? (($_SERVER['REQUEST_SCHEME'] ?? 'https').'://'.$_SERVER['HTTP_HOST'].$link) : $link);
    return $txt;
}

public function shareWhatsApp(array $ctx, int $id): void
{
    $pdo   = $this->tenantPdo();
    $this->ensureOrdersSchema($pdo);

    $orgId = $this->orgId($ctx);

    $h = $pdo->prepare("SELECT * FROM dms_orders WHERE org_id=? AND id=?");
    $h->execute([$orgId,$id]);
    $order = $h->fetch(PDO::FETCH_ASSOC);
    if (!$order) $this->abort404('Order not found.');

    $i = $pdo->prepare("
      SELECT product_id, product_name, qty, unit_price, line_total
      FROM dms_order_items WHERE org_id=? AND order_id=? ORDER BY id
    ");
    $i->execute([$orgId,$id]);
    $items = $i->fetchAll(PDO::FETCH_ASSOC) ?: [];

    $orgBrand = $this->orgBranding($pdo, (int)$orgId);
    $text     = $this->shareText($order, $items, $orgBrand);

    $phone = preg_replace('~\D+~','', (string)($_GET['phone'] ?? ($order['customer_phone'] ?? '')));
    $q     = http_build_query(['text'=>$text], '', '&', PHP_QUERY_RFC3986);
    $url   = $phone ? ("https://wa.me/".$phone."?".$q) : ("https://wa.me/?".$q);

    header('Location: '.$url, true, 302);
    exit;
}

public function shareEmail(array $ctx, int $id): void
{
    $pdo   = $this->tenantPdo();
    $this->ensureOrdersSchema($pdo);

    $orgId = $this->orgId($ctx);
    $base  = $this->base($ctx);

    $h = $pdo->prepare("SELECT * FROM dms_orders WHERE org_id=? AND id=?");
    $h->execute([$orgId,$id]);
    $order = $h->fetch(PDO::FETCH_ASSOC);
    if (!$order) $this->abort404('Order not found.');

    $i = $pdo->prepare("
      SELECT product_id, product_name, qty, unit_price, line_total
      FROM dms_order_items WHERE org_id=? AND order_id=? ORDER BY id
    ");
    $i->execute([$orgId,$id]);
    $items = $i->fetchAll(PDO::FETCH_ASSOC) ?: [];

    $tenant    = $this->tenantContact($pdo, (int)$orgId);
    $fromEmail = (string)$tenant['email'];
    if ($fromEmail === '') $this->abort500('Tenant default email is not configured.');

    $to = trim((string)($_POST['to'] ?? ($order['customer_email'] ?? '')));
    if ($to === '') {
        if (session_status() !== PHP_SESSION_ACTIVE) @session_start();
        $_SESSION['flash_errors'] = ['No recipient email. Provide ?to= or store customer_email on the order.'];
        header('Location: '.$base.'/orders/'.$id, true, 302);
        exit;
    }
    $cc = trim((string)($_POST['cc'] ?? ''));

    $orgBrand = $this->orgBranding($pdo, (int)$orgId);
    $subject  = sprintf('Order %s from %s', (string)($order['order_no'] ?? '#'.$id), (string)($orgBrand['name'] ?? ''));
    $text     = $this->shareText($order, $items, $orgBrand);

    $printUrl = (isset($_SERVER['HTTP_HOST'])
        ? (($_SERVER['REQUEST_SCHEME'] ?? 'https').'://'.$_SERVER['HTTP_HOST'].$this->base($ctx).'/orders/'.$id.'/print')
        : ($this->base($ctx).'/orders/'.$id.'/print'));

    $html = '<div style="font-family:system-ui,Segoe UI,Arial;line-height:1.5">'
          . '<h2 style="margin:0 0 8px">'.htmlspecialchars($subject,ENT_QUOTES,'UTF-8').'</h2>'
          . '<pre style="white-space:pre-wrap">'.htmlspecialchars($text,ENT_QUOTES,'UTF-8').'</pre>'
          . '<p style="margin-top:16px"><a href="'.htmlspecialchars($printUrl,ENT_QUOTES,'UTF-8').'" target="_blank">View / Print</a></p>'
          . '</div>';

    $boundary = 'b'.md5(uniqid((string)mt_rand(), true));
    $headers  = [];
    $headers[] = "From: {$fromEmail}";
    if ($cc !== '') $headers[] = "Cc: {$cc}";
    $headers[] = "MIME-Version: 1.0";
    $headers[] = "Content-Type: multipart/alternative; boundary=\"{$boundary}\"";

    $body  = "--{$boundary}\r\n";
    $body .= "Content-Type: text/plain; charset=UTF-8\r\n\r\n";
    $body .= $text . "\r\n";
    $body .= "--{$boundary}\r\n";
    $body .= "Content-Type: text/html; charset=UTF-8\r\n\r\n";
    $body .= $html . "\r\n";
    $body .= "--{$boundary}--\r\n";

    @mail($to, $subject, $body, implode("\r\n", $headers));

    if (session_status() !== PHP_SESSION_ACTIVE) @session_start();
    $_SESSION['flash_success'] = ['Order shared via email.'];
    header('Location: '.$base.'/orders/'.$id, true, 302);
    exit;
}
}