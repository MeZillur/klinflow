<?php
/**
 * BizFlow — Purchase Order PRINT (A4, browser print friendly)
 *
 * Expects:
 * - array $org
 * - array $purchase
 * - array $items OR $lines (controller may send either)
 * - optional array $identity (name, address, phone, email)
 * - optional array $logo     (url / data_url)
 */

$h = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');

$org       = $org       ?? [];
$purchase  = $purchase  ?? [];
// accept both keys from controller: $items or $lines
$rawItems  = $items ?? ($lines ?? []);
$identity  = $identity  ?? [];
$logoArr   = $logo      ?? [];

// ---------- Logo + identity ----------
$orgName = trim((string)($org['name'] ?? ''));

// prefer controller-provided logo, else fall back to global KlinFlow logo
$logoUrl = trim((string)($logoArr['url'] ?? ''));
if ($logoUrl === '') {
    // this path should exist in your global assets pack
    $logoUrl = '/assets/brand/logo.png';
}

$idName    = trim((string)($identity['name']    ?? ($org['name']    ?? '')));
$idAddress = trim((string)($identity['address'] ?? ($org['address'] ?? '')));
$idPhone   = trim((string)($identity['phone']   ?? ($org['phone']   ?? '')));
$idEmail   = trim((string)($identity['email']   ?? ($org['email']   ?? '')));

// ---------- Purchase core fields ----------
$id        = (int)($purchase['id'] ?? 0);
$poNo      = trim((string)($purchase['po_no'] ?? ''));
$status    = trim((string)($purchase['status'] ?? 'draft'));
$date      = (string)($purchase['date'] ?? '');
$currency  = (string)($purchase['currency'] ?? 'BDT');

$subtotal    = (float)($purchase['subtotal'] ?? 0);
$discountTot = (float)($purchase['discount_total'] ?? 0);
$taxTot      = (float)($purchase['tax_total'] ?? 0);
$vatTot      = (float)($purchase['vat_total'] ?? 0);
$shipTot     = (float)($purchase['shipping_total'] ?? 0);
$grandTot    = (float)($purchase['grand_total'] ?? 0);

$supplierName = trim((string)($purchase['supplier_name'] ?? ''));
$externalRef  = trim((string)($purchase['external_ref'] ?? ''));

// ---------- Meta (optional JSON) ----------
$meta = [];
if (!empty($purchase['meta_json'])) {
    $decoded = json_decode((string)$purchase['meta_json'], true);
    if (is_array($decoded)) {
        $meta = $decoded;
    }
}
$uiType         = (string)($meta['ui_type'] ?? ($purchase['purchase_type'] ?? 'mixed'));
$vatPercent     = (float)($meta['vat_percent'] ?? 0.0);
$suppContact    = trim((string)($meta['supplier_contact'] ?? ''));
$suppRef        = trim((string)($meta['supplier_reference'] ?? ''));
$paymentTerms   = trim((string)($meta['payment_terms'] ?? ''));
$deliveryTerms  = trim((string)($meta['delivery_terms'] ?? ''));
$expectedDate   = (string)($purchase['expected_date'] ?? ($meta['eta'] ?? ''));

// ---------- Line items ----------
$lineItems = is_array($rawItems) ? $rawItems : [];

// ---------- Header label for right side ----------
$poLabel = $poNo !== '' ? $poNo : ($id > 0 ? 'PO-' . $id : 'Purchase order');

function pn($v, int $dec = 2): string {
    return number_format((float)$v, $dec, '.', ',');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title><?= $h($poNo !== '' ? $poNo : 'Purchase Order') ?></title>
  <style>
    /* ============================================================
     * Page box + header spacing (browser print)
     * ========================================================== */
    @page {
      size: A4;
      margin: 0.9in 0.5in 0.5in 0.5in;
    }

    * { box-sizing: border-box; }

    body {
      font-family: DejaVu Sans, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
      font-size: 10pt;
      color: #111827;
      margin: 0;
      padding: 0;
    }

    #page-header {
      position: fixed;
      left: 0;
      right: 0;
      top: -0.9in;
      height: 0.9in;
    }

    .doc {
      width: 100%;
    }

    /* ============================================================
     * Header (org identity + PURCHASE ORDER)
     * ========================================================== */
    .header-table {
      width: 100%;
      border-collapse: collapse;
    }
    .header-logo {
      width: 60%;
      vertical-align: top;
    }
    .header-meta {
      width: 40%;
      vertical-align: top;
      text-align: right;
      font-size: 9pt;
    }
    .org-name {
      font-size: 12pt;
      font-weight: 600;
      margin-bottom: 2pt;
    }
    .org-line {
      font-size: 8pt;
      color: #374151;
    }
    .po-title {
      font-size: 11pt;
      font-weight: 600;
      letter-spacing: 0.18em;
      text-transform: uppercase;
    }
    .po-label {
      margin-top: 3pt;
      font-size: 10pt;
      font-weight: 600;
      color: #111827;
    }
    .po-dates {
      margin-top: 3pt;
      font-size: 8pt;
      color: #374151;
      line-height: 1.35;
    }

    /* ============================================================
     * Main content
     * ========================================================== */
    .content {
      margin-top: 10pt;
    }

    .box-table { width: 100%; border-collapse: collapse; margin-bottom: 10pt; margin-top: 8pt; }
    .box-cell { width: 50%; vertical-align: top; padding: 0; }
    .box-title {
      background: #f3f4f6;
      border: 0.3pt solid #e5e7eb;
      border-bottom: none;
      padding: 4pt 6pt;
      font-size: 8pt;
      font-weight: 600;
      text-transform: uppercase;
      color: #4b5563;
    }
    .box-body {
      border: 0.3pt solid #e5e7eb;
      padding: 5pt 6pt;
      font-size: 9pt;
      line-height: 1.3;
    }

    .section-title {
      font-size: 9pt;
      font-weight: 600;
      margin-bottom: 4pt;
    }

    /* ============================================================
     * Lines table
     * ========================================================== */
    .lines-table {
      width: 100%;
      border-collapse: collapse;
      font-size: 8.5pt;
    }
    .lines-table thead th {
      background: #f3f4f6;
      border-top: 0.3pt solid #e5e7eb;
      border-bottom: 0.3pt solid #e5e7eb;
      border-left: 0.3pt solid #e5e7eb;
      padding: 4pt 4pt;
      text-align: left;
      text-transform: uppercase;
      font-size: 7.5pt;
      color: #6b7280;
    }
    .lines-table thead th:last-child {
      border-right: 0.3pt solid #e5e7eb;
    }
    .lines-table thead th.col-num   { width: 18pt; }
    .lines-table thead th.col-item  { width: 95pt; }
    .lines-table thead th.col-spec  { width: auto; }
    .lines-table thead th.col-qty   { width: 38pt; text-align: right; }
    .lines-table thead th.col-unit  { width: 40pt; }
    .lines-table thead th.col-price { width: 65pt; text-align: right; }
    .lines-table thead th.col-disc  { width: 38pt; text-align: right; }
    .lines-table thead th.col-total { width: 72pt; text-align: right; }

    .lines-table tbody td {
      border-bottom: 0.3pt solid #e5e7eb;
      border-left: 0.3pt solid #e5e7eb;
      padding: 3pt 4pt;
      vertical-align: top;
    }
    .lines-table tbody td:last-child {
      border-right: 0.3pt solid #e5e7eb;
    }

    .text-right { text-align: right; }

    .item-name {
      font-weight: 600;
      font-size: 8.5pt;
    }
    .item-code {
      font-size: 7.2pt;
      color: #6b7280;
    }
    .spec-text {
      font-size: 7.5pt;
      color: #374151;
      white-space: pre-line;
      text-align: justify;
    }

    /* ============================================================
     * Totals
     * ========================================================== */
    .totals-wrap { margin-top: 8pt; width: 100%; }
    .totals-note { width: 55%; font-size: 7.5pt; color: #6b7280; vertical-align: top; }
    .totals-table { width: 45%; font-size: 8.5pt; vertical-align: top; }
    .totals-table table { width: 100%; border-collapse: collapse; }
    .totals-table td { padding: 2pt 0; }
    .totals-label { color: #4b5563; }
    .totals-grand {
      border-top: 0.4pt solid #d1d5db;
      padding-top: 3pt;
      margin-top: 2pt;
      font-weight: 600;
      font-size: 9pt;
    }

    /* ============================================================
     * Terms & conditions
     * ========================================================== */
    .terms {
      margin-top: 10pt;
      border: 0.3pt solid #e5e7eb;
      font-size: 8.5pt;
      page-break-inside: avoid;
    }
    .terms-head {
      background: #f3f4f6;
      border-bottom: 0.3pt solid #e5e7eb;
      padding: 4pt 6pt;
      font-size: 8pt;
      font-weight: 600;
      text-transform: uppercase;
      color: #4b5563;
    }
    .terms-body { display: table; width: 100%; }
    .terms-col { display: table-cell; width: 50%; padding: 6pt; vertical-align: top; }
    .terms-title { font-weight: 600; margin-bottom: 3pt; }
    .terms-text { font-size: 8pt; color: #374151; white-space: pre-line; }

    /* ============================================================
     * Footer
     * ========================================================== */
    .footer { margin-top: 14pt; font-size: 8pt; }
    .footer-table { width: 100%; border-collapse: collapse; }
    .footer-sign { width: 50%; vertical-align: top; }
    .footer-decl { width: 50%; vertical-align: top; text-align: right; }
    .sign-label { font-weight: 600; margin-bottom: 18pt; }
    .sign-line { border-top: 0.4pt solid #9ca3af; width: 160pt; margin-bottom: 2pt; }
    .decl-title { font-weight: 600; margin-bottom: 2pt; }
  </style>
</head>
<body>

<!-- ============================================================
     Fixed header
     ========================================================== -->
<div id="page-header">
  <table class="header-table">
    <tr>
      <td class="header-logo">
        <table>
          <tr>
            <td style="vertical-align: top; padding-right: 6pt;">
              <img src="<?= $h($logoUrl) ?>" alt="" style="height:26pt; max-width:110pt;">
            </td>
            <td style="vertical-align: top;">
              <div class="org-name">
                <?= $h($idName !== '' ? $idName : ($orgName !== '' ? $orgName : 'Organisation')) ?>
              </div>
              <?php if ($idAddress !== ''): ?>
                <div class="org-line"><?= $h($idAddress) ?></div>
              <?php endif; ?>
              <div class="org-line">
                <?php if ($idPhone !== ''): ?>
                  Phone: <?= $h($idPhone) ?>
                <?php endif; ?>
                <?php if ($idPhone !== '' && $idEmail !== ''): ?>&nbsp;&nbsp;<?php endif; ?>
                <?php if ($idEmail !== ''): ?>
                  Email: <?= $h($idEmail) ?>
                <?php endif; ?>
              </div>
            </td>
          </tr>
        </table>
      </td>
      <td class="header-meta">
        <div class="po-title">PURCHASE ORDER</div>
        <div class="po-label"><?= $h($poLabel) ?></div>
        <div class="po-dates">
          <div><strong>Status:</strong> <?= $h(ucfirst($status)) ?> (<?= $h(ucfirst($uiType)) ?>)</div>
          <div><strong>PO date:</strong> <?= $date ? $h($date) : '—' ?></div>
          <div><strong>Currency:</strong> <?= $h($currency ?: 'BDT') ?></div>
        </div>
      </td>
    </tr>
  </table>
</div>

<!-- ============================================================
     Main body
     ========================================================== -->
<div class="doc">
  <div class="content">

    <!-- Supplier + Summary -->
    <table class="box-table">
      <tr>
        <td class="box-cell">
          <div class="box-title">Supplier</div>
          <div class="box-body">
            <div><strong>Name:</strong> <?= $h($supplierName ?: '—') ?></div>
            <div><strong>Contact:</strong> <?= $h($suppContact ?: '—') ?></div>
            <div><strong>Supplier ref:</strong> <?= $h(($suppRef ?: $externalRef) ?: '—') ?></div>
          </div>
        </td>
        <td class="box-cell">
          <div class="box-title">Summary</div>
          <div class="box-body">
            <div><strong>PO no:</strong> <?= $h($poNo !== '' ? $poNo : ('PO-' . $id)) ?></div>
            <div><strong>Status:</strong> <?= $h(ucfirst($status)) ?> (<?= $h(ucfirst($uiType)) ?>)</div>
            <div><strong>PO date:</strong> <?= $date ? $h($date) : '—' ?></div>
            <div><strong>Expected date:</strong> <?= $expectedDate ? $h($expectedDate) : '—' ?></div>
            <div><strong>Currency:</strong> <?= $h($currency ?: 'BDT') ?></div>
            <?php if ($vatPercent > 0): ?>
              <div><strong>VAT %:</strong> <?= pn($vatPercent, 2) ?>%</div>
            <?php endif; ?>
          </div>
        </td>
      </tr>
    </table>

    <!-- Purchase lines -->
    <div class="section-title">Purchase order lines</div>

    <?php if (!$lineItems): ?>
      <div style="font-size:8.5pt; color:#6b7280; margin-bottom:8pt;">
        No line items have been stored yet for this purchase.
      </div>
    <?php else: ?>
      <table class="lines-table">
        <thead>
        <tr>
          <th class="col-num">#</th>
          <th class="col-item">Item</th>
          <th class="col-spec">Specification / description</th>
          <th class="col-qty text-right">Qty</th>
          <th class="col-unit">Unit</th>
          <th class="col-price text-right">Unit price</th>
          <th class="col-disc text-right">Disc %</th>
          <th class="col-total text-right">Line total</th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($lineItems as $i => $ln): ?>
          <?php
          // --------------- SAFER FIELD MAPPING ---------------
          $lnKind  = (string)($ln['kind'] ?? 'item');

          // try every likely "name" key we might have
          $rawName = trim((string)(
              $ln['item_name']
              ?? $ln['product_name']
              ?? $ln['name']
              ?? $ln['item_label']
              ?? ''
          ));

          // item code (any common key)
          $itemCode = trim((string)(
              $ln['item_code']
              ?? $ln['product_code']
              ?? $ln['code']
              ?? ''
          ));

          // specification / description
          $lnDesc = trim((string)(
              $ln['description']
              ?? $ln['spec']
              ?? $ln['details']
              ?? ''
          ));

          // qty + unit
          $lnQty   = (float)($ln['qty'] ?? $ln['qty_ordered'] ?? $ln['quantity'] ?? 0);
          $lnUnit  = trim((string)($ln['unit'] ?? $ln['uom'] ?? 'pcs'));

          // price + discount + total
          $lnPrice   = (float)($ln['unit_price'] ?? $ln['price'] ?? 0);
          $lnDiscPct = (float)($ln['discount_pct'] ?? $ln['disc_pct'] ?? $ln['discount_percent'] ?? 0);
          $lnTotal   = (float)($ln['line_total'] ?? $ln['total'] ?? 0);

          $lineNo = (int)($ln['line_no'] ?? ($i + 1));

          $displayName = $rawName !== '' ? $rawName : ('Line ' . $lineNo);
          ?>
          <tr>
            <td><?= $lineNo ?></td>
            <td>
              <div class="item-name"><?= $h($displayName) ?></div>
              <?php if ($itemCode !== ''): ?>
                <div class="item-code">Code: <?= $h($itemCode) ?></div>
              <?php endif; ?>
            </td>
            <td>
              <div class="spec-text">
                <?= $h($lnDesc !== '' ? $lnDesc : '—') ?>
              </div>
            </td>
            <td class="text-right"><?= pn($lnQty, 3) ?></td>
            <td><?= $h($lnUnit) ?></td>
            <td class="text-right"><?= pn($lnPrice) ?></td>
            <td class="text-right"><?= pn($lnDiscPct) ?></td>
            <td class="text-right"><?= pn($lnTotal) ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>

    <!-- Totals -->
    <table class="totals-wrap">
      <tr>
        <td class="totals-note">
          All amounts are in BDT. This purchase order reflects the items and pricing
          agreed with your vendor and accounting settings. Taxes or logistics charges
          can be captured via GRN and the posting engine.
        </td>
        <td class="totals-table">
          <table>
            <tr>
              <td class="totals-label">Subtotal</td>
              <td class="text-right"><?= pn($subtotal) ?></td>
            </tr>
            <tr>
              <td style="font-size:8pt; color:#6b7280;">Discounts</td>
              <td class="text-right" style="font-size:8pt; color:#6b7280;">- <?= pn($discountTot) ?></td>
            </tr>
            <tr>
              <td style="font-size:8pt; color:#6b7280;">Tax total</td>
              <td class="text-right" style="font-size:8pt; color:#6b7280;"><?= pn($taxTot) ?></td>
            </tr>
            <tr>
              <td style="font-size:8pt; color:#6b7280;">VAT total</td>
              <td class="text-right" style="font-size:8pt; color:#6b7280;"><?= pn($vatTot) ?></td>
            </tr>
            <tr>
              <td style="font-size:8pt; color:#6b7280;">Shipping</td>
              <td class="text-right" style="font-size:8pt; color:#6b7280;"><?= pn($shipTot) ?></td>
            </tr>
            <tr>
              <td class="totals-grand">Grand total (BDT)</td>
              <td class="totals-grand text-right"><?= pn($grandTot) ?></td>
            </tr>
          </table>
        </td>
      </tr>
    </table>

    <!-- Terms & conditions -->
    <div class="terms">
      <div class="terms-head">Terms &amp; conditions</div>
      <div class="terms-body">
        <div class="terms-col">
          <div class="terms-title">Payment terms</div>
          <div class="terms-text">
            <?= $h($paymentTerms !== '' ? $paymentTerms : 'Payment terms for this purchase order can be defined per supplier or per PO.') ?>
          </div>
        </div>
        <div class="terms-col">
          <div class="terms-title">Delivery terms</div>
          <div class="terms-text">
            <?= $h($deliveryTerms !== '' ? $deliveryTerms : 'Add delivery terms (location, delivery address, lead time) to keep your offer clear.') ?>
          </div>
        </div>
      </div>
    </div>

    <!-- Footer -->
    <div class="footer">
      <table class="footer-table">
        <tr>
          <td class="footer-sign">
            <div class="sign-label">Authorised signature</div>
            <div class="sign-line"></div>
            <div>For <?= $h($idName !== '' ? $idName : ($orgName !== '' ? $orgName : '')) ?></div>
          </td>
          <td class="footer-decl">
            <div class="decl-title">Declaration</div>
            <div>
              This purchase order has been generated from KlinFlow for
              <?= $h($idName !== '' ? $idName : ($orgName !== '' ? $orgName : 'your organisation')) ?>.
              It is a system-generated document and does not require a handwritten or stamped signature.
            </div>
          </td>
        </tr>
      </table>
    </div>

  </div>
</div>

<script>
  // Auto-open browser print dialog when this page is loaded
  (function () {
    if (typeof window === 'undefined') return;
    window.addEventListener('load', function () {
      try { window.print(); } catch (e) {}
    });
  })();
</script>

</body>
</html>