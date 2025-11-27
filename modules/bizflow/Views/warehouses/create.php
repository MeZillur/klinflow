<?php
/**
 * Create warehouse
 *
 * Expects:
 * - array  $org
 * - string $module_base
 * - array  $types      // optional; will fall back if missing
 * - array  $errors
 * - array  $old
 */

$h        = $h ?? fn($s)=>htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
$base     = rtrim($module_base ?? '/apps/bizflow', '/');
$orgName  = trim((string)($org['name'] ?? ''));

// Safe fallback if controller forgot to pass $types
$types = $types ?? [
    'store'      => 'Store / central warehouse',
    'shop'       => 'Shop / outlet',
    'in_transit' => 'In-transit location',
    'virtual'    => 'Virtual / logical location',
];
?>
<div class="max-w-4xl mx-auto px-4 py-6 space-y-6">

  <!-- Header + tabs -->
  <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
    <div>
      <h1 class="text-2xl sm:text-3xl font-bold tracking-tight">New warehouse</h1>
      <p class="mt-1 text-sm text-slate-600">
        Define a warehouse, store, or virtual location for
        <?= $orgName !== '' ? $h($orgName) : 'this organisation' ?>.
      </p>
    </div>

    <div class="flex flex-wrap items-center gap-1 justify-end text-xs sm:text-sm">
      <a href="<?= $h($base . '/warehouse') ?>"
         class="px-3 py-1.5 rounded-md border border-slate-200 hover:bg-slate-50">
        Warehouses list
      </a>
      <a href="<?= $h($base . '/inventory') ?>"
         class="px-3 py-1.5 rounded-md border border-slate-200 hover:bg-slate-50">
        Inventory
      </a>
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
    <!-- IMPORTANT: action points to /warehouse (singular) to match your routes -->
    <form action="<?= $h($base . '/warehouse') ?>" method="post" class="px-4 py-5 space-y-5">

      <!-- Basic -->
      <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <div class="sm:col-span-2">
          <label class="block text-sm font-medium text-slate-700 mb-1">
            Name <span class="text-red-500">*</span>
          </label>
          <input type="text" name="name" required
                 value="<?= $h($old['name'] ?? '') ?>"
                 class="block w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-emerald-600 focus:ring-emerald-600">
        </div>
        <div>
          <label class="block text-sm font-medium text-slate-700 mb-1">
            Code <span class="text-red-500">*</span>
          </label>
          <input type="text" name="code"
                 value="<?= $h($old['code'] ?? '') ?>"
                 placeholder="Auto WH01 / DHA01 if left blank"
                 class="block w-full rounded-md border border-slate-300 px-3 py-2 text-sm uppercase focus:border-emerald-600 focus:ring-emerald-600">
          <p class="mt-1 text-xs text-slate-500">
            Unique per organisation. Leave blank to auto-generate from the name.
          </p>
        </div>
      </div>

      <!-- Type + flags -->
      <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <div>
          <label class="block text-sm font-medium text-slate-700 mb-1">Type</label>
          <select name="type"
                  class="block w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-emerald-600 focus:ring-emerald-600">
            <?php
              $currentType = $old['type'] ?? 'store';
              foreach ($types as $key => $label):
            ?>
              <option value="<?= $h($key) ?>"
                <?= $currentType === $key ? 'selected' : '' ?>>
                <?= $h($label) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="flex items-center gap-3">
          <label class="inline-flex items-center gap-2 text-sm text-slate-700">
            <!-- match DB column: is_default (NOT is_primary) -->
            <input type="checkbox" name="is_default" value="1"
              <?= !empty($old['is_default']) ? 'checked' : '' ?>
              class="rounded border-slate-300 text-emerald-600 focus:ring-emerald-600">
            <span>Primary / default warehouse</span>
          </label>
        </div>

        <div class="flex items-center gap-3">
          <label class="inline-flex items-center gap-2 text-sm text-slate-700">
            <input type="checkbox" name="is_active" value="1"
              <?= array_key_exists('is_active',$old) ? (!empty($old['is_active'])?'checked':'') : 'checked' ?>
              class="rounded border-slate-300 text-emerald-600 focus:ring-emerald-600">
            <span>Active</span>
          </label>
        </div>
      </div>

      <!-- Location (columns that really exist in biz_warehouses) -->
      <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <div>
          <label class="block text-sm font-medium text-slate-700 mb-1">City</label>
          <input type="text" name="city"
                 value="<?= $h($old['city'] ?? '') ?>"
                 class="block w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-emerald-600 focus:ring-emerald-600">
        </div>
        <div>
          <label class="block text-sm font-medium text-slate-700 mb-1">District</label>
          <input type="text" name="district"
                 value="<?= $h($old['district'] ?? '') ?>"
                 class="block w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-emerald-600 focus:ring-emerald-600">
        </div>
        <div>
          <label class="block text-sm font-medium text-slate-700 mb-1">Country</label>
          <input type="text" name="country"
                 value="<?= $h($old['country'] ?? 'Bangladesh') ?>"
                 class="block w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-emerald-600 focus:ring-emerald-600">
        </div>
      </div>

      <!-- Notes -->
      <div>
        <label class="block text-sm font-medium text-slate-700 mb-1">Internal notes</label>
        <textarea name="notes" rows="3"
                  class="block w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-emerald-600 focus:ring-emerald-600"><?= $h($old['notes'] ?? '') ?></textarea>
      </div>

      <!-- Actions -->
      <div class="pt-3 border-t border-slate-100 flex items-center justify-end gap-3">
        <a href="<?= $h($base . '/warehouse') ?>"
           class="px-3 py-2 rounded-md border border-slate-200 text-xs sm:text-sm text-slate-700 hover:bg-slate-50">
          Cancel
        </a>
        <button type="submit"
                class="px-4 py-2 rounded-md bg-emerald-600 text-white text-xs sm:text-sm font-medium hover:bg-emerald-700">
          Save warehouse
        </button>
      </div>
    </form>
  </section>

  <!-- How to use this page -->
  <section class="mt-4 border border-emerald-100 bg-emerald-50/60 text-emerald-900 rounded-lg p-4 text-sm">
    <h2 class="font-semibold mb-1">How to use this page</h2>
    <ul class="list-disc pl-5 space-y-1 text-xs sm:text-sm">
      <li>Fill in the <strong>Name</strong> and <strong>Code</strong> – these identify the warehouse across orders, purchases, and inventory.</li>
      <li>Use <strong>Type</strong> to distinguish main warehouses, shops, in-transit, and virtual locations.</li>
      <li>Tick <strong>Primary / default warehouse</strong> for your main stock location; KlinFlow will automatically demote other defaults.</li>
      <li>Add <strong>city / district / country</strong> and notes so your team knows exactly where this location is used.</li>
    </ul>
  </section>

</div>