<?php
declare(strict_types=1);

/**
 * Tenant › Organization Settings (view)
 * Renders inside tenant shell layout.
 *
 * Expects:
 * - $org    : array (id, name, slug, timezone, country, status, meta[address|phone|email], logo_url?)
 * - $slug   : string
 * - $csrf   : string
 * - $flash  : ?string
 * - $error  : ?string
 */

$h = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');

$org     = $org ?? [];
$slug    = $slug ?? ($org['slug'] ?? '');
$meta    = (array)($org['meta'] ?? []);
$address = (string)($meta['address'] ?? '');
$phone   = (string)($meta['phone']   ?? '');
$email   = (string)($meta['email']   ?? '');
$logoUrl = (string)($org['logo_url'] ?? ''); // already cache-busted by controller
?>
<div class="max-w-5xl">
  <div class="flex items-center justify-between mb-6">
    <h1 class="text-2xl font-semibold">Organization Settings</h1>
  </div>

  <?php if (!empty($flash)): ?>
    <div class="mb-4 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-emerald-800 text-sm" role="status">
      <?= $h($flash) ?>
    </div>
  <?php endif; ?>
  <?php if (!empty($error)): ?>
    <div class="mb-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-red-800 text-sm" role="alert">
      <?= $h($error) ?>
    </div>
  <?php endif; ?>

  <form method="post"
        action="/t/<?= $h($slug) ?>/settings"
        enctype="multipart/form-data"
        class="space-y-8"
        novalidate>
    <input type="hidden" name="_csrf" value="<?= $h($csrf ?? '') ?>">

    <!-- Profile -->
    <section class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-5">
      <h2 class="font-semibold mb-4">Profile</h2>

      <div class="grid sm:grid-cols-2 gap-4">
        <label class="block">
          <span class="text-sm text-gray-600">Organization name</span>
          <input name="name"
                 value="<?= $h($org['name'] ?? '') ?>"
                 class="mt-1 w-full rounded-lg border border-gray-300 bg-white px-3 py-2 dark:bg-gray-900 dark:border-gray-700"
                 autocomplete="organization"
                 required>
        </label>

        <label class="block">
          <span class="text-sm text-gray-600">Slug</span>
          <input value="<?= $h($org['slug'] ?? '') ?>"
                 class="mt-1 w-full rounded-lg border border-gray-300 bg-gray-50 px-3 py-2 dark:bg-gray-900 dark:border-gray-700"
                 disabled>
        </label>

        <label class="block">
          <span class="text-sm text-gray-600">Timezone</span>
          <input name="timezone"
                 value="<?= $h($org['timezone'] ?? '') ?>"
                 placeholder="e.g. Asia/Dhaka"
                 class="mt-1 w-full rounded-lg border border-gray-300 bg-white px-3 py-2 dark:bg-gray-900 dark:border-gray-700"
                 inputmode="text" spellcheck="false" autocapitalize="none">
        </label>

        <label class="block">
          <span class="text-sm text-gray-600">Country</span>
          <input name="country"
                 value="<?= $h($org['country'] ?? '') ?>"
                 class="mt-1 w-full rounded-lg border border-gray-300 bg-white px-3 py-2 dark:bg-gray-900 dark:border-gray-700"
                 inputmode="text" spellcheck="false" autocapitalize="none">
        </label>
      </div>
    </section>

    <!-- Organization Identity -->
    <section class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-5">
      <h2 class="font-semibold mb-4">Organization Identity</h2>

      <div class="grid sm:grid-cols-2 gap-4">
        <label class="block sm:col-span-2">
          <span class="text-sm text-gray-600">Address</span>
          <textarea name="address" rows="3"
                    class="mt-1 w-full rounded-lg border border-gray-300 bg-white px-3 py-2 dark:bg-gray-900 dark:border-gray-700"
                    placeholder="Street, City, ZIP / Postcode, State/Province"><?= $h($address) ?></textarea>
        </label>

        <label class="block">
          <span class="text-sm text-gray-600">Phone</span>
          <input name="phone" value="<?= $h($phone) ?>"
                 class="mt-1 w-full rounded-lg border border-gray-300 bg-white px-3 py-2 dark:bg-gray-900 dark:border-gray-700"
                 inputmode="tel" autocomplete="tel">
        </label>

        <label class="block">
          <span class="text-sm text-gray-600">Email</span>
          <input name="email" value="<?= $h($email) ?>"
                 class="mt-1 w-full rounded-lg border border-gray-300 bg-white px-3 py-2 dark:bg-gray-900 dark:border-gray-700"
                 inputmode="email" autocomplete="email">
        </label>
      </div>

      <p class="text-xs text-gray-500 mt-2">
        These details appear on printed/emailed documents (invoices, orders, purchase orders) and headers where applicable.
      </p>
    </section>

    <!-- Logo -->
    <section class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-5">
      <h2 class="font-semibold mb-4">Logo</h2>

      <div class="flex items-center gap-4">
        <?php if ($logoUrl): ?>
          <img src="<?= $h($logoUrl) ?>" alt="Current organization logo"
               class="h-12 w-auto rounded border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900">
          <span class="text-xs text-gray-500">Current</span>
        <?php else: ?>
          <span class="text-xs text-gray-500">No logo uploaded yet</span>
        <?php endif; ?>
      </div>

      <div class="mt-3">
        <input type="file" name="logo" accept=".png,.jpg,.jpeg,.webp" class="block">
        <p class="text-xs text-gray-500 mt-2">
          PNG / JPG / WebP · Max ~1.5 MB · Uploading a new file replaces the existing logo.
        </p>
      </div>
    </section>

    <!-- Status (read-only) -->
    <section class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-5">
      <h2 class="font-semibold mb-2">Status</h2>
      <p class="text-sm">
        Current:
        <span class="font-medium"><?= $h(ucfirst($org['status'] ?? 'active')) ?></span>
      </p>
    </section>

    <button class="inline-flex items-center gap-2 rounded-lg px-4 py-2 text-white font-semibold shadow"
            style="background: var(--brand, #228B22)">
      Save Changes
    </button>
  </form>
</div>