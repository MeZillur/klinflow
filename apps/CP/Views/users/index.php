<?php
declare(strict_types=1);
/**
 * cp/users/index.php — Control Panel Users List
 * Expects:
 *   - $users: array[] (id,name,email,username,mobile,role,is_active,last_login_at,created_at)
 *   - $q:     string  (search query)
 *   - $role:  string  (filter role)
 */
$h = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
$users = is_array($users ?? null) ? $users : [];
$q     = (string)($q ?? '');
$role  = (string)($role ?? '');
$roles = ['superadmin','admin','staff'];
?>
<div class="max-w-6xl mx-auto px-4 py-6">
  <div class="flex items-center justify-between mb-4">
    <h1 class="text-2xl font-semibold">CP Users</h1>
    <a href="/cp/users/new"
       class="inline-flex items-center gap-2 px-3 py-2 rounded-lg text-white"
       style="background:var(--brand,#228B22)">
      <i class="fa-solid fa-user-plus"></i><span>New User</span>
    </a>
  </div>

  <p class="text-sm text-gray-500 dark:text-gray-400 mb-4">
    Manage control panel users. Email is unique; users can log in via email, username, or mobile.
  </p>

  <!-- Filters -->
  <form method="get" class="flex flex-wrap items-center gap-2 mb-4" autocomplete="off" role="search" aria-label="Filter users">
    <input
      class="border rounded-lg px-3 py-2 dark:bg-gray-900 dark:border-gray-700"
      type="search" name="q"
      placeholder="Search by name, email, username, mobile"
      value="<?= $h($q) ?>"
      aria-label="Search" />

    <select class="border rounded-lg px-3 py-2 dark:bg-gray-900 dark:border-gray-700" name="role" aria-label="Role">
      <option value="">All roles</option>
      <?php foreach ($roles as $r): ?>
        <option value="<?= $h($r) ?>" <?= $role===$r ? 'selected':'' ?>><?= ucfirst($h($r)) ?></option>
      <?php endforeach; ?>
    </select>

    <button class="px-3 py-2 rounded-lg border dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-800" type="submit">
      Filter
    </button>

    <?php if ($q !== '' || $role !== ''): ?>
      <a class="px-3 py-2 rounded-lg text-sm text-gray-600 dark:text-gray-300 hover:underline"
         href="/cp/users">Clear</a>
    <?php endif; ?>
  </form>

  <!-- Results -->
  <div class="overflow-auto rounded-xl border border-gray-200 dark:border-gray-700">
    <table class="min-w-full text-sm">
      <thead class="bg-gray-50 dark:bg-gray-800/60">
        <tr>
          <th class="text-left px-3 py-2">User</th>
          <th class="text-left px-3 py-2">Contact</th>
          <th class="text-left px-3 py-2">Role</th>
          <th class="text-left px-3 py-2">Status</th>
          <th class="text-left px-3 py-2">Last Login</th>
          <th class="px-3 py-2"></th>
        </tr>
      </thead>
      <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
        <?php if (!$users): ?>
          <tr>
            <td colspan="6" class="px-3 py-6 text-center text-gray-500 dark:text-gray-400">
              No users found.
            </td>
          </tr>
        <?php else: foreach ($users as $u):
          $active = (int)($u['is_active'] ?? 0) === 1;
          $roleTxt = (string)($u['role'] ?? '');
          $ll = (string)($u['last_login_at'] ?? '');
        ?>
          <tr>
            <td class="px-3 py-2 align-top">
              <div class="font-medium"><?= $h($u['name'] ?? '') ?></div>
              <div class="text-xs text-gray-500 dark:text-gray-400">@<?= $h($u['username'] ?? '') ?></div>
            </td>
            <td class="px-3 py-2 align-top">
              <div><?= $h($u['email'] ?? '') ?></div>
              <div class="text-xs text-gray-500 dark:text-gray-400"><?= $h($u['mobile'] ?? '—') ?></div>
            </td>
            <td class="px-3 py-2 align-top">
              <span class="px-2 py-1 rounded-full text-xs bg-gray-100 dark:bg-gray-800/70">
                <?= $h($roleTxt ?: 'staff') ?>
              </span>
            </td>
            <td class="px-3 py-2 align-top">
              <?php if ($active): ?>
                <span class="text-xs px-2 py-1 rounded-full bg-emerald-100 text-emerald-700">Active</span>
              <?php else: ?>
                <span class="text-xs px-2 py-1 rounded-full bg-gray-200 text-gray-600">Disabled</span>
              <?php endif; ?>
            </td>
            <td class="px-3 py-2 align-top"><?= $h($ll !== '' ? $ll : '—') ?></td>
            <td class="px-3 py-2 align-top text-right whitespace-nowrap">
              <a class="text-brand hover:underline" href="/cp/users/<?= (int)($u['id'] ?? 0) ?>">View</a>
              <span class="mx-1 text-gray-300">·</span>
              <a class="text-brand hover:underline" href="/cp/users/<?= (int)($u['id'] ?? 0) ?>/edit">Edit</a>
            </td>
          </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>