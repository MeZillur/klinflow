<?php
declare(strict_types=1);

/**
 * modules/DMS/Views/reports/index.php
 * Minimal, dark-mode friendly report launcher.
 * Only includes reports we actually built.
 */
$base = htmlspecialchars((string)($module_base ?? '/apps/dms'), ENT_QUOTES, 'UTF-8');
$tile = 'group relative rounded-2xl border border-slate-200 dark:border-slate-800 bg-white/70 dark:bg-slate-900/70 backdrop-blur px-4 py-4 shadow-sm hover:shadow-md transition hover:border-emerald-300 dark:hover:border-emerald-400';
$iconWrap = 'h-10 w-10 rounded-xl grid place-items-center shadow-sm';
$chev = 'absolute right-3 top-3 opacity-0 group-hover:opacity-100 transition text-slate-400 group-hover:text-emerald-500';
?>
<div class="space-y-8">
  <div class="flex items-center justify-between flex-wrap gap-3">
    <h1 class="text-xl font-extrabold tracking-tight">Reports</h1>
    <div class="text-sm text-slate-500 dark:text-slate-400">Financial & operational insights</div>
  </div>

  <!-- Accounting -->
  <div class="space-y-3">
    <h2 class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Accounting</h2>

    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">

      <!-- Customer (A/R) Statement -->
      <a href="<?= $base ?>/reports/ar-statement?type=ar"
         class="<?= $tile ?>">
        <svg class="<?= $chev ?>" width="18" height="18" viewBox="0 0 24 24" fill="none">
          <path d="M9 18l6-6-6-6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>

        <div class="flex items-start gap-3">
          <div class="<?= $iconWrap ?> bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300">
            <!-- Users/Receivable icon -->
            <svg width="22" height="22" viewBox="0 0 24 24" fill="currentColor">
              <path d="M16 11c1.657 0 3-1.79 3-4s-1.343-4-3-4-3 1.79-3 4 1.343 4 3 4ZM7 12c2.21 0 4-2.239 4-5S9.21 2 7 2 3 4.239 3 7s1.79 5 4 5ZM7 14c-2.761 0-5 2.239-5 5v1a1 1 0 0 0 1 1h8.535A6.98 6.98 0 0 1 10 17c0-1.084.258-2.107.715-3H7Zm9 0a5 5 0 1 0 0 10 5 5 0 0 0 0-10Zm2.5 5.5h-3v-3h1.5v1.5H18.5v1.5Z"/>
            </svg>
          </div>
          <div class="min-w-0">
            <div class="font-semibold text-slate-800 dark:text-slate-100">A/R Statement (Customer)</div>
            <div class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">Customer ledger, opening → closing, debits/credits</div>
          </div>
        </div>

        <div class="mt-3 flex items-center gap-2 text-xs text-slate-500 dark:text-slate-400">
          <span class="inline-block h-2 w-2 rounded-full bg-emerald-500/80"></span>
          <span>Ledger: dms_ar_ledger</span>
        </div>
      </a>

      <!-- Supplier (A/P) Statement -->
      <a href="<?= $base ?>/reports/ar-statement?type=ap"
         class="<?= $tile ?>">
        <svg class="<?= $chev ?>" width="18" height="18" viewBox="0 0 24 24" fill="none">
          <path d="M9 18l6-6-6-6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>

        <div class="flex items-start gap-3">
          <div class="<?= $iconWrap ?> bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-300">
            <!-- Factory/Payable icon -->
            <svg width="22" height="22" viewBox="0 0 24 24" fill="currentColor">
              <path d="M3 21V8l6 3V8l6 3V8l6 3v10H3Zm4-2h2v-3H7v3Zm4 0h2v-3h-2v3Zm4 0h2v-3h-2v3Z"/>
            </svg>
          </div>
          <div class="min-w-0">
            <div class="font-semibold text-slate-800 dark:text-slate-100">A/P Statement (Supplier)</div>
            <div class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">Supplier ledger, opening → closing, bills/payments</div>
          </div>
        </div>

        <div class="mt-3 flex items-center gap-2 text-xs text-slate-500 dark:text-slate-400">
          <span class="inline-block h-2 w-2 rounded-full bg-amber-500/80"></span>
          <span>Ledger: dms_ap_ledger</span>
        </div>
      </a>

    </div>
  </div>

  <!-- Footer -->
  <div class="pt-6 border-t border-slate-200 dark:border-slate-800 text-xs text-slate-500 dark:text-slate-400">
    <p>KlinFlow DMS — <?= date('Y') ?>.</p>
  </div>
</div>