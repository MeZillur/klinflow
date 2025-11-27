<?php
/**
 * Warehouses index
 *
 * Expects:
 * - array  $org
 * - string $module_base
 * - array  $warehouses
 * - array  $metrics
 * - string $search
 * - string $filter_type
 * - string $filter_status
 */

$h    = $h ?? fn($s)=>htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
$base = rtrim($module_base ?? '/apps/bizflow', '/');
$orgName = trim((string)($org['name'] ?? ''));
?>
<div class="max-w-6xl mx-auto px-4 py-6 space-y-8">

  <!-- Header + nav tabs -->
  <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
    <div>
      <h1 class="text-2xl sm:text-3xl font-bold tracking-tight">Warehouses</h1>
      <p class="mt-1 text-sm text-slate-600">
        Manage physical and virtual stock locations for
        <?= $orgName !== '' ? $h($orgName) : 'this organisation' ?>.
      </p>
    </div>

    <!-- Right-aligned tabs (same pattern as other BizFlow pages) -->
    <div class="flex flex-wrap items-center gap-1 justify-end text-xs sm:text-sm">
      <a href="<?= $h($base . '/items') ?>" class="px-3 py-1.5 rounded-md border border-slate-200 hover:bg-slate-50">Items</a>
      <a href="<?= $h($base . '/warehouse') ?>" class="px-3 py-1.5 rounded-md border border-emerald-600 bg-emerald-600 text-white">Warehouse</a>
      <a href="<?= $h($base . '/suppliers') ?>" class="px-3 py-1.5 rounded-md border border-slate-200 hover:bg-slate-50">Suppliers</a>
      <a href="<?= $h($base . '/orders') ?>" class="px-3 py-1.5 rounded-md border border-slate-200 hover:bg-slate-50">Orders</a>
      <a href="<?= $h($base . '/invoices') ?>" class="px-3 py-1.5 rounded-md border border-slate-200 hover:bg-slate-50">Invoices</a>
      <a href="<?= $h($base . '/purchases') ?>" class="px-3 py-1.5 rounded-md border border-slate-200 hover:bg-slate-50">Purchases</a>
      <a href="<?= $h($base . '/tenders') ?>" class="px-3 py-1.5 rounded-md border border-slate-200 hover:bg-slate-50">Tenders</a>
      <a href="<?= $h($base . '/inventory') ?>" class="px-3 py-1.5 rounded-md border border-slate-200 hover:bg-slate-50">Inventory</a>
    </div>
  </div>

  <!-- Top metrics -->
  <section class="grid grid-cols-1 sm:grid-cols-3 gap-4">
    <div class="border border-slate-200 rounded-lg p-4 bg-white">
      <p class="text-xs font-medium text-slate-500 uppercase tracking-wide">Total warehouses</p>
      <p class="mt-2 text-2xl font-semibold"><?= (int)($metrics['total'] ?? 0) ?></p>
      <p class="mt-1 text-xs text-slate-500">Includes all active and inactive locations.</p>
    </div>
    <div class="border border-slate-200 rounded-lg p-4 bg-white">
      <p class="text-xs font-medium text-slate-500 uppercase tracking-wide">Active locations</p>
      <p class="mt-2 text-2xl font-semibold"><?= (int)($metrics['active'] ?? 0) ?></p>
      <p class="mt-1 text-xs text-slate-500">Currently available for inventory movements.</p>
    </div>
    <div class="border border-slate-200 rounded-lg p-4 bg-white">
      <p class="text-xs font-medium text-slate-500 uppercase tracking-wide">Primary warehouse</p>
      <p class="mt-2 text-2xl font-semibold"><?= (int)($metrics['primary'] ?? 0) ?></p>
      <p class="mt-1 text-xs text-slate-500">Used as default for new stock.</p>
    </div>
  </section>

  <!-- Filters + New button -->
  <section class="bg-white border border-slate-200 rounded-lg shadow-sm">
    <div class="px-4 py-3 border-b border-slate-200 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
      <div class="flex-1">
        <form method="get" action="<?= $h($base . '/warehouse') ?>" class="flex flex-col gap-2 sm:flex-row sm:items-center">
          <div class="flex-1">
            <label class="sr-only">Search</label>
            <div class="relative">
              <input type="text" name="q"
                     value="<?= $h($search ?? '') ?>"
                     placeholder="Search by code, name, city, district"
                     class="block w-full rounded-md border border-slate-300 pl-3 pr-8 py-2 text-sm focus:border-emerald-600 focus:ring-emerald-600">
              <span class="absolute inset-y-0 right-2 flex items-center text-slate-400 text-xs">⌘K</span>
            </div>
          </div>

          <div class="flex gap-2">
            <select name="type"
                    class="rounded-md border border-slate-300 px-2 py-2 text-xs sm:text-sm focus:border-emerald-600 focus:ring-emerald-600">
              <option value="">All types</option>
              <?php
              $types = [
                  'warehouse'   => 'Warehouse',
                  'store'       => 'Store / outlet',
                  'regional'    => 'Regional hub',
                  'consignment' => 'Consignment',
                  'virtual'     => 'Virtual',
                  'other'       => 'Other',
              ];
              foreach ($types as $key => $label):
                $selected = ($filter_type === $key) ? 'selected' : '';
              ?>
                <option value="<?= $h($key) ?>" <?= $selected ?>><?= $h($label) ?></option>
              <?php endforeach; ?>
            </select>

            <select name="status"
                    class="rounded-md border border-slate-300 px-2 py-2 text-xs sm:text-sm focus:border-emerald-600 focus:ring-emerald-600">
              <option value="">All statuses</option>
              <option value="active"   <?= $filter_status === 'active'   ? 'selected' : '' ?>>Active</option>
              <option value="inactive" <?= $filter_status === 'inactive' ? 'selected' : '' ?>>Inactive</option>
            </select>

            <button type="submit"
                    class="px-3 py-2 rounded-md bg-emerald-600 text-white text-xs sm:text-sm font-medium hover:bg-emerald-700">
              Apply
            </button>
          </div>
        </form>
      </div>

      <div>
        <a href="<?= $h($base . '/warehouse/create') ?>"
           class="inline-flex items-center px-3 py-2 rounded-md bg-emerald-600 text-white text-xs sm:text-sm font-medium hover:bg-emerald-700">
          + New warehouse
        </a>
      </div>
    </div>

    <!-- Table -->
    <div class="overflow-x-auto">
      <table class="min-w-full text-sm">
        <thead class="bg-slate-50 border-b border-slate-200">
          <tr class="text-left text-xs font-medium text-slate-500 uppercase tracking-wide">
            <th class="px-4 py-2">Code</th>
            <th class="px-4 py-2">Name</th>
            <th class="px-4 py-2">Type</th>
            <th class="px-4 py-2">Location</th>
            <th class="px-4 py-2">Primary</th>
            <th class="px-4 py-2">Status</th>
            <th class="px-4 py-2">Updated</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
        <?php if (empty($warehouses)): ?>
          <tr>
            <td colspan="7" class="px-4 py-6 text-center text-sm text-slate-500">
              No warehouses yet. Use the <strong>“New warehouse”</strong> button to add your first location.
            </td>
          </tr>
        <?php else: ?>
          <?php foreach ($warehouses as $wh): ?>
            <tr class="hover:bg-slate-50">
              <td class="px-4 py-2 font-mono text-xs">
                <a href="<?= $h($base . '/warehouse/' . (int)$wh['id']) ?>"
                   class="text-emerald-700 hover:underline">
                  <?= $h($wh['code']) ?>
                </a>
              </td>
              <td class="px-4 py-2">
                <?= $h($wh['name']) ?>
              </td>
              <td class="px-4 py-2 text-xs text-slate-600">
                <?= $h($wh['type']) ?>
              </td>
              <td class="px-4 py-2 text-xs text-slate-600">
                <?php
                  $locParts = [];
                  if (!empty($wh['city']))     $locParts[] = $wh['city'];
                  if (!empty($wh['district'])) $locParts[] = $wh['district'];
                  if (!empty($wh['country']))  $locParts[] = $wh['country'];
                ?>
                <?= $h(implode(', ', $locParts)) ?>
              </td>
              <td class="px-4 py-2 text-xs">
                <?php if (!empty($wh['is_primary'])): ?>
                  <span class="inline-flex items-center rounded-full bg-emerald-50 px-2 py-0.5 text-[11px] font-medium text-emerald-700">
                    Primary
                  </span>
                <?php endif; ?>
              </td>
              <td class="px-4 py-2 text-xs">
                <?php if (!empty($wh['is_active'])): ?>
                  <span class="inline-flex items-center rounded-full bg-emerald-50 px-2 py-0.5 text-[11px] font-medium text-emerald-700">
                    Active
                  </span>
                <?php else: ?>
                  <span class="inline-flex items-center rounded-full bg-slate-100 px-2 py-0.5 text-[11px] font-medium text-slate-600">
                    Inactive
                  </span>
                <?php endif; ?>
              </td>
              <td class="px-4 py-2 text-xs text-slate-500">
                <?= $h($wh['updated_at'] ?? $wh['created_at'] ?? '') ?>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
      </table>
    </div>
  </section>

  <!-- How to use -->
  <section class="mt-6 border border-emerald-100 bg-emerald-50/60 text-emerald-900 rounded-lg p-4 text-sm">
    <h2 class="font-semibold mb-1 flex items-center gap-2">
      <span class="inline-flex items-center justify-center w-5 h-5 rounded-full bg-emerald-600 text-white text-xs">?</span>
      How to use this page
    </h2>
    <ul class="list-disc pl-5 space-y-1 text-xs sm:text-sm">
      <li>Use <strong>New warehouse</strong> to register each physical or virtual stock location.</li>
      <li>Mark one location as <strong>Primary</strong> to use it as the default for purchases and stock entries.</li>
      <li>Filter by <strong>type</strong> and <strong>status</strong> to quickly find relevant sites.</li>
      <li>Click a warehouse code to see its full profile and recent inventory movements.</li>
    </ul>
  </section>

</div>