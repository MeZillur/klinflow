<?php
declare(strict_types=1);
/**
 * cp/users/create.php  — Create CP User (refined, drop-in)
 * Expects:
 *   - $csrf  (string)
 *   - $old   (array) from session: ['name','email','username','mobile','role','active']
 *   - $error (string|null)
 */
$h     = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
$old   = is_array($old ?? null) ? $old : [];
$error = $error ?? null;

// sensible defaults
$old['role']   = $old['role']   ?? 'staff';
$old['active'] = isset($old['active']) ? (int)$old['active'] : 1;
?>
<div class="max-w-2xl mx-auto px-4 py-6">
  <a href="/cp/users" class="text-sm text-gray-600 dark:text-gray-400 hover:underline">&larr; Back</a>
  <h1 class="mt-2 text-2xl font-semibold">New CP User</h1>
  <p class="text-sm text-gray-500 dark:text-gray-400 mb-4">Create a control panel user. Email must be unique.</p>

  <?php if (!empty($error)): ?>
    <div class="mb-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-red-800 text-sm">
      <?= nl2br($h($error)) ?>
    </div>
  <?php endif; ?>

  <form method="post" action="/cp/users" class="space-y-5" autocomplete="off">
    <input type="hidden" name="_csrf" value="<?= $h($csrf ?? '') ?>">

    <!-- Name -->
    <div>
      <label for="name" class="block text-sm font-medium mb-1">Full Name</label>
      <input id="name" name="name" required
             class="w-full border rounded-lg px-3 py-2 dark:bg-gray-900 dark:border-gray-700"
             value="<?= $h($old['name'] ?? '') ?>">
    </div>

    <!-- Role & Email -->
    <div class="grid sm:grid-cols-2 gap-4">
      <div>
        <label for="role" class="block text-sm font-medium mb-1">Role</label>
        <select id="role" name="role"
                class="w-full border rounded-lg px-3 py-2 dark:bg-gray-900 dark:border-gray-700">
          <?php foreach (['superadmin','admin','staff'] as $r): ?>
            <option value="<?= $r ?>" <?= ($old['role'] === $r) ? 'selected' : '' ?>><?= ucfirst($r) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div>
        <label for="email" class="block text-sm font-medium mb-1">Email</label>
        <input id="email" type="email" name="email" required
               class="w-full border rounded-lg px-3 py-2 dark:bg-gray-900 dark:border-gray-700"
               value="<?= $h($old['email'] ?? '') ?>">
      </div>
    </div>

    <!-- Username & Mobile -->
    <div class="grid sm:grid-cols-2 gap-4">
      <div>
        <label for="username" class="block text-sm font-medium mb-1">Username (optional)</label>
        <input id="username" name="username"
               class="w-full border rounded-lg px-3 py-2 dark:bg-gray-900 dark:border-gray-700"
               value="<?= $h($old['username'] ?? '') ?>">
        <p class="text-xs text-gray-500 mt-1">Leave blank to use the part before “@” of the email.</p>
      </div>
      <div>
        <label for="mobile" class="block text-sm font-medium mb-1">Mobile (optional)</label>
        <input id="mobile" name="mobile" inputmode="numeric" pattern="[0-9\s+-]*" placeholder="01XXXXXXXXX"
               class="w-full border rounded-lg px-3 py-2 dark:bg-gray-900 dark:border-gray-700"
               value="<?= $h($old['mobile'] ?? '') ?>">
      </div>
    </div>

    <!-- Active -->
    <label class="inline-flex items-center gap-2 text-sm select-none">
      <input type="checkbox" name="is_active" value="1" <?= ((int)$old['active'] === 1) ? 'checked' : '' ?>>
      Active
    </label>

    <!-- Passwords -->
    <div class="grid sm:grid-cols-2 gap-4">
      <div>
        <label for="password" class="block text-sm font-medium mb-1">Password</label>
        <input id="password" type="password" name="password" required minlength="8" autocomplete="new-password"
               class="w-full border rounded-lg px-3 py-2 dark:bg-gray-900 dark:border-gray-700">
        <p class="text-xs text-gray-500 mt-1">At least 8 characters.</p>
      </div>
      <div>
        <label for="password_confirm" class="block text-sm font-medium mb-1">Confirm Password</label>
        <input id="password_confirm" type="password" name="password_confirm" required autocomplete="new-password"
               class="w-full border rounded-lg px-3 py-2 dark:bg-gray-900 dark:border-gray-700">
      </div>
    </div>

    <button class="btn btn-brand text-white rounded-lg px-4 py-2">
      <i class="fa-solid fa-floppy-disk mr-1"></i>
      Create User
    </button>
  </form>
</div>