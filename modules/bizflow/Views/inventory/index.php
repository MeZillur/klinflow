<?php
/**
 * BizFlow — Inventory index
 *
 * Expected vars (from InventoryController@index):
 * @var array  $org
 * @var string $module_base
 * @var string $title
 * @var array  $summary      // ['tracked_items'|'items','stock_value','below_reorder','negative_stock'|'negative']
 * @var array  $warehouses   // each ['id'=>..,'code'=>..,'name'=>..]
 * @var array  $categories   // each ['id'=>..,'name'=>..]
 * @var array  $inventory    // inventory rows (one per item, possibly per warehouse later)
 * @var array  $filters      // ['q','warehouse_id','category_id','problem_only'] (optional)
 */

$h = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');

$module_base = rtrim($module_base ?? ($base ?? '/apps/bizflow'), '/');
$orgName     = (string)($org['name'] ?? '');

$summary    = $summary    ?? [];
$warehouses = $warehouses ?? [];
$categories = $categories ?? [];
$filters    = $filters    ?? [];
$inventory  = $inventory  ?? ($rows ?? []);   // support both $rows and $inventory

// Filters (prefer controller-provided values, fall back to query string)
$search       = (string)($filters['q'] ?? ($_GET['q'] ?? ''));
$whFilter     = (string)($filters['warehouse_id'] ?? ($_GET['wh'] ?? ''));
$catFilter    = (string)($filters['category_id'] ?? ($_GET['cat'] ?? ''));
$onlyProblems = (bool)($filters['problem_only'] ?? (isset($_GET['problems']) && $_GET['problems'] === '1'));

// KPI numbers (fallbacks if summary not passed)
$totalItems = (int)(
    $summary['tracked_items']
    ?? $summary['items']
    ?? count(array_unique(array_map(
        fn($r) => $r['id'] ?? $r['item_id'] ?? null,
        $inventory
    )))
);

$totalValue = (float)(
    $summary['stock_value']
    ?? array_reduce(
        $inventory,
        fn($c, $r) => $c + (float)($r['stock_value'] ?? 0),
        0.0
    )
);

$belowReorder = (int)(
    $summary['below_reorder']
    ?? array_reduce(
        $inventory,
        fn($c, $r) =>
            $c + (
                ((float)($r['available'] ?? $r['available_qty'] ?? 0)
                    < (float)($r['reorder_level'] ?? 0)
                ) ? 1 : 0
            ),
        0
    )
);

$negativeCount = (int)(
    $summary['negative_stock']
    ?? $summary['negative']
    ?? array_reduce(
        $inventory,
        fn($c, $r) =>
            $c + (
                ((float)($r['on_hand'] ?? $r['on_hand_qty'] ?? 0) < 0)
                ? 1 : 0
            ),
        0
    )
);
?>
<div class="space-y-6" x-data="{ showFilters:true, viewMode:'table' }">

    <!-- Top: title + nav tabs -->
    <header class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
        <div class="space-y-1">
            <h1 class="text-2xl md:text-3xl font-semibold tracking-tight">
                <?= $h($title ?? 'Inventory') ?>
            </h1>
            <p class="text-sm text-slate-500">
                Realtime stock snapshot for <?= $h($orgName ?: 'your organisation') ?>, across all warehouses.
            </p>
        </div>

        <!-- Right-aligned BizFlow app tabs -->
        <nav class="flex flex-wrap justify-end gap-1 text-sm">
            <?php
            $tabs = [
                ['Items',      $module_base.'/items'],
                ['Warehouse',  $module_base.'/warehouse'],
                ['Suppliers',  $module_base.'/suppliers'],
                ['Orders',     $module_base.'/orders'],
                ['Invoices',   $module_base.'/invoices'],
                ['Purchases',  $module_base.'/purchases'],
                ['Tenders',    $module_base.'/tenders'],
                ['GRN',        $module_base.'/grn'],
            ];
            $current = $module_base.'/inventory';
            foreach ($tabs as [$label, $url]):
                $active = $url === $current;
            ?>
                <a href="<?= $h($url) ?>"
                   class="inline-flex items-center gap-1 rounded-full px-3 py-1 border text-xs md:text-[13px]
                          <?= $active
                               ? 'border-emerald-600 bg-emerald-50 text-emerald-700 font-semibold'
                               : 'border-slate-200 text-slate-600 hover:bg-slate-50' ?>">
                    <span><?= $h($label) ?></span>
                </a>
            <?php endforeach; ?>
        </nav>
    </header>

    <!-- As-of + primary actions -->
    <section class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
        <div class="flex flex-wrap items-center gap-3 text-xs md:text-sm">
            <div class="inline-flex items-center gap-2 rounded-lg border border-slate-200 bg-white px-3 py-2 shadow-sm">
                <span class="text-slate-500">As of</span>
                <input type="date"
                       name="as_of"
                       value="<?= $h($_GET['as_of'] ?? '') ?>"
                       class="rounded-md border border-slate-200 px-2 py-1 text-xs focus:border-emerald-500 focus:ring-emerald-500">
                <button type="submit"
                        form="invFilterForm"
                        class="inline-flex items-center gap-1 rounded-md bg-slate-900 px-2.5 py-1.5 text-xs font-medium text-white hover:bg-slate-800">
                    <i class="fa fa-rotate-right text-[11px]"></i>
                    <span>Refresh</span>
                </button>
            </div>
            <button type="button"
                    class="inline-flex items-center gap-2 rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs font-medium text-slate-700 shadow-sm hover:bg-slate-50 md:hidden"
                    @click="showFilters = !showFilters">
                <i class="fa fa-filter text-[11px]"></i>
                <span>Filters</span>
            </button>
        </div>

        <div class="flex flex-wrap items-center justify-end gap-2">
            <a href="<?= $h($module_base.'/inventory/transfers/create') ?>"
               class="inline-flex items-center gap-2 rounded-lg border border-emerald-600 px-3 py-2 text-xs font-medium text-emerald-700 bg-emerald-50 hover:bg-emerald-100">
                <i class="fa fa-arrows-left-right text-[11px]"></i>
                <span>Transfer stock</span>
            </a>
            <a href="<?= $h($module_base.'/inventory/adjustments/create') ?>"
               class="inline-flex items-center gap-2 rounded-lg bg-emerald-600 px-3 py-2 text-sm font-medium text-white shadow-sm hover:bg-emerald-700 focus:outline-none focus:ring-2 focus:ring-emerald-600 focus:ring-offset-1">
                <i class="fa fa-plus-circle text-xs"></i>
                <span>New adjustment</span>
            </a>
        </div>
    </section>

    <!-- KPI row -->
    <section class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
        <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
            <div class="flex items-center justify-between text-xs text-slate-500">
                <span>Tracked items</span>
                <i class="fa fa-boxes-stacked text-[11px] text-emerald-600"></i>
            </div>
            <div class="mt-1 text-2xl font-semibold text-slate-900">
                <?= $h(number_format($totalItems)) ?>
            </div>
            <p class="mt-1 text-xs text-slate-500">
                Unique SKUs with inventory records.
            </p>
        </div>

        <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
            <div class="flex items-center justify-between text-xs text-slate-500">
                <span>Stock value</span>
                <i class="fa fa-money-bill-wave text-[11px] text-emerald-600"></i>
            </div>
            <div class="mt-1 text-2xl font-semibold text-slate-900">
                <?= $h(number_format($totalValue, 2)) ?> <span class="text-xs font-normal">BDT</span>
            </div>
            <p class="mt-1 text-xs text-slate-500">
                Moving-average valuation across all warehouses.
            </p>
        </div>

        <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
            <div class="flex items-center justify-between text-xs text-slate-500">
                <span>Below reorder</span>
                <i class="fa fa-triangle-exclamation text-[11px] text-amber-600"></i>
            </div>
            <div class="mt-1 text-2xl font-semibold text-amber-700">
                <?= $h(number_format($belowReorder)) ?>
            </div>
            <p class="mt-1 text-xs text-slate-500">
                Items where available &lt; reorder level.
            </p>
        </div>

        <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
            <div class="flex items-center justify-between text-xs text-slate-500">
                <span>Negative stock</span>
                <i class="fa fa-battery-empty text-[11px] text-rose-600"></i>
            </div>
            <div class="mt-1 text-2xl font-semibold <?= $negativeCount > 0 ? 'text-rose-700' : 'text-slate-900' ?>">
                <?= $h(number_format($negativeCount)) ?>
            </div>
            <p class="mt-1 text-xs text-slate-500">
                Investigate and correct via adjustments.
            </p>
        </div>
    </section>

    <!-- Filters + view controls -->
    <section class="rounded-xl border border-slate-200 bg-white shadow-sm">
        <form id="invFilterForm"
              method="get"
              class="grid gap-3 border-b border-slate-100 px-4 py-3 md:grid-cols-[1.6fr,repeat(3,minmax(0,1fr)),auto] md:items-end"
              :class="{'hidden md:grid': !showFilters, 'grid': showFilters}">
            <!-- Search -->
            <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">Search</label>
                <div class="relative">
                    <input type="text"
                           name="q"
                           value="<?= $h($search) ?>"
                           placeholder="Search by code, name, barcode"
                           class="block w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500">
                    <span class="pointer-events-none absolute inset-y-0 right-3 flex items-center text-slate-400">
                        <i class="fa fa-search text-xs"></i>
                    </span>
                </div>
            </div>

            <!-- Warehouse -->
            <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">Warehouse</label>
                <select name="wh"
                        class="block w-full rounded-lg border border-slate-200 px-3 py-2 text-sm bg-white focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500"
                        data-choices>
                    <option value="">All warehouses</option>
                    <?php foreach ($warehouses as $wh): ?>
                        <?php
                        $wid  = (string)($wh['id'] ?? '');
                        $wlab = trim(($wh['code'] ?? '').' — '.($wh['name'] ?? ''));
                        ?>
                        <option value="<?= $h($wid) ?>" <?= $wid === $whFilter ? 'selected' : '' ?>>
                            <?= $h($wlab ?: ($wh['name'] ?? 'Warehouse')) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Category -->
            <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">Category</label>
                <select name="cat"
                        class="block w-full rounded-lg border border-slate-200 px-3 py-2 text-sm bg-white focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500"
                        data-choices>
                    <option value="">All categories</option>
                    <?php foreach ($categories as $cat): ?>
                        <?php $cid = (string)($cat['id'] ?? ''); ?>
                        <option value="<?= $h($cid) ?>" <?= $cid === $catFilter ? 'selected' : '' ?>>
                            <?= $h($cat['name'] ?? 'Category') ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Problem items toggle -->
            <div class="space-y-2">
                <label class="inline-flex items-center gap-2 text-xs text-slate-600">
                    <input type="checkbox"
                           name="problems"
                           value="1"
                           class="h-3.5 w-3.5 rounded border-slate-300 text-emerald-600 focus:ring-emerald-500"
                           <?= $onlyProblems ? 'checked' : '' ?>>
                    <span>Only problem items (below reorder / negative)</span>
                </label>
            </div>

            <!-- Actions + view toggle -->
            <div class="flex flex-col gap-2 md:items-end md:justify-between">
                <div class="flex gap-1 self-end rounded-full border border-slate-200 bg-slate-50 p-1 text-[11px] text-slate-600">
                    <button type="button"
                            class="inline-flex items-center gap-1 rounded-full px-2 py-1"
                            :class="viewMode==='table' ? 'bg-white shadow-sm text-slate-900' : ''"
                            @click="viewMode='table'">
                        <i class="fa fa-table"></i><span class="hidden sm:inline">Table</span>
                    </button>
                    <button type="button"
                            class="inline-flex items-center gap-1 rounded-full px-2 py-1"
                            :class="viewMode==='cards' ? 'bg-white shadow-sm text-slate-900' : ''"
                            @click="viewMode='cards'">
                        <i class="fa fa-grip"></i><span class="hidden sm:inline">Cards</span>
                    </button>
                </div>

                <div class="flex gap-2 justify-end">
                    <a href="<?= $h($module_base.'/inventory') ?>"
                       class="inline-flex items-center gap-1 rounded-lg border border-slate-200 px-2.5 py-1.5 text-xs font-medium text-slate-600 hover:bg-slate-50">
                        <i class="fa fa-rotate-left text-[11px]"></i>
                        <span>Reset</span>
                    </a>
                    <button type="submit"
                            class="inline-flex items-center gap-1 rounded-lg bg-slate-900 px-3 py-1.5 text-xs font-medium text-white shadow-sm hover:bg-slate-800">
                        <i class="fa fa-magnifying-glass text-[11px]"></i>
                        <span>Apply</span>
                    </button>
                </div>
            </div>
        </form>

        <!-- Inventory table view -->
        <div x-show="viewMode === 'table'" x-cloak class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50">
                <tr class="text-xs font-semibold uppercase tracking-wide text-slate-500">
                    <th class="px-4 py-2 text-left">Item</th>
                    <th class="px-4 py-2 text-left">Warehouse</th>
                    <th class="px-4 py-2 text-right">On hand</th>
                    <th class="px-4 py-2 text-right">Reserved</th>
                    <th class="px-4 py-2 text-right">Available</th>
                    <th class="px-4 py-2 text-right">Incoming</th>
                    <th class="px-4 py-2 text-right">Reorder</th>
                    <th class="px-4 py-2 text-right">Value (BDT)</th>
                    <th class="px-4 py-2 text-left">Last movement</th>
                </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 bg-white">
                <?php if (!empty($inventory)): ?>
                    <?php foreach ($inventory as $row): ?>
                        <?php
                        // Support both old and new column names
                        $onHand   = (float)($row['on_hand'] ?? $row['on_hand_qty'] ?? 0);
                        $reserved = (float)($row['reserved'] ?? $row['reserved_qty'] ?? 0);
                        $avail    = (float)($row['available'] ?? $row['available_qty'] ?? $onHand - $reserved);
                        $incoming = (float)($row['incoming_qty'] ?? 0);
                        $reorder  = (float)($row['reorder_level'] ?? 0);
                        $value    = (float)($row['stock_value'] ?? 0);

                        $itemCode = (string)($row['code'] ?? $row['item_code'] ?? '');
                        $itemName = (string)($row['name'] ?? $row['item_name'] ?? '');
                        $sku      = (string)($row['sku'] ?? '');
                        $unit     = (string)($row['unit'] ?? '');

                        $whName   = (string)($row['warehouse_name'] ?? 'All warehouses');
                        $whCode   = (string)($row['warehouse_code'] ?? '');

                        $lastMove = (string)($row['last_movement'] ?? $row['last_movement_at'] ?? '');

                        $problem  = ($onHand < 0) || ($avail < $reorder && $reorder > 0);
                        ?>
                        <tr class="<?= $problem ? 'bg-amber-50/60 hover:bg-amber-100/80' : 'hover:bg-emerald-50/40' ?>">
                            <td class="px-4 py-2 align-top">
                                <div class="flex flex-col gap-0.5">
                                    <div class="text-sm font-medium text-slate-900">
                                        <?= $h($itemName ?: '—') ?>
                                    </div>
                                    <div class="flex flex-wrap gap-1 text-[11px] text-slate-500">
                                        <?php if ($itemCode !== ''): ?>
                                            <span class="inline-flex items-center gap-1 rounded-full bg-slate-50 px-2 py-[2px] font-mono">
                                                <i class="fa fa-hashtag text-[9px]"></i><?= $h($itemCode) ?>
                                            </span>
                                        <?php endif; ?>
                                        <?php if ($sku !== ''): ?>
                                            <span class="inline-flex items-center gap-1 rounded-full bg-slate-50 px-2 py-[2px] font-mono">
                                                SKU <?= $h($sku) ?>
                                            </span>
                                        <?php endif; ?>
                                        <?php if ($unit !== ''): ?>
                                            <span class="inline-flex items-center gap-1 rounded-full bg-emerald-50 px-2 py-[2px] text-emerald-700">
                                                <?= $h($unit) ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </td>
                            <td class="px-4 py-2 align-top text-xs text-slate-600">
                                <div class="font-medium text-slate-800"><?= $h($whName ?: '—') ?></div>
                                <?php if ($whCode !== ''): ?>
                                    <div class="text-[11px] text-slate-500"><?= $h($whCode) ?></div>
                                <?php endif; ?>
                            </td>
                            <td class="px-4 py-2 align-top text-right text-sm text-slate-800 <?= $onHand < 0 ? 'text-rose-700' : '' ?>">
                                <?= $h(number_format($onHand, 2)) ?>
                            </td>
                            <td class="px-4 py-2 align-top text-right text-sm text-slate-700">
                                <?= $h(number_format($reserved, 2)) ?>
                            </td>
                            <td class="px-4 py-2 align-top text-right text-sm <?= ($reorder > 0 && $avail < $reorder) ? 'text-amber-700' : 'text-slate-800' ?>">
                                <?= $h(number_format($avail, 2)) ?>
                            </td>
                            <td class="px-4 py-2 align-top text-right text-sm text-slate-700">
                                <?= $h(number_format($incoming, 2)) ?>
                            </td>
                            <td class="px-4 py-2 align-top text-right text-sm text-slate-700">
                                <?= $reorder > 0 ? $h(number_format($reorder, 2)) : '<span class="text-slate-400">—</span>' ?>
                            </td>
                            <td class="px-4 py-2 align-top text-right text-sm text-slate-800">
                                <?= $value !== 0.0 ? $h(number_format($value, 2)) : '<span class="text-slate-400">—</span>' ?>
                            </td>
                            <td class="px-4 py-2 align-top text-xs text-slate-600">
                                <?= $lastMove !== '' ? $h($lastMove) : '<span class="text-slate-400">—</span>' ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="9" class="px-4 py-8 text-center text-sm text-slate-500">
                            No inventory records yet.
                            Once you post purchases or adjustments, stock will appear here.
                        </td>
                    </tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Card / heatmap style view -->
        <div x-show="viewMode === 'cards'" x-cloak class="p-4">
            <?php if (!empty($inventory)): ?>
                <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                    <?php foreach ($inventory as $row): ?>
                        <?php
                        $avail   = (float)($row['available'] ?? $row['available_qty'] ?? 0);
                        $reorder = (float)($row['reorder_level'] ?? 0);
                        $value   = (float)($row['stock_value'] ?? 0);
                        $onHand  = (float)($row['on_hand'] ?? $row['on_hand_qty'] ?? 0);

                        $itemName = (string)($row['name'] ?? $row['item_name'] ?? '');
                        $itemCode = (string)($row['code'] ?? $row['item_code'] ?? '');
                        $whName   = (string)($row['warehouse_name'] ?? 'All warehouses');

                        $problem  = ($onHand < 0) || ($reorder > 0 && $avail < $reorder);
                        ?>
                        <article class="rounded-2xl border border-slate-200 bg-gradient-to-br from-white to-emerald-50/30 p-3 shadow-sm">
                            <header class="mb-2 flex items-start justify-between gap-2">
                                <div>
                                    <h3 class="text-sm font-semibold text-slate-900 line-clamp-2">
                                        <?= $h($itemName ?: 'Item') ?>
                                    </h3>
                                    <?php if ($itemCode !== ''): ?>
                                        <p class="text-[11px] text-slate-500 font-mono mt-0.5">
                                            <?= $h($itemCode) ?>
                                        </p>
                                    <?php endif; ?>
                                </div>
                                <span class="inline-flex items-center gap-1 rounded-full px-2 py-[3px] text-[10px]
                                             <?= $problem ? 'bg-amber-100 text-amber-800' : 'bg-emerald-100 text-emerald-800' ?>">
                                    <span class="h-1.5 w-1.5 rounded-full <?= $problem ? 'bg-amber-600' : 'bg-emerald-600' ?>"></span>
                                    <?= $problem ? 'Attention' : 'Healthy' ?>
                                </span>
                            </header>
                            <dl class="grid grid-cols-2 gap-2 text-[11px] text-slate-600">
                                <div>
                                    <dt class="text-slate-500">Warehouse</dt>
                                    <dd class="font-medium text-slate-800"><?= $h($whName ?: '—') ?></dd>
                                </div>
                                <div>
                                    <dt class="text-slate-500">Available</dt>
                                    <dd class="font-mono <?= ($reorder > 0 && $avail < $reorder) ? 'text-amber-700' : 'text-slate-800' ?>">
                                        <?= $h(number_format($avail, 2)) ?>
                                    </dd>
                                </div>
                                <div>
                                    <dt class="text-slate-500">Reorder level</dt>
                                    <dd class="font-mono text-slate-700">
                                        <?= $reorder > 0 ? $h(number_format($reorder, 2)) : '—' ?>
                                    </dd>
                                </div>
                                <div>
                                    <dt class="text-slate-500">Stock value</dt>
                                    <dd class="font-mono text-slate-800">
                                        <?= $value !== 0.0 ? $h(number_format($value, 2)) : '—' ?> BDT
                                    </dd>
                                </div>
                            </dl>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p class="text-center text-sm text-slate-500">
                    No inventory cards to show yet.
                </p>
            <?php endif; ?>
        </div>
    </section>
</div>