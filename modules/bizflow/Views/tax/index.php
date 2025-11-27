<?php
declare(strict_types=1);

/** @var array  $org */
/** @var string $module_base */
/** @var array  $summary */
/** @var array  $vat_input */
/** @var array  $vat_output */
/** @var array  $wht_rows */
/** @var string $today */
/** @var string $title */
/** @var bool   $has_expenses */
/** @var bool   $has_purchases */
/** @var bool   $has_sales */

$h = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');

$module_base = rtrim($module_base ?? '/apps/bizflow', '/');
$orgName     = trim((string)($org['name'] ?? '')) ?: 'your organisation';

$periodLabel = $summary['period_label'] ?? 'This quarter';
$vatInput    = (float)($summary['vat_input']   ?? 0);
$vatOutput   = (float)($summary['vat_output']  ?? 0);
$whtPayable  = (float)($summary['wht_payable'] ?? 0);
$vatNet      = $vatOutput - $vatInput;
?>
<div class="space-y-6">

    <!-- Header + tabs (BizFlow style: right-aligned) -->
    <header class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
        <div>
            <h1 class="text-2xl md:text-3xl font-semibold tracking-tight">
                <?= $h($title ?? 'Tax / VAT / WHT center') ?>
            </h1>
            <p class="text-sm text-slate-500">
                High-level view of VAT input, VAT output and WHT for <?= $h($orgName) ?>.
            </p>
        </div>

        <?php
        $tabs = [
            ['Overview',       $module_base.'/tax',              true],
            ['VAT input',      '#vat-input',                     false],
            ['VAT output',     '#vat-output',                    false],
            ['WHT / AIT',      '#wht',                           false],
            ['Returns',        '#returns',                       false],
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

    <!-- Data source warning (until wired) -->
    <?php if (!$has_expenses || !$has_purchases || !$has_sales): ?>
        <div class="rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-900">
            Tax center is running on <strong>demo numbers</strong> only.
            Once <code>biz_expenses</code>, <code>biz_purchases</code> and <code>biz_invoices</code> are live,
            this page will auto-pull VAT/WHT from real postings.
        </div>
    <?php endif; ?>

    <!-- Summary row -->
    <section class="grid gap-3 md:grid-cols-4 text-xs">
        <div class="rounded-2xl border border-slate-200 bg-white px-4 py-3 shadow-sm">
            <div class="text-[11px] text-slate-500">Period</div>
            <div class="mt-1 text-xl font-semibold text-slate-900">
                <?= $h($periodLabel) ?>
            </div>
            <div class="mt-1 text-[11px] text-slate-500">
                Today: <?= $h($today) ?>
            </div>
        </div>

        <div class="rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 shadow-sm">
            <div class="text-[11px] text-emerald-700">VAT input (purchases + expenses)</div>
            <div class="mt-1 text-xl font-semibold text-emerald-900">
                <?= $h(number_format($vatInput, 2)) ?> BDT
            </div>
        </div>

        <div class="rounded-2xl border border-sky-200 bg-sky-50 px-4 py-3 shadow-sm">
            <div class="text-[11px] text-sky-700">VAT output (sales)</div>
            <div class="mt-1 text-xl font-semibold text-sky-900">
                <?= $h(number_format($vatOutput, 2)) ?> BDT
            </div>
        </div>

        <div class="rounded-2xl border <?= $vatNet >= 0 ? 'border-rose-200 bg-rose-50' : 'border-emerald-200 bg-emerald-50' ?> px-4 py-3 shadow-sm">
            <div class="text-[11px] <?= $vatNet >= 0 ? 'text-rose-700' : 'text-emerald-700' ?>">
                <?= $vatNet >= 0 ? 'Net VAT payable' : 'Net VAT refundable' ?>
            </div>
            <div class="mt-1 text-xl font-semibold <?= $vatNet >= 0 ? 'text-rose-900' : 'text-emerald-900' ?>">
                <?= $h(number_format(abs($vatNet), 2)) ?> BDT
            </div>
            <div class="mt-1 text-[11px] text-slate-500">
                WHT payable (AIT): <?= $h(number_format($whtPayable, 2)) ?> BDT
            </div>
        </div>
    </section>

    <div class="grid gap-6 lg:grid-cols-[2.2fr,1.1fr]">

        <!-- LEFT: VAT input + VAT output + WHT tables (anchor sections) -->
        <section class="space-y-6">

            <!-- VAT input -->
            <div id="vat-input" class="rounded-2xl border border-slate-200 bg-white shadow-sm">
                <div class="flex items-center justify-between border-b border-slate-100 px-4 py-3">
                    <div>
                        <h2 class="text-sm font-semibold text-slate-800">VAT input register (demo)</h2>
                        <p class="text-xs text-slate-500">
                            Purchases and expenses where suppliers charged VAT.
                        </p>
                    </div>
                </div>

                <div class="overflow-x-auto text-xs">
                    <table class="min-w-full divide-y divide-slate-100">
                        <thead class="bg-slate-50">
                            <tr>
                                <th class="px-3 py-2 text-left font-semibold text-slate-600">Date</th>
                                <th class="px-3 py-2 text-left font-semibold text-slate-600">Source</th>
                                <th class="px-3 py-2 text-left font-semibold text-slate-600">Party</th>
                                <th class="px-3 py-2 text-right font-semibold text-slate-600">Taxable</th>
                                <th class="px-3 py-2 text-right font-semibold text-slate-600">VAT</th>
                                <th class="px-3 py-2 text-left font-semibold text-slate-600">Form</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                        <?php if (!empty($vat_input)): ?>
                            <?php foreach ($vat_input as $row): ?>
                                <tr class="hover:bg-slate-50/60">
                                    <td class="px-3 py-2 text-[11px] text-slate-700">
                                        <?= $h($row['date'] ?? '—') ?>
                                    </td>
                                    <td class="px-3 py-2">
                                        <div class="text-xs text-slate-800"><?= $h($row['source'] ?? '—') ?></div>
                                        <div class="text-[11px] text-slate-500"><?= $h($row['ref'] ?? '') ?></div>
                                    </td>
                                    <td class="px-3 py-2 text-xs text-slate-800">
                                        <?= $h($row['party'] ?? '—') ?>
                                    </td>
                                    <td class="px-3 py-2 text-right text-xs text-slate-800">
                                        <?= $h(number_format((float)($row['taxable'] ?? 0), 2)) ?>
                                    </td>
                                    <td class="px-3 py-2 text-right text-xs text-slate-800">
                                        <?= $h(number_format((float)($row['vat_amount'] ?? 0), 2)) ?>
                                    </td>
                                    <td class="px-3 py-2 text-[11px] text-slate-700">
                                        <?= $h($row['form_code'] ?? '') ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" class="px-3 py-4 text-center text-[11px] text-slate-500">
                                    No VAT input rows yet. Once purchases and expenses are wired, they will show here.
                                </td>
                            </tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- VAT output -->
            <div id="vat-output" class="rounded-2xl border border-slate-200 bg-white shadow-sm">
                <div class="flex items-center justify-between border-b border-slate-100 px-4 py-3">
                    <div>
                        <h2 class="text-sm font-semibold text-slate-800">VAT output register (demo)</h2>
                        <p class="text-xs text-slate-500">
                            Sales invoices where VAT was charged to customers.
                        </p>
                    </div>
                </div>

                <div class="overflow-x-auto text-xs">
                    <table class="min-w-full divide-y divide-slate-100">
                        <thead class="bg-slate-50">
                            <tr>
                                <th class="px-3 py-2 text-left font-semibold text-slate-600">Date</th>
                                <th class="px-3 py-2 text-left font-semibold text-slate-600">Invoice</th>
                                <th class="px-3 py-2 text-left font-semibold text-slate-600">Customer</th>
                                <th class="px-3 py-2 text-right font-semibold text-slate-600">Taxable</th>
                                <th class="px-3 py-2 text-right font-semibold text-slate-600">VAT</th>
                                <th class="px-3 py-2 text-left font-semibold text-slate-600">Form</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                        <?php if (!empty($vat_output)): ?>
                            <?php foreach ($vat_output as $row): ?>
                                <tr class="hover:bg-slate-50/60">
                                    <td class="px-3 py-2 text-[11px] text-slate-700">
                                        <?= $h($row['date'] ?? '—') ?>
                                    </td>
                                    <td class="px-3 py-2">
                                        <div class="text-xs text-slate-800"><?= $h($row['source'] ?? '—') ?></div>
                                        <div class="text-[11px] text-slate-500"><?= $h($row['ref'] ?? '') ?></div>
                                    </td>
                                    <td class="px-3 py-2 text-xs text-slate-800">
                                        <?= $h($row['customer'] ?? '—') ?>
                                    </td>
                                    <td class="px-3 py-2 text-right text-xs text-slate-800">
                                        <?= $h(number_format((float)($row['taxable'] ?? 0), 2)) ?>
                                    </td>
                                    <td class="px-3 py-2 text-right text-xs text-slate-800">
                                        <?= $h(number_format((float)($row['vat_amount'] ?? 0), 2)) ?>
                                    </td>
                                    <td class="px-3 py-2 text-[11px] text-slate-700">
                                        <?= $h($row['form_code'] ?? '') ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" class="px-3 py-4 text-center text-[11px] text-slate-500">
                                    No VAT output rows yet. Sales VAT will show here once invoices &amp; posting are wired.
                                </td>
                            </tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- WHT -->
            <div id="wht" class="rounded-2xl border border-slate-200 bg-white shadow-sm">
                <div class="flex items-center justify-between border-b border-slate-100 px-4 py-3">
                    <div>
                        <h2 class="text-sm font-semibold text-slate-800">WHT / AIT register (demo)</h2>
                        <p class="text-xs text-slate-500">
                            Tax deducted at source (AIT) against vendors, to be deposited to NBR.
                        </p>
                    </div>
                </div>

                <div class="overflow-x-auto text-xs">
                    <table class="min-w-full divide-y divide-slate-100">
                        <thead class="bg-slate-50">
                            <tr>
                                <th class="px-3 py-2 text-left font-semibold text-slate-600">Date</th>
                                <th class="px-3 py-2 text-left font-semibold text-slate-600">Source</th>
                                <th class="px-3 py-2 text-left font-semibold text-slate-600">Party</th>
                                <th class="px-3 py-2 text-left font-semibold text-slate-600">Section</th>
                                <th class="px-3 py-2 text-right font-semibold text-slate-600">Base</th>
                                <th class="px-3 py-2 text-right font-semibold text-slate-600">WHT</th>
                                <th class="px-3 py-2 text-left font-semibold text-slate-600">Challan</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                        <?php if (!empty($wht_rows)): ?>
                            <?php foreach ($wht_rows as $row): ?>
                                <tr class="hover:bg-slate-50/60">
                                    <td class="px-3 py-2 text-[11px] text-slate-700">
                                        <?= $h($row['date'] ?? '—') ?>
                                    </td>
                                    <td class="px-3 py-2">
                                        <div class="text-xs text-slate-800"><?= $h($row['source'] ?? '—') ?></div>
                                        <div class="text-[11px] text-slate-500"><?= $h($row['ref'] ?? '') ?></div>
                                    </td>
                                    <td class="px-3 py-2 text-xs text-slate-800">
                                        <?= $h($row['party'] ?? '—') ?>
                                    </td>
                                    <td class="px-3 py-2 text-[11px] text-slate-700">
                                        <?= $h($row['section'] ?? '') ?>
                                    </td>
                                    <td class="px-3 py-2 text-right text-xs text-slate-800">
                                        <?= $h(number_format((float)($row['base_amount'] ?? 0), 2)) ?>
                                    </td>
                                    <td class="px-3 py-2 text-right text-xs text-slate-800">
                                        <?= $h(number_format((float)($row['wht_amount'] ?? 0), 2)) ?>
                                    </td>
                                    <td class="px-3 py-2 text-[11px] text-slate-700">
                                        <?php if (!empty($row['challan_no'])): ?>
                                            <?= $h($row['challan_no']) ?> (<?= $h($row['challan_date'] ?? '') ?>)
                                        <?php else: ?>
                                            <span class="text-amber-700">Not yet deposited</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" class="px-3 py-4 text-center text-[11px] text-slate-500">
                                    No WHT rows yet. Supplier/expense WHT will appear once posting is active.
                                </td>
                            </tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Returns anchor (placeholder) -->
            <div id="returns" class="rounded-2xl border border-dashed border-slate-300 bg-slate-50 px-4 py-4 text-xs">
                <h2 class="mb-1 text-sm font-semibold text-slate-800">Returns &amp; challans (coming soon)</h2>
                <p class="text-[11px] text-slate-600">
                    Once you share BD VAT and tax submission forms, we will map:
                </p>
                <ul class="ml-5 mt-1 list-disc space-y-1 text-[11px] text-slate-600">
                    <li>Mushak summaries from VAT input/output registers.</li>
                    <li>WHT challan tracking against each section.</li>
                    <li>“How much to deposit” snapshot by tax period.</li>
                </ul>
            </div>
        </section>

        <!-- RIGHT: How to use -->
        <aside class="space-y-4">
            <section class="rounded-2xl border border-dashed border-emerald-400 bg-emerald-50/60 px-4 py-4 text-sm text-slate-800">
                <h2 class="mb-2 flex items-center gap-2 text-sm font-semibold text-emerald-900">
                    <span class="inline-flex h-6 w-6 items-center justify-center rounded-full bg-emerald-600 text-[11px] text-white">
                        ?
                    </span>
                    How to use this page
                </h2>
                <ul class="ml-6 list-disc space-y-1 text-[13px]">
                    <li>Use this as your central view for <strong>VAT input</strong>, <strong>VAT output</strong> and <strong>WHT</strong> status.</li>
                    <li>Once expenses, purchases and sales modules are wired, this center will auto-calc what is
                        <strong>payable to NBR</strong> for each period.</li>
                    <li>WHT rows (AIT) will help you track which deductions are <strong>still pending challan</strong>.</li>
                    <li>Future phase: we will plug this into Bangladesh VAT forms (Mushak) and income tax schedules.</li>
                </ul>
            </section>
        </aside>
    </div>
</div>