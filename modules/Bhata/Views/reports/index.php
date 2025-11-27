<?php
declare(strict_types=1);
if (!function_exists('h')) { function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); } }

$base   = $module_base ?? '';
$from   = substr((string)($from ?? date('Y-m-01')), 0, 10);
$to     = substr((string)($to   ?? date('Y-m-d')), 0, 10);
$kpi    = is_array($kpi ?? null) ? $kpi : [];
$cycles = is_array($cycles ?? null) ? $cycles : [];

function n2($v){ return number_format((float)$v, 2); }
function n0($v){ return number_format((float)$v, 0); }
?>
<div class="flex items-center justify-between mb-4">
  <h2 class="text-xl font-semibold">Bhata — Reports</h2>
  <a href="<?=h($base.'/bhata/production')?>" class="px-3 py-2 rounded bg-slate-900 text-white hover:bg-black/90">Production</a>
</div>

<form class="sticky top-0 z-10 mb-4 rounded-2xl border bg-white/70 p-3 shadow-sm" method="GET" action="">
  <div class="grid grid-cols-1 md:grid-cols-5 gap-2">
    <div>
      <label class="block text-xs text-slate-600 mb-1">From</label>
      <input type="date" name="from" value="<?=h($from)?>" class="w-full rounded-xl border px-3 py-2">
    </div>
    <div>
      <label class="block text-xs text-slate-600 mb-1">To</label>
      <input type="date" name="to" value="<?=h($to)?>" class="w-full rounded-xl border px-3 py-2">
    </div>
    <div class="md:col-span-2"></div>
    <div class="flex items-end">
      <button class="px-4 py-2 rounded-xl bg-emerald-600 text-white hover:bg-emerald-700 shadow-sm">Apply</button>
    </div>
  </div>
</form>

<!-- KPI Cards -->
<div class="grid grid-cols-2 md:grid-cols-6 gap-3 mb-4">
  <div class="rounded-xl border p-3">
    <div class="text-[11px] text-slate-500">Green (pcs)</div>
    <div class="text-lg font-semibold"><?=n0($kpi['green_total'] ?? 0)?></div>
  </div>
  <div class="rounded-xl border p-3">
    <div class="text-[11px] text-slate-500">Fired (pcs)</div>
    <div class="text-lg font-semibold"><?=n0($kpi['fired_total'] ?? 0)?></div>
  </div>
  <div class="rounded-xl border p-3">
    <div class="text-[11px] text-slate-500">Yield</div>
    <div class="text-lg font-semibold"><?=n2(($kpi['yield'] ?? 0)*100)?>%</div>
  </div>
  <div class="rounded-xl border p-3">
    <div class="text-[11px] text-slate-500">SFC (kg / 1000)</div>
    <div class="text-lg font-semibold"><?=n2($kpi['sfc'] ?? 0)?></div>
  </div>
  <div class="rounded-xl border p-3">
    <div class="text-[11px] text-slate-500">Breakage %</div>
    <div class="text-lg font-semibold"><?=n2($kpi['breakage_pct'] ?? 0)?>%</div>
  </div>
  <div class="rounded-xl border p-3">
    <div class="text-[11px] text-slate-500">Labor / 1000</div>
    <div class="text-lg font-semibold">৳ <?=n2($kpi['labor_per_1000'] ?? 0)?></div>
  </div>
</div>

<div class="grid grid-cols-1 md:grid-cols-3 gap-3 mb-6">
  <div class="rounded-xl border p-3">
    <div class="text-[11px] text-slate-500">Revenue (range)</div>
    <div class="text-lg font-semibold">৳ <?=n2($kpi['revenue'] ?? 0)?></div>
  </div>
  <div class="rounded-xl border p-3">
    <div class="text-[11px] text-slate-500">Fuel Cost (range)</div>
    <div class="text-lg font-semibold">৳ <?=n2($kpi['fuel_cost'] ?? 0)?></div>
    <div class="text-[11px] text-slate-500 mt-1">Fuel Qty: <?=n2($kpi['fuel_kg'] ?? 0)?> kg</div>
  </div>
  <div class="rounded-xl border p-3">
    <div class="text-[11px] text-slate-500">Gross Margin / 1000</div>
    <div class="text-lg font-semibold">৳ <?=n2($kpi['gross_per_1000'] ?? 0)?></div>
  </div>
</div>

<!-- Per-cycle detail -->
<div class="text-sm font-medium mb-2">Cycles (ended in range)</div>
<div class="overflow-x-auto">
  <table class="min-w-full text-sm border rounded-2xl overflow-hidden">
    <thead class="bg-slate-50">
      <tr>
        <th class="px-3 py-2 text-left">Cycle</th>
        <th class="px-3 py-2 text-left">Start</th>
        <th class="px-3 py-2 text-left">End</th>
        <th class="px-3 py-2 text-right">Green</th>
        <th class="px-3 py-2 text-right">Fired</th>
        <th class="px-3 py-2 text-right">Yield %</th>
        <th class="px-3 py-2 text-right">Breakage %</th>
        <th class="px-3 py-2 text-right">Fuel (kg)</th>
        <th class="px-3 py-2 text-right">SFC kg/1000</th>
        <th class="px-3 py-2 text-left">Type</th>
      </tr>
    </thead>
    <tbody>
      <?php if (!$cycles): ?>
        <tr><td colspan="10" class="px-3 py-8 text-center text-slate-500">No cycles closed in this period.</td></tr>
      <?php else: foreach ($cycles as $r): ?>
        <tr class="border-t hover:bg-slate-50">
          <td class="px-3 py-2"><?=h($r['cycle_no'] ?? '')?></td>
          <td class="px-3 py-2"><?=h($r['start_date'] ?? '')?></td>
          <td class="px-3 py-2"><?=h($r['end_date'] ?? '')?></td>
          <td class="px-3 py-2 text-right"><?=n0($r['green_qty_pcs'] ?? 0)?></td>
          <td class="px-3 py-2 text-right"><?=n0($r['fired_pcs'] ?? 0)?></td>
          <td class="px-3 py-2 text-right"><?=n2((float)($r['yield'] ?? 0)*100)?></td>
          <td class="px-3 py-2 text-right"><?=n2((float)($r['breakage_pct'] ?? 0))?></td>
          <td class="px-3 py-2 text-right"><?=n2($r['fuel_kg'] ?? 0)?></td>
          <td class="px-3 py-2 text-right"><?=n2($r['sfc_kg_per_1000'] ?? 0)?></td>
          <td class="px-3 py-2"><?=h($r['kiln_type'] ?? '')?></td>
        </tr>
      <?php endforeach; endif; ?>
    </tbody>
  </table>
</div>