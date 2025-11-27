<?php declare(strict_types=1);
if (!function_exists('h')) { function h($v){ return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8'); } }
$r = $ret ?? [];
$items = is_array($items ?? null) ? $items : [];
$no  = (string)($r['return_no'] ?? '');
$dt  = substr((string)($r['return_date'] ?? ''),0,10);
$tot = (float)($r['grand_total'] ?? 0);
?><!DOCTYPE html>
<html><head><meta charset="utf-8">
<title>Return <?= h($no) ?> — Print</title>
<meta name="viewport" content="width=device-width,initial-scale=1">
<style>
:root{--ink:#111827;--muted:#6b7280;--line:#e5e7eb;}
*{box-sizing:border-box} body{font-family:system-ui,Segoe UI,Roboto,Arial;margin:0;color:var(--ink)}
.sheet{max-width:900px;margin:0 auto;padding:24px}
.head{display:flex;justify-content:space-between;gap:20px;border-bottom:1px solid var(--line);padding-bottom:10px}
.meta{text-align:right}
table{width:100%;border-collapse:collapse}
thead th{font-size:12px;color:var(--muted);padding:8px 10px;border-bottom:1px solid var(--line);text-align:left}
tbody td{padding:8px 10px;border-bottom:1px solid var(--line);font-size:13px}
.right{text-align:right} .no-print{display:block}
@media print{.no-print{display:none!important}}
</style></head>
<body>
<div class="sheet">
  <div class="no-print" style="display:flex;justify-content:flex-end;margin-bottom:6px">
    <button onclick="window.print()" style="padding:8px 12px;border:1px solid #d1d5db;border-radius:8px;background:#fff;cursor:pointer">Print</button>
  </div>
  <div class="head">
    <div>
      <div style="font-weight:700">KlinFlow — Sales Return</div>
      <div class="muted" style="color:var(--muted);font-size:12px">Tenant: <?= h($tenant_slug ?? '') ?></div>
    </div>
    <div class="meta">
      <div><strong>No:</strong> <?= h($no) ?></div>
      <div><strong>Date:</strong> <?= h($dt) ?></div>
      <div><strong>Customer:</strong> #<?= h((string)($r['customer_id'] ?? '')) ?></div>
    </div>
  </div>

  <h3 style="margin:14px 0 8px;font-size:16px">Items</h3>
  <table>
    <thead><tr>
      <th>Product</th><th class="right" style="width:120px">Qty</th>
      <th class="right" style="width:140px">Price</th><th class="right" style="width:160px">Line Total</th>
    </tr></thead>
    <tbody>
      <?php foreach ($items as $ln): $lt=(float)$ln['line_total']; ?>
      <tr>
        <td><?= h($ln['product_name']) ?></td>
        <td class="right"><?= number_format((float)$ln['qty'],2) ?></td>
        <td class="right">৳ <?= number_format((float)$ln['unit_price'],2) ?></td>
        <td class="right">৳ <?= number_format($lt,2) ?></td>
      </tr>
      <?php endforeach; ?>
      <tr>
        <td colspan="3" class="right" style="font-weight:700">Grand Total</td>
        <td class="right" style="font-weight:800">৳ <?= number_format($tot,2) ?></td>
      </tr>
    </tbody>
  </table>

  <?php if (!empty($r['notes'])): ?>
    <div style="margin-top:12px;border:1px dashed var(--line);padding:8px;font-size:12px">
      <strong>Notes:</strong><br><?= nl2br(h((string)$r['notes'])) ?>
    </div>
  <?php endif; ?>
</div>
<script>
if (new URLSearchParams(location.search).get('autoprint')==='1') {
  window.addEventListener('load',()=>window.print());
}
</script>
</body></html>