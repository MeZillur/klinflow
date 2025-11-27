<?php
declare(strict_types=1);

$h = fn($s)=>htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');

$base = rtrim((string)($module_base ?? ''), '/');
if ($base === '') {
  $slug = (string)($_SESSION['tenant_org']['slug'] ?? '');
  $base = $slug ? '/t/'.rawurlencode($slug).'/apps/dms' : '/apps/dms';
}

/* slot content */
ob_start(); ?>
<div class="space-y-4">
  <div class="flex items-center justify-between">
    <h1 class="text-lg font-semibold">Auto PO (Preview)</h1>
    <a class="text-sm underline" href="<?= $h($base) ?>/products/demand">Back to Demand</a>
  </div>

  <?php if (empty($groups)): ?>
    <div class="p-4 border rounded text-gray-600 dark:text-gray-300 dark:border-gray-700">
      No recommended purchases. Recompute demand first, or adjust lead/safety.
    </div>
  <?php endif; ?>

  <?php foreach (($groups ?? []) as $supplierId => $g): ?>
    <div class="rounded border border-gray-200 dark:border-gray-700 overflow-hidden">
      <div class="px-4 py-2 bg-gray-50 dark:bg-gray-800 flex items-center justify-between">
        <div class="font-medium"><?= $h($g['supplier_name']) ?></div>
        <form method="post" action="<?= $h($base) ?>/purchases/auto-po/create">
          <input type="hidden" name="_csrf" value="<?= $h($csrf ?? '') ?>">
          <input type="hidden" name="supplier_id" value="<?= (int)$supplierId ?>">
          <button class="px-3 py-1.5 rounded bg-emerald-600 text-white text-sm hover:bg-emerald-700">
            Create Purchase (stub)
          </button>
        </form>
      </div>
      <div class="overflow-x-auto">
        <table class="min-w-full text-sm">
          <thead class="bg-gray-50 dark:bg-gray-800 text-gray-600 dark:text-gray-300">
            <tr>
              <th class="px-3 py-2 text-left">SKU</th>
              <th class="px-3 py-2 text-left">Product</th>
              <th class="px-3 py-2 text-right">Stock</th>
              <th class="px-3 py-2 text-right">On Order</th>
              <th class="px-3 py-2 text-right">Avg/Day</th>
              <th class="px-3 py-2 text-right">ROP</th>
              <th class="px-3 py-2 text-right">Suggested</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($g['items'] as $r): ?>
              <tr class="border-t border-gray-200 dark:border-gray-700">
                <td class="px-3 py-2"><?= $h($r['sku_code'] ?? ('#'.$r['sku_id'])) ?></td>
                <td class="px-3 py-2"><?= $h($r['product_name'] ?? '') ?></td>
                <td class="px-3 py-2 text-right"><?= number_format((float)($r['stock_on_hand'] ?? 0), 2) ?></td>
                <td class="px-3 py-2 text-right"><?= number_format((float)($r['on_order'] ?? 0), 2) ?></td>
                <td class="px-3 py-2 text-right"><?= number_format((float)($r['avg_daily'] ?? 0), 3) ?></td>
                <td class="px-3 py-2 text-right"><?= number_format((float)($r['rop'] ?? 0), 2) ?></td>
                <td class="px-3 py-2 text-right font-semibold text-emerald-600"><?= number_format((float)($r['suggested_qty'] ?? 0), 0) ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  <?php endforeach; ?>
</div>
<?php
$_content = ob_get_clean();

$moduleSidenav = \defined('BASE_PATH')
  ? BASE_PATH . '/modules/DMS/Views/shared/partials/sidenav.php'
  : dirname(__DIR__,2) . '/Views/shared/partials/sidenav.php';

$shell = \defined('BASE_PATH')
  ? BASE_PATH . '/modules/DMS/Views/shared/layouts/shell.php'
  : dirname(__DIR__,2) . '/Views/shared/layouts/shell.php';

$title = $title ?? 'Auto PO (Preview)';
$org   = $org   ?? ($_SESSION['tenant_org'] ?? []);
$module_base = $base;

require $shell;