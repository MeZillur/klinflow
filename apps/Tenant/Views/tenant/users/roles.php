<?php
declare(strict_types=1);
/**
 * tenant/users/roles.php (drop-in view)
 * Expects: $slug
 * Optional:
 *   - $csrf
 *   - $roles = [
 *       ['key'=>'owner','name'=>'Owner','desc'=>'...', 'count'=>N],
 *       ...
 *     ]
 *   - $flash, $error
 */
$h     = fn($s)=>htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
$slug  = (string)($slug ?? '');
$csrf  = (string)($csrf ?? '');
$roles = is_array($roles ?? null) ? $roles : [
  ['key'=>'owner',   'name'=>'Owner',   'desc'=>'Full access to everything.'],
  ['key'=>'manager', 'name'=>'Manager', 'desc'=>'Manage users, settings, and assigned modules.'],
  ['key'=>'employee','name'=>'Employee','desc'=>'Standard access to assigned modules.'],
  ['key'=>'viewer',  'name'=>'Viewer',  'desc'=>'Read-only access where allowed.'],
];
$flash = (string)($flash ?? '');
$error = (string)($error ?? '');
$totalUsers = array_sum(array_map(static fn($r)=> (int)($r['count'] ?? 0), $roles));
?>
<div class="max-w-4xl mx-auto px-4 py-6">
  <div class="flex items-center justify-between mb-6">
    <div>
      <h1 class="text-2xl font-semibold">Roles</h1>
      <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
        <?= $h($totalUsers) ?> user<?= $totalUsers===1?'':'s' ?> across <?= count($roles) ?> role<?= count($roles)===1?'':'s' ?>.
      </p>
    </div>
    <a href="/t/<?= $h($slug) ?>/users" class="text-sm text-gray-600 dark:text-gray-400 hover:underline">&larr; Back to Users</a>
  </div>

  <?php if ($flash): ?>
    <div class="mb-4 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-emerald-800 text-sm"><?= nl2br($h($flash)) ?></div>
  <?php endif; ?>
  <?php if ($error): ?>
    <div class="mb-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-red-800 text-sm"><?= nl2br($h($error)) ?></div>
  <?php endif; ?>

  <div class="overflow-auto border border-gray-200 dark:border-gray-700 rounded-xl">
    <table class="min-w-full text-sm">
      <thead class="bg-gray-50 dark:bg-gray-800/60">
        <tr>
          <th class="text-left p-3">Role</th>
          <th class="text-left p-3">Key</th>
          <th class="text-left p-3">Description</th>
          <th class="text-left p-3">Users</th>
          <th class="text-left p-3"></th>
        </tr>
      </thead>
      <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
        <?php foreach ($roles as $r):
          $name = (string)($r['name'] ?? ucfirst((string)($r['key'] ?? 'role')));
          $key  = (string)($r['key']  ?? strtolower($name));
          $desc = (string)($r['desc'] ?? '');
          $cnt  = (int)($r['count'] ?? 0);
        ?>
          <tr>
            <td class="p-3 font-medium"><?= $h($name) ?></td>
            <td class="p-3">
              <code class="text-xs bg-gray-100 dark:bg-gray-800 rounded px-2 py-1"><?= $h($key) ?></code>
            </td>
            <td class="p-3 text-gray-600 dark:text-gray-400"><?= $h($desc) ?></td>
            <td class="p-3">
              <span class="inline-flex items-center text-xs px-2 py-1 rounded-full <?= $cnt>0?'bg-emerald-100 text-emerald-700':'bg-gray-200 text-gray-600' ?>">
                <?= $h((string)$cnt) ?>
              </span>
            </td>
            <td class="p-3 text-right whitespace-nowrap">
              <!-- Placeholder actions for future per-role management -->
              <a class="text-brand hover:underline hidden" href="#">Manage</a>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
      <tfoot class="bg-gray-50/60 dark:bg-gray-800/40">
        <tr>
          <td class="p-3 font-medium">Total</td>
          <td class="p-3"></td>
          <td class="p-3"></td>
          <td class="p-3">
            <span class="inline-flex items-center text-xs px-2 py-1 rounded-full bg-emerald-100 text-emerald-700">
              <?= $h((string)$totalUsers) ?>
            </span>
          </td>
          <td class="p-3"></td>
        </tr>
      </tfoot>
    </table>
  </div>

  <p class="text-xs text-gray-500 mt-3">
    Note: Fine-grained, per-module permissions can be layered on top of these roles later.
    DMS, POS, and HotelFlow ship their own side navigation; visibility follows the user’s role and the org’s enabled modules.
  </p>
</div>