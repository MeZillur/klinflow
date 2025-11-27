<?php
declare(strict_types=1);

/** @var array $items @var string $q @var array $haveCols @var string $base */

$h   = fn($x) => htmlspecialchars((string)$x, ENT_QUOTES, 'UTF-8');
$items     = $items     ?? [];
$q         = $q         ?? '';
$haveCols  = $haveCols  ?? [];
$base      = $base      ?? ($ctx['module_base'] ?? '/apps/pos');

$has = fn(string $c) => in_array($c, $haveCols, true);
?>
<div class="px-6 py-6 space-y-6">

  <!-- Header -->
  <div class="flex flex-wrap items-center justify-between gap-4">
    <div>
      <h1 class="text-2xl md:text-3xl font-semibold text-gray-900 dark:text-gray-50 flex items-center gap-2">
        <i class="fa fa-truck-field text-emerald-600" aria-hidden="true"></i>
        <span>Suppliers</span>
      </h1>
      <p class="text-sm text-gray-500 dark:text-gray-400">
        Central supplier directory for purchases and inventory.
      </p>
    </div>

    <div class="flex flex-wrap gap-2 text-sm">
      <a href="<?= $h($base) ?>/purchases"
         class="inline-flex items-center gap-2 px-3 py-2 rounded-lg border border-gray-200 dark:border-gray-700 text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-800">
        <i class="fa fa-file-invoice-dollar" aria-hidden="true"></i>
        View Purchases
      </a>
      <a href="<?= $h($base) ?>/suppliers/create"
         class="inline-flex items-center gap-2 px-3 py-2 rounded-lg text-white shadow"
         style="background:#228B22;">
        <i class="fa fa-plus" aria-hidden="true"></i>
        New Supplier
      </a>
    </div>
  </div>

  <!-- Search / filters -->
  <form method="get"
        class="rounded-2xl bg-white dark:bg-gray-900 shadow-sm border border-gray-100 dark:border-gray-800 px-4 py-3 flex flex-wrap items-center gap-3">
    <div class="flex-1 min-w-[220px]">
      <label class="block text-xs font-semibold text-gray-500 dark:text-gray-400 mb-1">
        Search
      </label>
      <div class="relative">
        <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-gray-400">
          <i class="fa fa-magnifying-glass"></i>
        </span>
        <input
          type="text"
          name="q"
          value="<?= $h($q) ?>"
          placeholder="Name, code, phone, email…"
          class="w-full rounded-xl border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800
                 pl-9 pr-3 py-2 text-sm text-gray-900 dark:text-gray-100
                 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500">
      </div>
    </div>

    <div class="text-xs text-gray-400 dark:text-gray-500 flex items-end">
      Tip: use part of <strong>name / phone / email</strong> to find a supplier quickly.
    </div>

    <div class="ml-auto">
      <button type="submit"
              class="inline-flex items-center gap-2 px-4 py-2 rounded-xl text-sm font-semibold text-white shadow"
              style="background:#228B22;">
        <i class="fa fa-filter" aria-hidden="true"></i>
        Apply
      </button>
    </div>
  </form>

  <!-- Main table card -->
  <div class="rounded-2xl bg-white dark:bg-gray-900 shadow-sm border border-gray-100 dark:border-gray-800 overflow-hidden">
    <div class="px-4 py-3 flex items-center justify-between border-b border-gray-100 dark:border-gray-800 text-xs text-gray-500 dark:text-gray-400">
      <div class="flex items-center gap-2">
        <i class="fa fa-table" aria-hidden="true"></i>
        <span><?= count($items) ? number_format(count($items)) : '0' ?> suppliers</span>
      </div>
      <div class="flex items-center gap-3">
        <span class="inline-flex items-center gap-1">
          <span class="h-2 w-2 rounded-full bg-emerald-500"></span>
          Active
        </span>
        <span class="inline-flex items-center gap-1">
          <span class="h-2 w-2 rounded-full bg-gray-400"></span>
          Inactive
        </span>
      </div>
    </div>

    <div class="overflow-x-auto">
      <table class="min-w-full text-sm text-left text-gray-700 dark:text-gray-200">
        <thead class="bg-gray-50 dark:bg-gray-800/80 text-xs uppercase tracking-wide">
          <tr>
            <th class="px-4 py-3 font-semibold">Name</th>
            <?php if ($has('code')): ?>
              <th class="px-4 py-3 font-semibold">Code</th>
            <?php endif; ?>
            <?php if ($has('contact')): ?>
              <th class="px-4 py-3 font-semibold">Contact</th>
            <?php endif; ?>
            <?php if ($has('phone')): ?>
              <th class="px-4 py-3 font-semibold">Phone</th>
            <?php endif; ?>
            <?php if ($has('email')): ?>
              <th class="px-4 py-3 font-semibold">Email</th>
            <?php endif; ?>
            <?php if ($has('city') || $has('country')): ?>
              <th class="px-4 py-3 font-semibold">Location</th>
            <?php endif; ?>
            <?php if ($has('is_active')): ?>
              <th class="px-4 py-3 font-semibold text-center">Status</th>
            <?php endif; ?>
            <?php if ($has('created_at')): ?>
              <th class="px-4 py-3 font-semibold">Created</th>
            <?php endif; ?>
            <!-- metrics columns (via API) -->
            <th class="px-4 py-3 font-semibold text-right whitespace-nowrap">Products</th>
            <th class="px-4 py-3 font-semibold text-right whitespace-nowrap">Sold 30d</th>
            <th class="px-4 py-3 font-semibold text-right whitespace-nowrap">Stock</th>
            <th class="px-4 py-3 font-semibold text-right">Actions</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
        <?php if (!$items): ?>
          <tr>
            <td colspan="12" class="px-4 py-10 text-center text-sm text-gray-400">
              <i class="fa fa-circle-info mr-1" aria-hidden="true"></i>
              No suppliers yet. Click <strong>New Supplier</strong> to add your first one.
            </td>
          </tr>
        <?php else: ?>
          <?php foreach ($items as $s): ?>
            <?php
              $id   = (int)($s['id'] ?? 0);
              $name = $s['name'] ?? '';
              $city = $s['city'] ?? '';
              $country = $s['country'] ?? '';
              $loc  = trim($city . ($city && $country ? ', ' : '') . $country);
              $active = (int)($s['is_active'] ?? 1);
              $created = $s['created_at'] ?? null;
            ?>
            <tr class="hover:bg-gray-50 dark:hover:bg-gray-800 transition" data-supplier-id="<?= $id ?>">
              <td class="px-4 py-3">
                <div class="font-medium text-gray-900 dark:text-gray-50 flex items-center gap-2">
                  <span class="inline-flex h-7 w-7 items-center justify-center rounded-full bg-emerald-50 text-emerald-600 dark:bg-emerald-900/40 dark:text-emerald-300 text-xs">
                    <i class="fa fa-truck" aria-hidden="true"></i>
                  </span>
                  <span class="truncate" title="<?= $h($name) ?>"><?= $h($name) ?></span>
                </div>
                <?php if (!empty($s['address'])): ?>
                  <div class="text-xs text-gray-500 dark:text-gray-400 mt-0.5 truncate">
                    <?= $h($s['address']) ?>
                  </div>
                <?php endif; ?>
              </td>

              <?php if ($has('code')): ?>
                <td class="px-4 py-3 text-xs text-gray-600 dark:text-gray-300">
                  <?= $h($s['code'] ?? '') ?>
                </td>
              <?php endif; ?>

              <?php if ($has('contact')): ?>
                <td class="px-4 py-3 text-xs text-gray-600 dark:text-gray-300">
                  <?= $h($s['contact'] ?? '') ?>
                </td>
              <?php endif; ?>

              <?php if ($has('phone')): ?>
                <td class="px-4 py-3 text-xs text-gray-700 dark:text-gray-200 whitespace-nowrap">
                  <?= $h($s['phone'] ?? '') ?>
                </td>
              <?php endif; ?>

              <?php if ($has('email')): ?>
                <td class="px-4 py-3 text-xs text-gray-700 dark:text-gray-200 whitespace-nowrap">
                  <?= $h($s['email'] ?? '') ?>
                </td>
              <?php endif; ?>

              <?php if ($has('city') || $has('country')): ?>
                <td class="px-4 py-3 text-xs text-gray-600 dark:text-gray-300">
                  <?= $loc ? $h($loc) : '—' ?>
                </td>
              <?php endif; ?>

              <?php if ($has('is_active')): ?>
                <td class="px-4 py-3 text-center text-xs">
                  <?php if ($active): ?>
                    <span class="inline-flex items-center px-2 py-0.5 rounded-full bg-emerald-50 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300">
                      <span class="h-1.5 w-1.5 rounded-full bg-emerald-500 mr-1"></span>
                      Active
                    </span>
                  <?php else: ?>
                    <span class="inline-flex items-center px-2 py-0.5 rounded-full bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-300">
                      <span class="h-1.5 w-1.5 rounded-full bg-gray-400 mr-1"></span>
                      Inactive
                    </span>
                  <?php endif; ?>
                </td>
              <?php endif; ?>

              <?php if ($has('created_at')): ?>
                <td class="px-4 py-3 text-xs text-gray-500 dark:text-gray-400 whitespace-nowrap">
                  <?= $created ? $h(date('d M Y', strtotime((string)$created))) : '—' ?>
                </td>
              <?php endif; ?>

              <!-- metrics (ajax filled) -->
              <td class="px-4 py-3 text-right text-xs">
                <span class="supplier-metric-products text-gray-500">…</span>
              </td>
              <td class="px-4 py-3 text-right text-xs">
                <span class="supplier-metric-sold text-gray-500">…</span>
              </td>
              <td class="px-4 py-3 text-right text-xs">
                <span class="supplier-metric-stock text-gray-500">…</span>
              </td>

              <td class="px-4 py-3 text-right text-xs whitespace-nowrap">
                <a href="<?= $h($base) ?>/suppliers/<?= $id ?>/edit"
                   class="inline-flex items-center gap-1 px-2.5 py-1.5 rounded-lg border border-gray-200 dark:border-gray-700 text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-800">
                  <i class="fa fa-pen-to-square text-[11px]" aria-hidden="true"></i>
                  Edit
                </a>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<script>
(function(){
  const rows = document.querySelectorAll('tr[data-supplier-id]');
  if(!rows.length) return;

  const ids = Array.from(rows).map(r => r.getAttribute('data-supplier-id')).filter(Boolean);
  const base = <?= json_encode($base, JSON_UNESCAPED_SLASHES) ?>;

  fetch(base + '/suppliers/api/metrics?ids=' + encodeURIComponent(ids.join(',')), {
    credentials:'include'
  })
    .then(r => r.ok ? r.json() : null)
    .then(data => {
      if(!data) return;
      rows.forEach(row => {
        const id = row.getAttribute('data-supplier-id');
        if(!id || !data[id]) return;
        const m = data[id];

        const p = row.querySelector('.supplier-metric-products');
        const s = row.querySelector('.supplier-metric-sold');
        const k = row.querySelector('.supplier-metric-stock');

        if(p) p.textContent = m.products ?? 0;
        if(s) s.textContent = m.sold_30d ?? 0;
        if(k) k.textContent = m.stock ?? 0;
      });
    })
    .catch(()=>{ /* soft fail */ });
})();
</script>