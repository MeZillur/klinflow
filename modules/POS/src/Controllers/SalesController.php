<?php
declare(strict_types=1);

namespace Modules\POS\Controllers;

use PDO;
use Throwable;

final class SalesController extends BaseController
{
    /* ============================================================
       LOW-LEVEL HELPERS (tables / customers / invoices / fetch)
       ============================================================ */

  private function env(array $ctx): array
{
    // same pattern as GlController
    $c     = $this->ctx($ctx);
    $base  = (string)($c['module_base'] ?? '/apps/pos');
    $orgId = (int)($c['org']['id'] ?? $c['org_id'] ?? 0);
    $pdo   = $this->pdo();

    return [$c, $base, $orgId, $pdo];
}
  
  
  
    /** Core POS tables (with fallbacks so it works in DMS-style schemas). */
    protected function salesTable(PDO $pdo): string
    {
        return $this->pickTable(
            $pdo,
            ['pos_sales', 'dms_sales', 'sales'],
            'Sales table not found'
        );
    }

    protected function itemsTable(PDO $pdo): string
    {
        return $this->pickTable(
            $pdo,
            ['pos_sale_items', 'dms_sale_items', 'dms_sales_items', 'sale_items', 'sales_items'],
            'Sale items table not found'
        );
    }

    protected function prodsTable(PDO $pdo): string
    {
        return $this->pickTable(
            $pdo,
            ['pos_products', 'dms_products', 'products'],
            'Products table not found'
        );
    }

    /** Optional customers table. */
    protected function custsTable(PDO $pdo): ?string
    {
        foreach (['pos_customers', 'dms_customers', 'customers'] as $t) {
            if ($this->hasTable($pdo, $t)) {
                return $t;
            }
        }
        return null;
    }

    /** Simple counter-based invoice generator (if counters table exists). */
    protected function nextInvoiceNo(PDO $pdo, int $orgId): string
    {
        $y = (int)date('Y');

        foreach (['pos_counters', 'dms_counters'] as $ctr) {
            if (
                $this->hasTable($pdo, $ctr) &&
                $this->hasCol($pdo, $ctr, 'name') &&
                $this->hasCol($pdo, $ctr, 'y') &&
                $this->hasCol($pdo, $ctr, 'seq')
            ) {
                $st = $pdo->prepare(
                    "INSERT INTO {$ctr} (org_id, name, y, seq)
                     VALUES (?,?,?,0)
                     ON DUPLICATE KEY UPDATE seq = LAST_INSERT_ID(seq + 1)"
                );
                $st->execute([$orgId, 'invoice', $y]);
                $seq = (int)$pdo->lastInsertId();
                if ($seq > 0) {
                    return sprintf('INV-%d-%05d', $y, $seq);
                }
            }
        }

        // fallback: random slug
        return sprintf('INV-%d-%s', $y, strtoupper(bin2hex(random_bytes(3))));
    }

    /**
     * Shared loader: header + lines (with product/category) + invoice/date col.
     */
    protected function fetchSale(PDO $pdo, int $orgId, int $id): array
    {
        $salesTbl = $this->salesTable($pdo);
        $itemsTbl = $this->itemsTable($pdo);

        // Header
        $hdr = $pdo->prepare("SELECT * FROM {$salesTbl} WHERE org_id = ? AND id = ? LIMIT 1");
        $hdr->execute([$orgId, $id]);
        $sale = $hdr->fetch(PDO::FETCH_ASSOC) ?: null;
        if (!$sale) {
            throw new \RuntimeException('Sale not found');
        }

        // Lines (join products + categories; amounts in BDT)
        $it = $pdo->prepare("
            SELECT
              si.*,
              p.name AS product_name,
              p.sku,
              p.unit        AS unit_name,
              p.category_id,
              c.name        AS category_name,
              (si.unit_price_cents / 100.0) AS unit_price,
              (si.line_total_cents / 100.0) AS line_total
            FROM {$itemsTbl} si
            LEFT JOIN pos_products p
               ON p.id     = si.product_id
              AND p.org_id = si.org_id
            LEFT JOIN pos_categories c
               ON c.id     = p.category_id
              AND c.org_id = p.org_id
            WHERE si.org_id = ?
              AND si.sale_id = ?
            ORDER BY si.sale_item_id ASC
        ");
        $it->execute([$orgId, $id]);
        $lines = $it->fetchAll(PDO::FETCH_ASSOC) ?: [];

        // Invoice & date column names
        $noCol = $this->hasCol($pdo, $salesTbl, 'invoice_no') ? 'invoice_no'
               : ($this->hasCol($pdo, $salesTbl, 'sale_no') ? 'sale_no'
               : ($this->hasCol($pdo, $salesTbl, 'code') ? 'code' : 'id'));

        $dateCol = $this->hasCol($pdo, $salesTbl, 'sale_date')   ? 'sale_date'
                 : ($this->hasCol($pdo, $salesTbl, 'created_at') ? 'created_at'
                 :  '');

        return [$sale, $lines, $noCol, $dateCol];
    }

    

    /* ============================================================
       PAGES
       ============================================================ */

    /** GET /sales */
    public function index(array $ctx = []): void
    {
        try {
            $c      = $this->ensureBase($ctx);
            $pdo    = $this->pdo();
            $orgId  = $this->requireOrg();
            $sales  = $this->salesTable($pdo);
            $branch = $this->currentBranchId();   // from BaseController

            $id    = 'id';
            $no    = $this->hasCol($pdo,$sales,'invoice_no') ? 'invoice_no'
                    : ($this->hasCol($pdo,$sales,'sale_no') ? 'sale_no' : 'code');
            $cust  = $this->hasCol($pdo,$sales,'customer_name') ? 'customer_name'
                    : ($this->hasCol($pdo,$sales,'customer') ? 'customer' : "''");
            $total = $this->hasCol($pdo,$sales,'total_amount') ? 'total_amount'
                    : ($this->hasCol($pdo,$sales,'grand_total') ? 'grand_total' : '0');
            $stat  = $this->hasCol($pdo,$sales,'status') ? 'status'
                    : ($this->hasCol($pdo,$sales,'invoice_status') ? 'invoice_status' : "''");
            $dt    = $this->hasCol($pdo,$sales,'sale_date') ? 'sale_date'
                    : ($this->hasCol($pdo,$sales,'created_at') ? 'created_at' : 'NOW()');

            $sql  = "SELECT {$id} id, {$no} invoice_no, {$cust} customer_name,
                            {$total} total_amount, {$stat} status, {$dt} created_at
                     FROM {$sales}
                     WHERE org_id = ?";
            $args = [$orgId];

            if ($branch && $this->hasCol($pdo, $sales, 'branch_id')) {
                $sql .= " AND branch_id = ?";
                $args[] = $branch;
            }

            $sql .= " ORDER BY {$id} DESC LIMIT 200";

            $st   = $pdo->prepare($sql);
            $st->execute($args);
            $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];

            $this->view($c['module_dir'].'/Views/sales/index.php', [
                'title'           => 'Sales',
                'rows'            => $rows,
                'q'               => '',
                'base'            => $c['module_base'],
                'branches'        => $this->listBranches($pdo, $orgId),
                'currentBranchId' => $branch,
            ], 'shell');

        } catch (Throwable $e) {
            $this->oops('Sales index failed', $e);
        }
    }

    /** GET /sales/register */
    public function register(array $ctx = []): void
    {
        try {
            $c      = $this->ensureBase($ctx);
            $pdo    = $this->pdo();
            $orgId  = $this->requireOrg();
            $branch = $this->currentBranchId();   // from BaseController

            $invoice = $this->nextInvoiceNo($pdo, $orgId);

            // Optional customers dropdown
            $customers = [];
            if ($tbl = $this->custsTable($pdo)) {
                $nameCol = $this->hasCol($pdo,$tbl,'name') ? 'name'
                         : ($this->hasCol($pdo,$tbl,'customer_name') ? 'customer_name' : 'id');

                $s = $pdo->prepare(
                    "SELECT id, {$nameCol} AS name
                     FROM {$tbl}
                     WHERE org_id = ?
                     ORDER BY {$nameCol} ASC
                     LIMIT 200"
                );
                $s->execute([$orgId]);
                $customers = $s->fetchAll(PDO::FETCH_ASSOC) ?: [];
            }

            $paymentMethods = [
                ['code'=>'Cash',  'label'=>'Cash'],
                ['code'=>'Card',  'label'=>'Card'],
                ['code'=>'Bkash', 'label'=>'bKash'],
            ];

            $this->view($c['module_dir'].'/Views/sales/register.php', [
                'title'           => 'Sales Register',
                'invoice_no'      => $invoice,
                'customers'       => $customers,
                'categories'      => [],
                'brands'          => [],
                'payment_methods' => $paymentMethods,
                'base'            => $c['module_base'],
                'branches'        => $this->listBranches($pdo, $orgId),
                'currentBranchId' => $branch,
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

            [$sale, $items, $noCol, $dateCol] = $this->fetchSale($pdo, $orgId, $id);

            $this->view($c['module_dir'].'/Views/sales/show.php', [
                'title'   => 'Invoice '.$sale[$noCol],
                'sale'    => $sale,
                'items'   => $items,
                'base'    => $c['module_base'],
                'noCol'   => $noCol,
                'dateCol' => $dateCol,
            ], 'shell');

        } catch (Throwable $e) {
            $this->oops('Sales show failed', $e);
        }
    }

    /* ============================================================
       REAL SALE CREATION (branch-aware + optional stock check)
       POST /sales  (JSON)
       ============================================================ */

    /** POST /sales */
    public function store(array $ctx = []): void
    {
        header('Content-Type: application/json; charset=utf-8');

        try {
            $c      = $this->ensureBase($ctx);
            $pdo    = $this->pdo();
            $orgId  = $this->requireOrg();
            $branch = $this->currentBranchId(); // may be 0 if “no branch”

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

            // Basic header info
            $invoiceNo = trim((string)($in['invoice_no'] ?? ''));
            if ($invoiceNo === '') {
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

            /* ---------- 1) Subtotal from items (server-side) ---------- */
            $subtotal = 0.0;
            $qtyByProduct  = [];   // for inventory check
            $nameByProduct = [];

            foreach ($items as $it) {
                $pid   = (int)($it['id'] ?? 0);
                $qty   = (float)($it['qty']   ?? 0);
                $price = (float)($it['price'] ?? 0);
                if ($pid <= 0 || $qty <= 0 || $price < 0) {
                    continue;
                }
                $subtotal += $qty * $price;

                if (!isset($qtyByProduct[$pid])) $qtyByProduct[$pid] = 0.0;
                $qtyByProduct[$pid] += $qty;
                $nameByProduct[$pid] = (string)($it['name'] ?? '');
            }

            if ($subtotal <= 0) {
                http_response_code(422);
                echo json_encode(['ok' => false, 'error' => 'Subtotal is zero']);
                return;
            }

            /* ---------- 2) Discount ---------- */
            $discountAmount = $discPercent > 0
                ? ($subtotal * $discPercent / 100.0)
                : $discAmountIn;

            if ($discountAmount < 0) $discountAmount = 0.0;

            /* ---------- 3) Tax & total ---------- */
            $taxBase   = max(0.0, $subtotal - $discountAmount);
            $taxAmount = $taxPercent > 0 ? ($taxBase * $taxPercent / 100.0) : 0.0;
            $total     = $taxBase + $taxAmount;

            $salesTbl = $this->salesTable($pdo);
            $itemsTbl = $this->itemsTable($pdo);
            $invTbl   = $this->inventoryTable($pdo); // can be null

            $this->begin();

            /* =====================================================
               OPTIONAL BRANCH STOCK CHECK (if inventory table exists)
               ===================================================== */
            $qtyCol = null;
            $hasBranchInInv = false;
            if ($invTbl !== null) {
                if ($this->hasCol($pdo, $invTbl, 'qty_on_hand')) {
                    $qtyCol = 'qty_on_hand';
                } elseif ($this->hasCol($pdo, $invTbl, 'qty')) {
                    $qtyCol = 'qty';
                }
                $hasBranchInInv = $this->hasCol($pdo, $invTbl, 'branch_id');
            }

            if ($qtyCol !== null && $hasBranchInInv && $branch > 0 && !empty($qtyByProduct)) {
                $pids = array_keys($qtyByProduct);

                // Build placeholders for IN (...)
                $inPlace = implode(',', array_fill(0, count($pids), '?'));

                $sqlInv = "SELECT product_id, {$qtyCol} AS qty
                           FROM {$invTbl}
                           WHERE org_id    = ?
                             AND branch_id = ?
                             AND product_id IN ({$inPlace})
                           FOR UPDATE";

                $argsInv = array_merge([$orgId, $branch], $pids);
                $stInv   = $pdo->prepare($sqlInv);
                $stInv->execute($argsInv);
                $rowsInv = $stInv->fetchAll(PDO::FETCH_ASSOC) ?: [];

                $stockByPid = [];
                foreach ($rowsInv as $r) {
                    $stockByPid[(int)$r['product_id']] = (float)$r['qty'];
                }

                // Validate each required product
                foreach ($qtyByProduct as $pid => $needQty) {
                    $stock = $stockByPid[$pid] ?? 0.0;
                    if ($stock < $needQty) {
                        $name = $nameByProduct[$pid] ?: ("Product #".$pid);
                        $this->rollBack();
                        http_response_code(422);
                        echo json_encode([
                            'ok'    => false,
                            'error' => "Insufficient stock for {$name} in this branch",
                        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                        return;
                    }
                }
            }

            /* =====================================================
               4) Insert header row (with optional branch_id)
               ===================================================== */
            $fields = [
                'org_id'          => ':org_id',
                'invoice_no'      => ':inv',
                'customer_name'   => ':cust_name',
                'customer_id'     => ':cust_id',
                'sale_date'       => ':sale_date',
                'status'          => ':status',
                'subtotal_amount' => ':subtotal',
                'discount_amount' => ':discount',
                'tax_amount'      => ':tax',
                'total_amount'    => ':total',
                'notes'           => ':notes',
            ];

            if ($branch > 0 && $this->hasCol($pdo, $salesTbl, 'branch_id')) {
                $fields['branch_id'] = ':branch_id';
            }

            $cols = array_keys($fields);
            $phs  = array_values($fields);

            // timestamps as NOW()
            $cols[] = 'created_at';
            $cols[] = 'updated_at';
            $phs[]  = 'NOW()';
            $phs[]  = 'NOW()';

            $sqlHdr = "INSERT INTO {$salesTbl} (".implode(',', $cols).") VALUES (".implode(',', $phs).")";
            $stHdr  = $pdo->prepare($sqlHdr);

            $params = [];
            foreach ($fields as $col => $ph) {
                switch ($col) {
                    case 'org_id':          $params[$ph] = $orgId; break;
                    case 'invoice_no':      $params[$ph] = $invoiceNo; break;
                    case 'customer_name':   $params[$ph] = $custName; break;
                    case 'customer_id':     $params[$ph] = $custId ?: null; break;
                    case 'sale_date':       $params[$ph] = $saleDate; break;
                    case 'status':          $params[$ph] = 'posted'; break;
                    case 'subtotal_amount': $params[$ph] = $subtotal; break;
                    case 'discount_amount': $params[$ph] = $discountAmount; break;
                    case 'tax_amount':      $params[$ph] = $taxAmount; break;
                    case 'total_amount':    $params[$ph] = $total; break;
                    case 'notes':           $params[$ph] = $notes !== '' ? $notes : null; break;
                    case 'branch_id':       $params[$ph] = $branch; break;
                }
            }

            $stHdr->execute($params);
            $saleId = (int)$pdo->lastInsertId();

            /* =====================================================
               5) Insert line items (with optional branch_id)
               ===================================================== */
            $itemCols = [
                'org_id',
                'sale_id',
                'product_id',
                'category_id',
                'qty',
                'unit_price_cents',
                'discount_cents',
                'tax_cents',
                'line_total_cents',
                'notes',
            ];

            if ($branch > 0 && $this->hasCol($pdo, $itemsTbl, 'branch_id')) {
                $itemCols[] = 'branch_id';
            }

            $itemCols[] = 'created_at';
            $itemCols[] = 'updated_at';

            $itemPhs = [];
            foreach ($itemCols as $cName) {
                if ($cName === 'created_at' || $cName === 'updated_at') {
                    $itemPhs[] = 'NOW()';
                } else {
                    $itemPhs[] = ':'.$cName;
                }
            }

            $itemSql = "INSERT INTO {$itemsTbl} (".implode(',', $itemCols).")
                        VALUES (".implode(',', $itemPhs).")";
            $itemSt = $pdo->prepare($itemSql);

            foreach ($items as $it) {
                $pid   = (int)($it['id'] ?? 0);
                $qty   = (float)($it['qty'] ?? 0);
                $price = (float)($it['price'] ?? 0);
                if ($pid <= 0 || $qty <= 0 || $price < 0) {
                    continue; // skip bad rows but keep others
                }

                $line       = $qty * $price;
                $priceCents = (int)round($price * 100);
                $lineCents  = (int)round($line * 100);

                $paramsItem = [
                    ':org_id'           => $orgId,
                    ':sale_id'          => $saleId,
                    ':product_id'       => $pid,
                    ':category_id'      => null,
                    ':qty'              => $qty,
                    ':unit_price_cents' => $priceCents,
                    ':discount_cents'   => 0,
                    ':tax_cents'        => 0,
                    ':line_total_cents' => $lineCents,
                    ':notes'            => null,
                ];

                if (in_array('branch_id', $itemCols, true)) {
                    $paramsItem[':branch_id'] = $branch > 0 ? $branch : null;
                }

                $itemSt->execute($paramsItem);
            }

            /* =====================================================
               6) Decrement inventory (same org + branch + product)
               ===================================================== */
            if ($qtyCol !== null && $hasBranchInInv && $branch > 0 && !empty($qtyByProduct)) {
                $updSql = "UPDATE {$invTbl}
                           SET {$qtyCol} = {$qtyCol} - :qty,
                               updated_at = NOW()
                           WHERE org_id    = :org_id
                             AND branch_id = :branch_id
                             AND product_id = :product_id
                           LIMIT 1";
                $updSt = $pdo->prepare($updSql);

                foreach ($qtyByProduct as $pid => $needQty) {
                    $updSt->execute([
                        ':qty'        => $needQty,
                        ':org_id'     => $orgId,
                        ':branch_id'  => $branch,
                        ':product_id' => $pid,
                    ]);
                }
            }

            $this->commit();

            echo json_encode([
                'ok'         => true,
                'id'         => $saleId,
                'invoice_no' => $invoiceNo,
                'branch_id'  => $branch,
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        } catch (Throwable $e) {
            $this->rollBack();
            http_response_code(500);
            echo json_encode(
                ['ok' => false, 'error' => 'Sale save failed'],
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
            );
        }
    }

    /* ============================================================
       PRINT (A4 + POS) – branch-agnostic but QR + branding aware
       ============================================================ */

    /** GET /sales/{id}/print/a4 */
    public function printA4(array $ctx = [], int $id = 0): void
    {
        try {
            $c     = $this->ensureBase($ctx);
            $pdo   = $this->pdo();
            $orgId = $this->requireOrg();

            [$sale, $lines, $noCol, $dateCol] = $this->fetchSale($pdo, $orgId, $id);

            // Full invoice URL for QR
            $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
                ? 'https://' : 'http://';
            $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
            $invoiceUrl = $scheme . $host . rtrim($c['module_base'], '/') . '/sales/' . $id;

            $qrUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=140x140&data='
                   . rawurlencode($invoiceUrl);

            $branding = $this->branding();

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
            ], null); // bare print (no shell)
        } catch (Throwable $e) {
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

            $branding = $this->branding();

            $this->view($c['module_dir'].'/Views/sales/print_pos.php', [
                'title'   => 'Receipt '.$sale[$noCol],
                'sale'    => $sale,
                'items'   => $lines,
                'noCol'   => $noCol,
                'dateCol' => $dateCol,
                'org'     => [
                    'id'      => $orgId,
                    'name'    => (string)($branding['business_name'] ?? 'Your Business Name'),
                    'address' => (string)($branding['address']       ?? ''),
                    'phone'   => (string)($branding['phone']         ?? ''),
                    'email'   => (string)($branding['email']         ?? ''),
                ],
                'orgLogo' => (string)($branding['logo_path'] ?? ''),
            ], null);  // bare print
        } catch (Throwable $e) {
            $this->oops('Sales print POS failed', $e);
        }
    }

    /* ============================================================
       REFUNDS PAGE
       ============================================================ */

    /** GET /sales/refunds */
    public function refundsPage(array $ctx = []): void
    {
        try {
            $c      = $this->ensureBase($ctx);
            $pdo    = $this->pdo();
            $orgId  = $this->requireOrg();
            $sales  = $this->salesTable($pdo);
            $branch = $this->currentBranchId();

            $id    = 'id';
            $no    = $this->hasCol($pdo,$sales,'invoice_no') ? 'invoice_no'
                    : ($this->hasCol($pdo,$sales,'sale_no') ? 'sale_no' : 'code');
            $cust  = $this->hasCol($pdo,$sales,'customer_name') ? 'customer_name'
                    : ($this->hasCol($pdo,$sales,'customer') ? 'customer' : "''");
            $total = $this->hasCol($pdo,$sales,'total_amount') ? 'total_amount'
                    : ($this->hasCol($pdo,$sales,'grand_total') ? 'grand_total' : '0');
            $stat  = $this->hasCol($pdo,$sales,'status') ? 'status'
                    : ($this->hasCol($pdo,$sales,'invoice_status') ? 'invoice_status' : "''");
            $dt    = $this->hasCol($pdo,$sales,'created_at') ? 'created_at'
                    : ($this->hasCol($pdo,$sales,'sale_date') ? 'sale_date' : 'NOW()');

            $refundStatuses = ['refunded','refund','void','voided','returned','cancelled','credit'];
            $ph   = implode(',', array_fill(0, count($refundStatuses), '?'));
            $args = [$orgId, ...$refundStatuses];

            $sql = "SELECT {$id} id, {$no} invoice_no, {$cust} customer_name,
                           {$total} total_amount, {$stat} status, {$dt} created_at
                    FROM {$sales}
                    WHERE org_id = ?
                      AND ({$total} < 0 OR {$stat} IN ({$ph}))";

            if ($branch && $this->hasCol($pdo, $sales, 'branch_id')) {
                $sql .= " AND branch_id = ?";
                $args[] = $branch;
            }

            $sql .= " ORDER BY {$id} DESC LIMIT 200";

            $st = $pdo->prepare($sql);
            $st->execute($args);
            $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];

            $this->view($c['module_dir'].'/Views/sales/refunds.php', [
                'title'           => 'Refunds',
                'rows'            => $rows,
                'base'            => $c['module_base'],
                'branches'        => $this->listBranches($pdo, $orgId),
                'currentBranchId' => $branch,
            ], 'shell');
        } catch (Throwable $e) {
            $this->oops('Sales refunds failed', $e);
        }
    }
  
  	public function hold(array $ctx = []): void
{
    [$c, $base, $orgId, $pdo] = $this->env($ctx);

    // Adjust table / status according to your schema
    $rows = $this->rows("
        SELECT id, invoice_no, customer_name, total_amount, created_at
        FROM pos_sales
        WHERE org_id = :o
          AND status  = 'hold'
        ORDER BY created_at DESC
        LIMIT 200
    ", [':o' => $orgId]);

    $this->view('sales/hold', [
        'base' => $base,
        'rows' => $rows,
    ], 'shell');
}
  
  public function resume(array $ctx = []): void
{
    try {
        [$c, $base, $orgId, $pdo] = $this->env($ctx);

        // Recent parked/held sales – tweak table/columns if your schema differs
        $rows = $this->rows("
            SELECT
                id,
                invoice_no,
                customer_name,
                total_amount,
                created_at
            FROM pos_sales
            WHERE org_id = :o
              AND status IN ('hold','parked','draft')
            ORDER BY created_at DESC
            LIMIT 20
        ", [':o' => $orgId]);

        $this->view(
            'sales/resume',
            [
                'base' => $base,
                'rows' => $rows,
            ],
            'shell'
        );
    } catch (Throwable $e) {
        $this->oops('Resume sales page failed', $e);
    }
}

    /* ============================================================
       APIS
       ============================================================ */

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

            $st   = $pdo->prepare($sql);
            $st->execute($args);
            $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];

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
        } catch (Throwable $e) {
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
        $pdo   = $this->pdo();
        $orgId = $this->requireOrg();
        header('Content-Type: application/json; charset=utf-8');
        try {
            $mode = (string)($_GET['mode'] ?? 'category');
            $q    = '%'.trim((string)($_GET['q'] ?? '')).'%';
            $rows = [];

            if ($mode === 'category' && $this->hasTable($pdo,'dms_categories')) {
                $st = $pdo->prepare(
                    "SELECT id,name
                     FROM dms_categories
                     WHERE org_id = ? AND name LIKE ?
                     ORDER BY name
                     LIMIT 60"
                );
                $st->execute([$orgId,$q]);
                $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
            } elseif ($mode === 'brand' && $this->hasTable($pdo,'dms_brands')) {
                $st = $pdo->prepare(
                    "SELECT id,name
                     FROM dms_brands
                     WHERE org_id = ? AND name LIKE ?
                     ORDER BY name
                     LIMIT 60"
                );
                $st->execute([$orgId,$q]);
                $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
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
        $pdo   = $this->pdo();
        $orgId = $this->requireOrg();
        header('Content-Type: application/json; charset=utf-8');
        try {
            $tbl = $this->custsTable($pdo);
            if (!$tbl) { echo json_encode(['ok'=>false]); exit; }

            $in   = json_decode(file_get_contents('php://input') ?: '[]', true) ?: [];
            $name = trim((string)($in['name'] ?? ''));
            if ($name === '') { echo json_encode(['ok'=>false]); exit; }

            $cols = ['org_id','name','created_at'];
            $qs   = ['?','?','NOW()'];
            $vals = [$orgId,$name];

            if ($this->hasCol($pdo,$tbl,'phone') && !empty($in['phone'])) {
                $cols[] = 'phone';
                $qs[]   = '?';
                $vals[] = (string)$in['phone'];
            }
            if ($this->hasCol($pdo,$tbl,'updated_at')) {
                $cols[] = 'updated_at';
                $qs[]   = 'NOW()';
            }

            $sql = "INSERT INTO {$tbl} (".implode(',',$cols).") VALUES (".implode(',',$qs).")";
            $pdo->prepare($sql)->execute($vals);
            echo json_encode(['ok'=>true,'id'=>(int)$pdo->lastInsertId()]);
        } catch (Throwable) {
            echo json_encode(['ok'=>false]);
        }
        exit;
    }
}