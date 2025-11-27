<?php
declare(strict_types=1);

/**
 * POS Main Dashboard (content-only)
 *
 * Expected vars:
 *  - $base         string  module base URL
 *  - $kpis         array   ['today','month','totalSales','totalOrders']
 *  - $chart        array   ['labels'=>[dates], 'values'=>[floats]]
 *  - $topProducts  array   [['name'=>..., 'qty'=>...], ...]
 *  - $recentOrders array   [['no'=>..., 'date'=>..., 'customer'=>..., 'total' (optional)], ...]
 *  - $brandColor   string  hex brand colour (e.g. #228B22)
 *  - $ctx          array   context (org, branch etc.)
 */

$h      = fn($x) => htmlspecialchars((string)$x, ENT_QUOTES, 'UTF-8');
$brand  = isset($brandColor) && $brandColor ? (string)$brandColor : '#228B22';
$kpis   = $kpis ?? ['today' => 0, 'month' => 0, 'totalSales' => 0, 'totalOrders' => 0];
$chart  = $chart ?? ['labels' => [], 'values' => []];
$values = $chart['values'] ?? [];
$labels = $chart['labels'] ?? [];

$maxVal = 0.0;
foreach ($values as $v) {
    if ($v > $maxVal) $maxVal = (float)$v;
}
if ($maxVal <= 0) $maxVal = 1.0;

// branch info text (current branch = header switch in shell)
$branchLabel = 'All branches';
if (!empty($ctx['branch_id'])) {
    $branchLabel = 'Branch #' . (int)$ctx['branch_id'];
}
?>
<div class="px-6 py-6 space-y-6">

  <!-- Header -->
  <div class="flex flex-wrap items-center justify-between gap-4">
    <div>
      <h1 class="text-2xl md:text-3xl font-semibold text-gray-900 dark:text-gray-50">
        POS Dashboard
      </h1>
      <p class="text-sm text-gray-500 dark:text-gray-400">
        Fast snapshot of sales, orders, and branch performance.
      </p>
      <p class="mt-1 text-xs text-gray-400 dark:text-gray-500">
        Scope:
        <span class="inline-flex items-center px-2 py-0.5 rounded-full border border-gray-200 dark:border-gray-700">
          <i class="fa fa-code-branch mr-1 text-xs" aria-hidden="true"></i>
          <?= $h($branchLabel) ?>
        </span>
      </p>
    </div>

    <!-- Quick links -->
    <div class="flex flex-wrap gap-2 text-sm">
      <a href="<?= $h($base) ?>/sales/register"
         class="inline-flex items-center gap-2 px-3 py-2 rounded-lg text-white shadow"
         style="background:<?= $brand ?>;">
        <i class="fa fa-cash-register" aria-hidden="true"></i>
        New Sale
      </a>
      <a href="<?= $h($base) ?>/inventory"
         class="inline-flex items-center gap-2 px-3 py-2 rounded-lg border border-gray-200 dark:border-gray-700 text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-800">
        <i class="fa fa-boxes-stacked" aria-hidden="true"></i>
        Inventory
      </a>
      <a href="<?= $h($base) ?>/sales"
         class="inline-flex items-center gap-2 px-3 py-2 rounded-lg border border-gray-200 dark:border-gray-700 text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-800">
        <i class="fa fa-receipt" aria-hidden="true"></i>
        All Sales
      </a>
    </div>
  </div>

  <!-- KPI cards (row 1) -->
  <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-4">

    <!-- Total Sales -->
    <div class="rounded-2xl p-4 shadow-sm bg-white dark:bg-gray-900 flex flex-col justify-between"
         style="border-top:4px solid <?= $brand ?>;">
      <div class="flex items-center justify-between">
        <div>
          <p class="text-xs font-semibold uppercase text-gray-500 dark:text-gray-400">Total Sales</p>
          <p class="mt-2 text-2xl font-bold text-gray-900 dark:text-gray-50">
            ৳<?= number_format((float)$kpis['totalSales'], 2) ?>
          </p>
        </div>
        <span class="inline-flex h-10 w-10 items-center justify-center rounded-full bg-green-50 text-green-600 dark:bg-green-900/40 dark:text-green-300">
          <i class="fa fa-arrow-trend-up" aria-hidden="true"></i>
        </span>
      </div>
      <p class="mt-3 text-xs text-gray-500 dark:text-gray-400">
        Cumulative revenue for the current organization and selected branch.
      </p>
    </div>

    <!-- Today -->
    <div class="rounded-2xl p-4 shadow-sm bg-white dark:bg-gray-900 flex flex-col justify-between"
         style="border-top:4px solid #0ea5e9;">
      <div class="flex items-center justify-between">
        <div>
          <p class="text-xs font-semibold uppercase text-gray-500 dark:text-gray-400">Today</p>
          <p class="mt-2 text-2xl font-bold text-gray-900 dark:text-gray-50">
            ৳<?= number_format((float)$kpis['today'], 2) ?>
          </p>
        </div>
        <span class="inline-flex h-10 w-10 items-center justify-center rounded-full bg-sky-50 text-sky-600 dark:bg-sky-900/40 dark:text-sky-300">
          <i class="fa fa-calendar-day" aria-hidden="true"></i>
        </span>
      </div>
      <p class="mt-3 text-xs text-gray-500 dark:text-gray-400">
        Sales posted on <?= $h(date('d M Y')) ?>.
      </p>
    </div>

    <!-- This Month -->
    <div class="rounded-2xl p-4 shadow-sm bg-white dark:bg-gray-900 flex flex-col justify-between"
         style="border-top:4px solid #6366f1;">
      <div class="flex items-center justify-between">
        <div>
          <p class="text-xs font-semibold uppercase text-gray-500 dark:text-gray-400">This Month</p>
          <p class="mt-2 text-2xl font-bold text-gray-900 dark:text-gray-50">
            ৳<?= number_format((float)$kpis['month'], 2) ?>
          </p>
        </div>
        <span class="inline-flex h-10 w-10 items-center justify-center rounded-full bg-indigo-50 text-indigo-600 dark:bg-indigo-900/40 dark:text-indigo-300">
          <i class="fa fa-calendar-alt" aria-hidden="true"></i>
        </span>
      </div>
      <p class="mt-3 text-xs text-gray-500 dark:text-gray-400">
        All sales recorded in <?= $h(date('M Y')) ?>.
      </p>
    </div>

    <!-- Orders count -->
    <div class="rounded-2xl p-4 shadow-sm bg-white dark:bg-gray-900 flex flex-col justify-between"
         style="border-top:4px solid #f97316;">
      <div class="flex items-center justify-between">
        <div>
          <p class="text-xs font-semibold uppercase text-gray-500 dark:text-gray-400">Total Orders</p>
          <p class="mt-2 text-2xl font-bold text-gray-900 dark:text-gray-50">
            <?= number_format((int)$kpis['totalOrders']) ?>
          </p>
        </div>
        <span class="inline-flex h-10 w-10 items-center justify-center rounded-full bg-orange-50 text-orange-600 dark:bg-orange-900/40 dark:text-orange-300">
          <i class="fa fa-shopping-bag" aria-hidden="true"></i>
        </span>
      </div>
      <p class="mt-3 text-xs text-gray-500 dark:text-gray-400">
        All invoices / bills generated so far.
      </p>
    </div>
  </div>

  <!-- Row 2: Sales chart + snapshot -->
  <div class="grid grid-cols-1 xl:grid-cols-3 gap-4">

    <!-- Sales chart / branch history -->
    <div class="xl:col-span-2 rounded-2xl bg-white dark:bg-gray-900 shadow-sm border border-gray-100 dark:border-gray-800 p-4 md:p-5">
      <div class="flex items-center justify-between mb-3">
        <div>
          <h2 class="text-base font-semibold text-gray-900 dark:text-gray-50 flex items-center gap-2">
            <i class="fa fa-chart-line text-sm text-emerald-500" aria-hidden="true"></i>
            Sales History (Last 14 Days)
          </h2>
          <p class="text-xs text-gray-500 dark:text-gray-400">
            Multi-branch aware: data follows the active branch selection in the shell.
          </p>
        </div>
        <!-- fake range pills (for future Chart.js) -->
        <div class="inline-flex items-center rounded-full bg-gray-100 dark:bg-gray-800 p-1 text-xs">
          <button type="button" class="px-2 py-0.5 rounded-full bg-white dark:bg-gray-900 shadow text-gray-800 dark:text-gray-50">
            14D
          </button>
          <button type="button" class="px-2 py-0.5 rounded-full text-gray-500 dark:text-gray-400">
            1M
          </button>
          <button type="button" class="px-2 py-0.5 rounded-full text-gray-500 dark:text-gray-400">
            3M
          </button>
        </div>
      </div>

      <?php if (empty($values)): ?>
        <div class="py-10 text-center text-gray-400 text-sm">
          <i class="fa fa-circle-info mb-2 text-lg" aria-hidden="true"></i>
          <div>No sales data available yet.</div>
        </div>
      <?php else: ?>
        <!-- CSS-only mini bar chart -->
        <div class="relative mt-2">
          <div class="h-52 flex items-end gap-1 md:gap-1.5">
            <?php foreach ($values as $idx => $v): ?>
              <?php
                $height = (int)round(($v / $maxVal) * 100);
                $label  = $labels[$idx] ?? '';
                $day    = $label ? date('d M', strtotime($label)) : '';
              ?>
              <div class="flex-1 flex flex-col items-center group">
                <div class="w-full rounded-t-full"
                     style="height:<?= max(4, $height) ?>%; background:linear-gradient(to top, <?= $brand ?>, rgba(34,139,34,.25));"></div>
                <div class="mt-1 text-[10px] text-gray-400 dark:text-gray-500 text-center whitespace-nowrap">
                  <?= $h($day) ?>
                </div>
                <div class="opacity-0 group-hover:opacity-100 text-[11px] mt-1 px-1.5 py-0.5 rounded-full bg-gray-900 text-white shadow transition">
                  ৳<?= number_format((float)$v, 0) ?>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        </div>

        <!-- Compact table under chart -->
        <div class="mt-4 overflow-x-auto">
          <table class="min-w-full text-xs text-left text-gray-600 dark:text-gray-300">
            <thead class="border-t border-b border-gray-100 dark:border-gray-800">
              <tr>
                <th class="py-2 pr-3 font-semibold">Date</th>
                <th class="py-2 pr-3 font-semibold text-right">Sales (৳)</th>
                <th class="py-2 pr-3 font-semibold text-right">Share</th>
              </tr>
            </thead>
            <tbody>
            <?php
              $totalWindow = array_sum(array_map('floatval', $values));
              foreach ($values as $idx => $v):
                $label = $labels[$idx] ?? '';
                $day   = $label ? date('d M Y', strtotime($label)) : '';
                $share = $totalWindow > 0 ? ($v / $totalWindow * 100) : 0;
            ?>
              <tr class="border-b border-gray-50 dark:border-gray-800 last:border-0">
                <td class="py-1.5 pr-3"><?= $h($day) ?></td>
                <td class="py-1.5 pr-3 text-right">৳<?= number_format((float)$v, 2) ?></td>
                <td class="py-1.5 pr-3 text-right">
                  <span class="inline-flex items-center gap-1">
                    <span class="inline-block h-1.5 w-10 rounded-full bg-emerald-400/70"></span>
                    <?= number_format($share, 1) ?>%
                  </span>
                </td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>

    <!-- Overall snapshot -->
    <div class="rounded-2xl bg-white dark:bg-gray-900 shadow-sm border border-gray-100 dark:border-gray-800 p-4 md:p-5 space-y-4">
      <div class="flex items-center justify-between mb-1">
        <h2 class="text-base font-semibold text-gray-900 dark:text-gray-50 flex items-center gap-2">
          <i class="fa fa-circle-nodes text-violet-500" aria-hidden="true"></i>
          Overall Snapshot
        </h2>
        <span class="text-[11px] px-2 py-0.5 rounded-full bg-gray-100 dark:bg-gray-800 text-gray-500 dark:text-gray-400">
          Live
        </span>
      </div>

      <?php
        $uniqueCustomers = array_filter(array_unique(array_map(
            static fn($r) => trim((string)($r['customer'] ?? '')),
            $recentOrders ?? []
        )));
        $recentCount = count($recentOrders ?? []);
      ?>

      <div class="grid grid-cols-3 gap-3 text-xs">
        <div class="rounded-xl bg-gray-50 dark:bg-gray-800/80 px-3 py-3 flex flex-col gap-1">
          <span class="text-gray-500 dark:text-gray-400 flex items-center gap-1">
            <i class="fa fa-user-group text-[11px] text-emerald-500" aria-hidden="true"></i>
            Active Customers
          </span>
          <span class="text-lg font-semibold text-gray-900 dark:text-gray-50">
            <?= number_format(count($uniqueCustomers)) ?>
          </span>
          <span class="text-[11px] text-gray-400 dark:text-gray-500">From recent sales only</span>
        </div>
        <div class="rounded-xl bg-gray-50 dark:bg-gray-800/80 px-3 py-3 flex flex-col gap-1">
          <span class="text-gray-500 dark:text-gray-400 flex items-center gap-1">
            <i class="fa fa-file-invoice-dollar text-[11px] text-sky-500" aria-hidden="true"></i>
            Recent Orders
          </span>
          <span class="text-lg font-semibold text-gray-900 dark:text-gray-50">
            <?= number_format($recentCount) ?>
          </span>
          <span class="text-[11px] text-gray-400 dark:text-gray-500">Last few invoices</span>
        </div>
        <div class="rounded-xl bg-gray-50 dark:bg-gray-800/80 px-3 py-3 flex flex-col gap-1">
          <span class="text-gray-500 dark:text-gray-400 flex items-center gap-1">
            <i class="fa fa-code-branch text-[11px] text-orange-500" aria-hidden="true"></i>
            Branch Mode
          </span>
          <span class="text-lg font-semibold text-gray-900 dark:text-gray-50">
            <?= $h($branchLabel) ?>
          </span>
          <span class="text-[11px] text-gray-400 dark:text-gray-500">Switch branch from top bar</span>
        </div>
      </div>

      <div class="mt-3 border-t border-gray-100 dark:border-gray-800 pt-3 text-[11px] text-gray-400 dark:text-gray-500">
        TIP: Sub-branches see only their own sales &amp; sub-inventory. Main branch can view consolidated
        numbers by selecting <strong>All branches</strong>.
      </div>
    </div>
  </div>

  <!-- Row 3: Top products / low stock teaser / recent sales -->
  <div class="grid grid-cols-1 xl:grid-cols-3 gap-4">

    <!-- Top products -->
    <div class="rounded-2xl bg-white dark:bg-gray-900 shadow-sm border border-gray-100 dark:border-gray-800 p-4 md:p-5">
      <div class="flex items-center justify-between mb-3">
        <h2 class="text-base font-semibold text-gray-900 dark:text-gray-50 flex items-center gap-2">
          <i class="fa fa-box text-pink-500" aria-hidden="true"></i>
          Top Selling Products
        </h2>
        <a href="<?= $h($base) ?>/sales"
           class="text-xs text-emerald-600 dark:text-emerald-400 hover:underline">
          View All
        </a>
      </div>

      <?php if (empty($topProducts)): ?>
        <p class="text-xs text-gray-400">No product-wise breakdown available yet.</p>
      <?php else: ?>
        <ul class="space-y-2 text-sm">
          <?php foreach ($topProducts as $p): ?>
            <li class="flex items-center justify-between gap-2 rounded-xl px-2 py-2 hover:bg-gray-50 dark:hover:bg-gray-800 transition">
              <div class="flex items-center gap-3">
                <div class="h-8 w-8 rounded-lg bg-gradient-to-tr from-emerald-500 to-lime-400 text-white flex items-center justify-center text-xs font-semibold">
                  <i class="fa fa-cube" aria-hidden="true"></i>
                </div>
                <div>
                  <div class="font-medium text-gray-900 dark:text-gray-50">
                    <?= $h($p['name'] ?? 'Unnamed') ?>
                  </div>
                  <div class="text-[11px] text-gray-500 dark:text-gray-400">
                    <?= number_format((float)($p['qty'] ?? 0)) ?> units sold
                  </div>
                </div>
              </div>
              <span class="inline-flex items-center text-xs text-emerald-600 dark:text-emerald-400">
                <i class="fa fa-arrow-trend-up mr-1" aria-hidden="true"></i> Hot
              </span>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </div>

    <!-- Low stock / branch inventory teaser -->
    <div class="rounded-2xl bg-white dark:bg-gray-900 shadow-sm border border-gray-100 dark:border-gray-800 p-4 md:p-5">
      <div class="flex items-center justify-between mb-3">
        <h2 class="text-base font-semibold text-gray-900 dark:text-gray-50 flex items-center gap-2">
          <i class="fa fa-triangle-exclamation text-amber-500" aria-hidden="true"></i>
          Low Stock Preview
        </h2>
        <a href="<?= $h($base) ?>/inventory/low-stock"
           class="text-xs text-amber-600 dark:text-amber-400 hover:underline">
          View All
        </a>
      </div>
      <p class="text-xs text-gray-500 dark:text-gray-400 mb-3">
        Quick link for the main branch to review products that need replenishment in each branch.
      </p>
      <ul class="space-y-2 text-xs">
        <li class="flex items-center justify-between">
          <span class="text-gray-600 dark:text-gray-300 flex items-center gap-2">
            <i class="fa fa-store text-[11px] text-emerald-500" aria-hidden="true"></i>
            Branch inventory overview
          </span>
          <span class="text-gray-400">Use Inventory &raquo; Low Stock</span>
        </li>
        <li class="flex items-center justify-between">
          <span class="text-gray-600 dark:text-gray-300 flex items-center gap-2">
            <i class="fa fa-arrows-left-right text-[11px] text-sky-500" aria-hidden="true"></i>
            Transfer between branches
          </span>
          <span class="text-gray-400">
            Use Inventory &raquo; Transfers
          </span>
        </li>
      </ul>
    </div>

    <!-- Recent sales -->
    <div class="rounded-2xl bg-white dark:bg-gray-900 shadow-sm border border-gray-100 dark:border-gray-800 p-4 md:p-5">
      <div class="flex items-center justify-between mb-3">
        <h2 class="text-base font-semibold text-gray-900 dark:text-gray-50 flex items-center gap-2">
          <i class="fa fa-receipt text-indigo-500" aria-hidden="true"></i>
          Recent Sales
        </h2>
        <span class="text-xs text-gray-500 dark:text-gray-400">
          Last <?= count($recentOrders ?? []) ?> invoices
        </span>
      </div>

      <?php if (empty($recentOrders)): ?>
        <p class="text-xs text-gray-400">No sales yet. Create your first invoice from the Sales Register.</p>
      <?php else: ?>
        <ul class="space-y-2 text-sm">
          <?php foreach ($recentOrders as $r): ?>
            <?php
              $dtRaw = (string)($r['date'] ?? '');
              $ts    = $dtRaw ? strtotime($dtRaw) : time();
              $showTotal = array_key_exists('total', $r);
            ?>
            <li class="flex items-center justify-between gap-2 rounded-xl px-2 py-2 hover:bg-gray-50 dark:hover:bg-gray-800 transition">
              <div class="flex items-center gap-3">
                <div class="h-8 w-8 rounded-lg bg-gray-100 dark:bg-gray-800 flex items-center justify-center text-xs text-gray-500">
                  <i class="fa fa-file-invoice" aria-hidden="true"></i>
                </div>
                <div>
                  <div class="font-medium text-gray-900 dark:text-gray-50">
                    #<?= $h($r['no'] ?? '') ?>
                  </div>
                  <div class="text-[11px] text-gray-500 dark:text-gray-400">
                    <?= $h($r['customer'] ?: 'Walk-in customer') ?>
                    &middot;
                    <?= $h(date('d M Y', $ts)) ?>
                  </div>
                </div>
              </div>
              <div class="text-right">
                <?php if ($showTotal): ?>
                  <div class="text-sm font-semibold text-gray-900 dark:text-gray-50">
                    ৳<?= number_format((float)$r['total'], 2) ?>
                  </div>
                <?php else: ?>
                  <div class="text-sm font-semibold text-gray-400 dark:text-gray-500">
                    View
                  </div>
                <?php endif; ?>
                <div class="text-[11px] text-emerald-500 dark:text-emerald-400">
                  <i class="fa fa-check-circle mr-1" aria-hidden="true"></i> Posted
                </div>
              </div>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </div>

  </div>
</div>