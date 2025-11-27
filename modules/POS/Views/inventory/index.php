<?php
declare(strict_types=1);

/**
 * Inventory → Overview (CONTENT-ONLY, enhanced, multi-branch)
 *
 * Expected inputs:
 *   $rows      – products (already filtered by branch, if backend does so)
 *   $cats      – category list
 *   $q         – search string
 *   $category  – selected category id
 *   $stat      – stock status filter
 *   $page      – current page (int)
 *   $pages     – total pages (int)
 *   $base      – module base (/t/{slug}/apps/pos)
 *   $csrf      – CSRF token for quick adjust buttons
 *   $branches  – (optional) list of branches [id, name, code, ...]
 *   $currentBranchId – (optional) currently selected branch id (0 = all/main)
 */

$h   = fn($x) => htmlspecialchars((string)$x, ENT_QUOTES, 'UTF-8');
$uri = $_SERVER['REQUEST_URI'] ?? '';

$branches        = $branches        ?? [];
$currentBranchId = isset($currentBranchId) ? (int)$currentBranchId : 0;

$page  = max(1, (int)($page  ?? 1));
$pages = max(1, (int)($pages ?? 1));

$totalCount = count($rows ?? []);
$lowCount   = count(array_filter($rows ?? [], fn($r)=>($r['stock_status'] ?? '') === 'low'));
$outCount   = count(array_filter($rows ?? [], fn($r)=>($r['stock_status'] ?? '') === 'out'));
$okCount    = max(0, $totalCount - $lowCount - $outCount);

/** helper: build query string preserving filters but overriding some keys */
$buildQuery = function(array $override = [] ) use ($q, $category, $stat, $currentBranchId): string {
    $base = [
        'q'          => $q         ?? '',
        'category'   => $category  ?? '',
        'stat'       => $stat      ?? '',
        'branch_id'  => $currentBranchId ?: null,
    ];
    $merged = array_filter(array_merge($base, $override), static fn($v) => $v !== null && $v !== '');
    return http_build_query($merged);
};
?>
<div class="px-6 py-6 space-y-8">

  <!-- Header + Menu -->
  <div class="flex flex-wrap items-start justify-between gap-4">
    <div class="space-y-1">
      <div class="inline-flex items-center gap-2">
        <span class="inline-flex h-9 w-9 items-center justify-center rounded-xl bg-blue-600 text-white shadow">
          <i class="fa fa-boxes-stacked"></i>
        </span>
        <div>
          <h1 class="text-3xl font-semibold text-gray-900 dark:text-gray-100">
            Inventory Dashboard
          </h1>
          <p class="text-sm text-gray-500 dark:text-gray-400">
            Central stock overview across branches.
          </p>
        </div>
      </div>
      <?php if (!empty($branches)): ?>
        <?php
          $currentBranchName = 'All Branches (Main Inventory)';
          foreach ($branches as $b) {
              if ((int)$b['id'] === $currentBranchId) {
                  $currentBranchName = $b['name'] . (!empty($b['code']) ? ' — '.$b['code'] : '');
                  break;
              }
          }
        ?>
        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
          <i class="fa fa-code-branch mr-1"></i>
          Viewing: <span class="font-medium text-gray-800 dark:text-gray-200"><?= $h($currentBranchName) ?></span>
        </p>
      <?php endif; ?>
    </div>

    <!-- Top Menu Tabs -->
    <nav class="flex flex-wrap gap-2 justify-end text-sm">
      <?php
      $menu = [
        'Dashboard'    => $base . '/dashboard',
        'Products'     => $base . '/products',
        'Inventory'    => $base . '/inventory',
        'Adjustments'  => $base . '/inventory/adjustments',
        'Transfers'    => $base . '/inventory/transfers',
        'Low Stock'    => $base . '/inventory/low-stock',
        'Movements'    => $base . '/inventory/movements',
        'Aging'        => $base . '/inventory/aging',
        'Branch'       => $base . '/branches',
        
      ];
      foreach ($menu as $label => $url):
        $active = str_contains($uri, parse_url($url, PHP_URL_PATH));
      ?>
        <a href="<?= $h($url) ?>"
           class="inline-flex items-center gap-2 px-4 py-2 rounded-md font-medium <?= $active
              ? 'bg-blue-600 text-white shadow'
              : 'text-gray-700 dark:text-gray-300 hover:text-blue-600 hover:bg-blue-50 dark:hover:bg-blue-900/20' ?>">
          <?php if ($label === 'Inventory'): ?>
            <i class="fa fa-warehouse text-xs"></i>
          <?php elseif ($label === 'Low Stock'): ?>
            <i class="fa fa-triangle-exclamation text-xs"></i>
          <?php elseif ($label === 'Movements'): ?>
            <i class="fa fa-arrows-rotate text-xs"></i>
          <?php endif; ?>
          <?= $h($label) ?>
        </a>
      <?php endforeach; ?>
    </nav>
  </div>

  <!-- Branch selector (chips) -->
  <?php if (!empty($branches)): ?>
    <div class="flex flex-col gap-2">
      <div class="flex items-center justify-between">
        <div class="flex items-center gap-2 text-sm font-medium text-gray-700 dark:text-gray-200">
          <i class="fa fa-store"></i>
          <span>Branches</span>
        </div>
        <span class="text-xs text-gray-500 dark:text-gray-400">
          Click a branch to view its inventory.
        </span>
      </div>
      <div class="flex gap-2 overflow-x-auto pb-1">
        <?php
        // “All branches / Main” pill
        $isActiveAll = $currentBranchId === 0;
        $qsAll = http_build_query([
            'q'         => $q ?? '',
            'category'  => $category ?? '',
            'stat'      => $stat ?? '',
            'page'      => 1,
        ]);
        ?>
        <a href="?<?= $qsAll ?>"
           class="whitespace-nowrap inline-flex items-center gap-2 px-3 py-1.5 rounded-full border text-xs font-semibold
                  <?= $isActiveAll
                      ? 'bg-emerald-600 text-white border-emerald-600 shadow-sm'
                      : 'bg-white dark:bg-gray-900 border-gray-300 dark:border-gray-700 text-gray-700 dark:text-gray-300 hover:bg-emerald-50 dark:hover:bg-emerald-900/30' ?>">
          <i class="fa fa-city text-[11px]"></i>
          <span>All / Main</span>
        </a>

        <?php foreach ($branches as $b):
          $bid      = (int)($b['id'] ?? 0);
          $isActive = $bid === $currentBranchId;
          $label    = $b['name'] ?? ('Branch #'.$bid);
          $code     = $b['code'] ?? '';
          $isMain   = isset($b['is_main']) && (int)$b['is_main'] === 1;
          $qsBranch = http_build_query([
              'q'         => $q ?? '',
              'category'  => $category ?? '',
              'stat'      => $stat ?? '',
              'branch_id' => $bid,
              'page'      => 1,
          ]);
        ?>
          <a href="?<?= $qsBranch ?>"
             class="whitespace-nowrap inline-flex items-center gap-2 px-3 py-1.5 rounded-full border text-xs font-semibold
                    <?= $isActive
                        ? 'bg-blue-600 text-white border-blue-600 shadow-sm'
                        : 'bg-white dark:bg-gray-900 border-gray-300 dark:border-gray-700 text-gray-700 dark:text-gray-300 hover:bg-blue-50 dark:hover:bg-blue-900/30' ?>">
            <i class="fa fa-store-alt text-[11px]"></i>
            <span><?= $h($label) ?></span>
            <?php if ($code): ?>
              <span class="px-1.5 py-0.5 rounded-full text-[10px] bg-black/5 dark:bg-white/10">
                <?= $h($code) ?>
              </span>
            <?php endif; ?>
            <?php if ($isMain): ?>
              <span class="px-1.5 py-0.5 rounded-full text-[10px] bg-emerald-600/10 text-emerald-700 dark:text-emerald-300">
                MAIN
              </span>
            <?php endif; ?>
          </a>
        <?php endforeach; ?>
      </div>
    </div>
  <?php endif; ?>

  <!-- Summary Cards -->
  <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
    <div class="bg-blue-600 text-white rounded-xl shadow p-4">
      <div class="flex items-center justify-between">
        <span class="text-sm font-medium uppercase tracking-wide opacity-80">Total Products</span>
        <i class="fa fa-box-open text-lg opacity-80"></i>
      </div>
      <div class="text-3xl font-bold mt-2"><?= number_format($totalCount) ?></div>
    </div>
    <div class="bg-green-600 text-white rounded-xl shadow p-4">
      <div class="flex items-center justify-between">
        <span class="text-sm font-medium uppercase tracking-wide opacity-80">In Stock</span>
        <i class="fa fa-circle-check text-lg opacity-80"></i>
      </div>
      <div class="text-3xl font-bold mt-2"><?= number_format($okCount) ?></div>
    </div>
    <div class="bg-yellow-500 text-white rounded-xl shadow p-4">
      <div class="flex items-center justify-between">
        <span class="text-sm font-medium uppercase tracking-wide opacity-80">Low Stock</span>
        <i class="fa fa-circle-exclamation text-lg opacity-80"></i>
      </div>
      <div class="text-3xl font-bold mt-2"><?= number_format($lowCount) ?></div>
    </div>
    <div class="bg-red-600 text-white rounded-xl shadow p-4">
      <div class="flex items-center justify-between">
        <span class="text-sm font-medium uppercase tracking-wide opacity-80">Out of Stock</span>
        <i class="fa fa-ban text-lg opacity-80"></i>
      </div>
      <div class="text-3xl font-bold mt-2"><?= number_format($outCount) ?></div>
    </div>
  </div>

  <!-- Filters -->
  <form method="get"
        class="flex flex-wrap items-end gap-3 bg-white dark:bg-gray-900 rounded-xl shadow p-4 border border-emerald-200 dark:border-emerald-900/50">
    <div class="flex-1 min-w-[220px]">
      <label class="text-sm text-gray-600 dark:text-gray-400 flex items-center gap-1">
        <i class="fa fa-magnifying-glass text-xs"></i>
        <span>Search</span>
      </label>
      <input
        type="text" name="q" value="<?= $h($q ?? '') ?>"
        class="w-full rounded-lg px-3 py-2 text-sm dark:bg-gray-800 dark:text-gray-100"
        style="border:1px solid #c6e6c6; outline:0;"
        placeholder="Name, SKU, or barcode..."
        onfocus="this.style.boxShadow='0 0 0 3px rgba(16,185,129,.25)'; this.style.borderColor='#10b981'"
        onblur="this.style.boxShadow='none'; this.style.borderColor='#c6e6c6'"
      />
    </div>

    <div>
      <label class="text-sm text-gray-600 dark:text-gray-400 flex items-center gap-1">
        <i class="fa fa-layer-group text-xs"></i>
        <span>Category</span>
      </label>
      <select name="category"
              class="w-44 rounded-lg px-3 py-2 text-sm dark:bg-gray-800 dark:text-gray-100"
              style="border:1px solid #c6e6c6; outline:0"
              onfocus="this.style.boxShadow='0 0 0 3px rgba(16,185,129,.25)'; this.style.borderColor='#10b981'"
              onblur="this.style.boxShadow='none'; this.style.borderColor='#c6e6c6'">
        <option value="">All</option>
        <?php foreach ($cats as $cRow): ?>
          <option value="<?= $h($cRow['id']) ?>"
                  <?= (string)($category ?? '') === (string)$cRow['id'] ? 'selected' : '' ?>>
            <?= $h($cRow['name']) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>

    <div>
      <label class="text-sm text-gray-600 dark:text-gray-400 flex items-center gap-1">
        <i class="fa fa-signal text-xs"></i>
        <span>Status</span>
      </label>
      <select name="stat"
              class="w-40 rounded-lg px-3 py-2 text-sm dark:bg-gray-800 dark:text-gray-100"
              style="border:1px solid #c6e6c6; outline:0"
              onfocus="this.style.boxShadow='0 0 0 3px rgba(16,185,129,.25)'; this.style.borderColor='#10b981'"
              onblur="this.style.boxShadow='none'; this.style.borderColor='#c6e6c6'">
        <option value="">All</option>
        <option value="ok"  <?= ($stat ?? '') === 'ok'  ? 'selected' : '' ?>>In Stock</option>
        <option value="low" <?= ($stat ?? '') === 'low' ? 'selected' : '' ?>>Low Stock</option>
        <option value="out" <?= ($stat ?? '') === 'out' ? 'selected' : '' ?>>Out of Stock</option>
      </select>
    </div>

    <!-- keep branch in query on filter submit -->
    <?php if ($currentBranchId): ?>
      <input type="hidden" name="branch_id" value="<?= (int)$currentBranchId ?>">
    <?php endif; ?>

    <button type="submit"
            class="inline-flex items-center gap-2 px-4 py-2 text-sm font-semibold rounded-lg shadow text-white bg-emerald-600 hover:bg-emerald-700">
      <i class="fa fa-filter"></i>
      <span>Apply</span>
    </button>
  </form>

  <!-- Inventory Table -->
  <div class="overflow-x-auto rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 shadow">
    <table class="min-w-full text-sm text-left">
      <thead class="bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-300">
        <tr>
          <th class="px-4 py-3 font-semibold">SKU</th>
          <th class="px-4 py-3 font-semibold">Name</th>
          <th class="px-4 py-3 font-semibold">Category</th>
          <th class="px-4 py-3 font-semibold text-right">Stock</th>
          <th class="px-4 py-3 font-semibold text-right">Low Threshold</th>
          <th class="px-4 py-3 font-semibold text-center">Status</th>
          <th class="px-4 py-3 font-semibold text-right">Actions</th>
        </tr>
      </thead>
      <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
      <?php if (empty($rows)): ?>
        <tr>
          <td colspan="7" class="px-4 py-6 text-center text-gray-500 dark:text-gray-400">
            <i class="fa fa-circle-info mr-1"></i>
            No products found for this filter/branch.
          </td>
        </tr>
      <?php else: ?>
        <?php foreach ($rows as $r):
          $st = (string)($r['stock_status'] ?? 'ok');
          $pill = match ($st) {
            'out' => 'bg-red-100 text-red-700 dark:bg-red-900/50 dark:text-red-300',
            'low' => 'bg-yellow-100 text-yellow-700 dark:bg-yellow-900/50 dark:text-yellow-300',
            default => 'bg-green-100 text-green-700 dark:bg-green-900/50 dark:text-green-300',
          };
        ?>
          <tr class="hover:bg-gray-50 dark:hover:bg-gray-800 transition-colors">
            <td class="px-4 py-2 font-mono text-xs"><?= $h($r['sku'] ?? '') ?></td>
            <td class="px-4 py-2 font-medium"><?= $h($r['name'] ?? '') ?></td>
            <td class="px-4 py-2"><?= $h($r['category_name'] ?? '—') ?></td>
            <td class="px-4 py-2 text-right"><?= number_format((float)($r['stock_on_hand_like'] ?? 0)) ?></td>
            <td class="px-4 py-2 text-right"><?= number_format((float)($r['low_stock_threshold_like'] ?? 0)) ?></td>
            <td class="px-4 py-2 text-center">
              <span class="inline-flex items-center justify-center gap-1 px-2 py-1 text-xs font-semibold rounded-full <?= $pill ?>">
                <?php if ($st === 'out'): ?>
                  <i class="fa fa-circle-xmark text-[11px]"></i>
                <?php elseif ($st === 'low'): ?>
                  <i class="fa fa-triangle-exclamation text-[11px]"></i>
                <?php else: ?>
                  <i class="fa fa-circle-check text-[11px]"></i>
                <?php endif; ?>
                <?= strtoupper($st) ?>
              </span>
            </td>
            <td class="px-4 py-2 text-right space-x-2 whitespace-nowrap">
              <form method="post" action="<?= $h($base) ?>/inventory/adjust" class="inline">
                <input type="hidden" name="_csrf" value="<?= $h($csrf ?? '') ?>">
                <input type="hidden" name="product_id" value="<?= (int)($r['id'] ?? 0) ?>">
                <input type="hidden" name="delta" value="1">
                <button type="submit"
                        title="Increase stock"
                        class="inline-flex items-center justify-center text-green-600 hover:text-green-800 dark:text-green-400 dark:hover:text-green-200">
                  <i class="fa fa-plus-circle"></i>
                </button>
              </form>
              <form method="post" action="<?= $h($base) ?>/inventory/adjust" class="inline">
                <input type="hidden" name="_csrf" value="<?= $h($csrf ?? '') ?>">
                <input type="hidden" name="product_id" value="<?= (int)($r['id'] ?? 0) ?>">
                <input type="hidden" name="delta" value="-1">
                <button type="submit"
                        title="Decrease stock"
                        class="inline-flex items-center justify-center text-red-600 hover:text-red-800 dark:text-red-400 dark:hover:text-red-200">
                  <i class="fa fa-minus-circle"></i>
                </button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
      <?php endif; ?>
      </tbody>
    </table>
  </div>

  <!-- Pagination -->
  <?php if ($pages > 1): ?>
    <div class="flex flex-wrap items-center justify-between gap-3 pt-4">
      <div class="text-sm text-gray-500 dark:text-gray-400">
        Page <?= $page ?> of <?= $pages ?>
        <span class="mx-1">•</span>
        Showing <?= number_format($totalCount) ?> items on this page
      </div>
      <div class="flex flex-wrap gap-2">
        <?php
        $prevPage = max(1, $page - 1);
        $nextPage = min($pages, $page + 1);
        ?>
        <a href="?<?= $buildQuery(['page' => $prevPage]) ?>"
           class="px-3 py-1.5 rounded-md border text-xs inline-flex items-center gap-1
                  <?= $page === 1
                      ? 'cursor-not-allowed opacity-40 border-gray-300 dark:border-gray-700 text-gray-400'
                      : 'border-gray-300 dark:border-gray-700 text-gray-700 dark:text-gray-300 hover:bg-blue-50 dark:hover:bg-blue-900/30' ?>">
          <i class="fa fa-chevron-left text-[10px]"></i>
          Prev
        </a>

        <?php for ($i = 1; $i <= $pages; $i++): ?>
          <a href="?<?= $buildQuery(['page' => $i]) ?>"
             class="px-3 py-1.5 rounded-md border text-xs
                    <?= $i === $page
                        ? 'bg-blue-600 text-white border-blue-600 shadow-sm'
                        : 'bg-white dark:bg-gray-800 border-gray-300 dark:border-gray-700 text-gray-700 dark:text-gray-300 hover:bg-blue-50 dark:hover:bg-blue-900/30' ?>">
            <?= $i ?>
          </a>
        <?php endfor; ?>

        <a href="?<?= $buildQuery(['page' => $nextPage]) ?>"
           class="px-3 py-1.5 rounded-md border text-xs inline-flex items-center gap-1
                  <?= $page === $pages
                      ? 'cursor-not-allowed opacity-40 border-gray-300 dark:border-gray-700 text-gray-400'
                      : 'border-gray-300 dark:border-gray-700 text-gray-700 dark:text-gray-300 hover:bg-blue-50 dark:hover:bg-blue-900/30' ?>">
          Next
          <i class="fa fa-chevron-right text-[10px]"></i>
        </a>
      </div>
    </div>
  <?php endif; ?>
</div>