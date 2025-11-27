<?php
declare(strict_types=1);

/**
 * BizFlow — Main dashboard
 *
 * @var array       $org
 * @var string      $module_base
 * @var array|null  $kpis         // optional: headline metrics
 * @var array|null  $pipelines    // optional: pipeline counts
 * @var array|null  $cash         // optional: cash and bank snapshot
 * @var array|null  $vat_tax      // optional: vat / tax snapshot
 * @var string      $title
 */

$h = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');

$module_base = rtrim($module_base ?? '/apps/bizflow', '/');
$orgName     = trim((string)($org['name'] ?? '')) ?: 'your organisation';
$brand       = '#228B22';

/** ---------- Safe defaults so page never breaks ---------- */

// KPIs row
$kpis = $kpis ?? [
    'today_sales'      => 0.0,
    'month_sales'      => 0.0,
    'receivables'      => 0.0,
    'payables'         => 0.0,
];

// Pipelines
$pipelines = $pipelines ?? [
    'open_quotes'       => 0,
    'open_orders'       => 0,
    'pending_lcs'       => 0,
    'pending_grn'       => 0,
    'pending_expenses'  => 0,
];

// Cash snapshot
$cash = $cash ?? [
    'bank_balance'      => 0.0,
    'cash_in_hand'      => 0.0,
    'uncleared_cheques' => 0.0,
];

// VAT / tax corner
$vat_tax = $vat_tax ?? [
    'vat_payable'       => 0.0,
    'ait_payable'       => 0.0,
    'next_return_date'  => null,   // string Y-m-d or null
];

// Simple sample chart data (12 months) if controller does not pass
$chart = [
    'labels'     => ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'],
    'sales'      => [0,0,0,0,0,0,0,0,0,0,0,0],
    'purchases'  => [0,0,0,0,0,0,0,0,0,0,0,0],
];
?>
<div class="space-y-6">

    <!-- Header + top tabs -->
    <header class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
        <div>
            <h1 class="text-2xl md:text-3xl font-semibold tracking-tight">
                <?= $h($title ?? 'BizFlow dashboard') ?>
            </h1>
            <p class="text-sm text-slate-500">
                One view across sales, imports, expenses, HR, banking and GL for <?= $h($orgName) ?>.
            </p>
        </div>

        <?php
        // Right aligned app tabs (BizFlow convention)
        $tabs = 
  [
            ['LCs',         $module_base.'/lcs',         false],
            ['Inventory',   $module_base.'/inventory',   false],
            ['HRM',         $module_base.'/employees',   false],
            ['Expenses',    $module_base.'/expenses',    false],
            ['Accounting',  $module_base.'/accounting',  false],
            ['Banking',     $module_base.'/banking',     false],
            ['Settings',    $module_base.'/settings',    false],
        ];
        ?>
        <nav class="flex flex-wrap justify-end gap-1 text-sm">
            <?php foreach ($tabs as [$label, $url, $active]): ?>
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

    <!-- KPI row -->
    <section class="grid gap-3 md:grid-cols-4 text-xs">
        <div class="rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 shadow-sm">
            <div class="text-[11px] text-emerald-700">Today sales (BDT)</div>
            <div class="mt-1 text-xl font-semibold text-emerald-900">
                <?= $h(number_format((float)$kpis['today_sales'], 2)) ?>
            </div>
            <div class="mt-1 text-[11px] text-emerald-700/80">
                POS, orders and invoices posted today.
            </div>
        </div>

        <div class="rounded-2xl border border-sky-200 bg-sky-50 px-4 py-3 shadow-sm">
            <div class="text-[11px] text-sky-700">Month sales (BDT)</div>
            <div class="mt-1 text-xl font-semibold text-sky-900">
                <?= $h(number_format((float)$kpis['month_sales'], 2)) ?>
            </div>
            <div class="mt-1 text-[11px] text-sky-700/80">
                All invoices and LC-backed sales this month.
            </div>
        </div>

        <div class="rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 shadow-sm">
            <div class="text-[11px] text-amber-700">Receivables (BDT)</div>
            <div class="mt-1 text-xl font-semibold text-amber-900">
                <?= $h(number_format((float)$kpis['receivables'], 2)) ?>
            </div>
            <div class="mt-1 text-[11px] text-amber-700/80">
                Open customer invoices and LC proceeds pending.
            </div>
        </div>

        <div class="rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 shadow-sm">
            <div class="text-[11px] text-rose-700">Payables (BDT)</div>
            <div class="mt-1 text-xl font-semibold text-rose-900">
                <?= $h(number_format((float)$kpis['payables'], 2)) ?>
            </div>
            <div class="mt-1 text-[11px] text-rose-700/80">
                Supplier bills, LC margins, freight and duties payable.
            </div>
        </div>
    </section>

    <div class="grid gap-6 lg:grid-cols-[2.2fr,1.1fr]">

        <!-- LEFT: chart + pipelines -->
        <section class="space-y-4">

            <!-- Sales vs purchases chart -->
            <div class="rounded-2xl border border-slate-200 bg-white px-4 py-4 shadow-sm">
                <div class="mb-3 flex items-center justify-between">
                    <div>
                        <h2 class="text-sm font-semibold text-slate-800">Sales vs purchases (last 12 months)</h2>
                        <p class="text-xs text-slate-500">
                            Combines local and LC-backed imports. Read-only snapshot; actual logic will come from GL and modules.
                        </p>
                    </div>
                </div>
                <div class="h-64">
                    <canvas id="bf-dashboard-chart"></canvas>
                </div>
            </div>

            <!-- Pipelines -->
            <div class="rounded-2xl border border-slate-200 bg-white px-4 py-4 shadow-sm">
                <div class="mb-3 flex items-center justify-between">
                    <h2 class="text-sm font-semibold text-slate-800">Pipelines</h2>
                    <span class="text-[11px] text-slate-400">Quotes → Orders → LCs → GRN → Billing</span>
                </div>

                <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4 text-xs">
                    <div class="rounded-xl border border-emerald-100 bg-emerald-50/70 px-3 py-3">
                        <div class="text-[11px] text-emerald-700">Open quotes</div>
                        <div class="mt-1 text-lg font-semibold text-emerald-900">
                            <?= $h((string)$pipelines['open_quotes']) ?>
                        </div>
                        <a href="<?= $h($module_base.'/quotes') ?>"
                           class="mt-1 inline-block text-[11px] text-emerald-700 hover:underline">
                            Go to quotes
                        </a>
                    </div>

                    <div class="rounded-xl border border-sky-100 bg-sky-50 px-3 py-3">
                        <div class="text-[11px] text-sky-700">Open orders</div>
                        <div class="mt-1 text-lg font-semibold text-sky-900">
                            <?= $h((string)$pipelines['open_orders']) ?>
                        </div>
                        <a href="<?= $h($module_base.'/orders') ?>"
                           class="mt-1 inline-block text-[11px] text-sky-700 hover:underline">
                            Go to orders
                        </a>
                    </div>

                    <div class="rounded-xl border border-amber-100 bg-amber-50 px-3 py-3">
                        <div class="text-[11px] text-amber-700">Active LCs</div>
                        <div class="mt-1 text-lg font-semibold text-amber-900">
                            <?= $h((string)$pipelines['pending_lcs']) ?>
                        </div>
                        <a href="<?= $h($module_base.'/lcs') ?>"
                           class="mt-1 inline-block text-[11px] text-amber-700 hover:underline">
                            LC register
                        </a>
                    </div>

                    <div class="rounded-xl border border-slate-200 bg-slate-50 px-3 py-3">
                        <div class="text-[11px] text-slate-700">GRN pending posting</div>
                        <div class="mt-1 text-lg font-semibold text-slate-900">
                            <?= $h((string)$pipelines['pending_grn']) ?>
                        </div>
                        <a href="<?= $h($module_base.'/grn') ?>"
                           class="mt-1 inline-block text-[11px] text-slate-700 hover:underline">
                            Goods receipts
                        </a>
                    </div>
                </div>
            </div>
        </section>

        <!-- RIGHT: cash, VAT / tax, quick links -->
        <aside class="space-y-4">

            <!-- Cash and bank -->
            <section class="rounded-2xl border border-slate-200 bg-white px-4 py-4 text-xs shadow-sm">
                <h2 class="mb-2 text-sm font-semibold text-slate-800">Cash and bank snapshot</h2>
                <dl class="space-y-2">
                    <div class="flex items-center justify-between">
                        <dt class="text-slate-500">Bank balance (GL)</dt>
                        <dd class="font-semibold text-slate-900">
                            <?= $h(number_format((float)$cash['bank_balance'], 2)) ?> BDT
                        </dd>
                    </div>
                    <div class="flex items-center justify-between">
                        <dt class="text-slate-500">Cash in hand</dt>
                        <dd class="font-semibold text-slate-900">
                            <?= $h(number_format((float)$cash['cash_in_hand'], 2)) ?> BDT
                        </dd>
                    </div>
                    <div class="flex items-center justify-between">
                        <dt class="text-slate-500">Uncleared cheques</dt>
                        <dd class="font-semibold text-slate-900">
                            <?= $h(number_format((float)$cash['uncleared_cheques'], 2)) ?> BDT
                        </dd>
                    </div>
                </dl>
                <a href="<?= $h($module_base.'/accounting/bank-reco') ?>"
                   class="mt-3 inline-block text-[11px] text-emerald-700 hover:underline">
                    Go to bank reconciliation
                </a>
            </section>

            <!-- VAT / tax corner -->
            <section class="rounded-2xl border border-amber-200 bg-amber-50 px-4 py-4 text-xs shadow-sm">
                <h2 class="mb-2 text-sm font-semibold text-amber-900">VAT / Tax corner</h2>
                <dl class="space-y-2">
                    <div class="flex items-center justify-between">
                        <dt class="text-amber-800">VAT payable</dt>
                        <dd class="font-semibold text-amber-900">
                            <?= $h(number_format((float)$vat_tax['vat_payable'], 2)) ?> BDT
                        </dd>
                    </div>
                    <div class="flex items-center justify-between">
                        <dt class="text-amber-800">AIT / source tax payable</dt>
                        <dd class="font-semibold text-amber-900">
                            <?= $h(number_format((float)$vat_tax['ait_payable'], 2)) ?> BDT
                        </dd>
                    </div>
                    <div class="flex items-center justify-between">
                        <dt class="text-amber-800">Next return</dt>
                        <dd class="font-medium text-amber-900">
                            <?= $vat_tax['next_return_date']
                                ? $h($vat_tax['next_return_date'])
                                : 'Not set yet' ?>
                        </dd>
                    </div>
                </dl>
                <p class="mt-2 text-[11px] text-amber-900/80">
                    In phase 2 this will link directly with your VAT and tax return forms for Bangladesh.
                </p>
            </section>

            <!-- Quick links / shortcuts -->
            <section class="rounded-2xl border border-dashed border-emerald-400 bg-emerald-50/70 px-4 py-4 text-xs text-slate-800">
                <h2 class="mb-2 text-sm font-semibold text-emerald-900">Quick actions</h2>
                <ul class="space-y-1 list-disc ml-4">
                    <li>
                        <a href="<?= $h($module_base.'/quotes/create') ?>" class="text-emerald-800 hover:underline">
                            New quotation
                        </a>
                    </li>
                    <li>
                        <a href="<?= $h($module_base.'/orders/create') ?>" class="text-emerald-800 hover:underline">
                            New sales order
                        </a>
                    </li>
                    <li>
                        <a href="<?= $h($module_base.'/purchases/create') ?>" class="text-emerald-800 hover:underline">
                            New purchase bill
                        </a>
                    </li>
                    <li>
                        <a href="<?= $h($module_base.'/lcs/create') ?>" class="text-emerald-800 hover:underline">
                            Open new import LC
                        </a>
                    </li>
                    <li>
                        <a href="<?= $h($module_base.'/expenses/create') ?>" class="text-emerald-800 hover:underline">
                            Log an expense (office, freight, duty)
                        </a>
                    </li>
                </ul>
            </section>
        </aside>
    </div>


</div>

<!-- Chart.js (CDN) for dashboard visual -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
(function(){
    var ctx = document.getElementById('bf-dashboard-chart');
    if (!ctx || typeof Chart === 'undefined') return;

    var labels    = <?= json_encode($chart['labels'], JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP) ?>;
    var salesData = <?= json_encode($chart['sales'], JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP) ?>;
    var purData   = <?= json_encode($chart['purchases'], JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP) ?>;

    new Chart(ctx, {
        type: 'line',
        data: {
            labels: labels,
            datasets: [
                {
                    label: 'Sales',
                    data: salesData,
                    borderColor: '#228B22',
                    backgroundColor: 'rgba(34,139,34,0.10)',
                    tension: 0.3,
                    borderWidth: 2,
                    fill: true,
                    pointRadius: 2
                },
                {
                    label: 'Purchases',
                    data: purData,
                    borderColor: '#0ea5e9',
                    backgroundColor: 'rgba(14,165,233,0.08)',
                    tension: 0.3,
                    borderWidth: 2,
                    fill: true,
                    pointRadius: 2
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: true, labels: { boxWidth: 10, font: { size: 10 } } },
            },
            scales: {
                x: {
                    ticks: { font: { size: 10 } },
                    grid:  { display: false }
                },
                y: {
                    ticks: { font: { size: 10 } },
                    grid:  { color: 'rgba(148,163,184,0.2)' }
                }
            }
        }
    });
})();
</script>