<?php
declare(strict_types=1);
/** @var array $totals @var array $recent @var array $banks @var string $base @var string $brand */
$h = fn($v)=>htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
?>
<div class="px-6 py-6 space-y-6">

  <!-- Header -->
  <div class="flex flex-wrap items-center justify-between gap-4">
    <div>
      <h1 class="text-2xl md:text-3xl font-semibold text-gray-900 dark:text-gray-50">
        Money &amp; Banking
      </h1>
      <p class="text-sm text-gray-500 dark:text-gray-400">
        Track income, expenses and bank balances across branches.
      </p>
    </div>

    <div class="flex flex-wrap gap-2 text-sm">
      <a href="<?= $h($base) ?>/money/income/create"
         class="inline-flex items-center gap-2 px-3 py-2 rounded-lg text-white shadow"
         style="background:<?= $brand ?>;">
        <i class="fa fa-arrow-down-long"></i>
        Record Income
      </a>
      <a href="<?= $h($base) ?>/money/expense/create"
         class="inline-flex items-center gap-2 px-3 py-2 rounded-lg border border-red-200 text-red-700 bg-red-50 hover:bg-red-100">
        <i class="fa fa-arrow-up-long"></i>
        Record Expense
      </a>
    </div>
  </div>

  <!-- KPI cards -->
  <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-4">
    <?php
      $inc = $totals['income'] ?? ['today'=>0,'month'=>0];
      $exp = $totals['expense']?? ['today'=>0,'month'=>0];
      $netToday = ($inc['today'] ?? 0) - ($exp['today'] ?? 0);
      $netMonth = ($inc['month'] ?? 0) - ($exp['month'] ?? 0);
    ?>
    <div class="rounded-2xl p-4 bg-white dark:bg-gray-900 shadow-sm border border-gray-100 dark:border-gray-800">
      <p class="text-xs font-semibold text-gray-500 uppercase">Today Income</p>
      <p class="mt-2 text-2xl font-bold text-emerald-600">৳<?= number_format((float)$inc['today'],2) ?></p>
      <p class="mt-1 text-xs text-gray-400">All money-in entries for today</p>
    </div>
    <div class="rounded-2xl p-4 bg-white dark:bg-gray-900 shadow-sm border border-gray-100 dark:border-gray-800">
      <p class="text-xs font-semibold text-gray-500 uppercase">Today Expense</p>
      <p class="mt-2 text-2xl font-bold text-red-600">৳<?= number_format((float)$exp['today'],2) ?></p>
      <p class="mt-1 text-xs text-gray-400">All money-out entries for today</p>
    </div>
    <div class="rounded-2xl p-4 bg-white dark:bg-gray-900 shadow-sm border border-gray-100 dark:border-gray-800">
      <p class="text-xs font-semibold text-gray-500 uppercase">Net Cashflow (Month)</p>
      <p class="mt-2 text-2xl font-bold <?= $netMonth >= 0 ? 'text-emerald-600' : 'text-red-600' ?>">
        ৳<?= number_format((float)$netMonth,2) ?>
      </p>
      <p class="mt-1 text-xs text-gray-400">Income − Expense this month</p>
    </div>
    <div class="rounded-2xl p-4 bg-white dark:bg-gray-900 shadow-sm border border-gray-100 dark:border-gray-800">
      <p class="text-xs font-semibold text-gray-500 uppercase">Net Cashflow (Today)</p>
      <p class="mt-2 text-2xl font-bold <?= $netToday >= 0 ? 'text-emerald-600' : 'text-red-600' ?>">
        ৳<?= number_format((float)$netToday,2) ?>
      </p>
      <p class="mt-1 text-xs text-gray-400">Positive = more in than out</p>
    </div>
  </div>

  <!-- Row 2: Banks + recent entries -->
  <div class="grid grid-cols-1 xl:grid-cols-3 gap-4">

    <!-- Banks -->
    <div class="xl:col-span-1 rounded-2xl bg-white dark:bg-gray-900 shadow-sm border border-gray-100 dark:border-gray-800 p-4">
      <div class="flex items-center justify-between mb-3">
        <h2 class="text-sm font-semibold text-gray-900 dark:text-gray-50 flex items-center gap-2">
          <i class="fa fa-building-columns text-emerald-500"></i>
          Bank Accounts
        </h2>
      </div>

      <?php if (empty($banks)): ?>
        <p class="text-xs text-gray-400">No bank accounts found. Configure them in the Accounting module.</p>
      <?php else: ?>
        <ul class="space-y-2 text-sm">
          <?php foreach ($banks as $b): ?>
            <li class="flex items-center justify-between gap-2 rounded-xl px-2 py-2 hover:bg-gray-50 dark:hover:bg-gray-800 transition">
              <div>
                <div class="font-medium text-gray-900 dark:text-gray-50">
                  <?= $h($b['bank_name'] ?? '') ?>
                </div>
                <div class="text-[11px] text-gray-500 dark:text-gray-400">
                  <?= $h($b['account_name'] ?? '') ?>
                  <?php if (!empty($b['account_no'])): ?>
                    • <?= $h($b['account_no']) ?>
                  <?php endif; ?>
                </div>
              </div>
              <div class="text-right">
                <div class="text-xs text-gray-400">Balance</div>
                <div class="text-sm font-semibold text-gray-900 dark:text-gray-50">
                  ৳<?= number_format((float)($b['current_balance'] ?? 0),2) ?>
                </div>
                <div class="text-[11px] <?= ($b['movement_month'] ?? 0) >= 0 ? 'text-emerald-500' : 'text-red-500' ?>">
                  <?= ($b['movement_month'] ?? 0) >= 0 ? '+' : '−' ?>
                  ৳<?= number_format(abs((float)($b['movement_month'] ?? 0)),2) ?> this month
                </div>
              </div>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </div>

    <!-- Recent entries -->
    <div class="xl:col-span-2 rounded-2xl bg-white dark:bg-gray-900 shadow-sm border border-gray-100 dark:border-gray-800 p-4">
      <div class="flex items-center justify-between mb-3">
        <h2 class="text-sm font-semibold text-gray-900 dark:text-gray-50 flex items-center gap-2">
          <i class="fa fa-clock-rotate-left text-indigo-500"></i>
          Recent Money Entries
        </h2>
      </div>

      <?php if (empty($recent)): ?>
        <p class="text-xs text-gray-400">Nothing recorded yet. Start by adding income or expense.</p>
      <?php else: ?>
        <div class="overflow-x-auto">
          <table class="min-w-full text-xs text-left">
            <thead class="border-b border-gray-100 dark:border-gray-800 text-gray-500 dark:text-gray-400">
              <tr>
                <th class="py-2 pr-3">Date</th>
                <th class="py-2 pr-3">Type</th>
                <th class="py-2 pr-3">Category</th>
                <th class="py-2 pr-3">Bank</th>
                <th class="py-2 pr-3">Description</th>
                <th class="py-2 pr-3 text-right">Amount</th>
              </tr>
            </thead>
            <tbody>
            <?php foreach ($recent as $r): ?>
              <tr class="border-b border-gray-50 dark:border-gray-800 last:border-0">
                <td class="py-1.5 pr-3">
                  <?= $h(date('d M Y', strtotime((string)$r['entry_date']))) ?>
                </td>
                <td class="py-1.5 pr-3">
                  <?php if (($r['type'] ?? '') === 'income'): ?>
                    <span class="inline-flex items-center px-2 py-0.5 rounded-full bg-emerald-50 text-emerald-700 text-[11px]">
                      <i class="fa fa-arrow-down-long mr-1"></i> Income
                    </span>
                  <?php else: ?>
                    <span class="inline-flex items-center px-2 py-0.5 rounded-full bg-red-50 text-red-700 text-[11px]">
                      <i class="fa fa-arrow-up-long mr-1"></i> Expense
                    </span>
                  <?php endif; ?>
                </td>
                <td class="py-1.5 pr-3">
                  <?= $h($r['category_name'] ?? '-') ?>
                </td>
                <td class="py-1.5 pr-3">
                  <?= $h($r['bank_name'] ?? '') ?>
                </td>
                <td class="py-1.5 pr-3 text-gray-500 dark:text-gray-400">
                  <?= $h($r['description'] ?? $r['ref_no'] ?? '') ?>
                </td>
                <td class="py-1.5 pr-3 text-right font-semibold <?= ($r['type'] ?? '')==='income' ? 'text-emerald-600' : 'text-red-600' ?>">
                  ৳<?= number_format((float)$r['amount'],2) ?>
                </td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>