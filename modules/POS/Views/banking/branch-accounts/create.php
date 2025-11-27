<?php
/**
 * @var string $base
 * @var int    $branchId
 * @var string $branchName
 * @var array  $hqAccounts
 */
$h = fn($x)=>htmlspecialchars((string)$x, ENT_QUOTES, 'UTF-8');
?>
<div class="px-6 py-6 max-w-3xl">
  <div class="flex items-center justify-between mb-4">
    <div>
      <h1 class="text-2xl md:text-3xl font-semibold text-gray-900 dark:text-gray-50 flex items-center gap-2">
        <i class="fa fa-store text-emerald-500" aria-hidden="true"></i>
        <span>New Outlet Bank Account</span>
      </h1>
      <p class="text-sm text-gray-500 dark:text-gray-400">
        Map this branch to a HQ bank/cash account for deposits.
      </p>
      <p class="mt-1 text-xs text-gray-400 dark:text-gray-500">
        Branch:
        <span class="inline-flex items-center px-2 py-0.5 rounded-full border border-gray-200 dark:border-gray-700">
          <?= $h($branchName ?: ('Branch '.$branchId)) ?>
        </span>
      </p>
    </div>
    <a href="<?= $h($base) ?>/branches"
       class="inline-flex items-center gap-2 px-3 py-2 rounded-xl border border-gray-200 dark:border-gray-700 text-sm text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-800">
      <i class="fa fa-arrow-left" aria-hidden="true"></i>
      <span>Back</span>
    </a>
  </div>

  <form method="post"
        action="<?= $h($base) ?>/branches"
        class="space-y-5 bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-2xl px-5 py-5 shadow-sm">

    <div>
      <label class="block text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400 mb-1">
        HQ Bank / Cash Account <span class="text-red-500">*</span>
      </label>
      <select name="hq_bank_account_id" required
              class="w-full px-3 py-2.5 rounded-xl border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800 text-sm">
        <option value="">Select...</option>
        <?php foreach ($hqAccounts as $a): ?>
          <option value="<?= $h($a['bank_account_id']) ?>">
            <?= $h(($a['bank_name'] ?? '') . ' — ' . ($a['name'] ?? '')) ?>
            <?php if (!empty($a['account_no'])): ?>
              (<?= $h($a['account_no']) ?>)
            <?php endif; ?>
          </option>
        <?php endforeach; ?>
      </select>
      <p class="mt-1 text-[11px] text-gray-400 dark:text-gray-500">
        Money actually lands in this HQ account when this branch deposits cash.
      </p>
    </div>

    <div>
      <label class="block text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400 mb-1">
        Outlet Alias (optional)
      </label>
      <input name="alias_name"
             class="w-full px-3 py-2.5 rounded-xl border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800 text-sm text-gray-900 dark:text-gray-50"
             placeholder="e.g. Mirpur Branch Collection">
      <p class="mt-1 text-[11px] text-gray-400 dark:text-gray-500">
        Friendly name used only inside this outlet.
      </p>
    </div>

    <div class="flex items-center justify-between gap-4">
      <label class="inline-flex items-center gap-2 text-sm text-gray-700 dark:text-gray-200">
        <input type="checkbox" name="is_default" value="1"
               class="rounded border-gray-300 dark:border-gray-600 text-emerald-600 focus:ring-emerald-500">
        <span>Set as default deposit account</span>
      </label>
      <label class="inline-flex items-center gap-2 text-sm text-gray-700 dark:text-gray-200">
        <input type="checkbox" name="is_active" value="1" checked
               class="rounded border-gray-300 dark:border-gray-600 text-emerald-600 focus:ring-emerald-500">
        <span>Active</span>
      </label>
    </div>

    <div class="flex items-center justify-between pt-2">
      <p class="text-[11px] text-gray-400 dark:text-gray-500">
        Each branch can have multiple linked HQ accounts, but only one default.
      </p>
      <div class="flex gap-2">
        <a href="<?= $h($base) ?>/branches"
           class="inline-flex items-center gap-1 px-3 py-2 rounded-xl border border-gray-300 dark:border-gray-700 text-sm text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-800">
          <i class="fa fa-times text-xs" aria-hidden="true"></i>
          Cancel
        </a>
        <button type="submit"
                class="inline-flex items-center gap-2 px-4 py-2 rounded-xl text-sm font-semibold text-white shadow bg-emerald-600 hover:bg-emerald-700">
          <i class="fa fa-check" aria-hidden="true"></i>
          Save Outlet Account
        </button>
      </div>
    </div>
  </form>
</div>