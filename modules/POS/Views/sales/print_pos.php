<?php
declare(strict_types=1);

/* POS Thermal Receipt — Final Premium Version */

$h = fn($x)=>htmlspecialchars((string)$x, ENT_QUOTES, 'UTF-8');

$invoiceNo = (string)$sale[$noCol];
$dateRaw   = $dateCol ? ($sale[$dateCol] ?? '') : '';
$dateText  = $dateRaw ? date('d M Y H:i', strtotime((string)$dateRaw)) : '';

$sub   = (float)($sale['subtotal_amount'] ?? 0);
$disc  = (float)($sale['discount_amount'] ?? 0);
$tax   = (float)($sale['tax_amount'] ?? 0);
$total = (float)($sale['total_amount'] ?? ($sale['grand_total'] ?? 0));

$fmt = fn($v)=>number_format((float)$v, 2);
?>
<!doctype html>
<html>
<head>
<meta charset="utf-8">
<title>Receipt <?= $h($invoiceNo) ?></title>

<style>
*{ box-sizing:border-box; }
body{
  font-family:-apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
  margin:0; padding:0;
  display:flex; justify-content:center;
  background:#fff;
}

.ticket{
  width:80mm;
  padding:4mm 3mm 6mm;
  font-size:12px;
}

.center{text-align:center;}
hr{ border:none; border-top:1px dashed #999; margin:6px 0; }

table{ width:100%; border-collapse:collapse; }
td,th{ padding:2px 0; }
.num{text-align:right;}

.logo{
  max-height:45px;
  margin-bottom:4px;
}

@media print{
  @page{ size:80mm auto; margin:0; }
  body{ margin:0; padding:0; }
}
</style>
</head>

<body>
<div class="ticket">

  <!-- HEADER -->
  <div class="center">
    <?php if (!empty($orgLogo)): ?>
      <img src="<?= $h($orgLogo) ?>" class="logo">
    <?php endif; ?>

    <div style="font-weight:700; font-size:14px;">
      <?= $h($org['name'] ?? '') ?>
    </div>

    <?php if (!empty($org['address'])): ?>
      <div><?= nl2br($h($org['address'])) ?></div>
    <?php endif; ?>

    <?php if (!empty($org['phone'])): ?>
      <div>Tel: <?= $h($org['phone']) ?></div>
    <?php endif; ?>
  </div>

  <hr>

  <!-- INFO -->
  <table>
    <tr><td>Invoice:</td><td class="num"><?= $h($invoiceNo) ?></td></tr>
    <tr><td>Date:</td><td class="num"><?= $h($dateText) ?></td></tr>
    <tr><td>Customer:</td><td class="num"><?= $h($sale['customer_name'] ?? 'Walk-in') ?></td></tr>
  </table>

  <hr>

  <!-- ITEMS -->
  <table>
    <thead>
      <tr>
        <th align="left">Item</th>
        <th class="num">Qty</th>
        <th class="num">Total</th>
      </tr>
    </thead>
    <tbody>
    <?php foreach ($items as $it):

      $qty = (float)($it['qty'] ?? $it['quantity'] ?? 0);

      $name =
        $it['product_name']
        ?? $it['name']
        ?? $it['product_label']
        ?? $it['label']
        ?? '';

      if (isset($it['unit_price_cents'])) {
        $unit = (float)$it['unit_price_cents'] / 100;
      } else {
        $unit = (float)($it['unit_price'] ?? $it['price'] ?? 0);
      }

      if (isset($it['line_total_cents'])) {
        $line = (float)$it['line_total_cents'] / 100;
      } else {
        $line = (float)($it['line_total'] ?? ($qty * $unit));
      }
    ?>
      <tr>
        <td><?= $h($name) ?></td>
        <td class="num"><?= $qty ?></td>
        <td class="num"><?= $fmt($line) ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>

  <hr>

  <!-- TOTALS -->
  <table>
    <tr><td>Subtotal</td><td class="num"><?= $fmt($sub) ?></td></tr>
    <tr><td>Discount</td><td class="num">−<?= $fmt($disc) ?></td></tr>
    <tr><td>Tax</td><td class="num"><?= $fmt($tax) ?></td></tr>
    <tr><td style="font-weight:700;">TOTAL</td><td class="num" style="font-weight:700;"><?= $fmt($total) ?></td></tr>
  </table>

    <hr>
  <div class="center" style="margin-top:4px;">
    <?php if (!empty($branch) && is_array($branch)): ?>
      <div style="margin-bottom:4px;">
        <strong>Branch:</strong> <?= $h($branch['name'] ?? '') ?><br>
        <?php if (!empty($branch['address'])): ?>
          <span><?= nl2br($h($branch['address'])) ?></span><br>
        <?php endif; ?>
      </div>
    <?php endif; ?>

    Thank you for shopping!<br>
    <strong>This is a system-generated receipt — no physical signature is required.</strong>
  </div>

</div>

<script>
window.onload = () => window.print();
</script>

</body>
</html>