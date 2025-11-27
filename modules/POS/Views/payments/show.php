<?php
/**
 * Payment show
 *
 * Expected:
 *  - $base     string module base
 *  - $payment  array  (or $pay) payment row
 */

$h   = fn($x)=>htmlspecialchars((string)$x, ENT_QUOTES, 'UTF-8');
$base = $base ?? '/apps/pos';

$p = $payment ?? ($pay ?? []);

$brand = '#228B22';

$dateRaw = $p['payment_date'] ?? $p['date'] ?? $p['txn_date'] ?? null;
$date    = $dateRaw ? date('d M Y', strtotime((string)$dateRaw)) : '';

$direction = strtolower((string)($p['direction'] ?? 'in'));
$type      = strtolower((string)($p['type'] ?? 'general'));
$method    = $p['method'] ?? $p['payment_method'] ?? '—';
$ref       = $p['reference'] ?? $p['ref_no'] ?? $p['memo'] ?? '—';

$fromName = $p['from_party_name'] ?? $p['customer_name'] ?? $p['supplier_name'] ?? $p['party_name'] ?? '';
$toName   = $p['to_party_name']   ?? '';
$party    = $fromName ?: $toName ?: ($p['other_party'] ?? '—');

$amount   = (float)($p['amount'] ?? $p['total'] ?? 0);
$status   = strtolower((string)($p['status'] ?? 'posted'));
$id       = (int)($p['id'] ?? 0);

$typeLabel = match ($type) {
  'customer'   => 'Customer',
  'supplier'   => 'Supplier',
  'expense'    => 'Expense',
  'income'     => 'Income',
  'adjustment' => 'Adjustment',
  default      => 'General',
};

$dirLabel = $direction === 'out' ? 'Money Out (Paid)' : 'Money In (Received)';
$dirIcon  = $direction === 'out' ? 'fa-arrow-up' : 'fa-arrow-down';
$dirColor = $direction === 'out'
  ? 'text-rose-500 dark:text-rose-300'
  : 'text-emerald-500 dark:text-emerald-300';

$badgeClasses = match ($status) {
  'pending'       => 'bg-amber-50 text-amber-700 dark:bg-amber-900/40 dark:text-amber-200',
  'void',
  'cancelled'     => 'bg-rose-50 text-rose-700 dark:bg-rose-900/40 dark:text-rose-200',
  default         => 'bg-emerald-50 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-200',
};
?>
<div class="px-4 md:px-6 py-6">
  <div class="max-w-6xl mx-auto space-y-6">

    <!-- Header -->
    <div class="flex flex-wrap items-start justify-between gap-4">
      <div>
        <h1 class="text-2xl md:text-3xl font-semibold text-gray-900 dark:text-gray-50 flex items-center gap-2">
          <i class="fa fa-money-check-dollar text-emerald-500"></i>
          <span>Payment #<?= $h($id ?: '-') ?></span>
        </h1>
        <p class="text-sm text-gray-500 dark:text-gray-400">
          Detailed view of a single payment and its posting info.
        </p>
      </div>
      <div class="flex flex-wrap items-center justify-end gap-2">
        <a href="<?= $h($base) ?>/payments"
           class="inline-flex items-center gap-2 h-9 px-3 rounded-lg border border-gray-200 dark:border-gray-700 text-sm text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-800">
          <i class="fa fa-arrow-left text-xs"></i>
          Back
        </a>
        <?php if ($id): ?>
          <a href="<?= $h($base) ?>/payments/<?= $id ?>/edit"
             class="inline-flex items-center gap-2 h-9 px-3 rounded-lg border border-emerald-500/80 text-sm font-semibold text-emerald-700 dark:text-emerald-300 bg-emerald-50 dark:bg-emerald-900/30 hover:bg-emerald-100">
            <i class="fa fa-pen text-xs"></i>
            Edit
          </a>
        <?php endif; ?>
      </div>
    </div>

    <!-- Layout: details left, guidance right -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-5 items-start">
      <!-- Main cards -->
      <div class="lg:col-span-2 space-y-4">

        <!-- Summary card -->
        <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-2xl px-4 py-4 flex flex-col md:flex-row md:items-center md:justify-between gap-4">
          <div class="space-y-1">
            <div class="flex items-center gap-2">
              <span class="inline-flex h-9 w-9 items-center justify-center rounded-full bg-gray-100 dark:bg-gray-800">
                <i class="fa <?= $h($dirIcon) ?> <?= $dirColor ?>"></i>
              </span>
              <div>
                <p class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide">
                  <?= $h($dirLabel) ?> • <?= $h($typeLabel) ?>
                </p>
                <p class="text-sm text-gray-500 dark:text-gray-400">
                  <?= $h($party) ?>
                </p>
              </div>
            </div>
            <p class="text-xs text-gray-400 dark:text-gray-500">
              Date: <span class="font-medium text-gray-700 dark:text-gray-300"><?= $h($date ?: '-') ?></span>
            </p>
          </div>
          <div class="text-right space-y-1">
            <p class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide">
              Amount
            </p>
            <p class="text-2xl font-bold text-gray-900 dark:text-gray-50">
              ৳<?= number_format($amount, 2) ?>
            </p>
            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[11px] <?= $badgeClasses ?>">
              <span class="h-1.5 w-1.5 rounded-full bg-current"></span>
              <?= $h(ucfirst($status)) ?>
            </span>
          </div>
        </div>

        <!-- Detail grid -->
        <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-2xl px-4 py-4">
          <h2 class="text-sm font-semibold text-gray-800 dark:text-gray-100 mb-3 flex items-center gap-2">
            <i class="fa fa-circle-info text-emerald-500"></i>
            <span>Details</span>
          </h2>
          <dl class="grid grid-cols-1 md:grid-cols-2 gap-x-6 gap-y-3 text-sm">
            <div>
              <dt class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wide">Method</dt>
              <dd class="text-gray-900 dark:text-gray-100"><?= $h($method) ?></dd>
            </div>
            <div>
              <dt class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wide">Reference</dt>
              <dd class="text-gray-900 dark:text-gray-100"><?= $h($ref) ?></dd>
            </div>
            <div>
              <dt class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wide">Bank Account</dt>
              <dd class="text-gray-900 dark:text-gray-100">
                <?= $h($p['bank_account_name'] ?? $p['bank_name'] ?? '—') ?>
              </dd>
            </div>
            <div>
              <dt class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wide">Cash Register</dt>
              <dd class="text-gray-900 dark:text-gray-100">
                <?= $h($p['cash_register_name'] ?? $p['register_name'] ?? '—') ?>
              </dd>
            </div>
            <div class="md:col-span-2">
              <dt class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-1">Notes / Memo</dt>
              <dd class="text-gray-900 dark:text-gray-100 whitespace-pre-line text-sm">
                <?= $h($p['memo'] ?? '') ?: '—' ?>
              </dd>
            </div>
          </dl>
        </div>

      </div>

      <!-- Guidance -->
      <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-2xl px-4 py-4 space-y-2">
        <h2 class="text-sm font-semibold text-gray-800 dark:text-gray-100 flex items-center gap-2">
          <i class="fa fa-lightbulb text-amber-400"></i>
          <span>এই Payment থেকে কী বোঝা যাচ্ছে?</span>
        </h2>
        <ul class="text-[13px] text-gray-600 dark:text-gray-300 space-y-1.5 leading-relaxed">
          <li>• Direction দেখে বোঝা যায় টাকা <strong>অফিসে ঢুকেছে</strong> নাকি <strong>বের হয়েছে</strong>।</li>
          <li>• Type দেখে বুঝবেন এটা customer due clear, supplier bill payment, নাকি সরাসরি expense / income।</li>
          <li>• Bank / Cash info থেকে clear হয় কোন account / drawer এ impact গেছে – GL report এ সেই অনুযায়ী দেখাবে।</li>
          <li>• Reference + Memo future audit / mismatch solve করতে অনেক help করবে (cheque no, trx id, invoice ref ইত্যাদি)।</li>
        </ul>
        <p class="text-[11px] text-gray-400 dark:text-gray-500">
          Tip: Payment ভুল হলে সরাসরি delete না করে <strong>void / adjustment</strong> ব্যবহার করলে audit trail পরিষ্কার থাকে।
        </p>
      </div>
    </div>
  </div>
</div>