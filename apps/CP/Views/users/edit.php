<?php
declare(strict_types=1);
/**
 * cp/users/edit.php — Edit Control Panel User
 * Expects:
 *   - $user: array (id, email, username, mobile, name, role, is_active)
 *   - $old:  array|null (optional from session)
 *   - $csrf: string (token)
 *   - $brandColor: string (optional, default green)
 */
$h = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
$brandColor = $brandColor ?? '#228B22';
$old = is_array($old ?? null) ? $old : [];
$user = is_array($user ?? null) ? $user : [];
$val = function(string $k) use ($old, $user) {
    return $old[$k] ?? $user[$k] ?? '';
};
$isActive = isset($old['is_active'])
    ? (int)$old['is_active'] === 1
    : ((int)($user['is_active'] ?? 1) === 1);
?>

<div class="max-w-3xl mx-auto px-4 py-6 space-y-6">
  <div class="flex items-center justify-between">
    <div>
      <h1 class="text-2xl font-semibold">Edit CP User</h1>
      <p class="text-sm text-gray-500 dark:text-gray-400">
        Update user details. Leave password fields blank to keep unchanged.
      </p>
    </div>
    <a href="/cp/users"
       class="px-4 py-2 rounded-xl border border-gray-200 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-700 text-sm">
       &larr; Back
    </a>
  </div>

  <?php if (!empty($error)): ?>
    <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-red-800 text-sm"><?= nl2br($h($error)) ?></div>
  <?php endif; ?>
  <?php if (!empty($flash)): ?>
    <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-emerald-800 text-sm"><?= nl2br($h($flash)) ?></div>
  <?php endif; ?>

  <form method="post"
        action="/cp/users/<?= (int)($user['id'] ?? 0) ?>"
        class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-2xl p-6 space-y-6"
        autocomplete="off">
    <input type="hidden" name="_csrf" value="<?= $h($csrf ?? '') ?>">
    <input type="hidden" name="_method" value="PUT">

    <!-- Core fields -->
    <div class="grid md:grid-cols-2 gap-4">
      <div>
        <label for="name" class="block text-sm font-medium mb-1">Full Name</label>
        <input id="name" name="name" required
               value="<?= $h($val('name')) ?>"
               class="w-full rounded-lg border border-gray-300 dark:border-gray-700 dark:bg-gray-900 px-3 py-2">
      </div>

      <div>
        <label for="role" class="block text-sm font-medium mb-1">Role</label>
        <select id="role" name="role"
                class="w-full rounded-lg border border-gray-300 dark:border-gray-700 dark:bg-gray-900 px-3 py-2">
          <?php foreach (['superadmin','admin','staff'] as $r): ?>
            <option value="<?= $h($r) ?>" <?= ($val('role') === $r) ? 'selected' : '' ?>>
              <?= ucfirst($h($r)) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div>
        <label for="email" class="block text-sm font-medium mb-1">Email</label>
        <input id="email" type="email" name="email" required
               value="<?= $h($val('email')) ?>"
               class="w-full rounded-lg border border-gray-300 dark:border-gray-700 dark:bg-gray-900 px-3 py-2">
      </div>

      <div>
        <label for="username" class="block text-sm font-medium mb-1">Username (optional)</label>
        <input id="username" name="username"
               value="<?= $h($val('username')) ?>"
               class="w-full rounded-lg border border-gray-300 dark:border-gray-700 dark:bg-gray-900 px-3 py-2">
      </div>

      <div>
        <label for="mobile" class="block text-sm font-medium mb-1">Mobile (optional)</label>
        <input id="mobile" name="mobile" inputmode="numeric"
               value="<?= $h($val('mobile')) ?>"
               placeholder="01XXXXXXXXX"
               class="w-full rounded-lg border border-gray-300 dark:border-gray-700 dark:bg-gray-900 px-3 py-2">
      </div>

      <div class="flex items-center gap-2 pt-5">
        <input id="active" type="checkbox" name="is_active" value="1"
               class="rounded border-gray-300 dark:border-gray-600"
               <?= $isActive ? 'checked' : '' ?>>
        <label for="active" class="text-sm">Active</label>
      </div>
    </div>

    <!-- Password update -->
    <div class="grid md:grid-cols-2 gap-4 pt-2">
      <div>
        <label for="password" class="block text-sm font-medium mb-1">New Password (optional)</label>
        <input id="password" type="password" name="password"
               placeholder="leave blank to keep"
               class="w-full rounded-lg border border-gray-300 dark:border-gray-700 dark:bg-gray-900 px-3 py-2">
      </div>
      <div>
        <label for="password_confirm" class="block text-sm font-medium mb-1">Confirm New Password</label>
        <input id="password_confirm" type="password" name="password_confirm"
               placeholder="leave blank to keep"
               class="w-full rounded-lg border border-gray-300 dark:border-gray-700 dark:bg-gray-900 px-3 py-2">
      </div>
    </div>

    <!-- Actions -->
    <div class="pt-2 flex items-center gap-3">
      <button class="px-5 py-2.5 rounded-xl text-white font-semibold shadow"
              style="background: <?= $h($brandColor) ?>;">
        <i class="fa-solid fa-floppy-disk mr-1"></i> Save Changes
      </button>
      <a href="/cp/users/<?= (int)($user['id'] ?? 0) ?>"
         class="px-4 py-2 rounded-xl border border-gray-200 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-700">
         View
      </a>
    </div>
  </form>
</div>