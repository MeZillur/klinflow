<?php
/** @var array  $user */
/** @var array  $flash */
/** @var string $csrf */
/** @var string $module_base */
/** @var string $avatar_url */

$initials  = strtoupper(substr((string)($user['name'] ?? 'Me'), 0, 2));
$hasAvatar = !empty($avatar_url);
?>
<div class="p-6 space-y-6">

  <!-- Flash -->
  <?php if (!empty($flash['ok'])): ?>
    <div class="rounded-lg bg-emerald-50 text-emerald-800 p-3 text-sm flex items-center gap-2">
      <i class="fa-solid fa-circle-check"></i>
      <span><?= htmlspecialchars($flash['ok'], ENT_QUOTES, 'UTF-8') ?></span>
    </div>
  <?php endif; ?>

  <?php if (!empty($flash['err'])): ?>
    <div class="rounded-lg bg-rose-50 text-rose-800 p-3 text-sm flex items-center gap-2">
      <i class="fa-solid fa-triangle-exclamation"></i>
      <span><?= htmlspecialchars($flash['err'], ENT_QUOTES, 'UTF-8') ?></span>
    </div>
  <?php endif; ?>

  <div class="flex items-center justify-between">
    <h1 class="text-2xl font-bold">My Profile</h1>
    <a href="<?= htmlspecialchars($module_base, ENT_QUOTES, 'UTF-8') ?>"
       class="text-sm inline-flex items-center gap-2 px-3 py-2 rounded-lg bg-slate-800 text-white hover:bg-slate-700">
      <i class="fa-solid fa-grid-2"></i>
      <span>Back to Apps</span>
    </a>
  </div>

  <div class="grid md:grid-cols-3 gap-6">
    <!-- Avatar -->
    <div class="md:col-span-1">
      <div class="bg-white dark:bg-gray-900 rounded-2xl shadow p-5 space-y-4">
        <div class="flex items-center gap-4">
          <?php if ($hasAvatar): ?>
            <img src="<?= htmlspecialchars($avatar_url, ENT_QUOTES, 'UTF-8') ?>"
                 alt="Avatar"
                 class="h-16 w-16 rounded-full object-cover border border-slate-200 shadow-sm">
          <?php else: ?>
            <div class="h-16 w-16 rounded-xl bg-emerald-600 text-white grid place-items-center font-bold text-xl">
              <?= htmlspecialchars($initials, ENT_QUOTES, 'UTF-8') ?>
            </div>
          <?php endif; ?>

          <div>
            <div class="font-semibold">
              <?= htmlspecialchars((string)($user['name'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
            </div>
            <div class="text-xs text-slate-500">
              <?= htmlspecialchars((string)($user['username'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
            </div>
          </div>
        </div>

        <form action="/me/avatar" method="post" enctype="multipart/form-data" class="mt-4 space-y-3">
          <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
          <div>
            <label class="text-sm mb-2 block">Change avatar</label>
            <input type="file" name="avatar" accept="image/*"
                   class="block w-full text-sm
                          file:mr-3 file:py-2 file:px-3
                          file:rounded-lg file:border-0
                          file:bg-emerald-600 file:text-white
                          hover:file:bg-emerald-700"/>
            <p class="text-xs text-slate-500 mt-2">PNG/JPG up to 2MB. Upload starts when you click Save.</p>
          </div>
          <button type="submit"
                  class="inline-flex items-center gap-2 px-3 py-2 rounded-lg bg-emerald-600 text-white text-sm hover:bg-emerald-700">
            <i class="fa-solid fa-upload"></i>
            <span>Save Avatar</span>
          </button>
        </form>
      </div>
    </div>

    <!-- Profile + Password -->
    <div class="md:col-span-2 space-y-6">
      <!-- Profile form -->
      <div class="bg-white dark:bg-gray-900 rounded-2xl shadow p-5">
        <h2 class="font-semibold mb-4">Profile Information</h2>
        <form action="/me/profile" method="post" class="grid md:grid-cols-2 gap-4">
          <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">

          <div>
            <label class="block text-sm text-slate-600 mb-1">Full name</label>
            <input name="name"
                   value="<?= htmlspecialchars((string)($user['name'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                   class="w-full rounded-lg border px-3 py-2 focus:outline-none focus:ring-2 focus:ring-emerald-500">
          </div>

          <div>
            <label class="block text-sm text-slate-600 mb-1">Email</label>
            <input name="email" type="email"
                   value="<?= htmlspecialchars((string)($user['email'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                   class="w-full rounded-lg border px-3 py-2 focus:outline-none focus:ring-2 focus:ring-emerald-500">
          </div>

          <div>
            <label class="block text-sm text-slate-600 mb-1">Phone</label>
            <input name="phone"
                   value="<?= htmlspecialchars((string)($user['phone'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                   class="w-full rounded-lg border px-3 py-2 focus:outline-none focus:ring-2 focus:ring-emerald-500">
          </div>

          <div>
            <label class="block text-sm text-slate-600 mb-1">Timezone</label>
            <input name="timezone"
                   placeholder="e.g., Asia/Dhaka"
                   value="<?= htmlspecialchars((string)($user['timezone'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                   class="w-full rounded-lg border px-3 py-2 focus:outline-none focus:ring-2 focus:ring-emerald-500">
          </div>

          <div class="md:col-span-2">
            <button type="submit"
                    class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-slate-900 text-white hover:bg-slate-800">
              <i class="fa-solid fa-floppy-disk"></i>
              <span>Save Changes</span>
            </button>
          </div>
        </form>
      </div>

      <!-- Password form -->
      <div class="bg-white dark:bg-gray-900 rounded-2xl shadow p-5">
        <h2 class="font-semibold mb-4">Change Password</h2>
        <form action="/me/password" method="post" class="grid md:grid-cols-3 gap-4">
          <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">

          <div>
            <label class="block text-sm text-slate-600 mb-1">Current password</label>
            <input name="current_password" type="password"
                   class="w-full rounded-lg border px-3 py-2 focus:outline-none focus:ring-2 focus:ring-emerald-500">
          </div>

          <div>
            <label class="block text-sm text-slate-600 mb-1">New password</label>
            <input name="new_password" type="password"
                   class="w-full rounded-lg border px-3 py-2 focus:outline-none focus:ring-2 focus:ring-emerald-500">
          </div>

          <div>
            <label class="block text-sm text-slate-600 mb-1">Confirm new password</label>
            <input name="confirm_password" type="password"
                   class="w-full rounded-lg border px-3 py-2 focus:outline-none focus:ring-2 focus:ring-emerald-500">
          </div>

          <div class="md:col-span-3">
            <button type="submit"
                    class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-emerald-600 text-white hover:bg-emerald-700">
              <i class="fa-solid fa-key"></i>
              <span>Update Password</span>
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- Small helper section -->
  <div class="mt-6 text-xs text-slate-500 border-t pt-4">
    <strong>How to use this page:</strong>
    Update your profile info or password, then click the save buttons.  
    After a successful save, you’ll be taken back to your app dashboard with a green success message.
  </div>
</div>