<?php declare(strict_types=1);
if(!function_exists('h')){function h($v){return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');}}
$base = $module_base ?? '';
?>
<h1 class="text-xl font-semibold mb-4">Create Free Product</h1>

<form method="POST" action="<?= h($base.'/free-products') ?>" class="space-y-6">
  <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
    <div>
      <label class="block text-sm font-medium mb-1">Code</label>
      <input name="code" class="w-full rounded-lg border px-3 py-2" placeholder="Auto if blank">
    </div>
    <div class="md:col-span-2">
      <label class="block text-sm font-medium mb-1">Name</label>
      <input name="name" required class="w-full rounded-lg border px-3 py-2" placeholder="Product name…">
    </div>
  </div>

  <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
    <div>
      <label class="block text-sm font-medium mb-1">Unit</label>
      <input name="unit" class="w-full rounded-lg border px-3 py-2" value="pcs">
    </div>
  </div>

  <div class="flex justify-end">
    <button class="px-4 py-2 rounded-lg bg-emerald-600 text-white hover:bg-emerald-700">Save</button>
  </div>
</form>