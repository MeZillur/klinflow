<?php
declare(strict_types=1);
/** @var array $product @var array $products @var string $base @var string $csrf */
$h = fn($x)=>htmlspecialchars((string)$x, ENT_QUOTES, 'UTF-8');
?>
<div class="px-6 py-6 space-y-6">
  <div class="flex items-center justify-between">
    <h1 class="text-2xl font-semibold">Adjust Inventory</h1>
    <a href="<?= $base ?>/inventory"
       class="inline-flex items-center gap-2 px-3 py-2 rounded-lg border border-gray-300 text-sm hover:bg-gray-100">
      <i class="fa fa-arrow-left"></i> Back to Inventory
    </a>
  </div>

  <form method="post" action="<?= $base ?>/inventory/adjust" class="max-w-xl space-y-4">
    <input type="hidden" name="_csrf" value="<?= $h($csrf) ?>">

    <div class="rounded-xl border border-gray-200 bg-white p-4 space-y-4">
      <h2 class="font-semibold text-sm text-gray-600">Product</h2>

      <?php if ($product): ?>
        <p class="text-sm">
          <strong><?= $h($product['name']) ?></strong><br>
          <span class="text-gray-500 text-xs">SKU: <?= $h($product['sku']) ?></span><br>
          <span class="text-gray-500 text-xs">Current stock: <?= $h($product['stock'] ?? '0') ?></span>
        </p>
        <input type="hidden" name="product_id" value="<?= (int)$product['id'] ?>">
      <?php else: ?>
        <label class="block text-sm mb-1">Product</label>
        <select name="product_id" required class="w-full border-gray-300 rounded-lg text-sm">
          <option value="">— Choose product —</option>
          <?php foreach ($products as $p): ?>
            <option value="<?= (int)$p['id'] ?>">
              <?= $h($p['name']) ?> (<?= $h($p['sku']) ?>)
            </option>
          <?php endforeach; ?>
        </select>
      <?php endif; ?>
    </div>

    <div class="rounded-xl border border-gray-200 bg-white p-4 space-y-4">
      <h2 class="font-semibold text-sm text-gray-600">Adjustment</h2>

      <label class="block text-sm mb-1">Quantity change</label>
      <input
        type="number"
        name="delta"
        step="0.001"
        class="w-full border-gray-300 rounded-lg text-sm"
        placeholder="e.g. +10 or -5"
        required
      >
      <p class="text-xs text-gray-500">
        Use positive numbers to add stock (e.g. 10) and negative numbers to reduce stock (e.g. -3).
      </p>

      <label class="block text-sm mb-1">Reason (optional)</label>
      <input
        type="text"
        name="reason"
        class="w-full border-gray-300 rounded-lg text-sm"
        placeholder="Stock count adjustment, damage, etc."
      >
    </div>

    <div class="flex items-center gap-2">
      <button type="submit" class="px-4 py-2 rounded-lg text-sm font-medium text-white" style="background:#228B22;">
        <i class="fa fa-check"></i> Apply Adjustment
      </button>
      <a href="<?= $base ?>/inventory"
         class="px-4 py-2 rounded-lg border border-gray-300 text-sm hover:bg-gray-100">
        Cancel
      </a>
    </div>
  </form>
</div>