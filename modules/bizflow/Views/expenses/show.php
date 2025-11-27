<?php
declare(strict_types=1);

/** @var array  $org */
/** @var string $module_base */
/** @var array  $expense */
/** @var array  $history */
/** @var string $title */
/** @var bool   $storage_ready */

$h = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');

$module_base = rtrim($module_base ?? '/apps/bizflow', '/');
$orgName     = trim((string)($org['name'] ?? '')) ?: 'your organisation';

$id     = (int)($expense['id'] ?? 0);
$gross  = (float)($expense['amount_gross'] ?? 0);
$vat    = (float)($expense['vat_amount'] ?? 0);
$wht    = (float)($expense['wht_amount'] ?? 0);
$status = strtolower((string)($expense['status'] ?? 'draft'));
?>
<div class="space-y-6">

    <!-- Header + back link -->
    <header class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
        <div>
            <div class="mb-1 text-[11px] text-slate-500">
                <a href="<?= $h($module_base.'/expenses') ?>" class="hover:underline">&larr; Back to expenses</a>
            </div>
            <h1 class="text-2xl md:text-3xl font-semibold tracking-tight">
                <?= $h($title ?? 'Expense details') ?>
            </h1>
            <p class="text-sm text-slate-500">
                Voucher <?= $h($expense['voucher_no'] ?? '') ?> for <?= $h($orgName) ?>.
            </p>
        </div>

        <div class="flex items-center gap-2">
            <a href="<?= $h($module_base.'/expenses/'.$id.'/edit') ?>"
               class="inline-flex items-center rounded-lg border border-slate-200 px-3 py-1.5 text-[11px] text-slate-700 hover:bg-slate-50">
                Edit
            </a>
        </div>
    </header>

    <div class="grid gap-6 lg:grid-cols-[2.1fr,1.1fr]">

        <!-- LEFT: main cards -->
        <section class="space-y-4">
            <div class="grid gap-3 md:grid-cols-3 text-xs">
                <div class="rounded-2xl border border-slate-200 bg-white px-4 py-3 shadow-sm">
                    <div class="text-[11px] text-slate-500">Gross amount (BDT)</div>
                    <div class="mt-1 text-xl font-semibold text-slate-900">
                        <?= $h(number_format($gross, 2)) ?>
                    </div>
                    <div class="mt-1 text-[11px] text-slate-500">
                        Date: <?= $h($expense['expense_date'] ?? '—') ?>
                    </div>
                </div>
                <div class="rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 shadow-sm">
                    <div class="text-[11px] text-emerald-700">VAT (BDT)</div>
                    <div class="mt-1 text-xl font-semibold text-emerald-900">
                        <?= $h(number_format($vat, 2)) ?>
                    </div>
                    <div class="mt-1 text-[11px] text-emerald-700">
                        Feeds VAT input register
                    </div>
                </div>
                <div class="rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 shadow-sm">
                    <div class="text-[11px] text-rose-700">WHT (BDT)</div>
                    <div class="mt-1 text-xl font-semibold text-rose-900">
                        <?= $h(number_format($wht, 2)) ?>
                    </div>
                    <div class="mt-1 text-[11px] text-rose-700">
                        Feeds WHT payable register
                    </div>
                </div>
            </div>

            <div class="rounded-2xl border border-slate-200 bg-white px-4 py-4 text-xs shadow-sm">
                <h2 class="mb-2 text-sm font-semibold text-slate-800">Voucher details</h2>
                <dl class="grid gap-x-6 gap-y-2 md:grid-cols-2">
                    <div>
                        <dt class="text-[11px] font-medium text-slate-500">Voucher no</dt>
                        <dd class="text-slate-800"><?= $h($expense['voucher_no'] ?? '—') ?></dd>
                    </div>
                    <div>
                        <dt class="text-[11px] font-medium text-slate-500">Status</dt>
                        <dd class="text-slate-800 capitalize"><?= $h($status ?: 'draft') ?></dd>
                    </div>
                    <div>
                        <dt class="text-[11px] font-medium text-slate-500">Category</dt>
                        <dd class="text-slate-800"><?= $h($expense['category_name'] ?? 'Uncategorised') ?></dd>
                    </div>
                    <div>
                        <dt class="text-[11px] font-medium text-slate-500">Payment method</dt>
                        <dd class="text-slate-800"><?= $h(ucfirst((string)($expense['payment_method'] ?? ''))) ?></dd>
                    </div>
                    <div class="md:col-span-2">
                        <dt class="text-[11px] font-medium text-slate-500">Payee</dt>
                        <dd class="text-slate-800"><?= $h($expense['payee_name'] ?? '—') ?></dd>
                    </div>
                    <div class="md:col-span-2">
                        <dt class="text-[11px] font-medium text-slate-500">Reference</dt>
                        <dd class="text-slate-800"><?= $h($expense['reference'] ?? '—') ?></dd>
                    </div>
                    <div class="md:col-span-2">
                        <dt class="text-[11px] font-medium text-slate-500">Description</dt>
                        <dd class="text-slate-800 whitespace-pre-line">
                            <?= $h($expense['description'] ?? '') ?>
                        </dd>
                    </div>
                </dl>
            </div>
        </section>

        <!-- RIGHT: timeline + how to -->
        <aside class="space-y-4">
            <section class="rounded-2xl border border-slate-200 bg-white px-4 py-4 text-xs shadow-sm">
                <h2 class="mb-2 text-sm font-semibold text-slate-800">Timeline</h2>
                <?php if (!empty($history)): ?>
                    <ol class="space-y-2">
                        <?php foreach ($history as $ev): ?>
                            <li class="flex items-start gap-2">
                                <div class="mt-1 h-2 w-2 rounded-full bg-emerald-600"></div>
                                <div>
                                    <div class="text-[11px] font-medium text-slate-700">
                                        <?= $h($ev['label']) ?>
                                    </div>
                                    <div class="text-[11px] text-slate-500">
                                        <?= $h($ev['ts']) ?>
                                    </div>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ol>
                <?php else: ?>
                    <p class="text-[11px] text-slate-500">
                        Timeline events will appear here as we wire approvals and payments into the engine.
                    </p>
                <?php endif; ?>
            </section>

            <section class="rounded-2xl border border-dashed border-emerald-400 bg-emerald-50/60 px-4 py-4 text-sm text-slate-800">
                <h2 class="mb-2 flex items-center gap-2 text-sm font-semibold text-emerald-900">
                    <span class="inline-flex h-6 w-6 items-center justify-center rounded-full bg-emerald-600 text-[11px] text-white">
                        ?
                    </span>
                    How to use this page
                </h2>
                <ul class="ml-6 list-disc space-y-1 text-[13px]">
                    <li>Review the full breakdown of an expense before approving or paying.</li>
                    <li>Use VAT/WHT amounts here to reconcile with your tax registers and challans.</li>
                    <li>Keep descriptions clear; these details will appear in future exports and audit reports.</li>
                </ul>
            </section>
        </aside>
    </div>
</div>