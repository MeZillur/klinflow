<?php
declare(strict_types=1);
/**
 * Inventory → Adjustments (CONTENT-ONLY)
 * Inputs (optional):
 *   $base, $csrf
 *   $rows  optional: recent movement rows with direction='in'|'out' and reason='adjustment'
 *   $page, $pages, $total (optional)
 */
$h = fn($x)=>htmlspecialchars((string)$x, ENT_QUOTES, 'UTF-8');
$brand = '#228B22';
?>
<div class="px-6 py-6 space-y-6">
  <div class="flex items-center justify-between">
    <h1 class="text-2xl font-semibold">Adjustments</h1>
    <a href="<?= $base ?>/inventory/movements"
       class="inline-flex items-center gap-2 px-3 py-2 rounded-lg text-white shadow"
       style="background: <?= $brand ?>;">
      <i class="fa fa-right-left"></i> View Movements
    </a>
  </div>

  <!-- Quick Adjust -->
  <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 p-4">
    <h2 class="font-semibold mb-3">Quick adjust</h2>
    <form method="post" action="<?= $base ?>/inventory/adjust" class="grid grid-cols-1 md:grid-cols-4 gap-3">
      <input type="hidden" name="_csrf" value="<?= $h($csrf ?? '') ?>">
      <div>
        <label class="text-sm text-gray-600 dark:text-gray-400">Product ID</label>
        <input name="product_id" type="number" required min="1"
               class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100">
      </div>
      <div>
        <label class="text-sm text-gray-600 dark:text-gray-400">Delta (e.g. +5 or -2)</label>
        <input name="delta" type="number" step="0.001" required
               class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100">
      </div>
      <div class="md:col-span-2">
        <label class="text-sm text-gray-600 dark:text-gray-400">Reason / Note</label>
        <input name="reason" type="text" placeholder="Manual adjustment"
               class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100">
      </div>
      <div class="md:col-span-4">
        <button type="submit"
                class="inline-flex items-center gap-2 px-4 py-2 rounded-lg text-white"
                style="background: <?= $brand ?>;">
          <i class="fa fa-check"></i> Apply
        </button>
      </div>
    </form>
  </div>

  <!-- Recent Adjustments -->
  <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 overflow-x-auto">
    <table class="min-w-full text-sm">
      <thead class="bg-gray-100 dark:bg-gray-800">
        <tr>
          <th class="px-4 py-3 text-left font-semibold">Date</th>
          <th class="px-4 py-3 text-left font-semibold">Product</th>
          <th class="px-4 py-3 text-center font-semibold">Direction</th>
          <th class="px-4 py-3 text-right font-semibold">Qty</th>
          <th class="px-4 py-3 text-left font-semibold">Note</th>
        </tr>
      </thead>
      <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
      <?php if (empty($rows)): ?>
        <tr><td colspan="5" class="px-4 py-6 text-center text-gray-500">No adjustments yet</td></tr>
      <?php else: foreach ($rows as $r): ?>
        <?php $in = ($r['direction'] ?? '') === 'in'; ?>
        <tr class="hover:bg-gray-50 dark:hover:bg-gray-800">
          <td class="px-4 py-2 whitespace-nowrap"><?= $h(date('Y-m-d H:i', strtotime((string)($r['created_at'] ?? '')))) ?></td>
          <td class="px-4 py-2"><?= $h(($r['sku'] ?? '').' — '.($r['name'] ?? '')) ?></td>
          <td class="px-4 py-2 text-center">
            <span class="inline-block px-2 py-1 text-xs rounded-full <?= $in ? 'text-white' : 'bg-red-100 text-red-700 dark:bg-red-900/50 dark:text-red-300' ?>"
                  style="<?= $in ? "background: {$brand};" : '' ?>">
              <?= $in ? 'IN' : 'OUT' ?>
            </span>
          </td>
          <td class="px-4 py-2 text-right"><?= rtrim(rtrim(number_format((float)($r['qty'] ?? 0), 3, '.', ''), '0'), '.') ?></td>
          <td class="px-4 py-2"><?= $h($r['note'] ?? '') ?></td>
        </tr>
      <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>