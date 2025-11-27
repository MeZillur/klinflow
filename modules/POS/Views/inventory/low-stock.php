<?php
declare(strict_types=1);
/**
 * Inventory → Low Stock (CONTENT-ONLY)
 * Inputs: $rows, $base, $csrf
 */
$h = fn($x)=>htmlspecialchars((string)$x, ENT_QUOTES, 'UTF-8');
$brand = '#228B22';
?>
<div class="px-6 py-6 space-y-6">
  <div class="flex items-center justify-between">
    <h1 class="text-2xl font-semibold">Low Stock</h1>
    <a href="<?= $base ?>/inventory?stat=low"
       class="inline-flex items-center gap-2 px-3 py-2 rounded-lg text-white"
       style="background: <?= $brand ?>;">
      <i class="fa fa-filter"></i> Open in Inventory
    </a>
  </div>

  <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 overflow-x-auto">
    <table class="min-w-full text-sm">
      <thead class="bg-gray-100 dark:bg-gray-800">
        <tr>
          <th class="px-4 py-3 text-left font-semibold">SKU</th>
          <th class="px-4 py-3 text-left font-semibold">Name</th>
          <th class="px-4 py-3 text-left font-semibold">Category</th>
          <th class="px-4 py-3 text-right font-semibold">Stock</th>
          <th class="px-4 py-3 text-right font-semibold">Low Threshold</th>
          <th class="px-4 py-3 text-center font-semibold">Status</th>
          <th class="px-4 py-3 text-right font-semibold">Actions</th>
        </tr>
      </thead>
      <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
      <?php if (empty($rows)): ?>
        <tr>
          <td colspan="7" class="px-4 py-6 text-center text-gray-500">
            No low stock rows supplied. Use the button above to view via Inventory filter.
          </td>
        </tr>
      <?php else: foreach ($rows as $r): ?>
        <?php $st = (string)($r['stock_status'] ?? 'ok'); ?>
        <tr class="hover:bg-gray-50 dark:hover:bg-gray-800">
          <td class="px-4 py-2 font-mono"><?= $h($r['sku'] ?? '') ?></td>
          <td class="px-4 py-2"><?= $h($r['name'] ?? '') ?></td>
          <td class="px-4 py-2"><?= $h($r['category_name'] ?? '—') ?></td>
          <td class="px-4 py-2 text-right"><?= number_format((float)($r['stock_on_hand_like'] ?? 0)) ?></td>
          <td class="px-4 py-2 text-right"><?= number_format((float)($r['low_stock_threshold_like'] ?? 0)) ?></td>
          <td class="px-4 py-2 text-center">
            <span class="inline-block px-2 py-1 text-xs rounded-full <?= $st==='low'
              ? 'bg-yellow-100 text-yellow-700 dark:bg-yellow-900/50 dark:text-yellow-300'
              : 'bg-red-100 text-red-700 dark:bg-red-900/50 dark:text-red-300' ?>">
              <?= strtoupper($st==='low' ? 'LOW' : 'OUT') ?>
            </span>
          </td>
          <td class="px-4 py-2 text-right space-x-2">
            <form method="post" action="<?= $base ?>/inventory/adjust" class="inline">
              <input type="hidden" name="_csrf" value="<?= $h($csrf ?? '') ?>">
              <input type="hidden" name="product_id" value="<?= (int)($r['id'] ?? 0) ?>">
              <input type="hidden" name="delta" value="1">
              <button class="text-white px-3 py-1 rounded-md" style="background: <?= $brand ?>;">+1</button>
            </form>
          </td>
        </tr>
      <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>