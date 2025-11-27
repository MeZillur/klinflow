<?php
/** @var array  $metrics */
/** @var array  $org */
/** @var string $module_base */
/** @var string $title */
/** @var bool   $data_ready */

$h = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');

$module_base = rtrim($module_base ?? ($base ?? '/apps/bizflow'), '/');
$orgName     = $org['name'] ?? '';

$m = $metrics ?? [];

/* Helper for safe metric rendering */
$metric = function ($key, $default = '—') use ($m, $h) {
    if (!array_key_exists($key, $m) || $m[$key] === null) {
        return $default;
    }
    return $h((string)$m[$key]);
};

/* Numeric money */
$money = function ($key) use ($m, $h) {
    $v = $m[$key] ?? null;
    if ($v === null) return '<span class="text-slate-400">—</span>';
    return $h(number_format((float)$v, 2)) . ' BDT';
};

/* Flags */
$flag = function ($key) use ($m) {
    return !empty($m[$key]);
};
?>
<div class="space-y-6">

    <!-- Header -->
    <header class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
        <div class="space-y-1">
            <h1 class="text-2xl md:text-3xl font-semibold tracking-tight">
                <?= $h($title ?? 'Reports & Analytics') ?>
            </h1>
            <p class="text-sm text-slate-500">
                High-level overview for <?= $h($orgName ?: 'your organisation') ?> across sales,
                cash, purchases and inventory.
            </p>
            <?php if (!$data_ready): ?>
                <p class="mt-1 text-xs text-amber-700">
                    BizFlow data warehouse is warming up. As you start using quotes, orders, invoices
                    and purchases, this dashboard will automatically light up.
                </p>
            <?php endif; ?>
        </div>

        <!-- Top-right tabs (BizFlow convention) -->
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
            $current = $module_base.'/reports';
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

    <!-- Top metric cards -->
    <section class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
        <!-- Sales pipeline -->
        <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm space-y-2">
            <div class="flex items-center justify-between">
                <div class="text-xs font-medium uppercase tracking-wide text-slate-500">
                    Sales pipeline
                </div>
                <span class="inline-flex items-center gap-1 rounded-full bg-emerald-50 px-2 py-[2px] text-[10px] font-medium text-emerald-700">
                    <span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>
                    Live
                </span>
            </div>
            <div class="mt-1 text-sm text-slate-700">
                Quotes → Orders → Invoices
            </div>
            <dl class="mt-3 grid grid-cols-3 gap-2 text-xs">
                <div>
                    <dt class="text-slate-500">Quotes</dt>
                    <dd class="mt-1 text-lg font-semibold text-slate-900">
                        <?= $metric('quotes_count', '0') ?>
                    </dd>
                </div>
                <div>
                    <dt class="text-slate-500">Orders</dt>
                    <dd class="mt-1 text-lg font-semibold text-slate-900">
                        <?= $metric('orders_count', '0') ?>
                    </dd>
                </div>
                <div>
                    <dt class="text-slate-500">Invoices</dt>
                    <dd class="mt-1 text-lg font-semibold text-slate-900">
                        <?= $metric('invoices_count', '0') ?>
                    </dd>
                </div>
            </dl>
        </div>

        <!-- Revenue (invoiced) -->
        <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm space-y-2">
            <div class="flex items-center justify-between">
                <div class="text-xs font-medium uppercase tracking-wide text-slate-500">
                    Revenue (invoiced)
                </div>
                <i class="fa fa-file-invoice-dollar text-slate-400 text-xs"></i>
            </div>
            <div class="mt-2 text-2xl font-semibold text-slate-900">
                <?= $money('invoices_total') ?>
            </div>
            <p class="mt-1 text-[11px] text-slate-500">
                Sum of BizFlow invoices for this tenant (all time).
            </p>
        </div>

        <!-- Cash collected -->
        <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm space-y-2">
            <div class="flex items-center justify-between">
                <div class="text-xs font-medium uppercase tracking-wide text-slate-500">
                    Cash collected
                </div>
                <i class="fa fa-coins text-slate-400 text-xs"></i>
            </div>
            <div class="mt-2 text-2xl font-semibold text-slate-900">
                <?= $money('payments_total') ?>
            </div>
            <p class="mt-1 text-[11px] text-slate-500">
                Total from BizFlow payments (receipts) for this tenant.
            </p>
        </div>

        <!-- Purchases -->
        <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm space-y-2">
            <div class="flex items-center justify-between">
                <div class="text-xs font-medium uppercase tracking-wide text-slate-500">
                    Purchases
                </div>
                <i class="fa fa-cart-shopping text-slate-400 text-xs"></i>
            </div>
            <div class="mt-2 text-2xl font-semibold text-slate-900">
                <?= $money('purchases_total') ?>
            </div>
            <p class="mt-1 text-[11px] text-slate-500">
                Total purchase value recorded in BizFlow purchase docs.
            </p>
        </div>
    </section>

    <!-- Middle: 2-column analytics -->
    <section class="grid gap-4 lg:grid-cols-[1.6fr,1.2fr]">

        <!-- Sales & cash lane -->
        <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm space-y-4">
            <div class="flex items-center justify-between">
                <h2 class="text-sm font-semibold text-slate-800">
                    Sales & cash lane
                </h2>
                <span class="text-[11px] text-slate-500">
                    High-level pipeline completeness
                </span>
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <!-- Pipeline health -->
                <div class="space-y-2">
                    <div class="text-xs font-medium text-slate-600">Pipeline health</div>
                    <div class="rounded-lg bg-slate-50 p-3 text-xs text-slate-600 space-y-2">
                        <div class="flex justify-between">
                            <span>Quotes → Orders</span>
                            <span class="font-medium text-slate-900">
                                <?= $metric('quotes_count', '0') ?> / <?= $metric('orders_count', '0') ?>
                            </span>
                        </div>
                        <div class="h-1.5 w-full rounded-full bg-slate-200 overflow-hidden">
                            <div class="h-full bg-emerald-500 w-1/2"></div>
                        </div>

                        <div class="flex justify-between pt-1">
                            <span>Orders → Invoices</span>
                            <span class="font-medium text-slate-900">
                                <?= $metric('orders_count', '0') ?> / <?= $metric('invoices_count', '0') ?>
                            </span>
                        </div>
                        <div class="h-1.5 w-full rounded-full bg-slate-200 overflow-hidden">
                            <div class="h-full bg-emerald-600 w-1/2"></div>
                        </div>

                        <p class="mt-2 text-[11px] text-slate-500">
                            Bars are visual placeholders; once data volume grows we can convert these into
                            real ratios and charts.
                        </p>
                    </div>
                </div>

                <!-- Cash vs invoices -->
                <div class="space-y-2">
                    <div class="text-xs font-medium text-slate-600">Cash vs invoiced</div>
                    <div class="rounded-lg bg-slate-50 p-3 text-xs text-slate-600 space-y-2">
                        <div class="flex justify-between">
                            <span>Invoiced</span>
                            <span class="font-medium text-slate-900"><?= $money('invoices_total') ?></span>
                        </div>
                        <div class="flex justify-between">
                            <span>Collected</span>
                            <span class="font-medium text-slate-900"><?= $money('payments_total') ?></span>
                        </div>
                        <div class="flex justify-between">
                            <span>Estimated outstanding</span>
                            <?php
                            $inv  = $m['invoices_total']  ?? null;
                            $pay  = $m['payments_total']  ?? null;
                            $diff = null;
                            if ($inv !== null && $pay !== null) {
                                $diff = (float)$inv - (float)$pay;
                            }
                            ?>
                            <span class="font-medium <?= ($diff ?? 0) > 0 ? 'text-amber-700' : 'text-emerald-700' ?>">
                                <?php if ($diff === null): ?>
                                    <span class="text-slate-400">—</span>
                                <?php else: ?>
                                    <?= $h(number_format((float)$diff, 2)) ?> BDT
                                <?php endif; ?>
                            </span>
                        </div>
                        <p class="mt-2 text-[11px] text-slate-500">
                            This is a simple <strong>total invoices − total payments</strong> view.
                            Detailed AR ageing can come later as a dedicated report.
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Inventory & data readiness -->
        <div class="space-y-4">
            <!-- Inventory snapshot -->
            <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm space-y-3">
                <div class="flex items-center justify-between">
                    <h2 class="text-sm font-semibold text-slate-800">
                        Inventory snapshot
                    </h2>
                    <i class="fa fa-warehouse text-slate-400 text-xs"></i>
                </div>
                <dl class="space-y-2 text-xs text-slate-600">
                    <div class="flex justify-between gap-4">
                        <dt class="text-slate-500">Tracked SKUs</dt>
                        <dd class="text-right text-slate-900">
                            <?= $metric('inventory_skus', '0') ?>
                        </dd>
                    </div>
                    <div class="flex justify-between gap-4">
                        <dt class="text-slate-500">Qty moved in</dt>
                        <dd class="text-right text-slate-900">
                            <?= $metric('inventory_moves_in', '0') ?>
                        </dd>
                    </div>
                    <div class="flex justify-between gap-4">
                        <dt class="text-slate-500">Qty moved out</dt>
                        <dd class="text-right text-slate-900">
                            <?= $metric('inventory_moves_out', '0') ?>
                        </dd>
                    </div>
                </dl>
                <p class="mt-1 text-[11px] text-slate-500">
                    This section reads from <code>biz_items</code> and <code>biz_inventory_moves</code>
                    when they exist. Until then, it stays as a safe placeholder.
                </p>
            </div>

            <!-- Data readiness radar -->
            <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm space-y-3">
                <h2 class="text-sm font-semibold text-slate-800">Data readiness</h2>
                <ul class="space-y-1 text-xs text-slate-600">
                    <li class="flex items-center justify-between">
                        <span>Quotes</span>
                        <span class="inline-flex items-center gap-1 rounded-full px-2 py-[2px] text-[10px]
                                     <?= $flag('has_quotes')
                                          ? 'bg-emerald-50 text-emerald-700'
                                          : 'bg-slate-100 text-slate-500' ?>">
                            <?= $flag('has_quotes') ? 'Connected' : 'Pending' ?>
                        </span>
                    </li>
                    <li class="flex items-center justify-between">
                        <span>Orders</span>
                        <span class="inline-flex items-center gap-1 rounded-full px-2 py-[2px] text-[10px]
                                     <?= $flag('has_orders')
                                          ? 'bg-emerald-50 text-emerald-700'
                                          : 'bg-slate-100 text-slate-500' ?>">
                            <?= $flag('has_orders') ? 'Connected' : 'Pending' ?>
                        </span>
                    </li>
                    <li class="flex items-center justify-between">
                        <span>Invoices</span>
                        <span class="inline-flex items-center gap-1 rounded-full px-2 py-[2px] text-[10px]
                                     <?= $flag('has_invoices')
                                          ? 'bg-emerald-50 text-emerald-700'
                                          : 'bg-slate-100 text-slate-500' ?>">
                            <?= $flag('has_invoices') ? 'Connected' : 'Pending' ?>
                        </span>
                    </li>
                    <li class="flex items-center justify-between">
                        <span>Payments</span>
                        <span class="inline-flex items-center gap-1 rounded-full px-2 py-[2px] text-[10px]
                                     <?= $flag('has_payments')
                                          ? 'bg-emerald-50 text-emerald-700'
                                          : 'bg-slate-100 text-slate-500' ?>">
                            <?= $flag('has_payments') ? 'Connected' : 'Pending' ?>
                        </span>
                    </li>
                    <li class="flex items-center justify-between">
                        <span>Purchases</span>
                        <span class="inline-flex items-center gap-1 rounded-full px-2 py-[2px] text-[10px]
                                     <?= $flag('has_purchases')
                                          ? 'bg-emerald-50 text-emerald-700'
                                          : 'bg-slate-100 text-slate-500' ?>">
                            <?= $flag('has_purchases') ? 'Connected' : 'Pending' ?>
                        </span>
                    </li>
                    <li class="flex items-center justify-between">
                        <span>Inventory</span>
                        <span class="inline-flex items-center gap-1 rounded-full px-2 py-[2px] text-[10px]
                                     <?= $flag('has_inventory')
                                          ? 'bg-emerald-50 text-emerald-700'
                                          : 'bg-slate-100 text-slate-500' ?>">
                            <?= $flag('has_inventory') ? 'Connected' : 'Pending' ?>
                        </span>
                    </li>
                </ul>
                <p class="mt-2 text-[11px] text-slate-500">
                    This gives you a quick confidence check on which BizFlow domains are wired and feeding data
                    into reports, without throwing any database errors.
                </p>
            </div>
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
            <li>Use the <strong>Sales pipeline</strong> card to see how many quotes progress into orders and invoices.</li>
            <li>Compare <strong>Revenue (invoiced)</strong> vs <strong>Cash collected</strong> to monitor receivables pressure.</li>
            <li>Watch the <strong>Purchases</strong> and <strong>Inventory snapshot</strong> to understand supply-side activity.</li>
            <li>Check <strong>Data readiness</strong> to know which BizFlow modules already feed reliable numbers into this dashboard.</li>
            <li>Once schemas are fully stable, we can add detailed drill-down reports (ageing, profitability, category-wise sales, etc.) from this hub.</li>
        </ul>
    </section>
</div>