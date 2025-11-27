<?php
/**
 * New HQ Bank Account
 *
 * Expects:
 *  - $base string  module base (/apps/pos or /t/{slug}/apps/pos)
 */

$h     = fn($x)=>htmlspecialchars((string)$x, ENT_QUOTES, 'UTF-8');
$brand = '#228B22';
?>
<div class="px-6 py-6 max-w-3xl mx-auto">
  <!-- Header -->
  <div class="flex items-center justify-between mb-5">
    <div>
      <h1 class="text-2xl md:text-3xl font-semibold text-gray-900 dark:text-gray-50 flex items-center gap-2">
        <span class="inline-flex h-9 w-9 items-center justify-center rounded-xl bg-emerald-100 text-emerald-700">
          <i class="fa fa-landmark" aria-hidden="true"></i>
        </span>
        <span>New HQ Bank Account</span>
      </h1>
      <p class="text-sm text-gray-500 dark:text-gray-400">
        Master-level bank &amp; cash account for the main branch.
      </p>
    </div>

    <a href="<?= $h($base) ?>/banking/accounts"
       class="inline-flex items-center gap-2 px-3 py-2 rounded-xl border border-gray-200 dark:border-gray-700
              text-sm text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-800">
      <i class="fa fa-arrow-left" aria-hidden="true"></i>
      <span>Back</span>
    </a>
  </div>

  <!-- Form -->
  <form method="post"
        action="<?= $h($base) ?>/banking/accounts"
        class="space-y-6 bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800
               rounded-2xl px-5 py-6 shadow-sm">

    <!-- Bank + account name -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
      <div>
        <label class="block text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400 mb-1">
          Bank Name <span class="text-red-500">*</span>
        </label>
        <input name="bank_name" required
               class="w-full px-3 py-2.5 rounded-xl border border-gray-200 dark:border-gray-700
                      bg-gray-50 dark:bg-gray-800 text-sm text-gray-900 dark:text-gray-50
                      focus:outline-none focus:ring-2 focus:ring-emerald-500/70 focus:border-emerald-500"
               placeholder="e.g. BRAC Bank, City Bank">
      </div>

      <div>
        <label class="block text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400 mb-1">
          Account Name <span class="text-red-500">*</span>
        </label>
        <input name="account_name" required
               class="w-full px-3 py-2.5 rounded-xl border border-gray-200 dark:border-gray-700
                      bg-gray-50 dark:bg-gray-800 text-sm text-gray-900 dark:text-gray-50
                      focus:outline-none focus:ring-2 focus:ring-emerald-500/70 focus:border-emerald-500"
               placeholder="e.g. Happy Mart Collection">
      </div>
    </div>

    <!-- Account no + opening balance -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
      <div>
        <label class="block text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400 mb-1">
          Account Number <span class="text-red-500">*</span>
        </label>
        <input name="account_no" required
               class="w-full px-3 py-2.5 rounded-xl border border-gray-200 dark:border-gray-700
                      bg-gray-50 dark:bg-gray-800 text-sm text-gray-900 dark:text-gray-50
                      focus:outline-none focus:ring-2 focus:ring-emerald-500/70 focus:border-emerald-500"
               placeholder="e.g. 0123-456789-01">
      </div>

      <div>
        <label class="block text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400 mb-1">
          Opening Balance
        </label>
        <div class="relative">
          <span class="absolute inset-y-0 left-3 flex items-center text-gray-400 text-xs">৳</span>
          <input name="opening_balance" type="number" step="0.01" min="0"
                 class="w-full pl-7 pr-3 py-2.5 rounded-xl border border-gray-200 dark:border-gray-700
                        bg-gray-50 dark:bg-gray-800 text-sm text-gray-900 dark:text-gray-50
                        focus:outline-none focus:ring-2 focus:ring-emerald-500/70 focus:border-emerald-500"
                 placeholder="0.00">
        </div>
        <p class="mt-1 text-[11px] text-gray-400 dark:text-gray-500">
          Optional. If set, current balance will start from this amount.
        </p>
      </div>
    </div>

    <!-- Master toggle -->
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3">
      <label class="inline-flex items-center gap-2 text-sm text-gray-700 dark:text-gray-200">
        <input type="checkbox" name="is_master" value="1"
               class="rounded border-gray-300 dark:border-gray-600 text-emerald-600 focus:ring-emerald-500">
        <span class="font-medium">Mark as Master Bank Account</span>
      </label>
      <p class="text-[11px] text-gray-400 dark:text-gray-500 md:text-right">
        Only one master per organisation. Existing master (if any) will be downgraded automatically.
      </p>
    </div>

    <!-- Footer -->
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3 pt-2">
      <p class="text-[11px] text-gray-400 dark:text-gray-500">
        This account lives at <strong>HQ level</strong>. Outlets only see their own outlet bank accounts.
      </p>
      <div class="flex gap-2 justify-end">
        <a href="<?= $h($base) ?>/banking/accounts"
           class="inline-flex items-center gap-1 px-3 py-2 rounded-xl border border-gray-300 dark:border-gray-700
                  text-sm text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-800">
          <i class="fa fa-times text-xs" aria-hidden="true"></i>
          Cancel
        </a>
        <button type="submit"
                class="inline-flex items-center gap-2 px-4 py-2 rounded-xl text-sm font-semibold text-white shadow"
                style="background:<?= $brand ?>;">
          <i class="fa fa-check" aria-hidden="true"></i>
          Save Bank Account
        </button>
      </div>
    </div>
  </form>
</div>