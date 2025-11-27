<?php
/**
 * Deposits index
 *
 * Expected:
 *  - $base   string  module base (/apps/pos or /t/{slug}/apps/pos)
 *  - $rows   array   list of deposits (may be empty)
 *  - $search string  search query
 */

$h      = fn($x)=>htmlspecialchars((string)$x, ENT_QUOTES, 'UTF-8');
$base   = $base   ?? '/apps/pos';
$rows   = $rows   ?? [];
$search = $search ?? '';
$brand  = '#228B22';
?>
<div class="px-4 md:px-6 py-6">
  <div class="max-w-6xl mx-auto space-y-6">

    <!-- Header -->
    <div class="flex flex-wrap items-start justify-between gap-4">
      <div class="space-y-1">
        <h1 class="text-2xl md:text-3xl font-semibold text-gray-900 dark:text-gray-50 flex items-center gap-2">
          <i class="fa fa-arrow-up-from-bracket text-emerald-500" aria-hidden="true"></i>
          <span>Deposits</span>
        </h1>
        <p class="text-sm text-gray-500 dark:text-gray-400">
          Branch cash → HQ bank deposits, outlet wise tracking.
        </p>
        <p class="text-xs text-gray-400 dark:text-gray-500">
          আজকের তারিখ:
          <span class="font-medium text-gray-600 dark:text-gray-300">
            <?= $h(date('d M Y')) ?>
          </span>
        </p>
      </div>

      <!-- Actions / nav -->
      <div class="flex flex-wrap items-center justify-end gap-2">
        <a href="<?= $h($base) ?>/banking/accounts"
           class="inline-flex items-center gap-1 h-9 px-3 rounded-full text-xs font-medium border border-gray-200 dark:border-gray-700 text-gray-600 dark:text-gray-300 bg-white/80 dark:bg-gray-900/70 hover:bg-gray-50">
          <i class="fa fa-landmark text-[11px]" aria-hidden="true"></i>
          <span>HQ Accounts</span>
        </a>
        <a href="<?= $h($base) ?>/banking/cash-registers"
           class="inline-flex items-center gap-1 h-9 px-3 rounded-full text-xs font-medium border border-gray-200 dark:border-gray-700 text-gray-600 dark:text-gray-300 bg-white/80 dark:bg-gray-900/70 hover:bg-gray-50">
          <i class="fa fa-cash-register text-[11px]" aria-hidden="true"></i>
          <span>Cash Registers</span>
        </a>
        <a href="<?= $h($base) ?>/banking/deposits"
           class="inline-flex items-center gap-1 h-9 px-3 rounded-full text-xs font-semibold border border-emerald-500/80 text-emerald-700 dark:text-emerald-300 bg-emerald-50 dark:bg-emerald-900/40">
          <i class="fa fa-arrow-up-from-bracket text-[11px]" aria-hidden="true"></i>
          <span>Deposits</span>
        </a>
        <a href="<?= $h($base) ?>/banking/deposits/create"
           class="inline-flex items-center gap-2 h-9 px-4 rounded-lg text-sm font-semibold text-white shadow-sm"
           style="background:<?= $brand ?>;">
          <span class="fa fa-plus text-xs" aria-hidden="true"></span>
          <span>New Deposit</span>
        </a>
      </div>
    </div>

  
    <!-- Filter + table -->
    <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-2xl overflow-hidden">
      <div class="px-4 py-3 border-b border-gray-100 dark:border-gray-800 flex flex-wrap items-center justify-between gap-3">
        <form method="get" class="flex items-center gap-2 flex-1 min-w-[220px]">
          <div class="relative flex-1">
            <span class="absolute inset-y-0 left-3 flex items-center text-gray-400 text-xs">
              <i class="fa fa-search" aria-hidden="true"></i>
            </span>
            <input name="q"
                   value="<?= $h($search) ?>"
                   placeholder="Search branch / bank / reference"
                   class="w-full pl-8 pr-3 py-2 rounded-lg border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800 text-sm text-gray-800 dark:text-gray-100 focus:outline-none focus:ring-2 focus:ring-emerald-500/60 focus:border-emerald-500">
          </div>
          <button class="inline-flex items-center gap-1 px-3 py-2 rounded-lg border border-gray-200 dark:border-gray-700 text-xs font-medium text-gray-700 dark:text-gray-200 bg-white dark:bg-gray-900 hover:bg-gray-50 dark:hover:bg-gray-800">
            <i class="fa fa-filter text-[11px]" aria-hidden="true"></i>
            <span>Search</span>
          </button>
        </form>
        <div class="text-[11px] text-gray-400 dark:text-gray-500">
          <?= count($rows) ?> record<?= count($rows) === 1 ? '' : 's' ?> found
        </div>
      </div>

      <?php if (empty($rows)): ?>
        <!-- Empty state -->
        <div class="py-12 flex flex-col items-center justify-center text-center gap-3">
          <div class="h-11 w-11 rounded-2xl bg-gray-100 dark:bg-gray-800 flex items-center justify-center text-gray-400 dark:text-gray-500">
            <i class="fa fa-arrow-up-from-bracket" aria-hidden="true"></i>
          </div>
          <div class="space-y-1">
            <p class="text-sm font-medium text-gray-800 dark:text-gray-100">
              No deposits recorded yet.
            </p>
            <p class="text-xs text-gray-500 dark:text-gray-400">
              দিন শেষে আউটলেটের cash bank এ জমা দিলে এখানে একটি deposit create করুন।
            </p>
          </div>
          <a href="<?= $h($base) ?>/banking/deposits/create"
             class="inline-flex items-center gap-2 h-9 px-4 rounded-lg text-sm font-semibold text-white shadow-sm"
             style="background:<?= $brand ?>;">
            <i class="fa fa-plus text-xs" aria-hidden="true"></i>
            <span>Add first deposit</span>
          </a>
        </div>
      <?php else: ?>
        <div class="overflow-x-auto">
          <table class="min-w-full text-sm text-gray-700 dark:text-gray-200">
            <thead class="bg-gray-50 dark:bg-gray-800/70 border-b border-gray-100 dark:border-gray-800 text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">
              <tr>
                <th class="px-4 py-2 text-left">Date</th>
                <th class="px-4 py-2 text-left">Branch</th>
                <th class="px-4 py-2 text-left">Register</th>
                <th class="px-4 py-2 text-left">Bank Account</th>
                <th class="px-4 py-2 text-right">Amount</th>
                <th class="px-4 py-2 text-left">Status</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-50 dark:divide-gray-800">
            <?php foreach ($rows as $d): ?>
              <?php
                $date   = $d['deposit_date'] ?? $d['date'] ?? null;
                $date   = $date ? date('d M Y', strtotime((string)$date)) : '';
                $branch = $d['branch_name'] ?? $d['branch'] ?? '—';
                $reg    = $d['register_name'] ?? $d['source_register'] ?? '—';
                $bank   = $d['bank_account_name'] ?? $d['bank_name'] ?? $d['account_name'] ?? '—';
                $amount = (float)($d['amount'] ?? $d['total'] ?? 0);
                $status = strtolower((string)($d['status'] ?? 'pending'));
                $badgeClasses = match ($status) {
                    'approved','posted' => 'bg-emerald-50 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-200',
                    'rejected','void'   => 'bg-rose-50 text-rose-700 dark:bg-rose-900/40 dark:text-rose-200',
                    default             => 'bg-amber-50 text-amber-700 dark:bg-amber-900/40 dark:text-amber-200',
                };
              ?>
              <tr class="hover:bg-gray-50/70 dark:hover:bg-gray-800/70">
                <td class="px-4 py-2 whitespace-nowrap"><?= $h($date) ?></td>
                <td class="px-4 py-2 whitespace-nowrap"><?= $h($branch) ?></td>
                <td class="px-4 py-2 whitespace-nowrap text-xs"><?= $h($reg) ?></td>
                <td class="px-4 py-2 whitespace-nowrap text-xs">
                  <?= $h($bank) ?>
                </td>
                <td class="px-4 py-2 whitespace-nowrap text-right font-semibold">
                  ৳<?= number_format($amount, 2) ?>
                </td>
                <td class="px-4 py-2 whitespace-nowrap">
                  <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[11px] <?= $badgeClasses ?>">
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
    
     <!-- Guidance card (Bangla-first) -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
      <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-2xl px-4 py-4 space-y-2">
        <h2 class="text-sm font-semibold text-gray-800 dark:text-gray-100 flex items-center gap-2">
          <i class="fa fa-lightbulb text-amber-400" aria-hidden="true"></i>
          <span>কীভাবে Deposits ব্যবহার করবেন</span>
        </h2>
        <ul class="text-[13px] text-gray-600 dark:text-gray-300 space-y-1.5 leading-relaxed">
          <li>১) প্রতিটি আউটলেটের POS cash register প্রথমে ঠিকভাবে সেট করুন (Cash Registers)।</li>
          <li>২) দিন শেষে আউটলেটের cash amount → এখানে নতুন Deposit হিসেবে এন্ট্রি দিন।</li>
          <li>৩) Deposit করার সময় কোন bank account এ জমা দিচ্ছেন এবং কোন outlet থেকে আসছে, দুটোই নির্বাচন করুন।</li>
          <li>৪) HQ ব্যবহারকারী সব আউটলেটের deposit দেখতে পারবেন, outlet user শুধু নিজের আউটলেটেরটি দেখতে পারবে।</li>
          <li>৫) Accounting মডিউলে এই deposit গুলো auto-sync হবে (cash ↓, bank ↑)।</li>
        </ul>
      </div>

      <div class="bg-emerald-50/70 dark:bg-emerald-900/30 border border-emerald-100 dark:border-emerald-800 rounded-2xl px-4 py-4 flex flex-col justify-between">
        <div class="space-y-2">
          <h3 class="text-sm font-semibold text-emerald-900 dark:text-emerald-100">
            ছোট্ট টিপস
          </h3>
          <ul class="text-[13px] text-emerald-900/80 dark:text-emerald-100 space-y-1.5 leading-relaxed">
            <li>• Outlet-wise deposit মিলিয়ে cash short/over সহজে ধরতে পারবেন।</li>
            <li>• Master bank account এ গেলে boss live cash-to-bank flow দেখতে পাবেন।</li>
            <li>• Reference / memo ফিল্ড ব্যবহার করে deposit slip বা bank scroll number লিখে রাখুন।</li>
          </ul>
        </div>
        <p class="mt-3 text-[12px] text-emerald-800/80 dark:text-emerald-200">
          <span class="font-semibold">Remember:</span>
          এখানে শুধু deposit request থাকে – actual bank reconciliation পরে Reconcile screen থেকে হবে।
        </p>
      </div>
    </div>

  </div>
</div>