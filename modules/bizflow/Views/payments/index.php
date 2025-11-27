<?php
/** @var array  $payments */
/** @var array  $org */
/** @var string $module_base */
/** @var string $title */
/** @var string|null $search */
/** @var string|null $filter_method */
/** @var string|null $date_from */
/** @var string|null $date_to */
/** @var bool   $storage_ready */

$h = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');

$module_base = rtrim($module_base ?? ($base ?? '/apps/bizflow'), '/');
$orgName     = $org['name'] ?? '';

$search        = (string)($search ?? ($_GET['q'] ?? ''));
$filter_method = (string)($filter_method ?? ($_GET['method'] ?? ''));
$date_from     = (string)($date_from ?? ($_GET['from'] ?? ''));
$date_to       = (string)($date_to ?? ($_GET['to'] ?? ''));

$payments      = $payments ?? [];

/* Quick metrics (client-side only) */
$totalCount   = count($payments);
$totalAmount  = 0.0;
$last30Amount = 0.0;
$today        = new DateTimeImmutable('today');
$since30      = $today->modify('-30 days')->format('Y-m-d');

foreach ($payments as $row) {
    $amt  = (float)($row['amount'] ?? 0);
    $date = (string)($row['date'] ?? '');
    $totalAmount += $amt;
    if ($date !== '' && $date >= $since30) {
        $last30Amount += $amt;
    }
}
?>
<div class="space-y-6" x-data="{ openFilters:false }">

    <!-- Header: title + tabs -->
    <header class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
        <div class="space-y-1">
            <h1 class="text-2xl md:text-3xl font-semibold tracking-tight">
                <?= $h($title ?? 'Payments') ?>
            </h1>
            <p class="text-sm text-slate-500">
                Track incoming receipts and allocations for <?= $h($orgName ?: 'your organisation') ?>.
            </p>
            <?php if (!$storage_ready): ?>
                <p class="mt-1 text-xs text-amber-700">
                    Payments tables are not created yet. This console will light up automatically once
                    <code>biz_payments</code> schema is in place.
                </p>
            <?php endif; ?>
        </div>

        <!-- Right-aligned app tabs -->
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
                ['Payments',   $module_base.'/payments'],
                ['Settings',   $module_base.'/settings'],
            ];
            $current = $module_base.'/payments';
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

    <!-- Metrics + actions -->
    <section class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
        <!-- Stats -->
        <div class="flex flex-wrap gap-3 text-xs md:text-sm">
            <div class="inline-flex items-center gap-2 rounded-lg border border-slate-200 bg-white px-3 py-2 shadow-sm">
                <span class="inline-flex h-6 w-6 items-center justify-center rounded-full bg-emerald-100 text-emerald-700 text-xs font-semibold">
                    <?= $h($totalCount) ?>
                </span>
                <div>
                    <div class="font-medium text-slate-800">Payments</div>
                    <div class="text-[11px] text-slate-500">Total receipts in current view</div>
                </div>
            </div>

            <div class="inline-flex items-center gap-2 rounded-lg border border-slate-200 bg-white px-3 py-2 shadow-sm">
                <div class="h-6 w-6 rounded-full bg-emerald-50 flex items-center justify-center text-[11px] text-emerald-700 font-semibold">
                    ৳
                </div>
                <div>
                    <div class="font-medium text-slate-800">
                        <?= $h(number_format($totalAmount, 2)) ?> BDT
                    </div>
                    <div class="text-[11px] text-slate-500">Total amount (all listed)</div>
                </div>
            </div>

            <div class="inline-flex items-center gap-2 rounded-lg border border-slate-200 bg-white px-3 py-2 shadow-sm">
                <div class="h-6 w-6 rounded-full bg-sky-50 flex items-center justify-center text-[11px] text-sky-700 font-semibold">
                    30
                </div>
                <div>
                    <div class="font-medium text-slate-800">
                        <?= $h(number_format($last30Amount, 2)) ?> BDT
                    </div>
                    <div class="text-[11px] text-slate-500">Collected in last 30 days</div>
                </div>
            </div>
        </div>

        <!-- Actions -->
        <div class="flex flex-wrap items-center justify-end gap-2">
            <button type="button"
                    class="inline-flex items-center gap-2 rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs font-medium text-slate-700 shadow-sm hover:bg-slate-50 md:hidden"
                    @click="openFilters = !openFilters">
                <i class="fa fa-filter text-[11px]"></i>
                <span>Filters</span>
            </button>

            <a href="<?= $h($module_base.'/payments/create') ?>"
               class="inline-flex items-center gap-2 rounded-lg bg-emerald-600 px-3 py-2 text-sm font-medium text-white shadow-sm hover:bg-emerald-700 focus:outline-none focus:ring-2 focus:ring-emerald-600 focus:ring-offset-1">
                <i class="fa fa-plus-circle text-xs"></i>
                <span>Record payment</span>
            </a>
        </div>
    </section>

    <!-- Filters -->
    <section class="rounded-xl border border-slate-200 bg-white shadow-sm">
        <form method="get"
              class="grid gap-3 border-b border-slate-100 px-4 py-3 md:grid-cols-[1.6fr,1.1fr,1.1fr,auto] md:items-end"
              :class="{'hidden md:grid': !openFilters, 'grid': openFilters}">
            <!-- Search -->
            <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">Search</label>
                <div class="relative">
                    <input type="text"
                           name="q"
                           value="<?= $h($search) ?>"
                           placeholder="Customer, reference, notes"
                           class="block w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500">
                    <span class="pointer-events-none absolute inset-y-0 right-3 flex items-center text-slate-400">
                        <i class="fa fa-search text-xs"></i>
                    </span>
                </div>
            </div>

            <!-- Method -->
            <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">Method</label>
                <select name="method"
                        class="block w-full rounded-lg border border-slate-200 px-3 py-2 text-sm bg-white focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500">
                    <option value="">All</option>
                    <option value="cash"           <?= $filter_method === 'cash' ? 'selected' : '' ?>>Cash</option>
                    <option value="bank_transfer"  <?= $filter_method === 'bank_transfer' ? 'selected' : '' ?>>Bank transfer</option>
                    <option value="mobile_money"   <?= $filter_method === 'mobile_money' ? 'selected' : '' ?>>Mobile money (bKash/Nagad)</option>
                    <option value="cheque"         <?= $filter_method === 'cheque' ? 'selected' : '' ?>>Cheque</option>
                    <option value="lc_settlement"  <?= $filter_method === 'lc_settlement' ? 'selected' : '' ?>>LC settlement</option>
                    <option value="adjustment"     <?= $filter_method === 'adjustment' ? 'selected' : '' ?>>Adjustment</option>
                </select>
            </div>

            <!-- Date range -->
            <div class="grid grid-cols-2 gap-2">
                <div>
                    <label class="block text-xs font-medium text-slate-600 mb-1">From</label>
                    <input type="date" name="from" value="<?= $h($date_from) ?>"
                           class="block w-full rounded-lg border border-slate-200 px-2.5 py-2 text-sm focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500">
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-600 mb-1">To</label>
                    <input type="date" name="to" value="<?= $h($date_to) ?>"
                           class="block w-full rounded-lg border border-slate-200 px-2.5 py-2 text-sm focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500">
                </div>
            </div>

            <!-- Filter actions -->
            <div class="flex flex-col gap-2 md:items-end md:justify-between">
                <div class="flex gap-2 justify-end">
                    <a href="<?= $h($module_base.'/payments') ?>"
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
                    <th class="px-4 py-2 text-left">Date</th>
                    <th class="px-4 py-2 text-left">Customer</th>
                    <th class="px-4 py-2 text-left">Method</th>
                    <th class="px-4 py-2 text-left">Reference</th>
                    <th class="px-4 py-2 text-right">Amount</th>
                    <th class="px-4 py-2 text-right">Actions</th>
                </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 bg-white">
                <?php if (!empty($payments)): ?>
                    <?php foreach ($payments as $row): ?>
                        <?php
                        $id       = (int)($row['id'] ?? 0);
                        $date     = $row['date'] ?? ($row['created_at'] ?? '');
                        $amount   = $row['amount'] ?? 0;
                        $curr     = $row['currency'] ?? 'BDT';
                        $method   = $row['method'] ?? '';
                        $ref      = $row['reference'] ?? '';
                        $custName = $row['customer_name'] ?? '';
                        $custCode = $row['customer_code'] ?? '';
                        ?>
                        <tr class="hover:bg-emerald-50/40">
                            <td class="px-4 py-2 align-top text-xs text-slate-700 whitespace-nowrap">
                                <?= $h($date ?: '—') ?>
                            </td>
                            <td class="px-4 py-2 align-top text-xs text-slate-700">
                                <?php if ($custName !== ''): ?>
                                    <div class="font-medium text-slate-800"><?= $h($custName) ?></div>
                                    <?php if ($custCode !== ''): ?>
                                        <div class="font-mono text-[11px] text-slate-500"><?= $h($custCode) ?></div>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span class="text-slate-400">—</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-4 py-2 align-top text-xs text-slate-700">
                                <?php
                                $label = 'Other';
                                $chipClass = 'bg-slate-100 text-slate-700';
                                switch ($method) {
                                    case 'cash':          $label = 'Cash';          $chipClass = 'bg-emerald-50 text-emerald-700'; break;
                                    case 'bank_transfer': $label = 'Bank transfer'; $chipClass = 'bg-sky-50 text-sky-700'; break;
                                    case 'mobile_money':  $label = 'Mobile money';  $chipClass = 'bg-purple-50 text-purple-700'; break;
                                    case 'cheque':        $label = 'Cheque';        $chipClass = 'bg-amber-50 text-amber-700'; break;
                                    case 'lc_settlement': $label = 'LC settlement'; $chipClass = 'bg-indigo-50 text-indigo-700'; break;
                                    case 'adjustment':    $label = 'Adjustment';    $chipClass = 'bg-slate-100 text-slate-700'; break;
                                }
                                ?>
                                <span class="inline-flex items-center gap-1 rounded-full px-2 py-[3px] text-[11px] <?= $chipClass ?>">
                                    <span class="h-1.5 w-1.5 rounded-full bg-current/70"></span>
                                    <?= $h($label) ?>
                                </span>
                            </td>
                            <td class="px-4 py-2 align-top text-xs text-slate-600">
                                <?= $ref !== '' ? $h($ref) : '<span class="text-slate-400">—</span>' ?>
                            </td>
                            <td class="px-4 py-2 align-top text-right text-xs text-slate-800 whitespace-nowrap">
                                <?= $h(number_format((float)$amount, 2)) ?> <?= $h($curr) ?>
                            </td>
                            <td class="px-4 py-2 align-top text-right text-xs">
                                <a href="<?= $h($module_base.'/payments/'.$id) ?>"
                                   class="inline-flex items-center rounded-lg border border-slate-200 px-2 py-1 text-[11px] text-slate-700 hover:bg-slate-50">
                                    <i class="fa-regular fa-eye mr-1 text-[10px]"></i> View
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="6" class="px-4 py-8 text-center text-sm text-slate-500">
                            No payments recorded yet.
                            <a href="<?= $h($module_base.'/payments/create') ?>"
                               class="text-emerald-700 font-medium hover:underline">
                                Record your first payment
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
            <li>Use <strong>Search</strong> to find payments by customer, reference or notes.</li>
            <li>Filter by <strong>method</strong> (cash, bank transfer, mobile money, LC settlement, etc.) and date range.</li>
            <li>Click <strong>Record payment</strong> to capture a new receipt against a customer.</li>
            <li>Open <strong>View</strong> to see full details and invoice allocations once schema is wired.</li>
            <li>All records are tenant-safe via <code>org_id</code> inside BizFlow’s <code>biz_payments</code> tables.</li>
        </ul>
    </section>
</div>