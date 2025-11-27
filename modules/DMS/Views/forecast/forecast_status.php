<?php
declare(strict_types=1);

/** View: Forecast Status (table output + download) */
$h = fn($s)=>htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');

$base = rtrim((string)($module_base ?? ($org['module_base'] ?? '')), '/');
if ($base==='') {
  $slug=(string)($org['slug'] ?? ($_SESSION['tenant_org']['slug'] ?? ''));
  $base=$slug ? '/t/'.rawurlencode($slug).'/apps/dms' : '/apps/dms';
}
$run   = $run   ?? null;
$items = $items ?? [];
$progress = (int)($run['progress'] ?? 0);
$state    = (string)($run['status']   ?? 'unknown');
$msg      = (string)($run['message']  ?? '—');

ob_start(); ?>
<div class="space-y-4">
  <div class="flex items-center justify-between">
    <h1 class="text-xl font-semibold">Forecast Status</h1>
    <div class="text-sm">
      <a class="text-emerald-700 dark:text-emerald-300 hover:underline" href="<?=$h($base)?>/forecast">Back to Forecast</a>
      <span class="mx-2">·</span>
      <a class="text-emerald-700 dark:text-emerald-300 hover:underline" href="<?=$h($base)?>/auto-po">Auto-PO</a>
    </div>
  </div>

  <div class="rounded-lg border bg-white dark:bg-gray-900">
    <div class="grid md:grid-cols-2 gap-0 border-b">
      <div class="p-4 border-r">
        <div class="text-xs text-gray-500 mb-1">Job:</div>
        <div class="font-medium"><?= $h((string)($run['id'] ?? '—')) ?></div>
      </div>
      <div class="p-4">
        <div class="text-xs text-gray-500 mb-1">Message</div>
        <div class="font-medium"><?= $h($msg) ?></div>
      </div>
    </div>

    <div class="grid md:grid-cols-2 gap-0 border-b">
      <div class="p-4 border-r">
        <div class="text-xs text-gray-500 mb-1">State</div>
        <div class="font-semibold"><?= $h($state) ?></div>
      </div>
      <div class="p-4">
        <div class="text-xs text-gray-500 mb-1">Progress</div>
        <div class="w-full h-2 bg-gray-100 dark:bg-gray-800 rounded">
          <div class="h-2 bg-emerald-600 rounded" style="width: <?= (int)$progress ?>%"></div>
        </div>
        <div class="text-xs mt-1"><?= (int)$progress ?>%</div>
      </div>
    </div>

    <div class="p-4 flex items-center justify-between">
      <div class="text-sm text-gray-500">Rows: <?= count($items) ?></div>
      <?php if (!empty($run['id'])): ?>
        <a class="text-sm text-emerald-700 dark:text-emerald-300 hover:underline" href="<?=$h($base)?>/forecast/download?id=<?=$h((string)$run['id'])?>">Download</a>
      <?php endif; ?>
    </div>
  </div>

  <div class="rounded-lg border bg-white dark:bg-gray-900 overflow-x-auto">
    <table class="min-w-full text-sm">
      <thead class="bg-gray-50 dark:bg-gray-800">
        <tr>
          <th class="px-2 py-2 text-left">SKU</th>
          <th class="px-2 py-2 text-left">Name</th>
          <th class="px-2 py-2 text-right">Stock</th>
          <th class="px-2 py-2 text-right">Sales (History)</th>
          <th class="px-2 py-2 text-right">Forecast/Day</th>
          <th class="px-2 py-2 text-right">Horizon Qty</th>
          <th class="px-2 py-2 text-right">Suggested PO</th>
        </tr>
      </thead>
      <tbody>
        <?php if (!$items): ?>
          <tr><td colspan="7" class="px-3 py-6 text-center text-gray-500">No results.</td></tr>
        <?php else: foreach ($items as $r): ?>
          <tr class="border-t">
            <td class="px-2 py-2"><?= $h((string)$r['sku']) ?></td>
            <td class="px-2 py-2"><?= $h((string)$r['name']) ?></td>
            <td class="px-2 py-2 text-right"><?= number_format((float)$r['stock_qty'], 2) ?></td>
            <td class="px-2 py-2 text-right"><?= number_format((float)$r['sales_hist_qty'], 2) ?></td>
            <td class="px-2 py-2 text-right"><?= number_format((float)$r['forecast_per_day'], 4) ?></td>
            <td class="px-2 py-2 text-right"><?= number_format((float)$r['horizon_qty'], 2) ?></td>
            <td class="px-2 py-2 text-right font-semibold"><?= number_format((float)$r['suggested_po'], 2) ?></td>
          </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>
<?php
$slot = ob_get_clean();
$basePath = \defined('BASE_PATH') ? BASE_PATH : dirname(__DIR__, 4);
$shell    = $basePath.'/modules/DMS/Views/shared/layouts/shell.php';
if (is_file($shell)) { $module_base=$base; require $shell; } else { echo $slot; }