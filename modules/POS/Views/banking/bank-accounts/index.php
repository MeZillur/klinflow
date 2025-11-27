<?php
declare(strict_types=1);

/** @var array $rows */
/** @var string $base */

$h    = $h    ?? fn($s)=>htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
$base = $base ?? '/apps/pos';
$search = $search ?? '';
?>
<div class="px-6 py-6 space-y-6">

  <!-- Header -->
  <div class="flex flex-wrap items-center justify-between gap-4">
    <div>
      <h1 class="text-2xl md:text-3xl font-semibold text-gray-900 dark:text-gray-50">
        HQ Bank &amp; Cash Accounts
      </h1>
      <p class="text-sm text-gray-500 dark:text-gray-400">
        Master list of all organisation-level bank and cash accounts.
      </p>
    </div>

    <div class="flex flex-wrap gap-2">
      <form method="get" class="flex items-center gap-2">
        <input
          type="text"
          name="q"
          value="<?= $h($search) ?>"
          placeholder="Search bank / name / account no"
          class="h-9 w-52 md:w-64 rounded-lg border border-gray-300 dark:border-gray-700
                 bg-white dark:bg-gray-900 px-3 text-sm
                 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500">
        <button
          type="submit"
          class="inline-flex items-center gap-2 h-9 px-3 rounded-lg text-sm
                 bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-100">
          <span class="fa fa-search text-xs"></span>
          <span>Search</span>
        </button>
      </form>

      <a href="<?= $h($base) ?>/banking/accounts/create"
         class="inline-flex items-center gap-2 h-9 px-4 rounded-lg text-sm font-semibold
                bg-emerald-600 hover:bg-emerald-700 text-white shadow-sm">
        <span class="fa fa-plus text-xs"></span>
        <span>New Account</span>
      </a>
      <!-- Outlet / branch accounts -->
    <a href="<?= $h($base) ?>/banking/branches"
       class="inline-flex items-center gap-2 h-9 px-3 rounded-lg text-xs md:text-sm font-medium
              border border-gray-200 dark:border-gray-700 text-gray-700 dark:text-gray-200
              bg-white dark:bg-gray-900 hover:bg-gray-50 dark:hover:bg-gray-800">
      <span class="fa fa-store text-xs"></span>
      <span>Outlet Accounts</span>
    </a>
      <!-- Cash registers -->
    <a href="<?= $h($base) ?>/banking/cash-registers"
       class="inline-flex items-center gap-2 h-9 px-3 rounded-lg text-xs md:text-sm font-medium
              border border-gray-200 dark:border-gray-700 text-gray-700 dark:text-gray-200
              bg-white dark:bg-gray-900 hover:bg-gray-50 dark:hover:bg-gray-800">
      <span class="fa fa-cash-register text-xs"></span>
      <span>Cash Registers</span>
    </a>
    </div>
  </div>

  <!-- Table card -->
  <div class="rounded-2xl bg-white dark:bg-gray-900 shadow-sm border border-gray-100 dark:border-gray-800">
    <div class="px-4 py-3 border-b border-gray-100 dark:border-gray-800 flex items-center justify-between">
      <div class="text-sm font-semibold text-gray-800 dark:text-gray-100">Accounts</div>
      <div class="text-xs text-gray-400 dark:text-gray-500">
        <?= count($rows) ?> record<?= count($rows) === 1 ? '' : 's' ?>
      </div>
    </div>

    <?php if (empty($rows)): ?>
      <div class="px-4 py-8 text-center text-sm text-gray-400 dark:text-gray-500">
        <div class="mb-2">
          <span class="inline-flex h-9 w-9 items-center justify-center rounded-full
                       bg-gray-100 dark:bg-gray-800 text-gray-500">
            <span class="fa fa-university text-xs"></span>
          </span>
        </div>
        <div>No bank accounts defined yet.</div>
        <div class="mt-2">
          <a href="<?= $h($base) ?>/banking/accounts/create"
             class="inline-flex items-center gap-2 text-emerald-600 dark:text-emerald-400 text-xs font-semibold">
            <span class="fa fa-plus-circle text-xs"></span>
            <span>Add your first account</span>
          </a>
        </div>
      </div>
    <?php else: ?>
      <div class="overflow-x-auto">
        <table class="min-w-full text-sm">
          <thead class="bg-gray-50 dark:bg-gray-800/80 text-xs uppercase text-gray-500 dark:text-gray-400">
            <tr>
              <th class="px-4 py-2 text-left">Account</th>
              <th class="px-4 py-2 text-left">Bank / Branch</th>
              <th class="px-4 py-2 text-left">Type</th>
              <th class="px-4 py-2 text-right">Current Balance</th>
              <th class="px-4 py-2 text-center">Status</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
          <?php foreach ($rows as $r): 
              $id      = (int)($r['id'] ?? 0);
              $code    = (string)($r['code'] ?? '');
              $name    = (string)($r['name'] ?? '');
              $bank    = (string)($r['bank_name'] ?? '');
              $branch  = (string)($r['branch_name'] ?? '');
              $accNo   = (string)($r['account_no'] ?? '');
              $type    = (string)($r['type'] ?? 'bank');
              $cur     = (string)($r['currency'] ?? 'BDT');
              $curBalC = (int)($r['current_balance_cents'] ?? 0);
              $curBal  = $curBalC / 100;
              $active  = (int)($r['is_active'] ?? 1) === 1;
          ?>
            <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/60">
              <td class="px-4 py-2 align-top">
                <div class="font-semibold text-gray-900 dark:text-gray-50">
                  <?= $h($name ?: $bank ?: 'Unnamed account') ?>
                </div>
                <div class="text-xs text-gray-500 dark:text-gray-400">
                  <?= $code ? $h($code).' · ' : '' ?>
                  <?= $accNo ? 'A/C '.$h($accNo) : '' ?>
                </div>
              </td>
              <td class="px-4 py-2 align-top">
                <div class="text-gray-800 dark:text-gray-100 text-sm">
                  <?= $h($bank ?: '-') ?>
                </div>
                <?php if ($branch): ?>
                  <div class="text-xs text-gray-500 dark:text-gray-400">
                    <?= $h($branch) ?>
                  </div>
                <?php endif; ?>
              </td>
              <td class="px-4 py-2 align-top">
                <?php
                  $label = ucfirst($type ?: 'bank');
                  $badgeClasses = 'bg-emerald-50 text-emerald-700';
                  if ($type === 'cash')         $badgeClasses = 'bg-amber-50 text-amber-700';
                  if ($type === 'mobile_wallet')$badgeClasses = 'bg-sky-50 text-sky-700';
                ?>
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?= $badgeClasses ?>">
                  <?= $h($label) ?>
                </span>
              </td>
              <td class="px-4 py-2 align-top text-right">
                <div class="font-semibold text-gray-900 dark:text-gray-50">
                  <?= $h($cur) ?> <?= number_format($curBal, 2) ?>
                </div>
              </td>
              <td class="px-4 py-2 align-top text-center">
                <?php if ($active): ?>
                  <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                               bg-emerald-50 text-emerald-700">
                    <span class="fa fa-check-circle mr-1 text-[10px]"></span> Active
                  </span>
                <?php else: ?>
                  <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                               bg-gray-100 text-gray-600">
                    <span class="fa fa-pause-circle mr-1 text-[10px]"></span> Inactive
                  </span>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>
</div>