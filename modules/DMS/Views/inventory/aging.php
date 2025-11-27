<?php declare(strict_types=1);

/**
 * Inventory Aging View
 * Shows per-product stock, arrival, expiry, and age buckets.
 *
 * Expected $rows = [
 *   ['name'=>'...', 'sku'=>'...', 'stock_qty'=>123, 'arrival_date'=>'2025-09-01', 'expiry_date'=>'2025-12-31']
 * ]
 */

/** Safe HTML escape helper */
$h = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');

/** Calculate simple age bucket */
function ageBucket(?string $arrival): string {
  if (!$arrival) return 'n/a';
  $days = (int)floor((time() - strtotime($arrival)) / 86400);
  if ($days < 0) return 'n/a';
  if ($days <= 30) return '0–30d';
  if ($days <= 60) return '31–60d';
  if ($days <= 90) return '61–90d';
  return '90+d';
}
?>
<h1 class="text-xl font-semibold mb-4">Inventory Aging</h1>

<div class="overflow-x-auto">
  <table class="min-w-full text-sm border rounded-lg overflow-hidden">
    <thead class="bg-slate-50">
      <tr>
        <th class="px-3 py-2 text-left">Product</th>
        <th class="px-3 py-2 text-left">SKU</th>
        <th class="px-3 py-2 text-right">Stock</th>
        <th class="px-3 py-2 text-left">Arrival</th>
        <th class="px-3 py-2 text-left">Expiry</th>
        <th class="px-3 py-2 text-left">Age</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($rows as $r): ?>
        <tr class="border-t">
          <td class="px-3 py-2"><?= $h($r['name'] ?? '') ?></td>
          <td class="px-3 py-2"><?= $h($r['sku'] ?? '') ?></td>
          <td class="px-3 py-2 text-right"><?= number_format((float)($r['stock_qty'] ?? 0),2) ?></td>
          <td class="px-3 py-2"><?= $h($r['arrival_date'] ?? '') ?></td>
          <td class="px-3 py-2"><?= $h($r['expiry_date'] ?? '') ?></td>
          <td class="px-3 py-2"><?= $h(ageBucket($r['arrival_date'] ?? null)) ?></td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$rows): ?>
        <tr><td colspan="6" class="px-3 py-6 text-center text-slate-500">No data.</td></tr>
      <?php endif; ?>
    </tbody>
  </table>
</div>