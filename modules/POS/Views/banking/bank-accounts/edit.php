<?php
/**
 * @var array  $b    normalised bank account (see BankAccountsController::norm)
 * @var string $base base URL like /t/{slug}/apps/pos/banking
 */
$h     = fn($x)=>htmlspecialchars((string)$x, ENT_QUOTES, 'UTF-8');
$brand = '#228B22';
?>
<div class="px-6 py-6 max-w-3xl">
  <div class="flex items-center justify-between mb-4">
    <div>
      <h1 class="text-2xl md:text-3xl font-semibold text-gray-900 dark:text-gray-50 flex items-center gap-2">
        <i class="fa fa-landmark text-emerald-500" aria-hidden="true"></i>
        <span>Edit HQ Bank Account</span>
      </h1>
      <p class="text-sm text-gray-500 dark:text-gray-400">
        Update master-level bank &amp; cash account for the main branch.
      </p>
    </div>
    <a href="<?= $h($base) ?>/accounts"
       class="inline-flex items-center gap-2 px-3 py-2 rounded-xl border border-gray-200 dark:border-gray-700 text-sm text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-800">
      <i class="fa fa-arrow-left" aria-hidden="true"></i>
      <span>Back</span>
    </a>
  </div>

  <form method="post"
        action="<?= $h($base) ?>/accounts/<?= $h($b['id']) ?>"
        class="space-y-5 bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-2xl px-5 py-5 shadow-sm">

    <!-- Code (read-only) -->
    <div>
      <label class="block text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400 mb-1">
        Account Code
      </label>
      <input value="<?= $h($b['code']) ?>" disabled
             class="w-full px-3 py-2.5 rounded-xl border border-dashed border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800 text-sm text-gray-500 dark:text-gray-400">
    </div>

    <!-- Bank + account name -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
      <div>
        <label class="block text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400 mb-1">
          Bank Name <span class="text-red-500">*</span>
        </label>
        <input name="bank_name" required
               value="<?= $h($b['bank_name']) ?>"
               class="w-full px-3 py-2.5 rounded-xl border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800 text-sm text-gray-900 dark:text-gray-50 focus:outline-none focus:ring-2 focus:ring-emerald-500/70 focus:border-emerald-500">
      </div>
      <div>
        <label class="block text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400 mb-1">
          Account Name <span class="text-red-500">*</span>
        </label>
        <input name="account_name" required
               value="<?= $h($b['name']) ?>"
               class="w-full px-3 py-2.5 rounded-xl border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800 text-sm text-gray-900 dark:text-gray-50 focus:outline-none focus:ring-2 focus:ring-emerald-500/70 focus:border-emerald-500">
      </div>
    </div>

    <!-- Account no + opening balance -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
      <div>
        <label class="block text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400 mb-1">
          Account Number <span class="text-red-500">*</span>
        </label>
        <input name="account_no" required
               value="<?= $h($b['account_no']) ?>"
               class="w-full px-3 py-2.5 rounded-xl border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800 text-sm text-gray-900 dark:text-gray-50 focus:outline-none focus:ring-2 focus:ring-emerald-500/70 focus:border-emerald-500">
      </div>
      <div>
        <label class="block text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400 mb-1">
          Opening Balance
        </label>
        <div class="relative">
          <span class="absolute inset-y-0 left-3 flex items-center text-gray-400 text-xs">৳</span>
          <input name="opening_balance" type="number" step="0.01" min="0"
                 value="<?= $h(number_format($b['opening_balance'], 2, '.', '')) ?>"
                 class="w-full pl-7 pr-3 py-2.5 rounded-xl border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800 text-sm text-gray-900 dark:text-gray-50 focus:outline-none focus:ring-2 focus:ring-emerald-500/70 focus:border-emerald-500">
        </div>
        <p class="mt-1 text-[11px] text-gray-400 dark:text-gray-500">
          Used for reporting only; live balance uses GL in full accounting version.
        </p>
      </div>
    </div>

    <!-- Current balance (read-only snapshot) -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
      <div class="md:col-span-1">
        <p class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400 mb-1">
          Current Balance (snapshot)
        </p>
        <div class="px-3 py-2.5 rounded-xl border border-gray-100 dark:border-gray-700 bg-emerald-50/60 dark:bg-emerald-900/30 text-sm font-semibold text-emerald-700 dark:text-emerald-300">
          ৳<?= $h(number_format($b['current_balance'], 2)) ?>
        </div>
      </div>
    </div>

    <!-- Footer buttons -->
    <div class="flex items-center justify-between pt-2">
      <p class="text-[11px] text-gray-400 dark:text-gray-500">
        HQ users can see all bank &amp; cash accounts. Outlets only see their own outlet accounts.
      </p>
      <div class="flex gap-2">
        <a href="<?= $h($base) ?>/accounts"
           class="inline-flex items-center gap-1 px-3 py-2 rounded-xl border border-gray-300 dark:border-gray-700 text-sm text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-800">
          <i class="fa fa-times text-xs" aria-hidden="true"></i>
          Cancel
        </a>
        <button type="submit"
                class="inline-flex items-center gap-2 px-4 py-2 rounded-xl text-sm font-semibold text-white shadow"
                style="background:<?= $brand ?>;">
          <i class="fa fa-check" aria-hidden="true"></i>
          Save Changes
        </button>
      </div>
    </div>
  </form>
</div>