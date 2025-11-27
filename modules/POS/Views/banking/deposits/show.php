<?php
$h       = fn($x)=>htmlspecialchars((string)$x, ENT_QUOTES, 'UTF-8');
$base    = $base ?? '/apps/pos';
$d       = $deposit ?? [];
$brand   = '#228B22';
?>
<div class="px-6 py-6 max-w-3xl space-y-5">
  <div class="flex items-center justify-between gap-3">
    <div>
      <h1 class="text-2xl md:text-3xl font-semibold text-gray-900 dark:text-gray-50 flex items-center gap-2">
        <i class="fa fa-arrow-up-from-bracket text-emerald-500"></i>
        <span>Deposit #<?= (int)($d['id'] ?? 0) ?></span>
      </h1>
      <p class="text-sm text-gray-500 dark:text-gray-400">
        Posted on <?= $h(date('d M Y', strtotime((string)($d['deposited_at'] ?? date('Y-m-d'))))) ?>.
      </p>
    </div>
    <a href="<?= $h($base) ?>/banking/deposits"
       class="inline-flex items-center gap-2 h-9 px-3 rounded-lg border border-gray-200 dark:border-gray-700 text-sm text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-800">
      <i class="fa fa-arrow-left text-xs"></i>
      Back
    </a>
  </div>

  <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-2xl px-5 py-5 shadow-sm space-y-4">
    <div class="flex items-center justify-between gap-3">
      <div>
        <div class="text-xs font-semibold uppercase text-gray-500 dark:text-gray-400 mb-1">
          Amount
        </div>
        <div class="text-2xl font-bold text-gray-900 dark:text-gray-50">
          ৳<?= number_format((float)($d['amount'] ?? 0), 2) ?>
        </div>
      </div>
      <div class="text-right">
        <div class="text-xs font-semibold uppercase text-gray-500 dark:text-gray-400 mb-1">
          Status
        </div>
        <?php
          $status = (string)($d['status'] ?? 'posted');
          $pillClass = 'bg-emerald-50 text-emerald-700 border-emerald-200';
          if ($status === 'pending')   $pillClass = 'bg-amber-50 text-amber-700 border-amber-200';
          if ($status === 'cancelled') $pillClass = 'bg-red-50 text-red-700 border-red-200';
        ?>
        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full border text-[11px] <?= $h($pillClass) ?>">
          <?= ucfirst($h($status)) ?>
        </span>
      </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
      <div class="space-y-2">
        <div>
          <div class="text-xs text-gray-500 dark:text-gray-400">Bank Account</div>
          <div class="font-semibold text-gray-900 dark:text-gray-50">
            <?= $h(($d['bank_name'] ?? '').' — '.($d['bank_account_name'] ?? '')) ?>
          </div>
        </div>
        <div>
          <div class="text-xs text-gray-500 dark:text-gray-400">From Register</div>
          <div class="text-gray-800 dark:text-gray-200">
            <?php if (!empty($d['register_name'])): ?>
              <?= $h($d['register_name']) ?>
            <?php else: ?>
              <span class="text-gray-400">Not linked (manual)</span>
            <?php endif; ?>
          </div>
        </div>
        <div>
          <div class="text-xs text-gray-500 dark:text-gray-400">Method</div>
          <div class="text-gray-800 dark:text-gray-200">
            <?= $h($d['method'] ?? 'Cash') ?>
          </div>
        </div>
      </div>
      <div class="space-y-2">
        <div>
          <div class="text-xs text-gray-500 dark:text-gray-400">Reference</div>
          <div class="text-gray-800 dark:text-gray-200">
            <?= $h($d['reference'] ?: '—') ?>
          </div>
        </div>
        <div>
          <div class="text-xs text-gray-500 dark:text-gray-400">Created</div>
          <div class="text-gray-800 dark:text-gray-200">
            <?= $h(!empty($d['created_at']) ? date('d M Y H:i', strtotime((string)$d['created_at'])) : '—') ?>
          </div>
        </div>
        <div>
          <div class="text-xs text-gray-500 dark:text-gray-400">Updated</div>
          <div class="text-gray-800 dark:text-gray-200">
            <?= $h(!empty($d['updated_at']) ? date('d M Y H:i', strtotime((string)$d['updated_at'])) : '—') ?>
          </div>
        </div>
      </div>
    </div>

    <?php if (!empty($d['notes'])): ?>
      <div class="pt-3 border-t border-gray-100 dark:border-gray-800 text-sm">
        <div class="text-xs text-gray-500 dark:text-gray-400 mb-1">Notes</div>
        <p class="text-gray-800 dark:text-gray-200 whitespace-pre-line">
          <?= $h($d['notes']) ?>
        </p>
      </div>
    <?php endif; ?>
  </div>
</div>