<?php declare(strict_types=1);
if(!function_exists('h')){function h($v){return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');}}
$base = $module_base ?? '';
?>
<h1 class="text-xl font-semibold mb-4">Receive Free Product</h1>

<form method="POST" action="<?= h($base.'/free-products/receive') ?>" class="space-y-6">
  <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
    <div class="md:col-span-2">
      <label class="block text-sm font-medium mb-1">Product</label>
      <input type="number" name="product_id" class="w-full rounded-lg border px-3 py-2" placeholder="Product ID (typeahead hook ready)">
      <p class="text-xs text-slate-500 mt-1">Wire global typeahead later.</p>
    </div>
    <div>
      <label class="block text-sm font-medium mb-1">Quantity</label>
      <input type="number" step="0.01" min="0" name="qty" class="w-full rounded-lg border px-3 py-2" required>
    </div>
  </div>

  <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
    <div>
      <label class="block text-sm font-medium mb-1">Reference</label>
      <input name="ref_no" class="w-full rounded-lg border px-3 py-2" placeholder="Optional ref…">
    </div>
    <div class="md:col-span-2">
      <label class="block text-sm font-medium mb-1">Note</label>
      <input name="note" class="w-full rounded-lg border px-3 py-2" placeholder="Optional note…">
    </div>
  </div>

  <div class="flex justify-end">
    <button class="px-4 py-2 rounded-lg bg-emerald-600 text-white hover:bg-emerald-700">Receive</button>
  </div>
</form>