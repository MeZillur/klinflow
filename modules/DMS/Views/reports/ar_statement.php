<?php
declare(strict_types=1);
if (!function_exists('h')) { function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); } }

$type  = (string)($type ?? 'ar');
$isAR  = ($type === 'ar');

$from   = (string)($from   ?? date('Y-m-01'));
$to     = (string)($to     ?? date('Y-m-d'));
$cId    = (int)   ($customer_id   ?? 0);
$cName  = (string)($customer_name ?? '');
$lines  = is_array($lines ?? null) ? $lines : [];
$opening= (float)($opening ?? 0);
$closing= (float)($closing ?? 0);
$tDr    = (float)($total_debit ?? 0);
$tCr    = (float)($total_credit ?? 0);
$isPrint = ($_GET['print'] ?? '') === '1';
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<title><?= h($isAR ? 'Customer' : 'Supplier') ?> Statement</title>
<meta name="viewport" content="width=device-width,initial-scale=1">
<style>
:root { --ink:#111827; --muted:#6b7280; --line:#e5e7eb; }
* { box-sizing:border-box; }
body { font-family: system-ui, Segoe UI, Roboto, Arial; color:var(--ink); margin:0; }
.container { max-width: 980px; margin: 0 auto; padding: 20px; }
h1 { font-size: 20px; margin: 0 0 10px; }
.card { background:#fff; border:1px solid var(--line); border-radius:12px; padding:16px; }
.row { display:grid; grid-template-columns: repeat(12, 1fr); gap:12px; }
.badge { display:inline-block; padding:3px 8px; border-radius:999px; background:#f1f5f9; color:#0f172a; font-size:12px; }
.table { width:100%; border-collapse:collapse; }
.table thead th { text-align:left; font-size:12px; color:var(--muted); padding:8px 10px; border-bottom:1px solid var(--line); }
.table tbody td { padding:8px 10px; border-bottom:1px solid var(--line); font-size:13px; }
.right { text-align:right; }
.meta { display:flex; justify-content:space-between; gap:16px; margin-bottom:10px; }
.no-print { <?php if($isPrint) echo 'display:none;'; ?> }
@media print { .no-print { display:none!important; } body { background:white; } .container { padding:0; } .card { border:none; } }
</style>
</head>
<body>
<div class="container">
  <div class="no-print" style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;">
    <div><a href="<?= h(($module_base ?? '').'/dashboard') ?>" style="text-decoration:none;color:#475569">&larr; Back</a></div>
    <div>
      <a href="<?= h(($module_base ?? '').'/reports/ar-statement/print?type='.$type.'&customer_id='.$cId.'&customer_name='.urlencode($cName).'&from='.$from.'&to='.$to.'&print=1') ?>"
         class="badge" style="background:#10b981;color:white;">Print</a>
    </div>
  </div>

  <div class="card">
    <div class="meta">
      <div>
        <h1><?= h($isAR ? 'Customer' : 'Supplier') ?> Statement</h1>
        <div class="badge">Period: <?= h($from) ?> → <?= h($to) ?></div>
      </div>
      <div class="right">
        <div><strong><?= h($isAR ? 'Customer' : 'Supplier') ?>:</strong> <?= h($cName ?: ('#'.$cId)) ?></div>
      </div>
    </div>

    <div class="row" style="margin-bottom:10px;">
      <div style="grid-column: span 3;">
        <div class="badge">Opening</div>
        <div style="font-weight:600;margin-top:6px;">৳ <?= number_format($opening,2) ?></div>
      </div>
      <div style="grid-column: span 3;">
        <div class="badge">Debits (<?= h($isAR ? 'Invoices' : 'Bills') ?>)</div>
        <div style="font-weight:600;margin-top:6px;">৳ <?= number_format($tDr,2) ?></div>
      </div>
      <div style="grid-column: span 3;">
        <div class="badge">Credits (<?= h($isAR ? 'Receipts' : 'Payments') ?>)</div>
        <div style="font-weight:600;margin-top:6px;">৳ <?= number_format($tCr,2) ?></div>
      </div>
      <div style="grid-column: span 3;">
        <div class="badge">Closing</div>
        <div style="font-weight:800;margin-top:6px;">৳ <?= number_format($closing,2) ?></div>
      </div>
    </div>

    <table class="table">
      <thead>
        <tr>
          <th style="width:16%;">Date</th>
          <th>Memo</th>
          <th class="right" style="width:16%;">Debit</th>
          <th class="right" style="width:16%;">Credit</th>
          <th class="right" style="width:16%;">Balance</th>
        </tr>
      </thead>
      <tbody>
        <tr>
          <td><?= h($from) ?></td>
          <td><em>Opening balance</em></td>
          <td class="right">—</td>
          <td class="right">—</td>
          <td class="right"><?= number_format($opening,2) ?></td>
        </tr>
        <?php if(!$lines): ?>
          <tr><td colspan="5" style="text-align:center;color:var(--muted);padding:16px;">No activity in this period.</td></tr>
        <?php else: foreach($lines as $ln): ?>
          <tr>
            <td><?= h($ln['date']) ?></td>
            <td><?= h($ln['memo']) ?></td>
            <td class="right"><?= $ln['debit'] ? number_format($ln['debit'],2) : '—' ?></td>
            <td class="right"><?= $ln['credit']? number_format($ln['credit'],2): '—' ?></td>
            <td class="right"><?= number_format($ln['balance'],2) ?></td>
          </tr>
        <?php endforeach; endif; ?>
        <tr>
          <td colspan="2" class="right" style="font-weight:700;">Totals</td>
          <td class="right" style="font-weight:700;">৳ <?= number_format($tDr,2) ?></td>
          <td class="right" style="font-weight:700;">৳ <?= number_format($tCr,2) ?></td>
          <td class="right" style="font-weight:800;">৳ <?= number_format($closing,2) ?></td>
        </tr>
      </tbody>
    </table>
  </div>

  <!-- Filter -->
  <div class="card no-print" style="margin-top:12px;">
    <form method="GET" action="">
      <div class="row">
        <div style="grid-column: span 2;">
          <label style="font-size:12px;color:var(--muted)">Type</label>
          <select name="type" style="width:100%;padding:8px;border:1px solid var(--line);border-radius:8px;">
            <option value="ar" <?= $isAR ? 'selected' : '' ?>>Customer</option>
            <option value="ap" <?= $isAR ? '' : 'selected' ?>>Supplier</option>
          </select>
        </div>
        <div style="grid-column: span 3;">
          <label style="font-size:12px;color:var(--muted)">From</label>
          <input type="date" name="from" value="<?= h($from) ?>" style="width:100%;padding:8px;border:1px solid var(--line);border-radius:8px;">
        </div>
        <div style="grid-column: span 3;">
          <label style="font-size:12px;color:var(--muted)">To</label>
          <input type="date" name="to" value="<?= h($to) ?>" style="width:100%;padding:8px;border:1px solid var(--line);border-radius:8px;">
        </div>
        <div style="grid-column: span 2;">
          <label style="font-size:12px;color:var(--muted)"><?= h($isAR ? 'Customer' : 'Supplier') ?> ID</label>
          <!-- keep legacy param names to match controller -->
          <input type="number" name="customer_id" value="<?= h((string)$cId) ?>"
                 style="width:100%;padding:8px;border:1px solid var(--line);border-radius:8px;">
        </div>
        <div style="grid-column: span 2;">
          <label style="font-size:12px;color:var(--muted)"><?= h($isAR ? 'Customer' : 'Supplier') ?> Name</label>
          <input type="text" name="customer_name" value="<?= h($cName) ?>" placeholder="Optional"
                 style="width:100%;padding:8px;border:1px solid var(--line);border-radius:8px;">
        </div>
      </div>
      <div style="margin-top:10px;display:flex;gap:8px;justify-content:flex-end;">
        <button class="badge" style="background:#0ea5e9;color:#fff;border:none;cursor:pointer;">Apply</button>
        <a class="badge" href="<?= h(($module_base ?? '').'/reports/ar-statement') ?>">Reset</a>
      </div>
    </form>
  </div>
</div>

<script>
(function(){
  const p = new URLSearchParams(location.search);
  if(p.get('print')==='1'){ window.addEventListener('load', ()=>window.print()); }
})();
</script>
</body>
</html>