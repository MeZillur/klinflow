<?php
declare(strict_types=1);

/**
 * Expense show view
 *
 * Expected:
 *  - $expense array  main expense row with mixed fields from POS + Accounting
 *  - $lines   array  optional line items (can be empty / missing)
 *  - $base    string module base (/apps/pos or /t/{slug}/apps/pos)
 */

$h      = fn($x)=>htmlspecialchars((string)$x, ENT_QUOTES, 'UTF-8');
$base   = $base ?? '/apps/pos';
$expense = $expense ?? [];
$lines   = $lines   ?? [];

// simple money helper (cents → formatted BDT)
$money = $money ?? function ($cents) {
    $c = (int)$cents;
    return '৳'.number_format($c / 100, 2);
};

$brand = '#228B22';

$id           = (int)($expense['expense_id'] ?? $expense['id'] ?? 0);
$no           = $expense['expense_no'] ?? $expense['voucher_no'] ?? ('EXP-'.$id);
$status       = strtolower((string)($expense['status'] ?? ''));
$date         = $expense['expense_date'] ?? $expense['voucher_date'] ?? null;
$datePretty   = $date ? date('d M Y', strtotime((string)$date)) : '';
$branchName   = $expense['branch_name'] ?? $expense['branch'] ?? 'HQ / General';
$payee        = $expense['payee'] ?? $expense['payee_name'] ?? '';
$reference    = $expense['reference'] ?? $expense['bill_no'] ?? '';
$method       = ucfirst((string)($expense['method'] ?? 'Cash'));
$totalCents   = (int)($expense['total_cents'] ?? $expense['total_amount_cents'] ?? $expense['total_cents_bdt'] ?? 0);
$paidCents    = (int)($expense['paid_amount_cents'] ?? 0);
$currency     = $expense['currency'] ?? 'BDT';

$glAccCode    = $expense['gl_account_code'] ?? '';
$glAccName    = $expense['gl_account_name'] ?? '';
$glSubCode    = $expense['gl_subaccount_code'] ?? '';
$glSubName    = $expense['gl_subaccount_name'] ?? '';

$bankName     = $expense['bank_name'] ?? '';
$bankAccount  = $expense['bank_account_name'] ?? '';
$bankNo       = $expense['bank_account_no'] ?? $expense['account_no'] ?? '';

$createdAt    = $expense['created_at'] ?? null;
$updatedAt    = $expense['updated_at'] ?? null;
$createdBy    = $expense['created_by_name'] ?? $expense['created_by'] ?? '';
$approvedBy   = $expense['approved_by_name'] ?? $expense['approved_by'] ?? '';
$paidBy       = $expense['paid_by_name'] ?? $expense['paid_by'] ?? '';
$glVoucherNo  = $expense['gl_voucher_no'] ?? '';
$glPostedAt   = $expense['gl_posted_at'] ?? null;

// badge color
$badgeClass = match ($status) {
    'paid'     => 'bg-emerald-50 text-emerald-700 border-emerald-200',
    'approved' => 'bg-amber-50 text-amber-700 border-amber-200',
    'draft'    => 'bg-gray-100 text-gray-700 border-gray-200',
    'void'     => 'bg-rose-50 text-rose-700 border-rose-200',
    default    => 'bg-gray-50 text-gray-700 border-gray-200',
};
?>
<div class="px-4 md:px-6 py-6">
  <div class="max-w-5xl mx-auto space-y-6">

    <!-- Header -->
    <div class="flex flex-wrap items-start justify-between gap-4">
      <div class="space-y-1">
        <div class="flex items-center gap-2">
          <i class="fa fa-receipt text-emerald-500 text-xl"></i>
          <h1 class="text-2xl md:text-3xl font-semibold text-gray-900 dark:text-gray-50">
            Expense #<?= $h($no) ?>
          </h1>
        </div>
        <p class="text-sm text-gray-500 dark:text-gray-400">
          Branch expense routed into GL &amp; accounting.
        </p>
        <?php if ($datePretty): ?>
          <p class="text-xs text-gray-400 dark:text-gray-500">
            Expense date:
            <span class="font-medium text-gray-700 dark:text-gray-300"><?= $h($datePretty) ?></span>
          </p>
        <?php endif; ?>
      </div>

      <div class="flex flex-wrap items-center justify-end gap-2">
        <span class="inline-flex items-center px-2.5 py-1 rounded-full border text-xs font-medium <?= $badgeClass ?>">
          <span class="h-1.5 w-1.5 rounded-full bg-current mr-1"></span>
          <?= $h(ucfirst($status ?: '')) ?>
        </span>
        <?php if ($id): ?>
          <a href="<?= $h($base) ?>/expenses/<?= $id ?>/edit"
             class="inline-flex items-center gap-1 h-9 px-3 rounded-lg border border-gray-200 dark:border-gray-700 text-sm text-gray-700 dark:text-gray-200 bg-white dark:bg-gray-900 hover:bg-gray-50 dark:hover:bg-gray-800">
            <i class="fa fa-pen text-xs"></i>
            Edit
          </a>
        <?php endif; ?>
        <a href="<?= $h($base) ?>/expenses"
           class="inline-flex items-center gap-1 h-9 px-3 rounded-lg border border-gray-200 dark:border-gray-700 text-sm text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-800">
          <i class="fa fa-arrow-left text-xs"></i>
          Back
        </a>
      </div>
    </div>

    <!-- Summary cards -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
      <div class="p-4 rounded-2xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900">
        <div class="text-xs text-gray-500">Total amount</div>
        <div class="mt-1 text-xl font-semibold text-gray-900 dark:text-gray-50">
          <?= $h($money($totalCents)) ?> <span class="text-xs font-normal text-gray-400"><?= $h($currency) ?></span>
        </div>
      </div>
      <div class="p-4 rounded-2xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900">
        <div class="text-xs text-gray-500">Paid</div>
        <div class="mt-1 text-xl font-semibold text-emerald-700 dark:text-emerald-300">
          <?= $h($money($paidCents)) ?>
        </div>
      </div>
      <div class="p-4 rounded-2xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900">
        <div class="text-xs text-gray-500">Branch</div>
        <div class="mt-1 text-sm font-medium text-gray-900 dark:text-gray-50">
          <?= $h($branchName) ?>
        </div>
      </div>
      <div class="p-4 rounded-2xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900">
        <div class="text-xs text-gray-500">Method</div>
        <div class="mt-1 text-sm font-medium text-gray-900 dark:text-gray-50">
          <?= $h($method) ?>
        </div>
      </div>
    </div>

    <!-- Main details (2 columns) -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
      <!-- Left: expense details -->
      <div class="space-y-3 bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-2xl p-4">
        <h2 class="text-sm font-semibold text-gray-800 dark:text-gray-100">
          Expense details
        </h2>
        <dl class="text-sm text-gray-700 dark:text-gray-200 space-y-2">
          <div class="flex justify-between gap-4">
            <dt class="text-gray-500 dark:text-gray-400 w-32">Expense no</dt>
            <dd class="flex-1 text-right"><?= $h($no) ?></dd>
          </div>
          <?php if ($payee): ?>
          <div class="flex justify-between gap-4">
            <dt class="text-gray-500 dark:text-gray-400 w-32">Payee / Supplier</dt>
            <dd class="flex-1 text-right"><?= $h($payee) ?></dd>
          </div>
          <?php endif; ?>
          <?php if ($reference): ?>
          <div class="flex justify-between gap-4">
            <dt class="text-gray-500 dark:text-gray-400 w-32">Reference</dt>
            <dd class="flex-1 text-right"><?= $h($reference) ?></dd>
          </div>
          <?php endif; ?>
          <?php if (!empty($expense['description'])): ?>
          <div class="flex flex-col gap-1 mt-2">
            <dt class="text-gray-500 dark:text-gray-400 text-xs uppercase tracking-wide">Description</dt>
            <dd class="text-sm leading-relaxed">
              <?= nl2br($h((string)$expense['description'])) ?>
            </dd>
          </div>
          <?php endif; ?>
        </dl>
      </div>

      <!-- Right: GL + bank mapping -->
      <div class="space-y-3 bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-2xl p-4">
        <h2 class="text-sm font-semibold text-gray-800 dark:text-gray-100">
          GL &amp; accounting link
        </h2>
        <dl class="text-sm text-gray-700 dark:text-gray-200 space-y-2">
          <div class="flex justify-between gap-4">
            <dt class="text-gray-500 dark:text-gray-400 w-36">Main account</dt>
            <dd class="flex-1 text-right">
              <?php if ($glAccCode || $glAccName): ?>
                <?= $h(trim($glAccCode.' '.$glAccName)) ?>
              <?php else: ?>
                <span class="text-gray-400">Not mapped</span>
              <?php endif; ?>
            </dd>
          </div>
          <div class="flex justify-between gap-4">
            <dt class="text-gray-500 dark:text-gray-400 w-36">Sub-account</dt>
            <dd class="flex-1 text-right">
              <?php if ($glSubCode || $glSubName): ?>
                <?= $h(trim($glSubCode.' '.$glSubName)) ?>
              <?php else: ?>
                <span class="text-gray-400">None</span>
              <?php endif; ?>
            </dd>
          </div>

          <div class="border-t border-dashed border-gray-200 dark:border-gray-800 my-2"></div>

          <div class="flex justify-between gap-4">
            <dt class="text-gray-500 dark:text-gray-400 w-36">Bank account</dt>
            <dd class="flex-1 text-right">
              <?php if ($bankName || $bankAccount || $bankNo): ?>
                <?= $h(trim($bankName.' '.$bankAccount)) ?>
                <?php if ($bankNo): ?>
                  <span class="block text-xs text-gray-400"><?= $h($bankNo) ?></span>
                <?php endif; ?>
              <?php else: ?>
                <span class="text-gray-400">Paid from cash / unknown</span>
              <?php endif; ?>
            </dd>
          </div>

          <div class="flex justify-between gap-4">
            <dt class="text-gray-500 dark:text-gray-400 w-36">GL voucher</dt>
            <dd class="flex-1 text-right">
              <?php if ($glVoucherNo): ?>
                <?= $h($glVoucherNo) ?>
                <?php if ($glPostedAt): ?>
                  <span class="block text-xs text-gray-400">
                    Posted on <?= $h(date('d M Y H:i', strtotime((string)$glPostedAt))) ?>
                  </span>
                <?php endif; ?>
              <?php else: ?>
                <span class="text-gray-400">Not yet posted</span>
              <?php endif; ?>
            </dd>
          </div>
        </dl>
      </div>
    </div>

    <!-- Line items (optional) -->
    <?php if (!empty($lines)): ?>
      <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-2xl overflow-hidden">
        <div class="px-4 py-3 border-b border-gray-100 dark:border-gray-800 flex items-center justify-between">
          <h2 class="text-sm font-semibold text-gray-800 dark:text-gray-100">
            Line items
          </h2>
        </div>
        <div class="overflow-x-auto">
          <table class="min-w-full text-sm text-gray-700 dark:text-gray-200">
            <thead class="bg-gray-50 dark:bg-gray-800/70 text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">
              <tr>
                <th class="px-4 py-2 text-left">Description</th>
                <th class="px-4 py-2 text-right">Qty</th>
                <th class="px-4 py-2 text-right">Rate</th>
                <th class="px-4 py-2 text-right">Tax</th>
                <th class="px-4 py-2 text-right">Amount</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-50 dark:divide-gray-800">
              <?php foreach ($lines as $ln): ?>
                <?php
                  $qty     = $ln['qty'] ?? $ln['quantity'] ?? null;
                  $rateC   = (int)($ln['rate_cents'] ?? 0);
                  $taxC    = (int)($ln['tax_cents'] ?? 0);
                  $amtC    = (int)($ln['line_total_cents'] ?? $ln['total_cents'] ?? 0);
                ?>
                <tr class="hover:bg-gray-50/70 dark:hover:bg-gray-800/70">
                  <td class="px-4 py-2 whitespace-nowrap">
                    <?= $h($ln['description'] ?? $ln['name'] ?? '') ?>
                  </td>
                  <td class="px-4 py-2 text-right">
                    <?= $qty !== null ? $h((string)$qty) : '' ?>
                  </td>
                  <td class="px-4 py-2 text-right">
                    <?= $rateC ? $h($money($rateC)) : '' ?>
                  </td>
                  <td class="px-4 py-2 text-right">
                    <?= $taxC ? $h($money($taxC)) : '' ?>
                  </td>
                  <td class="px-4 py-2 text-right font-semibold">
                    <?= $h($money($amtC)) ?>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    <?php endif; ?>

    <!-- Audit / history + guidance (bottom, horizontal feeling) -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
      <!-- Audit -->
      <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-2xl p-4">
        <h2 class="text-sm font-semibold text-gray-800 dark:text-gray-100 mb-2">
          Audit trail
        </h2>
        <dl class="text-xs text-gray-600 dark:text-gray-300 space-y-1.5">
          <?php if ($createdAt): ?>
          <div class="flex justify-between gap-4">
            <dt>Created at</dt>
            <dd class="text-right">
              <?= $h(date('d M Y H:i', strtotime((string)$createdAt))) ?>
              <?php if ($createdBy): ?>
                <span class="block text-[11px] text-gray-400">by <?= $h($createdBy) ?></span>
              <?php endif; ?>
            </dd>
          </div>
          <?php endif; ?>

          <?php if ($approvedBy): ?>
          <div class="flex justify-between gap-4">
            <dt>Approved by</dt>
            <dd class="text-right"><?= $h($approvedBy) ?></dd>
          </div>
          <?php endif; ?>

          <?php if ($paidBy): ?>
          <div class="flex justify-between gap-4">
            <dt>Paid by</dt>
            <dd class="text-right"><?= $h($paidBy) ?></dd>
          </div>
          <?php endif; ?>

          <?php if ($updatedAt && $updatedAt !== $createdAt): ?>
          <div class="flex justify-between gap-4">
            <dt>Last updated</dt>
            <dd class="text-right"><?= $h(date('d M Y H:i', strtotime((string)$updatedAt))) ?></dd>
          </div>
          <?php endif; ?>
        </dl>
      </div>

      <!-- Guidance -->
      <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-2xl p-4">
        <h2 class="text-sm font-semibold text-gray-800 dark:text-gray-100 mb-2">
          How this expense flows to accounting
        </h2>
        <ul class="text-[13px] text-gray-600 dark:text-gray-300 space-y-1.5 leading-relaxed">
          <li>• Main / sub-account determine which GL expense line will be debited.</li>
          <li>• Method + bank account decide whether you credit <strong>Cash</strong> or the selected <strong>Bank</strong>.</li>
          <li>• When a GL voucher no is present, this expense is already posted into the core accounting module.</li>
          <li>• Branch information allows branch-wise P&amp;L and cost centre reporting.</li>
        </ul>
      </div>
    </div>

  </div>
</div>