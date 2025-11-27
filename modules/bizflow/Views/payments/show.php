<?php
/** @var array  $payment */
/** @var array  $allocations */
/** @var array  $org */
/** @var string $module_base */
/** @var string $title */
/** @var bool   $storage_ready */

$h = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');

$module_base = rtrim($module_base ?? ($base ?? '/apps/bizflow'), '/');
$orgName     = $org['name'] ?? '';

$id       = (int)($payment['id'] ?? 0);
$date     = $payment['date'] ?? ($payment['created_at'] ?? '');
$amount   = (float)($payment['amount'] ?? 0);
$currency = $payment['currency'] ?? 'BDT';
$method   = $payment['method'] ?? '';
$ref      = $payment['reference'] ?? '';
$notes    = $payment['notes'] ?? '';
$custName = $payment['customer_name'] ?? '';
$custCode = $payment['customer_code'] ?? '';
?>
<div class="space-y-6">

    <!-- Header -->
    <header class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
        <div class="space-y-1">
            <div class="text-xs text-slate-500 flex items-center gap-1">
                <a href="<?= $h($module_base.'/payments') ?>" class="hover:underline">Payments</a>
                <span>/</span>
                <span class="font-medium text-slate-700">
                    Payment #<?= $h($id > 0 ? (string)$id : 'N/A') ?>
                </span>
            </div>

            <h1 class="text-2xl md:text-3xl font-semibold tracking-tight">
                <?= $h(number_format($amount, 2)) ?> <?= $h($currency) ?>
            </h1>
            <p class="text-sm text-slate-500">
                Received on <?= $h($date ?: '—') ?> for <?= $h($custName !== '' ? $custName : ($orgName ?: 'customer')) ?>.
            </p>

            <?php if (!$storage_ready): ?>
                <p class="mt-1 text-xs text-amber-700">
                    This is a preview shell. Once <code>biz_payments</code> is created, this page
                    will show live data from the database.
                </p>
            <?php endif; ?>
        </div>

        <nav class="flex flex-wrap justify-end gap-1 text-xs md:text-[13px]">
            <a href="<?= $h($module_base.'/payments') ?>"
               class="inline-flex items-center gap-1 rounded-full px-3 py-1 border border-slate-200 text-slate-600 hover:bg-slate-50">
                <i class="fa fa-arrow-left text-[11px]"></i>
                <span>Back to payments</span>
            </a>
        </nav>
    </header>

    <!-- Layout -->
    <div class="grid gap-6 lg:grid-cols-[2.1fr,1.1fr]">

        <!-- LEFT: allocations + timeline -->
        <section class="space-y-4">

            <!-- Allocation summary -->
            <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                <div class="flex items-center justify-between mb-3">
                    <h2 class="text-sm font-semibold text-slate-800 flex items-center gap-2">
                        <span class="inline-flex h-6 w-6 items-center justify-center rounded-full bg-sky-500 text-[11px] text-white">
                            AL
                        </span>
                        Allocations
                    </h2>
                    <span class="text-xs text-slate-500">
                        <?= $h(count($allocations)) ?> records
                    </span>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200 text-sm">
                        <thead class="bg-slate-50 text-xs font-semibold text-slate-500 uppercase tracking-wide">
                        <tr>
                            <th class="px-3 py-2 text-left">Invoice</th>
                            <th class="px-3 py-2 text-left">Date</th>
                            <th class="px-3 py-2 text-right">Allocated</th>
                        </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 bg-white">
                        <?php if (!empty($allocations)): ?>
                            <?php foreach ($allocations as $row): ?>
                                <?php
                                $invId   = $row['invoice_id'] ?? null;
                                $invNo   = $row['invoice_no'] ?? $invId;
                                $invDate = $row['invoice_date'] ?? '';
                                $amt     = $row['amount'] ?? $row['allocated_amount'] ?? 0;
                                ?>
                                <tr class="hover:bg-emerald-50/40">
                                    <td class="px-3 py-2 align-top text-xs text-slate-700">
                                        <?php if ($invId): ?>
                                            <a href="<?= $h($module_base.'/invoices/'.$invId) ?>"
                                               class="font-mono text-[11px] text-emerald-700 hover:underline">
                                                <?= $h($invNo !== '' ? $invNo : ('INV-'.$invId)) ?>
                                            </a>
                                        <?php else: ?>
                                            <span class="text-slate-400">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-3 py-2 align-top text-xs text-slate-600">
                                        <?= $h($invDate ?: '—') ?>
                                    </td>
                                    <td class="px-3 py-2 align-top text-right text-xs text-slate-800 whitespace-nowrap">
                                        <?= $h(number_format((float)$amt, 2)) ?> <?= $h($currency) ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="3" class="px-3 py-4 text-center text-xs text-slate-500">
                                    No allocations yet. Once invoices are linked, they will show here.
                                </td>
                            </tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Timeline shell -->
            <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                <h2 class="text-sm font-semibold text-slate-800 mb-3">Event timeline (preview)</h2>
                <ol class="relative ml-3 border-l border-slate-200 pl-4 text-xs text-slate-600 space-y-3">
                    <li class="relative">
                        <span class="absolute -left-[9px] mt-0.5 inline-flex h-3 w-3 items-center justify-center rounded-full bg-emerald-600"></span>
                        <div class="text-slate-800 font-medium">Payment recorded</div>
                        <div class="text-[11px] text-slate-500">
                            <?= $h($date ?: 'Date pending') ?> — Amount <?= $h(number_format($amount, 2)) ?> <?= $h($currency) ?>
                        </div>
                    </li>
                    <li class="relative">
                        <span class="absolute -left-[9px] mt-0.5 inline-flex h-3 w-3 items-center justify-center rounded-full bg-slate-300"></span>
                        <div class="text-slate-800 font-medium">Allocated to invoices (future)</div>
                        <div class="text-[11px] text-slate-500">
                            When BizFlow AR engine is live, each allocation/edit/refund will appear in this history.
                        </div>
                    </li>
                </ol>
            </div>

        </section>

        <!-- RIGHT: info card + how-to -->
        <aside class="space-y-4">

            <!-- Info card -->
            <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm space-y-3">
                <h2 class="text-sm font-semibold text-slate-800">Payment details</h2>
                <dl class="space-y-2 text-xs text-slate-600">
                    <div class="flex justify-between gap-4">
                        <dt class="text-slate-500">Customer</dt>
                        <dd class="text-right text-slate-800">
                            <?php if ($custName !== ''): ?>
                                <div><?= $h($custName) ?></div>
                                <?php if ($custCode !== ''): ?>
                                    <div class="font-mono text-[11px] text-slate-500"><?= $h($custCode) ?></div>
                                <?php endif; ?>
                            <?php else: ?>
                                <span class="text-slate-400">—</span>
                            <?php endif; ?>
                        </dd>
                    </div>
                    <div class="flex justify-between gap-4">
                        <dt class="text-slate-500">Method</dt>
                        <dd class="text-right text-slate-800">
                            <?= $h($method !== '' ? ucfirst(str_replace('_',' ',$method)) : '—') ?>
                        </dd>
                    </div>
                    <div class="flex justify-between gap-4">
                        <dt class="text-slate-500">Reference</dt>
                        <dd class="text-right text-slate-800">
                            <?= $ref !== '' ? $h($ref) : '<span class="text-slate-400">—</span>' ?>
                        </dd>
                    </div>
                    <div class="flex justify-between gap-4">
                        <dt class="text-slate-500">Organisation</dt>
                        <dd class="text-right text-slate-800">
                            <?= $h($orgName ?: '—') ?>
                        </dd>
                    </div>
                </dl>

                <?php if ($notes !== ''): ?>
                    <div class="mt-3 rounded-lg bg-slate-50 px-3 py-2 text-xs text-slate-700">
                        <div class="mb-1 text-[11px] font-medium text-slate-500">Internal notes</div>
                        <p><?= nl2br($h($notes)) ?></p>
                    </div>
                <?php endif; ?>
            </div>

            <!-- How to use this page -->
            <div class="rounded-xl border border-dashed border-emerald-400 bg-emerald-50/60 px-4 py-4 text-sm text-slate-800">
                <h2 class="mb-2 flex items-center gap-2 text-sm font-semibold text-emerald-900">
                    <span class="inline-flex h-6 w-6 items-center justify-center rounded-full bg-emerald-600 text-[11px] text-white">
                        ?
                    </span>
                    How to use this page
                </h2>
                <ul class="ml-6 list-disc space-y-1 text-[13px]">
                    <li>Review the <strong>amount, method and reference</strong> to confirm the payment matches your bank / cash records.</li>
                    <li>Check the <strong>allocations</strong> table to see which invoices were cleared by this receipt (once schema is active).</li>
                    <li>Use the <strong>timeline</strong> as a quick audit trail of when the payment was created and updated.</li>
                    <li>This page will later support <strong>refunds, re-allocations</strong> and <strong>GL posting drill-down</strong> in BizFlow.</li>
                </ul>
            </div>
        </aside>
    </div>
</div>