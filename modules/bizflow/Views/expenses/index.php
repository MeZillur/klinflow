<?php
declare(strict_types=1);

/** @var array  $org */
/** @var string $module_base */
/** @var array  $expenses */
/** @var array  $metrics */
/** @var array  $filters */
/** @var string $title */
/** @var bool   $storage_ready */
/** @var ?string $flash */

$h = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');

$module_base = rtrim($module_base ?? '/apps/bizflow', '/');
$orgName     = trim((string)($org['name'] ?? '')) ?: 'your organisation';
?>
<div class="space-y-6">

    <!-- Header + tabs -->
    <header class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
        <div>
            <h1 class="text-2xl md:text-3xl font-semibold tracking-tight">
                <?= $h($title ?? 'Expenses') ?>
            </h1>
            <p class="text-sm text-slate-500">
                Expense vouchers for <?= $h($orgName) ?> — with VAT &amp; WHT visibility.
            </p>
        </div>

        <?php
        $tabs = [
            ['Expenses',   $module_base.'/expenses',       true],
            ['New expense',$module_base.'/expenses/create', false],
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

    <?php if ($flash): ?>
        <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2 text-xs text-emerald-800">
            <?= $h($flash) ?>
        </div>
    <?php endif; ?>

    <?php if (!$storage_ready): ?>
        <div class="rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-900">
            Expense storage table <code>biz_expenses</code> is not created yet. You are viewing demo records only.
            Schema &amp; posting will come after we lock the front-end.
        </div>
    <?php endif; ?>

    <!-- Metrics row -->
    <section class="grid gap-3 md:grid-cols-4 text-xs">
        <div class="rounded-2xl border border-slate-200 bg-white px-4 py-3 shadow-sm">
            <div class="text-[11px] text-slate-500">Total vouchers</div>
            <div class="mt-1 text-xl font-semibold text-slate-900">
                <?= $h((string)($metrics['total_count'] ?? 0)) ?>
            </div>
        </div>
        <div class="rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 shadow-sm">
            <div class="text-[11px] text-emerald-700">Total gross (BDT)</div>
            <div class="mt-1 text-xl font-semibold text-emerald-900">
                <?= $h(number_format((float)($metrics['total_gross'] ?? 0), 2)) ?>
            </div>
        </div>
        <div class="rounded-2xl border border-slate-200 bg-white px-4 py-3 shadow-sm">
            <div class="text-[11px] text-slate-500">VAT (BDT)</div>
            <div class="mt-1 text-xl font-semibold text-slate-900">
                <?= $h(number_format((float)($metrics['total_vat'] ?? 0), 2)) ?>
            </div>
        </div>
        <div class="rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 shadow-sm">
            <div class="text-[11px] text-rose-700">WHT (BDT)</div>
            <div class="mt-1 text-xl font-semibold text-rose-900">
                <?= $h(number_format((float)($metrics['total_wht'] ?? 0), 2)) ?>
            </div>
        </div>
    </section>

    <div class="grid gap-6 lg:grid-cols-[2.2fr,1.1fr]">

        <!-- LEFT: expenses table -->
        <section class="rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="flex flex-col gap-3 border-b border-slate-100 px-4 py-3 md:flex-row md:items-center md:justify-between">
                <div>
                    <h2 class="text-sm font-semibold text-slate-800">Expense register</h2>
                    <p class="text-xs text-slate-500">
                        Filter by category, date, method or tax to drive VAT &amp; WHT reports later.
                    </p>
                </div>
                <a href="<?= $h($module_base.'/expenses/create') ?>"
                   class="inline-flex items-center gap-1 rounded-lg bg-slate-900 px-3 py-2 text-xs font-medium text-white shadow-sm hover:bg-slate-800">
                    <span>+ New expense</span>
                </a>
            </div>

            <!-- Filters -->
            <form method="get" action="<?= $h($module_base.'/expenses') ?>" class="border-b border-slate-100 px-4 py-3 text-xs">
                <div class="grid gap-2 md:grid-cols-6">
                    <div class="md:col-span-2">
                        <input type="text"
                               name="q"
                               value="<?= $h($filters['q'] ?? '') ?>"
                               placeholder="Search voucher no, payee, description, reference…"
                               class="w-full rounded-lg border border-slate-200 px-3 py-1.5 text-xs focus:border-emerald-600 focus:outline-none focus:ring-1 focus:ring-emerald-600">
                    </div>
                    <div>
                        <input type="text"
                               name="category"
                               value="<?= $h($filters['category'] ?? '') ?>"
                               placeholder="Category (e.g. Rent, Utilities)"
                               class="w-full rounded-lg border border-slate-200 px-3 py-1.5 text-xs focus:border-emerald-600 focus:outline-none focus:ring-1 focus:ring-emerald-600">
                    </div>
                    <div>
                        <select name="method"
                                class="w-full rounded-lg border border-slate-200 px-2 py-1.5 text-xs focus:border-emerald-600 focus:outline-none focus:ring-1 focus:ring-emerald-600">
                            <?php $sel = (string)($filters['method'] ?? ''); ?>
                            <option value="">Method</option>
                            <option value="cash"  <?= $sel === 'cash'  ? 'selected' : '' ?>>Cash</option>
                            <option value="bank"  <?= $sel === 'bank'  ? 'selected' : '' ?>>Bank</option>
                            <option value="mobile"<?= $sel === 'mobile'? 'selected' : '' ?>>Mobile</option>
                        </select>
                    </div>
                    <div class="flex gap-2">
                        <input type="date"
                               name="from"
                               value="<?= $h($filters['from'] ?? '') ?>"
                               class="w-1/2 rounded-lg border border-slate-200 px-2 py-1.5 text-xs focus:border-emerald-600 focus:outline-none focus:ring-1 focus:ring-emerald-600">
                        <input type="date"
                               name="to"
                               value="<?= $h($filters['to'] ?? '') ?>"
                               class="w-1/2 rounded-lg border border-slate-200 px-2 py-1.5 text-xs focus:border-emerald-600 focus:outline-none focus:ring-1 focus:ring-emerald-600">
                    </div>
                    <div class="flex items-center gap-2">
                        <?php $hasTax = (string)($filters['has_tax'] ?? ''); ?>
                        <label class="inline-flex items-center gap-1 text-[11px] text-slate-600">
                            <input type="checkbox"
                                   name="has_tax"
                                   value="1"
                                   <?= $hasTax === '1' ? 'checked' : '' ?>
                                   class="h-3 w-3 rounded border-slate-300 text-emerald-600 focus:ring-emerald-600">
                            <span>With VAT/WHT only</span>
                        </label>
                    </div>
                </div>

                <div class="mt-2 flex justify-end gap-2">
                    <a href="<?= $h($module_base.'/expenses') ?>"
                       class="rounded-lg border border-slate-200 px-3 py-1.5 text-[11px] text-slate-600 hover:bg-slate-50">
                        Reset
                    </a>
                    <button type="submit"
                            class="rounded-lg bg-emerald-600 px-3 py-1.5 text-[11px] font-medium text-white hover:bg-emerald-700">
                        Apply filters
                    </button>
                </div>
            </form>

            <!-- Table -->
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-100 text-xs">
                    <thead class="bg-slate-50">
                        <tr>
                            <th class="px-3 py-2 text-left font-semibold text-slate-600">Voucher</th>
                            <th class="px-3 py-2 text-left font-semibold text-slate-600">Date &amp; category</th>
                            <th class="px-3 py-2 text-left font-semibold text-slate-600">Payee / note</th>
                            <th class="px-3 py-2 text-right font-semibold text-slate-600">Gross</th>
                            <th class="px-3 py-2 text-right font-semibold text-slate-600">VAT / WHT</th>
                            <th class="px-3 py-2 text-left font-semibold text-slate-600">Method</th>
                            <th class="px-3 py-2 text-left font-semibold text-slate-600">Status</th>
                            <th class="px-3 py-2 text-left font-semibold text-slate-600"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                    <?php if (!empty($expenses)): ?>
                        <?php foreach ($expenses as $row): ?>
                            <?php
                            $id     = (int)($row['id'] ?? 0);
                            $status = strtolower((string)($row['status'] ?? 'draft'));
                            $badge  = 'bg-slate-100 text-slate-700';
                            if ($status === 'posted') {
                                $badge = 'bg-emerald-50 text-emerald-700 border border-emerald-100';
                            } elseif ($status === 'cancelled') {
                                $badge = 'bg-rose-50 text-rose-700 border border-rose-100';
                            }
                            $gross = (float)($row['amount_gross'] ?? 0);
                            $vat   = (float)($row['vat_amount'] ?? 0);
                            $wht   = (float)($row['wht_amount'] ?? 0);
                            ?>
                            <tr class="hover:bg-slate-50/60">
                                <td class="px-3 py-2 align-top">
                                    <div class="font-semibold text-slate-800">
                                        <a href="<?= $h($module_base.'/expenses/'.$id) ?>" class="hover:underline">
                                            <?= $h($row['voucher_no'] ?? '') ?>
                                        </a>
                                    </div>
                                    <div class="text-[11px] text-slate-500">
                                        Ref: <?= $h($row['reference'] ?? '—') ?>
                                    </div>
                                </td>
                                <td class="px-3 py-2 align-top text-[11px] text-slate-700">
                                    <div><?= $h($row['expense_date'] ?? '—') ?></div>
                                    <div class="text-slate-500">
                                        <?= $h($row['category_name'] ?? 'Uncategorised') ?>
                                    </div>
                                </td>
                                <td class="px-3 py-2 align-top">
                                    <div class="text-xs font-medium text-slate-800">
                                        <?= $h($row['payee_name'] ?? '—') ?>
                                    </div>
                                    <div class="text-[11px] text-slate-500 line-clamp-2">
                                        <?= $h($row['description'] ?? '') ?>
                                    </div>
                                </td>
                                <td class="px-3 py-2 align-top text-right">
                                    <div class="text-xs font-semibold text-slate-900">
                                        <?= $h(number_format($gross, 2)) ?>
                                    </div>
                                </td>
                                <td class="px-3 py-2 align-top text-right text-[11px] text-slate-700">
                                    <div>VAT: <?= $h(number_format($vat, 2)) ?></div>
                                    <div>WHT: <?= $h(number_format($wht, 2)) ?></div>
                                </td>
                                <td class="px-3 py-2 align-top text-[11px] text-slate-700">
                                    <?= $h(ucfirst((string)($row['payment_method'] ?? ''))) ?>
                                </td>
                                <td class="px-3 py-2 align-top">
                                    <span class="inline-flex items-center rounded-full px-2 py-0.5 text-[11px] <?= $badge ?>">
                                        <?= $h(ucfirst($status ?: 'draft')) ?>
                                    </span>
                                </td>
                                <td class="px-3 py-2 align-top text-right">
                                    <a href="<?= $h($module_base.'/expenses/'.$id.'/edit') ?>"
                                       class="text-[11px] text-slate-500 hover:text-slate-800 hover:underline">
                                        Edit
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="8" class="px-3 py-6 text-center text-xs text-slate-500">
                                No expenses recorded yet. Use <strong>New expense</strong> to add your first voucher.
                            </td>
                        </tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <!-- RIGHT: How to / notes -->
        <aside class="space-y-4">
            <section class="rounded-2xl border border-dashed border-emerald-400 bg-emerald-50/60 px-4 py-4 text-sm text-slate-800">
                <h2 class="mb-2 flex items-center gap-2 text-sm font-semibold text-emerald-900">
                    <span class="inline-flex h-6 w-6 items-center justify-center rounded-full bg-emerald-600 text-[11px] text-white">
                        ?
                    </span>
                    How to use this page
                </h2>
                <ul class="ml-6 list-disc space-y-1 text-[13px]">
                    <li>Record <strong>non-PO expenses</strong> here (rent, utilities, travel, small purchases, etc.).</li>
                    <li>Always set a proper <strong>category</strong> so reporting and GL mapping stay clean.</li>
                    <li>Use the <strong>VAT/WHT fields</strong> when tax is deducted or paid at source — BizFlow will feed this to your Tax/VAT/WHT center.</li>
                    <li>Filters on the top of the table help you slice by period, method, or only tax-bearing vouchers.</li>
                </ul>
            </section>
        </aside>
    </div>
</div>