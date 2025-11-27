<?php
declare(strict_types=1);
/**
 * Expenses index view
 *
 * Expects:
 *  - $rows: each row -> id, expense_no, expense_date, amount, payee, memo, acc_code, acc_name, bank_name
 *  - $from, $to, $q: strings
 *  - $module_base: string (module base path, e.g. /t/xyz/apps/dms)
 * Optional:
 *  - $banks: [ ['id'=>..,'account_name'=>..], ... ]
 */

if (!function_exists('h')) { function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); } }
$money = fn($n)=>number_format((float)$n, 2);

// Group rows by category (code + name)
$groups = []; $total = 0.0;
$days = max(1,(int)((strtotime($to??'now') - strtotime($from??'now'))/86400)+1);

foreach ($rows ?? [] as $r) {
  $catLabel = trim(($r['acc_code'] ?? '0000').' '.($r['acc_name'] ?? 'Uncategorized'));
  if (!isset($groups[$catLabel])) $groups[$catLabel] = ['sum'=>0.0,'items'=>[]];
  $amt = (float)($r['amount'] ?? 0);
  $groups[$catLabel]['sum']   += $amt;
  $groups[$catLabel]['items'][] = $r;
  $total += $amt;
}
uasort($groups, fn($a,$b)=>($b['sum']<=>$a['sum']));

// Chart data (top 8 categories)
$chartLabels = []; $chartData=[];
$i=0; foreach ($groups as $k=>$g) { if ($i>=8) break; $chartLabels[]=$k; $chartData[]=round($g['sum'],2); $i++; }

// Current query helpers
$qs = static function(array $overrides = []) use ($from,$to,$q) {
  return http_build_query(array_filter([
    'from' => $from ?? '',
    'to'   => $to   ?? '',
    'q'    => $q    ?? '',
  ] + $overrides));
};
$base = rtrim((string)($module_base ?? ''), '/');
?>
<div class="space-y-6">

  <!-- Header + actions -->
  <div class="flex items-center justify-between gap-3 flex-wrap">
    <h1 class="text-xl font-semibold">Expenses</h1>
    <div class="flex items-center gap-2">
      <a href="<?= h($base.'/expenses/create') ?>"
         class="px-3 py-2 rounded-lg bg-emerald-600 text-white hover:bg-emerald-700">
        <i class="fa-solid fa-plus mr-1"></i> New Expense
      </a>
      <button id="btn-csv" class="px-3 py-2 rounded-lg border hover:bg-gray-50 dark:hover:bg-gray-800">
        Export CSV
      </button>
    </div>
  </div>

  <!-- Filters (Apply button stays on same row as Search) -->
  <form method="get" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3 p-3 rounded-xl border bg-white dark:bg-gray-900">
    <label class="text-sm">
      <div class="text-gray-500 mb-1">From</div>
      <input type="date" name="from" value="<?= h($from ?? '') ?>"
             class="w-full border rounded-lg px-3 py-2 bg-white dark:bg-gray-900">
    </label>

    <label class="text-sm">
      <div class="text-gray-500 mb-1">To</div>
      <input type="date" name="to" value="<?= h($to ?? '') ?>"
             class="w-full border rounded-lg px-3 py-2 bg-white dark:bg-gray-900">
    </label>

    <label class="text-sm">
      <div class="text-gray-500 mb-1">Bank Account</div>
      <select name="bank_account_id"
              class="w-full border rounded-lg px-3 py-2 bg-white dark:bg-gray-900">
        <?php $selBank=(int)($_GET['bank_account_id']??0); ?>
        <option value="">All banks</option>
        <?php foreach (($banks ?? []) as $b): ?>
          <option value="<?= (int)$b['id'] ?>" <?= $selBank===(int)$b['id']?'selected':'' ?>>
            <?= h($b['account_name']) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </label>

    <!-- Search + Apply in one row -->
    <div class="text-sm sm:col-span-2 lg:col-span-2">
      <div class="text-gray-500 mb-1">Search (payee / memo / category)</div>
      <div class="flex items-center gap-2">
        <input type="text" name="q" value="<?= h($q ?? '') ?>"
               placeholder="e.g. fuel, office, supplier…"
               class="w-full border rounded-lg px-3 py-2 bg-white dark:bg-gray-900">
        <button class="px-3 py-2 rounded-lg bg-emerald-600 text-white hover:bg-emerald-700">
          Apply
        </button>
      </div>
    </div>
  </form>

  <!-- KPIs -->
  <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
    <div class="p-4 rounded-xl border bg-white dark:bg-gray-900">
      <div class="text-xs uppercase text-gray-500">Total Expense</div>
      <div class="text-2xl font-bold mt-1">৳ <?= $money($total) ?></div>
    </div>
    <div class="p-4 rounded-xl border bg-white dark:bg-gray-900">
      <div class="text-xs uppercase text-gray-500">Avg / Day</div>
      <div class="text-2xl font-bold mt-1">৳ <?= $money($total/$days) ?></div>
    </div>
    <div class="p-4 rounded-xl border bg-white dark:bg-gray-900">
      <div class="text-xs uppercase text-gray-500">Transactions</div>
      <div class="text-2xl font-bold mt-1"><?= number_format(count($rows ?? [])) ?></div>
    </div>
  </div>

  <!-- Category Grid -->
  <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
    <?php if (!$groups): ?>
      <div class="p-6 rounded-xl border bg-white dark:bg-gray-900 text-gray-500">
        No expenses for the selected filters.
      </div>
    <?php else: ?>
      <?php foreach ($groups as $cat => $g): ?>
        <div class="rounded-xl border bg-white dark:bg-gray-900 overflow-hidden">
          <div class="flex items-center justify-between p-4 border-b">
            <div>
              <div class="text-sm text-gray-500"><?= h($cat) ?></div>
              <div class="text-xl font-semibold mt-0.5">৳ <?= $money($g['sum']) ?></div>
            </div>
            <!-- FIXED: link to /expenses (not /transactions) -->
            <a class="text-xs px-2 py-1 rounded-full border hover:bg-gray-50 dark:hover:bg-gray-800"
               href="<?= h($base.'/expenses?'.$qs(['q'=>$cat])) ?>">
              View only
            </a>
          </div>
          <div class="divide-y">
            <?php
              usort($g['items'], fn($a,$b)=>strcmp($b['expense_date'],$a['expense_date']));
              $preview = array_slice($g['items'], 0, 3);
            ?>
            <?php foreach ($preview as $r): ?>
              <div class="p-3 flex items-start justify-between gap-3">
                <div class="min-w-0">
                  <div class="text-[13px] text-gray-500">
                    <?= h($r['expense_date']) ?> · <?= h($r['bank_name'] ?? '—') ?>
                  </div>
                  <div class="text-sm font-medium truncate">
                    <?= h($r['payee'] ?: ($r['memo'] ?: '—')) ?>
                  </div>
                  <?php if (!empty($r['memo'])): ?>
                    <div class="text-[12px] text-gray-500 truncate"><?= h($r['memo']) ?></div>
                  <?php endif; ?>
                </div>
                <div class="text-right font-semibold">৳ <?= $money($r['amount']) ?></div>
              </div>
            <?php endforeach; ?>
            <?php if (count($g['items']) > 3): ?>
              <div class="px-3 py-2 text-[12px] text-gray-500">
                + <?= count($g['items'])-3 ?> more in this period
              </div>
            <?php endif; ?>
          </div>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>

  <!-- Chart: Top Categories -->
  <div class="p-4 rounded-xl border bg-white dark:bg-gray-900">
    <div class="text-sm font-semibold mb-2">Top Categories</div>
    <div class="h-[220px]">
      <canvas id="chartTopCats" height="200" aria-label="Top categories"></canvas>
    </div>
  </div>

  <!-- Recent table -->
  <div class="overflow-x-auto rounded-xl border bg-white dark:bg-gray-900">
    <table class="min-w-full text-[13px]" id="tx-table">
      <thead class="bg-gray-50 dark:bg-gray-800 text-gray-600 dark:text-gray-300">
        <tr>
          <th class="px-2 py-1.5 text-left w-28">Date</th>
          <th class="px-2 py-1.5 text-left w-28">No.</th>
          <th class="px-2 py-1.5 text-left w-44">Category</th>
          <th class="px-2 py-1.5 text-left w-40">Payee</th>
          <th class="px-2 py-1.5 text-left">Bank</th>
          <th class="px-2 py-1.5 text-left">Memo</th>
          <th class="px-2 py-1.5 text-right w-28">Amount</th>
          <th class="px-2 py-1.5 text-right w-20"></th>
        </tr>
      </thead>
      <tbody>
        <?php if (!$rows): ?>
          <tr><td colspan="8" class="px-3 py-6 text-center text-slate-500">No transactions to show.</td></tr>
        <?php else: ?>
          <?php foreach ($rows as $r): ?>
            <tr class="border-t">
              <td class="px-2 py-1.5"><?= h($r['expense_date']) ?></td>
              <td class="px-2 py-1.5"><?= h($r['expense_no']) ?></td>
              <td class="px-2 py-1.5"><?= h(trim(($r['acc_code'] ?? '').' '.($r['acc_name'] ?? ''))) ?></td>
              <td class="px-2 py-1.5"><?= h($r['payee'] ?: '—') ?></td>
              <td class="px-2 py-1.5"><?= h($r['bank_name'] ?: '—') ?></td>
              <td class="px-2 py-1.5 truncate max-w-[320px]"><?= h($r['memo'] ?: '—') ?></td>
              <td class="px-2 py-1.5 text-right font-semibold">৳ <?= $money($r['amount']) ?></td>
              <td class="px-2 py-1.5 text-right">
                <!-- FIXED: /expenses/{id}/edit -->
                <a class="text-xs px-2 py-1 rounded-lg border hover:bg-gray-50 dark:hover:bg-gray-800"
                   href="<?= h($base.'/expenses/'.(int)$r['id'].'/edit') ?>">Edit</a>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
      <?php if ($rows): ?>
      <tfoot class="bg-gray-50 dark:bg-gray-800">
        <tr class="font-semibold border-t">
          <td class="px-2 py-2" colspan="6">Total</td>
          <td class="px-2 py-2 text-right">৳ <?= $money($total) ?></td>
          <td></td>
        </tr>
      </tfoot>
      <?php endif; ?>
    </table>
  </div>
</div>

<script>
(function () {
  // CSV export
  document.getElementById('btn-csv')?.addEventListener('click', ()=>{
    const rows = Array.from(document.querySelectorAll('#tx-table tr'));
    const csv  = rows.map(tr => Array.from(tr.children).map(td=>{
      const t=(td.innerText||'').trim().replace(/\s+/g,' ');
      return `"${t.replace(/"/g,'""')}"`;
    }).join(',')).join('\n');
    const blob=new Blob([csv],{type:'text/csv;charset=utf-8;'});
    const url=URL.createObjectURL(blob); const a=document.createElement('a');
    a.href=url; a.download='expenses.csv'; document.body.appendChild(a); a.click(); a.remove(); URL.revokeObjectURL(url);
  });

  // Top categories chart (defensive if Chart.js missing)
  if (typeof Chart !== 'undefined') {
    const ctx  = document.getElementById('chartTopCats');
    const labels = <?= json_encode($chartLabels, JSON_UNESCAPED_UNICODE) ?>;
    const data   = <?= json_encode($chartData,   JSON_UNESCAPED_UNICODE) ?>;
    new Chart(ctx, {
      type: 'bar',
      data: { labels, datasets: [{ label: '৳ by category', data }] },
      options: {
        responsive: true, maintainAspectRatio: false,
        plugins: { legend: { display: true } },
        scales: { x: { ticks: { autoSkip: true, maxRotation:0 } }, y: { beginAtZero: true } }
      }
    });
  }
})();
</script>