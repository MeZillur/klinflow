<?php declare(strict_types=1);
/** @var array $rows @var array $filters @var float $tot_qty @var string $module_base */
$h = fn($v)=>htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
?>
<div class="space-y-4">
  <h1 class="text-2xl font-semibold">Damage Reports</h1>

  <form method="get" class="flex flex-wrap gap-2 items-end">
    <input type="hidden" name="route" value="/apps/dms/reports/damage">
    <div>
      <label class="text-xs text-slate-500 block">From</label>
      <input type="date" name="from" value="<?= $h($filters['from']) ?>" class="border rounded px-2 py-1">
    </div>
    <div>
      <label class="text-xs text-slate-500 block">To</label>
      <input type="date" name="to" value="<?= $h($filters['to']) ?>" class="border rounded px-2 py-1">
    </div>
    <div class="flex-1 min-w-[220px]">
      <label class="text-xs text-slate-500 block">Search</label>
      <input type="text" name="q" value="<?= $h($filters['q']) ?>" placeholder="SKU / product / note…" class="w-full border rounded px-2 py-1">
    </div>
    <button class="px-3 py-2 rounded bg-emerald-600 text-white">Apply</button>
  </form>

  <div class="rounded-xl border overflow-x-auto">
    <table class="min-w-full text-sm">
      <thead class="bg-slate-50">
        <tr>
          <th class="px-3 py-2 text-left">Date</th>
          <th class="px-3 py-2 text-left">SKU</th>
          <th class="px-3 py-2 text-left">Product</th>
          <th class="px-3 py-2 text-right">Qty (out)</th>
          <th class="px-3 py-2 text-left">Note</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($rows as $r): ?>
        <tr class="border-t">
          <td class="px-3 py-2 whitespace-nowrap"><?= $h(substr((string)($r['move_at'] ?? ''),0,19)) ?></td>
          <td class="px-3 py-2"><?= $h($r['sku'] ?? '') ?></td>
          <td class="px-3 py-2"><?= $h($r['product_name'] ?? '') ?></td>
          <td class="px-3 py-2 text-right text-rose-600"><?= number_format((float)($r['qty_out'] ?? 0),2) ?></td>
          <td class="px-3 py-2"><?= $h($r['memo'] ?? '') ?></td>
        </tr>
        <?php endforeach; ?>
        <?php if (!$rows): ?>
          <tr><td colspan="5" class="px-3 py-8 text-center text-slate-500">No damage within range.</td></tr>
        <?php endif; ?>
      </tbody>
      <tfoot class="bg-slate-50">
        <tr>
          <td class="px-3 py-2 font-medium" colspan="3">Total</td>
          <td class="px-3 py-2 text-right font-medium"><?= number_format($tot_qty,2) ?></td>
          <td></td>
        </tr>
      </tfoot>
    </table>
  </div>
</div>