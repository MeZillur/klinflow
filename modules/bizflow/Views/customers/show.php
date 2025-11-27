<?php
/** @var array  $customer */
/** @var array  $metrics */
/** @var array  $quotes */
/** @var array  $orders */
/** @var array  $invoices */
/** @var array  $payments */
/** @var array  $org */
/** @var string $module_base */
/** @var string $title */

$h = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');

$module_base = rtrim($module_base ?? ($base ?? '/apps/bizflow'), '/');
$orgName     = $org['name'] ?? '';

$name        = $customer['name'] ?? '';
$code        = $customer['code'] ?? '';
$segment     = $customer['segment'] ?? '';
$company     = $customer['company_name'] ?? '';
$email       = $customer['email'] ?? '';
$phone       = $customer['phone'] ?? '';
$altPhone    = $customer['alt_phone'] ?? '';
$city        = $customer['city'] ?? '';
$district    = $customer['district'] ?? '';
$country     = $customer['country'] ?? '';
$locParts    = array_filter([$city, $district, $country]);
$loc         = implode(', ', $locParts);
$active      = (int)($customer['is_active'] ?? 0) === 1;

$metrics = $metrics ?? [];
$lifetimeInvoiced   = $metrics['lifetime_invoiced']   ?? null;
$lifetimePaid       = $metrics['lifetime_paid']       ?? null;
$outstandingBalance = $metrics['outstanding_balance'] ?? null;
$lastActivity       = $metrics['last_activity_at']    ?? null;
?>
<div class="space-y-6">

    <!-- Top: breadcrumb + title + tabs -->
    <header class="space-y-3">
        <div class="text-xs text-slate-500 flex items-center gap-1">
            <a href="<?= $h($module_base.'/customers') ?>" class="hover:underline">Customers</a>
            <span>/</span>
            <span class="font-medium text-slate-700"><?= $h($code ?: 'Customer') ?></span>
        </div>

        <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
            <div>
                <div class="flex items-center gap-3">
                    <div class="flex h-11 w-11 items-center justify-center rounded-full bg-emerald-600 text-sm font-semibold text-white">
                        <?= $h(strtoupper(substr($name ?: ($company ?: 'C'), 0, 2))) ?>
                    </div>
                    <div>
                        <h1 class="text-2xl md:text-3xl font-semibold tracking-tight">
                            <?= $h($name ?: 'Customer') ?>
                        </h1>
                        <?php if ($company !== ''): ?>
                            <p class="text-xs text-slate-500"><?= $h($company) ?></p>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="mt-3 flex flex-wrap gap-2 text-xs">
                    <?php if ($code !== ''): ?>
                        <span class="inline-flex items-center gap-1 rounded-full border border-slate-200 bg-slate-50 px-2 py-[3px] font-mono text-[11px] text-slate-700">
                            <i class="fa fa-hashtag text-[9px]"></i> <?= $h($code) ?>
                        </span>
                    <?php endif; ?>
                    <?php if ($segment !== ''): ?>
                        <span class="inline-flex items-center gap-1 rounded-full bg-emerald-50 px-2 py-[3px] text-[11px] font-medium text-emerald-700">
                            <i class="fa fa-layer-group text-[9px]"></i> <?= $h(strtoupper($segment)) ?>
                        </span>
                    <?php endif; ?>
                    <?php if ($loc !== ''): ?>
                        <span class="inline-flex items-center gap-1 rounded-full bg-slate-50 px-2 py-[3px] text-[11px] text-slate-600">
                            <i class="fa fa-location-dot text-[9px]"></i> <?= $h($loc) ?>
                        </span>
                    <?php endif; ?>
                    <span class="inline-flex items-center gap-1 rounded-full px-2 py-[3px] text-[11px]
                                 <?= $active
                                        ? 'bg-emerald-50 text-emerald-700'
                                        : 'bg-slate-100 text-slate-600' ?>">
                        <span class="h-1.5 w-1.5 rounded-full <?= $active ? 'bg-emerald-500' : 'bg-slate-400' ?>"></span>
                        <?= $active ? 'Active' : 'Inactive' ?>
                    </span>
                </div>
            </div>

            <!-- Right: mini nav (BizFlow tabs scoped to customers) -->
            <nav class="flex flex-wrap justify-end gap-1 text-xs md:text-[13px]">
                <a href="<?= $h($module_base.'/customers') ?>"
                   class="inline-flex items-center gap-1 rounded-full px-3 py-1 border border-slate-200 text-slate-600 hover:bg-slate-50">
                    <i class="fa fa-users text-[11px]"></i>
                    <span>All customers</span>
                </a>
                <a href="<?= $h($module_base.'/customers/'.$customer['id'].'/edit') ?>"
                   class="inline-flex items-center gap-1 rounded-full px-3 py-1 border border-slate-200 text-slate-700 hover:bg-slate-50">
                    <i class="fa fa-pen text-[11px]"></i>
                    <span>Edit</span>
                </a>
            </nav>
        </div>
    </header>

    <!-- Main layout: 2 columns on desktop -->
    <div class="grid gap-6 lg:grid-cols-[2fr,1fr]">

        <!-- LEFT: history + timelines -->
        <section class="space-y-4">
            <!-- Metrics row -->
            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                    <div class="text-xs font-medium text-slate-500">Lifetime invoiced</div>
                    <div class="mt-1 text-xl font-semibold text-slate-900">
                        <?php if ($lifetimeInvoiced !== null): ?>
                            <?= $h(number_format((float)$lifetimeInvoiced, 2)) ?> BDT
                        <?php else: ?>
                            <span class="text-slate-400">—</span>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                    <div class="text-xs font-medium text-slate-500">Lifetime paid</div>
                    <div class="mt-1 text-xl font-semibold text-slate-900">
                        <?php if ($lifetimePaid !== null): ?>
                            <?= $h(number_format((float)$lifetimePaid, 2)) ?> BDT
                        <?php else: ?>
                            <span class="text-slate-400">—</span>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                    <div class="text-xs font-medium text-slate-500">Outstanding balance</div>
                    <div class="mt-1 text-xl font-semibold <?= ($outstandingBalance ?? 0) > 0 ? 'text-amber-700' : 'text-slate-900' ?>">
                        <?php if ($outstandingBalance !== null): ?>
                            <?= $h(number_format((float)$outstandingBalance, 2)) ?> BDT
                        <?php else: ?>
                            <span class="text-slate-400">—</span>
                        <?php endif; ?>
                    </div>
                    <?php if ($lastActivity): ?>
                        <div class="mt-2 text-[11px] text-slate-500">
                            Last activity: <?= $h($lastActivity) ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Quotes -->
            <div class="rounded-xl border border-slate-200 bg-white shadow-sm">
                <div class="flex items-center justify-between border-b border-slate-100 px-4 py-3">
                    <h2 class="text-sm font-semibold text-slate-800 flex items-center gap-2">
                        <span class="inline-flex h-6 w-6 items-center justify-center rounded-full bg-emerald-600 text-[11px] text-white">
                            Q
                        </span>
                        Quotes
                    </h2>
                    <span class="text-xs text-slate-500">
                        <?= $h($metrics['open_quote_count'] ?? 0) ?> records
                    </span>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-100 text-sm">
                        <thead class="bg-slate-50 text-xs font-semibold text-slate-500 uppercase tracking-wide">
                        <tr>
                            <th class="px-4 py-2 text-left">Quote #</th>
                            <th class="px-4 py-2 text-left">Date</th>
                            <th class="px-4 py-2 text-left">Status</th>
                            <th class="px-4 py-2 text-right">Amount</th>
                        </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 bg-white">
                        <?php if (!empty($quotes)): ?>
                            <?php foreach ($quotes as $q): ?>
                                <?php
                                $no   = $q['quote_no']    ?? $q['id'] ?? '';
                                $date = $q['date']        ?? ($q['created_at'] ?? '');
                                $st   = $q['status']      ?? '';
                                $amt  = $q['grand_total'] ?? null;
                                ?>
                                <tr class="hover:bg-emerald-50/40">
                                    <td class="px-4 py-2 text-xs font-mono text-slate-700">
                                        <?= $h($no !== '' ? $no : ('Q-'.$q['id'])) ?>
                                    </td>
                                    <td class="px-4 py-2 text-xs text-slate-600"><?= $h($date ?: '—') ?></td>
                                    <td class="px-4 py-2 text-xs text-slate-600">
                                        <?= $st !== '' ? $h(ucfirst($st)) : '<span class="text-slate-400">—</span>' ?>
                                    </td>
                                    <td class="px-4 py-2 text-xs text-right text-slate-700">
                                        <?php if ($amt !== null && $amt !== ''): ?>
                                            <?= $h(number_format((float)$amt, 2)) ?> BDT
                                        <?php else: ?>
                                            <span class="text-slate-400">—</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="4" class="px-4 py-4 text-center text-xs text-slate-500">
                                    No quotes yet for this customer.
                                </td>
                            </tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Orders -->
            <div class="rounded-xl border border-slate-200 bg-white shadow-sm">
                <div class="flex items-center justify-between border-b border-slate-100 px-4 py-3">
                    <h2 class="text-sm font-semibold text-slate-800 flex items-center gap-2">
                        <span class="inline-flex h-6 w-6 items-center justify-center rounded-full bg-slate-900 text-[11px] text-white">
                            SO
                        </span>
                        Orders
                    </h2>
                    <span class="text-xs text-slate-500">
                        <?= $h($metrics['open_order_count'] ?? 0) ?> records
                    </span>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-100 text-sm">
                        <thead class="bg-slate-50 text-xs font-semibold text-slate-500 uppercase tracking-wide">
                        <tr>
                            <th class="px-4 py-2 text-left">Order #</th>
                            <th class="px-4 py-2 text-left">Date</th>
                            <th class="px-4 py-2 text-left">Status</th>
                            <th class="px-4 py-2 text-right">Amount</th>
                        </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 bg-white">
                        <?php if (!empty($orders)): ?>
                            <?php foreach ($orders as $o): ?>
                                <?php
                                $no   = $o['order_no']    ?? $o['id'] ?? '';
                                $date = $o['date']        ?? ($o['created_at'] ?? '');
                                $st   = $o['status']      ?? '';
                                $amt  = $o['grand_total'] ?? null;
                                ?>
                                <tr class="hover:bg-emerald-50/40">
                                    <td class="px-4 py-2 text-xs font-mono text-slate-700">
                                        <?= $h($no !== '' ? $no : ('SO-'.$o['id'])) ?>
                                    </td>
                                    <td class="px-4 py-2 text-xs text-slate-600"><?= $h($date ?: '—') ?></td>
                                    <td class="px-4 py-2 text-xs text-slate-600">
                                        <?= $st !== '' ? $h(ucfirst($st)) : '<span class="text-slate-400">—</span>' ?>
                                    </td>
                                    <td class="px-4 py-2 text-xs text-right text-slate-700">
                                        <?php if ($amt !== null && $amt !== ''): ?>
                                            <?= $h(number_format((float)$amt, 2)) ?> BDT
                                        <?php else: ?>
                                            <span class="text-slate-400">—</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="4" class="px-4 py-4 text-center text-xs text-slate-500">
                                    No orders recorded for this customer.
                                </td>
                            </tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Invoices -->
            <div class="rounded-xl border border-slate-200 bg-white shadow-sm">
                <div class="flex items-center justify-between border-b border-slate-100 px-4 py-3">
                    <h2 class="text-sm font-semibold text-slate-800 flex items-center gap-2">
                        <span class="inline-flex h-6 w-6 items-center justify-center rounded-full bg-amber-500 text-[11px] text-white">
                            INV
                        </span>
                        Invoices
                    </h2>
                    <span class="text-xs text-slate-500">
                        <?= $h($metrics['invoice_count'] ?? 0) ?> records
                    </span>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-100 text-sm">
                        <thead class="bg-slate-50 text-xs font-semibold text-slate-500 uppercase tracking-wide">
                        <tr>
                            <th class="px-4 py-2 text-left">Invoice #</th>
                            <th class="px-4 py-2 text-left">Date</th>
                            <th class="px-4 py-2 text-left">Status</th>
                            <th class="px-4 py-2 text-right">Total</th>
                        </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 bg-white">
                        <?php if (!empty($invoices)): ?>
                            <?php foreach ($invoices as $inv): ?>
                                <?php
                                $no   = $inv['invoice_no']  ?? $inv['id'] ?? '';
                                $date = $inv['date']        ?? ($inv['created_at'] ?? '');
                                $st   = $inv['status']      ?? '';
                                $amt  = $inv['grand_total'] ?? null;
                                ?>
                                <tr class="hover:bg-emerald-50/40">
                                    <td class="px-4 py-2 text-xs font-mono text-slate-700">
                                        <?= $h($no !== '' ? $no : ('INV-'.$inv['id'])) ?>
                                    </td>
                                    <td class="px-4 py-2 text-xs text-slate-600"><?= $h($date ?: '—') ?></td>
                                    <td class="px-4 py-2 text-xs text-slate-600">
                                        <?= $st !== '' ? $h(ucfirst($st)) : '<span class="text-slate-400">—</span>' ?>
                                    </td>
                                    <td class="px-4 py-2 text-xs text-right text-slate-700">
                                        <?php if ($amt !== null && $amt !== ''): ?>
                                            <?= $h(number_format((float)$amt, 2)) ?> BDT
                                        <?php else: ?>
                                            <span class="text-slate-400">—</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="4" class="px-4 py-4 text-center text-xs text-slate-500">
                                    No invoices found for this customer.
                                </td>
                            </tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Payments -->
            <div class="rounded-xl border border-slate-200 bg-white shadow-sm">
                <div class="flex items-center justify-between border-b border-slate-100 px-4 py-3">
                    <h2 class="text-sm font-semibold text-slate-800 flex items-center gap-2">
                        <span class="inline-flex h-6 w-6 items-center justify-center rounded-full bg-sky-500 text-[11px] text-white">
                            RC
                        </span>
                        Payments
                    </h2>
                    <span class="text-xs text-slate-500">
                        <?= $h($metrics['payment_count'] ?? 0) ?> records
                    </span>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-100 text-sm">
                        <thead class="bg-slate-50 text-xs font-semibold text-slate-500 uppercase tracking-wide">
                        <tr>
                            <th class="px-4 py-2 text-left">Date</th>
                            <th class="px-4 py-2 text-left">Method</th>
                            <th class="px-4 py-2 text-left">Ref</th>
                            <th class="px-4 py-2 text-right">Amount</th>
                        </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 bg-white">
                        <?php if (!empty($payments)): ?>
                            <?php foreach ($payments as $p): ?>
                                <?php
                                $date = $p['date']        ?? ($p['created_at'] ?? '');
                                $mtd  = $p['method']      ?? ($p['channel'] ?? '');
                                $ref  = $p['reference']   ?? ($p['ref_no'] ?? '');
                                $amt  = $p['amount']      ?? null;
                                ?>
                                <tr class="hover:bg-emerald-50/40">
                                    <td class="px-4 py-2 text-xs text-slate-600"><?= $h($date ?: '—') ?></td>
                                    <td class="px-4 py-2 text-xs text-slate-600"><?= $mtd !== '' ? $h($mtd) : '<span class="text-slate-400">—</span>' ?></td>
                                    <td class="px-4 py-2 text-xs text-slate-600"><?= $ref !== '' ? $h($ref) : '<span class="text-slate-400">—</span>' ?></td>
                                    <td class="px-4 py-2 text-xs text-right text-slate-700">
                                        <?php if ($amt !== null && $amt !== ''): ?>
                                            <?= $h(number_format((float)$amt, 2)) ?> BDT
                                        <?php else: ?>
                                            <span class="text-slate-400">—</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="4" class="px-4 py-4 text-center text-xs text-slate-500">
                                    No payments recorded for this customer.
                                </td>
                            </tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </section>

        <!-- RIGHT: profile & contact card -->
        <aside class="space-y-4">
            <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                <h2 class="text-sm font-semibold text-slate-800 mb-3">Contact details</h2>
                <dl class="space-y-2 text-xs text-slate-600">
                    <div class="flex justify-between gap-4">
                        <dt class="text-slate-500">Name</dt>
                        <dd class="text-right text-slate-800"><?= $h($name ?: '—') ?></dd>
                    </div>
                    <?php if ($company !== ''): ?>
                        <div class="flex justify-between gap-4">
                            <dt class="text-slate-500">Company</dt>
                            <dd class="text-right text-slate-800"><?= $h($company) ?></dd>
                        </div>
                    <?php endif; ?>
                    <?php if ($phone !== '' || $altPhone !== ''): ?>
                        <div class="flex justify-between gap-4">
                            <dt class="text-slate-500">Phone</dt>
                            <dd class="text-right text-slate-800 space-y-0.5">
                                <?php if ($phone !== ''): ?>
                                    <div><?= $h($phone) ?></div>
                                <?php endif; ?>
                                <?php if ($altPhone !== ''): ?>
                                    <div class="text-slate-500 text-[11px]"><?= $h($altPhone) ?></div>
                                <?php endif; ?>
                            </dd>
                        </div>
                    <?php endif; ?>
                    <?php if ($email !== ''): ?>
                        <div class="flex justify-between gap-4">
                            <dt class="text-slate-500">Email</dt>
                            <dd class="text-right text-emerald-700"><?= $h($email) ?></dd>
                        </div>
                    <?php endif; ?>
                    <?php if ($loc !== ''): ?>
                        <div class="flex justify-between gap-4">
                            <dt class="text-slate-500">Location</dt>
                            <dd class="text-right text-slate-800"><?= $h($loc) ?></dd>
                        </div>
                    <?php endif; ?>
                </dl>
            </div>

            <!-- How to use this page -->
            <div class="rounded-xl border border-dashed border-emerald-400 bg-emerald-50/60 p-4 text-xs text-slate-800">
                <h2 class="mb-2 flex items-center gap-2 text-sm font-semibold text-emerald-900">
                    <span class="inline-flex h-6 w-6 items-center justify-center rounded-full bg-emerald-600 text-[11px] text-white">
                        ?
                    </span>
                    How to use this page
                </h2>
                <ul class="ml-5 list-disc space-y-1 text-[12px]">
                    <li>Review <strong>Lifetime invoiced</strong> and <strong>Outstanding balance</strong> to see this customer’s financial standing in BDT.</li>
                    <li>Scan through <strong>Quotes</strong>, <strong>Orders</strong>, <strong>Invoices</strong> and <strong>Payments</strong> to follow the full sales journey.</li>
                    <li>Use <strong>Edit</strong> to refresh contact, segment or credit terms without leaving BizFlow.</li>
                    <li>All figures and history are scoped to this organisation only, keeping multi-tenant data safe.</li>
                </ul>
            </div>
        </aside>
    </div>
</div>