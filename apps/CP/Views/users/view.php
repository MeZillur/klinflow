<?php
declare(strict_types=1);
/**
 * cp/users/view.php — CP User Details
 *
 * Expects:
 *   - $user: array (id,email,username,mobile,name,role,is_active,is_superadmin,created_at,updated_at,last_login_at)
 * Optional:
 *   - $csrf: string (for delete form)
 *
 * Notes:
 *   - Hides the Delete button for self and for superadmins.
 *   - Adds accessible markup and dark-mode styles.
 */
$h         = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
$brandColor= $brandColor ?? '#228B22';
$csrf      = $csrf ?? '';

// Best-effort safety: prevent deleting yourself or a superadmin
$selfId     = (int)($_SESSION['cp_user']['id'] ?? 0);
$targetId   = (int)($user['id'] ?? 0);
$isSelf     = $selfId > 0 && $selfId === $targetId;
$isSuper    = (int)($user['is_superadmin'] ?? 0) === 1;
$canDelete  = !$isSelf && !$isSuper && $targetId > 0;

// Presentable fields
$name       = $h($user['name']         ?? '—');
$email      = $h($user['email']        ?? '—');
$username   = $h($user['username']     ?? '—');
$mobile     = $h($user['mobile']       ?? '—');
$role       = $h($user['role']         ?? 'staff');
$created    = $h($user['created_at']   ?? '—');
$updated    = $h($user['updated_at']   ?? '—');
$lastLogin  = $h($user['last_login_at']?? '—');
$isActive   = (int)($user['is_active'] ?? 0) === 1;
?>
<div class="max-w-3xl mx-auto space-y-6 px-4 py-6">
  <div class="flex items-center justify-between">
    <div>
      <h1 class="text-2xl font-semibold">User Details</h1>
      <p class="text-sm text-gray-500 dark:text-gray-400">Full profile &amp; activity information.</p>
    </div>
    <div class="flex items-center gap-2">
      <a href="/cp/users/<?= (int)$targetId ?>/edit"
         class="px-4 py-2 rounded-xl text-white font-medium shadow"
         style="background: <?= $h($brandColor) ?>;">
        <i class="fa-regular fa-pen-to-square mr-1"></i> Edit
      </a>
      <a href="/cp/users"
         class="px-4 py-2 rounded-xl border border-gray-200 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-700">
        Back
      </a>
    </div>
  </div>

  <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-2xl">
    <div class="p-6 grid md:grid-cols-2 gap-6">
      <div>
        <div class="text-sm text-gray-500 dark:text-gray-400">Name</div>
        <div class="font-medium"><?= $name ?></div>
      </div>
      <div>
        <div class="text-sm text-gray-500 dark:text-gray-400">Email</div>
        <div class="font-medium"><?= $email ?></div>
      </div>

      <div>
        <div class="text-sm text-gray-500 dark:text-gray-400">Username</div>
        <div class="font-medium"><?= $username !== '' ? $username : '—' ?></div>
      </div>
      <div>
        <div class="text-sm text-gray-500 dark:text-gray-400">Mobile</div>
        <div class="font-medium"><?= $mobile !== '' ? $mobile : '—' ?></div>
      </div>

      <div>
        <div class="text-sm text-gray-500 dark:text-gray-400">Role</div>
        <div class="flex items-center gap-2">
          <span class="px-2 py-1 rounded-full text-xs"
                style="background: rgba(34,139,34,.12); color: <?= $h($brandColor) ?>;">
            <?= $role ?>
          </span>
          <?php if ($isSuper): ?>
            <span class="px-2 py-1 rounded-full text-xs bg-purple-100 text-purple-700">Superadmin</span>
          <?php endif; ?>
        </div>
      </div>

      <div>
        <div class="text-sm text-gray-500 dark:text-gray-400">Status</div>
        <div>
          <?php if ($isActive): ?>
            <span class="px-2 py-1 rounded-full text-xs bg-emerald-100 text-emerald-700">Active</span>
          <?php else: ?>
            <span class="px-2 py-1 rounded-full text-xs bg-gray-200 text-gray-700">Inactive</span>
          <?php endif; ?>
        </div>
      </div>

      <div>
        <div class="text-sm text-gray-500 dark:text-gray-400">Created</div>
        <div class="font-medium text-sm"><?= $created ?></div>
      </div>
      <div>
        <div class="text-sm text-gray-500 dark:text-gray-400">Updated</div>
        <div class="font-medium text-sm"><?= $updated ?></div>
      </div>
      <div>
        <div class="text-sm text-gray-500 dark:text-gray-400">Last Login</div>
        <div class="font-medium text-sm"><?= $lastLogin ?></div>
      </div>
    </div>

    <div class="px-6 pb-6 flex items-center justify-end gap-2">
      <?php if ($canDelete): ?>
        <form method="post"
              action="/cp/users/<?= (int)$targetId ?>/delete"
              onsubmit="return confirm('Delete this user? This action cannot be undone.');"
              class="inline">
          <?= function_exists('\\Shared\\Csrf::field')
               ? \Shared\Csrf::field()
               : '<input type="hidden" name="_csrf" value="'. $h($csrf) .'">' ?>
          <!-- If your router supports method override, uncomment the next line -->
          <!-- <input type="hidden" name="_method" value="DELETE"> -->
          <button type="submit" class="px-4 py-2 rounded-xl text-red-600 hover:bg-red-50 dark:hover:bg-red-900/10">
            <i class="fa-regular fa-trash-can mr-1"></i> Delete
          </button>
        </form>
      <?php elseif ($isSelf): ?>
        <span class="text-xs text-gray-500 dark:text-gray-400">You cannot delete your own account.</span>
      <?php elseif ($isSuper): ?>
        <span class="text-xs text-gray-500 dark:text-gray-400">Superadmin accounts cannot be deleted.</span>
      <?php endif; ?>
    </div>
  </div>
</div>