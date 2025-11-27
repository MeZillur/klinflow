<?php
declare(strict_types=1);
/**
 * Brands → Show (DETAIL) (CONTENT-ONLY)
 * Inputs: $base, $brand (row from pos_brands)
 */
$h     = fn($x)=>htmlspecialchars((string)$x, ENT_QUOTES, 'UTF-8');
$color = '#228B22';

$id       = (int)($brand['id'] ?? 0);
$name     = $brand['name'] ?? '';
$slug     = $brand['slug'] ?? '';
$isActive = (int)($brand['is_active'] ?? 1) === 1;
$created  = $brand['created_at'] ?? '';
$updated  = $brand['updated_at'] ?? '';
?>
<div class="px-6 py-6 space-y-6">
  <div class="flex items-center justify-between">
    <div>
      <h1 class="text-2xl font-semibold">Brand Details</h1>
      <p class="text-sm text-gray-500">
        Use brands to quickly filter and group products in the sales register.
      </p>
    </div>
    <a href="<?= $base ?>/products"
       class="inline-flex items-center gap-2 px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-700 hover:bg-gray-100 dark:hover:bg-gray-800 text-sm">
      <i class="fa fa-arrow-left"></i> Back to Products
    </a>
  </div>

  <div class="max-w-3xl grid grid-cols-1 md:grid-cols-2 gap-6">
    <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 p-4 space-y-3">
      <h2 class="font-semibold text-sm tracking-wide text-gray-600">BASIC INFO</h2>

      <div>
        <div class="text-xs text-gray-500 uppercase">Brand Name</div>
        <div class="mt-0.5 font-medium text-lg"><?= $h($name) ?></div>
      </div>

      <div>
        <div class="text-xs text-gray-500 uppercase">Slug</div>
        <div class="mt-0.5 font-mono text-sm"><?= $h($slug) ?></div>
      </div>

      <div>
        <div class="text-xs text-gray-500 uppercase">Status</div>
        <div class="mt-1">
          <span class="inline-block px-2 py-1 text-xs rounded-full
                       <?= $isActive ? 'text-white' : 'bg-gray-200 text-gray-700 dark:bg-gray-700 dark:text-gray-200' ?>"
                style="<?= $isActive ? "background: {$color};" : '' ?>">
            <?= $isActive ? 'Active' : 'Inactive' ?>
          </span>
        </div>
      </div>
    </div>

    <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 p-4 space-y-3">
      <h2 class="font-semibold text-sm tracking-wide text-gray-600">META</h2>

      <div>
        <div class="text-xs text-gray-500 uppercase">Brand ID</div>
        <div class="mt-0.5 font-mono text-sm">#<?= $id ?></div>
      </div>

      <div>
        <div class="text-xs text-gray-500 uppercase">Created</div>
        <div class="mt-0.5 text-sm"><?= $h((string)$created) ?: '—' ?></div>
      </div>

      <div>
        <div class="text-xs text-gray-500 uppercase">Last Updated</div>
        <div class="mt-0.5 text-sm"><?= $h((string)$updated) ?: '—' ?></div>
      </div>

      <p class="text-xs text-gray-500 pt-2">
        Tip: you can assign this brand to products from the product create / edit page.
      </p>
    </div>
  </div>
</div>