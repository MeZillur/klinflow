<?php
declare(strict_types=1);

namespace Modules\BizFlow\Controllers;

use PDO;
use Throwable;

/**
 * BizFlow AwardsController
 *
 * - Lists and shows awards
 * - Creates awards from quotes
 * - Creates purchase orders from awards
 *
 * Tables used (current design):
 *   biz_quotes
 *   biz_quote_lines
 *   biz_awards
 *   biz_award_lines
 *   biz_suppliers
 *   biz_purchase_orders
 *   biz_purchase_order_lines
 */
final class AwardsController extends BaseController
{
    /* -------------------------------------------------------------
     * Shared helper: ctx + org + PDO + module base
     * ----------------------------------------------------------- */
    private function base(?array $ctx = null): array
    {
        $c          = $this->ctx($ctx ?? []);
        $orgId      = $this->requireOrg();
        $pdo        = $this->pdo();
        $moduleBase = $c['module_base'] ?? '/apps/bizflow';

        return [$c, $orgId, $pdo, $moduleBase];
    }
  

    /* =============================================================
     * INDEX
     * =========================================================== */

    /**
     * GET /apps/bizflow/awards
     * GET /t/{slug}/apps/bizflow/awards
     */
    public function index(?array $ctx = null): void
    {
        try {
            [$c, $orgId, $pdo, $moduleBase] = $this->base($ctx);

            $q      = trim((string)($_GET['q'] ?? ''));
            $status = trim((string)($_GET['status'] ?? ''));

            $where = ['a.org_id = ?'];
            $bind  = [$orgId];

            if ($q !== '') {
                $like    = '%' . $q . '%';
                $where[] = '(a.award_no LIKE ? OR a.external_ref LIKE ?)';
                $bind[]  = $like;
                $bind[]  = $like;
            }

            if ($status !== '' && $status !== 'all') {
                $where[] = 'a.status = ?';
                $bind[]  = $status;
            }

            $sql = "
                SELECT
                    a.id,
                    a.award_no,
                    a.status,
                    a.award_date,
                    a.currency,
                    a.grand_total,
                    a.quote_id,
                    a.external_ref
                FROM biz_awards a
                WHERE " . implode(' AND ', $where) . "
                ORDER BY a.id DESC
                LIMIT 200
            ";

            $awards = $this->rows($sql, $bind);

            $this->view('awards/index', [
                'title'       => 'Awards',
                'org'         => $c['org'] ?? [],
                'module_base' => $moduleBase,
                'awards'      => $awards,
                'search'      => $q,
                'status'      => $status,
            ], 'shell');

        } catch (Throwable $e) {
            $this->oops('Awards index failed', $e);
        }
    }

    /* =============================================================
     * SHOW
     * =========================================================== */

    /**
     * GET /apps/bizflow/awards/{id}
     * GET /t/{slug}/apps/bizflow/awards/{id}
     */
    public function show(?array $ctx = null, int $id = 0): void
    {
        try {
            [$c, $orgId, $pdo, $moduleBase] = $this->base($ctx);

            $award = $this->row(
                "SELECT *
                   FROM biz_awards
                  WHERE org_id = ? AND id = ?
                  LIMIT 1",
                [$orgId, $id]
            );

            if (!$award) {
                http_response_code(404);
                echo 'Award not found';
                return;
            }

            $lines = $this->rows(
                "SELECT *
                   FROM biz_award_lines
                  WHERE org_id = ? AND award_id = ?
                  ORDER BY line_no ASC, id ASC",
                [$orgId, $id]
            );

            $title = 'Award ' . ($award['award_no'] ?? ('#' . $id));

            $this->view('awards/show', [
                'title'       => $title,
                'org'         => $c['org'] ?? [],
                'module_base' => $moduleBase,
                'award'       => $award,
                'lines'       => $lines,
            ], 'shell');

        } catch (Throwable $e) {
            $this->oops('Award show failed', $e);
        }
    }

    /* =============================================================
     * CREATE AWARD FROM QUOTE
     * =========================================================== */

    /**
     * GET /apps/bizflow/quotes/{id}/award
     * GET /t/{slug}/apps/bizflow/quotes/{id}/award
     *
     * Create an award from a quote.
     */
    public function createFromQuote(?array $ctx = null, int $quoteId = 0): void
    {
        $pdo = null;

        try {
            if ($quoteId <= 0) {
                http_response_code(400);
                echo 'Invalid quote id.';
                return;
            }

            [$c, $orgId, $pdo, $moduleBase] = $this->base($ctx);

            $pdo->beginTransaction();

            // 1) Load quote header
            $quote = $this->row(
                "SELECT *
                   FROM biz_quotes
                  WHERE org_id = ? AND id = ?
                  LIMIT 1",
                [$orgId, $quoteId]
            );

            if (!$quote) {
                $pdo->rollBack();
                http_response_code(404);
                echo 'Quote not found.';
                return;
            }

            // 1b) If an award already exists for this quote, go there
            $existingAwardId = $this->val(
                "SELECT id
                   FROM biz_awards
                  WHERE org_id = ? AND quote_id = ?
                  LIMIT 1",
                [$orgId, $quoteId]
            );

            if ($existingAwardId) {
                $pdo->rollBack();
                $this->redirect(rtrim($moduleBase, '/') . '/awards/' . (int)$existingAwardId);
                return;
            }

            // 2) Prepare header fields from quote
            $currency = (string)($quote['currency'] ?? 'BDT');
            $date     = (string)($quote['date'] ?? date('Y-m-d'));

            $custName = trim((string)($quote['customer_name'] ?? ''));
            if ($custName === '') {
                $custName = 'Customer';
            }

            $externalRef = (string)($quote['external_ref'] ?? '');
            $subtotal    = (float)($quote['subtotal'] ?? 0);
            $discountTot = (float)($quote['discount_total'] ?? 0);
            $taxTot      = (float)($quote['tax_total'] ?? 0);
            $shipTot     = (float)($quote['shipping_total'] ?? 0);
            $grandTot    = (float)($quote['grand_total'] ?? 0);
            $metaJson    = $quote['meta_json'] ?? null;

            // Meta holds contact + customer reference on the quote
            $meta = [];
            if (!empty($metaJson)) {
                $tmp = json_decode((string)$metaJson, true);
                if (is_array($tmp)) {
                    $meta = $tmp;
                }
            }

            $custContact = trim((string)($meta['customer_contact'] ?? ''));
            $custRef     = trim((string)($meta['customer_reference'] ?? ''));
            if ($custRef === '' && $externalRef !== '') {
                $custRef = $externalRef;
            }

            // 3) Generate award_no
            $awardNo = $this->generateAwardNo($pdo, $orgId, $date);

            // 4) Insert award header (matches biz_awards schema)
            $this->exec(
                "INSERT INTO biz_awards (
                     org_id,
                     quote_id,
                     customer_name,
                     customer_contact,
                     customer_ref,
                     award_no,
                     external_ref,
                     award_date,
                     status,
                     currency,
                     subtotal,
                     discount_total,
                     tax_total,
                     shipping_total,
                     grand_total,
                     meta_json,
                     date
                 ) VALUES (
                     ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?
                 )",
                [
                    $orgId,
                    $quoteId,
                    $custName,
                    $custContact !== '' ? $custContact : null,
                    $custRef !== '' ? $custRef : null,
                    $awardNo,
                    $externalRef !== '' ? $externalRef : null,
                    $date,
                    'confirmed',
                    $currency,
                    $subtotal,
                    $discountTot,
                    $taxTot,
                    $shipTot,
                    $grandTot,
                    $metaJson,
                    $date,
                ]
            );

            $awardId = (int)$pdo->lastInsertId();

            // 5) Copy line items biz_quote_lines → biz_award_lines
            $this->exec(
                "INSERT INTO biz_award_lines (
                     org_id,
                     award_id,
                     quote_line_id,
                     line_no,
                     kind,
                     item_id,
                     item_name,
                     item_code,
                     product_id,
                     product_name,
                     product_code,
                     description,
                     qty,
                     unit,
                     unit_price,
                     discount_pct,
                     line_total,
                     meta_json
                 )
                 SELECT
                     org_id,
                     ?          AS award_id,
                     id         AS quote_line_id,
                     line_no,
                     kind,
                     NULL       AS item_id,
                     name       AS item_name,
                     NULL       AS item_code,
                     product_id,
                     name       AS product_name,
                     NULL       AS product_code,
                     description,
                     qty,
                     unit,
                     unit_price,
                     discount_pct,
                     line_total,
                     meta_json
                   FROM biz_quote_lines
                  WHERE org_id = ? AND quote_id = ?
                  ORDER BY line_no, id",
                [
                    $awardId,
                    $orgId,
                    $quoteId,
                ]
            );

            // 6) Mark quote as approved
            $this->exec(
                "UPDATE biz_quotes
                    SET status = 'approved'
                  WHERE org_id = ? AND id = ?",
                [$orgId, $quoteId]
            );

            $pdo->commit();

            // Go straight to the award details page
            $this->redirect(rtrim($moduleBase, '/') . '/awards/' . $awardId);

        } catch (Throwable $e) {
            if ($pdo && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $this->oops('Create award from quote failed', $e);
        }
    }

    /* =============================================================
     * CREATE PURCHASE ORDER FROM AWARD
     * =========================================================== */

    /**
     * GET  /apps/bizflow/awards/{id}/purchase
     * POST /apps/bizflow/awards/{id}/purchase
     *
     * Step 1 (GET): show award lines + supplier selector
     * Step 2 (POST): create purchase order + lines from this award
     */
    public function createPurchaseFromAward(?array $ctx = null, int $id = 0): void
    {
        $pdo = null;

        try {
            $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

            [$c, $orgId, $pdo, $moduleBase] = $this->base($ctx);

            // 1) Load award header
            $award = $this->row(
                "SELECT *
                   FROM biz_awards
                  WHERE org_id = ? AND id = ?
                  LIMIT 1",
                [$orgId, $id]
            );

            if (!$award) {
                http_response_code(404);
                echo 'Award not found.';
                return;
            }

            // 2) Load award lines
            $lines = $this->rows(
                "SELECT *
                   FROM biz_award_lines
                  WHERE org_id = ? AND award_id = ?
                  ORDER BY line_no ASC, id ASC",
                [$orgId, $id]
            );

            // 3) Supplier list for selector
            $suppliers = $this->rows(
                "SELECT id, name, code, contact_person, contact_name, phone, email
                   FROM biz_suppliers
                  WHERE org_id = ?
                  ORDER BY name
                  LIMIT 300",
                [$orgId]
            );

            $selectedSupplierId = isset($award['supplier_id']) ? (int)$award['supplier_id'] : 0;

            // ---------- GET → show wizard page ----------
            if ($method === 'GET') {
                $this->view('awards/purchase', [
                    'title'                => 'Create purchase from award ' . ($award['award_no'] ?? ('#' . $id)),
                    'org'                  => $c['org'] ?? [],
                    'module_base'          => $moduleBase,
                    'award'                => $award,
                    'lines'                => $lines,
                    'suppliers'            => $suppliers,
                    'selected_supplier_id' => $selectedSupplierId,
                    'error'                => null,
                ], 'shell');
                return;
            }

            // ---------- POST → actually create the PO ----------
            if ($method !== 'POST') {
                http_response_code(405);
                echo 'Method Not Allowed';
                return;
            }

            $supplierId = isset($_POST['supplier_id']) ? (int)$_POST['supplier_id'] : 0;

            // If user didn’t pick anything, but award already has supplier_id, use that.
            if ($supplierId <= 0 && $selectedSupplierId > 0) {
                $supplierId = $selectedSupplierId;
            }

            if ($supplierId <= 0) {
                // Re-show page with error message
                $this->view('awards/purchase', [
                    'title'                => 'Create purchase from award ' . ($award['award_no'] ?? ('#' . $id)),
                    'org'                  => $c['org'] ?? [],
                    'module_base'          => $moduleBase,
                    'award'                => $award,
                    'lines'                => $lines,
                    'suppliers'            => $suppliers,
                    'selected_supplier_id' => 0,
                    'error'                => 'Please select a supplier before creating the purchase order.',
                ], 'shell');
                return;
            }

            // Load supplier details
            $supplier = $this->row(
                "SELECT id, name, code, contact_person, contact_name, phone, email
                   FROM biz_suppliers
                  WHERE org_id = ? AND id = ?
                  LIMIT 1",
                [$orgId, $supplierId]
            );

            if (!$supplier) {
                $this->view('awards/purchase', [
                    'title'                => 'Create purchase from award ' . ($award['award_no'] ?? ('#' . $id)),
                    'org'                  => $c['org'] ?? [],
                    'module_base'          => $moduleBase,
                    'award'                => $award,
                    'lines'                => $lines,
                    'suppliers'            => $suppliers,
                    'selected_supplier_id' => 0,
                    'error'                => 'Selected supplier not found for this organisation.',
                ], 'shell');
                return;
            }

            $pdo->beginTransaction();

            /* -----------------------------------------------------
             * 4) Update biz_awards with supplier info
             * --------------------------------------------------- */
            $supplierContact = '';
            if (!empty($supplier['contact_person'])) {
                $supplierContact = (string)$supplier['contact_person'];
            } elseif (!empty($supplier['contact_name'])) {
                $supplierContact = (string)$supplier['contact_name'];
            } elseif (!empty($supplier['phone'])) {
                $supplierContact = (string)$supplier['phone'];
            } elseif (!empty($supplier['email'])) {
                $supplierContact = (string)$supplier['email'];
            }

            $this->exec(
                "UPDATE biz_awards
                    SET supplier_id      = ?,
                        supplier_name    = ?,
                        supplier_contact = ?
                  WHERE org_id = ? AND id = ?",
                [
                    (int)$supplier['id'],
                    (string)$supplier['name'],
                    $supplierContact !== '' ? $supplierContact : null,
                    $orgId,
                    $id,
                ]
            );

            // 5) Prepare totals (fallback to sum of lines if needed)
            $subtotal       = (float)($award['subtotal']       ?? 0);
            $discountTotal  = (float)($award['discount_total'] ?? 0);
            $taxTotal       = (float)($award['tax_total']      ?? 0);
            $shippingTotal  = (float)($award['shipping_total'] ?? 0);
            $grandTotal     = (float)($award['grand_total']    ?? 0);

            if ($subtotal <= 0 && $lines) {
                $sum = 0.0;
                foreach ($lines as $ln) {
                    $sum += (float)($ln['line_total'] ?? 0);
                }
                $subtotal = $sum;
            }

            if ($grandTotal <= 0) {
                $grandTotal = $subtotal - $discountTotal + $taxTotal + $shippingTotal;
            }

            /* -----------------------------------------------------
             * 6) INSERT into biz_purchase_orders
             * --------------------------------------------------- */
            $poNo = $this->generatePoNo($pdo, $orgId);

            $poDate       = (string)($award['award_date'] ?? $award['date'] ?? date('Y-m-d'));
            $expectedDate = null; // optional future use
            $supplierRef  = (string)($award['customer_ref'] ?? '');
            $currency     = (string)($award['currency']     ?? 'BDT');
            $notes        = $award['notes']     ?? null;
            $metaJson     = $award['meta_json'] ?? null;
            $externalRef  = $award['external_ref'] ?? null;

            $this->exec(
                "INSERT INTO biz_purchase_orders (
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
                 ) VALUES (
                     ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?
                 )",
                [
                    $orgId,
                    $id,
                    $award['quote_id'] ?? null,
                    $poNo,
                    $externalRef,
                    (int)$supplier['id'],
                    (string)$supplier['name'],
                    $supplierContact !== '' ? $supplierContact : null,
                    $supplierRef !== '' ? $supplierRef : null,
                    $currency,
                    $poDate,
                    $expectedDate,
                    $subtotal,
                    $discountTotal,
                    $taxTotal,
                    $shippingTotal,
                    $grandTotal,
                    'draft',
                    $notes,
                    $metaJson,
                ]
            );

            $poId = (int)$pdo->lastInsertId();

            /* -----------------------------------------------------
             * 7) Insert PO lines from award lines
             * --------------------------------------------------- */
            $sqlLine = "
                INSERT INTO biz_purchase_order_lines (
                    org_id,
                    purchase_id,
                    award_line_id,
                    item_id,
                    item_name,
                    item_code,
                    description,
                    qty,
                    unit,
                    unit_price,
                    discount_pct,
                    line_total,
                    meta_json
                ) VALUES (
                    ?, ?, ?,
                    ?, ?, ?,
                    ?, ?, ?, ?, ?, ?,
                    ?
                )
            ";

            foreach ($lines as $ln) {
                $awardLineId = (int)$ln['id'];

                $itemId   = $ln['item_id']   ?? $ln['product_id']   ?? null;
                $itemName = $ln['item_name'] ?? $ln['product_name'] ?? '';
                if ($itemName === '') {
                    $itemName = (string)($ln['description'] ?? 'Award line ' . $awardLineId);
                }
                $itemCode = $ln['item_code'] ?? $ln['product_code'] ?? null;

                $qty       = (float)($ln['qty']          ?? 0);
                $unit      = (string)($ln['unit']        ?? 'pcs');
                $unitPrice = (float)($ln['unit_price']   ?? 0);
                $discPct   = (float)($ln['discount_pct'] ?? 0);
                $lineTotal = (float)($ln['line_total']   ?? 0);

                $this->exec($sqlLine, [
                    $orgId,
                    $poId,
                    $awardLineId,
                    $itemId,
                    $itemName,
                    $itemCode,
                    $ln['description'] ?? null,
                    $qty,
                    $unit,
                    $unitPrice,
                    $discPct,
                    $lineTotal,
                    $ln['meta_json'] ?? null,
                ]);
            }

            $pdo->commit();

            // Redirect straight to THIS purchase show page
            $base = $c['module_base'] ?? '/apps/bizflow';
            $this->redirect(rtrim($base, '/') . '/purchases/' . $poId);

        } catch (Throwable $e) {
            if ($pdo && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $this->oops('Create purchase order from award failed', $e);
        }
    }
  
  
	      /* =============================================================
     * CREATE INVOICE FROM AWARD (one-click)
     *   GET /awards/{id}/invoice
     * -------------------------------------------------------------
     * - If an invoice already exists for this award → redirect to it
     * - Otherwise:
     *     • load award + quote + customer
     *     • create biz_invoices header (NO customer_name column)
     *     • copy biz_award_lines → biz_invoice_items
     *     • link award.invoice_id
     * =========================================================== */
    public function createInvoiceFromAward(?array $ctx = null, int $awardId = 0): void
    {
        $pdo = null;

        try {
            if ($awardId <= 0) {
                http_response_code(400);
                echo 'Invalid award id.';
                return;
            }

            [$c, $orgId, $pdo, $moduleBase] = $this->base($ctx);

            // --- 1) If already invoiced, just redirect to that invoice ---
            $existingInvoiceId = $this->val(
                "SELECT id
                   FROM biz_invoices
                  WHERE org_id = ? AND award_id = ?
                  LIMIT 1",
                [$orgId, $awardId]
            );

            if ($existingInvoiceId) {
                $this->redirect(rtrim($moduleBase, '/') . '/invoices/' . (int)$existingInvoiceId);
                return;
            }

            // --- 2) Load award + quote + customer (for customer_id) ---
            $award = $this->row(
    "SELECT
         a.*,
         q.customer_id       AS customer_id,
         q.date              AS quote_date,
         c.name              AS customer_name,
         c.code              AS customer_code
     FROM biz_awards a
LEFT JOIN biz_quotes q
       ON q.org_id = a.org_id
      AND q.id     = a.quote_id
LEFT JOIN biz_customers c
       ON c.org_id = a.org_id
      AND c.id     = q.customer_id
    WHERE a.org_id = ?
      AND a.id     = ?
    LIMIT 1",
    [$orgId, $awardId]
);

            if (!$award) {
                http_response_code(404);
                echo 'Award not found.';
                return;
            }

            $quoteId    = (int)($award['quote_id']    ?? 0);
            $customerId = (int)($award['customer_id'] ?? 0);

            if ($customerId <= 0) {
                http_response_code(500);
                echo 'This award is not linked to a customer (quote has no customer). Please set a customer on the quote first.';
                return;
            }

            // --- 3) Load award lines (for invoice items and subtotal fallback) ---
            $lines = $this->rows(
                "SELECT *
                   FROM biz_award_lines
                  WHERE org_id = ? AND award_id = ?
                  ORDER BY line_no ASC, id ASC",
                [$orgId, $awardId]
            );

            // --- 4) Compute header totals from award (with sensible fallbacks) ---
            $subtotal       = (float)($award['subtotal']       ?? 0);
            $discountTotal  = (float)($award['discount_total'] ?? 0);
            $taxTotal       = (float)($award['tax_total']      ?? 0);
            $shippingTotal  = (float)($award['shipping_total'] ?? 0);
            $roundingAdjust = (float)($award['rounding_adjust'] ?? 0);
            $grandTotal     = (float)($award['grand_total']    ?? 0);

            if ($subtotal <= 0 && $lines) {
                $sum = 0.0;
                foreach ($lines as $ln) {
                    $sum += (float)($ln['line_total'] ?? 0);
                }
                $subtotal = $sum;
            }

            if ($grandTotal <= 0) {
                $grandTotal = $subtotal - $discountTotal + $taxTotal + $shippingTotal + $roundingAdjust;
            }

            $currency = (string)($award['currency'] ?? 'BDT');

            $date = (string)($award['award_date'] ?? $award['date'] ?? date('Y-m-d'));
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
                $date = date('Y-m-d');
            }

            $dueObj = new \DateTimeImmutable($date);
            $due    = $dueObj->modify('+7 days')->format('Y-m-d');

            // --- 5) Generate invoice number ---
            $invoiceNo = $this->generateInvoiceNo($pdo, $orgId, $date);

            // --- 6) Build meta_json to carry free-form customer/award info ---
            $awardMeta = [];
            if (!empty($award['meta_json'])) {
                $tmp = json_decode((string)$award['meta_json'], true);
                if (is_array($tmp)) {
                    $awardMeta = $tmp;
                }
            }

            $meta = array_merge($awardMeta, [
                'source'           => 'award',
                'award_id'         => $awardId,
                'award_no'         => $award['award_no'] ?? null,
                'quote_id'         => $quoteId ?: null,
                'customer_name'    => $award['customer_name']   ?? ($award['customer_name']   ?? null),
                'customer_contact' => $award['customer_contact'] ?? null,
                'customer_ref'     => $award['customer_ref']     ?? null,
            ]);

            $metaJson = json_encode($meta);

            $externalRef = trim((string)($award['external_ref'] ?? ''));

            $balanceDue = $grandTotal; // nothing paid yet

            // --- 7) Insert into biz_invoices (NO customer_name column!) ---
            $pdo->beginTransaction();

            $this->exec(
                "INSERT INTO biz_invoices (
                     org_id,
                     customer_id,
                     order_id,
                     quote_id,
                     award_id,
                     invoice_no,
                     external_ref,
                     invoice_type,
                     status,
                     date,
                     due_date,
                     currency,
                     exchange_rate,
                     subtotal,
                     discount_total,
                     tax_total,
                     vat_total,
                     shipping_total,
                     rounding_adjust,
                     grand_total,
                     paid_total,
                     balance_due,
                     notes_internal,
                     notes_external,
                     meta_json
                 ) VALUES (
                     ?, ?, ?, ?, ?,
                     ?, ?, ?, ?, ?,
                     ?, ?, ?, ?, ?,
                     ?, ?, ?, ?, ?,
                     ?, ?, ?, ?, ?
                 )",
                [
                    $orgId,
                    $customerId,
                    null,                        // order_id
                    $quoteId ?: null,
                    $awardId,
                    $invoiceNo,
                    $externalRef !== '' ? $externalRef : null,
                    'standard',                  // invoice_type
                    'draft',                     // status
                    $date,
                    $due,
                    $currency,
                    1.0,                         // exchange_rate
                    $subtotal,
                    $discountTotal,
                    $taxTotal,
                    0.0,                         // vat_total (not used yet)
                    $shippingTotal,
                    $roundingAdjust,
                    $grandTotal,
                    0.0,                         // paid_total
                    $balanceDue,
                    null,                        // notes_internal
                    null,                        // notes_external
                    $metaJson,
                ]
            );

            $invoiceId = (int)$pdo->lastInsertId();

            // --- 8) Copy award lines → biz_invoice_items (if table exists) ---
if ($this->hasTable($pdo, 'biz_invoice_items') && $lines) {
    $sqlItem = "
        INSERT INTO biz_invoice_items (
            org_id,
            invoice_id,
            award_line_id,
            line_no,
            kind,
            item_id,
            item_name,
            item_code,
            description,
            qty,
            unit,
            unit_price,
            discount_pct,
            tax_pct,
            line_subtotal,
            discount_amount,
            tax_amount,
            line_total,
            meta_json
        ) VALUES (
            ?, ?, ?,      -- org_id, invoice_id, award_line_id
            ?, ?, ?,      -- line_no, kind, item_id
            ?, ?, ?,      -- item_name, item_code, description
            ?, ?, ?,      -- qty, unit, unit_price
            ?, ?, ?,      -- discount_pct, tax_pct, line_subtotal
            ?, ?, ?, ?    -- discount_amount, tax_amount, line_total, meta_json
        )
    ";

    foreach ($lines as $ln) {
        $awardLineId = (int)$ln['id'];
        $lineNo      = (int)($ln['line_no'] ?? $awardLineId);

        $itemId   = $ln['item_id']   ?? $ln['product_id']   ?? null;
        $itemName = $ln['item_name'] ?? $ln['product_name'] ?? '';
        if ($itemName === '') {
            $itemName = (string)($ln['description'] ?? ('Line '.$lineNo));
        }
        $itemCode = $ln['item_code'] ?? $ln['product_code'] ?? null;

        $qty       = (float)($ln['qty']          ?? 0);
        $unit      = (string)($ln['unit']        ?? 'pcs');
        $unitPrice = (float)($ln['unit_price']   ?? 0);
        $discPct   = (float)($ln['discount_pct'] ?? 0);
        $taxPct    = 0.0;

        $lineSubtotal = $qty * $unitPrice;
        $discAmount   = $discPct !== 0.0 ? round($lineSubtotal * $discPct / 100, 2) : 0.0;
        $taxAmount    = 0.0;
        $lineTotal    = $lineSubtotal - $discAmount + $taxAmount;

        $this->exec($sqlItem, [
            $orgId,                     // org_id
            $invoiceId,                 // invoice_id
            $awardLineId,               // award_line_id
            $lineNo,                    // line_no
            $ln['kind'] ?? 'item',      // kind
            $itemId,                    // item_id
            $itemName,                  // item_name
            $itemCode,                  // item_code
            $ln['description'] ?? null, // description
            $qty,                       // qty
            $unit,                      // unit
            $unitPrice,                 // unit_price
            $discPct,                   // discount_pct
            $taxPct,                    // tax_pct
            $lineSubtotal,              // line_subtotal
            $discAmount,                // discount_amount
            $taxAmount,                 // tax_amount
            $lineTotal,                 // line_total
            $ln['meta_json'] ?? null,   // meta_json
        ]);
    }
}

            // --- 9) Link invoice back to award ---
            $this->exec(
                "UPDATE biz_awards
                    SET invoice_id = ?
                  WHERE org_id = ? AND id = ?",
                [$invoiceId, $orgId, $awardId]
            );

            $pdo->commit();

            // --- 10) Redirect to invoice show page ---
            $this->redirect(rtrim($moduleBase, '/') . '/invoices/' . $invoiceId);

        } catch (Throwable $e) {
            if ($pdo && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $this->oops('Create invoice from award failed', $e);
        }
    }
  

    /* ============================================================
     * Helpers: number generators
     * ========================================================== */

    /**
     * Simple PO number generator: PO-YYYY-0001 style.
     */
    private function generatePoNo(PDO $pdo, int $orgId): string
    {
        $year   = date('Y');
        $prefix = 'PO-' . $year . '-';

        $last = $this->val(
            "SELECT po_no
               FROM biz_purchase_orders
              WHERE org_id = ? AND po_no LIKE ?
              ORDER BY po_no DESC
              LIMIT 1",
            [$orgId, $prefix . '%']
        );

        $next = 1;
        if ($last && preg_match('/^' . preg_quote($prefix, '/') . '(\d{4})$/', (string)$last, $m)) {
            $next = (int)$m[1] + 1;
        }

        return $prefix . str_pad((string)$next, 4, '0', STR_PAD_LEFT);
    }
  
  
  	/**
     * Simple invoice number generator: INV-YYYY-00001 style.
     */
    private function generateInvoiceNo(PDO $pdo, int $orgId, string $date): string
    {
        $year = substr($date, 0, 4);
        if (!ctype_digit($year)) {
            $year = date('Y');
        }

        $prefix = 'INV-' . $year . '-';

        $last = $this->val(
            "SELECT invoice_no
               FROM biz_invoices
              WHERE org_id = ? AND invoice_no LIKE ?
              ORDER BY invoice_no DESC
              LIMIT 1",
            [$orgId, $prefix.'%']
        );

        $next = 1;
        if ($last && preg_match('/^'.preg_quote($prefix, '/').'(\d{5})$/', (string)$last, $m)) {
            $next = (int)$m[1] + 1;
        }

        return $prefix . str_pad((string)$next, 5, '0', STR_PAD_LEFT);
    }
  
  	/* ============================================================
     * Local helper: check if table exists (robust)
     * ========================================================== */
    private function hasTable(PDO $pdo, string $table): bool
    {
        try {
            $table = trim($table);
            if ($table === '') {
                return false;
            }

            // Use information_schema instead of "SHOW TABLES LIKE ?"
            $sql = "
                SELECT 1
                  FROM information_schema.TABLES
                 WHERE TABLE_SCHEMA = DATABASE()
                   AND TABLE_NAME   = :t
                 LIMIT 1
            ";

            $stmt = $pdo->prepare($sql);
            $stmt->execute([':t' => $table]);
            $found = $stmt->fetchColumn();

            return (bool)$found;
        } catch (\Throwable $e) {
            // If this check itself fails, do NOT block the page –
            // assume table exists and let normal queries throw if not.
            return true;
        }
    }
  
  

    /**
     * Award number generator: A-YYYY-00001 style.
     */
    private function generateAwardNo(PDO $pdo, int $orgId, string $date): string
    {
        $year = substr($date, 0, 4);
        if (!ctype_digit($year)) {
            $year = date('Y');
        }

        $prefix = 'A-' . $year . '-';

        $last = $this->val(
            "SELECT award_no
               FROM biz_awards
              WHERE org_id = ? AND award_no LIKE ?
              ORDER BY award_no DESC
              LIMIT 1",
            [$orgId, $prefix . '%']
        );

        $next = 1;
        if ($last && preg_match('/^' . preg_quote($prefix, '/') . '(\d{5})$/', (string)$last, $m)) {
            $next = (int)$m[1] + 1;
        }

        return $prefix . str_pad((string)$next, 5, '0', STR_PAD_LEFT);
    }
}