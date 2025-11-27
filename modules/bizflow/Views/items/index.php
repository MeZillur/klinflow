<?php
declare(strict_types=1);

/**
 * BizFlow — Items index (catalog)
 * Content-only view. Wrapper shell comes from BizFlow layout.
 *
 * Expects (softly):
 * - string $title
 * - string $module_base
 * - array  $org
 * - array  $items  rows from biz_items (or similar)
 */

$h = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');

$module_base = isset($module_base) && $module_base !== ''
    ? rtrim((string)$module_base, '/')
    : '/apps/bizflow';

$orgName = $org['name'] ?? 'Your Organisation';

// Safe defaults
$items = is_array($items ?? null) ? $items : [];

// Lightweight stats (defensive against missing cols)
$total     = 0;
$active    = 0;
$services  = 0;
$products  = 0;

foreach ($items as $row) {
    $total++;

    $statusRaw = strtolower((string)($row['status'] ?? ($row['is_active'] ?? 'active')));
    if (in_array($statusRaw, ['1', 'true', 'active', 'enabled', 'yes'], true)) {
        $active++;
    }

    $typeRaw = strtolower((string)($row['type'] ?? ($row['item_type'] ?? ($row['kind'] ?? 'product'))));
    if (strpos($typeRaw, 'service') !== false) {
        $services++;
    } else {
        $products++;
    }
}

// JSON for Alpine (HTML-encoded so it doesn’t break x-data)
$itemsJson = htmlspecialchars(
    json_encode(array_values($items), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
    ENT_QUOTES,
    'UTF-8'
);
$brandColor = '#228B22';
?>
<div
    class="space-y-6 lg:space-y-8"
    x-data="{
        q: '',
        typeFilter: 'all',
        statusFilter: 'all',
        get rows() {
            const q  = this.q.toLowerCase();
            const tf = this.typeFilter;
            const sf = this.statusFilter;

            const data = <?= $itemsJson ?>;

            return data.filter(r => {
                // text match
                let hay = ((r.name || '') + ' ' + (r.code || '') + ' ' + (r.category_name || '')).toLowerCase();
                if (q && !hay.includes(q)) return false;

                // type filter
                let typeRaw = String(r.type || r.item_type || r.kind || 'product').toLowerCase();
                if (tf === 'product' && !typeRaw.includes('prod')) return false;
                if (tf === 'service' && !typeRaw.includes('serv')) return false;

                // status filter
                let statusRaw = String(r.status ?? r.is_active ?? 'active').toLowerCase();
                let isActive = ['1','true','active','enabled','yes'].includes(statusRaw);
                if (sf === 'active' && !isActive) return false;
                if (sf === 'inactive' && isActive) return false;

                return true;
            });
        }
    }"
>

    <!-- Top row: title + actions + tabs (right-aligned) -->
    <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
        <div class="space-y-1">
            <div class="inline-flex items-center gap-2 rounded-full border border-emerald-100 bg-emerald-50 px-3 py-1 text-xs font-medium text-emerald-800">
                <span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>
                <span>BizFlow Items · BDT ready</span>
            </div>
            <h1 class="text-2xl font-semibold tracking-tight sm:text-3xl">
                <?= $h($title ?? 'Items catalog') ?>
            </h1>
            <p class="text-sm text-gray-500 dark:text-gray-400">
                Manage all products and services offered by
                <span class="font-medium text-gray-700 dark:text-gray-200"><?= $h($orgName) ?></span>
                across quotes, orders, tenders, and invoices.
            </p>
        </div>

        <?php
        $tabs = [
            ['key' => 'items',      'label' => 'Items',      'href' => $module_base.'/items'],
            ['key' => 'categories', 'label' => 'Categories', 'href' => $module_base.'/categories'],
            ['key' => 'suppliers',  'label' => 'Suppliers',  'href' => $module_base.'/suppliers'],
            ['key' => 'quotes',     'label' => 'Quotes',     'href' => $module_base.'/quotes'],
            ['key' => 'uoms',       'label' => 'UoM',        'href' => $module_base.'/uoms'],
            ['key' => 'tenders',    'label' => 'Tenders',    'href' => $module_base.'/tenders'],
        ];
        $currentTab = 'items';
        ?>

        <div class="flex flex-1 flex-col items-stretch gap-3">
            <div class="flex justify-end">
                <div class="inline-flex items-center gap-1 rounded-xl border border-gray-200 bg-white px-1 py-1 shadow-sm dark:border-gray-700 dark:bg-gray-900/80">
                    <?php foreach ($tabs as $tab):
                        $isActive = $tab['key'] === $currentTab;
                    ?>
                        <a
                            href="<?= $h($tab['href']) ?>"
                            class="inline-flex items-center gap-2 rounded-lg px-3 py-1.5 text-xs font-medium transition
                                <?= $isActive
                                    ? 'bg-emerald-600 text-white shadow-sm'
                                    : 'text-gray-600 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-800' ?>"
                        >
                            <span><?= $h($tab['label']) ?></span>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Primary actions -->
            <div class="flex flex-wrap items-center justify-end gap-2">
                <a href="<?= $h($module_base.'/items/create') ?>"
                   class="inline-flex items-center gap-2 rounded-lg bg-emerald-600 px-3 py-2 text-sm font-medium text-white shadow-sm hover:bg-emerald-700 focus:outline-none focus:ring-2 focus:ring-emerald-600 focus:ring-offset-1">
                    <i class="fa fa-plus-circle text-xs"></i>
                    <span>New item</span>
                </a>
            </div>
        </div>
    </div>

    <!-- Metrics -->
    <section class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
        <div class="rounded-2xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-900/70">
            <div class="text-xs font-medium text-gray-500">Total items</div>
            <div class="mt-1 flex items-baseline gap-2">
                <div class="text-2xl font-semibold"><?= $h($total) ?></div>
            </div>
        </div>
        <div class="rounded-2xl border border-emerald-100 bg-emerald-50 p-4 shadow-sm dark:border-emerald-700/50 dark:bg-emerald-900/40">
            <div class="text-xs font-medium text-emerald-900/90 dark:text-emerald-100">Active</div>
            <div class="mt-1 flex items-baseline gap-2">
                <div class="text-2xl font-semibold"><?= $h($active) ?></div>
                <?php if ($total > 0):
                    $pct = round(($active / $total) * 100);
                ?>
                    <div class="text-xs text-emerald-800 dark:text-emerald-100"><?= $h($pct) ?>%</div>
                <?php endif; ?>
            </div>
        </div>
        <div class="rounded-2xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-900/70">
            <div class="text-xs font-medium text-gray-500">Products</div>
            <div class="mt-1 text-2xl font-semibold"><?= $h($products) ?></div>
        </div>
        <div class="rounded-2xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-900/70">
            <div class="text-xs font-medium text-gray-500">Services</div>
            <div class="mt-1 text-2xl font-semibold"><?= $h($services) ?></div>
        </div>
    </section>

    <!-- Filters + search -->
    <section class="rounded-2xl border border-gray-200 bg-white p-3 shadow-sm dark:border-gray-700 dark:bg-gray-900/80">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div class="flex flex-1 items-center gap-2">
                <div class="relative flex-1">
                    <span class="pointer-events-none absolute inset-y-0 left-2 flex items-center pl-0.5 text-gray-400">
                        <i class="fa fa-search text-xs"></i>
                    </span>
                    <input
                        type="search"
                        x-model="q"
                        placeholder="Search by name, code, or category"
                        class="w-full rounded-lg border border-gray-200 bg-white py-2 pl-7 pr-3 text-sm text-gray-900 shadow-sm outline-none placeholder:text-gray-400 focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-50"
                    />
                </div>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <select
                    x-model="typeFilter"
                    class="rounded-lg border border-gray-200 bg-white px-2.5 py-1.5 text-xs text-gray-800 shadow-sm outline-none focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-50"
                >
                    <option value="all">Type: All</option>
                    <option value="product">Products</option>
                    <option value="service">Services</option>
                </select>

                <select
                    x-model="statusFilter"
                    class="rounded-lg border border-gray-200 bg-white px-2.5 py-1.5 text-xs text-gray-800 shadow-sm outline-none focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-50"
                >
                    <option value="all">Status: All</option>
                    <option value="active">Active only</option>
                    <option value="inactive">Inactive only</option>
                </select>
            </div>
        </div>
    </section>

    <!-- Items table -->
    <section class="rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-900/80">
        <div class="overflow-x-auto">
            <table class="min-w-full border-separate border-spacing-0 text-left text-sm">
                <thead class="bg-gray-50 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:bg-gray-900 dark:text-gray-400">
                    <tr>
                        <th class="sticky left-0 z-10 border-b border-gray-200 bg-gray-50 px-3 py-2 dark:border-gray-700 dark:bg-gray-900">
                            Item
                        </th>
                        <th class="border-b border-gray-200 px-3 py-2 dark:border-gray-700">Category</th>
                        <th class="border-b border-gray-200 px-3 py-2 dark:border-gray-700">Type</th>
                        <th class="border-b border-gray-200 px-3 py-2 dark:border-gray-700">Unit</th>
                        <th class="border-b border-gray-200 px-3 py-2 text-right dark:border-gray-700">Price (BDT)</th>
                        <th class="border-b border-gray-200 px-3 py-2 text-right dark:border-gray-700">Tax %</th>
                        <th class="border-b border-gray-200 px-3 py-2 dark:border-gray-700">Status</th>
                        <th class="border-b border-gray-200 px-3 py-2 text-right dark:border-gray-700">Updated</th>
                        <th class="border-b border-gray-200 px-3 py-2 text-right dark:border-gray-700"></th>
                    </tr>
                </thead>
                <tbody
                    class="divide-y divide-gray-100 text-sm text-gray-800 dark:divide-gray-800 dark:text-gray-100"
                >
                    <template x-if="rows.length === 0">
                        <tr>
                            <td colspan="9" class="px-3 py-6 text-center text-sm text-gray-500 dark:text-gray-400">
                                No items match the current filters.
                            </td>
                        </tr>
                    </template>

                    <template x-for="row in rows" :key="row.id ?? (row.code + '-' + row.name)">
                        <tr class="hover:bg-emerald-50/40 dark:hover:bg-gray-800/60">
                            <!-- Item (sticky) -->
                            <td class="sticky left-0 z-0 max-w-xs border-b border-gray-100 bg-white px-3 py-2 dark:border-gray-800 dark:bg-gray-900">
                                <div class="flex flex-col">
                                    <span class="truncate font-medium" x-text="row.name || 'Unnamed item'"></span>
                                    <span class="mt-0.5 text-xs text-gray-500" x-text="row.code || '—'"></span>
                                </div>
                            </td>

                            <!-- Category -->
                            <td class="border-b border-gray-100 px-3 py-2 text-sm text-gray-700 dark:border-gray-800 dark:text-gray-200">
                                <span x-text="row.category_name || row.category || 'Uncategorised'"></span>
                            </td>

                            <!-- Type -->
                            <td class="border-b border-gray-100 px-3 py-2 text-xs dark:border-gray-800">
                                <span
                                    class="inline-flex items-center rounded-full px-2 py-0.5 font-medium"
                                    :class="(String(row.type || row.item_type || row.kind || 'product').toLowerCase().includes('serv'))
                                        ? 'bg-sky-50 text-sky-700 dark:bg-sky-900/40 dark:text-sky-200'
                                        : 'bg-emerald-50 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-200'"
                                    x-text="(row.type || row.item_type || row.kind || 'Product')"
                                ></span>
                            </td>

                            <!-- Unit -->
                            <td class="border-b border-gray-100 px-3 py-2 text-sm text-gray-700 dark:border-gray-800 dark:text-gray-200">
                                <span x-text="row.unit || row.uom || '—'"></span>
                            </td>

                            <!-- Price (BDT) -->
                            <td class="border-b border-gray-100 px-3 py-2 text-right text-sm dark:border-gray-800">
                                <span
                                    x-text="row.price_bdt != null
                                        ? Number(row.price_bdt).toLocaleString('en-BD', {minimumFractionDigits:2, maximumFractionDigits:2})
                                        : '0.00'"
                                ></span>
                            </td>

                            <!-- Tax % -->
                            <td class="border-b border-gray-100 px-3 py-2 text-right text-sm dark:border-gray-800">
                                <span
                                    x-text="row.tax_percent != null
                                        ? Number(row.tax_percent).toFixed(2)
                                        : '0.00'"
                                ></span>
                            </td>

                            <!-- Status -->
                            <td class="border-b border-gray-100 px-3 py-2 text-xs dark:border-gray-800">
                                <template x-if="['1','true','active','enabled','yes'].includes(String(row.status ?? row.is_active ?? 'active').toLowerCase())">
                                    <span class="inline-flex items-center gap-1 rounded-full bg-emerald-50 px-2 py-0.5 font-medium text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-200">
                                        <span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>
                                        Active
                                    </span>
                                </template>
                                <template x-if="!['1','true','active','enabled','yes'].includes(String(row.status ?? row.is_active ?? 'active').toLowerCase())">
                                    <span class="inline-flex items-center gap-1 rounded-full bg-gray-100 px-2 py-0.5 font-medium text-gray-600 dark:bg-gray-800 dark:text-gray-300">
                                        <span class="h-1.5 w-1.5 rounded-full bg-gray-400"></span>
                                        Inactive
                                    </span>
                                </template>
                            </td>

                            <!-- Updated -->
                            <td class="border-b border-gray-100 px-3 py-2 text-right text-xs text-gray-500 dark:border-gray-800 dark:text-gray-400">
                                <span x-text="row.updated_at || row.modified_at || row.created_at || '—'"></span>
                            </td>

                            <!-- Actions -->
                            <td class="border-b border-gray-100 px-3 py-2 text-right text-xs dark:border-gray-800">
                                <div class="inline-flex items-center gap-1">
                                    <template x-if="row.id">
                                        <a :href="'<?= $h($module_base) ?>/items/' + row.id + '/edit'"
                                           class="inline-flex items-center gap-1 rounded-md border border-gray-200 px-2 py-1 font-medium text-gray-700 hover:border-emerald-500 hover:text-emerald-700 dark:border-gray-700 dark:text-gray-200 dark:hover:border-emerald-500">
                                            <i class="fa fa-pen text-[10px]"></i>
                                            <span>Edit</span>
                                        </a>
                                    </template>
                                </div>
                            </td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>
    </section>

    <!-- How to use this page -->
    <section class="rounded-2xl border border-dashed border-gray-300 bg-gray-50 p-4 text-sm text-gray-700 dark:border-gray-700 dark:bg-gray-900/60 dark:text-gray-200">
        <h2 class="mb-2 text-base font-semibold">How to use this page</h2>
        <ol class="list-decimal space-y-1 pl-5">
            <li>
                <span class="font-medium">Add new items:</span>
                use the <span class="font-semibold text-emerald-700 dark:text-emerald-300">“New item”</span> button to create
                products or services that will be used in quotes, orders, tenders and invoices.
            </li>
            <li>
                <span class="font-medium">Find items fast:</span>
                type part of the name, code or category in the search box; adjust
                <span class="font-medium">Type</span> and <span class="font-medium">Status</span> filters to narrow the list.
            </li>
            <li>
                <span class="font-medium">Check pricing in BDT:</span>
                the price column always shows values in Bangladeshi Taka
                so your team has a single currency reference everywhere.
            </li>
            <li>
                <span class="font-medium">Keep the catalog clean:</span>
                set unused items to <span class="font-medium">inactive</span> instead of deleting,
                so old documents (quotes, orders, invoices) still remain consistent.
            </li>
            <li>
                <span class="font-medium">Move across BizFlow:</span>
                use the tabs in the top right to jump between Items, Customers, Suppliers,
                Quotes and Tenders without leaving the BizFlow shell.
            </li>
        </ol>
    </section>
</div>