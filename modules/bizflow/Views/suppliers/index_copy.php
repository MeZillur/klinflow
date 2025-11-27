<?php
/** @var array $suppliers */
/** @var array $org */
/** @var string $module_base */
/** @var string $title */
/** @var string|null $search */
/** @var string|null $filter_type */
/** @var bool|null $only_active */

$h = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');

$module_base = rtrim($module_base ?? ($base ?? '/apps/bizflow'), '/');
$orgName     = $org['name'] ?? '';
$brand       = '#228B22';

$search      = (string)($search ?? ($_GET['q'] ?? ''));
$filter_type = (string)($filter_type ?? ($_GET['type'] ?? ''));
$only_active = !empty($only_active);
?>
<div class="space-y-6" x-data="{ openFilters:false }">

    <!-- Top bar: title + nav tabs -->
    <header class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
        <div class="space-y-1">
            <h1 class="text-2xl md:text-3xl font-semibold tracking-tight">
                <?= $h($title ?? 'Suppliers') ?>
            </h1>
            <p class="text-sm text-slate-500">
                Manage your supplier master data for <?= $h($orgName ?: 'your organisation') ?>.
            </p>
        </div>

        <!-- Right-aligned app tabs (BizFlow convention) -->
        <nav class="flex flex-wrap justify-end gap-1 text-sm">
            <?php
            $tabs = [
                ['Items',      $module_base.'/items'],
                ['Customers',  $module_base.'/customers'],
                ['Suppliers',  $module_base.'/suppliers'],
                ['Quotes',     $module_base.'/quotes'],
                ['Orders',     $module_base.'/orders'],
                ['Invoices',   $module_base.'/invoices'],
                ['Purchases',  $module_base.'/purchases'],
                ['Tenders',    $module_base.'/tenders'],
                ['Inventory',  $module_base.'/inventory'],
                ['Reports',    $module_base.'/reports'],
                ['Settings',   $module_base.'/settings'],
            ];
            $current = $module_base.'/suppliers';
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

    <!-- Actions + quick stats -->
    <section class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
        <!-- Left: quick stats -->
        <div class="flex flex-wrap gap-3 text-xs md:text-sm">
            <?php
            $total        = count($suppliers ?? []);
            $activeCount  = array_reduce($suppliers ?? [], fn($c, $row) => $c + ((int)($row['is_active'] ?? 0) === 1 ? 1 : 0), 0);
            ?>
            <div class="inline-flex items-center gap-2 rounded-lg border border-slate-200 bg-white px-3 py-2 shadow-sm">
                <span class="inline-flex h-6 w-6 items-center justify-center rounded-full bg-emerald-100 text-emerald-700 text-xs font-semibold">
                    <?= $h($total) ?>
                </span>
                <div>
                    <div class="font-medium text-slate-800">Suppliers</div>
                    <div class="text-[11px] text-slate-500">Total records in this tenant</div>
                </div>
            </div>

            <div class="inline-flex items-center gap-2 rounded-lg border border-slate-200 bg-white px-3 py-2 shadow-sm">
                <span class="inline-flex h-6 w-6 items-center justify-center rounded-full bg-emerald-50 text-emerald-700 text-xs font-semibold">
                    <?= $h($activeCount) ?>
                </span>
                <div>
                    <div class="font-medium text-slate-800">Active</div>
                    <div class="text-[11px] text-slate-500">Suppliers you can transact with</div>
                </div>
            </div>
        </div>

        <!-- Right: primary actions -->
        <div class="flex flex-wrap items-center justify-end gap-2">
            <button type="button"
                    class="inline-flex items-center gap-2 rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs font-medium text-slate-700 shadow-sm hover:bg-slate-50 md:hidden"
                    @click="openFilters = !openFilters">
                <i class="fa fa-filter text-[11px]"></i>
                <span>Filters</span>
            </button>

            <a href="<?= $h($module_base.'/suppliers/create') ?>"
               class="inline-flex items-center gap-2 rounded-lg bg-emerald-600 px-3 py-2 text-sm font-medium text-white shadow-sm hover:bg-emerald-700 focus:outline-none focus:ring-2 focus:ring-emerald-600 focus:ring-offset-1">
                <i class="fa fa-plus-circle text-xs"></i>
                <span>New supplier</span>
            </a>
        </div>
    </section>

    <!-- Filters -->
    <section class="rounded-xl border border-slate-200 bg-white shadow-sm">
        <form method="get"
              class="grid gap-3 border-b border-slate-100 px-4 py-3 md:grid-cols-[1.6fr,1fr,auto] md:items-end"
              :class="{'hidden md:grid': !openFilters, 'grid': openFilters}">
            <!-- Search -->
            <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">Search</label>
                <div class="relative">
                    <input type="text"
                           name="q"
                           value="<?= $h($search) ?>"
                           placeholder="Search by name, code, phone, email"
                           class="block w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500">
                    <span class="pointer-events-none absolute inset-y-0 right-3 flex items-center text-slate-400">
                        <i class="fa fa-search text-xs"></i>
                    </span>
                </div>
            </div>

            <!-- Type -->
            <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">Supplier type</label>
                <select name="type"
                        class="block w-full rounded-lg border border-slate-200 px-3 py-2 text-sm bg-white focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500">
                    <option value="">All</option>
                    <option value="local" <?= $filter_type === 'local' ? 'selected' : '' ?>>Local</option>
                    <option value="international" <?= $filter_type === 'international' ? 'selected' : '' ?>>International</option>
                </select>
            </div>

            <!-- Active toggle + actions -->
            <div class="flex flex-col gap-2 md:items-end md:justify-between">
                <label class="inline-flex items-center gap-2 text-xs text-slate-600">
                    <input type="checkbox"
                           name="active"
                           value="1"
                           class="h-3.5 w-3.5 rounded border-slate-300 text-emerald-600 focus:ring-emerald-500"
                           <?= $only_active ? 'checked' : '' ?>>
                    <span>Only active suppliers</span>
                </label>

                <div class="flex gap-2 justify-end">
                    <a href="<?= $h($module_base.'/suppliers') ?>"
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

        <!-- Table -->
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50">
                <tr class="text-xs font-semibold uppercase tracking-wide text-slate-500">
                    <th class="px-4 py-2 text-left">Code</th>
                    <th class="px-4 py-2 text-left">Name</th>
                    <th class="px-4 py-2 text-left">Contact</th>
                    <th class="px-4 py-2 text-left">Location</th>
                    <th class="px-4 py-2 text-left">Terms</th>
                    <th class="px-4 py-2 text-right">Credit limit</th>
                    <th class="px-4 py-2 text-center">Status</th>
                    <th class="px-4 py-2 text-right">Actions</th>
                </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 bg-white">
                <?php if (!empty($suppliers)): ?>
                    <?php foreach ($suppliers as $row): ?>
                        <?php
                        $code   = $row['code'] ?? '';
                        $name   = $row['name'] ?? '';
                        $type   = $row['type'] ?? 'local';
                        $city   = $row['city'] ?? '';
                        $district = $row['district'] ?? '';
                        $country  = $row['country'] ?? '';
                        $locParts = array_filter([$city, $district, $country]);
                        $loc   = implode(', ', $locParts);
                        $active = (int)($row['is_active'] ?? 0) === 1;
                        $credit = $row['credit_limit'] ?? null;
                        $phone  = $row['phone'] ?? '';
                        $email  = $row['email'] ?? '';
                        $contact= $row['contact_name'] ?? '';
                        $id     = (int)$row['id'];
                        ?>
                        <tr class="hover:bg-emerald-50/40">
                            <td class="px-4 py-2 align-top whitespace-nowrap">
                                <div class="font-mono text-xs text-slate-700">
                                    <?= $h($code ?: ('SUP-'.$id)) ?>
                                </div>
                                <div class="mt-0.5 inline-flex items-center gap-1 rounded-full border border-slate-200 bg-slate-50 px-2 py-[2px] text-[10px] uppercase tracking-wide text-slate-500">
                                    <?= $type === 'international' ? 'International' : 'Local' ?>
                                </div>
                            </td>
                            <td class="px-4 py-2 align-top">
                                <div class="font-medium text-slate-900"><?= $h($name) ?></div>
                            </td>
                            <td class="px-4 py-2 align-top text-xs text-slate-600">
                                <?php if ($contact !== ''): ?>
                                    <div class="font-medium text-slate-700"><?= $h($contact) ?></div>
                                <?php endif; ?>
                                <?php if ($phone !== ''): ?>
                                    <div><?= $h($phone) ?></div>
                                <?php endif; ?>
                                <?php if ($email !== ''): ?>
                                    <div class="text-emerald-700"><?= $h($email) ?></div>
                                <?php endif; ?>
                            </td>
                            <td class="px-4 py-2 align-top text-xs text-slate-600">
                                <?= $loc !== '' ? $h($loc) : '<span class="text-slate-400">—</span>' ?>
                            </td>
                            <td class="px-4 py-2 align-top text-xs text-slate-600">
                                <?= !empty($row['payment_terms'])
                                    ? $h($row['payment_terms'])
                                    : '<span class="text-slate-400">—</span>' ?>
                            </td>
                            <td class="px-4 py-2 align-top text-right text-xs text-slate-700">
                                <?php if ($credit !== null && $credit !== ''): ?>
                                    <span><?= $h(number_format((float)$credit, 2)) ?> BDT</span>
                                <?php else: ?>
                                    <span class="text-slate-400">—</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-4 py-2 align-top text-center">
                                <?php if ($active): ?>
                                    <span class="inline-flex items-center gap-1 rounded-full bg-emerald-50 px-2 py-[3px] text-[11px] font-medium text-emerald-700">
                                        <span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>
                                        Active
                                    </span>
                                <?php else: ?>
                                    <span class="inline-flex items-center gap-1 rounded-full bg-slate-100 px-2 py-[3px] text-[11px] font-medium text-slate-600">
                                        <span class="h-1.5 w-1.5 rounded-full bg-slate-400"></span>
                                        Inactive
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td class="px-4 py-2 align-top text-right text-xs">
                                <div class="inline-flex gap-1">
                                    <a href="<?= $h($module_base.'/suppliers/'.$id) ?>"
                                       class="inline-flex items-center rounded-lg border border-slate-200 px-2 py-1 text-[11px] text-slate-700 hover:bg-slate-50">
                                        <i class="fa-regular fa-eye mr-1 text-[10px]"></i> View
                                    </a>
                                    <a href="<?= $h($module_base.'/suppliers/'.$id.'/edit') ?>"
                                       class="inline-flex items-center rounded-lg border border-slate-200 px-2 py-1 text-[11px] text-slate-700 hover:bg-slate-50">
                                        <i class="fa-regular fa-pen-to-square mr-1 text-[10px]"></i> Edit
                                    </a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="8" class="px-4 py-8 text-center text-sm text-slate-500">
                            No suppliers found yet.
                            <a href="<?= $h($module_base.'/suppliers/create') ?>"
                               class="text-emerald-700 font-medium hover:underline">
                                Create your first supplier
                            </a>.
                        </td>
                    </tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>

    <!-- How to use this page -->
    <section class="rounded-xl border border-dashed border-emerald-400 bg-emerald-50/60 px-4 py-4 text-sm text-slate-800">
        <h2 class="mb-2 flex items-center gap-2 text-sm font-semibold text-emerald-900">
            <span class="inline-flex h-6 w-6 items-center justify-center rounded-full bg-emerald-600 text-[11px] text-white">
                ?
            </span>
            How to use this page
        </h2>
        <ul class="ml-6 list-disc space-y-1 text-[13px]">
            <li>Use <strong>Search</strong> to find suppliers by name, code, phone or email.</li>
            <li>Filter by <strong>Local</strong> or <strong>International</strong> type and hide inactive suppliers with the toggle.</li>
            <li>Click <strong>New supplier</strong> to add a vendor with full contact and payment details.</li>
            <li>Use <strong>View</strong> to see a supplier’s profile or <strong>Edit</strong> to update terms and status.</li>
            <li>All records are scoped by your current organisation (org_id) to stay tenant-safe for BizFlow.</li>
        </ul>
    </section>
</div>