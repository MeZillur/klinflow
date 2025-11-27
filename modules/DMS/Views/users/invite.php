<?php
declare(strict_types=1);
/** @var string $module_base */
$h = fn($v)=>htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
?>
<div class="p-6 dark:bg-slate-950 dark:text-slate-100">
  <div class="flex items-center justify-between mb-6">
    <div class="flex items-center gap-3">
      <div class="h-10 w-10 rounded-xl bg-sky-600 text-white grid place-items-center shadow-sm">
        <i class="fa-solid fa-paper-plane"></i>
      </div>
      <div>
        <h1 class="text-2xl font-extrabold tracking-tight">Invite User</h1>
        <p class="text-sm text-slate-500 dark:text-slate-400">Send an email invite to join</p>
      </div>
    </div>
    <a href="<?= $h($module_base) ?>/users"
       class="inline-flex items-center gap-2 px-3 py-2 rounded-xl border border-slate-300 dark:border-slate-700 hover:bg-slate-50 dark:hover:bg-slate-900">
      <i class="fa-solid fa-arrow-left"></i> Back to users
    </a>
  </div>

  <?php if (!empty($_SESSION['flash'])): ?>
    <div class="mb-4">
      <?php foreach ($_SESSION['flash'] as $k=>$m): unset($_SESSION['flash'][$k]); ?>
        <div class="px-3 py-2 rounded-lg text-sm
                    <?= $k==='ok' ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-900/20 dark:text-emerald-300'
                                  : 'bg-rose-50 text-rose-700 dark:bg-rose-900/20 dark:text-rose-300' ?>">
          <?= $h($m) ?>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

  <form method="post" action="<?= $h($module_base) ?>/users/invite" class="max-w-lg">
    <label class="block mb-4">
      <span class="text-sm font-medium text-slate-700 dark:text-slate-300">Email</span>
      <input type="email" name="email" required
             class="mt-1 w-full rounded-xl border border-slate-300 dark:border-slate-700 px-3 py-2 bg-white dark:bg-slate-900 focus:ring-2 focus:ring-emerald-400 focus:outline-none">
    </label>

    <label class="block mb-6">
      <span class="text-sm font-medium text-slate-700 dark:text-slate-300">Role</span>
      <select name="role"
              class="mt-1 w-full rounded-xl border border-slate-300 dark:border-slate-700 px-3 py-2 bg-white dark:bg-slate-900 focus:ring-2 focus:ring-emerald-400 focus:outline-none">
        <option value="employee">Employee</option>
        <option value="manager">Manager</option>
        <option value="owner">Owner</option>
      </select>
    </label>

    <div class="flex gap-3">
      <button type="submit"
              class="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-emerald-600 text-white hover:opacity-95">
        <i class="fa-solid fa-paper-plane"></i> Send invite
      </button>
      <a href="<?= $h($module_base) ?>/users"
         class="inline-flex items-center gap-2 px-4 py-2 rounded-xl border border-slate-300 dark:border-slate-700 hover:bg-slate-50 dark:hover:bg-slate-900">
        Cancel
      </a>
    </div>
  </form>
</div>