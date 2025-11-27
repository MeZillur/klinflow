<?php
declare(strict_types=1);
/** @var string $module_base */
/** @var array  $org */
$h = fn($s)=>htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
?>
<div class="max-w-5xl mx-auto p-4">
  <div class="flex items-center justify-between mb-4">
    <h1 class="text-2xl font-semibold">Inventory — Items</h1>
    <a href="<?= $h($module_base.'/inventory/batches') ?>" class="inline-flex items-center gap-2 px-3 py-2 rounded-lg border">
      <i class="fa-solid fa-layer-group"></i><span>Batches</span>
    </a>
  </div>

  <div class="rounded-xl border p-4 bg-white dark:bg-gray-900 dark:border-gray-700">
    <div class="flex items-center gap-2 text-sm text-gray-600 dark:text-gray-300">
      <i class="fa-solid fa-circle-info" style="color:#228B22"></i>
      <span>This is a minimal front page to confirm routing & shell. You can flesh it out next.</span>
    </div>

    <div class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
      <a class="block p-4 rounded-lg border hover:bg-gray-50 dark:hover:bg-gray-800"
         href="<?= $h($module_base.'/inventory/stock-moves') ?>">
        <div class="font-medium">Stock Moves</div>
        <div class="text-sm text-gray-500">View all in/out transactions</div>
      </a>
      <a class="block p-4 rounded-lg border hover:bg-gray-50 dark:hover:bg-gray-800"
         href="<?= $h($module_base.'/inventory/low-stock') ?>">
        <div class="font-medium">Low Stock</div>
        <div class="text-sm text-gray-500">Reorder suggestions</div>
      </a>
      <a class="block p-4 rounded-lg border hover:bg-gray-50 dark:hover:bg-gray-800"
         href="<?= $h($module_base.'/inventory/near-expiry') ?>">
        <div class="font-medium">Near Expiry</div>
        <div class="text-sm text-gray-500">Batches close to expiry</div>
      </a>
    </div>
  </div>
</div>