<?php
declare(strict_types=1);
/** @var array  $rows */
/** @var string $q */
/** @var string $module_base */

$h   = fn($v)=>htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
$fmt = fn($n)=>number_format((float)$n, 2);
$base = rtrim((string)$module_base, '/');
?>
<div class="space-y-6">

  <!-- Header -->
  <div class="flex items-center justify-between flex-wrap gap-3">
    <h1 class="text-xl font-semibold">Bank Accounts</h1>
    <div class="flex items-center gap-2">
      <!-- supports /bank-accounts?create=1 per router -->
      <a href="<?= $h($base) ?>/bank-accounts?create=1"
         class="px-3 py-1.5 rounded-lg bg-emerald-600 text-white hover:bg-emerald-700">+ New Account</a>
    </div>
  </div>

  <!-- Search -->
  <form method="get" class="flex items-center gap-2 p-3 rounded-xl border bg-white dark:bg-gray-900">
    <input type="text" name="q" value="<?= $h($q ?? '') ?>"
           placeholder="Search bank or account name..."
           class="flex-1 border rounded-lg px-3 py-2 bg-white dark:bg-gray-900">
    <button class="px-3 py-2 rounded-lg bg-emerald-600 text-white hover:bg-emerald-700">Search</button>
  </form>

  <!-- Table -->
  <div class="overflow-x-auto rounded-xl border bg-white dark:bg-gray-900">
    <table class="min-w-full text-[13px]">
      <thead class="bg-gray-50 dark:bg-gray-800 text-gray-600 dark:text-gray-300">
        <tr>
          <th class="px-3 py-2 text-left">Code</th>
          <th class="px-3 py-2 text-left">Bank</th>
          <th class="px-3 py-2 text-left">Account</th>
          <th class="px-3 py-2 text-left">Number</th>
          <th class="px-3 py-2 text-right">Balance</th>
          <th class="px-3 py-2 text-center">Status</th>
          <th class="px-3 py-2 text-center">Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php if (!empty($rows)): foreach ($rows as $r): ?>
        <tr class="border-t hover:bg-gray-50 dark:hover:bg-gray-800/40">
          <td class="px-3 py-2 font-mono"><?= $h($r['code'] ?? '') ?></td>
          <td class="px-3 py-2"><?= $h($r['bank_name'] ?? '') ?></td>
          <td class="px-3 py-2"><?= $h($r['account_name'] ?? '') ?></td>
          <td class="px-3 py-2"><?= $h($r['account_no'] ?? '—') ?></td>
          <td class="px-3 py-2 text-right">৳ <?= $fmt($r['current_balance'] ?? 0) ?></td>
          <td class="px-3 py-2 text-center">
            <?php if ((int)($r['is_master'] ?? 0) === 1): ?>
              <span class="px-2 py-0.5 rounded-lg text-xs font-semibold bg-emerald-100 text-emerald-700 dark:bg-emerald-800/40 dark:text-emerald-300">Master</span>
            <?php elseif (($r['status'] ?? '') === 'active'): ?>
              <span class="px-2 py-0.5 rounded-lg text-xs font-semibold bg-blue-100 text-blue-700 dark:bg-blue-800/40 dark:text-blue-300">Active</span>
            <?php else: ?>
              <span class="px-2 py-0.5 rounded-lg text-xs font-semibold bg-gray-200 text-gray-700 dark:bg-gray-800/50 dark:text-gray-400">Inactive</span>
            <?php endif; ?>
          </td>
          <td class="px-3 py-2 text-center">
            <!-- View + Edit -->
            <a href="<?= $h($base) ?>/bank-accounts/<?= $h($r['id']) ?>" class="text-slate-600 hover:underline text-sm">View</a>
            ·
            <a href="<?= $h($base) ?>/bank-accounts/<?= $h($r['id']) ?>/edit" class="text-emerald-600 hover:underline text-sm">Edit</a>

            <!-- Make master (POST) -->
            <?php if (empty($r['is_master'])): ?>
              ·
              <form method="post"
                    action="<?= $h($base) ?>/bank-accounts/<?= $h($r['id']) ?>/make-master"
                    class="inline"
                    onsubmit="return confirm('Make this the master bank account?');">
                <?php if (function_exists('csrf_field')) echo csrf_field(); ?>
                <button class="text-gray-500 hover:text-emerald-700 text-sm" type="submit">Make master</button>
              </form>
            <?php endif; ?>
          </td>
        </tr>
        <?php endforeach; else: ?>
        <tr>
          <td colspan="7" class="px-3 py-10 text-center text-gray-500 dark:text-gray-400">
            No bank accounts found. <br>
            <a href="<?= $h($base) ?>/bank-accounts?create=1" class="underline text-emerald-600">Create your first bank account</a>
          </td>
        </tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>