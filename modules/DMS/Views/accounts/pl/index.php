<?php
declare(strict_types=1);

/** @var array  $rows */
/** @var float  $totalIncome */
/** @var float  $totalExpense */
/** @var float  $netProfit */
/** @var array  $chart_labels */
/** @var array  $chart_net */
/** @var string $from */
/** @var string $to */
/** @var bool   $show_all */
/** @var string $module_base */

$h   = fn($v)  => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
$fmt = fn($n)  => number_format((float)$n, 2);
$rows = is_array($rows ?? null) ? $rows : [];
$labels = is_array($chart_labels ?? null) ? $chart_labels : [];
$net    = is_array($chart_net ?? null) ? $chart_net : [];
?>
<div class="space-y-6">

  <!-- Header + actions -->
  <div class="flex items-center justify-between flex-wrap gap-3">
    <h1 class="text-xl font-semibold">Profit &amp; Loss</h1>

    <div class="flex items-center gap-2">
      <a class="px-3 py-1.5 rounded-lg border hover:bg-gray-50 dark:hover:bg-gray-800"
         href="<?= $h(($module_base ?? '/apps/dms').'/accounts/profit-and-loss') ?>?from=<?= $h($from) ?>&to=<?= $h($to) ?>&print=1"
         target="_blank" rel="noopener">Print</a>
      <button id="btn-csv" class="px-3 py-1.5 rounded-lg border hover:bg-gray-50 dark:hover:bg-gray-800">Export CSV</button>
    </div>
  </div>

  <!-- KPIs -->
  <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
    <div class="p-4 rounded-xl border bg-white dark:bg-gray-900">
      <div class="text-xs uppercase text-gray-500">Income (period)</div>
      <div class="text-2xl font-bold mt-1">৳ <?= $fmt($totalIncome ?? 0) ?></div>
    </div>
    <div class="p-4 rounded-xl border bg-white dark:bg-gray-900">
      <div class="text-xs uppercase text-gray-500">Expense (period)</div>
      <div class="text-2xl font-bold mt-1">৳ <?= $fmt($totalExpense ?? 0) ?></div>
    </div>
    <div class="p-4 rounded-xl border bg-white dark:bg-gray-900">
      <div class="text-xs uppercase text-gray-500">Net Profit</div>
      <div class="text-2xl font-bold mt-1 <?= ($netProfit ?? 0) >= 0 ? 'text-emerald-600' : 'text-rose-600' ?>">
        ৳ <?= $fmt($netProfit ?? 0) ?>
      </div>
    </div>
  </div>

  <!-- Filters -->
  <form class="grid grid-cols-1 md:grid-cols-5 gap-3 p-3 rounded-xl border bg-white dark:bg-gray-900" method="get">
    <label class="text-sm">
      <div class="text-gray-500 mb-1">From</div>
      <input type="date" name="from" value="<?= $h($from) ?>"
             class="w-full border rounded-lg px-3 py-2 bg-white dark:bg-gray-900">
    </label>
    <label class="text-sm">
      <div class="text-gray-500 mb-1">To</div>
      <input type="date" name="to" value="<?= $h($to) ?>"
             class="w-full border rounded-lg px-3 py-2 bg-white dark:bg-gray-900">
    </label>

    <label class="text-sm flex items-end gap-2">
      <input type="checkbox" name="show" value="all" <?= ($show_all ?? false) ? 'checked' : '' ?>>
      <span class="text-gray-700 dark:text-gray-300">Show all accounts (CoA)</span>
    </label>

    <div class="md:col-span-2 flex items-end">
      <button class="px-3 py-2 rounded-lg bg-emerald-600 text-white hover:bg-emerald-700 w-full md:w-auto">Apply</button>
    </div>
  </form>

  <!-- Table -->
  <div class="overflow-x-auto rounded-xl border bg-white dark:bg-gray-900">
    <table class="min-w-full text-[13px]" id="pl-table">
      <thead class="bg-gray-50/80 dark:bg-gray-800/60 sticky top-0 backdrop-blur">
        <tr class="text-gray-600 dark:text-gray-300">
          <th class="px-3 py-2 text-left w-36">Account</th>
          <th class="px-3 py-2 text-left">Name</th>
          <th class="px-3 py-2 text-right w-32">Income</th>
          <th class="px-3 py-2 text-right w-32">Expense</th>
        </tr>
      </thead>
      <tbody>
        <?php if ($rows): ?>
          <?php foreach ($rows as $i => $r): ?>
            <tr class="border-t <?= $i % 2 ? 'bg-gray-50/40 dark:bg-gray-800/30' : '' ?>">
              <td class="px-3 py-2"><?= $h($r['code'] ?? '') ?></td>
              <td class="px-3 py-2"><?= $h($r['name'] ?? '') ?></td>
              <td class="px-3 py-2 text-right"><?= $fmt($r['income'] ?? 0) ?></td>
              <td class="px-3 py-2 text-right"><?= $fmt($r['expense'] ?? 0) ?></td>
            </tr>
          <?php endforeach; ?>
        <?php else: ?>
          <tr class="border-t">
            <td class="px-3 py-10 text-center text-gray-500 dark:text-gray-400" colspan="4">
              No data for the selected period.
              <?php if (!($show_all ?? false)): ?>
                <a class="underline ml-1" href="?from=<?= $h($from) ?>&to=<?= $h($to) ?>&show=all">Show full chart of accounts</a>
              <?php endif; ?>
            </td>
          </tr>
        <?php endif; ?>
      </tbody>
      <tfoot class="bg-gray-50 dark:bg-gray-800">
        <tr class="font-semibold border-t">
          <td class="px-3 py-2" colspan="2">Totals</td>
          <td class="px-3 py-2 text-right"><?= $fmt($totalIncome ?? 0) ?></td>
          <td class="px-3 py-2 text-right"><?= $fmt($totalExpense ?? 0) ?></td>
        </tr>
        <tr class="font-bold">
          <td class="px-3 py-2" colspan="2">Net Profit</td>
          <td class="px-3 py-2 text-right <?= ($netProfit ?? 0) >= 0 ? 'text-emerald-600' : 'text-rose-600' ?>" colspan="2">
            <?= $fmt($netProfit ?? 0) ?>
          </td>
        </tr>
      </tfoot>
    </table>
  </div>

  <!-- Net Profit (daily) chart -->
  <div class="p-4 rounded-xl border bg-white dark:bg-gray-900">
    <div class="text-sm font-semibold mb-2">Net Profit (daily)</div>
    <canvas id="chartNetProfit" height="140" aria-label="Net Profit trend"></canvas>
    <?php if (!$labels): ?>
      <div class="text-xs text-gray-500 mt-2">No data to display.</div>
    <?php endif; ?>
  </div>
</div>

<script>
(function () {
  // CSV export
  document.getElementById('btn-csv')?.addEventListener('click', ()=>{
    const rows = Array.from(document.querySelectorAll('#pl-table tr'));
    const csv  = rows.map(tr => Array.from(tr.children).map(td=>{
      const t=(td.innerText||'').trim().replace(/\s+/g,' ');
      return '"' + t.replace(/"/g,'""') + '"';
    }).join(',')).join('\n');

    const blob = new Blob([csv], {type:'text/csv;charset=utf-8;'});
    const url  = URL.createObjectURL(blob);
    const a    = Object.assign(document.createElement('a'), { href:url, download:'profit-and-loss.csv' });
    document.body.appendChild(a); a.click(); a.remove(); URL.revokeObjectURL(url);
  });

  // Net Profit chart
  if (typeof Chart !== 'undefined') {
    const labels = <?= json_encode($labels, JSON_UNESCAPED_SLASHES) ?>;
    const net    = <?= json_encode($net,    JSON_UNESCAPED_SLASHES) ?>;
    const el = document.getElementById('chartNetProfit');
    if (el) {
      new Chart(el, {
        type: 'bar',
        data: { labels, datasets: [{ label: 'Net Profit', data: net }] },
        options: {
          responsive: true, maintainAspectRatio: false,
          plugins: { legend: { display: true } },
          scales: {
            x: { ticks: { autoSkip: true, maxTicksLimit: 7 } },
            y: { beginAtZero: true }
          }
        }
      });
    }
  }
})();
</script>