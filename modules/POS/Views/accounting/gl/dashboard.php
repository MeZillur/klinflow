<?php
$h      = fn($x)=>htmlspecialchars((string)$x, ENT_QUOTES, 'UTF-8');
$base   = $base ?? '/apps/pos';
$totals = $totals ?? ['accounts'=>0,'journals'=>0,'current_month'=>['dr'=>0,'cr'=>0]];
?>
<div class="px-4 md:px-6 py-6 max-w-5xl mx-auto space-y-6">
  <div class="flex items-center justify-between gap-3">
    <div>
      <h1 class="text-2xl md:text-3xl font-semibold text-gray-900 dark:text-gray-50">
        General Ledger
      </h1>
      <p class="text-sm text-gray-500 dark:text-gray-400">
        Core accounting overview from the shared GL.
      </p>
    </div>
    <a href="<?= $h($base) ?>/accounting/reports/trial-balance"
       class="inline-flex items-center gap-2 px-3 py-2 rounded-lg border border-gray-200 dark:border-gray-700 text-sm text-gray-800 dark:text-gray-100 hover:bg-gray-50 dark:hover:bg-gray-800">
      <i class="fa fa-balance-scale text-xs"></i>
      Trial balance
    </a>
  </div>

  <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
    <div class="p-4 rounded-2xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900">
      <div class="text-xs text-gray-500">Accounts</div>
      <div class="mt-1 text-2xl font-semibold text-gray-900 dark:text-gray-50">
        <?= (int)$totals['accounts'] ?>
      </div>
    </div>
    <div class="p-4 rounded-2xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900">
      <div class="text-xs text-gray-500">Journals</div>
      <div class="mt-1 text-2xl font-semibold text-gray-900 dark:text-gray-50">
        <?= (int)$totals['journals'] ?>
      </div>
    </div>
    <div class="p-4 rounded-2xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900">
      <div class="text-xs text-gray-500">This month movement</div>
      <div class="mt-1 text-sm text-gray-700 dark:text-gray-200">
        Dr: <span class="font-semibold"><?= number_format((float)$totals['current_month']['dr'], 2) ?></span><br>
        Cr: <span class="font-semibold"><?= number_format((float)$totals['current_month']['cr'], 2) ?></span>
      </div>
    </div>
  </div>

  <div class="text-xs text-gray-400">
    All GL data is read-only from here. Editing / deletion should be done via controlled accounting flows only.
  </div>
</div>