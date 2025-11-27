<?php
/** @var array $products */
/** @var array $rows */
/** @var string $module_base */
/** @var array|null $ctx */

$h = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
?>
<div class="space-y-6">
  <h1 class="text-2xl font-semibold text-slate-900 dark:text-slate-100">Damage Entry</h1>

  <!-- Form -->
  <form action="<?= $h($module_base) ?>/inventory/damage" method="post"
        class="rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 p-6 space-y-4">
    <input type="hidden" name="csrf_token" value="<?= $h($_SESSION['csrf_token'] ?? '') ?>">
    <div>
      <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Product</label>
      <select name="product_id" required
              class="w-full rounded-lg border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 px-3 py-2 focus:ring-2 focus:ring-emerald-500">
        <option value="">-- Select Product --</option>
        <?php foreach ($products as $p): ?>
          <option value="<?= (int)$p['id'] ?>" <?= (isset($_GET['product_id']) && $_GET['product_id']==$p['id'])?'selected':'' ?>>
            <?= $h($p['name'] ?? '') ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>

    <div>
      <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Quantity</label>
      <input type="number" step="0.01" min="0.01" name="qty" required
             class="w-full rounded-lg border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 px-3 py-2 focus:ring-2 focus:ring-emerald-500" />
    </div>

    <div>
      <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Note</label>
      <textarea name="note" rows="2"
                class="w-full rounded-lg border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 px-3 py-2 focus:ring-2 focus:ring-emerald-500"
                placeholder="Optional remarks..."></textarea>
    </div>

    <div class="flex items-center justify-end">
      <button type="submit"
              class="inline-flex items-center rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white px-4 py-2 font-medium">
        Save Damage Entry
      </button>
    </div>
  </form>

  <!-- Table -->
  <div class="rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800">
    <div class="px-5 py-4 flex items-center justify-between">
      <div class="text-slate-900 dark:text-slate-100 font-medium">Recent Damage Records</div>
      <div class="text-xs text-slate-500 dark:text-slate-400">
        <?= count($rows) ?> record<?= count($rows)===1?'':'s' ?>
      </div>
    </div>
    <div class="overflow-x-auto pb-4">
      <table class="min-w-full text-sm">
        <thead class="bg-slate-50 dark:bg-slate-900/40">
          <tr class="text-left text-slate-600 dark:text-slate-400">
            <th class="py-2 px-4">Date</th>
            <th class="py-2 px-4">Product</th>
            <th class="py-2 px-4 text-right">Qty</th>
            <th class="py-2 px-4">Note</th>
          </tr>
        </thead>
        <tbody class="text-slate-800 dark:text-slate-100">
          <?php if (!$rows): ?>
            <tr>
              <td colspan="4" class="py-6 text-center text-slate-500 dark:text-slate-400">No damage records found.</td>
            </tr>
          <?php else: ?>
            <?php foreach ($rows as $r): ?>
              <tr class="border-t border-slate-200 dark:border-slate-700">
                <td class="py-2 px-4 whitespace-nowrap"><?= $h($r['created_at'] ?? '') ?></td>
                <td class="py-2 px-4"><?= $h($r['product_name'] ?? '') ?></td>
                <td class="py-2 px-4 text-right text-rose-600 dark:text-rose-400 font-mono"><?= number_format((float)($r['qty'] ?? 0),2) ?></td>
                <td class="py-2 px-4"><?= $h($r['note'] ?? '') ?></td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>