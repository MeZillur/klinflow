<?php
/**
 * Payments index
 *
 * Expected:
 *  - $base   string  module base (/apps/pos or /t/{slug}/apps/pos)
 *  - $rows   array   list of payments (may be empty)
 *  - $search string  search query
 */

$h      = fn($x)=>htmlspecialchars((string)$x, ENT_QUOTES, 'UTF-8');
$base   = $base   ?? '/apps/pos';
$rows   = $rows   ?? [];
$search = $search ?? '';
$brand  = '#228B22';
?>
<div class="px-4 md:px-6 py-6">
  <div class="max-w-6xl mx-auto space-y-6"><!-- horizontal wide layout -->

    <!-- Header + top-right nav + primary action -->
    <div class="flex flex-wrap items-start justify-between gap-4">
      <div class="space-y-1">
        <h1 class="text-2xl md:text-3xl font-semibold text-gray-900 dark:text-gray-50 flex items-center gap-2">
          <i class="fa fa-exchange-alt text-emerald-500" aria-hidden="true"></i>
          <span>Payments</span>
        </h1>
        <p class="text-sm text-gray-500 dark:text-gray-400">
          Collections & payouts flowing through your bank / cash accounts.
        </p>
        <p class="text-xs text-gray-400 dark:text-gray-500">
          আজকের তারিখ:
          <span class="font-medium text-gray-700 dark:text-gray-300"><?= $h(date('d M Y')) ?></span>
        </p>
      </div>

      <div class="flex flex-col items-end gap-3">
        <!-- menu tabs on top-right edge -->
        <div class="flex flex-wrap items-center justify-end gap-2">
          <a href="<?= $h($base) ?>/accounting"
             class="inline-flex items-center gap-1 h-8 px-3 rounded-full text-[11px]
                    border border-gray-200 dark:border-gray-700 text-gray-600 dark:text-gray-300
                    bg-white dark:bg-gray-900">
            <i class="fa fa-chart-line text-[10px]" aria-hidden="true"></i>
            <span>Accounting</span>
          </a>
          <a href="<?= $h($base) ?>/banking/accounts"
             class="inline-flex items-center gap-1 h-8 px-3 rounded-full text-[11px]
                    border border-gray-200 dark:border-gray-700 text-gray-600 dark:text-gray-300
                    bg-white dark:bg-gray-900">
            <i class="fa fa-landmark text-[10px]" aria-hidden="true"></i>
            <span>Banking</span>
          </a>
          <a href="<?= $h($base) ?>/banking/deposits"
             class="inline-flex items-center gap-1 h-8 px-3 rounded-full text-[11px]
                    border border-gray-200 dark:border-gray-700 text-gray-600 dark:text-gray-300
                    bg-white dark:bg-gray-900">
            <i class="fa fa-arrow-up-from-bracket text-[10px]" aria-hidden="true"></i>
            <span>Deposits</span>
          </a>
        </div>

        <!-- primary action -->
        <a href="<?= $h($base) ?>/payments/create"
           class="inline-flex items-center gap-2 h-9 px-4 rounded-lg text-sm font-semibold text-white shadow-sm"
           style="background:<?= $brand ?>;">
          <span class="fa fa-plus text-xs" aria-hidden="true"></span>
          <span>New Payment</span>
        </a>
      </div>
    </div>

    <!-- Filters + search (horizontal bar) -->
    <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-2xl overflow-hidden">
      <div class="px-4 py-3 border-b border-gray-100 dark:border-gray-800 flex flex-wrap items-center gap-3 justify-between">
        <!-- quick filter chips -->
        <div class="flex flex-wrap items-center gap-2">
          <a href="<?= $h($base) ?>/payments"
             class="inline-flex items-center gap-1 h-8 px-3 rounded-full text-[11px] font-semibold
                    <?= $search === '' && ($_GET['dir'] ?? '') === '' ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-200 border border-emerald-500/60' : 'bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 text-gray-600 dark:text-gray-300' ?>">
            <span class="fa fa-circle text-[8px]" aria-hidden="true"></span>
            <span>All</span>
          </a>
          <a href="<?= $h($base) ?>/payments?dir=in"
             class="inline-flex items-center gap-1 h-8 px-3 rounded-full text-[11px]
                    border border-gray-200 dark:border-gray-700 text-gray-600 dark:text-gray-300
                    bg-white dark:bg-gray-900">
            <span class="fa fa-arrow-down text-[10px]" aria-hidden="true"></span>
            <span>Money In</span>
          </a>
          <a href="<?= $h($base) ?>/payments?dir=out"
             class="inline-flex items-center gap-1 h-8 px-3 rounded-full text-[11px]
                    border border-gray-200 dark:border-gray-700 text-gray-600 dark:text-gray-300
                    bg-white dark:bg-gray-900">
            <span class="fa fa-arrow-up text-[10px]" aria-hidden="true"></span>
            <span>Money Out</span>
          </a>
          <a href="<?= $h($base) ?>/payments?status=pending"
             class="inline-flex items-center gap-1 h-8 px-3 rounded-full text-[11px]
                    border border-gray-200 dark:border-gray-700 text-gray-600 dark:text-gray-300
                    bg-white dark:bg-gray-900">
            <span class="fa fa-clock text-[10px]" aria-hidden="true"></span>
            <span>Pending</span>
          </a>
        </div>

        <!-- search -->
        <form method="get" class="flex items-center gap-2 min-w-[220px] flex-1 md:flex-none md:w-auto">
          <div class="relative flex-1">
            <span class="absolute inset-y-0 left-3 flex items-center text-gray-400 text-xs">
              <i class="fa fa-search" aria-hidden="true"></i>
            </span>
            <input
              type="text"
              name="q"
              value="<?= $h($search) ?>"
              placeholder="Search party, reference or notes"
              class="w-full pl-8 pr-3 py-2 rounded-lg border border-gray-200 dark:border-gray-700
                     bg-gray-50 dark:bg-gray-800 text-sm text-gray-800 dark:text-gray-100
                     focus:outline-none focus:ring-2 focus:ring-emerald-500/60 focus:border-emerald-500">
          </div>
          <button class="inline-flex items-center gap-1 px-3 py-2 rounded-lg border border-gray-200 dark:border-gray-700 text-xs font-medium text-gray-700 dark:text-gray-200 bg-white dark:bg-gray-900 hover:bg-gray-50 dark:hover:bg-gray-800">
            <i class="fa fa-filter text-[11px]" aria-hidden="true"></i>
            <span>Apply</span>
          </button>
        </form>
      </div>

      <?php if (empty($rows)): ?>
        <!-- Empty state -->
        <div class="py-10 flex flex-col items-center justify-center text-center gap-3">
          <div class="h-11 w-11 rounded-2xl bg-gray-100 dark:bg-gray-800 flex items-center justify-center text-gray-400 dark:text-gray-500">
            <i class="fa fa-exchange-alt" aria-hidden="true"></i>
          </div>
          <div class="space-y-1">
            <p class="text-sm font-medium text-gray-800 dark:text-gray-100">
              No payments recorded yet.
            </p>
            <p class="text-xs text-gray-500 dark:text-gray-400">
              প্রথম payment add করলে customer dues, supplier payment এবং ব্যাংক cash flow এক জায়গায় দেখতে পারবেন।
            </p>
          </div>
          <a href="<?= $h($base) ?>/payments/create"
             class="inline-flex items-center gap-2 h-9 px-4 rounded-lg text-sm font-semibold text-white shadow-sm"
             style="background:<?= $brand ?>;">
            <i class="fa fa-plus text-xs" aria-hidden="true"></i>
            <span>Add first payment</span>
          </a>
        </div>
      <?php else: ?>
        <!-- Table -->
        <div class="overflow-x-auto">
          <table class="min-w-full text-sm text-gray-700 dark:text-gray-200">
            <thead class="bg-gray-50 dark:bg-gray-800/70 border-b border-gray-100 dark:border-gray-800 text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">
            <tr>
              <th class="px-4 py-2 text-left">Date</th>
              <th class="px-4 py-2 text-left">Type</th>
              <th class="px-4 py-2 text-left">Party</th>
              <th class="px-4 py-2 text-left">Method</th>
              <th class="px-4 py-2 text-right">Amount</th>
              <th class="px-4 py-2 text-left">Status</th>
            </tr>
            </thead>
            <tbody class="divide-y divide-gray-50 dark:divide-gray-800">
            <?php foreach ($rows as $p): ?>
              <?php
              $date     = $p['payment_date'] ?? $p['date'] ?? null;
              $dateText = $date ? date('d M Y', strtotime((string)$date)) : '—';

              $direction = strtolower((string)($p['direction'] ?? $p['type'] ?? ''));
              $typeLabel = $p['type'] ?? ($direction === 'in' ? 'Receive' : ($direction === 'out' ? 'Payment' : 'Other'));

              $party   = $p['party_name'] ?? $p['party'] ?? '—';
              $method  = $p['method'] ?? $p['channel'] ?? '—';
              $amount  = (float)($p['amount'] ?? $p['total'] ?? 0);
              $status  = strtolower((string)($p['status'] ?? 'posted'));

              $dirBadge = $direction === 'in'
                  ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-200'
                  : ($direction === 'out'
                      ? 'bg-rose-50 text-rose-700 dark:bg-rose-900/40 dark:text-rose-200'
                      : 'bg-gray-100 text-gray-700 dark:bg-gray-800/60 dark:text-gray-200');

              $statusBadge = match ($status) {
                  'pending'      => 'bg-amber-50 text-amber-700 dark:bg-amber-900/40 dark:text-amber-200',
                  'failed','void'=> 'bg-rose-50 text-rose-700 dark:bg-rose-900/40 dark:text-rose-200',
                  default        => 'bg-emerald-50 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-200',
              };
              ?>
              <tr class="hover:bg-gray-50/70 dark:hover:bg-gray-800/70">
                <td class="px-4 py-2 whitespace-nowrap"><?= $h($dateText) ?></td>
                <td class="px-4 py-2 whitespace-nowrap text-xs">
                  <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full <?= $dirBadge ?>">
                    <?php if ($direction === 'in'): ?>
                      <i class="fa fa-arrow-down text-[9px]" aria-hidden="true"></i>
                    <?php elseif ($direction === 'out'): ?>
                      <i class="fa fa-arrow-up text-[9px]" aria-hidden="true"></i>
                    <?php else: ?>
                      <i class="fa fa-arrows-alt-h text-[9px]" aria-hidden="true"></i>
                    <?php endif; ?>
                    <span><?= $h($typeLabel) ?></span>
                  </span>
                </td>
                <td class="px-4 py-2 whitespace-nowrap"><?= $h($party) ?></td>
                <td class="px-4 py-2 whitespace-nowrap text-xs"><?= $h($method) ?></td>
                <td class="px-4 py-2 whitespace-nowrap text-right font-semibold">
                  ৳<?= number_format($amount, 2) ?>
                </td>
                <td class="px-4 py-2 whitespace-nowrap">
                  <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[11px] <?= $statusBadge ?>">
                    <span class="h-1.5 w-1.5 rounded-full bg-current"></span>
                    <?= $h(ucfirst($status)) ?>
                  </span>
                </td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>

    <!-- Bangla guidance at bottom (big-table rule) -->
    <div class="max-w-6xl mx-auto">
      <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-2xl px-4 py-4 space-y-2">
        <h2 class="text-sm font-semibold text-gray-800 dark:text-gray-100 flex items-center gap-2">
          <i class="fa fa-info-circle text-emerald-500" aria-hidden="true"></i>
          <span>Payments মডিউল – ব্যবহার নির্দেশনা</span>
        </h2>
        <ul class="text-[13px] text-gray-600 dark:text-gray-300 space-y-1.5 leading-relaxed">
          <li>১) <strong>Money In</strong> ব্যবহার করুন customer payment, advance receive, অন্যান্য income (bank বা cash) রেকর্ড করার জন্য।</li>
          <li>২) <strong>Money Out</strong> ব্যবহার করুন supplier payment, খরচ, refund, owner draw ইত্যাদি দেয়ার সময়।</li>
          <li>৩) প্রতিটি payment সবসময় কোনো না কোনো bank বা cash account-এর সাথে লিঙ্ক থাকবে, যাতে accounting-এর double entry balance ঠিক থাকে।</li>
          <li>৪) Customers / Suppliers মডিউল থেকে যেসব dues adjust হবে সেগুলোর ট্র্যাকও এখানে দেখা যাবে – এক স্ক্রিনেই পুরো cash flow দেখার জন্য।</li>
          <li>৫) Status <strong>Pending</strong> থাকলে বুঝবেন এখনো approve হয়নি; boss / accounts user approve করলে সেটি final হয়ে bank balance এর সাথে sync হবে।</li>
        </ul>
      </div>
    </div>

  </div>
</div>