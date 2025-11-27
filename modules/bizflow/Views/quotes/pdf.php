<?php
/**
 * BizFlow — Quote PDF (A4, Dompdf-friendly, repeated header)
 *
 * Expects:
 * - array  $org
 * - array  $quote
 * - array  $lines
 * - array  $identity (name, address, phone, email)
 * - array  $logo     (url / data_url)
 */

$h = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');

$org         = $org   ?? [];
$quote       = $quote ?? [];
$lines       = $lines ?? [];
$identityArr = $identity ?? [];
$logoArr     = $logo ?? [];

$orgName = trim((string)($org['name'] ?? ''));

// ---------- Logo + identity ----------
$logoUrl = (string)($logoArr['url'] ?? '');
if ($logoUrl === '') {
    $logoUrl = '/assets/brand/logo.png';
}

$idName    = trim((string)($identityArr['name']    ?? ($org['name']    ?? '')));
$idAddress = trim((string)($identityArr['address'] ?? ($org['address'] ?? '')));
$idPhone   = trim((string)($identityArr['phone']   ?? ($org['phone']   ?? '')));
$idEmail   = trim((string)($identityArr['email']   ?? ($org['email']   ?? '')));

// ---------- Quote core fields ----------
$id         = (int)($quote['id'] ?? 0);
$quoteNo    = trim((string)($quote['quote_no'] ?? ''));
$status     = trim((string)($quote['status'] ?? 'draft'));
$date       = (string)($quote['date'] ?? '');
$validUntil = (string)($quote['valid_until'] ?? '');
$currency   = (string)($quote['currency'] ?? 'BDT');

$subtotal    = (float)($quote['subtotal'] ?? 0);
$discountTot = (float)($quote['discount_total'] ?? 0);
$taxTot      = (float)($quote['tax_total'] ?? 0);
$vatTot      = (float)($quote['vat_total'] ?? 0);
$shipTot     = (float)($quote['shipping_total'] ?? 0);
$grandTot    = (float)($quote['grand_total'] ?? 0);

$customerName = trim((string)($quote['customer_name'] ?? ''));
$externalRef  = trim((string)($quote['external_ref'] ?? ''));

// ---------- Meta ----------
$meta = [];
if (!empty($quote['meta_json'])) {
    $decoded = json_decode((string)$quote['meta_json'], true);
    if (is_array($decoded)) {
        $meta = $decoded;
    }
}
$uiType      = (string)($meta['ui_type'] ?? 'mixed');
$vatPercent  = (float)($meta['vat_percent'] ?? 0.0);
$custContact = trim((string)($meta['customer_contact'] ?? ''));
$custRef     = trim((string)($meta['customer_reference'] ?? ''));
$paymentTs   = trim((string)($meta['payment_terms'] ?? ''));
$deliveryTs  = trim((string)($meta['delivery_terms'] ?? ''));

// ---------- Line items: prefer $lines from DB, fallback to meta["rows"] ----------
$lineItems = is_array($lines) ? $lines : [];

if (!$lineItems && isset($meta['rows']) && is_array($meta['rows'])) {
    foreach ($meta['rows'] as $idx => $row) {
        if (!is_array($row)) {
            continue;
        }
        $lineItems[] = [
            'line_no'       => $row['line_no']      ?? ($idx + 1),
            'kind'          => $row['kind']         ?? 'item',
            'product_name'  => $row['product_name'] ?? ($row['name'] ?? ''),
            'product_code'  => $row['product_code'] ?? ($row['code'] ?? ''),
            'name'          => $row['name']         ?? '',
            'description'   => $row['description']  ?? '',
            'qty'           => $row['qty']          ?? 0,
            'unit'          => $row['unit']         ?? 'pcs',
            'unit_price'    => $row['unit_price']   ?? 0,
            'discount_pct'  => $row['discount_pct'] ?? 0,
            'line_total'    => $row['line_total']   ?? ($row['total'] ?? 0),
        ];
    }
}

function qn($v, int $dec = 2): string {
    return number_format((float)$v, $dec, '.', ',');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title><?= $h($quoteNo ?: 'Quotation') ?></title>
  <style>
    /* ============================================================
     * Page box + fixed header
     *  - Margin top sized to header height so no overlap
     *  - Header sits in the margin area and repeats on each page
     * ========================================================== */
    @page {
      size: A4;
      margin: 0.9in 0.5in 0.5in 0.5in; /* top, right, bottom, left */
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
      top: -0.9in;          /* move into top margin */
      height: 0.9in;        /* must match @page top margin */
    }

    .doc {
      width: 100%;
    }

    /* ============================================================
     * Header (org identity + QUOTATION)
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
    .quote-title {
      font-size: 11pt;
      font-weight: 600;
      letter-spacing: 0.18em;
      text-transform: uppercase;
    }
    .quote-dates {
      margin-top: 3pt;
      font-size: 8pt;
      color: #374151;
    }

    /* ============================================================
     * Main content below header
     * ========================================================== */
    .content {
      margin-top: 10pt; /* small breathing room under header */
    }

    /* Customer + summary boxes */
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
    .lines-table thead th.col-item  { width: 95pt; }  /* smaller item col */
    .lines-table thead th.col-spec  { width: auto; }  /* spec takes remaining */
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
      text-align: justify;   /* <— specification justified */
    }
    .badge-service {
      display: inline-block;
      border-radius: 999px;
      border: 0.3pt solid #38bdf8;
      padding: 1pt 4pt;
      font-size: 7pt;
      color: #0369a1;
      margin-top: 1pt;
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
     * Terms & conditions (keep block together if possible)
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
     Fixed header (repeats on every page)
     ========================================================== -->
<div id="page-header">
  <table class="header-table">
    <tr>
      <td class="header-logo">
        <table>
          <tr>
            <td style="vertical-align: top; padding-right: 6pt;">
              <img src="<?= $h($logoUrl) ?>" alt="Logo" style="height:26pt; max-width:110pt;">
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
        <div class="quote-title">QUOTATION</div>
        <div class="quote-dates">
          <div><strong>Quote date:</strong> <?= $date ? $h($date) : '—' ?></div>
          <div><strong>Valid until:</strong> <?= $validUntil ? $h($validUntil) : '—' ?></div>
        </div>
      </td>
    </tr>
  </table>
</div>

<!-- ============================================================
     Main document body
     ========================================================== -->
<div class="doc">
  <div class="content">

    <!-- Customer + Summary -->
    <table class="box-table">
      <tr>
        <td class="box-cell">
          <div class="box-title">Customer</div>
          <div class="box-body">
            <div><strong>Name:</strong> <?= $h($customerName ?: '—') ?></div>
            <div><strong>Contact:</strong> <?= $h($custContact ?: '—') ?></div>
            <div><strong>Reference:</strong> <?= $h(($custRef ?: $externalRef) ?: '—') ?></div>
          </div>
        </td>
        <td class="box-cell">
          <div class="box-title">Summary</div>
          <div class="box-body">
            <div><strong>Quote no:</strong> <?= $h($quoteNo ?: '—') ?></div>
            <div><strong>Status:</strong> <?= $h(ucfirst($status)) ?> (<?= $h(ucfirst($uiType)) ?>)</div>
            <div><strong>Quote date:</strong> <?= $date ? $h($date) : '—' ?></div>
            <div><strong>Valid until:</strong> <?= $h($validUntil ?: '—') ?></div>
            <div><strong>Currency:</strong> <?= $h($currency ?: 'BDT') ?></div>
            <?php if ($vatPercent > 0): ?>
              <div><strong>VAT %:</strong> <?= qn($vatPercent, 2) ?>%</div>
            <?php endif; ?>
          </div>
        </td>
      </tr>
    </table>

    <!-- Quote lines -->
    <div class="section-title">Quote lines</div>

    <?php if (!$lineItems): ?>
      <div style="font-size:8.5pt; color:#6b7280; margin-bottom:8pt;">
        No line items have been stored yet for this quote. Header totals below come from what was
        submitted on the create screen.
      </div>
    <?php else: ?>
      <table class="lines-table">
        <thead>
        <tr>
          <th class="col-num">#</th>
          <th class="col-item">Item</th>
          <th class="col-spec">Key features / specification</th>
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
          $lnKind       = (string)($ln['kind'] ?? 'item');
          $rawName      = trim((string)($ln['name'] ?? ''));
          $productName  = trim((string)($ln['product_name'] ?? ''));
          $productCode  = trim((string)($ln['product_code'] ?? ''));
          $lnDesc       = trim((string)($ln['description'] ?? ''));
          $lnQty        = (float)($ln['qty'] ?? 0);
          $lnUnit       = trim((string)($ln['unit'] ?? 'pcs'));
          $lnPrice      = (float)($ln['unit_price'] ?? 0);
          $lnDiscPct    = (float)($ln['discount_pct'] ?? 0);
          $lnTotal      = (float)($ln['line_total'] ?? 0);
          $lineNo       = (int)($ln['line_no'] ?? ($i + 1));

          $displayName = $productName !== ''
              ? $productName
              : ($rawName !== '' ? $rawName : ('Line ' . $lineNo));
          ?>
          <tr>
            <td><?= $lineNo ?></td>
            <td>
              <div class="item-name"><?= $h($displayName) ?></div>
              <?php if ($productCode !== ''): ?>
                <div class="item-code">Code: <?= $h($productCode) ?></div>
              <?php endif; ?>
              <?php if ($lnKind === 'service'): ?>
                <div class="badge-service">Service</div>
              <?php endif; ?>
            </td>
            <td>
              <div class="spec-text">
                <?= $h($lnDesc ?: '—') ?>
              </div>
            </td>
            <td class="text-right"><?= qn($lnQty, 3) ?></td>
            <td><?= $h($lnUnit) ?></td>
            <td class="text-right"><?= qn($lnPrice) ?></td>
            <td class="text-right"><?= qn($lnDiscPct) ?></td>
            <td class="text-right"><?= qn($lnTotal) ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>

    <!-- Totals -->
    <table class="totals-wrap">
      <tr>
        <td class="totals-note">
          Totals are expressed in BDT. For now, quotes are kept simple without multi-currency breakdown;
          this can be extended later if needed.
        </td>
        <td class="totals-table">
          <table>
            <tr>
              <td class="totals-label">Subtotal</td>
              <td class="text-right"><?= qn($subtotal) ?></td>
            </tr>
            <tr>
              <td style="font-size:8pt; color:#6b7280;">Discounts</td>
              <td class="text-right" style="font-size:8pt; color:#6b7280;">- <?= qn($discountTot) ?></td>
            </tr>
            <tr>
              <td style="font-size:8pt; color:#6b7280;">Tax total</td>
              <td class="text-right" style="font-size:8pt; color:#6b7280;"><?= qn($taxTot) ?></td>
            </tr>
            <tr>
              <td style="font-size:8pt; color:#6b7280;">VAT total</td>
              <td class="text-right" style="font-size:8pt; color:#6b7280;"><?= qn($vatTot) ?></td>
            </tr>
            <tr>
              <td style="font-size:8pt; color:#6b7280;">Shipping</td>
              <td class="text-right" style="font-size:8pt; color:#6b7280;"><?= qn($shipTot) ?></td>
            </tr>
            <tr>
              <td class="totals-grand">Grand total (BDT)</td>
              <td class="totals-grand text-right"><?= qn($grandTot) ?></td>
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
            <?= $h($paymentTs !== '' ? $paymentTs : 'Custom payment terms can be filled on the quote create screen.') ?>
          </div>
        </div>
        <div class="terms-col">
          <div class="terms-title">Delivery terms</div>
          <div class="terms-text">
            <?= $h($deliveryTs !== '' ? $deliveryTs : 'Add delivery terms or notes per quote to keep tender responses clear.') ?>
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
              This quotation has been generated from KlinFlow for
              <?= $h($idName !== '' ? $idName : ($orgName !== '' ? $orgName : 'your organisation')) ?>.
              It is a system-generated document and does not require a handwritten or stamped signature.
            </div>
          </td>
        </tr>
      </table>
    </div>

  </div>
</div>

</body>
</html>