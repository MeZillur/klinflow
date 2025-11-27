<?php
declare(strict_types=1);

/**
 * Views/accounts/dashboard.php
 * Simplified responsive dashboard
 *
 * Inputs:
 * - $module_base (string)
 * - $kpi: [
 *     sales_mtd, cogs_mtd, gp_mtd, cash_bal, ar_bal, ap_bal,
 *     sales_today, cogs_today
 *   ]
 * - $trend (ignored here)
 */

$module_base = isset($module_base) && is_string($module_base) ? $module_base : '';
$kpi = is_array($kpi ?? null) ? $kpi : [];

$fmt = static fn($n): string => number_format((float)$n, 2);
$h   = static fn($s): string => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');

// Derived
$sales_mtd = (float)($kpi['sales_mtd'] ?? 0);
$gp_mtd    = (float)($kpi['gp_mtd']    ?? 0);
$marginPct = $sales_mtd ? round(($gp_mtd / $sales_mtd) * 100, 1) : null;
?>
<div class="space-y-6 px-2 sm:px-4 md:px-6">

  <!-- Header -->
  <div class="flex items-center justify-between flex-wrap gap-3">
    <h1 class="text-2xl font-semibold text-gray-800 dark:text-gray-100">📊 Accounting Dashboard</h1>
    <div class="text-sm text-gray-500"><?= date('Y-m-d') ?></div>
  </div>

  <!-- KPI CARDS ONLY (2×2 on small, 4×1 on desktop) -->
  <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4">
    <div class="p-4 rounded-2xl border bg-white dark:bg-gray-900 shadow-sm hover:shadow-md transition">
      <div class="text-[11px] uppercase tracking-wide text-gray-500">Sales (MTD)</div>
      <div class="text-xl sm:text-2xl font-semibold mt-1 text-gray-900 dark:text-gray-50">৳ <?= $fmt($kpi['sales_mtd'] ?? 0) ?></div>
      <div class="text-[11px] text-gray-500 mt-1">Today: ৳ <?= $fmt($kpi['sales_today'] ?? 0) ?></div>
    </div>

    <div class="p-4 rounded-2xl border bg-white dark:bg-gray-900 shadow-sm hover:shadow-md transition">
      <div class="text-[11px] uppercase tracking-wide text-gray-500">COGS (MTD)</div>
      <div class="text-xl sm:text-2xl font-semibold mt-1 text-gray-900 dark:text-gray-50">৳ <?= $fmt($kpi['cogs_mtd'] ?? 0) ?></div>
      <div class="text-[11px] text-gray-500 mt-1">Today: ৳ <?= $fmt($kpi['cogs_today'] ?? 0) ?></div>
    </div>

    <div class="p-4 rounded-2xl border bg-white dark:bg-gray-900 shadow-sm hover:shadow-md transition">
      <div class="text-[11px] uppercase tracking-wide text-gray-500">Gross Profit (MTD)</div>
      <div class="text-xl sm:text-2xl font-semibold mt-1 text-emerald-600 dark:text-emerald-400">৳ <?= $fmt($kpi['gp_mtd'] ?? 0) ?></div>
      <div class="text-[11px] text-gray-500 mt-1">Margin: <?= $marginPct !== null ? $marginPct.'%' : '—' ?></div>
    </div>

    <div class="p-4 rounded-2xl border bg-white dark:bg-gray-900 shadow-sm hover:shadow-md transition">
      <div class="text-[11px] uppercase tracking-wide text-gray-500">Cash Balance</div>
      <div class="text-xl sm:text-2xl font-semibold mt-1 text-gray-900 dark:text-gray-50">৳ <?= $fmt($kpi['cash_bal'] ?? 0) ?></div>
      <div class="text-[11px] text-gray-500 mt-1">A/R: ৳ <?= $fmt($kpi['ar_bal'] ?? 0) ?> · A/P: ৳ <?= $fmt($kpi['ap_bal'] ?? 0) ?></div>
    </div>
  </div>

  <!-- SUMMARY SECTION (kept) -->
  <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
    <!-- Snapshot -->
    <div class="p-4 rounded-2xl border bg-white dark:bg-gray-900 shadow-sm">
      <div class="text-sm font-semibold mb-3">Snapshot</div>
      <div class="overflow-x-auto">
        <table class="min-w-full text-sm">
          <thead class="text-gray-500">
            <tr>
              <th class="px-3 py-2 text-left">Metric</th>
              <th class="px-3 py-2 text-right">Amount</th>
            </tr>
          </thead>
          <tbody class="divide-y">
            <tr><td class="px-3 py-2">Cash Balance</td><td class="px-3 py-2 text-right">৳ <?= $fmt($kpi['cash_bal'] ?? 0) ?></td></tr>
            <tr><td class="px-3 py-2">Accounts Receivable</td><td class="px-3 py-2 text-right">৳ <?= $fmt($kpi['ar_bal'] ?? 0) ?></td></tr>
            <tr><td class="px-3 py-2">Accounts Payable</td><td class="px-3 py-2 text-right">৳ <?= $fmt($kpi['ap_bal'] ?? 0) ?></td></tr>
            <tr class="bg-gray-50 dark:bg-gray-800/40 font-medium">
              <td class="px-3 py-2">Gross Profit (MTD)</td>
              <td class="px-3 py-2 text-right text-emerald-600 dark:text-emerald-400">৳ <?= $fmt($kpi['gp_mtd'] ?? 0) ?></td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Bank & Cash Breakdown (placeholder totals) -->
    <div class="p-4 rounded-2xl border bg-white dark:bg-gray-900 shadow-sm">
      <div class="text-sm font-semibold mb-3">Bank & Cash Breakdown</div>
      <div class="text-xs text-gray-500 mb-2">When you pass a real breakdown, this will list actual accounts.</div>
      <div class="overflow-x-auto">
        <table class="min-w-full text-sm">
          <thead class="text-gray-500">
            <tr>
              <th class="px-3 py-2 text-left">Account</th>
              <th class="px-3 py-2 text-right">Balance</th>
            </tr>
          </thead>
          <tbody class="divide-y">
            <tr><td class="px-3 py-2">All Cash/Bank</td><td class="px-3 py-2 text-right">৳ <?= $fmt($kpi['cash_bal'] ?? 0) ?></td></tr>
            <tr><td class="px-3 py-2">A/R</td><td class="px-3 py-2 text-right">৳ <?= $fmt($kpi['ar_bal'] ?? 0) ?></td></tr>
            <tr><td class="px-3 py-2">A/P</td><td class="px-3 py-2 text-right">৳ <?= $fmt($kpi['ap_bal'] ?? 0) ?></td></tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <!-- Quick links -->
  <div class="flex flex-wrap gap-2 pt-3">
    <a href="<?= $h($module_base) ?>/accounts/trial-balance" class="px-3 py-1.5 rounded-lg border hover:bg-gray-50 dark:hover:bg-gray-800">Trial Balance</a>
    <a href="<?= $h($module_base) ?>/accounts/ledger" class="px-3 py-1.5 rounded-lg border hover:bg-gray-50 dark:hover:bg-gray-800">General Ledger</a>
    <a href="<?= $h($module_base) ?>/reports" class="px-3 py-1.5 rounded-lg border hover:bg-gray-50 dark:hover:bg-gray-800">Reports</a>
  </div>
</div>