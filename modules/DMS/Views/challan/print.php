<?php
declare(strict_types=1);

/**
 * DMS — Delivery Challan PRINT (A4, browser print friendly)
 *
 * Expects (from controller):
 * - array $org
 * - array $challan
 * - array $items
 * - optional array $identity (name, address, phone, email)
 * - optional array $logo (url and/or data_url)
 */

$h        = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
$org      = isset($org) && is_array($org) ? $org : [];
$challan  = isset($challan) && is_array($challan) ? $challan : [];
$items    = isset($items) && is_array($items) ? $items : [];
$identity = isset($identity) && is_array($identity) ? $identity : [];
$logoArr  = isset($logo) && is_array($logo) ? $logo : [];

/* ---------- Helper: format number ---------- */
if (!function_exists('pn')) {
    function pn($v, int $dec = 2): string {
        return number_format((float)$v, $dec, '.', ',');
    }
}

/* ---------- Identity + logo ---------- */

// Fallback to org if identity missing
$idName    = trim((string)($identity['name']    ?? ($org['name']    ?? 'Organisation')));
$idAddress = trim((string)($identity['address'] ?? ($org['address'] ?? '')));
$idPhone   = trim((string)($identity['phone']   ?? ($org['phone']   ?? '')));
$idEmail   = trim((string)($identity['email']   ?? ($org['email']   ?? '')));

// Prefer controller-provided data_url, then url, else KlinFlow default
$logoData = trim((string)($logoArr['data_url'] ?? ''));
$logoUrl  = $logoData !== '' ? $logoData : trim((string)($logoArr['url'] ?? ''));
if ($logoUrl === '') {
    // very last fallback
    $logoUrl = '/assets/brand/logo.png';
}

/* ---------- Challan core fields ---------- */

$chId        = (int)($challan['id'] ?? 0);
$chNo        = trim((string)($challan['challan_no'] ?? ''));
$chDate      = (string)($challan['challan_date'] ?? '');
$statusRaw   = strtolower((string)($challan['status'] ?? 'ready'));
$statusLabel = ucfirst($statusRaw ?: 'Ready');

// WARNING: dms_challans does not have invoice_no; we read it only if controller sets it.
$invoiceNo   = trim((string)($challan['invoice_no'] ?? ''));

$customer    = trim((string)($challan['customer_name'] ?? ''));
$shipToName  = trim((string)($challan['ship_to_name'] ?? $customer));
$shipToAddr  = trim((string)($challan['ship_to_addr'] ?? ''));
$vehicleNo   = trim((string)($challan['vehicle_no'] ?? ''));
$driverName  = trim((string)($challan['driver_name'] ?? ''));
$remarks     = trim((string)($challan['notes'] ?? ''));

/* ---------- Line totals ---------- */

$totalQty    = 0.0;
$totalAmount = 0.0; // BDT

if (is_array($items)) {
    foreach ($items as $ln) {
        $qty       = (float)($ln['qty'] ?? $ln['quantity'] ?? 0);
        $price     = (float)($ln['unit_price'] ?? $ln['price'] ?? 0);
        $lineTotal = (float)($ln['line_total'] ?? $ln['total'] ?? ($qty * $price));

        $totalQty    += $qty;
        $totalAmount += $lineTotal;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title><?= $h($chNo !== '' ? $chNo : 'Delivery Challan') ?></title>
  <style>
    /* ============================================================
     * Page box + base styles
     * ========================================================== */
    @page {
      size: A4;
      margin: 0.8in 0.5in 0.6in 0.5in;
    }

    * { box-sizing: border-box; }

    body {
      font-family: DejaVu Sans, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
      font-size: 10pt;
      color: #111827;
      margin: 0;
      padding: 0;
    }

    .doc {
      width: 100%;
    }

    /* ============================================================
     * Header (logo + org + challan meta)
     * ========================================================== */
    .header-table {
      width: 100%;
      border-collapse: collapse;
      margin-bottom: 14pt;
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
    .doc-title {
      font-size: 11pt;
      font-weight: 600;
      letter-spacing: 0.16em;
      text-transform: uppercase;
    }
    .doc-label {
      margin-top: 3pt;
      font-size: 10pt;
      font-weight: 600;
      color: #111827;
    }
    .doc-meta {
      margin-top: 3pt;
      font-size: 8pt;
      color: #374151;
      line-height: 1.4;
    }
    .status-pill {
      display: inline-block;
      padding: 2pt 6pt;
      border-radius: 999px;
      background: #ecfdf5;
      color: #065f46;
      border: 0.3pt solid #a7f3d0;
      font-size: 7pt;
      font-weight: 600;
      text-transform: uppercase;
    }

    /* ============================================================
     * Info boxes (customer / ship to / transport)
     * ========================================================== */
    .box-table {
      width: 100%;
      border-collapse: collapse;
      margin-bottom: 12pt;
    }
    .box-cell {
      width: 33.3333%;
      vertical-align: top;
      padding: 0;
    }
    .box-title {
      background: #f3f4f6;
      border: 0.4pt solid #e5e7eb;
      border-bottom: none;
      padding: 4pt 6pt;
      font-size: 8pt;
      font-weight: 600;
      letter-spacing: 0.12em;
      text-transform: uppercase;
      color: #4b5563;
    }
    .box-body {
      border: 0.4pt solid #e5e7eb;
      padding: 5pt 6pt;
      font-size: 9pt;
      line-height: 1.3;
      min-height: 30pt;
    }

    /* ============================================================
     * Items table
     * ========================================================== */
    .section-title {
      font-size: 9pt;
      font-weight: 600;
      margin: 2pt 0 4pt 0;
    }

    .lines-table {
      width: 100%;
      border-collapse: collapse;
      font-size: 8.5pt;
    }
    .lines-table thead th {
      background: #f3f4f6;
      border-top: 0.4pt solid #e5e7eb;
      border-bottom: 0.4pt solid #e5e7eb;
      border-left: 0.4pt solid #e5e7eb;
      padding: 4pt 4pt;
      text-align: left;
      text-transform: uppercase;
      font-size: 7.5pt;
      color: #6b7280;
    }
    .lines-table thead th:last-child {
      border-right: 0.4pt solid #e5e7eb;
    }
    .lines-table thead th.col-num   { width: 18pt; }
    .lines-table thead th.col-item  { width: 110pt; }
    .lines-table thead th.col-desc  { width: auto; }
    .lines-table thead th.col-qty   { width: 38pt; text-align: right; }
    .lines-table thead th.col-unit  { width: 38pt; }
    .lines-table thead th.col-price { width: 60pt; text-align: right; }
    .lines-table thead th.col-total { width: 70pt; text-align: right; }

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
    .item-desc {
      font-size: 7.5pt;
      color: #374151;
      white-space: pre-line;
    }

    /* ============================================================
     * Totals + note
     * ========================================================== */
    .totals-wrap {
      margin-top: 8pt;
      width: 100%;
      border-collapse: collapse;
    }
    .totals-note {
      width: 60%;
      font-size: 7.5pt;
      color: #6b7280;
      vertical-align: top;
      padding-right: 10pt;
    }
    .totals-table {
      width: 40%;
      vertical-align: top;
      font-size: 8.5pt;
    }
    .totals-table table {
      width: 100%;
      border-collapse: collapse;
    }
    .totals-table td {
      padding: 2pt 0;
    }
    .totals-label {
      color: #4b5563;
    }
    .totals-grand {
      border-top: 0.4pt solid #d1d5db;
      padding-top: 3pt;
      margin-top: 2pt;
      font-weight: 600;
      font-size: 9pt;
    }

    /* ============================================================
     * Remarks + challan meta footer block
     * ========================================================== */
    .remarks-table {
      width: 100%;
      border-collapse: collapse;
      margin-top: 10pt;
      font-size: 8.5pt;
    }
    .remarks-left {
      width: 65%;
      vertical-align: top;
    }
    .remarks-right {
      width: 35%;
      vertical-align: top;
    }
    .remarks-title {
      background: #f3f4f6;
      border: 0.4pt solid #e5e7eb;
      border-bottom: none;
      padding: 4pt 6pt;
      font-size: 8pt;
      font-weight: 600;
      letter-spacing: 0.12em;
      text-transform: uppercase;
      color: #4b5563;
    }
    .remarks-body {
      border: 0.4pt solid #e5e7eb;
      padding: 6pt;
      min-height: 30pt;
    }
    .meta-table {
      width: 100%;
      border-collapse: collapse;
      font-size: 8pt;
    }
    .meta-table td {
      padding: 2pt 4pt;
    }
    .meta-label {
      color: #4b5563;
      width: 40%;
    }

    /* ============================================================
     * Signature footer
     * ========================================================== */
    .sign-footer {
      margin-top: 20pt;
      font-size: 8pt;
    }
    .sign-table {
      width: 100%;
      border-collapse: collapse;
    }
    .sign-cell {
      width: 33.3333%;
      text-align: center;
      vertical-align: top;
      padding: 0 10pt;
    }
    .sign-line {
      border-top: 0.4pt solid #9ca3af;
      margin: 0 auto 3pt auto;
      width: 140pt;
    }
  </style>
</head>
<body>

<div class="doc">

  <!-- ==========================================================
       Header
       ======================================================== -->
  <table class="header-table">
    <tr>
      <td class="header-logo">
        <table>
          <tr>
            <td style="vertical-align: top; padding-right: 6pt;">
              <img src="<?= $h($logoUrl) ?>" alt="" style="height:26pt; max-width:110pt;">
            </td>
            <td style="vertical-align: top;">
              <div class="org-name"><?= $h($idName !== '' ? $idName : 'Organisation') ?></div>
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
        <div class="doc-title">DELIVERY CHALLAN</div>
        <div class="doc-label">
          <?= $h($chNo !== '' ? $chNo : ($chId > 0 ? 'CH-' . $chId : '')) ?>
        </div>
        <div class="doc-meta">
          <div>
            <strong>Status:</strong>
            <span class="status-pill"><?= $h($statusLabel) ?></span>
          </div>
          <div><strong>Date:</strong> <?= $chDate !== '' ? $h($chDate) : '—' ?></div>
          <?php if ($invoiceNo !== ''): ?>
            <div><strong>Invoice no:</strong> <?= $h($invoiceNo) ?></div>
          <?php endif; ?>
        </div>
      </td>
    </tr>
  </table>

  <!-- ==========================================================
       Customer / Ship to / Transport
       ======================================================== -->
  <table class="box-table">
    <tr>
      <td class="box-cell">
        <div class="box-title">Customer</div>
        <div class="box-body">
          <div><strong><?= $h($customer !== '' ? $customer : '—') ?></strong></div>
        </div>
      </td>
      <td class="box-cell">
        <div class="box-title">Ship to</div>
        <div class="box-body">
          <div><strong><?= $h($shipToName !== '' ? $shipToName : $customer) ?></strong></div>
          <?php if ($shipToAddr !== ''): ?>
            <div><?= nl2br($h($shipToAddr)) ?></div>
          <?php endif; ?>
        </div>
      </td>
      <td class="box-cell">
        <div class="box-title">Transport</div>
        <div class="box-body">
          <div><strong>Vehicle no:</strong> <?= $vehicleNo !== '' ? $h($vehicleNo) : '—' ?></div>
          <div><strong>Driver:</strong> <?= $driverName !== '' ? $h($driverName) : '—' ?></div>
        </div>
      </td>
    </tr>
  </table>

  <!-- ==========================================================
       Dispatched items
       ======================================================== -->
  <div class="section-title">Dispatched items</div>

  <?php if (!$items): ?>
    <div style="font-size:8.5pt; color:#6b7280; margin-bottom:8pt;">
      No item rows have been stored yet for this challan.
    </div>
  <?php else: ?>
    <table class="lines-table">
      <thead>
      <tr>
        <th class="col-num">SL</th>
        <th class="col-item">Item</th>
        <th class="col-desc">Description</th>
        <th class="col-qty text-right">Qty</th>
        <th class="col-unit">Unit</th>
        <th class="col-price text-right">Unit price</th>
        <th class="col-total text-right">Line total</th>
      </tr>
      </thead>
      <tbody>
      <?php foreach ($items as $i => $ln): ?>
        <?php
        $name = trim((string)(
            $ln['product_name']
            ?? $ln['item_name']
            ?? $ln['name']
            ?? ''
        ));
        $code = trim((string)(
            $ln['product_code']
            ?? $ln['item_code']
            ?? $ln['code']
            ?? ''
        ));
        $desc = trim((string)(
            $ln['description']
            ?? $ln['details']
            ?? ''
        ));
        $qty   = (float)($ln['qty'] ?? $ln['quantity'] ?? 0);
        $unit  = trim((string)($ln['unit'] ?? $ln['uom'] ?? 'pcs'));
        $price = (float)($ln['unit_price'] ?? $ln['price'] ?? 0);
        $total = (float)($ln['line_total'] ?? $ln['total'] ?? ($qty * $price));

        $lineNo = (int)($ln['line_no'] ?? ($i + 1));
        if ($name === '') {
            $name = 'Line ' . $lineNo;
        }
        ?>
        <tr>
          <td><?= $lineNo ?></td>
          <td>
            <div class="item-name"><?= $h($name) ?></div>
            <?php if ($code !== ''): ?>
              <div class="item-code">Code: <?= $h($code) ?></div>
            <?php endif; ?>
          </td>
          <td><div class="item-desc"><?= $h($desc !== '' ? $desc : '—') ?></div></td>
          <td class="text-right"><?= pn($qty, 3) ?></td>
          <td><?= $h($unit) ?></td>
          <td class="text-right"><?= pn($price) ?></td>
          <td class="text-right"><?= pn($total) ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>

  <!-- ==========================================================
       Totals
       ======================================================== -->
  <table class="totals-wrap">
    <tr>
      <td class="totals-note">
        All amounts are in BDT. This challan reflects quantities dispatched from
        your warehouse against the related sale/invoice. Any returns or
        differences should be recorded via a return challan or adjustment entry.
      </td>
      <td class="totals-table">
        <table>
          <tr>
            <td class="totals-label">Total quantity</td>
            <td class="text-right"><?= pn($totalQty, 3) ?></td>
          </tr>
          <tr>
            <td class="totals-grand">Total amount (BDT)</td>
            <td class="totals-grand text-right"><?= pn($totalAmount) ?></td>
          </tr>
        </table>
      </td>
    </tr>
  </table>

  <!-- ==========================================================
       Remarks + challan meta block
       ======================================================== -->
  <table class="remarks-table">
    <tr>
      <td class="remarks-left">
        <div class="remarks-title">Remarks</div>
        <div class="remarks-body">
          <?= $remarks !== '' ? nl2br($h($remarks)) : '—' ?>
        </div>
      </td>
      <td class="remarks-right">
        <div class="remarks-title">Challan details</div>
        <div class="remarks-body">
          <table class="meta-table">
            <tr>
              <td class="meta-label">Challan ID</td>
              <td>#<?= $chId > 0 ? $h((string)$chId) : '—' ?></td>
            </tr>
            <tr>
              <td class="meta-label">Challan no</td>
              <td><?= $h($chNo !== '' ? $chNo : '—') ?></td>
            </tr>
            <tr>
              <td class="meta-label">Printed at</td>
              <td><?= $h(date('Y-m-d H:i')) ?></td>
            </tr>
            <tr>
              <td class="meta-label">Printed by</td>
              <td><?= $h($_SESSION['tenant_user']['name'] ?? 'User') ?></td>
            </tr>
          </table>
        </div>
      </td>
    </tr>
  </table>

  <!-- ==========================================================
       Signature footer
       ======================================================== -->
  <div class="sign-footer">
    <table class="sign-table">
      <tr>
        <td class="sign-cell">
          <div class="sign-line"></div>
          Prepared by
        </td>
        <td class="sign-cell">
          <div class="sign-line"></div>
          Checked / Approved by
        </td>
        <td class="sign-cell">
          <div class="sign-line"></div>
          Receiver signature &amp; seal
        </td>
      </tr>
    </table>
  </div>

</div>

<script>
  // Auto-open browser print dialog when this page loads
  (function () {
    if (typeof window === 'undefined') return;
    window.addEventListener('load', function () {
      try { window.print(); } catch (e) {}
    });
  })();
</script>

</body>
</html>