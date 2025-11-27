<?php
/** @var array $products */
/** @var array|null $rows */
/** @var string $module_base */

$h = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');

$selected_id   = isset($_GET['product_id']) ? (int)$_GET['product_id'] : 0;
$selected_name = '';
if ($selected_id && !empty($products)) {
  foreach ($products as $p) {
    if ((int)($p['id'] ?? 0) === $selected_id) { $selected_name = (string)($p['name'] ?? ''); break; }
  }
}
?>
<div class="space-y-6 text-[15px] sm:text-base">
  <div class="flex items-center justify-between">
    <h1 class="text-2xl font-semibold text-slate-900 dark:text-slate-100">Stock Adjustment</h1>
    <div class="flex gap-2">
      <a href="<?= $h($module_base) ?>/inventory" class="rounded-lg border border-slate-300 dark:border-slate-700 px-3 py-1.5 text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-800">Back to Inventory</a>
      <a href="<?= $h($module_base) ?>/inventory/aging" class="rounded-lg border border-slate-300 dark:border-slate-700 px-3 py-1.5 text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-800">Aging</a>
    </div>
  </div>

  <p class="text-sm text-slate-600 dark:text-slate-400">
    Positive quantity adds stock; negative quantity removes stock.
  </p>

  <!-- Form -->
  <form action="<?= $h($module_base) ?>/inventory/adjust" method="post"
        class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <input type="hidden" name="csrf_token" value="<?= $h($_SESSION['csrf_token'] ?? '') ?>">

    <!-- Main form (2/3 width) -->
    <div class="lg:col-span-2 rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 p-6 space-y-4">

      <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <!-- Product (KF.lookup) -->
        <div>
          <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Product</label>
          <input id="adj_product_input"
                 class="w-full rounded-lg border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 px-3 py-2 focus:ring-2 focus:ring-emerald-500"
                 placeholder="Search product…"
                 autocomplete="off"
                 data-kf-lookup="products"
                 data-kf-target-id="#adj_product_id"
                 value="<?= $h($selected_name) ?>">
          <input type="hidden" id="adj_product_id" name="product_id" value="<?= $selected_id ?: '' ?>">
        </div>

        <div>
          <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Quantity</label>
          <input type="number" step="0.01" name="qty" required
                 placeholder="e.g. 5 or -3"
                 class="w-full rounded-lg border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 px-3 py-2 focus:ring-2 focus:ring-emerald-500" />
          <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">Use negative numbers to decrease stock.</p>
        </div>
      </div>

      <div>
        <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Note (optional)</label>
        <textarea name="note" rows="2"
                  class="w-full rounded-lg border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 px-3 py-2 focus:ring-2 focus:ring-emerald-500"
                  placeholder="Reason / reference..."></textarea>
      </div>

      <div class="flex justify-end">
        <button type="submit"
                class="inline-flex items-center rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white px-4 py-2 font-medium">
          Save Adjustment
        </button>
      </div>
    </div>

    <!-- Sidebar (1/3 width) -->
    <div class="rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 p-6">
      <h2 class="font-medium text-slate-900 dark:text-slate-100 mb-2">Recent Adjustments</h2>
      <p class="text-xs text-slate-500 dark:text-slate-400 mb-3">Last 10 stock moves (adjust/damage)</p>

      <?php if (empty($rows)): ?>
        <p class="text-sm text-slate-500 dark:text-slate-400">No recent entries.</p>
      <?php else: ?>
        <ul class="divide-y divide-slate-200 dark:divide-slate-700 text-sm">
          <?php foreach ($rows as $r): ?>
            <li class="py-2 flex justify-between">
              <div>
                <div class="text-slate-900 dark:text-slate-100 font-medium"><?= $h($r['product_name'] ?? '') ?></div>
                <div class="text-xs text-slate-500 dark:text-slate-400"><?= $h($r['created_at'] ?? '') ?></div>
              </div>
              <div class="font-mono text-right <?= ($r['in_qty'] ?? 0) > 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-rose-600 dark:text-rose-400' ?>">
                <?= ($r['in_qty'] ?? 0) > 0 ? '+' : '' ?><?= number_format((float)(($r['in_qty'] ?? 0) - ($r['out_qty'] ?? 0)), 2) ?>
              </div>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </div>
  </form>
</div>

<script>
  // If your global bootstrap exposes KF.rescan, trigger it so the product lookup binds immediately.
  if (window.KF && typeof KF.rescan === 'function') {
    KF.rescan(document);
  }
</script>