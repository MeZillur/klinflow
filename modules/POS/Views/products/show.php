<?php
declare(strict_types=1);
/**
 * Products → Show (DETAIL) (CONTENT-ONLY)
 * Inputs: $base, $prod (row from DB: p.*, category_name, brand_name)
 * Brand: #228B22
 */
$h     = fn($x) => htmlspecialchars((string)$x, ENT_QUOTES, 'UTF-8');
$brand = '#228B22';

/**
 * Simple money formatter – NO cents-magic.
 * We store real decimals (not *_cents), so just format them.
 */
$fmtMoney = function ($v, int $decimals = 2): string {
    if (!is_numeric($v)) {
        $v = 0;
    }
    $f = (float)$v;
    return number_format($f, $decimals, '.', ',');
};

// convenience
$id        = (int)($prod['id'] ?? 0);
$sku       = $prod['sku'] ?? '';
$barcode   = $prod['barcode'] ?? '';
$name      = $prod['name'] ?? '';
$category  = $prod['category_name'] ?? ('ID: '.((int)($prod['category_id'] ?? 0)));
$brandName = $prod['brand_name'] ?? '—';
$unit      = $prod['unit'] ?? '';
$taxRate   = $prod['tax_rate'] ?? '0';
$cost      = $prod['cost_price'] ?? $prod['cost_cents'] ?? 0;
$sale      = $prod['sale_price'] ?? $prod['price_cents'] ?? 0;
$track     = (int)($prod['track_stock'] ?? 0) === 1;
$lowStock  = (int)($prod['low_stock_threshold'] ?? 0);
$isActive  = (int)($prod['is_active'] ?? 1) === 1;
?>
<div class="px-6 py-6 space-y-6">
  <!-- Header -->
  <div class="flex items-center justify-between">
    <div>
      <h1 class="text-2xl font-semibold mb-1">Product Details</h1>
      <p class="text-sm text-gray-500">
        SKU <?= $h($sku) ?> • Changes here affect new sales immediately.
      </p>
    </div>
    <div class="flex items-center gap-2">
      <a href="<?= $base ?>/products"
         class="inline-flex items-center gap-2 px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-700 hover:bg-gray-100 dark:hover:bg-gray-800 text-sm">
        <i class="fa fa-arrow-left"></i> Back
      </a>
      <a href="<?= $base ?>/products/<?= $id ?>/edit"
         class="inline-flex items-center gap-2 px-3 py-2 rounded-lg text-sm text-white"
         style="background: <?= $brand ?>;">
        <i class="fa fa-pen-to-square"></i> Edit
      </a>
    </div>
  </div>

  <!-- Content grid -->
  <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
    <!-- Basics -->
    <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 p-4 space-y-2">
      <h2 class="font-semibold mb-3 text-sm tracking-wide text-gray-600">BASICS</h2>

      <div><span class="text-gray-500 text-sm">SKU:</span>
        <span class="font-mono text-sm ml-1"><?= $h($sku) ?></span>
      </div>

      <div><span class="text-gray-500 text-sm">Barcode:</span>
        <span class="font-mono text-sm ml-1"><?= $h($barcode) ?: '—' ?></span>
      </div>

      <div><span class="text-gray-500 text-sm">Name:</span>
        <span class="ml-1"><?= $h($name) ?></span>
      </div>

      <div><span class="text-gray-500 text-sm">Category:</span>
        <span class="ml-1"><?= $h($category) ?></span>
      </div>

      <div><span class="text-gray-500 text-sm">Brand:</span>
        <span class="ml-1"><?= $h($brandName) ?></span>
      </div>

      <div><span class="text-gray-500 text-sm">Unit:</span>
        <span class="ml-1"><?= $h($unit) ?></span>
      </div>

      <div class="pt-2">
        <span class="text-gray-500 text-sm">Active:</span>
        <span class="inline-block ml-2 px-2 py-1 text-xs rounded-full
                     <?= $isActive ? 'text-white' : 'bg-gray-200 text-gray-700 dark:bg-gray-700 dark:text-gray-200' ?>"
              style="<?= $isActive ? "background: {$brand};" : '' ?>">
          <?= $isActive ? 'Active' : 'Inactive' ?>
        </span>
      </div>
    </div>

    <!-- Pricing & stock -->
    <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 p-4 space-y-2">
      <h2 class="font-semibold mb-3 text-sm tracking-wide text-gray-600">PRICING & STOCK</h2>

      <div><span class="text-gray-500 text-sm">Tax Rate (%):</span>
        <span class="ml-1"><?= $h((string)$taxRate) ?></span>
      </div>

      <div><span class="text-gray-500 text-sm">Cost:</span>
        <span class="ml-1 font-mono"><?= $fmtMoney($cost, 4) ?></span>
      </div>

      <div><span class="text-gray-500 text-sm">Sale Price:</span>
        <span class="ml-1 font-mono"><?= $fmtMoney($sale, 2) ?></span>
      </div>

      <div><span class="text-gray-500 text-sm">Track Stock:</span>
        <span class="ml-1"><?= $track ? 'Yes' : 'No' ?></span>
      </div>

      <div><span class="text-gray-500 text-sm">Low Stock Threshold:</span>
        <span class="ml-1"><?= $lowStock ?></span>
      </div>
    </div>
  </div>
</div>