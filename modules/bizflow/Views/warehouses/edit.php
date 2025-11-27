<?php
/**
 * Edit warehouse
 *
 * Expects:
 * - array  $org
 * - string $module_base
 * - array  $types
 * - array  $warehouse
 * - array  $errors
 */

$h    = $h ?? fn($s)=>htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
$base = rtrim($module_base ?? '/apps/bizflow', '/');
$orgName = trim((string)($org['name'] ?? ''));

$id   = (int)($warehouse['id'] ?? 0);
?>
<div class="max-w-4xl mx-auto px-4 py-6 space-y-6">

  <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
    <div>
      <h1 class="text-2xl sm:text-3xl font-bold tracking-tight">
        Edit warehouse <?= $h($warehouse['code'] ?? '') ?>
      </h1>
      <p class="mt-1 text-sm text-slate-600">
        Update details for <?= $h($warehouse['name'] ?? '') ?> in
        <?= $orgName !== '' ? $h($orgName) : 'this organisation' ?>.
      </p>
    </div>

    <div class="flex flex-wrap items-center gap-1 justify-end text-xs sm:text-sm">
      <a href="<?= $h($base . '/warehouse') ?>" class="px-3 py-1.5 rounded-md border border-slate-200 hover:bg-slate-50">Warehouses list</a>
      <a href="<?= $h($base . '/warehouse/' . $id) ?>" class="px-3 py-1.5 rounded-md border border-slate-200 hover:bg-slate-50">View profile</a>
    </div>
  </div>

  <?php if (!empty($errors)): ?>
    <div class="border border-red-200 bg-red-50 text-red-800 rounded-md px-4 py-3 text-sm">
      <p class="font-semibold mb-1">Please fix the following:</p>
      <ul class="list-disc pl-5 space-y-1">
        <?php foreach ($errors as $err): ?>
          <li><?= $h($err) ?></li>
        <?php endforeach; ?>
      </ul>
    </div>
  <?php endif; ?>

  <section class="bg-white border border-slate-200 rounded-lg shadow-sm">
    <form action="<?= $h($base . '/warehouse/' . $id) ?>" method="post" class="px-4 py-5 space-y-5">
      <!-- Basic -->
      <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <div class="sm:col-span-2">
          <label class="block text-sm font-medium text-slate-700 mb-1">
            Name <span class="text-red-500">*</span>
          </label>
          <input type="text" name="name" required
                 value="<?= $h($warehouse['name'] ?? '') ?>"
                 class="block w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-emerald-600 focus:ring-emerald-600">
        </div>
        <div>
          <label class="block text-sm font-medium text-slate-700 mb-1">
            Code <span class="text-red-500">*</span>
          </label>
          <input type="text" name="code"
                 value="<?= $h($warehouse['code'] ?? '') ?>"
                 class="block w-full rounded-md border border-slate-300 px-3 py-2 text-sm uppercase focus:border-emerald-600 focus:ring-emerald-600">
        </div>
      </div>

      <!-- Type + flags -->
      <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <div>
          <label class="block text-sm font-medium text-slate-700 mb-1">Type</label>
          <select name="type"
                  class="block w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-emerald-600 focus:ring-emerald-600">
            <?php
            $currentType = $warehouse['type'] ?? 'warehouse';
            foreach ($types as $key => $label): ?>
              <option value="<?= $h($key) ?>" <?= $currentType === $key ? 'selected' : '' ?>>
                <?= $h($label) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="flex items-center gap-3">
          <label class="inline-flex items-center gap-2 text-sm text-slate-700">
            <input type="checkbox" name="is_primary" value="1"
              <?= !empty($warehouse['is_primary']) ? 'checked' : '' ?>
              class="rounded border-slate-300 text-emerald-600 focus:ring-emerald-600">
            <span>Primary warehouse</span>
          </label>
        </div>
        <div class="flex items-center gap-3">
          <label class="inline-flex items-center gap-2 text-sm text-slate-700">
            <input type="checkbox" name="is_active" value="1"
              <?= !empty($warehouse['is_active']) ? 'checked' : '' ?>
              class="rounded border-slate-300 text-emerald-600 focus:ring-emerald-600">
            <span>Active</span>
          </label>
        </div>
      </div>

      <!-- Contact -->
      <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <div>
          <label class="block text-sm font-medium text-slate-700 mb-1">Contact person</label>
          <input type="text" name="contact_name"
                 value="<?= $h($warehouse['contact_name'] ?? '') ?>"
                 class="block w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-emerald-600 focus:ring-emerald-600">
        </div>
        <div>
          <label class="block text-sm font-medium text-slate-700 mb-1">Phone</label>
          <input type="text" name="phone"
                 value="<?= $h($warehouse['phone'] ?? '') ?>"
                 class="block w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-emerald-600 focus:ring-emerald-600">
        </div>
        <div>
          <label class="block text-sm font-medium text-slate-700 mb-1">Email</label>
          <input type="email" name="email"
                 value="<?= $h($warehouse['email'] ?? '') ?>"
                 class="block w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-emerald-600 focus:ring-emerald-600">
        </div>
      </div>

      <!-- Address -->
      <div class="space-y-3">
        <div>
          <label class="block text-sm font-medium text-slate-700 mb-1">Address line 1</label>
          <input type="text" name="address_line1"
                 value="<?= $h($warehouse['address_line1'] ?? '') ?>"
                 class="block w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-emerald-600 focus:ring-emerald-600">
        </div>
        <div>
          <label class="block text-sm font-medium text-slate-700 mb-1">Address line 2</label>
          <input type="text" name="address_line2"
                 value="<?= $h($warehouse['address_line2'] ?? '') ?>"
                 class="block w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-emerald-600 focus:ring-emerald-600">
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
          <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">City</label>
            <input type="text" name="city"
                   value="<?= $h($warehouse['city'] ?? '') ?>"
                   class="block w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-emerald-600 focus:ring-emerald-600">
          </div>
          <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">District</label>
            <input type="text" name="district"
                   value="<?= $h($warehouse['district'] ?? '') ?>"
                   class="block w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-emerald-600 focus:ring-emerald-600">
          </div>
          <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Postcode</label>
            <input type="text" name="postcode"
                   value="<?= $h($warehouse['postcode'] ?? '') ?>"
                   class="block w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-emerald-600 focus:ring-emerald-600">
          </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
          <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">State / Division</label>
            <input type="text" name="state"
                   value="<?= $h($warehouse['state'] ?? '') ?>"
                   class="block w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-emerald-600 focus:ring-emerald-600">
          </div>
          <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Country</label>
            <input type="text" name="country"
                   value="<?= $h($warehouse['country'] ?? 'Bangladesh') ?>"
                   class="block w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-emerald-600 focus:ring-emerald-600">
          </div>
          <div class="grid grid-cols-2 gap-2">
            <div>
              <label class="block text-sm font-medium text-slate-700 mb-1">Lat</label>
              <input type="text" name="lat"
                     value="<?= $h($warehouse['lat'] ?? '') ?>"
                     class="block w-full rounded-md border border-slate-300 px-2 py-2 text-xs focus:border-emerald-600 focus:ring-emerald-600">
            </div>
            <div>
              <label class="block text-sm font-medium text-slate-700 mb-1">Lng</label>
              <input type="text" name="lng"
                     value="<?= $h($warehouse['lng'] ?? '') ?>"
                     class="block w-full rounded-md border border-slate-300 px-2 py-2 text-xs focus:border-emerald-600 focus:ring-emerald-600">
            </div>
          </div>
        </div>
      </div>

      <!-- Notes -->
      <div>
        <label class="block text-sm font-medium text-slate-700 mb-1">Internal notes</label>
        <textarea name="notes" rows="3"
                  class="block w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-emerald-600 focus:ring-emerald-600"><?= $h($warehouse['notes'] ?? '') ?></textarea>
      </div>

      <div class="pt-3 border-t border-slate-100 flex items-center justify-end gap-3">
        <a href="<?= $h($base . '/warehouse/' . $id) ?>"
           class="px-3 py-2 rounded-md border border-slate-200 text-xs sm:text-sm text-slate-700 hover:bg-slate-50">
          Cancel
        </a>
        <button type="submit"
                class="px-4 py-2 rounded-md bg-emerald-600 text-white text-xs sm:text-sm font-medium hover:bg-emerald-700">
          Update warehouse
        </button>
      </div>
    </form>
  </section>

  <section class="mt-4 border border-emerald-100 bg-emerald-50/60 text-emerald-900 rounded-lg p-4 text-sm">
    <h2 class="font-semibold mb-1">How to use this page</h2>
    <ul class="list-disc pl-5 space-y-1 text-xs sm:text-sm">
      <li>Make sure the <strong>Code</strong> stays unique; changing it will affect how future documents reference this location.</li>
      <li>Use the <strong>Primary</strong> toggle carefully – only one primary warehouse is kept per organisation.</li>
      <li>Deactivate a warehouse by unchecking <strong>Active</strong> instead of deleting, so historical inventory remains consistent.</li>
    </ul>
  </section>

</div>