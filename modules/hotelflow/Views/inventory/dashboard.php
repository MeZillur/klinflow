<?php
$h=fn($v)=>htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');
$base=rtrim((string)($module_base ?? '/apps/hotelflow'),'/');
?>
<div class="max-w-[1100px] mx-auto space-y-6">
  <div class="flex items-center justify-between">
    <h1 class="text-2xl font-extrabold">Inventory</h1>
    <div class="flex gap-2">
      <a class="px-3 py-2 rounded-lg border hover:bg-slate-50" href="<?= $h($base) ?>/inventory/products">Products</a>
      <a class="px-3 py-2 rounded-lg border hover:bg-slate-50" href="<?= $h($base) ?>/inventory/categories">Categories</a>
      <a class="px-3 py-2 rounded-lg border hover:bg-slate-50" href="<?= $h($base) ?>/inventory/purchases">Purchases</a>
      <a class="px-3 py-2 rounded-lg border hover:bg-slate-50" href="<?= $h($base) ?>/inventory/stock">Stock</a>
    </div>
  </div>

  <div class="grid md:grid-cols-2 gap-4">
    <div class="rounded-xl border p-4">
      <div class="font-semibold mb-2">Recent Products</div>
      <div class="divide-y">
        <?php if(!$products): ?><div class="text-slate-500 text-sm">No products yet.</div><?php endif; ?>
        <?php foreach($products as $p): ?>
          <div class="py-2 flex justify-between">
            <div class="truncate"><span class="text-slate-500"><?= $h((string)$p['sku']) ?></span> • <?= $h((string)$p['name']) ?></div>
            <div class="text-slate-500"><?= $h((string)$p['unit']) ?></div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
    <div class="rounded-xl border p-4">
      <div class="font-semibold mb-2">Recent Purchases</div>
      <div class="divide-y">
        <?php if(!$purchases): ?><div class="text-slate-500 text-sm">No purchases yet.</div><?php endif; ?>
        <?php foreach($purchases as $po): ?>
          <div class="py-2 flex justify-between">
            <div>#<?= (int)$po['id'] ?> • <?= $h((string)$po['reference']) ?> — <?= $h((string)$po['supplier']) ?></div>
            <div class="text-right"><?= number_format((float)($po['total']??0),2) ?> <span class="text-slate-500"><?= $h((string)($po['currency']??'')) ?></span></div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
</div>