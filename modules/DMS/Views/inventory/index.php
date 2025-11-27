<?php
declare(strict_types=1);
/** @var array $rows */
/** @var array $kpi */
/** @var array $filters */
/** @var string $module_base */
/** @var string|null $stock_source */

if (!function_exists('h')) {
  function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
}

$brand = '#228B22';

$distinct = (int)($kpi['distinct'] ?? 0);
$totalQty = (float)($kpi['total'] ?? 0);
$lowCount = (int)($kpi['low'] ?? 0);
$invValue = (float)($kpi['value'] ?? 0.0);

$q     = (string)($filters['q']   ?? '');
$low   = (int)   ($filters['low'] ?? 0);
?>
<style>
  :root {
    --brand: <?= $brand ?>;
  }
  .btn { padding:8px 12px; border-radius:10px; font-size:14px; }
  .btn-brand { background:var(--brand); color:#fff; }
  .btn-brand:hover{ filter:brightness(0.95); }
  .btn-soft { background:#f1f5f9; }
  .pill { font-size:11px; padding:3px 8px; border-radius:999px; }
  .pill-low { background:#fee2e2; color:#b91c1c; }
  .card { border:1px solid #e5e7eb; border-radius:16px; background:#fff; }
  .kpi .label{ font-size:12px; text-transform:uppercase; color:#64748b; }
  .kpi .val{ font-size:28px; font-weight:700; }
  .tbl th { font-weight:600; color:#475569; background:#f8fafc; }
  .tbl td, .tbl th { padding:10px 12px; }
  .row-low { background:#fff1f2; }
</style>

<div class="mb-6">
  <h1 class="text-2xl font-bold tracking-tight">Inventory</h1>
  <p class="text-sm text-slate-500">
    Live stock across products. Update via Purchases, Sales, Adjustments, or Damage.
    <?php if (!empty($stock_source)): ?>
      <span class="ml-2 text-xs text-slate-400">source: <em><?= h($stock_source) ?></em></span>
    <?php endif; ?>
  </p>
</div>

<!-- Actions -->
<div class="flex gap-2 mb-4">
  <a href="<?= h($module_base) ?>/inventory/adjust" class="btn btn-brand">
    <i class="fa-solid fa-sliders"></i> <span class="ml-1">Adjust Stock</span>
  </a>
  <a href="<?= h($module_base) ?>/inventory/damage" class="btn btn-soft">
    <i class="fa-solid fa-ban"></i> <span class="ml-1">Damage</span>
  </a>
  <a href="<?= h($module_base) ?>/inventory/aging" class="btn btn-soft">
    <i class="fa-solid fa-hourglass-half"></i> <span class="ml-1">Aging</span>
  </a>
  <button id="exportCsv" class="btn btn-soft">
    <i class="fa-regular fa-file-lines"></i> <span class="ml-1">Export CSV</span>
  </button>
</div>

<!-- KPIs -->
<div class="grid grid-cols-1 sm:grid-cols-4 gap-4 mb-6">
  <div class="card p-4 kpi">
    <div class="label">Distinct Products</div>
    <div class="val"><?= number_format($distinct) ?></div>
  </div>
  <div class="card p-4 kpi">
    <div class="label">Total Stock (Units)</div>
    <div class="val"><?= number_format($totalQty, 2) ?></div>
  </div>
  <div class="card p-4 kpi">
    <div class="label">Low Stock Alerts</div>
    <div class="val"><?= number_format($lowCount) ?></div>
  </div>
  <div class="card p-4 kpi">
    <div class="label">Inventory Value</div>
    <div class="val">৳ <?= number_format($invValue, 2) ?></div>
  </div>
</div>

<!-- Filters -->
<form method="get" class="flex flex-col sm:flex-row sm:items-center gap-3 mb-4">
  <div class="relative flex-1">
    <i class="fa-solid fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-slate-400"></i>
    <input name="q" value="<?= h($q) ?>" placeholder="Search by product or SKU..."
           class="w-full pl-9 pr-3 py-2 rounded-lg border" />
  </div>
  <label class="inline-flex items-center gap-2 text-sm text-slate-600">
    <input type="checkbox" name="low" value="1" <?= $low ? 'checked' : '' ?> />
    Low stock only
  </label>
  <button class="btn btn-soft" type="submit">Apply</button>
</form>

<!-- Table -->
<div class="card overflow-x-auto">
  <table id="inv-table" class="min-w-full text-sm tbl">
    <thead>
      <tr>
        <th class="text-left w-[30%]">Product</th>
        <th class="text-left w-[18%]">SKU</th>
        <th class="text-left w-[18%]">Supplier</th>
        <th class="text-right w-[10%]">On Hand</th>
        <th class="text-right w-[10%]">Avg Cost</th>
        <th class="text-right w-[10%]">Last Cost</th>
        <th class="text-right w-[14%]">Stock Value</th>
        <th class="text-right w-[10%]">Actions</th>
      </tr>
    </thead>
    <tbody>
      <?php if (!$rows): ?>
        <tr><td colspan="8" class="px-3 py-10 text-center text-slate-500">
          No products yet. Add products and record purchases/adjustments to see stock here.
        </td></tr>
      <?php else: ?>
        <?php foreach ($rows as $r): ?>
          <?php
            // Robust numeric reads (NULL-safe)
            $onHand     = (float)($r['on_hand']     ?? 0.0);
            $avgCost    = (float)($r['avg_cost']    ?? 0.0);
            $lastCost   = (float)($r['last_cost']   ?? 0.0);
            $stockValue = array_key_exists('stock_value', $r)
                          ? (float)$r['stock_value']
                          : $onHand * ($avgCost > 0 ? $avgCost : $lastCost);
            $rowLow = ($onHand <= 0.00001);
          ?>
          <tr class="<?= $rowLow ? 'row-low' : '' ?> border-t">
            <td class="px-3 py-2">
              <div class="font-medium"><?= h($r['name'] ?? '') ?></div>
              <?php if ($rowLow): ?>
                <span class="pill pill-low mt-1 inline-flex items-center">
                  <i class="fa-solid fa-triangle-exclamation mr-1"></i> Low / Out of stock
                </span>
              <?php endif; ?>
            </td>
            <td class="px-3 py-2 text-slate-600"><?= h($r['sku'] ?? '') ?></td>
            <td class="px-3 py-2 text-slate-600">
              <?php
                $sid = (int)($r['supplier_id'] ?? 0);
                echo $sid > 0 ? 'SUP-'.str_pad((string)$sid, 4, '0', STR_PAD_LEFT) : '—';
              ?>
            </td>
            <td class="px-3 py-2 text-right font-semibold" data-onhand><?= number_format($onHand, 2) ?></td>
            <td class="px-3 py-2 text-right">৳ <?= number_format($avgCost, 2) ?></td>
            <td class="px-3 py-2 text-right">৳ <?= number_format($lastCost, 2) ?></td>
            <td class="px-3 py-2 text-right font-semibold" data-val><?= $stockValue > 0 ? '৳ '.number_format($stockValue, 2) : '—' ?></td>
            <td class="px-3 py-2 text-right">
              <a class="text-[13px] text-[color:var(--brand)] hover:underline"
                 href="<?= h($module_base) ?>/inventory/moves?product_id=<?= (int)($r['id'] ?? 0) ?>">
                Movements
              </a>
            </td>
          </tr>
        <?php endforeach; ?>
      <?php endif; ?>
    </tbody>
  </table>
</div>

<!-- Quick nav buttons -->
<div class="mt-4 flex flex-wrap gap-3">
  <a href="<?= h($module_base) ?>/products" class="btn btn-soft"><i class="fa-solid fa-boxes-stacked"></i> <span class="ml-1">Products</span></a>
  <a href="<?= h($module_base) ?>/purchases" class="btn btn-soft"><i class="fa-solid fa-cart-shopping"></i> <span class="ml-1">Purchases</span></a>
  <a href="<?= h($module_base) ?>/sales" class="btn btn-soft"><i class="fa-solid fa-cash-register"></i> <span class="ml-1">Sales</span></a>
</div>

<script>
(function(){
  // CSV export (uses visible table, keeps numeric formatting as plain text)
  document.getElementById('exportCsv')?.addEventListener('click', ()=>{
    const ths = Array.from(document.querySelectorAll('#inv-table thead th')).map(th=>th.textContent.trim());
    const trs = Array.from(document.querySelectorAll('#inv-table tbody tr'))
      .filter(tr => tr.querySelectorAll('td').length);

    const data = trs.map(tr => {
      const tds = Array.from(tr.querySelectorAll('td'));
      return [
        tds[0]?.innerText.replace(/\s+/g,' ').trim() || '',
        tds[1]?.innerText.replace(/\s+/g,' ').trim() || '',
        tds[2]?.innerText.replace(/\s+/g,' ').trim() || '',
        tds[3]?.innerText.replace(/[^\d.\-]/g,'') || '0',
        tds[4]?.innerText.replace(/[^\d.\-]/g,'') || '0',
        tds[5]?.innerText.replace(/[^\d.\-]/g,'') || '0',
        tds[6]?.innerText.replace(/[^\d.\-]/g,'') || '0',
      ];
    });

    const csv = [ths.join(','), ...data.map(r => r.map(v => `"${v.replace(/"/g,'""')}"`).join(','))].join('\r\n');
    const blob = new Blob([csv], {type:'text/csv;charset=utf-8;'});
    const a = document.createElement('a');
    a.href = URL.createObjectURL(blob);
    a.download = 'inventory.csv';
    a.click();
    URL.revokeObjectURL(a.href);
  });
})();
</script>