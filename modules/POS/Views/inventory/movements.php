<?php
declare(strict_types=1);
/**
 * Inventory → Movements (CONTENT-ONLY)
 * Inputs from controller:
 *   $rows, $page, $pages, $per, $total, $base
 * Notes:
 *   - View is content-only. Shell/sidenav are added by BaseController/front.
 *   - Brand color: #228B22 (used for primary actions & “in” badges).
 */
$h = fn($x)=>htmlspecialchars((string)$x, ENT_QUOTES, 'UTF-8');
$brand = '#228B22';
?>
<div class="px-6 py-6 space-y-6">
  <div class="flex items-center justify-between">
    <div>
      <h1 class="text-2xl font-semibold text-gray-800 dark:text-gray-100">Stock Movements</h1>
      <p class="text-sm text-gray-500 mt-1">Total: <?= (int)($total ?? count($rows ?? [])) ?></p>
    </div>

    <div class="flex items-center gap-2">
      <a href="<?= $base ?>/inventory"
         class="inline-flex items-center gap-2 px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-700 text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-800">
        <i class="fa fa-arrow-left"></i> Back to Inventory
      </a>
      <button type="button"
              onclick="window.print()"
              class="inline-flex items-center gap-2 px-3 py-2 rounded-lg text-white shadow"
              style="background: <?= $brand ?>;">
        <i class="fa fa-print"></i> Print
      </button>
    </div>
  </div>

  <div class="overflow-x-auto rounded-lg border border-gray-200 dark:border-gray-700 shadow-sm bg-white dark:bg-gray-900">
    <table class="min-w-full text-sm text-left">
      <thead class="bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-300">
        <tr>
          <th class="px-4 py-3 font-semibold">Date</th>
          <th class="px-4 py-3 font-semibold">SKU</th>
          <th class="px-4 py-3 font-semibold">Product</th>
          <th class="px-4 py-3 font-semibold text-center">Direction</th>
          <th class="px-4 py-3 font-semibold text-right">Qty</th>
          <th class="px-4 py-3 font-semibold">Reason</th>
          <th class="px-4 py-3 font-semibold">Note</th>
        </tr>
      </thead>
      <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
      <?php if (empty($rows)): ?>
        <tr><td colspan="7" class="px-4 py-6 text-center text-gray-500">No movements yet</td></tr>
      <?php else: foreach ($rows as $r): ?>
        <?php
          $dir = (string)($r['direction'] ?? '');
          $isIn  = ($dir === 'in');
          $pill  = $isIn
            ? 'text-white'
            : 'bg-red-100 text-red-700 dark:bg-red-900/50 dark:text-red-300';
          $pillStyle = $isIn ? "background: {$brand};" : "";
          $qty = (float)($r['qty'] ?? 0);
        ?>
        <tr class="hover:bg-gray-50 dark:hover:bg-gray-800">
          <td class="px-4 py-2 whitespace-nowrap"><?= $h(date('Y-m-d H:i', strtotime((string)($r['created_at'] ?? '')))) ?></td>
          <td class="px-4 py-2 font-mono"><?= $h($r['sku'] ?? '') ?></td>
          <td class="px-4 py-2"><?= $h($r['name'] ?? '') ?></td>
          <td class="px-4 py-2 text-center">
            <span class="inline-flex items-center gap-1 px-2 py-1 text-xs font-medium rounded-full <?= $pill ?>"
                  style="<?= $pillStyle ?>">
              <?php if ($isIn): ?>
                <i class="fa fa-arrow-down-a-z rotate-180"></i> IN
              <?php else: ?>
                <i class="fa fa-arrow-up-z-a"></i> OUT
              <?php endif; ?>
            </span>
          </td>
          <td class="px-4 py-2 text-right"><?= rtrim(rtrim(number_format($qty, 3, '.', ''), '0'), '.') ?></td>
          <td class="px-4 py-2 capitalize"><?= $h($r['reason'] ?? '') ?></td>
          <td class="px-4 py-2"><?= $h($r['note'] ?? '') ?></td>
        </tr>
      <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>

  <?php if (!empty($pages) && (int)$pages > 1): ?>
  <div class="flex justify-between items-center pt-4">
    <div class="text-sm text-gray-500">Page <?= (int)($page ?? 1) ?> of <?= (int)$pages ?></div>
    <div class="flex gap-2">
      <?php
        $cur = (int)($page ?? 1);
        $qs  = function(int $i) use($base) {
          $q = $_GET; $q['page'] = $i; return '?'.http_build_query($q);
        };
      ?>
      <a href="<?= $qs(max(1, $cur-1)) ?>"
         class="px-3 py-1 rounded-md border bg-white dark:bg-gray-800 border-gray-300 dark:border-gray-700 text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700">Prev</a>
      <?php for ($i = 1; $i <= (int)$pages; $i++): ?>
        <a href="<?= $qs($i) ?>"
           class="px-3 py-1 rounded-md border <?= $i === $cur
              ? 'text-white border-transparent'
              : 'bg-white dark:bg-gray-800 border-gray-300 dark:border-gray-700 text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700' ?>"
           style="<?= $i === $cur ? "background: {$brand};" : '' ?>">
          <?= $i ?>
        </a>
      <?php endfor; ?>
      <a href="<?= $qs(min((int)$pages, $cur+1)) ?>"
         class="px-3 py-1 rounded-md border bg-white dark:bg-gray-800 border-gray-300 dark:border-gray-700 text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700">Next</a>
    </div>
  </div>
  <?php endif; ?>
</div>