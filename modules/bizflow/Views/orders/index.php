<?php
/** @var array  $orders */
/** @var array  $metrics */
/** @var array  $org */
/** @var string $module_base */
/** @var string $title */
/** @var string|null $search */
/** @var string|null $filterStatus */
/** @var string|null $from */
/** @var string|null $to */
/** @var bool|null   $only_open */

$h = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');

$module_base  = rtrim($module_base ?? ($base ?? '/apps/bizflow'), '/');
$orgName      = $org['name'] ?? '';

$search       = (string)($search ?? ($_GET['q'] ?? ''));
$filterStatus = (string)($filterStatus ?? ($_GET['status'] ?? ''));
$from         = (string)($from ?? ($_GET['from'] ?? ''));
$to           = (string)($to ?? ($_GET['to'] ?? ''));
$only_open    = !empty($only_open);
?>
<div class="space-y-6" x-data="{ openFilters:false }">

    <!-- Top bar: title + nav tabs -->
    <header class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
        <div class="space-y-1">
            <h1 class="text-2xl md:text-3xl font-semibold tracking-tight">
                <?= $h($title ?? 'Orders') ?>
            </h1>
            <p class="text-sm text-slate-500">
                Track sales orders and fulfillment status for <?= $h($orgName ?: 'your organisation') ?>.
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
            $current = $module_base.'/orders';
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
            $total       = (int)($metrics['order_count']     ?? 0);
            $openCount   = (int)($metrics['open_count']      ?? 0);
            $draftCount  = (int)($metrics['draft_count']     ?? 0);
            $totalValue  = (float)($metrics['total_value']   ?? 0);
            $openValue   = (float)($metrics['open_value']    ?? 0);
            ?>
            <div class="inline-flex items-center gap-2 rounded-lg border border-slate-200 bg-white px-3 py-2 shadow-sm">
                <span class="inline-flex h-6 w-6 items-center justify-center rounded-full bg-emerald-100 text-emerald-700 text-xs font-semibold">
                    <?= $h($total) ?>
                </span>
                <div>
                    <div class="font-medium text-slate-800">Orders</div>
                    <div class="text-[11px] text-slate-500">Total documents in this tenant</div>
                </div>
            </div>

            <div class="inline-flex items-center gap-2 rounded-lg border border-slate-200 bg-white px-3 py-2 shadow-sm">
                <span class="inline-flex h-6 w-6 items-center justify-center rounded-full bg-amber-50 text-amber-700 text-xs font-semibold">
                    <?= $h($openCount) ?>
                </span>
                <div>
                    <div class="font-medium text-slate-800">Open</div>
                    <div class="text-[11px] text-slate-500">Awaiting fulfillment / invoicing</div>
                </div>
            </div>

            <div class="inline-flex items-center gap-2 rounded-lg border border-slate-200 bg-white px-3 py-2 shadow-sm">
                <div>
                    <div class="text-[11px] text-slate-500">Order value</div>
                    <div class="text-xs font-semibold text-slate-800">
                        <?= $totalValue > 0 ? $h(number_format($totalValue, 2)).' BDT' : '—' ?>
                    </div>
                    <div class="text-[11px] text-amber-700">
                        Open: <?= $openValue > 0 ? $h(number_format($openValue, 2)).' BDT' : '—' ?>
                    </div>
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

            <a href="<?= $h($module_base.'/orders/create') ?>"
               class="inline-flex items-center gap-2 rounded-lg bg-emerald-600 px-3 py-2 text-sm font-medium text-white shadow-sm hover:bg-emerald-700 focus:outline-none focus:ring-2 focus:ring-emerald-600 focus:ring-offset-1">
                <i class="fa fa-plus-circle text-xs"></i>
                <span>New order</span>
            </a>
        </div>
    </section>

    <!-- Filters -->
    <section class="rounded-xl border border-slate-200 bg-white shadow-sm">
        <form method="get"
              class="grid gap-3 border-b border-slate-100 px-4 py-3
                     md:grid-cols-[1.5fr,1fr,1.2fr,auto] md:items-end"
              :class="{'hidden md:grid': !openFilters, 'grid': openFilters}">
            <!-- Search -->
            <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">Search</label>
                <div class="relative">
                    <input type="text"
                           name="q"
                           value="<?= $h($search) ?>"
                           placeholder="Search by order #, external ref, customer"
                           class="block w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500">
                    <span class="pointer-events-none absolute inset-y-0 right-3 flex items-center text-slate-400">
                        <i class="fa fa-search text-xs"></i>
                    </span>
                </div>
            </div>

            <!-- Status -->
            <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">Status</label>
                <select name="status"
                        class="block w-full rounded-lg border border-slate-200 px-3 py-2 text-sm bg-white focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500">
                    <option value="">All</option>
                    <?php
                    $statuses = [
                        'draft'             => 'Draft',
                        'pending'           => 'Pending',
                        'confirmed'         => 'Confirmed',
                        'partially_shipped' => 'Partially shipped',
                        'completed'         => 'Completed',
                        'cancelled'         => 'Cancelled',
                    ];
                    foreach ($statuses as $key => $label):
                    ?>
                        <option value="<?= $h($key) ?>" <?= $filterStatus === $key ? 'selected' : '' ?>>
                            <?= $h($label) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Date range -->
            <div class="grid grid-cols-2 gap-2">
                <div>
                    <label class="block text-xs font-medium text-slate-600 mb-1">From</label>
                    <input type="date"
                           name="from"
                           value="<?= $h($from) ?>"
                           class="block w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500">
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-600 mb-1">To</label>
                    <input type="date"
                           name="to"
                           value="<?= $h($to) ?>"
                           class="block w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500">
                </div>
            </div>

            <!-- Open toggle + actions -->
            <div class="flex flex-col gap-2 md:items-end md:justify-between">
                <label class="inline-flex items-center gap-2 text-xs text-slate-600">
                    <input type="checkbox"
                           name="only_open"
                           value="1"
                           class="h-3.5 w-3.5 rounded border-slate-300 text-emerald-600 focus:ring-emerald-500"
                           <?= $only_open ? 'checked' : '' ?>>
                    <span>Only open orders</span>
                </label>

                <div class="flex gap-2 justify-end">
                    <a href="<?= $h($module_base.'/orders') ?>"
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
                    <th class="px-4 py-2 text-left">Order #</th>
                    <th class="px-4 py-2 text-left">Date</th>
                    <th class="px-4 py-2 text-left">Customer</th>
                    <th class="px-4 py-2 text-left">Status</th>
                    <th class="px-4 py-2 text-left">Expected ship</th>
                    <th class="px-4 py-2 text-right">Amount</th>
                    <th class="px-4 py-2 text-right">Actions</th>
                </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 bg-white">
                <?php if (!empty($orders)): ?>
                    <?php foreach ($orders as $row): ?>
                        <?php
                        $id       = (int)($row['id'] ?? 0);
                        $no       = $row['order_no']         ?? '';
                        $extRef   = $row['external_ref']     ?? '';
                        $date     = $row['date']             ?? ($row['created_at'] ?? '');
                        $custName = $row['customer_name']    ?? '';
                        $status   = $row['status']           ?? '';
                        $shipDate = $row['expected_ship_date'] ?? '';
                        $amt      = $row['grand_total']      ?? null;

                        $statusLabel = $status !== '' ? ucfirst(str_replace('_',' ', $status)) : '—';
                        $statusClass = match ($status) {
                            'draft'             => 'bg-slate-100 text-slate-700',
                            'pending'           => 'bg-amber-50 text-amber-700',
                            'confirmed',
                            'partially_shipped' => 'bg-sky-50 text-sky-700',
                            'completed'         => 'bg-emerald-50 text-emerald-700',
                            'cancelled'         => 'bg-rose-50 text-rose-700',
                            default             => 'bg-slate-100 text-slate-600',
                        };
                        ?>
                        <tr class="hover:bg-emerald-50/40">
                            <td class="px-4 py-2 align-top whitespace-nowrap">
                                <div class="font-mono text-xs text-slate-800">
                                    <?= $h($no !== '' ? $no : ('SO-'.$id)) ?>
                                </div>
                                <?php if ($extRef !== ''): ?>
                                    <div class="mt-0.5 text-[11px] text-slate-500">
                                        Ref: <?= $h($extRef) ?>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td class="px-4 py-2 align-top text-xs text-slate-600 whitespace-nowrap">
                                <?= $h($date ?: '—') ?>
                            </td>
                            <td class="px-4 py-2 align-top text-xs text-slate-700">
                                <?= $custName !== '' ? $h($custName) : '<span class="text-slate-400">—</span>' ?>
                            </td>
                            <td class="px-4 py-2 align-top text-center">
                                <span class="inline-flex items-center gap-1 rounded-full px-2 py-[3px] text-[11px] font-medium <?= $statusClass ?>">
                                    <span class="h-1.5 w-1.5 rounded-full bg-current"></span>
                                    <?= $h($statusLabel) ?>
                                </span>
                            </td>
                            <td class="px-4 py-2 align-top text-xs text-slate-600 whitespace-nowrap">
                                <?= $shipDate !== '' ? $h($shipDate) : '<span class="text-slate-400">—</span>' ?>
                            </td>
                            <td class="px-4 py-2 align-top text-right text-xs text-slate-800 whitespace-nowrap">
                                <?php if ($amt !== null && $amt !== ''): ?>
                                    <?= $h(number_format((float)$amt, 2)) ?> BDT
                                <?php else: ?>
                                    <span class="text-slate-400">—</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-4 py-2 align-top text-right text-xs">
                                <div class="inline-flex gap-1">
                                    <a href="<?= $h($module_base.'/orders/'.$id) ?>"
                                       class="inline-flex items-center rounded-lg border border-slate-200 px-2 py-1 text-[11px] text-slate-700 hover:bg-slate-50">
                                        <i class="fa-regular fa-eye mr-1 text-[10px]"></i> View
                                    </a>
                                    <a href="<?= $h($module_base.'/orders/'.$id.'/edit') ?>"
                                       class="inline-flex items-center rounded-lg border border-slate-200 px-2 py-1 text-[11px] text-slate-700 hover:bg-slate-50">
                                        <i class="fa-regular fa-pen-to-square mr-1 text-[10px]"></i> Edit
                                    </a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="7" class="px-4 py-8 text-center text-sm text-slate-500">
                            No orders found yet.
                            <a href="<?= $h($module_base.'/orders/create') ?>"
                               class="text-emerald-700 font-medium hover:underline">
                                Create your first order
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
            <li>Use <strong>Search</strong> to find orders by number, external reference, or customer name.</li>
            <li>Filter by <strong>Status</strong> and <strong>Date range</strong> to focus on a specific pipeline slice.</li>
            <li>Toggle <strong>Only open orders</strong> to see work-in-progress that needs attention.</li>
            <li>Click <strong>New order</strong> to start a new sales order for an existing customer.</li>
            <li>Use <strong>View</strong> to open the full 360° order history and <strong>Edit</strong> to update details.</li>
            <li>All numbers are scoped by <strong>org_id</strong> and safe for multi-tenant BizFlow.</li>
        </ul>
    </section>
</div>