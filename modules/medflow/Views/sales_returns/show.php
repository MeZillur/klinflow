<?php
declare(strict_types=1);
$h = fn($v)=>htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
$base = (string)($module_base ?? '');
$rid  = (int)($head['id'] ?? 0);
?>
<div class="max-w-6xl mx-auto p-4 space-y-6">
  <div class="flex items-center justify-between">
    <div>
      <div class="text-sm text-gray-500">Return</div>
      <h1 class="text-2xl font-semibold">#<?= $rid ?> <span class="text-gray-400">for</span> Sale #<?= (int)($head['sale_id'] ?? 0) ?></h1>
      <div class="mt-1">
        <span class="px-2 py-0.5 rounded-full text-xs bg-emerald-100 text-emerald-700"><?= $h((string)($head['status'] ?? 'draft')) ?></span>
      </div>
    </div>
    <div class="flex items-center gap-2">
      <form method="post" action="<?= $h($base.'/sales/returns/'.$rid.'/confirm') ?>">
        <button class="px-3 py-2 rounded-lg text-white" style="background:#10b981">Confirm</button>
      </form>
      <form method="post" action="<?= $h($base.'/sales/returns/'.$rid.'/cancel') ?>">
        <button class="px-3 py-2 rounded-lg text-white" style="background:#ef4444">Cancel</button>
      </form>
    </div>
  </div>

  <!-- Current items -->
  <div class="bg-white dark:bg-gray-800 border dark:border-gray-700 rounded-xl overflow-hidden">
    <table class="w-full text-sm">
      <thead class="bg-gray-50 dark:bg-gray-700/40 text-gray-600 dark:text-gray-300">
        <tr>
          <th class="p-2 text-left">SKU</th>
          <th class="p-2 text-left">Name</th>
          <th class="p-2 text-right">Qty</th>
          <th class="p-2 text-right">Price</th>
          <th class="p-2 text-right">Line Total</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach (($items ?? []) as $it): ?>
          <tr class="border-t dark:border-gray-700">
            <td class="p-2"><?= $h((string)($it['sku'] ?? '')) ?></td>
            <td class="p-2"><?= $h((string)($it['name'] ?? '')) ?></td>
            <td class="p-2 text-right"><?= (float)($it['qty_returned'] ?? 0) ?></td>
            <td class="p-2 text-right"><?= number_format((float)($it['price'] ?? 0),2) ?></td>
            <td class="p-2 text-right"><?= number_format((float)($it['price'] * $it['qty_returned']),2) ?></td>
          </tr>
        <?php endforeach; ?>
        <?php if (empty($items)): ?>
          <tr><td colspan="5" class="p-4 text-center text-gray-500">No items yet.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>

  <!-- Add item -->
  <div class="bg-white dark:bg-gray-800 border dark:border-gray-700 rounded-xl p-4">
    <h2 class="font-semibold mb-3">Add Item</h2>
    <form method="post" action="<?= $h($base.'/sales/returns/'.$rid) ?>" class="grid md:grid-cols-4 gap-3">
      <div class="md:col-span-2">
        <label class="block text-sm mb-1">Sale Items</label>
        <select name="item_id" required class="w-full border rounded-lg px-3 py-2 dark:bg-gray-900 dark:border-gray-700">
          <option value="">Select item…</option>
          <?php foreach (($sourceItems ?? []) as $s): ?>
            <option value="<?= (int)$s['item_id'] ?>">
              <?= $h((string)$s['sku']) ?> — <?= $h((string)$s['name']) ?> (sold: <?= (float)$s['qty'] ?> @ <?= number_format((float)$s['price_unit'],2) ?>)
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div>
        <label class="block text-sm mb-1">Qty to return</label>
        <input type="number" step="0.01" min="0.01" name="qty" required
               class="w-full border rounded-lg px-3 py-2 dark:bg-gray-900 dark:border-gray-700">
      </div>
      <div>
        <label class="block text-sm mb-1">Refund price</label>
        <input type="number" step="0.01" min="0" name="price" required
               class="w-full border rounded-lg px-3 py-2 dark:bg-gray-900 dark:border-gray-700"
               placeholder="Defaults to sale unit price">
      </div>
      <div class="md:col-span-4">
        <button class="inline-flex items-center gap-2 px-4 py-2 rounded-lg text-white" style="background:#10b981">
          <i class="fa fa-plus"></i><span>Add line</span>
        </button>
      </div>
    </form>
  </div>
</div>