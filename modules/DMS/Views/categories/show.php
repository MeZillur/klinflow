<?php
declare(strict_types=1);
/** @var array $cat @var int $productCount @var int $supplierCount @var array $recent */
$cat    = $cat ?? [];
$base   = $module_base ?? '';
$cid    = (int)($cat['id'] ?? 0);
$code   = (string)($cat['code'] ?? '');
$name   = (string)($cat['name'] ?? '');
$notes  = (string)($cat['notes'] ?? '');
$active = (int)($cat['is_active'] ?? 1);

$h = fn($s)=>htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
?>
<div class="max-w-6xl mx-auto space-y-6">
  <div class="flex items-start justify-between gap-4">
    <div>
      <h1 class="text-2xl font-semibold"><?= $h($name ?: 'Category') ?></h1>
      <div class="mt-2 flex items-center gap-3">
        <?php if ($code): ?>
          <span class="text-sm text-gray-600">Code:</span>
          <span class="font-mono px-2 py-0.5 rounded bg-gray-100 dark:bg-gray-800"><?= $h($code) ?></span>
        <?php endif; ?>
        <span class="ml-2 text-xs px-2 py-1 rounded-full <?= $active? 'bg-emerald-100 text-emerald-700':'bg-gray-100 text-gray-600' ?>">
          <?= $active? 'Active':'Inactive' ?>
        </span>
      </div>
    </div>

    <div class="flex items-center gap-2">
      <a href="<?= $h($base.'/categories/'.$cid.'/edit') ?>"
         class="px-3 py-2 rounded-lg border">Edit</a>
      <a href="<?= $h($base.'/products/create?category_id='.$cid) ?>"
         class="px-4 py-2 rounded-lg bg-emerald-600 text-white hover:bg-emerald-700">New Product</a>
    </div>
  </div>

  <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
    <div class="rounded-xl border bg-gray-50 dark:bg-gray-900/40 p-4">
      <div class="text-sm text-gray-500 mb-1">Products</div>
      <div class="text-2xl font-semibold"><?= (int)$productCount ?></div>
    </div>
    <div class="rounded-xl border bg-gray-50 dark:bg-gray-900/40 p-4">
      <div class="text-sm text-gray-500 mb-1">Suppliers (via purchases)</div>
      <div class="text-2xl font-semibold"><?= (int)$supplierCount ?></div>
    </div>
    <div class="rounded-xl border bg-gray-50 dark:bg-gray-900/40 p-4">
      <div class="text-sm text-gray-500 mb-1">Updated</div>
      <div class="text-lg font-semibold">
        <?= $h(substr((string)($cat['updated_at'] ?? $cat['created_at'] ?? ''),0,16) ?: '—') ?>
      </div>
    </div>
  </div>

  <div class="rounded-xl border">
    <div class="px-4 py-3 bg-gray-50 dark:bg-gray-900/40 border-b">
      <div class="text-sm font-medium">Notes</div>
    </div>
    <div class="p-4 text-gray-800 dark:text-gray-200">
      <?= $notes !== '' ? nl2br($h($notes)) : '<span class="text-gray-500">—</span>' ?>
    </div>
  </div>

  <div class="rounded-xl border">
    <div class="px-4 py-3 bg-gray-50 dark:bg-gray-900/40 border-b flex items-center justify-between">
      <div class="text-sm font-medium">Recent Products</div>
      <a class="text-sm text-blue-600 hover:underline" href="<?= $h($base.'/products?q=&category_id='.$cid) ?>">View all</a>
    </div>
    <div class="p-2">
      <?php if (empty($recent)): ?>
        <div class="px-3 py-6 text-center text-gray-500">No products in this category yet.</div>
      <?php else: ?>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-2">
          <?php foreach ($recent as $p): ?>
            <a href="<?= $h($base.'/products/'.$p['id']) ?>"
               class="block rounded-lg border px-3 py-2 hover:bg-gray-50 dark:hover:bg-gray-800/40">
              <div class="font-medium truncate"><?= $h($p['name'] ?? '') ?></div>
              <div class="text-xs text-gray-500 flex items-center gap-2">
                <span class="font-mono"><?= $h($p['code'] ?? '—') ?></span>
                <?php if (!empty($p['brand'])): ?><span>· <?= $h($p['brand']) ?></span><?php endif; ?>
                <?php if (!empty($p['model'])): ?><span>· <?= $h($p['model']) ?></span><?php endif; ?>
                <?php if (!empty($p['barcode'])): ?><span>· <?= $h($p['barcode']) ?></span><?php endif; ?>
              </div>
              <div class="mt-1 text-[11px]">
                <span class="px-1.5 py-0.5 rounded <?= ($p['status']??'')==='active' ? 'bg-emerald-100 text-emerald-700':'bg-gray-100 text-gray-600' ?>">
                  <?= $h($p['status'] ?? 'active') ?>
                </span>
              </div>
            </a>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
  </div>

  <div class="flex gap-2">
    <a href="<?= $h($base.'/categories') ?>" class="px-3 py-2 rounded-lg border">Back</a>
  </div>
</div>