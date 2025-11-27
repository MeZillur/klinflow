<?php
declare(strict_types=1);

namespace Modules\DMS\Controllers;

use PDO;

final class SalesController extends BaseController
{
    /* =========================
     * Helpers
     * ========================= */
    private function ensureBase(array &$ctx): void
    {
        $slug = (string)($ctx['slug'] ?? ($_SESSION['tenant_org']['slug'] ?? ''));
        if ($slug === '' || $slug === '_') {
            if (!empty($_SERVER['REQUEST_URI']) &&
                preg_match('#^/t/([^/]+)/apps/dms#', (string)$_SERVER['REQUEST_URI'], $m)) {
                $slug = $m[1];
            }
        }
        $ctx['slug'] = $slug;

        $mb = (string)($ctx['module_base'] ?? '');
        if ($mb === '' || !preg_match('#^/t/[^/]+/apps/dms$#', $mb)) {
            $ctx['module_base'] = ($slug !== '' && $slug !== '_')
                ? '/t/' . rawurlencode($slug) . '/apps/dms'
                : '/apps/dms';
        }
    }

    private function resolveOrgId(array &$ctx, PDO $pdo): int
    {
        $id = (int)($ctx['org']['id'] ?? 0);
        if ($id > 0) return $id;

        $id = (int)($_SESSION['tenant_org']['id'] ?? 0);
        if ($id > 0) { $ctx['org']['id'] = $id; return $id; }

        $slug = (string)($ctx['slug'] ?? '');
        if ($slug === '' || $slug === '_') {
            if (!empty($_SERVER['REQUEST_URI']) &&
                preg_match('#^/t/([^/]+)/apps/dms#', (string)$_SERVER['REQUEST_URI'], $m)) {
                $slug = $m[1];
                $ctx['slug'] = $slug;
            }
        }
        if ($slug !== '' && $this->hasColumn($pdo, 'cp_organizations', 'slug')) {
            $st = $pdo->prepare("SELECT id FROM cp_organizations WHERE slug=? LIMIT 1");
            $st->execute([$slug]);
            $id = (int)($st->fetchColumn() ?: 0);
            if ($id > 0) { $ctx['org']['id'] = $id; return $id; }
        }

        $st = $pdo->query("SELECT id FROM cp_organizations ORDER BY id LIMIT 1");
        $id = (int)($st?->fetchColumn() ?: 0);
        if ($id > 0) { $ctx['org']['id'] = $id; return $id; }

        throw new \RuntimeException('No tenant organization in context (org_id empty).');
    }

  
  		/** Detect the correct sale-items table name. Returns string or null if none. */
		private function findSaleItemsTable(PDO $pdo): ?string
		{
    		foreach (['dms_sale_items', 'dms_sales_items'] as $cand) {
        		if ($this->hasTable($pdo, $cand)) return $cand;
    		}
    		return null;
			}
  
  
  /** Load header + items for a sale (used by print/edit). Throws 404 if header missing. */
private function loadSaleWithItems(PDO $pdo, int $orgId, int $id): array
{
    $h = $pdo->prepare("SELECT * FROM dms_sales WHERE org_id=? AND id=? LIMIT 1");
    $h->execute([$orgId, $id]);
    $sale = $h->fetch(PDO::FETCH_ASSOC) ?: null;
    if (!$sale) $this->abort404('Invoice not found.');

    $items = [];
    if ($tbl = $this->findSaleItemsTable($pdo)) {
        $q = $pdo->prepare("SELECT * FROM {$tbl} WHERE org_id=? AND sale_id=? ORDER BY id");
        $q->execute([$orgId, $id]);
        $items = $q->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
    return [$sale, $items];
}

/**
 * Render a standalone view WITHOUT wrapping it in the module shell.
 * Use this for print pages that are full HTML documents.
 */
private function renderPrintView(string $viewRel, array $vars, array $ctx): void
{
    $moduleDir = (string)($ctx['module_dir'] ?? dirname(__DIR__, 2));
    $file = rtrim($moduleDir, '/').'/Views/'.$viewRel.'.php';

    if (!is_file($file)) {
        http_response_code(404);
        header('Content-Type: text/plain; charset=utf-8');
        echo "Printable view not found: {$viewRel}";
        exit;
    }

    header('Content-Type: text/html; charset=utf-8');
    (static function($__file, $__vars) {
        extract($__vars, EXTR_SKIP);
        require $__file;
    })($file, $vars);
}

/** Normalize org array for documents (logo/name/address/phone/email keys guaranteed). */
private function normalizeOrgForDoc(array $ctx): array
{
    $org = is_array($ctx['org'] ?? null) ? $ctx['org'] : [];
    $org += ['name'=>'','address'=>'','phone'=>'','email'=>'','logo_url'=>''];
    if (($org['logo_url'] ?? '') === '') {
        $org['logo_url'] = '/public/assets/brand/logo.png';
    }
    return $org;
}
  
    private function hasTable(PDO $pdo, string $table): bool
    {
        $st = $pdo->prepare("SELECT 1 FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1");
        $st->execute([$table]);
        return (bool)$st->fetchColumn();
    }
    private function hasColumn(PDO $pdo, string $table, string $column): bool
    {
        $st = $pdo->prepare("SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1");
        $st->execute([$table, $column]);
        return (bool)$st->fetchColumn();
    }

    private function nextInvoiceNo(PDO $pdo, int $orgId): string
    {
        $y = (int)date('Y');
        if ($this->hasTable($pdo,'dms_counters')) {
            $st = $pdo->prepare("
                INSERT INTO dms_counters (org_id, name, y, seq)
                VALUES (?,?,?,0)
                ON DUPLICATE KEY UPDATE seq = LAST_INSERT_ID(seq + 1)
            ");
            $st->execute([$orgId,'invoice',$y]);
            $seq = (int)$pdo->lastInsertId();
            if ($seq > 0) return sprintf('INV-%d-%05d', $y, $seq);
        }
        if ($this->hasTable($pdo,'dms_sales') && $this->hasColumn($pdo,'dms_sales','sale_no')) {
            $st = $pdo->prepare("
                SELECT COALESCE(MAX(CAST(SUBSTRING_INDEX(sale_no, '-', -1) AS UNSIGNED)), 0)
                FROM dms_sales
                WHERE org_id=? AND sale_no LIKE CONCAT('INV-', ?, '-%')
            ");
            $st->execute([$orgId, $y]);
            $seq = ((int)$st->fetchColumn()) + 1;
            return sprintf('INV-%d-%05d', $y, $seq);
        }
        return sprintf('INV-%d-%s', $y, strtoupper(bin2hex(random_bytes(3))));
    }

    private function ensureUniqueSaleNo(PDO $pdo, int $orgId, string $requestedNo): string
    {
        $saleNo = trim($requestedNo);
        if ($saleNo === '') return $this->nextInvoiceNo($pdo, $orgId);
        if ($this->hasTable($pdo,'dms_sales') && $this->hasColumn($pdo,'dms_sales','sale_no')) {
            $st = $pdo->prepare("SELECT 1 FROM dms_sales WHERE org_id=? AND sale_no=? LIMIT 1");
            $st->execute([$orgId, $saleNo]);
            if ($st->fetchColumn()) return $this->nextInvoiceNo($pdo, $orgId);
        }
        return $saleNo;
    }
		
  
  		/** Ensure challan tables exist (safe, non-destructive) */
private function ensureChallanSchema(PDO $pdo): void
{
    $pdo->exec("CREATE TABLE IF NOT EXISTS dms_challans(
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        org_id BIGINT UNSIGNED NOT NULL,
        sale_id BIGINT UNSIGNED NOT NULL,
        challan_no VARCHAR(64) NOT NULL,
        challan_date DATE NULL,
        customer_id BIGINT UNSIGNED NULL,
        customer_name VARCHAR(190) NULL,
        status ENUM('draft','ready','dispatched','delivered','cancelled') NOT NULL DEFAULT 'ready',
        vehicle_no VARCHAR(64) NULL,
        driver_name VARCHAR(128) NULL,
        dispatch_at DATETIME NULL,
        notes TEXT NULL,
        created_at DATETIME NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY(id),
        UNIQUE KEY uq_org_no (org_id, challan_no),
        KEY idx_org_sale (org_id, sale_id),
        KEY idx_org_status (org_id, status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS dms_challan_items(
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        org_id BIGINT UNSIGNED NOT NULL,
        challan_id BIGINT UNSIGNED NOT NULL,
        sale_item_id BIGINT UNSIGNED NULL,
        product_id BIGINT UNSIGNED NULL,
        product_name VARCHAR(255) NULL,
        qty DECIMAL(16,4) NOT NULL DEFAULT 0.0000,
        unit_price DECIMAL(16,4) NULL,
        created_at DATETIME NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY(id),
        KEY idx_head (org_id, challan_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
}

/** Auto-create a challan that mirrors the invoice’s remaining qty; returns challan_id (or 0 if nothing to ship) */
private function autoCreateChallan(PDO $pdo, int $orgId, int $saleId): int
{
    $this->ensureChallanSchema($pdo);

    // Reuse existing active challan if present
    $chk = $pdo->prepare("SELECT id FROM dms_challans
                          WHERE org_id=? AND sale_id=? AND status IN('ready','dispatched','delivered')
                          ORDER BY id DESC LIMIT 1");
    $chk->execute([$orgId,$saleId]);
    if ($cid = (int)$chk->fetchColumn()) return $cid;

    // Load sale header (for customer snapshot + sale_no in notes)
    $h = $pdo->prepare("SELECT id, org_id, customer_id, customer_name, COALESCE(sale_no, invoice_no, code) AS sale_no
                        FROM dms_sales WHERE org_id=? AND id=? LIMIT 1");
    $h->execute([$orgId,$saleId]);
    $sale = $h->fetch(PDO::FETCH_ASSOC);
    if (!$sale) return 0;

    // Load invoice items
    $i = $pdo->prepare("SELECT id, product_id, COALESCE(product_name,name) AS product_name,
                               COALESCE(qty,0) AS qty, COALESCE(unit_price,price,0) AS unit_price
                        FROM dms_sale_items WHERE org_id=? AND sale_id=? ORDER BY id");
    $i->execute([$orgId,$saleId]);
    $items = $i->fetchAll(PDO::FETCH_ASSOC) ?: [];

    if (!$items) return 0;

    // Already dispatched qty per sale_item (across ready/dispatch/delivered)
    $dispatched = [];
    $qd = $pdo->prepare("
        SELECT i.sale_item_id, SUM(i.qty) shipped
          FROM dms_challan_items i
          JOIN dms_challans h ON h.id=i.challan_id AND h.org_id=i.org_id
         WHERE i.org_id=? AND h.sale_id=? AND h.status IN('ready','dispatched','delivered')
         GROUP BY i.sale_item_id
    ");
    $qd->execute([$orgId,$saleId]);
    foreach($qd->fetchAll(PDO::FETCH_ASSOC) as $r){
        $dispatched[(int)$r['sale_item_id']] = (float)$r['shipped'];
    }

    // Build remaining payload
    $lines = [];
    foreach ($items as $r) {
        $sid = (int)$r['id'];
        $qty = (float)$r['qty'];
        $sh  = (float)($dispatched[$sid] ?? 0);
        $rem = max(0.0, $qty - $sh);
        if ($rem > 0) {
            $lines[] = [
                'sale_item_id' => $sid,
                'product_id'   => (int)($r['product_id'] ?? 0) ?: null,
                'product_name' => (string)$r['product_name'],
                'qty'          => $rem,
                'unit_price'   => (float)($r['unit_price'] ?? 0),
            ];
        }
    }
    if (!$lines) return 0; // already fully dispatched

    // Next challan no (uses dms_counters if present; falls back to CH-YYYY-xxxxx)
    $y = (int)date('Y'); $no = null;
    $hasCounters = $this->hasTable($pdo,'dms_counters')
                  && $this->hasColumn($pdo,'dms_counters','name')
                  && $this->hasColumn($pdo,'dms_counters','y')
                  && $this->hasColumn($pdo,'dms_counters','seq');
    if ($hasCounters) {
        $st = $pdo->prepare("INSERT INTO dms_counters (org_id,name,y,seq)
                             VALUES (?,?,?,0)
                             ON DUPLICATE KEY UPDATE seq=LAST_INSERT_ID(seq+1)");
        $st->execute([$orgId,'challan',$y]);
        $seq = (int)$pdo->lastInsertId();
        if ($seq > 0) $no = sprintf('CH-%d-%05d', $y, $seq);
    }
    if (!$no) $no = sprintf('CH-%d-%s', $y, strtoupper(bin2hex(random_bytes(3))));

    // Create challan + items
    $pdo->beginTransaction();
    try {
        $insH = $pdo->prepare("INSERT INTO dms_challans
            (org_id, sale_id, challan_no, challan_date, customer_id, customer_name, status, notes, created_at)
            VALUES (?,?,?,?,?,?, 'ready', ?, NOW())");
        $insH->execute([
            $orgId,
            $saleId,
            $no,
            date('Y-m-d'),
            $sale['customer_id'] ?? null,
            $sale['customer_name'] ?? '',
            'Auto-generated from invoice '.($sale['sale_no'] ?? ('#'.$saleId)),
        ]);
        $cid = (int)$pdo->lastInsertId();

        $insI = $pdo->prepare("INSERT INTO dms_challan_items
            (org_id, challan_id, sale_item_id, product_id, product_name, qty, unit_price, created_at)
            VALUES (?,?,?,?,?,?,?, NOW())");
        foreach ($lines as $ln) {
            $insI->execute([
                $orgId, $cid,
                $ln['sale_item_id'],
                $ln['product_id'],
                $ln['product_name'],
                $ln['qty'],
                $ln['unit_price'],
            ]);
        }

        $pdo->commit();
        return $cid;
    } catch (\Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        return 0;
    }
}
   
    /* =========================
     * Screens
     * ========================= */
    public function index(array $ctx): void
{
    $this->ensureBase($ctx);
    try {
        $pdo   = $this->pdo();
        $orgId = $this->resolveOrgId($ctx, $pdo);

        // fetch sales headers (tolerant: select * so we don't reference missing cols)
        $st = $pdo->prepare("SELECT * FROM dms_sales WHERE org_id=? ORDER BY id DESC LIMIT 200");
        $st->execute([$orgId]);
        $hdrs = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $rows = [];

        // helpers
        $safe = static fn(array $a, array $keys, $def='') => (function() use($a,$keys,$def){
            foreach ($keys as $k) {
                if (array_key_exists($k,$a) && $a[$k] !== null && $a[$k] !== '') return $a[$k];
            }
            return $def;
        })();

        $fetchAll = function(string $sql, array $args) use ($pdo) {
            try { $q = $pdo->prepare($sql); $q->execute($args); return $q->fetchAll(PDO::FETCH_ASSOC) ?: []; }
            catch (\Throwable) { return []; }
        };

        $fetchOne = function(string $sql, array $args) use ($pdo) {
            try { $q = $pdo->prepare($sql); $q->execute($args); return $q->fetchColumn(); }
            catch (\Throwable) { return false; }
        };

        foreach ($hdrs as $h) {
            $sid  = (int)($h['id'] ?? 0);
            $no   = (string)$safe($h, ['sale_no','invoice_no','code','no'], $sid ? ('INV-'.$sid) : '');
            $date = (string)$safe($h, ['sale_date','invoice_date','date'], '');
            $cust = trim((string)$safe($h, ['customer_name','customer','cust_name'], '—')) ?: '—';

            // totals from header fallbacks
            $total = (float)$safe($h, ['grand_total','total','amount'], 0);
            $disc  = (float)$safe($h, ['discount_value','discount_total','discount'], 0);

            // roll up items: tolerant to different item-table names and column names
            $qtySum = 0.0; $itemsTotal = 0.0;
            $itemRows = $fetchAll("SELECT * FROM dms_sale_items WHERE org_id=? AND sale_id=?", [$orgId,$sid]);
            if (empty($itemRows)) {
                // fallback names some installs use
                $itemRows = $fetchAll("SELECT * FROM dms_sales_items WHERE org_id=? AND sale_id=?", [$orgId,$sid]);
            }
            foreach ($itemRows as $ln) {
                $q = (float)$safe($ln, ['qty','quantity','qty_ordered','q'], 0);
                $p = (float)$safe($ln, ['unit_price','price','rate','selling_price'], 0);
                $line = (float)$safe($ln, ['line_total','amount'], ($q * $p));
                $qtySum += $q;
                $itemsTotal += $line;
            }
            if ($total <= 0.00001 && $itemsTotal > 0) $total = $itemsTotal;

            // compute paid sum from payments table (tolerant to invoice_id or sale_id)
            $paid = 0.0;
            $v = $fetchOne("SELECT COALESCE(SUM(amount),0) FROM dms_payments WHERE org_id=? AND (sale_id=? OR invoice_id=?)", [$orgId,$sid,$sid]);
            if ($v !== false) $paid = (float)$v;

            // compute returns (if you have returns table, adapt)
            $returns = 0.0;
            $v = $fetchOne("SELECT COALESCE(SUM(amount),0) FROM dms_sales_returns WHERE org_id=? AND sale_id=?", [$orgId,$sid]);
            if ($v !== false) $returns = (float)$v;

            // compute challan count (tolerant)
            $challanCount = 0;
            $v = $fetchOne("SELECT COUNT(*) FROM dms_challans WHERE org_id=? AND (invoice_id=? OR sale_id=?)", [$orgId,$sid,$sid]);
            if ($v !== false) $challanCount = (int)$v;

            // recalc due & status
            $due = max(0.0, $total - $paid - $returns);
            $status = strtolower((string)$safe($h, ['invoice_status','status','state'], 'issued'));
            if ($status === 'posted') $status = 'issued';
            // override: if paid covers total, mark paid (UI-level decision)
            if ($paid >= $total && $total > 0.00001) $status = 'paid';

            $rows[] = [
                'id'            => $sid,
                'sale_no'       => $no,
                'sale_date'     => $date,
                'customer_name' => $cust,
                'qty'           => $qtySum,
                'discount'      => $disc,
                'total'         => $total,
                'paid'          => $paid,
                'due'           => $due,
                'returns'       => $returns,
                'status'        => $status,
                'challan_count' => $challanCount,
            ];
        }

        $this->view('sales/index', [
            'title'     => 'Sales (Invoices)',
            'rows'      => $rows,
            'active'    => 'sales',
            'subactive' => 'sales.index',
        ], $ctx);

    } catch (\Throwable $e) {
        $this->abort500('Sales index error: '.$e->getMessage());
    }
}

    public function create(array $ctx): void
    {
        $this->ensureBase($ctx);
        try {
            $pdo   = $this->pdo();
            $orgId = $this->resolveOrgId($ctx, $pdo);

            $y = (int)date('Y');
            $preview = sprintf('INV-%d-00001', $y);
            if ($this->hasTable($pdo,'dms_counters')) {
                $st = $pdo->prepare("SELECT seq FROM dms_counters WHERE org_id=? AND name='invoice' AND y=?");
                $st->execute([$orgId,$y]);
                $seq = (int)($st->fetchColumn() ?: 0) + 1;
                $preview = sprintf('INV-%d-%05d', $y, $seq);
            }

            $this->view('sales/create', [
                'title'   => 'Create Invoice',
                'today'   => date('Y-m-d'),
                'st'      => 'draft',
                'dt'      => 'amount',
                'next_no' => $preview,
            ], $ctx);
        } catch (\Throwable $e) {
            $this->abort500('Sales create error: '.$e->getMessage());
        }
    }

    public function show(array $ctx, int $id): void
    {
        $this->ensureBase($ctx);
        try {
            $pdo   = $this->pdo();
            $orgId = $this->resolveOrgId($ctx, $pdo);

            $h = $pdo->prepare("SELECT * FROM dms_sales WHERE org_id=? AND id=? LIMIT 1");
            $h->execute([$orgId, $id]);
            $sale = $h->fetch(PDO::FETCH_ASSOC);
            if (!$sale) $this->abort404('Invoice not found.');

            $items = [];
            if ($this->hasTable($pdo,'dms_sale_items')) {
                $q = $pdo->prepare("SELECT * FROM dms_sale_items WHERE org_id=? AND sale_id=? ORDER BY id");
                $q->execute([$orgId, $id]);
                $items = $q->fetchAll(PDO::FETCH_ASSOC) ?: [];
            }

            $this->view('sales/show', [
                'title'     => 'Invoice '.$sale['sale_no'],
                'sale'      => $sale,
                'items'     => $items,
                'active'    => 'sales',
                'subactive' => 'sales.show',
            ], $ctx);
        } catch (\Throwable $e) {
            $this->abort500('Sales show error: '.$e->getMessage());
        }
    }
  
  
  	public function edit(array $ctx, int $id): void
{
    $this->ensureBase($ctx);

    $pdo   = $this->pdo();
    $orgId = $this->resolveOrgId($ctx, $pdo);

    // Reuse the print/edit loader you added earlier
    [$sale, $items] = $this->loadSaleWithItems($pdo, $orgId, $id);

    // Map header → variables the create view expects
    $today   = substr((string)($sale['sale_date'] ?? date('Y-m-d')), 0, 10);
    $st      = (string)($sale['status'] ?? $sale['invoice_status'] ?? 'draft');
    $dt      = (string)($sale['discount_type'] ?? 'amount');
    $next_no = (string)($sale['sale_no'] ?? ($sale['invoice_no'] ?? ''));

    // Build a compact payload to hydrate the UI (Create page JS will use this)
    $existing = [
        'id'               => (int)$sale['id'],
        'sale_no'          => $next_no,
        'sale_date'        => $today,
        'status'           => $st,
        'discount_type'    => $dt,
        'discount_value'   => (float)($sale['discount_value'] ?? 0),
        'order_id'         => (int)($sale['order_id'] ?? 0) ?: null,
        'delivery_user_id' => (int)($sale['delivery_user_id'] ?? 0) ?: null,
        'customer_id'      => (int)($sale['customer_id'] ?? 0) ?: null,
        'customer_name'    => (string)($sale['customer_name'] ?? ''),
        'items'            => array_map(function(array $r){
            return [
                'product_id' => (int)($r['product_id'] ?? 0) ?: null,
                'name'       => (string)($r['product_name'] ?? $r['name'] ?? ''),
                'qty'        => (float)($r['qty'] ?? 0),
                'price'      => (float)($r['unit_price'] ?? $r['price'] ?? 0),
            ];
        }, $items),
    ];

    // Render the SAME view as Create
    $this->view('sales/create', [
        'title'       => 'Edit Invoice',
        // the create view already reads these; we feed them from existing sale
        'today'       => $today,
        'st'          => $st,
        'dt'          => $dt,
        'next_no'     => $next_no,
        // edit hydrator
        'edit_id'     => $id,
        'existing'    => $existing,
    ], $ctx);
}

    /* =========================
     * Store (schema-aware)
     * ========================= */
    public function store(array $ctx): void
    {
        $this->ensureBase($ctx);
        try {
            $pdo   = $this->pdo();
            $orgId = $this->resolveOrgId($ctx, $pdo);

            $action       = (string)($_POST['action'] ?? 'save');
            $requestedNo  = trim((string)($_POST['sale_no'] ?? ''));
            $saleDate     = trim((string)($_POST['sale_date'] ?? date('Y-m-d')));
            $statusIn     = (string)($_POST['status'] ?? 'confirmed');
            $status       = in_array($statusIn, ['draft','confirmed','cancelled'], true) ? $statusIn : 'confirmed';
            $invoiceStat  = 'issued';
            $dt           = strtolower((string)($_POST['discount_type'] ?? 'amount'));
            $dv           = (float)($_POST['discount_value'] ?? 0);
            $orderId      = isset($_POST['order_id']) ? (int)$_POST['order_id'] : null;
            $dealerId     = isset($_POST['delivery_user_id']) ? (int)$_POST['delivery_user_id'] : null;
            $cid          = isset($_POST['customer_id']) ? (int)$_POST['customer_id'] : null;
            $cname        = trim((string)($_POST['customer_name'] ?? ''));

            // items
            $items = [];
            if (!empty($_POST['items']) && is_array($_POST['items'])) {
                foreach ($_POST['items'] as $r) {
                    $name = trim((string)($r['product_name'] ?? $r['name'] ?? ''));
                    $qty  = (float)($r['qty'] ?? 0);
                    $pr   = (float)($r['unit_price'] ?? $r['price'] ?? 0);
                    $pid  = isset($r['product_id']) ? (int)$r['product_id'] : null;
                    if ($name !== '' && $qty > 0) {
                        $items[] = [
                            'product_id' => $pid,
                            'name'       => $name,
                            'qty'        => $qty,
                            'price'      => $pr,
                            'line'       => $qty * $pr,
                        ];
                    }
                }
            }

            // totals
            $subtotal = array_reduce($items, fn($s,$ln)=>$s + (float)$ln['line'], 0.0);
            $discAmt  = ($dt === 'percent') ? min($subtotal, $subtotal * ($dv/100)) : min($subtotal, (float)$dv);
            $grand    = max(0, $subtotal - $discAmt);
            $saleNo   = $this->ensureUniqueSaleNo($pdo, $orgId, $requestedNo);

            // header insert (tolerant)
            $cols = ['org_id','sale_no','sale_date','grand_total','created_at'];
            $vals = [$orgId, $saleNo, $saleDate, $grand];
            $qs   = ['?','?','?','?','NOW()'];

            $optional = [
                'order_id'       => $orderId,
                'dealer_id'      => $dealerId,
                'customer_id'    => $cid,
                'customer_name'  => $cname !== '' ? $cname : null,
                'status'         => $status,
                'invoice_status' => $invoiceStat,
                'discount_type'  => $dt,
                'discount_value' => $dv,
                'notes'          => (string)($_POST['notes'] ?? null),
            ];
            foreach ($optional as $col=>$val) {
                if ($this->hasColumn($pdo,'dms_sales',$col)) {
                    $cols[] = $col; $vals[] = $val; $qs[] = '?';
                }
            }
            if ($this->hasColumn($pdo,'dms_sales','updated_at')) { $cols[]='updated_at'; $qs[]='NOW()'; }

            $sql = "INSERT INTO dms_sales (".implode(',',$cols).") VALUES (".implode(',',$qs).")";

            $pdo->beginTransaction();
            try {
                // insert header with retry
                $attempts=0; $saleId=0;
                do {
                    try {
                        $h = $pdo->prepare($sql);
                        $h->execute($vals);
                        $saleId = (int)$pdo->lastInsertId();
                        break;
                    } catch (\PDOException $e) {
                        if ((int)($e->errorInfo[1] ?? 0) === 1062 && $attempts < 3) {
                            $saleNo = $this->nextInvoiceNo($pdo, $orgId);
                            $idx = array_search('sale_no',$cols,true);
                            if ($idx !== false) $vals[$idx] = $saleNo;
                            $attempts++;
                            continue;
                        }
                        throw $e;
                    }
                } while (true);

                // items
                if ($items && $this->hasTable($pdo,'dms_sale_items')) {
                    $itemCols = ['org_id','sale_id','qty'];
                    if ($this->hasColumn($pdo,'dms_sale_items','product_id'))   $itemCols[]='product_id';
                    if ($this->hasColumn($pdo,'dms_sale_items','product_name')) $itemCols[]='product_name';
                    elseif ($this->hasColumn($pdo,'dms_sale_items','name'))     $itemCols[]='name';
                    if ($this->hasColumn($pdo,'dms_sale_items','unit_price'))   $itemCols[]='unit_price';
                    elseif ($this->hasColumn($pdo,'dms_sale_items','price'))    $itemCols[]='price';
                    if ($this->hasColumn($pdo,'dms_sale_items','line_total'))   $itemCols[]='line_total';
                    elseif ($this->hasColumn($pdo,'dms_sale_items','amount'))   $itemCols[]='amount';
                    if ($this->hasColumn($pdo,'dms_sale_items','created_at'))   $itemCols[]='created_at';

                    $qsItem = [];
                    foreach ($itemCols as $c) $qsItem[] = ($c === 'created_at') ? 'NOW()' : '?';
                    $sqlI = "INSERT INTO dms_sale_items (".implode(',',$itemCols).") VALUES (".implode(',',$qsItem).")";
                    $i = $pdo->prepare($sqlI);

                    foreach ($items as $ln) {
                        $valsI = [];
                        foreach ($itemCols as $c) {
                            switch ($c) {
                                case 'org_id':      $valsI[] = $orgId; break;
                                case 'sale_id':     $valsI[] = $saleId; break;
                                case 'product_id':  $valsI[] = $ln['product_id'] ?? null; break;
                                case 'product_name':
                                case 'name':        $valsI[] = $ln['name']; break;
                                case 'qty':         $valsI[] = $ln['qty']; break;
                                case 'unit_price':
                                case 'price':       $valsI[] = $ln['price']; break;
                                case 'line_total':
                                case 'amount':      $valsI[] = $ln['line']; break;
                                case 'created_at':  /* NOW() */ break;
                            }
                        }
                        $i->execute($valsI);
                    }
                }

                // optional AR update
                if ($cid && $this->hasTable($pdo,'dms_customers') && $this->hasColumn($pdo,'dms_customers','balance')) {
                    $set = "balance = IFNULL(balance,0) + ?";
                    if ($this->hasColumn($pdo,'dms_customers','updated_at')) $set .= ", updated_at = NOW()";
                    $u = $pdo->prepare("UPDATE dms_customers SET $set WHERE org_id=? AND id=?");
                    $u->execute([$grand, $orgId, $cid]);
                }

                // optional stock decrement
                if ($this->hasTable($pdo,'dms_products') && $this->hasColumn($pdo,'dms_products','stock_qty')) {
                    $set = "stock_qty = IFNULL(stock_qty,0) - ?";
                    if ($this->hasColumn($pdo,'dms_products','updated_at')) $set .= ", updated_at = NOW()";
                    $ustock = $pdo->prepare("UPDATE dms_products SET $set WHERE org_id=? AND id=?");
                    foreach ($items as $ln) {
                        $pid = (int)($ln['product_id'] ?? 0);
                        if ($pid > 0 && $ln['qty'] > 0) $ustock->execute([$ln['qty'], $orgId, $pid]);
                    }
                }

                $pdo->commit();
            } catch (\Throwable $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                throw $e;
            }

            $base = $this->moduleBase($ctx);
            if ($action === 'save_print') {
                $this->redirect("{$base}/sales/{$saleId}/print?autoprint=1"); return;
            }
            $this->redirect("{$base}/sales/{$saleId}/print");

        } catch (\Throwable $e) {
            $this->abort500('Sales store error: '.$e->getMessage());
        }
    }

    /* =========================
     * Fulfil / Deliver / Pay / Return / Print
     * ========================= */
    public function fulfil(array $ctx, int $id): void
    {
        $this->ensureBase($ctx);
        try {
            $pdo = $this->pdo(); $orgId = $this->resolveOrgId($ctx, $pdo);
            $set = "status='confirmed'";
            if ($this->hasColumn($pdo,'dms_sales','updated_at')) $set .= ", updated_at=NOW()";
            $pdo->prepare("UPDATE dms_sales SET $set WHERE org_id=? AND id=?")->execute([$orgId,$id]);
            $this->redirect($this->moduleBase($ctx)."/sales/{$id}/print?autoprint=1");
        } catch (\Throwable $e) { $this->abort500('Fulfil failed: '.$e->getMessage()); }
    }

    public function deliver(array $ctx, int $id): void
    {
        $this->ensureBase($ctx);
        try {
            $pdo = $this->pdo(); $orgId = $this->resolveOrgId($ctx, $pdo);
            if (!$this->hasColumn($pdo,'dms_sales','invoice_status')) { $this->redirect($this->moduleBase($ctx).'/sales'); return; }
            $set = "invoice_status='delivered'";
            if ($this->hasColumn($pdo,'dms_sales','updated_at')) $set .= ", updated_at=NOW()";
            $pdo->prepare("UPDATE dms_sales SET $set WHERE org_id=? AND id=?")->execute([$orgId,$id]);
            $this->redirect($this->moduleBase($ctx).'/sales');
        } catch (\Throwable $e) { $this->abort500('Deliver failed: '.$e->getMessage()); }
    }

    public function pay(array $ctx, int $id): void
    {
        $this->ensureBase($ctx);
        try {
            $pdo = $this->pdo(); $orgId = $this->resolveOrgId($ctx, $pdo);
            $set = [];
            if ($this->hasColumn($pdo,'dms_sales','invoice_status')) $set[] = "invoice_status='paid'";
            if ($this->hasColumn($pdo,'dms_sales','paid_at'))        $set[] = "paid_at=NOW()";
            if ($this->hasColumn($pdo,'dms_sales','updated_at'))     $set[] = "updated_at=NOW()";
            if ($set) $pdo->prepare("UPDATE dms_sales SET ".implode(', ',$set)." WHERE org_id=? AND id=?")->execute([$orgId,$id]);
            $this->redirect($this->moduleBase($ctx).'/sales');
        } catch (\Throwable $e) { $this->abort500('Pay failed: '.$e->getMessage()); }
    }

    public function returnFull(array $ctx, int $id): void
    {
        $this->ensureBase($ctx);
        try {
            $pdo = $this->pdo(); $orgId = $this->resolveOrgId($ctx, $pdo);
            if (!$this->hasTable($pdo,'dms_sales_returns')) { $this->abort500('Missing table dms_sales_returns.'); return; }
            $set = [];
            if ($this->hasColumn($pdo,'dms_sales','invoice_status')) $set[] = "invoice_status='returned'";
            if ($this->hasColumn($pdo,'dms_sales','updated_at'))     $set[] = "updated_at=NOW()";
            if ($set) $pdo->prepare("UPDATE dms_sales SET ".implode(', ',$set)." WHERE org_id=? AND id=?")->execute([$orgId,$id]);
            $this->redirect($this->moduleBase($ctx).'/sales');
        } catch (\Throwable $e) { $this->abort500('Return failed: '.$e->getMessage()); }
    }

public function print(array $ctx, int $id): void
{
    $this->ensureBase($ctx);

    try {
        $pdo = $this->pdo();
        if (!$pdo instanceof \PDO) { throw new \RuntimeException('DB not available'); }

        $orgId = $this->resolveOrgId($ctx, $pdo);

        // ---- Load sale header
        $h = $pdo->prepare("SELECT * FROM dms_sales WHERE org_id=? AND id=? LIMIT 1");
        $h->execute([$orgId, $id]);
        $sale = $h->fetch(\PDO::FETCH_ASSOC) ?: null;
        if (!$sale) $this->abort404('Invoice not found.');

        // ---- Load items (support both legacy table names)
        $items = [];
        $itemsTbl = $this->findSaleItemsTable($pdo);
        if ($itemsTbl !== null) {
            $q = $pdo->prepare("SELECT * FROM {$itemsTbl} WHERE org_id=? AND sale_id=? ORDER BY id");
            $q->execute([$orgId, $id]);
            $items = $q->fetchAll(\PDO::FETCH_ASSOC) ?: [];
        }

        // ---- Org block + logo fallback
        $org = is_array($ctx['org'] ?? null) ? $ctx['org'] : [];
        $org += ['name'=>'', 'address'=>'', 'phone'=>'', 'email'=>'', 'logo_url'=>''];
        if (($org['logo_url'] ?? '') === '') {
            // stable fallback under /public/assets/…
            $org['logo_url'] = '/public/assets/brand/logo.png';
        }

        // ---- Render the standalone printable view (NO SHELL)
        $vars = [
            'title'       => 'Invoice',
            'sale'        => $sale,
            'items'       => $items,
            'org'         => $org,
            'module_base' => (string)($ctx['module_base'] ?? '/apps/dms'),
            'autoprint'   => isset($_GET['autoprint']),
        ];

        $this->renderPrintView('sales/print', $vars, $ctx);
    } catch (\Throwable $e) {
        // Keep the error handler quiet but informative
        $this->abort500('Print failed: '.$e->getMessage());
    }
}

  
      /**
     * GET /apps/dms/sales/{id}/challan
     * Auto-create challan from invoice and redirect to challan show.
     */
    public function autoChallan(array $ctx, int $id): void
    {
        $this->ensureBase($ctx);

        try {
            $pdo   = $this->pdo();
            $orgId = $this->resolveOrgId($ctx, $pdo);

            if ($id <= 0) {
                $this->abort404('Invalid invoice id for challan.');
            }

            // Use the existing helper you already have above in this class
            $cid = $this->autoCreateChallan($pdo, $orgId, $id);

            $base = $this->moduleBase($ctx);

            if ($cid <= 0) {
                // Nothing to ship (already fully dispatched or no lines)
                $this->redirect("{$base}/sales/{$id}?challan=none");
                return;
            }

            // Go to challan show page
            $this->redirect("{$base}/challan/{$cid}");
        } catch (\Throwable $e) {
            $this->abort500('Auto challan failed: ' . $e->getMessage());
        }
    }
  
  
  

    /* =========================
     * Lightweight JSON for typeaheads & hydrate
     * ========================= */

    /** /sales.customers.lookup.json?q=&limit= */
    public function apiLookupCustomers(array $ctx): void
    {
        $this->ensureBase($ctx);
        try {
            header('Content-Type: application/json; charset=utf-8');
            $pdo=$this->pdo(); $org=$this->resolveOrgId($ctx,$pdo);
            $q = '%'.trim((string)($_GET['q'] ?? '')).'%';
            $limit = max(1, min(50, (int)($_GET['limit'] ?? 20)));
            if (!$this->hasTable($pdo,'dms_customers')) { echo json_encode(['items'=>[]]); return; }
            $st = $pdo->prepare("
                SELECT id, name, COALESCE(code,'') code, COALESCE(phone,'') phone, COALESCE(email,'') email
                FROM dms_customers
                WHERE org_id=? AND (name LIKE ? OR COALESCE(code,'') LIKE ? OR COALESCE(phone,'') LIKE ? OR COALESCE(email,'') LIKE ?)
                ORDER BY name ASC
                LIMIT ?
            ");
            $st->execute([$org,$q,$q,$q,$q,$limit]);
            $items=[]; foreach ($st->fetchAll(PDO::FETCH_ASSOC) ?: [] as $r) {
                $meta = trim(($r['phone']?:'').($r['code'] ? ' · '.$r['code'] : ''));
                $items[] = ['id'=>(int)$r['id'],'label'=>$r['name'],'name'=>$r['name'],'phone'=>$r['phone']?:null,'email'=>$r['email']?:null,'meta'=>$meta];
            }
            echo json_encode(['items'=>$items], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
        } catch (\Throwable) { echo json_encode(['items'=>[]]); }
    }

    /** /sales.products.lookup.json?q=&limit= */
    public function apiLookupProducts(array $ctx): void
    {
        $this->ensureBase($ctx);
        try {
            header('Content-Type: application/json; charset=utf-8');
            $pdo=$this->pdo(); $org=$this->resolveOrgId($ctx,$pdo);
            if (!$this->hasTable($pdo,'dms_products')) { echo json_encode(['items'=>[]]); return; }

            $q = '%'.trim((string)($_GET['q'] ?? '')).'%';
            $limit = max(1, min(50, (int)($_GET['limit'] ?? 30)));

            // tolerant expressions
            $sku   = "COALESCE(sku, product_sku, code, product_code, pid)";
            $bar   = "COALESCE(barcode, ean, upc)";
            $unit  = "COALESCE(unit, uom, uom_name)";
            $price = "COALESCE(unit_price, price, selling_price, sale_price, mrp, default_price, price1, 0)";

            $st = $pdo->prepare("
                SELECT id, name, {$unit} AS unit, {$price} AS price, {$sku} AS sku, {$bar} AS barcode
                FROM dms_products
                WHERE org_id=?
                  AND (name LIKE ? OR {$sku} LIKE ? OR {$bar} LIKE ?)
                ORDER BY id DESC
                LIMIT ?
            ");
            $st->execute([$org,$q,$q,$q,$limit]);

            $items=[]; foreach ($st->fetchAll(PDO::FETCH_ASSOC) ?: [] as $r) {
                $meta = trim(($r['sku']?:'').($r['unit'] ? ' · '.$r['unit'] : ''));
                $items[] = [
                    'id'=>(int)$r['id'],
                    'label'=>$r['name'],
                    'name'=>$r['name'],
                    'unit'=>$r['unit']??null,
                    'price'=>(float)($r['price']??0),
                    'sku'=>$r['sku']??null,
                    'barcode'=>$r['barcode']??null,
                    'meta'=>$meta
                ];
            }
            echo json_encode(['items'=>$items], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
        } catch (\Throwable) { echo json_encode(['items'=>[]]); }
    }

    /** /sales.users.lookup.json?q=&limit=  (delivery person) */
    public function apiLookupUsers(array $ctx): void
    {
        $this->ensureBase($ctx);
        try {
            header('Content-Type: application/json; charset=utf-8');
            $pdo=$this->pdo(); $org=$this->resolveOrgId($ctx,$pdo);
            if (!$this->hasTable($pdo,'cp_users')) { echo json_encode(['items'=>[]]); return; }
            $q = '%'.trim((string)($_GET['q'] ?? '')).'%';
            $limit = max(1, min(50, (int)($_GET['limit'] ?? 20)));
            $st = $pdo->prepare("SELECT id, COALESCE(name,'') name, COALESCE(email,'') email, COALESCE(phone,'') phone
                                 FROM cp_users
                                 WHERE org_id=? AND (COALESCE(name,'') LIKE ? OR COALESCE(email,'') LIKE ? OR COALESCE(phone,'') LIKE ?)
                                 ORDER BY id DESC LIMIT ?");
            $st->execute([$org,$q,$q,$q,$limit]);
            $items=[]; foreach ($st->fetchAll(PDO::FETCH_ASSOC) ?: [] as $r) {
                $label = $r['name'] ?: ($r['email'] ?: ('User #'.$r['id']));
                $meta  = $r['phone'] ?: $r['email'];
                $items[]=['id'=>(int)$r['id'],'label'=>$label,'name'=>$label,'email'=>$r['email']?:null,'meta'=>$meta];
            }
            echo json_encode(['items'=>$items], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
        } catch (\Throwable) { echo json_encode(['items'=>[]]); }
    }

    /** GET /api/orders/{id}/detail → hydrate invoice (customer + items) */
    public function apiOrderDetail(array $ctx, int $id): void
    {
        $this->ensureBase($ctx);
        header('Content-Type: application/json; charset=utf-8');
        try {
            $pdo=$this->pdo(); $org=$this->resolveOrgId($ctx,$pdo);
            if (!$this->hasTable($pdo,'dms_orders')) { echo json_encode(['ok'=>false]); return; }

            $o = $pdo->prepare("SELECT id, order_no, customer_id, customer_name FROM dms_orders WHERE org_id=? AND id=?");
            $o->execute([$org,$id]);
            $ord = $o->fetch(PDO::FETCH_ASSOC);
            if (!$ord) { echo json_encode(['ok'=>false]); return; }

            $items=[];
            if ($this->hasTable($pdo,'dms_order_items')) {
                $q = $pdo->prepare("SELECT product_id, product_name, qty, unit_price FROM dms_order_items WHERE org_id=? AND order_id=? ORDER BY id");
                $q->execute([$org,$id]);
                foreach ($q->fetchAll(PDO::FETCH_ASSOC) ?: [] as $r) {
                    $items[]=[
                        'product_id'=>(int)$r['product_id'],
                        'name'=>$r['product_name'],
                        'qty'=>(float)$r['qty'],
                        'price'=>(float)$r['unit_price'],
                    ];
                }
            }

            echo json_encode(['ok'=>true,'order'=>$ord,'items'=>$items], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
        } catch (\Throwable $e) {
            echo json_encode(['ok'=>false,'error'=>$e->getMessage()]);
        }
    }

    /** NEW: /sales.order.fetch.json?id=123 OR ?no=ORD-2025-00001 (for the Create Invoice UI) */
    public function apiOrderFetch(array $ctx): void
    {
        $this->ensureBase($ctx);
        header('Content-Type: application/json; charset=utf-8');
        try {
            $pdo=$this->pdo(); $org=$this->resolveOrgId($ctx,$pdo);
            if (!$this->hasTable($pdo,'dms_orders')) { echo json_encode(['ok'=>false,'msg'=>'orders table missing']); return; }

            $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
            $no = trim((string)($_GET['no'] ?? ''));

            if ($id <= 0 && $no === '') { echo json_encode(['ok'=>false,'msg'=>'id or no required']); return; }

            if ($id > 0) {
                $st = $pdo->prepare("SELECT id, order_no, customer_id, customer_name, COALESCE(customer_phone,'') customer_phone
                                     FROM dms_orders WHERE org_id=? AND id=? LIMIT 1");
                $st->execute([$org,$id]);
            } else {
                // tolerate alt column names for order number
                $st = $pdo->prepare("SELECT id, COALESCE(order_no, ref_no, code) AS order_no,
                                            customer_id, customer_name, COALESCE(customer_phone,'') customer_phone
                                     FROM dms_orders
                                     WHERE org_id=? AND (order_no = ? OR ref_no = ? OR code = ?)
                                     LIMIT 1");
                $st->execute([$org,$no,$no,$no]);
            }

            $ord = $st->fetch(PDO::FETCH_ASSOC);
            if (!$ord) { echo json_encode(['ok'=>false,'msg'=>'Order not found']); return; }

            // items (tolerant)
            $items = [];
            if ($this->hasTable($pdo,'dms_order_items')) {
                $q = $pdo->prepare("
                    SELECT
                        COALESCE(product_id, item_id, product) AS product_id,
                        COALESCE(product_name, name, item_name) AS product_name,
                        COALESCE(qty, quantity) AS qty,
                        COALESCE(unit_price, price, rate) AS unit_price
                    FROM dms_order_items
                    WHERE org_id=? AND order_id=?
                    ORDER BY id
                ");
                $q->execute([$org,(int)$ord['id']]);
                foreach ($q->fetchAll(PDO::FETCH_ASSOC) ?: [] as $r) {
                    $items[]=[
                        'product_id'=>(int)($r['product_id'] ?? 0),
                        'name'=>(string)($r['product_name'] ?? ''),
                        'qty'=>(float)($r['qty'] ?? 0),
                        'price'=>(float)($r['unit_price'] ?? 0),
                    ];
                }
            }

            // customer block
            $customer = null;
            if (!empty($ord['customer_id']) && $this->hasTable($pdo,'dms_customers')) {
                $c = $pdo->prepare("SELECT id, name, COALESCE(phone,'') phone FROM dms_customers WHERE org_id=? AND id=?");
                $c->execute([$org,(int)$ord['customer_id']]);
                $customer = $c->fetch(PDO::FETCH_ASSOC) ?: null;
            }
            if (!$customer && !empty($ord['customer_name'])) {
                $customer = ['id'=>0,'name'=>(string)$ord['customer_name'],'phone'=>$ord['customer_phone'] ?? ''];
            }

            echo json_encode([
                'ok'=>true,
                'order'=>['id'=>(int)$ord['id'],'no'=>(string)($ord['order_no'] ?? ''),'customer_id'=>(int)($ord['customer_id'] ?? 0)],
                'customer'=>$customer,
                'items'=>$items
            ], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
        } catch (\Throwable $e) {
            echo json_encode(['ok'=>false,'msg'=>$e->getMessage()]);
        }
    }
}