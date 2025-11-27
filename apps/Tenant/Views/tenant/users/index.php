<?php
declare(strict_types=1);
/** tenant/users/index.php (drop-in)
 * Expects: $rows (array), $slug (string), optional $flash, $error
 */
$h     = fn($s)=>htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
$rows  = is_array($rows ?? null) ? $rows : [];
$slug  = (string)($slug ?? '');
$flash = (string)($flash ?? '');
$error = (string)($error ?? '');

/** Preserve sort via query (?sort=created_at|name|role&dir=asc|desc) */
$allowedSorts = ['name','email','username','role','is_active','created_at'];
$sort = in_array(($_GET['sort'] ?? ''), $allowedSorts, true) ? $_GET['sort'] : 'created_at';
$dir  = (($_GET['dir'] ?? '') === 'asc') ? 'asc' : 'desc';
$dirFlip = ($dir === 'asc') ? 'desc' : 'asc';

/** Build a single query string with overrides (prevents duplication) */
$with = function(array $overrides) {
    $base = $_GET;                       // current query
    foreach ($overrides as $k=>$v) $base[$k] = $v;
    return '?' . http_build_query($base);
};
?>
<div class="max-w-7xl mx-auto space-y-5 px-4 py-6">
  <div class="flex items-center justify-between gap-3">
    <h1 class="text-2xl font-semibold">Users</h1>
    <a href="/t/<?= $h($slug) ?>/users/invite" class="btn btn-brand text-white rounded-lg px-3 py-2">+ Invite User</a>
  </div>

  <?php if ($flash !== ''): ?>
    <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-emerald-800 text-sm"><?= nl2br($h($flash)) ?></div>
  <?php endif; ?>
  <?php if ($error !== ''): ?>
    <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-red-800 text-sm"><?= nl2br($h($error)) ?></div>
  <?php endif; ?>

  <div class="overflow-auto border border-gray-200 dark:border-gray-700 rounded-xl">
    <table class="min-w-full text-sm">
      <thead class="bg-gray-50 dark:bg-gray-800/60">
        <tr>
          <th class="text-left p-3">
            <a class="inline-flex items-center gap-1 hover:underline" href="<?= $h($with(['sort'=>'name','dir'=>$sort==='name'?$dirFlip:'asc'])) ?>">
              Name <?= $sort==='name' ? ($dir==='asc'?'▲':'▼') : '' ?>
            </a>
          </th>
          <th class="text-left p-3">
            <a class="inline-flex items-center gap-1 hover:underline" href="<?= $h($with(['sort'=>'email','dir'=>$sort==='email'?$dirFlip:'asc'])) ?>">
              Email <?= $sort==='email' ? ($dir==='asc'?'▲':'▼') : '' ?>
            </a>
          </th>
          <th class="text-left p-3">
            <a class="inline-flex items-center gap-1 hover:underline" href="<?= $h($with(['sort'=>'username','dir'=>$sort==='username'?$dirFlip:'asc'])) ?>">
              Username <?= $sort==='username' ? ($dir==='asc'?'▲':'▼') : '' ?>
            </a>
          </th>
          <th class="text-left p-3">
            <a class="inline-flex items-center gap-1 hover:underline" href="<?= $h($with(['sort'=>'role','dir'=>$sort==='role'?$dirFlip:'asc'])) ?>">
              Role <?= $sort==='role' ? ($dir==='asc'?'▲':'▼') : '' ?>
            </a>
          </th>
          <th class="text-left p-3">
            <a class="inline-flex items-center gap-1 hover:underline" href="<?= $h($with(['sort'=>'is_active','dir'=>$sort==='is_active'?$dirFlip:'asc'])) ?>">
              Status <?= $sort==='is_active' ? ($dir==='asc'?'▲':'▼') : '' ?>
            </a>
          </th>
          <th class="text-left p-3">
            <a class="inline-flex items-center gap-1 hover:underline" href="<?= $h($with(['sort'=>'created_at','dir'=>$sort==='created_at'?$dirFlip:'desc'])) ?>">
              Joined <?= $sort==='created_at' ? ($dir==='asc'?'▲':'▼') : '' ?>
            </a>
          </th>
          <th class="text-left p-3">Actions</th>
        </tr>
      </thead>

      <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
        <?php if (!$rows): ?>
          <tr>
            <td colspan="7" class="p-10 text-center">
              <div class="text-gray-600 dark:text-gray-400 mb-3">No users yet.</div>
              <a href="/t/<?= $h($slug) ?>/users/invite" class="inline-flex items-center gap-2 rounded-lg px-3 py-2 bg-emerald-600 text-white hover:brightness-95">
                <span>+ Invite User</span>
              </a>
            </td>
          </tr>
        <?php else: ?>
          <?php foreach ($rows as $r): ?>
            <?php
              $id       = (int)($r['id'] ?? 0);
              $name     = (string)($r['name'] ?? '');
              $email    = (string)($r['email'] ?? '');
              $username = (string)($r['username'] ?? '');
              $mobile   = (string)($r['mobile'] ?? '');
              $role     = (string)($r['role'] ?? 'employee');
              $active   = (int)($r['is_active'] ?? 0) === 1;
              $joined   = (string)($r['created_at'] ?? '');
            ?>
            <tr>
              <td class="p-3">
                <div class="font-medium"><?= $name !== '' ? $h($name) : '<span class="text-gray-500">—</span>' ?></div>
                <?php if ($mobile !== ''): ?>
                  <div class="text-xs text-gray-500"><?= $h($mobile) ?></div>
                <?php endif; ?>
              </td>
              <td class="p-3"><?= $h($email) ?></td>
              <td class="p-3"><?= $username !== '' ? $h($username) : '<span class="text-gray-400">—</span>' ?></td>
              <td class="p-3 capitalize">
                <span class="inline-flex items-center gap-1">
                  <?= $h($role) ?>
                  <?php if ($role === 'owner'): ?>
                    <span class="text-[10px] px-1.5 py-0.5 rounded-full bg-amber-100 text-amber-700 border border-amber-200">Owner</span>
                  <?php endif; ?>
                </span>
              </td>
              <td class="p-3">
                <span class="text-xs px-2 py-1 rounded-full border
                  <?= $active ? 'bg-emerald-50 text-emerald-700 border-emerald-200'
                              : 'bg-gray-100 text-gray-600 border-gray-300' ?>">
                  <?= $active ? 'Active' : 'Inactive' ?>
                </span>
              </td>
              <td class="p-3 whitespace-nowrap"><?= $h($joined) ?></td>
              <td class="p-3">
                <a class="text-brand hover:underline" href="/t/<?= $h($slug) ?>/users/<?= $id ?>/edit">Edit</a>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>

  <!-- Optional footer link -->
  <div class="pt-2">
    <a href="/t/<?= $h($slug) ?>/users/roles" class="text-sm text-gray-600 dark:text-gray-400 hover:underline">
      Manage roles &amp; permissions →
    </a>
  </div>
</div>