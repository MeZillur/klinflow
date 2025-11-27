<?php
declare(strict_types=1);
/** @var array $rows */
/** @var string $module_base */
$h = fn($v)=>htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
?>
<div class="p-6 dark:bg-slate-950 dark:text-slate-100">
  <div class="flex items-center justify-between mb-6">
    <div class="flex items-center gap-3">
      <div class="h-10 w-10 rounded-xl bg-emerald-600 text-white grid place-items-center shadow-sm">
        <i class="fa-solid fa-users"></i>
      </div>
      <div>
        <h1 class="text-2xl font-extrabold tracking-tight">Users</h1>
        <p class="text-sm text-slate-500 dark:text-slate-400">Manage access to this workspace</p>
      </div>
    </div>
    <a href="<?= $h($module_base) ?>/users/invite"
       class="inline-flex items-center gap-2 px-3 py-2 rounded-xl bg-emerald-600 text-white hover:opacity-95">
      <i class="fa-solid fa-paper-plane"></i> Invite user
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

  <div class="overflow-x-auto rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900">
    <table class="min-w-full text-sm">
      <thead class="text-left text-slate-600 dark:text-slate-300">
        <tr>
          <th class="px-4 py-3">Name</th>
          <th class="px-4 py-3">Email</th>
          <th class="px-4 py-3">Username</th>
          <th class="px-4 py-3">Role</th>
          <th class="px-4 py-3">Status</th>
          <th class="px-4 py-3">Joined</th>
        </tr>
      </thead>
      <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
        <?php if (!$rows): ?>
          <tr><td colspan="6" class="px-4 py-6 text-center text-slate-500 dark:text-slate-400">No users yet.</td></tr>
        <?php else: foreach ($rows as $r): ?>
          <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/40">
            <td class="px-4 py-3 font-medium"><?= $h($r['name'] ?: '—') ?></td>
            <td class="px-4 py-3"><?= $h($r['email']) ?></td>
            <td class="px-4 py-3"><?= $h($r['username'] ?: '—') ?></td>
            <td class="px-4 py-3">
              <span class="px-2 py-0.5 rounded-lg text-xs
                           <?= $r['role']==='owner' ? 'bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-300'
                           : ($r['role']==='manager' ? 'bg-sky-100 text-sky-800 dark:bg-sky-900/30 dark:text-sky-300'
                                                     : 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300') ?>">
                <?= $h(strtoupper($r['role'])) ?>
              </span>
            </td>
            <td class="px-4 py-3">
              <?php if ((int)$r['is_active']===1): ?>
                <span class="inline-flex items-center gap-1 text-emerald-600 dark:text-emerald-400">
                  <i class="fa-solid fa-circle-check"></i> Active
                </span>
              <?php else: ?>
                <span class="inline-flex items-center gap-1 text-slate-500 dark:text-slate-400">
                  <i class="fa-regular fa-circle"></i> Inactive
                </span>
              <?php endif; ?>
            </td>
            <td class="px-4 py-3"><?= $h(substr((string)$r['created_at'],0,16)) ?></td>
          </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>