<?php
/**
 * @var array  $b    normalised bank account
 * @var string $base
 */
$h = fn($x)=>htmlspecialchars((string)$x, ENT_QUOTES, 'UTF-8');
?>
<div class="px-6 py-6 max-w-4xl">
  <div class="flex items-center justify-between mb-4">
    <div>
      <h1 class="text-2xl md:text-3xl font-semibold text-gray-900 dark:text-gray-50 flex items-center gap-2">
        <i class="fa fa-landmark text-emerald-500" aria-hidden="true"></i>
        <span><?= $h($b['name']) ?></span>
      </h1>
      <p class="text-sm text-gray-500 dark:text-gray-400">
        <?= $h($b['bank_name']) ?> &middot; <?= $h($b['account_no']) ?>
      </p>
    </div>
    <div class="flex gap-2">
      <a href="<?= $h($base) ?>/accounts/<?= $h($b['id']) ?>/edit"
         class="inline-flex items-center gap-2 px-3 py-2 rounded-xl border border-gray-200 dark:border-gray-700 text-sm text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-800">
        <i class="fa fa-pen" aria-hidden="true"></i>
        Edit
      </a>
      <a href="<?= $h($base) ?>/accounts"
         class="inline-flex items-center gap-2 px-3 py-2 rounded-xl border border-gray-200 dark:border-gray-700 text-sm text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-800">
        <i class="fa fa-arrow-left" aria-hidden="true"></i>
        Back
      </a>
    </div>
  </div>

  <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
    <div class="rounded-2xl border border-gray-100 dark:border-gray-800 bg-white dark:bg-gray-900 p-4">
      <p class="text-xs font-semibold uppercase text-gray-500 dark:text-gray-400 mb-1">Code</p>
      <p class="text-sm font-mono text-gray-900 dark:text-gray-50"><?= $h($b['code']) ?></p>
    </div>
    <div class="rounded-2xl border border-gray-100 dark:border-gray-800 bg-white dark:bg-gray-900 p-4">
      <p class="text-xs font-semibold uppercase text-gray-500 dark:text-gray-400 mb-1">Type</p>
      <p class="text-sm text-gray-900 dark:text-gray-50"><?= $h(ucfirst($b['type'])) ?></p>
    </div>
    <div class="rounded-2xl border border-gray-100 dark:border-gray-800 bg-white dark:bg-gray-900 p-4">
      <p class="text-xs font-semibold uppercase text-gray-500 dark:text-gray-400 mb-1">Currency</p>
      <p class="text-sm text-gray-900 dark:text-gray-50"><?= $h($b['currency']) ?></p>
    </div>
  </div>

  <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
    <div class="rounded-2xl border border-gray-100 dark:border-gray-800 bg-white dark:bg-gray-900 p-4 space-y-2">
      <p class="text-xs font-semibold uppercase text-gray-500 dark:text-gray-400">Bank</p>
      <p class="text-sm text-gray-900 dark:text-gray-50"><?= $h($b['bank_name']) ?></p>
      <?php if (!empty($b['branch_name'])): ?>
        <p class="text-xs text-gray-500 dark:text-gray-400">
          Branch: <?= $h($b['branch_name']) ?>
        </p>
      <?php endif; ?>
      <p class="text-xs text-gray-500 dark:text-gray-400">
        Account No: <?= $h($b['account_no']) ?>
      </p>
    </div>

    <div class="rounded-2xl border border-gray-100 dark:border-gray-800 bg-white dark:bg-gray-900 p-4 space-y-3">
      <div>
        <p class="text-xs font-semibold uppercase text-gray-500 dark:text-gray-400">Opening Balance</p>
        <p class="text-xl font-semibold text-gray-900 dark:text-gray-50">
          ৳<?= $h(number_format($b['opening_balance'], 2)) ?>
        </p>
      </div>
      <div>
        <p class="text-xs font-semibold uppercase text-gray-500 dark:text-gray-400">Current Balance (snapshot)</p>
        <p class="text-xl font-semibold text-emerald-600 dark:text-emerald-300">
          ৳<?= $h(number_format($b['current_balance'], 2)) ?>
        </p>
      </div>
      <p class="text-[11px] text-gray-400 dark:text-gray-500">
        In full accounting mode this balance is reconciled with GL transactions.
      </p>
    </div>
  </div>
</div>