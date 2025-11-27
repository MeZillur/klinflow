<?php declare(strict_types=1);
if(!function_exists('h')){function h($v){return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');}}
$r = is_array($receipt??null)?$receipt:[];
$items = is_array($items??null)?$items:[];
?><!DOCTYPE html><html><head><meta charset="utf-8"><title>Receipt <?= h($r['receipt_no']??'') ?></title>
<style>
:root{--ink:#111827;--muted:#6b7280;--line:#e5e7eb}
*{box-sizing:border-box}body{font-family:system-ui,Segoe UI,Roboto,Arial; color:var(--ink); margin:0}
.sheet{max-width:900px;margin:0 auto;padding:28px}
.head{display:flex;justify-content:space-between;gap:24px;border-bottom:1px solid var(--line);padding-bottom:12px}
.brand h1{font-size:18px;margin:0 0 2px}.brand small{color:var(--muted)}
.meta{text-align:right}.meta div{line-height:1.3}
table{width:100%;border-collapse:collapse}thead th{font-size:12px;color:var(--muted);padding:8px 10px;border-bottom:1px solid var(--line);text-align:left}
tbody td{padding:8px 10px;border-bottom:1px solid var(--line);font-size:13px}
.right{text-align:right}.muted{color:var(--muted)}
@media print{.no-print{display:none!important}}
</style></head><body>
<div class="sheet">
  <div class="no-print" style="display:flex;justify-content:flex-end;margin-bottom:6px">
    <button onclick="window.print()" style="padding:8px 12px;border:1px solid #d1d5db;border-radius:8px;background:#fff;cursor:pointer">Print</button>
  </div>

  <div class="head">
    <div class="brand">
      <h1>KlinFlow — Receipt</h1>
      <small class="muted">Tenant: <?= h($tenant_slug ?? '') ?></small>
    </div>
    <div class="meta">
      <div><strong>No:</strong> <?= h($r['receipt_no']??'') ?></div>
      <div><strong>Date:</strong> <?= h(substr($r['receipt_date']??'',0,10)) ?></div>
      <div><strong>Customer:</strong> <?= h($r['customer_name']??'') ?></div>
      <div><strong>Method:</strong> <?= h($r['pay_method']??'') ?></div>
      <div><strong>Amount:</strong> ৳ <?= number_format((float)($r['total_amount']??0),2) ?></div>
    </div>
  </div>

  <h3 style="margin:16px 0 8px">Applied to Invoices</h3>
  <table>
    <thead><tr><th>Invoice</th><th class="right" style="width:200px">Amount</th></tr></thead>
    <tbody>
      <?php if(!$items): ?>
        <tr><td class="muted" colspan="2">On-account (not applied to specific invoices)</td></tr>
      <?php else: foreach($items as $it): ?>
        <tr>
          <td><?= h($it['sale_no'] ? ('#'.$it['sale_no']) : ('Sale ID '.$it['sale_id'])) ?></td>
          <td class="right">৳ <?= number_format((float)$it['amount_applied'],2) ?></td>
        </tr>
      <?php endforeach; endif; ?>
    </tbody>
  </table>
</div>
<script>
(function(){
  const p = new URLSearchParams(location.search);
  if(p.get('autoprint')==='1'){ window.addEventListener('load', ()=>window.print()); }
})();
</script>
</body></html>