<?php
declare(strict_types=1);
/**
 * Inventory → Aging (CONTENT-ONLY)
 * Optional Inputs: $rows (each: sku,name,days_bucket,qty), $base
 */
$h = fn($x)=>htmlspecialchars((string)$x, ENT_QUOTES, 'UTF-8');
$brand = '#228B22';
?>
<div class="px-6 py-6 space-y-6">
  <div class="flex items-center justify-between">
    <h1 class="text-2xl font-semibold">Inventory Aging</h1>
    <div class="flex items-center gap-2">
      <a href="<?= $base ?>/inventory"
         class="inline-flex items-center gap-2 px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-700 hover:bg-gray-100 dark:hover:bg-gray-800">
        <i class="fa fa-warehouse"></i> Inventory
      </a>
      <button type="button" onclick="window.print()"
              class="inline-flex items-center gap-2 px-3 py-2 rounded-lg text-white"
              style="background: <?= $brand ?>;">
        <i class="fa fa-print"></i> Print
      </button>
    </div>
  </div>

  <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 overflow-x-auto">
    <table class="min-w-full text-sm">
      <thead class="bg-gray-100 dark:bg-gray-800">
        <tr>
          <th class="px-4 py-3 text-left font-semibold">SKU</th>
          <th class="px-4 py-3 text-left font-semibold">Name</th>
          <th class="px-4 py-3 text-left font-semibold">Bucket</th>
          <th class="px-4 py-3 text-right font-semibold">Qty</th>
        </tr>
      </thead>
      <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
      <?php if (empty($rows)): ?>
        <tr>
          <td colspan="4" class="px-4 py-6 text-center text-gray-500">
            No aging data yet. Generate by logging movements and computing days since last move.
          </td>
        </tr>
      <?php else: foreach ($rows as $r): ?>
        <tr class="hover:bg-gray-50 dark:hover:bg-gray-800">
          <td class="px-4 py-2 font-mono"><?= $h($r['sku'] ?? '') ?></td>
          <td class="px-4 py-2"><?= $h($r['name'] ?? '') ?></td>
          <td class="px-4 py-2"><?= $h($r['days_bucket'] ?? '—') ?></td>
          <td class="px-4 py-2 text-right"><?= rtrim(rtrim(number_format((float)($r['qty'] ?? 0), 3, '.', ''), '0'), '.') ?></td>
        </tr>
      <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>