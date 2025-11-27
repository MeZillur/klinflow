<?php
declare(strict_types=1);

/**
 * POS — A4 Invoice Print
 * ----------------------
 * Expected variables:
 *   - array  $sale          : header from pos_sales
 *   - array  $items         : line items (pos_sale_items + joined products)
 *   - array  $org           : ['name','address','phone','email','website'?]
 *   - string $orgLogo       : logo URL (can be empty)
 *   - string $noCol         : column name for invoice number (e.g. invoice_no)
 *   - ?string $dateCol      : date column name (e.g. sale_date / created_at)
 *   - ?string $qr_url       : QR image URL (optional)
 *   - ?string $invoice_url  : full invoice URL (optional, used for reference)
 */

$h = fn($x) => htmlspecialchars((string)$x, ENT_QUOTES, 'UTF-8');

// ---- Safe column handling ---------------------------------------------------
$dateCol = $dateCol ?? null;

$invoiceNo = (string)($sale[$noCol] ?? '');
$dateRaw   = $dateCol ? ($sale[$dateCol] ?? '') : '';
$dateText  = $dateRaw ? date('d M Y', strtotime((string)$dateRaw)) : '';

// ---- Money fields -----------------------------------------------------------
$sub   = (float)($sale['subtotal_amount'] ?? 0);
$disc  = (float)($sale['discount_amount'] ?? 0);
$tax   = (float)($sale['tax_amount'] ?? 0);
$total = (float)($sale['total_amount'] ?? ($sale['grand_total'] ?? 0));

$fmt        = fn($v) => number_format((float)$v, 2);
$qrUrl      = isset($qr_url)      ? (string)$qr_url      : '';
$invoiceUrl = isset($invoice_url) ? (string)$invoice_url : '';
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <title><?= $h($invoiceNo) ?> – Invoice</title>

  <!-- ========== BASE STYLES ========== -->
  <style>
    :root { --brand:#228B22; }
    * { box-sizing:border-box; }

    body{
      font-family:system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;
      margin:0; padding:20px;
      background:#f3f4f6; color:#111827;
    }

    .sheet{
      max-width:900px;
      margin:0 auto;
      background:#fff;
      padding:32px 36px 40px;
      box-shadow:0 10px 40px rgba(15,23,42,.15);
    }

    .row{ display:flex; justify-content:space-between; gap:24px; }
    .w-50{ width:50%; }

    h1{
      margin:8px 0 4px;
      font-size:26px;
      letter-spacing:.5px;
    }

    .muted{ color:#6b7280; font-size:13px; }

    .badge{
      display:inline-block;
      padding:4px 10px;
      border-radius:999px;
      font-size:11px;
      font-weight:600;
      background:#ecfdf3;
      color:#166534;
    }

    table{
      width:100%;
      border-collapse:collapse;
      margin-top:24px;
      font-size:13px;
    }
    th,td{
      padding:8px 8px;
      border-bottom:1px solid #e5e7eb;
    }
    thead th{
      text-align:left;
      background:#f9fafb;
      font-weight:600;
    }
    td.num, th.num{ text-align:right; }

    .totals{
      margin-top:20px;
      width:300px;
      margin-left:auto;
      font-size:13px;
    }
    .totals tr:last-child td{
      font-weight:700;
      font-size:15px;
      border-top:2px solid #e5e7eb;
    }
    .totals td.label{ text-align:left; }

    .logo{ max-height:60px; }

    .footer{
      margin-top:40px;
      font-size:11px;
      text-align:center;
      color:#6b7280;
      line-height:1.6;
    }

    @media print {
      @page { size:A4; margin:10mm; }
      body{
        background:#fff;
        padding:0;
        -webkit-print-color-adjust:exact;
      }
      .sheet{
        box-shadow:none;
        margin:0;
        max-width:none;
      }
    }
  </style>
</head>
<body>

<div class="sheet">

  <!-- ========== HEADER (ORG + BILL-TO + QR) ========== -->
  <div class="row" style="margin-bottom:24px;">
    <!-- Left: Org info -->
    <div class="w-50">
      <?php if (!empty($orgLogo)): ?>
        <img src="<?= $h($orgLogo) ?>" alt="Logo" class="logo">
      <?php endif; ?>

      <h1><?= $h($org['name'] ?? 'Your Business Name') ?></h1>

      <?php if (!empty($org['address'])): ?>
        <div class="muted"><?= nl2br($h($org['address'])) ?></div>
      <?php endif; ?>

      <div class="muted">
        <?php if (!empty($org['phone'])): ?>
          Phone: <?= $h($org['phone']) ?>
        <?php endif; ?>

        <?php if (!empty($org['email'])): ?>
          <?= !empty($org['phone']) ? ' · ' : '' ?>
          Email: <?= $h($org['email']) ?>
        <?php endif; ?>
      </div>

      <?php if (!empty($org['website'] ?? '')): ?>
        <div class="muted">
          Website: <?= $h($org['website']) ?>
        </div>
      <?php endif; ?>
    </div>

    <!-- Right: Invoice meta + Bill-to + QR -->
    <div class="w-50" style="text-align:right;">
      <div style="font-size:22px;font-weight:700;">INVOICE</div>

      <div class="muted" style="margin-top:4px;">
        No: <strong><?= $h($invoiceNo) ?></strong>
      </div>

      <div class="muted">Date: <?= $h($dateText ?: '-') ?></div>

      <div style="margin-top:8px;">
        <span class="badge">
          <?= strtoupper((string)($sale['status'] ?? 'POSTED')) ?>
        </span>
      </div>

      <div style="margin-top:14px;" class="muted">
        <div>Bill To:</div>
        <div style="font-weight:600;font-size:14px;color:#111827;">
          <?= $h($sale['customer_name'] ?? 'Walk-in Customer') ?>
        </div>
      </div>

      <?php if ($qrUrl !== ''): ?>
        <div style="margin-top:16px; text-align:right;">
          <div class="muted" style="font-size:11px; margin-bottom:4px;">
            Scan to view this invoice
          </div>
          <img src="<?= $h($qrUrl) ?>" alt="Invoice QR"
               style="width:90px;height:90px;object-fit:contain;border:1px solid #e5e7eb;border-radius:8px;padding:4px;">
        </div>
      <?php endif; ?>
    </div>
  </div>

  <!-- ========== LINE ITEMS ========== -->
  <table>
    <thead>
      <tr>
        <th style="width:40%;">Product</th>
        <th>Qty</th>
        <th class="num">Unit Price</th>
        <th class="num">Line Total</th>
      </tr>
    </thead>
    <tbody>
    <?php if (empty($items)): ?>
      <tr>
        <td colspan="4" class="muted" style="text-align:center;padding:14px 8px;">
          No items found.
        </td>
      </tr>
    <?php else: ?>
      <?php foreach ($items as $it):

        // ---- Product name: align with POS receipt logic ----
        $name =
          $it['product_name']
          ?? $it['name']
          ?? $it['product_label']
          ?? '';

        // Quantity
        $qty = (float)($it['qty'] ?? $it['quantity'] ?? 0);

        // Unit price: cents → float (fallback to plain price/unit_price)
        if (isset($it['unit_price_cents'])) {
          $unit = (float)$it['unit_price_cents'] / 100;
        } else {
          $unit = (float)($it['unit_price'] ?? $it['price'] ?? 0);
        }

        // Line total: cents → float (fallback to line_total/total or qty*unit)
        if (isset($it['line_total_cents'])) {
          $line = (float)$it['line_total_cents'] / 100;
        } else {
          $line = (float)($it['line_total'] ?? $it['total'] ?? ($qty * $unit));
        }
      ?>
        <tr>
          <td><?= $h($name) ?></td>
          <td><?= $qty ?></td>
          <td class="num"><?= $fmt($unit) ?></td>
          <td class="num"><?= $fmt($line) ?></td>
        </tr>
      <?php endforeach; ?>
    <?php endif; ?>
    </tbody>
  </table>

  <!-- ========== TOTALS ========== -->
  <table class="totals">
    <tr>
      <td class="label">Subtotal</td>
      <td class="num"><?= $fmt($sub) ?></td>
    </tr>
    <tr>
      <td class="label">Discount</td>
      <td class="num">−<?= $fmt($disc) ?></td>
    </tr>
    <tr>
      <td class="label">Tax</td>
      <td class="num"><?= $fmt($tax) ?></td>
    </tr>
    <tr>
      <td class="label">Grand Total</td>
      <td class="num"><?= $fmt($total) ?></td>
    </tr>
  </table>

    <!-- FOOTER -->
  <div class="footer">
    <?php if (!empty($branch) && is_array($branch)): ?>
      <div style="margin-bottom:6px;">
        <strong>Branch:</strong>
        <?= $h($branch['name'] ?? '') ?>
        <?php if (!empty($branch['address'])): ?>
          <br>
          <span><?= nl2br($h($branch['address'])) ?></span>
        <?php endif; ?>
      </div>
    <?php endif; ?>

    Thank you for your business.<br>
    <strong>This is a system-generated invoice — no physical signature is required.</strong>
  </div>

</div>

<script>
// Auto-open print dialog for this tab
window.addEventListener('load', function () {
  window.print();
});
</script>

</body>
</html>