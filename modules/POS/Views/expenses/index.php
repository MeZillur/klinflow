<?php
declare(strict_types=1);

/**
 * @var array  $rows   expense rows from controller
 * @var string $search search query
 * @var array  $ctx    context (may include branch_id etc.)
 */

$h     = fn($s)=>htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
$base  = $base  ?? '/apps/pos';
$rows  = $rows  ?? [];
$search = $search ?? ($_GET['q'] ?? '');
$status = $_GET['status'] ?? '';
$from   = $_GET['from']   ?? '';
$to     = $_GET['to']     ?? '';
$branchFilter = $_GET['branch'] ?? '';

// simple page totals
$totalRecords = count($rows);
$totalAmount  = 0.0;
$approvedAmt  = 0.0;
$paidAmt      = 0.0;

foreach ($rows as $r) {
    $amt    = (float)($r['amount'] ?? 0);
    $st     = strtolower((string)($r['status'] ?? ''));
    $totalAmount += $amt;
    if ($st === 'approved') $approvedAmt += $amt;
    if ($st === 'paid')     $paidAmt     += $amt;
}

$brand = '#228B22';
?>
<div class="px-4 md:px-6 py-6">
  <div class="max-w-6xl mx-auto space-y-6">

    <!-- Header -->
    <div class="flex flex-wrap items-start justify-between gap-4">
      <div class="space-y-1">
        <h1 class="text-2xl md:text-3xl font-semibold text-gray-900 dark:text-gray-50 flex items-center gap-2">
          <i class="fa fa-receipt text-emerald-500"></i>
          <span>Expenses</span>
        </h1>
        <p class="text-sm text-gray-500 dark:text-gray-400">
          Day-to-day expenses across branches, paid via cash or bank.
        </p>
      </div>
      <a href="<?= $h($base) ?>/expenses/create"
         class="inline-flex items-center gap-2 h-9 px-4 rounded-lg text-sm font-semibold text-white shadow-sm"
         style="background:<?= $brand ?>;">
        <i class="fa fa-plus text-xs"></i>
        <span>New Expense</span>
      </a>
    </div>

    <!-- Top nav chips (horizontal) -->
    <div class="flex flex-wrap gap-2">
      <a href="<?= $h($base) ?>/banking/accounts"
         class="inline-flex items-center gap-1 h-8 px-3 rounded-full text-[11px] border border-gray-200 dark:border-gray-700 text-gray-600 dark:text-gray-300 bg-white dark:bg-gray-900">
        <i class="fa fa-landmark text-[10px]"></i> HQ Accounts
      </a>
      <a href="<?= $h($base) ?>/banking/deposits"
         class="inline-flex items-center gap-1 h-8 px-3 rounded-full text-[11px] border border-gray-200 dark:border-gray-700 text-gray-600 dark:text-gray-300 bg-white dark:bg-gray-900">
        <i class="fa fa-arrow-up-from-bracket text-[10px]"></i> Deposits
      </a>
      <a href="<?= $h($base) ?>/payments"
         class="inline-flex items-center gap-1 h-8 px-3 rounded-full text-[11px] border border-gray-200 dark:border-gray-700 text-gray-600 dark:text-gray-300 bg-white dark:bg-gray-900">
        <i class="fa fa-arrow-right-arrow-left text-[10px]"></i> Payments
      </a>
      <a href="<?= $h($base) ?>/expenses"
         class="inline-flex items-center gap-1 h-8 px-3 rounded-full text-[11px] border border-emerald-500/80 text-emerald-700 dark:text-emerald-300 bg-emerald-50 dark:bg-emerald-900/40">
        <i class="fa fa-receipt text-[10px]"></i> Expenses
      </a>
    </div>

    <!-- Summary cards (horizontal grid) -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
      <div class="p-3 rounded-2xl border border-emerald-100 bg-emerald-50/70 text-emerald-800">
        <div class="text-[11px] uppercase tracking-wide font-semibold">Total (this page)</div>
        <div class="mt-1 text-xl font-semibold">
          ৳<?= number_format($totalAmount, 2) ?>
        </div>
      </div>
      <div class="p-3 rounded-2xl border border-amber-100 bg-amber-50/70 text-amber-800">
        <div class="text-[11px] uppercase tracking-wide font-semibold">Approved</div>
        <div class="mt-1 text-xl font-semibold">
          ৳<?= number_format($approvedAmt, 2) ?>
        </div>
      </div>
      <div class="p-3 rounded-2xl border border-blue-100 bg-blue-50/70 text-blue-800">
        <div class="text-[11px] uppercase tracking-wide font-semibold">Paid</div>
        <div class="mt-1 text-xl font-semibold">
          ৳<?= number_format($paidAmt, 2) ?>
        </div>
      </div>
      <div class="p-3 rounded-2xl border border-gray-200 bg-gray-50 text-gray-800">
        <div class="text-[11px] uppercase tracking-wide font-semibold">Records (this page)</div>
        <div class="mt-1 text-xl font-semibold">
          <?= $totalRecords ?>
        </div>
      </div>
    </div>

    <!-- Filters row (horizontal) -->
    <form method="get"
          class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-2xl px-4 py-3 flex flex-wrap items-center gap-3">
      <div class="relative flex-1 min-w-[180px]">
        <span class="absolute inset-y-0 left-3 flex items-center text-gray-400 text-xs">
          <i class="fa fa-search"></i>
        </span>
        <input name="q"
               value="<?= $h($search) ?>"
               placeholder="Search payee / description / category"
               class="w-full pl-8 pr-3 py-2 rounded-xl border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800 text-sm text-gray-800 dark:text-gray-100 focus:outline-none focus:ring-2 focus:ring-emerald-500/60 focus:border-emerald-500">
      </div>

      <select name="status"
              class="px-3 py-2 rounded-xl border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800 text-sm text-gray-800 dark:text-gray-100 focus:outline-none focus:ring-2 focus:ring-emerald-500/60 focus:border-emerald-500">
        <option value="">All status</option>
        <?php foreach (['draft','approved','paid','void'] as $st): ?>
          <option value="<?= $h($st) ?>" <?= $status===$st ? 'selected' : '' ?>>
            <?= ucfirst($st) ?>
          </option>
        <?php endforeach; ?>
      </select>

      <input type="date" name="from" value="<?= $h($from) ?>"
             class="px-3 py-2 rounded-xl border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800 text-sm text-gray-800 dark:text-gray-100 focus:outline-none focus:ring-2 focus:ring-emerald-500/60 focus:border-emerald-500">
      <input type="date" name="to" value="<?= $h($to) ?>"
             class="px-3 py-2 rounded-xl border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800 text-sm text-gray-800 dark:text-gray-100 focus:outline-none focus:ring-2 focus:ring-emerald-500/60 focus:border-emerald-500">

      <input name="branch" value="<?= $h($branchFilter) ?>"
             placeholder="Branch ID"
             class="px-3 py-2 rounded-xl border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800 text-sm text-gray-800 dark:text-gray-100 focus:outline-none focus:ring-2 focus:ring-emerald-500/60 focus:border-emerald-500">

      <button class="px-3 py-2 rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 text-sm text-gray-800 dark:text-gray-100 hover:bg-gray-50 dark:hover:bg-gray-800">
        Apply
      </button>
    </form>

    <!-- Main table -->
    <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-2xl overflow-hidden">
      <div class="px-4 py-2 border-b border-gray-100 dark:border-gray-800 flex items-center justify-between text-xs text-gray-500 dark:text-gray-400">
        <span>Showing <?= $totalRecords ?> record<?= $totalRecords === 1 ? '' : 's' ?></span>
      </div>

      <div class="overflow-x-auto">
        <table class="min-w-full text-sm text-gray-700 dark:text-gray-200">
          <thead class="bg-gray-50 dark:bg-gray-800/70 border-b border-gray-100 dark:border-gray-800 text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">
            <tr>
              <th class="px-4 py-2 text-left">Date</th>
              <th class="px-4 py-2 text-left">Branch</th>
              <th class="px-4 py-2 text-left">Category</th>
              <th class="px-4 py-2 text-left">Payee</th>
              <th class="px-4 py-2 text-left">Method</th>
              <th class="px-4 py-2 text-right">Amount</th>
              <th class="px-4 py-2 text-left">Status</th>
              <th class="px-4 py-2 text-left">Notes</th>
              <th class="px-4 py-2 text-right"></th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-50 dark:divide-gray-800">
          <?php if (empty($rows)): ?>
            <tr>
              <td colspan="9" class="px-4 py-10 text-center text-gray-500 dark:text-gray-400">
                No expenses recorded yet.
              </td>
            </tr>
          <?php else: ?>
            <?php foreach ($rows as $e): ?>
              <?php
                $dateRaw = (string)($e['expense_date'] ?? '');
                $date    = $dateRaw ? date('d M Y', strtotime($dateRaw)) : '';
                $branchId = (int)($e['branch_id'] ?? 0);
                $branchLabel = $branchId > 0
                  ? "Branch #{$branchId}"
                  : 'HQ / General';

                $statusVal = strtolower((string)($e['status'] ?? ''));
                $badgeClasses = match ($statusVal) {
                    'paid'     => 'bg-emerald-50 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-200',
                    'approved' => 'bg-amber-50 text-amber-700 dark:bg-amber-900/40 dark:text-amber-200',
                    'draft'    => 'bg-gray-100 text-gray-700 dark:bg-gray-800/70 dark:text-gray-200',
                    'void'     => 'bg-rose-50 text-rose-700 dark:bg-rose-900/40 dark:text-rose-200',
                    default    => 'bg-gray-100 text-gray-700 dark:bg-gray-800/70 dark:text-gray-200',
                };
                $amount = (float)($e['amount'] ?? 0);
              ?>
              <tr class="hover:bg-gray-50/70 dark:hover:bg-gray-800/70">
                <td class="px-4 py-2 whitespace-nowrap"><?= $h($date) ?></td>
                <td class="px-4 py-2 whitespace-nowrap text-xs"><?= $h($branchLabel) ?></td>
                <td class="px-4 py-2 whitespace-nowrap text-xs"><?= $h($e['category'] ?? '') ?></td>
                <td class="px-4 py-2 whitespace-nowrap"><?= $h($e['payee'] ?? '') ?></td>
                <td class="px-4 py-2 whitespace-nowrap text-xs"><?= $h($e['method'] ?? 'Cash') ?></td>
                <td class="px-4 py-2 whitespace-nowrap text-right font-semibold">
                  ৳<?= number_format($amount, 2) ?>
                </td>
                <td class="px-4 py-2 whitespace-nowrap">
                  <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[11px] <?= $badgeClasses ?>">
                    <span class="h-1.5 w-1.5 rounded-full bg-current"></span>
                    <?= $h(ucfirst($statusVal ?: '')) ?>
                  </span>
                </td>
                <td class="px-4 py-2 whitespace-nowrap text-xs max-w-[180px] truncate">
                  <?= $h($e['description'] ?? $e['notes'] ?? '') ?>
                </td>
                <td class="px-4 py-2 whitespace-nowrap text-right text-xs">
                  <a href="<?= $h($base) ?>/expenses/<?= (int)($e['id'] ?? 0) ?>"
                     class="text-emerald-700 dark:text-emerald-300 hover:underline">
                    View
                  </a>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Guidance below the table (as requested) -->
    <div class="mt-4 bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-2xl px-4 py-4">
      <h2 class="text-sm font-semibold text-gray-800 dark:text-gray-100 mb-2">
        How to use the Expenses screen
      </h2>
      <ul class="text-[13px] text-gray-600 dark:text-gray-300 space-y-1.5 leading-relaxed">
        <li>• Use <strong>branch</strong> column to make sure each expense is tagged to the right outlet or HQ.</li>
        <li>• Pick a clear <strong>category</strong> (utilities, rent, transport, etc.) so reporting stays clean.</li>
        <li>• Set <strong>status = Approved</strong> after manager review and <strong>Paid</strong> once money actually goes out.</li>
        <li>• If you pay from bank, select the correct <strong>bank account</strong> when creating the expense.</li>
        <li>• Notes / description help future you understand why this bill was paid – don’t be shy to write 1–2 lines.</li>
      </ul>
    </div>

  </div>
</div>