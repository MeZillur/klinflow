<?php
/**
 * @var string $base
 * @var array  $rows
 * @var array  $hqMap
 * @var bool   $needsBranch
 * @var string $branchName
 * @var string $search
 */
$h = fn($x)=>htmlspecialchars((string)$x, ENT_QUOTES, 'UTF-8');
?>
<div class="px-6 py-6">
  <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
    <div>
      <h1 class="text-2xl md:text-3xl font-semibold text-gray-900 dark:text-gray-50">
        Outlet Bank &amp; Cash Accounts
      </h1>
      <p class="text-sm text-gray-500 dark:text-gray-400">
        Each outlet links to HQ bank/cash accounts for deposits and payouts.
      </p>
      <?php if (!$needsBranch): ?>
        <p class="mt-1 text-xs text-gray-400 dark:text-gray-500">
          Active branch:
          <span class="inline-flex items-center px-2 py-0.5 rounded-full border border-gray-200 dark:border-gray-700">
            <i class="fa fa-store mr-1 text-xs text-emerald-500" aria-hidden="true"></i>
            <?= $h($branchName ?: 'Branch') ?>
          </span>
        </p>
      <?php endif; ?>
    </div>

    <?php if (!$needsBranch): ?>
      <form method="get" class="flex items-center gap-2 text-sm">
        <input type="text" name="q" value="<?= $h($search) ?>"
               placeholder="Search alias / branch"
               class="px-3 py-2 rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 text-sm">
        <button class="px-3 py-2 rounded-xl border border-gray-200 dark:border-gray-700 text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-800">
          <i class="fa fa-search mr-1 text-xs"></i> Search
        </button>
        <a href="<?= $h($base) ?>/branches/create"
           class="inline-flex items-center gap-2 px-3 py-2 rounded-xl bg-emerald-600 text-white text-sm hover:bg-emerald-700">
          <i class="fa fa-plus" aria-hidden="true"></i>
          New Outlet Account
        </a>
      </form>
    <?php endif; ?>
  </div>

  <?php if ($needsBranch): ?>
    <div class="mt-10 max-w-xl rounded-2xl border border-dashed border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-900 p-6 text-center">
      <i class="fa fa-store text-3xl text-emerald-500 mb-3" aria-hidden="true"></i>
      <p class="text-sm text-gray-700 dark:text-gray-200 mb-1">
        Select a branch to manage outlet bank &amp; cash accounts.
      </p>
      <p class="text-xs text-gray-400 dark:text-gray-500">
        Use the branch switcher at the top bar, then come back to this page.
      </p>
    </div>
  <?php elseif (!$rows): ?>
    <div class="mt-8 max-w-xl mx-auto rounded-2xl border border-dashed border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-900 p-6 text-center">
      <i class="fa fa-piggy-bank text-3xl text-emerald-500 mb-3" aria-hidden="true"></i>
      <p class="text-sm text-gray-700 dark:text-gray-200 mb-1">
        No outlet bank &amp; cash accounts defined for this branch yet.
      </p>
      <p class="text-xs text-gray-400 dark:text-gray-500 mb-4">
        Create at least one account so this branch can deposit daily sales into HQ bank accounts.
      </p>
      <a href="<?= $h($base) ?>/branches/create"
         class="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-emerald-600 text-white text-sm hover:bg-emerald-700">
        <i class="fa fa-plus" aria-hidden="true"></i>
        Add first outlet account
      </a>
    </div>
  <?php else: ?>
    <div class="mt-4 rounded-2xl border border-gray-100 dark:border-gray-800 bg-white dark:bg-gray-900 overflow-hidden">
      <table class="min-w-full text-sm">
        <thead class="bg-gray-50 dark:bg-gray-800/60 text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">
          <tr>
            <th class="px-4 py-3 text-left">Alias</th>
            <th class="px-4 py-3 text-left">HQ Account</th>
            <th class="px-4 py-3 text-center">Default</th>
            <th class="px-4 py-3 text-center">Status</th>
            <th class="px-4 py-3 text-right">Actions</th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($rows as $r): ?>
          <?php
            $hq = $hqMap[$r['hq_bank_account_id']] ?? null;
          ?>
          <tr class="border-t border-gray-100 dark:border-gray-800">
            <td class="px-4 py-3 align-top">
              <div class="font-medium text-gray-900 dark:text-gray-50">
                <?= $h($r['alias_name'] ?: ($hq['label'] ?? 'Outlet account')) ?>
              </div>
              <div class="text-xs text-gray-400 dark:text-gray-500">
                Branch: <?= $h($r['branch_name']) ?>
              </div>
            </td>
            <td class="px-4 py-3 align-top">
              <?php if ($hq): ?>
                <div class="text-gray-900 dark:text-gray-50">
                  <?= $h($hq['label']) ?>
                </div>
                <?php if ($hq['account_no']): ?>
                  <div class="text-xs text-gray-400 dark:text-gray-500">
                    A/C <?= $h($hq['account_no']) ?>
                  </div>
                <?php endif; ?>
              <?php else: ?>
                <span class="text-xs text-red-500">Linked HQ account missing</span>
              <?php endif; ?>
            </td>
            <td class="px-4 py-3 text-center align-top">
              <?php if ($r['is_default']): ?>
                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] bg-emerald-50 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300">
                  <i class="fa fa-star mr-1 text-[10px]" aria-hidden="true"></i>
                  Default
                </span>
              <?php else: ?>
                <span class="text-xs text-gray-400 dark:text-gray-500">—</span>
              <?php endif; ?>
            </td>
            <td class="px-4 py-3 text-center align-top">
              <?php if ($r['is_active']): ?>
                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] bg-emerald-50 text-emerald-700 dark:bg-emerald-300">
                  Active
                </span>
              <?php else: ?>
                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] bg-gray-100 text-gray-500 dark:bg-gray-800 dark:text-gray-400">
                  Inactive
                </span>
              <?php endif; ?>
            </td>
            <td class="px-4 py-3 text-right align-top">
              <a href="<?= $h($base) ?>/branches/<?= $h($r['id']) ?>/edit"
                 class="inline-flex items-center gap-1 px-3 py-1.5 rounded-lg border border-gray-200 dark:border-gray-700 text-xs text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-800">
                <i class="fa fa-pen text-[11px]" aria-hidden="true"></i>
                Edit
              </a>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>