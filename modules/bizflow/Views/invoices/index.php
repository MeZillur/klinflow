<?php
/** @var array       $invoices */
/** @var array       $metrics */
/** @var array       $org */
/** @var string      $module_base */
/** @var string      $title */
/** @var string|null $search */
/** @var string|null $status */
/** @var string|null $date_from */
/** @var string|null $date_to */
/** @var bool        $overdue_only */

$h = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');

$module_base  = rtrim($module_base ?? ($base ?? '/apps/bizflow'), '/');
$orgName      = $org['name'] ?? '';

$search       = (string)($search ?? ($_GET['q'] ?? ''));
$status       = (string)($status ?? ($_GET['status'] ?? ''));
$date_from    = (string)($date_from ?? ($_GET['date_from'] ?? ''));
$date_to      = (string)($date_to ?? ($_GET['date_to'] ?? ''));
$overdue_only = (bool)($overdue_only ?? (!empty($_GET['overdue'])));

$newInvoiceUrl = $module_base . '/invoices/create';

$brand  = '#228B22';

// Metrics with safe defaults
$total       = (int)($metrics['total']        ?? 0);
$unpaid      = (int)($metrics['unpaid']       ?? 0);
$overdue     = (int)($metrics['overdue']      ?? 0);
$month_sum   = (float)($metrics['sum_month']  ?? 0.0);
?>
<div class="space-y-6" x-data="{ openFilters:false }">

    <!-- HEADER + APP TABS -->
    <header class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
        <div class="space-y-1">
            <div class="inline-flex items-center gap-2 text-xs font-semibold tracking-wide text-emerald-700 bg-emerald-50 border border-emerald-100 px-2.5 py-1 uppercase">
                <i class="fa-regular fa-file-invoice text-[11px]"></i>
                <span>Invoice workspace</span>
            </div>
            <h1 class="text-2xl md:text-3xl font-semibold tracking-tight">
                <?= $h($title ?? 'Invoices') ?><?= $orgName ? ' — '.$h($orgName) : '' ?>
            </h1>
            <p class="text-sm text-slate-500">
                Track invoices, payments and outstanding balances for
                <?= $h($orgName ?: 'your organisation') ?>.
            </p>
        </div>

        <!-- BizFlow app tabs (right aligned) -->
        <nav class="flex flex-row-reverse flex-wrap gap-1 text-xs md:text-[13px]">
            <?php
            $tabs = [
                ['Settings',  $module_base.'/settings',  'fa-sliders'],
                ['Reports',   $module_base.'/reports',   'fa-chart-line'],
                ['Inventory', $module_base.'/inventory', 'fa-boxes-stacked'],
                ['Orders',    $module_base.'/orders',    'fa-cart-flatbed'],
                ['Quotes',    $module_base.'/quotes',    'fa-file-lines'],
                ['Customers', $module_base.'/customers', 'fa-user'],
            ];
            $current = $module_base.'/invoices';
            foreach ($tabs as [$label, $url, $icon]):
                $active = ($url === $current);
            ?>
                <a href="<?= $h($url) ?>"
                   class="inline-flex items-center gap-1 rounded-full px-3 py-1.5 border
                          <?= $active
                               ? 'border-emerald-600 bg-emerald-600 text-white font-semibold'
                               : 'border-slate-200 bg-white text-slate-700 hover:bg-slate-50' ?>">
                    <i class="fa-regular <?= $h($icon) ?> text-[11px]"></i>
                    <span><?= $h($label) ?></span>
                </a>
            <?php endforeach; ?>
        </nav>
    </header>

    <!-- METRICS + ACTIONS -->
    <section class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
        <!-- Metrics cards -->
        <div class="flex flex-wrap gap-3 text-xs md:text-sm">
            <!-- Total invoices -->
            <div class="inline-flex items-center gap-2 rounded-lg border border-slate-200 bg-white px-3 py-2 shadow-sm">
                <span class="inline-flex h-7 w-7 items-center justify-center rounded-full bg-emerald-100 text-emerald-700 text-xs font-semibold">
                    <?= $h($total) ?>
                </span>
                <div>
                    <div class="font-medium text-slate-800">Invoices</div>
                    <div class="text-[11px] text-slate-500">Total documents in this tenant</div>
                </div>
            </div>

            <!-- Open invoices -->
            <div class="inline-flex items-center gap-2 rounded-lg border border-slate-200 bg-white px-3 py-2 shadow-sm">
                <span class="inline-flex h-7 w-7 items-center justify-center rounded-full bg-amber-50 text-amber-700 text-xs font-semibold">
                    <?= $h($unpaid) ?>
                </span>
                <div>
                    <div class="font-medium text-slate-800">Open invoices</div>
                    <div class="text-[11px] text-slate-500">Balance &gt; 0 BDT</div>
                </div>
            </div>

            <!-- Overdue -->
            <div class="inline-flex items-center gap-2 rounded-lg border border-slate-200 bg-white px-3 py-2 shadow-sm">
                <span class="inline-flex h-7 w-7 items-center justify-center rounded-full bg-rose-50 text-rose-700 text-xs font-semibold">
                    <?= $h($overdue) ?>
                </span>
                <div>
                    <div class="font-medium text-slate-800">Overdue</div>
                    <div class="text-[11px] text-slate-500">Past due date &amp; unpaid</div>
                </div>
            </div>

            <!-- This month billed -->
            <div class="inline-flex items-center gap-2 rounded-lg border border-slate-200 bg-white px-3 py-2 shadow-sm">
                <span class="inline-flex h-7 w-7 items-center justify-center rounded-full bg-emerald-50 text-emerald-700 text-xs font-semibold">
                    ৳
                </span>
                <div>
                    <div class="font-medium text-slate-800">This month</div>
                    <div class="text-[11px] text-slate-500">
                        Billed <?= $h(number_format($month_sum, 2)) ?> BDT
                    </div>
                </div>
            </div>
        </div>

        <!-- Actions -->
        <div class="flex flex-wrap items-center justify-end gap-2">
            <!-- Mobile filters toggle -->
            <button type="button"
                    class="inline-flex items-center gap-2 rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs font-medium text-slate-700 shadow-sm hover:bg-slate-50 md:hidden"
                    @click="openFilters = !openFilters">
                <i class="fa fa-filter text-[11px]"></i>
                <span>Filters</span>
            </button>

            <!-- New invoice -->
            <a href="<?= $h($newInvoiceUrl) ?>"
               class="inline-flex items-center gap-2 rounded-lg px-3 py-2 text-xs md:text-sm font-medium text-white shadow-sm hover:shadow-md"
               style="background: <?= $h($brand) ?>;">
                <i class="fa fa-plus-circle text-[11px]"></i>
                <span>New invoice</span>
            </a>
        </div>
    </section>

    <!-- FILTER BAR -->
    <section class="rounded-xl border border-slate-200 bg-white shadow-sm">
        <form method="get"
              class="grid gap-3 border-b border-slate-100 px-4 py-3 md:grid-cols-[minmax(0,1.7fr),minmax(0,1.2fr),auto] md:items-end"
              :class="{'hidden md:grid': !openFilters, 'grid': openFilters}">
            <!-- Search -->
            <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">Search</label>
                <div class="relative">
                    <input type="text"
                           name="q"
                           value="<?= $h($search) ?>"
                           placeholder="Invoice no, customer, reference…"
                           class="block w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500">
                    <span class="pointer-events-none absolute inset-y-0 right-3 flex items-center text-slate-400">
                        <i class="fa fa-magnifying-glass text-xs"></i>
                    </span>
                </div>
            </div>

            <!-- Status + date range -->
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                <div>
                    <label class="block text-xs font-medium text-slate-600 mb-1">Status</label>
                    <select name="status"
                            class="block w-full rounded-lg border border-slate-200 px-3 py-2 text-sm bg-white focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500">
                        <option value="">All</option>
                        <?php
                        $statuses = ['draft','sent','part_paid','paid','void'];
                        foreach ($statuses as $st):
                            $sel = $status === $st ? 'selected' : '';
                        ?>
                            <option value="<?= $h($st) ?>" <?= $sel ?>>
                                <?= $h(ucfirst(str_replace('_', ' ', $st))) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="grid grid-cols-2 gap-2">
                    <div>
                        <label class="block text-xs font-medium text-slate-600 mb-1">From date</label>
                        <input type="date"
                               name="date_from"
                               value="<?= $h($date_from) ?>"
                               class="block w-full rounded-lg border border-slate-200 px-2.5 py-2 text-sm focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-600 mb-1">To date</label>
                        <input type="date"
                               name="date_to"
                               value="<?= $h($date_to) ?>"
                               class="block w-full rounded-lg border border-slate-200 px-2.5 py-2 text-sm focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500">
                    </div>
                </div>
            </div>

            <!-- Overdue + filter actions -->
            <div class="flex flex-col gap-2 md:items-end md:justify-between">
                <label class="inline-flex items-center gap-2 text-xs text-slate-600">
                    <input type="checkbox"
                           name="overdue"
                           value="1"
                           class="h-3.5 w-3.5 rounded border-slate-300 text-emerald-600 focus:ring-emerald-500"
                           <?= $overdue_only ? 'checked' : '' ?>>
                    <span>Only overdue invoices</span>
                </label>

                <div class="flex gap-2 justify-end">
                    <a href="<?= $h($module_base.'/invoices') ?>"
                       class="inline-flex items-center gap-1 rounded-lg border border-slate-200 px-2.5 py-1.5 text-xs font-medium text-slate-600 hover:bg-slate-50">
                        <i class="fa fa-rotate-left text-[11px]"></i>
                        <span>Reset</span>
                    </a>
                    <button type="submit"
                            class="inline-flex items-center gap-1 rounded-lg bg-slate-900 px-3 py-1.5 text-xs font-medium text-white shadow-sm hover:bg-slate-800">
                        <i class="fa fa-filter text-[11px]"></i>
                        <span>Apply</span>
                    </button>
                </div>
            </div>
        </form>

        <!-- INVOICES TABLE -->
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50">
                <tr class="text-xs font-semibold uppercase tracking-wide text-slate-500">
                    <th class="px-4 py-2 text-left">Invoice</th>
                    <th class="px-4 py-2 text-left">Dates</th>
                    <th class="px-4 py-2 text-left">Customer</th>
                    <th class="px-4 py-2 text-center">Status</th>
                    <th class="px-4 py-2 text-right">Total (BDT)</th>
                    <th class="px-4 py-2 text-right">Paid (BDT)</th>
                    <th class="px-4 py-2 text-right">Balance (BDT)</th>
                    <th class="px-4 py-2 text-right">Actions</th>
                </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 bg-white">
                <?php if (!empty($invoices)): ?>
                    <?php
                    $today = date('Y-m-d');
                    foreach ($invoices as $row):
                        $id       = (int)($row['id'] ?? 0);
                        $invNo    = trim((string)($row['invoice_no'] ?? ''));
                        $date     = (string)($row['date']     ?? '');
                        $due      = (string)($row['due_date'] ?? '');
                        $cust     = trim((string)($row['customer_name'] ?? ''));
                        $status   = strtolower((string)($row['status'] ?? ''));

                        $total    = $row['grand_total']   ?? null;
                        $paid     = $row['paid_total']    ?? null;
                        $balance  = $row['balance_due']   ?? ($row['balance'] ?? null);
                        if ($balance === null && $total !== null && $paid !== null) {
                            $balance = (float)$total - (float)$paid;
                        }

                        $isOverdue = ($balance > 0)
                                     && $due !== ''
                                     && $due < $today
                                     && !in_array($status, ['void','draft'], true);

                        $statusLabel = $status !== '' ? ucfirst(str_replace('_',' ',$status)) : '—';
                        $statusClass = match ($status) {
                            'draft'     => 'bg-slate-100 text-slate-700',
                            'sent'      => 'bg-sky-50 text-sky-700',
                            'part_paid' => 'bg-amber-50 text-amber-700',
                            'paid'      => 'bg-emerald-50 text-emerald-700',
                            'void'      => 'bg-slate-100 text-slate-500 line-through',
                            default     => 'bg-slate-100 text-slate-700',
                        };
                        if ($isOverdue) {
                            $statusLabel = 'Overdue';
                            $statusClass = 'bg-rose-50 text-rose-700';
                        }

                        $showUrl = $module_base.'/invoices/'.$id;
                    ?>
                        <tr class="hover:bg-emerald-50/40">
                            <!-- Invoice no + ref -->
                            <td class="px-4 py-2 align-top whitespace-nowrap">
                                <a href="<?= $h($showUrl) ?>" class="font-mono text-xs text-slate-900 hover:underline">
                                    <?= $h($invNo !== '' ? $invNo : ('INV-'.$id)) ?>
                                </a>
                                <?php if (!empty($row['external_ref'])): ?>
                                    <div class="mt-0.5 text-[11px] text-slate-500">
                                        Ref: <?= $h($row['external_ref']) ?>
                                    </div>
                                <?php endif; ?>
                            </td>

                            <!-- Dates -->
                            <td class="px-4 py-2 align-top text-xs text-slate-600">
                                <?php if ($date): ?>
                                    <div>Invoice: <?= $h($date) ?></div>
                                <?php endif; ?>
                                <?php if ($due): ?>
                                    <div class="text-slate-500">
                                        Due: <?= $h($due) ?>
                                    </div>
                                <?php endif; ?>
                            </td>

                            <!-- Customer -->
                            <td class="px-4 py-2 align-top text-xs text-slate-700">
                                <?= $cust !== '' ? $h($cust) : '<span class="text-slate-400">—</span>' ?>
                            </td>

                            <!-- Status -->
                            <td class="px-4 py-2 align-top text-center">
                                <span class="inline-flex items-center gap-1 rounded-full px-2 py-[3px] text-[11px] font-medium <?= $statusClass ?>">
                                    <span class="h-1.5 w-1.5 rounded-full <?= $isOverdue ? 'bg-rose-500' : 'bg-emerald-500' ?>"></span>
                                    <?= $h($statusLabel) ?>
                                </span>
                            </td>

                            <!-- Totals -->
                            <td class="px-4 py-2 align-top text-right text-xs text-slate-700">
                                <?php if ($total !== null && $total !== ''): ?>
                                    <?= $h(number_format((float)$total, 2)) ?>
                                <?php else: ?>
                                    <span class="text-slate-400">—</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-4 py-2 align-top text-right text-xs text-slate-700">
                                <?php if ($paid !== null && $paid !== ''): ?>
                                    <?= $h(number_format((float)$paid, 2)) ?>
                                <?php else: ?>
                                    <span class="text-slate-400">—</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-4 py-2 align-top text-right text-xs <?= ($balance ?? 0) > 0 ? 'text-amber-700' : 'text-slate-700' ?>">
                                <?php if ($balance !== null && $balance !== ''): ?>
                                    <?= $h(number_format((float)$balance, 2)) ?>
                                <?php else: ?>
                                    <span class="text-slate-400">—</span>
                                <?php endif; ?>
                            </td>

                            <!-- Actions -->
                            <td class="px-4 py-2 align-top text-right text-xs">
                                <div class="inline-flex gap-1.5">
                                    <a href="<?= $h($showUrl) ?>"
                                       class="inline-flex items-center rounded-lg border border-slate-200 px-2 py-1 text-[11px] text-slate-700 hover:bg-slate-50">
                                        <i class="fa-regular fa-eye mr-1 text-[10px]"></i>
                                        View
                                    </a>
                                    <!-- Future: add /invoices/{id}/print once wired -->
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="8" class="px-4 py-8 text-center text-sm text-slate-500">
                            No invoices found yet.
                            <span class="block text-xs text-slate-400 mt-1">
                                Use the <strong>New invoice</strong> button to create your first invoice.
                            </span>
                        </td>
                    </tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>

    <!-- HOW TO USE THIS PAGE -->
    <section class="rounded-xl border border-dashed border-emerald-400 bg-emerald-50/60 px-4 py-4 text-sm text-slate-800">
        <h2 class="mb-2 flex items-center gap-2 text-sm font-semibold text-emerald-900">
            <span class="inline-flex h-6 w-6 items-center justify-center rounded-full bg-emerald-600 text-[11px] text-white">
                ?
            </span>
            How to use this page
        </h2>
        <ul class="ml-6 list-disc space-y-1 text-[13px]">
            <li>Use <strong>New invoice</strong> to issue a direct customer invoice.</li>
            <li>Search by invoice number, customer name or reference, and filter by <strong>status</strong> or date range.</li>
            <li>Tick <strong>Only overdue invoices</strong> to focus on pending collections.</li>
            <li>Use the app tabs on the top-right to jump to <strong>Quotes</strong>, <strong>Orders</strong> or <strong>Reports</strong>.</li>
            <li>Click any <strong>Invoice</strong> number in the table to open the full invoice view with print and PDF options.</li>
        </ul>
    </section>
</div>