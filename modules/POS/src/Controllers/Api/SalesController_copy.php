<?php
declare(strict_types=1);

namespace Modules\POS\Controllers;

use PDO;
use Throwable;

final class SalesController extends BaseController
{
    /* ----------------------- helpers ----------------------- */

    private function hasTable(PDO $pdo, string $table): bool
    {
        $st = $pdo->prepare(
            "SELECT 1 FROM information_schema.tables
             WHERE table_schema = DATABASE() AND table_name = ? LIMIT 1"
        );
        $st->execute([$table]);
        return (bool)$st->fetchColumn();
    }

    private function hasCol(PDO $pdo, string $table, string $col): bool
    {
        $st = $pdo->prepare(
            "SELECT 1 FROM information_schema.columns
             WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ? LIMIT 1"
        );
        $st->execute([$table, $col]);
        return (bool)$st->fetchColumn();
    }

    private function pickTable(PDO $pdo, array $candidates, string $err): string
    {
        foreach ($candidates as $t) if ($this->hasTable($pdo, $t)) return $t;
        throw new \RuntimeException($err);
    }

    private function salesTable(PDO $pdo): string  { return $this->pickTable($pdo, ['pos_sales','dms_sales','sales'], 'Sales table not found'); }
    private function itemsTable(PDO $pdo): string  { return $this->pickTable($pdo, ['pos_sale_items','dms_sale_items','dms_sales_items','sale_items','sales_items'], 'Sale items table not found'); }
    private function prodsTable(PDO $pdo): string  { return $this->pickTable($pdo, ['pos_products','dms_products','products'], 'Products table not found'); }
    private function custsTable(PDO $pdo): ?string
    {
        foreach (['pos_customers','dms_customers','customers'] as $t) if ($this->hasTable($pdo,$t)) return $t;
        return null;
    }

    /** Ensure ctx has slug/base and return the normalized ctx */
    private function ensureBase(array $ctx = []): array
    {
        $c = $this->ctx($ctx);
        // nothing to change if kernel already set them; just return normalized ctx
        return $c;
    }

    /** Simple counter-based invoice generator if counters table exists */
    private function nextInvoiceNo(PDO $pdo, int $orgId): string
    {
        $y = (int)date('Y');
        foreach (['pos_counters','dms_counters'] as $ctr) {
            if ($this->hasTable($pdo,$ctr) && $this->hasCol($pdo,$ctr,'name')
                && $this->hasCol($pdo,$ctr,'y') && $this->hasCol($pdo,$ctr,'seq')) {
                $st = $pdo->prepare(
                    "INSERT INTO {$ctr} (org_id,name,y,seq)
                     VALUES (?,?,?,0)
                     ON DUPLICATE KEY UPDATE seq = LAST_INSERT_ID(seq + 1)"
                );
                $st->execute([$orgId,'invoice',$y]);
                $seq = (int)$pdo->lastInsertId();
                if ($seq > 0) return sprintf('INV-%d-%05d', $y, $seq);
            }
        }
        return sprintf('INV-%d-%s', $y, strtoupper(bin2hex(random_bytes(3))));
    }
  
  	 /** Shared printer loader */
    private function renderInvoice(array $ctx, int $id, string $fmt): void
    {
        try {
            $c     = $this->ensureBase($ctx);
            $pdo   = $this->pdo();
            $orgId = $this->requireOrg();
            $sales = $this->salesTable($pdo);
            $items = $this->itemsTable($pdo);

            $hdr = $pdo->prepare("SELECT * FROM {$sales} WHERE org_id=? AND id=? LIMIT 1");
            $hdr->execute([$orgId,$id]);
            $sale = $hdr->fetch(PDO::FETCH_ASSOC);
            if (!$sale) { http_response_code(404); echo "Sale not found"; return; }

            $li = $pdo->prepare("SELECT * FROM {$items} WHERE org_id=? AND sale_id=? ORDER BY id ASC");
            $li->execute([$orgId,$id]);
            $lines = $li->fetchAll(PDO::FETCH_ASSOC) ?: [];

            $view = ($fmt === 'pos')
                ? 'print_pos.php'
                : 'print_a4.php';

            $this->view($c['module_dir'].'/Views/sales/'.$view, [
                'sale'=>$sale,
                'items'=>$lines,
                'base'=>$c['module_base'],
            ], null); // no shell → print-friendly
        } catch (Throwable $e) {
            echo "Print failed: ".$e->getMessage();
        }
    }
  	
  
  //Print section
  // inside SalesController
private function fetchSale(PDO $pdo, int $orgId, int $id): array
{
    $salesTbl = $this->salesTable($pdo);   // pos_sales
    $itemsTbl = $this->itemsTable($pdo);   // pos_sale_items

    // Header
    $hdr = $pdo->prepare("SELECT * FROM {$salesTbl} WHERE org_id = ? AND id = ? LIMIT 1");
    $hdr->execute([$orgId, $id]);
    $sale = $hdr->fetch(PDO::FETCH_ASSOC) ?: null;

    if (!$sale) {
        throw new \RuntimeException('Sale not found');
    }

    // Lines: join products + categories and expose human-friendly fields
    $it = $pdo->prepare("
        SELECT
          si.*,
          p.name AS product_name,
          p.sku,
          p.unit,
          p.category_id,
          c.name AS category_name,
          (si.unit_price_cents / 100.0) AS unit_price,
          (si.line_total_cents / 100.0) AS line_total
        FROM {$itemsTbl} si
        LEFT JOIN pos_products  p
          ON p.id = si.product_id AND p.org_id = si.org_id
        LEFT JOIN pos_categories c
          ON c.id = p.category_id AND c.org_id = p.org_id
        WHERE si.org_id = ? AND si.sale_id = ?
        ORDER BY si.sale_item_id ASC
    ");
    $it->execute([$orgId, $id]);
    $lines = $it->fetchAll(PDO::FETCH_ASSOC) ?: [];

    // Figure out which columns are used for invoice no & date
    $noCol = $this->hasCol($pdo, $salesTbl, 'invoice_no') ? 'invoice_no'
           : ($this->hasCol($pdo, $salesTbl, 'sale_no') ? 'sale_no' : 'code');

    $dateCol = $this->hasCol($pdo, $salesTbl, 'sale_date') ? 'sale_date'
             : ($this->hasCol($pdo, $salesTbl, 'created_at') ? 'created_at' : '');

    return [$sale, $lines, $noCol, $dateCol];
}

    /* ------------------------- pages ---------------------------- */

    /** GET /sales */
    public function index(array $ctx = []): void
{
    try {
        $c     = $this->ensureBase($ctx);
        $pdo   = $this->pdo();
        $orgId = $this->requireOrg();

        $sales = $this->salesTable($pdo);

        // Fixed column mapping for pos_sales
        $sql = "SELECT 
                    id,
                    invoice_no AS no,
                    customer_name AS customer,
                    total_amount AS total,
                    status,
                    sale_date AS dt
                FROM {$sales}
                WHERE org_id = ?
                ORDER BY id DESC
                LIMIT 200";

        $st = $pdo->prepare($sql);
        $st->execute([$orgId]);
        $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $this->view($c['module_dir'].'/Views/sales/index.php', [
            'title'=>'Sales',
            'rows'=>$rows,
            'q'=>'',
            'base'=>$c['module_base'],
        ], 'shell');

    } catch (Throwable $e) {
        $this->oops('Sales index failed', $e);
    }
}

    /** GET /sales/register */
    public function register(array $ctx = []): void
    {
        try {
            $c     = $this->ensureBase($ctx);
            $pdo   = $this->pdo();
            $orgId = $this->requireOrg();

            $invoice = $this->nextInvoiceNo($pdo, $orgId);

            // optional customers dropdown
            $customers = [];
            if ($tbl = $this->custsTable($pdo)) {
                $nameCol = $this->hasCol($pdo,$tbl,'name') ? 'name'
                         : ($this->hasCol($pdo,$tbl,'customer_name') ? 'customer_name' : 'id');
                $s = $pdo->prepare("SELECT id, {$nameCol} AS name
                                    FROM {$tbl} WHERE org_id=? ORDER BY {$nameCol} ASC LIMIT 200");
                $s->execute([$orgId]);
                $customers = $s->fetchAll(PDO::FETCH_ASSOC) ?: [];
            }

            $paymentMethods = [
                ['code'=>'Cash','label'=>'Cash'],
                ['code'=>'Card','label'=>'Card'],
                ['code'=>'Bkash','label'=>'bKash'],
            ];

            $this->view($c['module_dir'].'/Views/sales/register.php', [
                'title'           => 'Sales Register',
                'invoice_no'      => $invoice,
                'customers'       => $customers,
                'categories'      => [],
                'brands'          => [],
                'payment_methods' => $paymentMethods,
                'base'            => $c['module_base'],
            ], 'shell');
        } catch (Throwable $e) {
            $this->oops('Sales register failed', $e);
        }
    }

   /** GET /sales/{id} */
public function showOne(array $ctx = [], int $id = 0): void
{
    try {
        $c     = $this->ensureBase($ctx);
        $pdo   = $this->pdo();
        $orgId = $this->requireOrg();

        $salesTbl = $this->salesTable($pdo);   // pos_sales
        $itemsTbl = $this->itemsTable($pdo);   // pos_sale_items

        /* --------------------- FETCH SALE HEADER --------------------- */
        $sqlHdr = "SELECT * FROM {$salesTbl} WHERE org_id = ? AND id = ? LIMIT 1";
        $stHdr  = $pdo->prepare($sqlHdr);
        $stHdr->execute([$orgId, $id]);
        $sale = $stHdr->fetch(PDO::FETCH_ASSOC);

        if (!$sale) {
            http_response_code(404);
            echo "Sale not found";
            return;
        }

        /* --------------------- FETCH SALE ITEMS ---------------------- */
        $sqlItems = "
            SELECT
                si.*,
                p.name AS product_name,
                p.sku,
                p.unit AS unit_name,
                c.name AS category_name,

                /* Convert cents stored in DB → BDT for UI */
                (si.unit_price_cents / 100.0) AS unit_price,
                (si.line_total_cents / 100.0) AS line_total

            FROM {$itemsTbl} si

            LEFT JOIN pos_products p
              ON p.id = si.product_id
             AND p.org_id = si.org_id

            LEFT JOIN pos_categories c
              ON c.id = p.category_id
             AND c.org_id = p.org_id

            WHERE si.org_id = ?
              AND si.sale_id = ?
            ORDER BY si.sale_item_id ASC
        ";

        $stItems = $pdo->prepare($sqlItems);
        $stItems->execute([$orgId, $id]);
        $items = $stItems->fetchAll(PDO::FETCH_ASSOC) ?: [];

        /* --------------------- DETERMINE INVOICE NO ------------------ */
        $noCol = $this->hasCol($pdo, $salesTbl, 'invoice_no') ? 'invoice_no'
                : ($this->hasCol($pdo, $salesTbl, 'sale_no') ? 'sale_no'
                : ($this->hasCol($pdo, $salesTbl, 'code') ? 'code' : 'id'));

        /* --------------------- RENDER VIEW ---------------------------- */
        $this->view($c['module_dir'].'/Views/sales/show.php', [
            'title' => 'Invoice '.$sale[$noCol],
            'sale'  => $sale,
            'items' => $items,
            'base'  => $c['module_base'],
        ], 'shell');

    } catch (Throwable $e) {
        $this->oops('Sales show failed', $e);
    }
}

   /* ============================================================
       ⭐ FIXED — REAL SALE CREATION
       POST /sales  (JSON)
    ============================================================ */
   /** POST /sales (JSON) — store sale + items into pos_sales / pos_sale_items */
public function store(array $ctx = []): void
{
    header('Content-Type: application/json; charset=utf-8');

    try {
        $c     = $this->ensureBase($ctx);
        $pdo   = $this->pdo();
        $orgId = $this->requireOrg();

        $raw = file_get_contents('php://input') ?: '';
        $in  = json_decode($raw, true);
        if (!is_array($in)) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => 'Invalid JSON']);
            return;
        }

        $items = $in['items'] ?? [];
        if (empty($items) || !is_array($items)) {
            http_response_code(422);
            echo json_encode(['ok' => false, 'error' => 'No line items']);
            return;
        }

        // Basic fields from payload
        $invoiceNo = trim((string)($in['invoice_no'] ?? ''));
        if ($invoiceNo === '') {
            // fallback – though normally register already sends invoice_no
            $invoiceNo = $this->nextInvoiceNo($pdo, $orgId);
        }

        $saleDate = trim((string)($in['sale_date'] ?? date('Y-m-d')));
        $custId   = (int)($in['customer_id'] ?? 0);
        $custName = trim((string)($in['customer_name'] ?? ''));
        if ($custName === '' && $custId <= 0) {
            $custName = 'Walk-in customer';
        }

        $discAmountIn = (float)($in['discount_amount'] ?? 0);
        $discPercent  = (float)($in['discount_percent'] ?? 0);
        $taxPercent   = (float)($in['tax_percent'] ?? 0);
        $notes        = trim((string)($in['notes'] ?? ''));

        // 1) Subtotal from items (server-side)
        $subtotal = 0.0;
        foreach ($items as $it) {
            $qty   = (float)($it['qty']   ?? 0);
            $price = (float)($it['price'] ?? 0);
            if ($qty <= 0 || $price < 0) {
                continue;
            }
            $subtotal += $qty * $price;
        }

        if ($subtotal <= 0) {
            http_response_code(422);
            echo json_encode(['ok' => false, 'error' => 'Subtotal is zero']);
            return;
        }

        // 2) Discount
        $discountAmount = $discPercent > 0
            ? ($subtotal * $discPercent / 100.0)
            : $discAmountIn;
        if ($discountAmount < 0) $discountAmount = 0.0;

        // 3) Tax and grand total
        $taxBase   = max(0.0, $subtotal - $discountAmount);
        $taxAmount = $taxPercent > 0 ? ($taxBase * $taxPercent / 100.0) : 0.0;
        $total     = $taxBase + $taxAmount;

        $salesTbl = $this->salesTable($pdo);      // pos_sales
        $itemsTbl = $this->itemsTable($pdo);      // pos_sale_items

        $this->begin();

        // Insert header row into pos_sales
        $st = $pdo->prepare("
            INSERT INTO {$salesTbl}
              (org_id, invoice_no, customer_name, customer_id, sale_date, status,
               subtotal_amount, discount_amount, tax_amount, total_amount, notes,
               created_at, updated_at)
            VALUES
              (:org_id, :inv, :cust_name, :cust_id, :sale_date, :status,
               :subtotal, :discount, :tax, :total, :notes,
               NOW(), NOW())
        ");
        $st->execute([
            ':org_id'    => $orgId,
            ':inv'       => $invoiceNo,
            ':cust_name' => $custName,
            ':cust_id'   => $custId ?: null,
            ':sale_date' => $saleDate,
            ':status'    => 'posted',
            ':subtotal'  => $subtotal,
            ':discount'  => $discountAmount,
            ':tax'       => $taxAmount,
            ':total'     => $total,
            ':notes'     => $notes !== '' ? $notes : null,
        ]);
        $saleId = (int)$pdo->lastInsertId();

        // Insert line items into pos_sale_items (amounts stored in cents)
        $itemSql = "
          INSERT INTO {$itemsTbl}
            (org_id, sale_id, product_id, category_id, qty,
             unit_price_cents, discount_cents, tax_cents, line_total_cents,
             notes, created_at, updated_at)
          VALUES
            (:org_id, :sale_id, :product_id, :category_id, :qty,
             :unit_price_cents, :discount_cents, :tax_cents, :line_total_cents,
             :notes, NOW(), NOW())
        ";
        $itemSt = $pdo->prepare($itemSql);

        foreach ($items as $it) {
            $pid   = (int)($it['id'] ?? 0);
            $qty   = (float)($it['qty'] ?? 0);
            $price = (float)($it['price'] ?? 0);
            if ($pid <= 0 || $qty <= 0 || $price < 0) {
                continue; // skip bad rows but keep others
            }

            $line  = $qty * $price;
            $priceCents = (int)round($price * 100);
            $lineCents  = (int)round($line * 100);

            $itemSt->execute([
                ':org_id'           => $orgId,
                ':sale_id'          => $saleId,
                ':product_id'       => $pid,
                ':category_id'      => null,   // can be filled later if needed
                ':qty'              => $qty,
                ':unit_price_cents' => $priceCents,
                ':discount_cents'   => 0,
                ':tax_cents'        => 0,
                ':line_total_cents' => $lineCents,
                ':notes'            => null,
            ]);
        }

        $this->commit();

        echo json_encode([
            'ok'         => true,
            'id'         => $saleId,
            'invoice_no' => $invoiceNo,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    } catch (Throwable $e) {
        $this->rollBack();
        http_response_code(500);
        echo json_encode(['ok' => false, 'error' => 'Sale save failed'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        // you can log $e as needed
    }
}
  
 
/* ============================================================
   INVOICE PRINT (A4 + POS RECEIPT) WITH QR
============================================================ */

/** GET /sales/{id}/print/a4 */
public function printA4(array $ctx = [], int $id = 0): void
{
    try {
        $c     = $this->ensureBase($ctx);
        $pdo   = $this->pdo();
        $orgId = $this->requireOrg();

        [$sale, $lines, $noCol, $dateCol] = $this->fetchSale($pdo, $orgId, $id);

        // ---------- QR: full invoice URL ----------
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
        $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $invoiceUrl = $scheme . $host . rtrim($c['module_base'], '/') . '/sales/' . $id;

        // simple public QR API (can swap to local lib later)
        $qrUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=140x140&data='
               . rawurlencode($invoiceUrl);

        $branding = $this->branding(); // from BaseController as discussed

        $this->view($c['module_dir'].'/Views/sales/print_a4.php', [
            'title'       => 'Invoice '.$sale[$noCol].' (A4)',
            'sale'        => $sale,
            'items'       => $lines,
            'noCol'       => $noCol,
            'dateCol'     => $dateCol,
            'base'        => $c['module_base'],
            'org'         => [
                'id'      => $orgId,
                'name'    => (string)($branding['business_name'] ?? 'Your Business Name'),
                'address' => (string)($branding['address']       ?? ''),
                'phone'   => (string)($branding['phone']         ?? ''),
                'email'   => (string)($branding['email']         ?? ''),
                'website' => (string)($branding['website']       ?? ''),
            ],
            'orgLogo'     => (string)($branding['logo_path'] ?? ''),
            'invoice_url' => $invoiceUrl,
            'qr_url'      => $qrUrl,
        ], 'shell');
    } catch (\Throwable $e) {
        $this->oops('Sales print A4 failed', $e);
    }
}

/** GET /sales/{id}/print/pos */
public function printPos(array $ctx = [], int $id = 0): void
{
    try {
        $c     = $this->ensureBase($ctx);
        $pdo   = $this->pdo();
        $orgId = $this->requireOrg();

        [$sale, $lines, $noCol, $dateCol] = $this->fetchSale($pdo, $orgId, $id);

        $this->view($c['module_dir'].'/Views/sales/print_pos.php', [
            'title' => 'Receipt '.$sale[$noCol],
            'sale'  => $sale,
            'items' => $lines,
            'noCol' => $noCol,
            'dateCol'=> $dateCol,
            'org'   => [
                'id'      => $orgId,
                'name'    => (string)($c['org']['name']    ?? ''),
                'address' => (string)($c['org']['address'] ?? ''),
                'phone'   => (string)($c['org']['phone']   ?? ''),
                'email'   => (string)($c['org']['email']   ?? ''),
            ],
            'orgLogo' => rtrim($c['module_base'],'/').'/Assets/Brand/logo/'.$orgId.'/logo.png',
        ], 'blank');   // ← FIXED
    } catch (Throwable $e) {
        $this->oops('Sales print POS failed', $e);
    }
}
   


    /** GET /sales/refunds */
    public function refundsPage(array $ctx = []): void
    {
        try {
            $c     = $this->ensureBase($ctx);
            $pdo   = $this->pdo();
            $orgId = $this->requireOrg();
            $sales = $this->salesTable($pdo);

            $id    = 'id';
            $no    = $this->hasCol($pdo,$sales,'invoice_no') ? 'invoice_no'
                    : ($this->hasCol($pdo,$sales,'sale_no') ? 'sale_no' : 'code');
            $cust  = $this->hasCol($pdo,$sales,'customer_name') ? 'customer_name'
                    : ($this->hasCol($pdo,$sales,'customer') ? 'customer' : "''");
            $total = $this->hasCol($pdo,$sales,'grand_total') ? 'grand_total'
                    : ($this->hasCol($pdo,$sales,'total') ? 'total' : '0');
            $stat  = $this->hasCol($pdo,$sales,'status') ? 'status'
                    : ($this->hasCol($pdo,$sales,'invoice_status') ? 'invoice_status' : "''");
            $dt    = $this->hasCol($pdo,$sales,'created_at') ? 'created_at'
                    : ($this->hasCol($pdo,$sales,'sale_date') ? 'sale_date' : 'NOW()');

            $refundStatuses = ['refunded','refund','void','voided','returned','cancelled','credit'];
            $ph   = implode(',', array_fill(0, count($refundStatuses), '?'));
            $args = [$orgId, ...$refundStatuses];

            $sql = "SELECT {$id} id, {$no} invoice_no, {$cust} customer_name, {$total} total_amount, {$stat} status, {$dt} created_at
                    FROM {$sales}
                    WHERE org_id=? AND ({$total} < 0 OR {$stat} IN ({$ph}))
                    ORDER BY {$id} DESC LIMIT 200";
            $st = $pdo->prepare($sql);
            $st->execute($args);
            $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];

            $this->view($c['module_dir'].'/Views/sales/refunds.php', [
                'title'=>'Refunds','rows'=>$rows,'base'=>$c['module_base'],
            ], 'shell');
        } catch (Throwable $e) {
            $this->oops('Sales refunds failed', $e);
        }
    }

    /* -------------------------- APIs --------------------------- */

    /** GET /sales/api/products */
public function apiProducts(array $ctx = []): void
{
    try {
        $c     = $this->ctx($ctx);
        $pdo   = $this->pdo();
        $orgId = (int)($c['org_id'] ?? 0);

        $q            = trim((string)($_GET['q'] ?? ''));
        $mode         = strtolower((string)($_GET['mode'] ?? '')); // 'category' | 'brand' | ''
        $exactBarcode = (string)($_GET['exact_barcode'] ?? '') === '1';

        // ---------------- WHERE & ARGS (positional) ----------------
        $where = 'p.org_id = ? AND p.is_active = 1';
        $args  = [$orgId];

        if ($exactBarcode && $q !== '') {
            // Exact barcode scan
            $where .= ' AND p.barcode = ?';
            $args[] = $q;
        } elseif ($q !== '') {
            // Normal search
            $like   = "%{$q}%";
            $where .= ' AND (p.name LIKE ? OR p.sku LIKE ? OR p.barcode LIKE ?';
            $args[] = $like;
            $args[] = $like;
            $args[] = $like;

            if ($mode === 'category') {
                $where .= ' OR c.name LIKE ?';
                $args[] = $like;
            } elseif ($mode === 'brand') {
                $where .= ' OR b.name LIKE ?';
                $args[] = $like;
            }

            $where .= ')';
        }

        // ---------------- SQL ----------------
        $sql = "
            SELECT
              p.id,
              p.sku,
              p.name,
              p.unit       AS unit_name,
              p.sale_price,
              p.barcode,
              c.name       AS category_name,
              b.name       AS brand_name
            FROM pos_products p
            LEFT JOIN pos_categories c
              ON c.id = p.category_id AND c.org_id = p.org_id
            LEFT JOIN pos_brands b
              ON b.id = p.brand_id AND b.org_id = p.org_id
            WHERE {$where}
            ORDER BY p.name ASC
            LIMIT 60
        ";

        $st = $pdo->prepare($sql);
        $st->execute($args);
        $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];

        // ---------------- Shape JSON for the UI ----------------
        $items = array_map(static function (array $r): array {
            return [
                'id'            => (int)$r['id'],
                'sku'           => (string)($r['sku'] ?? ''),
                'code'          => (string)($r['sku'] ?? ''),
                'name'          => (string)($r['name'] ?? ''),
                'unit_name'     => (string)($r['unit_name'] ?: 'pcs'),
                'sale_price'    => (float)($r['sale_price'] ?? 0),
                'barcode'       => (string)($r['barcode'] ?? ''),
                'category_name' => (string)($r['category_name'] ?? ''),
                'brand_name'    => (string)($r['brand_name'] ?? ''),
            ];
        }, $rows);

        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($items, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    } catch (\Throwable $e) {
        // Debug-friendly error so you can see details in the browser if something goes wrong
        header('Content-Type: text/plain; charset=utf-8');
        echo "Lookup failed (apiProducts):\n\n";
        echo $e->getMessage() . "\n\n";
        echo $e->getTraceAsString();
        exit;
    }
}

    /** GET /sales/api/tiles */
    public function apiTiles(array $ctx = []): void
    {
        $pdo = $this->pdo(); $orgId = $this->requireOrg();
        header('Content-Type: application/json; charset=utf-8');
        try {
            $mode = (string)($_GET['mode'] ?? 'category');
            $q    = '%'.trim((string)($_GET['q'] ?? '')).'%';
            $rows = [];

            if ($mode === 'category' && $this->hasTable($pdo,'dms_categories')) {
                $st = $pdo->prepare("SELECT id,name FROM dms_categories WHERE org_id=? AND name LIKE ? ORDER BY name LIMIT 60");
                $st->execute([$orgId,$q]); $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
            } elseif ($mode === 'brand' && $this->hasTable($pdo,'dms_brands')) {
                $st = $pdo->prepare("SELECT id,name FROM dms_brands WHERE org_id=? AND name LIKE ? ORDER BY name LIMIT 60");
                $st->execute([$orgId,$q]); $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
            }

            echo json_encode($rows, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
        } catch (Throwable) {
            echo json_encode([], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
        }
        exit;
    }

    /** POST /sales/api/customers.create */
    public function apiCustomersCreate(array $ctx = []): void
    {
        $pdo = $this->pdo(); $orgId = $this->requireOrg();
        header('Content-Type: application/json; charset=utf-8');
        try {
            $tbl = $this->custsTable($pdo);
            if (!$tbl) { echo json_encode(['ok'=>false]); exit; }

            $in = json_decode(file_get_contents('php://input') ?: '[]', true) ?: [];
            $name = trim((string)($in['name'] ?? ''));
            if ($name === '') { echo json_encode(['ok'=>false]); exit; }

            $cols = ['org_id','name','created_at']; $qs = ['?','?','NOW()']; $vals = [$orgId,$name];
            if ($this->hasCol($pdo,$tbl,'phone') && !empty($in['phone'])) { $cols[]='phone'; $qs[]='?'; $vals[]=(string)$in['phone']; }
            if ($this->hasCol($pdo,$tbl,'updated_at')) { $cols[]='updated_at'; $qs[]='NOW()'; }

            $sql = "INSERT INTO {$tbl} (".implode(',',$cols).") VALUES (".implode(',',$qs).")";
            $pdo->prepare($sql)->execute($vals);
            echo json_encode(['ok'=>true,'id'=>(int)$pdo->lastInsertId()]);
        } catch (Throwable) {
            echo json_encode(['ok'=>false]);
        }
        exit;
    }
}