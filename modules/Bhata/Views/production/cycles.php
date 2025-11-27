<?php declare(strict_types=1); if(!function_exists('h')){function h($v){return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');}} $rows=$rows??[]; $base=$module_base??''; ?>
<h2 class="text-xl font-semibold mb-4">Kiln Cycles</h2>

<div class="rounded border p-3 mb-4">
  <div class="text-sm font-medium mb-2">Open New Cycle</div>
  <form method="POST" action="<?=h($base.'/bhata/production/cycles')?>" class="grid md:grid-cols-4 gap-3">
    <?= function_exists('csrf_field') ? csrf_field() : '' ?>
    <input type="hidden" name="action" value="open">
    <div><label class="block text-sm">Cycle No</label><input name="cycle_no" class="w-full rounded border px-3 py-2" required></div>
    <div><label class="block text-sm">Start Date</label><input type="date" name="start_date" class="w-full rounded border px-3 py-2" value="<?=h(date('Y-m-d'))?>"></div>
    <div><label class="block text-sm">Kiln Type</label>
      <select name="kiln_type" class="w-full rounded border px-3 py-2">
        <?php foreach(['zigzag','hoffman','clamp','other'] as $k): ?>
          <option value="<?=h($k)?>"><?=h(ucfirst($k))?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="flex items-end"><button class="px-4 py-2 rounded bg-emerald-600 text-white">Open</button></div>
  </form>
</div>

<div class="overflow-x-auto">
  <table class="min-w-full text-sm border rounded">
    <thead class="bg-slate-50"><tr>
      <th class="px-3 py-2">Cycle</th><th class="px-3 py-2">Start</th><th class="px-3 py-2">End</th><th class="px-3 py-2">Status</th><th class="px-3 py-2">Close</th>
    </tr></thead>
    <tbody>
    <?php foreach ($rows as $r): ?>
      <tr class="border-t">
        <td class="px-3 py-2"><?=h($r['cycle_no'])?></td>
        <td class="px-3 py-2"><?=h($r['start_date'])?></td>
        <td class="px-3 py-2"><?=h($r['end_date']??'—')?></td>
        <td class="px-3 py-2"><?=h(strtoupper($r['status']))?></td>
        <td class="px-3 py-2">
          <?php if (($r['status'] ?? '') === 'open'): ?>
          <form method="POST" action="<?=h($base.'/bhata/production/cycles')?>" class="grid md:grid-cols-6 gap-2">
            <?= function_exists('csrf_field') ? csrf_field() : '' ?>
            <input type="hidden" name="action" value="close">
            <input type="hidden" name="cycle_id" value="<?= (int)$r['id'] ?>">
            <input type="date" name="end_date" class="rounded border px-2 py-1" value="<?=h(date('Y-m-d'))?>">
            <input type="number" step="0.001" name="fuel_qty_kg" class="rounded border px-2 py-1" placeholder="Fuel kg">
            <input type="number" name="fired_1st_pcs"  class="rounded border px-2 py-1" placeholder="1st pcs">
            <input type="number" name="fired_2nd_pcs"  class="rounded border px-2 py-1" placeholder="2nd pcs">
            <input type="number" name="fired_3rd_pcs"  class="rounded border px-2 py-1" placeholder="3rd pcs">
            <input type="number" name="fired_batta_pcs"class="rounded border px-2 py-1" placeholder="Batta pcs">
            <input type="number" name="breakage_pcs"   class="rounded border px-2 py-1" placeholder="Breakage pcs">
            <button class="px-3 py-1 rounded bg-emerald-600 text-white">Close</button>
          </form>
          <?php else: ?>
            <span class="text-slate-500">Closed</span>
          <?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>