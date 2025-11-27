<?php
declare(strict_types=1);

/**
 * BizFlow — Invoice PDF (A4, Dompdf-friendly, repeated header)
 *
 * Expects (but does NOT strictly require):
 *   - array $org
 *   - array $invoice
 *   - array $items
 *   - array $identity (optional: name, address, phone, email)
 *   - array $logo     (optional: url / data_url)
 */

$h = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');

/* ===============================================================
 * SEGMENT 0: Base arrays with safe fallbacks
 * ============================================================= */
$org      = $org      ?? [];
$invoice  = $invoice  ?? [];
$items    = $items    ?? [];
$identity = $identity ?? [];
$logoArr  = $logo     ?? [];

$orgName  = trim((string)($org['name'] ?? ''));
$orgId    = (int)($org['id'] ?? 0);

/* ===============================================================
 * SEGMENT 1: Identity resolution
 *   - Priority:
 *       1) $identity passed from controller
 *       2) modules/bizflow/Assets/settings/org_{id}/identity.json
 *       3) $org record
 *       4) generic safe defaults
 * ============================================================= */
$identityArr = [];

// (1) If controller already passed an identity array, use it.
if (!empty($identity) && is_array($identity)) {
    $identityArr = $identity;
}
// (2) Otherwise, try filesystem identity.json (same pattern as Quote PDF)
elseif ($orgId > 0) {
    $settingsBase = dirname(__DIR__, 2) . '/Assets/settings';
    $idDir        = $settingsBase . '/org_' . $orgId;
    $idFile       = $idDir . '/identity.json';

    if (is_file($idFile)) {
        $raw = @file_get_contents($idFile);
        $data = json_decode((string)$raw, true);
        if (is_array($data)) {
            $identityArr = $data;
        }
    }
}

// (3) & (4) Identity fallbacks → org → generic
$idName    = trim((string)($identityArr['name']    ?? ($org['name']    ?? '')));
$idAddress = trim((string)($identityArr['address'] ?? ($org['address'] ?? '')));
$idPhone   = trim((string)($identityArr['phone']   ?? ($org['phone']   ?? '')));
$idEmail   = trim((string)($identityArr['email']   ?? ($org['email']   ?? '')));

/* ===============================================================
 * SEGMENT 2: Logo resolution
 *   - Priority:
 *       1) $logo['data_url'] (controller-prepared)
 *       2) $logo['url'] (controller-prepared)
 *       3) modules/bizflow/Assets/brand/logo/org_{id}/logo.{ext}
 *          → embedded as data: URL for Dompdf
 *       4) Global KlinFlow mark /assets/brand/logo.png
 * ============================================================= */
$logoUrl = '';

// (1) Prefer explicit data_url
if (!empty($logoArr['data_url'])) {
    $logoUrl = (string)$logoArr['data_url'];
}
// (2) Next, any explicit URL passed by controller
elseif (!empty($logoArr['url'])) {
    $logoUrl = (string)$logoArr['url'];
}

// (3) If still empty and org id is known, read file from module Assets
if ($logoUrl === '' && $orgId > 0) {
    $logoBaseFs = dirname(__DIR__, 2) . '/Assets/brand/logo';
    $orgKey     = 'org_' . $orgId;
    $candidates = ['png', 'jpg', 'jpeg', 'webp', 'svg'];

    foreach ($candidates as $ext) {
        $fsPath = $logoBaseFs . '/' . $orgKey . '/logo.' . $ext;
        if (!is_file($fsPath)) {
            continue;
        }

        $raw = @file_get_contents($fsPath);
        if ($raw === false) {
            continue;
        }

        $mime = 'image/png';
        $e    = strtolower($ext);
        if ($e === 'jpg' || $e === 'jpeg') {
            $mime = 'image/jpeg';
        } elseif ($e === 'webp') {
            $mime = 'image/webp';
        } elseif ($e === 'svg') {
            $mime = 'image/svg+xml';
        }

        // Embed as data URL so Dompdf does not need HTTP access
        $logoUrl = 'data:' . $mime . ';base64,' . base64_encode($raw);
        break;
    }
}

// (4) Final fallback → global KlinFlow logo
if ($logoUrl === '') {
    $logoUrl = '/assets/brand/logo.png';
}

/* ===============================================================
 * SEGMENT 3: Invoice core fields + meta
 * ============================================================= */
$id        = (int)($invoice['id'] ?? 0);
$invNo     = trim((string)($invoice['invoice_no'] ?? ''));
$status    = trim((string)($invoice['status'] ?? 'draft'));
$date      = (string)($invoice['date'] ?? '');
$dueDate   = (string)($invoice['due_date'] ?? '');
$currency  = (string)($invoice['currency'] ?? 'BDT');

$subtotal    = (float)($invoice['subtotal']        ?? 0);
$discountTot = (float)($invoice['discount_total']  ?? 0);
$taxTot      = (float)($invoice['tax_total']       ?? 0);
$shippingTot = (float)($invoice['shipping_total']  ?? 0);
$grandTot    = (float)($invoice['grand_total']     ?? 0);
$roundingAdj = (float)($invoice['rounding_adjust'] ?? 0);

// Meta (customer + terms)
$meta = [];
if (!empty($invoice['meta_json'])) {
    $decoded = json_decode((string)$invoice['meta_json'], true);
    if (is_array($decoded)) {
        $meta = $decoded;
    }
}

$customerName = trim((string)(
    $invoice['customer_name']
    ?? ($meta['customer_name'] ?? '')
));
$custContact  = trim((string)(
    $invoice['customer_contact']
    ?? ($meta['customer_contact'] ?? '')
));
$custRef      = trim((string)(
    $invoice['customer_ref']
    ?? ($meta['customer_ref'] ?? '')
));
$externalRef  = trim((string)(
    $invoice['external_ref']
    ?? ($meta['external_ref'] ?? '')
));

$paymentTs  = trim((string)(
    $meta['payment_terms']
    ?? ($invoice['payment_terms'] ?? '')
));
$deliveryTs = trim((string)(
    $meta['delivery_terms']
    ?? ($invoice['delivery_terms'] ?? '')
));

function in_n($v, int $dec = 2): string {
    return number_format((float)$v, $dec, '.', ',');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title><?= $h($invNo ?: 'Invoice') ?></title>
  <style>
    /* ============================================================
     * SEGMENT 4: Page box + fixed header (Dompdf friendly)
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
      top: -0.9in;   /* move into top margin */
      height: 0.9in; /* must match @page top margin */
    }

    .doc { width: 100%; }

    /* Header (org identity + INVOICE) */
    .header-table { width: 100%; border-collapse: collapse; }
    .header-logo  { width: 60%; vertical-align: top; }
    .header-meta  { width: 40%; vertical-align: top; text-align: right; font-size: 9pt; }
    .org-name     { font-size: 12pt; font-weight: 600; margin-bottom: 2pt; }
    .org-line     { font-size: 8pt;  color: #374151; }
    .inv-title    { font-size: 11pt; font-weight: 600; letter-spacing: 0.18em; text-transform: uppercase; }
    .inv-dates    { margin-top: 3pt; font-size: 8pt; color: #374151; }

    /* Main content */
    .content { margin-top: 10pt; }

    .box-table  { width: 100%; border-collapse: collapse; margin-bottom: 10pt; margin-top: 8pt; }
    .box-cell   { width: 50%; vertical-align: top; padding: 0; }
    .box-title  {
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

    .section-title { font-size: 9pt; font-weight: 600; margin-bottom: 4pt; }

    /* Lines table */
    .lines-table { width: 100%; border-collapse: collapse; font-size: 8.5pt; }
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
    .lines-table thead th:last-child { border-right: 0.3pt solid #e5e7eb; }

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
    .lines-table tbody td:last-child { border-right: 0.3pt solid #e5e7eb; }

    .text-right { text-align: right; }

    .item-name { font-weight: 600; font-size: 8.5pt; }
    .item-code { font-size: 7.2pt; color: #6b7280; }
    .spec-text { font-size: 7.5pt; color: #374151; white-space: pre-line; text-align: justify; }

    /* Totals */
    .totals-wrap  { margin-top: 8pt; width: 100%; }
    .totals-note  { width: 55%; font-size: 7.5pt; color: #6b7280; vertical-align: top; }
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

    /* Terms & footer */
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
    .terms-col  { display: table-cell; width: 50%; padding: 6pt; vertical-align: top; }
    .terms-title { font-weight: 600; margin-bottom: 3pt; }
    .terms-text  { font-size: 8pt; color: #374151; white-space: pre-line; }

    .footer       { margin-top: 14pt; font-size: 8pt; }
    .footer-table { width: 100%; border-collapse: collapse; }
    .footer-sign  { width: 50%; vertical-align: top; }
    .footer-decl  { width: 50%; vertical-align: top; text-align: right; }
    .sign-label   { font-weight: 600; margin-bottom: 18pt; }
    .sign-line    { border-top: 0.4pt solid #9ca3af; width: 160pt; margin-bottom: 2pt; }
    .decl-title   { font-weight: 600; margin-bottom: 2pt; }
  </style>
</head>
<body>

<!-- ============================================================
     SEGMENT 5: Fixed header (repeats on every page)
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
        <div class="inv-title">INVOICE</div>
        <div class="inv-dates">
          <div><strong>Invoice date:</strong> <?= $date ? $h($date) : '—' ?></div>
          <div><strong>Due date:</strong> <?= $dueDate ? $h($dueDate) : '—' ?></div>
        </div>
      </td>
    </tr>
  </table>
</div>

<!-- ============================================================
     SEGMENT 6: Main document body
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
            <div><strong>Invoice no:</strong> <?= $h($invNo ?: '—') ?></div>
            <div><strong>Status:</strong> <?= $h(ucfirst($status)) ?></div>
            <div><strong>Invoice date:</strong> <?= $date ? $h($date) : '—' ?></div>
            <div><strong>Due date:</strong> <?= $dueDate ? $h($dueDate) : '—' ?></div>
            <div><strong>Currency:</strong> <?= $h($currency ?: 'BDT') ?></div>
          </div>
        </td>
      </tr>
    </table>

    <!-- Invoice items -->
    <div class="section-title">Invoice items</div>

    <?php if (!$items): ?>
      <div style="font-size:8.5pt; color:#6b7280; margin-bottom:8pt;">
        No line items have been stored yet for this invoice. Header totals below come from what was
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
        <?php foreach ($items as $i => $ln): ?>
          <?php
          $rawName     = trim((string)($ln['item_name'] ?? $ln['product_name'] ?? ''));
          $productCode = trim((string)($ln['item_code'] ?? $ln['product_code'] ?? ''));
          $lnDesc      = trim((string)($ln['description'] ?? ''));
          $lnQty       = (float)($ln['qty'] ?? 0);
          $lnUnit      = trim((string)($ln['unit'] ?? 'pcs'));
          $lnPrice     = (float)($ln['unit_price'] ?? 0);
          $lnDiscPct   = (float)($ln['discount_pct'] ?? 0);
          $lnTotal     = (float)($ln['line_total'] ?? ($lnQty * $lnPrice));
          $lineNo      = (int)($ln['line_no'] ?? ($i + 1));

          $displayName = $rawName !== '' ? $rawName : ('Line '.$lineNo);
          ?>
          <tr>
            <td><?= $lineNo ?></td>
            <td>
              <div class="item-name"><?= $h($displayName) ?></div>
              <?php if ($productCode !== ''): ?>
                <div class="item-code">Code: <?= $h($productCode) ?></div>
              <?php endif; ?>
            </td>
            <td>
              <div class="spec-text">
                <?= $h($lnDesc ?: '—') ?>
              </div>
            </td>
            <td class="text-right"><?= in_n($lnQty, 3) ?></td>
            <td><?= $h($lnUnit) ?></td>
            <td class="text-right"><?= in_n($lnPrice) ?></td>
            <td class="text-right"><?= in_n($lnDiscPct) ?></td>
            <td class="text-right"><?= in_n($lnTotal) ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>

    <!-- Totals -->
    <table class="totals-wrap">
      <tr>
        <td class="totals-note">
          Totals are expressed in BDT. For now, invoices are kept simple without multi-currency breakdown;
          this can be extended later if needed.
        </td>
        <td class="totals-table">
          <table>
            <tr>
              <td class="totals-label">Subtotal</td>
              <td class="text-right"><?= in_n($subtotal) ?></td>
            </tr>
            <tr>
              <td style="font-size:8pt; color:#6b7280;">Discounts</td>
              <td class="text-right" style="font-size:8pt; color:#6b7280;">- <?= in_n($discountTot) ?></td>
            </tr>
            <tr>
              <td style="font-size:8pt; color:#6b7280;">Tax total</td>
              <td class="text-right" style="font-size:8pt; color:#6b7280;"><?= in_n($taxTot) ?></td>
            </tr>
            <tr>
              <td style="font-size:8pt; color:#6b7280;">Shipping</td>
              <td class="text-right" style="font-size:8pt; color:#6b7280;"><?= in_n($shippingTot) ?></td>
            </tr>
            <tr>
              <td style="font-size:8pt; color:#6b7280;">Rounding adj.</td>
              <td class="text-right" style="font-size:8pt; color:#6b7280;"><?= in_n($roundingAdj) ?></td>
            </tr>
            <tr>
              <td class="totals-grand">Grand total (BDT)</td>
              <td class="totals-grand text-right"><?= in_n($grandTot) ?></td>
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
            <?= $h($paymentTs !== '' ? $paymentTs : 'Custom payment terms can be filled on the invoice header.') ?>
          </div>
        </div>
        <div class="terms-col">
          <div class="terms-title">Delivery terms</div>
          <div class="terms-text">
            <?= $h($deliveryTs !== '' ? $deliveryTs : 'Add delivery or shipment terms to keep client expectations clear.') ?>
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
              This invoice has been generated from KlinFlow for
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