<?php declare(strict_types=1); if(!function_exists('h')){function h($v){return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');}} $base=$module_base??''; ?>
<h2 class="text-xl font-semibold mb-4">New Green Batch</h2>
<form method="POST" action="<?=h($base.'/bhata/production/green')?>" class="space-y-4">
  <?= function_exists('csrf_field') ? csrf_field() : '' ?>
  <div class="grid md:grid-cols-3 gap-3">
    <div><label class="block text-sm">Batch No</label><input name="batch_no" class="w-full rounded border px-3 py-2" required></div>
    <div><label class="block text-sm">Batch Date</label><input type="date" name="batch_date" class="w-full rounded border px-3 py-2" value="<?=h(date('Y-m-d'))?>"></div>
    <div><label class="block text-sm">Location</label><input name="location" class="w-full rounded border px-3 py-2"></div>
  </div>
  <div class="grid md:grid-cols-3 gap-3">
    <div><label class="block text-sm">Prepared By</label><input name="prepared_by" class="w-full rounded border px-3 py-2"></div>
    <div><label class="block text-sm">Qty (pcs)</label><input type="number" min="1" name="qty_pcs" class="w-full rounded border px-3 py-2" required></div>
    <div><label class="block text-sm">Moisture %</label><input type="number" step="0.01" name="moisture_pct" class="w-full rounded border px-3 py-2"></div>
  </div>
  <div><label class="block text-sm">Notes</label><textarea name="notes" class="w-full rounded border px-3 py-2" rows="3"></textarea></div>
  <div class="flex justify-end"><button class="px-4 py-2 rounded bg-emerald-600 text-white">Save</button></div>
</form>