<?php
declare(strict_types=1);

/** View: Demand Forecasting (DMS) */

use Shared\Csrf;

$h     = fn($s)=>htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
$title = $title ?? 'Demand Forecasting';

/* Robust module base */
$base = rtrim((string)($module_base ?? ($org['module_base'] ?? '')), '/');
if ($base === '') {
  $slug = (string)($_SESSION['tenant_org']['slug'] ?? '');
  if ($slug === '' && isset($_SERVER['REQUEST_URI']) &&
      preg_match('#^/t/([^/]+)/apps/dms#', (string)$_SERVER['REQUEST_URI'], $m)) { $slug = $m[1]; }
  $base = $slug !== '' ? '/t/'.rawurlencode($slug).'/apps/dms' : '/apps/dms';
}

$csrf = class_exists(Csrf::class) ? Csrf::token('tenant') : '';

/* Content into $slot for the shared shell */
ob_start(); ?>
  <style>
    /* Keep inputs pure white; dark mode uses neutral */
    .clean-card{background:#fff}
    .clean-input, .choices, .choices__inner{background:#fff !important}
    .choices{border-radius:.5rem}
    .choices__inner{border:1px solid #d1d5db !important; min-height:2.5rem; padding:.45rem .6rem}
    .choices__list--dropdown{border-radius:.5rem}
    .dark .clean-card{background:#111827}
    .dark .clean-input, .dark .choices, .dark .choices__inner{background:#111827 !important; border-color:#374151 !important}
    .dark .choices__list--dropdown{background:#0f172a; border-color:#334155}
  </style>

  <div class="space-y-4">
    <div class="flex items-center justify-between">
      <h1 class="text-xl font-semibold">Demand Forecasting</h1>
      <div class="flex items-center gap-2 text-sm">
        <a href="<?= $h($base) ?>/products" class="text-emerald-700 dark:text-emerald-300 hover:underline">Products &amp; Purchases</a>
        <a href="<?= $h($base) ?>/auto-po" class="text-emerald-700 dark:text-emerald-300 hover:underline">Auto-PO</a>
      </div>
    </div>

    <!-- Top filters row: Supplier / Category / Product -->
    <div class="rounded-lg border p-4 clean-card">
      <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <!-- Supplier -->
        <label class="block">
          <span class="text-sm">Supplier</span>
          <select
            name="filter_supplier_id"
            id="filter_supplier_id"
            class="w-full mt-1 rounded clean-input px-2 py-1.5"
            data-choices
            data-choices-ajax="<?= $h($base) ?>/api/lookup/suppliers"
            data-value-key="id"
            data-label-key="label">
            <option value="">All</option>
          </select>
        </label>

        <!-- Category -->
        <label class="block">
          <span class="text-sm">Category</span>
          <select
            name="filter_category_id"
            id="filter_category_id"
            class="w-full mt-1 rounded clean-input px-2 py-1.5"
            data-choices
            data-choices-ajax="<?= $h($base) ?>/api/lookup/categories"
            data-value-key="id"
            data-label-key="label">
            <option value="">All</option>
          </select>
        </label>

        <!-- Product -->
        <label class="block">
          <span class="text-sm">Product</span>
          <select
            name="filter_product_id"
            id="filter_product_id"
            class="w-full mt-1 rounded clean-input px-2 py-1.5"
            data-choices
            data-choices-ajax="<?= $h($base) ?>/api/lookup/products"
            data-value-key="id"
            data-label-key="label">
            <option value="">All</option>
          </select>
        </label>
      </div>
    </div>

    <!-- Algorithm & run -->
    <form method="post" action="<?= $h($base) ?>/forecast/run"
          class="rounded-lg border p-4 clean-card" id="forecastForm">
      <input type="hidden" name="_csrf" value="<?= $h($csrf) ?>">
      <!-- echo selected top filters so the POST can see them -->
      <input type="hidden" name="supplier_id" id="hf_supplier">
      <input type="hidden" name="category_id" id="hf_category">
      <input type="hidden" name="product_id"  id="hf_product">

      <div class="grid grid-cols-1 sm:grid-cols-4 gap-4">
        <label class="block">
          <span class="text-sm">Algorithm</span>
          <select name="algo" class="w-full mt-1 rounded clean-input px-2 py-1.5">
            <option value="moving-average" selected>Moving Average</option>
            <option value="ses">Simple Exponential Smoothing</option>
          </select>
        </label>

        <label class="block">
          <span class="text-sm">History (days)</span>
          <input type="number" name="history_days" value="90" min="7" class="w-full mt-1 rounded clean-input border px-2 py-1.5">
        </label>

        <label class="block">
          <span class="text-sm">Forecast horizon (days)</span>
          <input type="number" name="horizon_days" value="30" min="7" class="w-full mt-1 rounded clean-input border px-2 py-1.5">
        </label>

        <div class="flex items-end">
          <button class="w-full px-4 py-2 rounded bg-emerald-600 text-white hover:bg-emerald-700">
            Run Forecast
          </button>
        </div>
      </div>

      <p class="mt-3 text-xs text-gray-500">Results will appear below. You can convert them to Auto-PO.</p>
    </form>

    <div class="rounded-lg border p-4 clean-card">
      <h2 class="font-medium mb-2">Preview (sample products)</h2>
      <div class="overflow-x-auto rounded border">
        <table class="min-w-full text-[13px]">
          <thead class="bg-gray-50 dark:bg-gray-800 text-gray-600 dark:text-gray-300">
            <tr>
              <th class="px-2 py-1.5 text-left">SKU</th>
              <th class="px-2 py-1.5 text-left">Name</th>
              <th class="px-2 py-1.5 text-right">Forecast (30d)</th>
              <th class="px-2 py-1.5 text-right">Stock</th>
              <th class="px-2 py-1.5 text-right">Suggested PO</th>
            </tr>
          </thead>
          <tbody>
            <?php if (!empty($products)): ?>
              <?php foreach ($products as $p): ?>
                <tr class="border-t">
                  <td class="px-2 py-1.5"><?= $h($p['sku'] ?? '') ?></td>
                  <td class="px-2 py-1.5"><?= $h($p['name'] ?? '') ?></td>
                  <td class="px-2 py-1.5 text-right">—</td>
                  <td class="px-2 py-1.5 text-right">—</td>
                  <td class="px-2 py-1.5 text-right">—</td>
                </tr>
              <?php endforeach; ?>
            <?php else: ?>
              <tr><td colspan="5" class="px-3 py-6 text-center text-sm text-gray-500">No products to preview.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <script>
    (function(){
      // Ensure Choices binds (your shell lazy-loads assets)
      function scan() {
        if (window.KF && KF.choices && typeof KF.choices.scan === 'function') {
          KF.choices.scan(document);
        } else { setTimeout(scan, 120); }
      }
      if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', scan, { once:true }); else scan();

      // Mirror top filters into the POST form (hidden inputs) so controller receives them
      const map = [
        ['#filter_supplier_id', '#hf_supplier'],
        ['#filter_category_id', '#hf_category'],
        ['#filter_product_id',  '#hf_product']
      ];
      function syncOne(selQ, hidQ){
        const sel = document.querySelector(selQ), hid = document.querySelector(hidQ);
        if (!sel || !hid) return;
        const write = () => { hid.value = sel.value || ''; };
        write(); sel.addEventListener('change', write);
      }
      map.forEach(([a,b]) => syncOne(a,b));
    })();
  </script>
<?php
$slot = ob_get_clean();

/* Feed into shared shell + sidenav */
$basePath   = \defined('BASE_PATH') ? BASE_PATH : dirname(__DIR__, 4);
$shellPath  = $basePath . '/modules/DMS/Views/shared/layouts/shell.php';
$moduleSidenav = $moduleSidenav ?? null;
if (!$moduleSidenav) {
  $cand = $basePath . '/modules/DMS/Views/shared/sidenav.php';
  if (!is_file($cand)) {
    $alt = $basePath . '/modules/DMS/Views/shared/partials/sidenav.php';
    if (is_file($alt)) $moduleSidenav = $alt;
  } else {
    $moduleSidenav = $cand;
  }
}

if (is_file($shellPath)) {
  $module_base = $base;
  require $shellPath;
} else {
  echo $slot;
}