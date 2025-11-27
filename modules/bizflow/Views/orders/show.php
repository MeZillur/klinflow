<?php
/** @var array  $order */
/** @var array  $items */
/** @var array  $invoices */
/** @var array  $payments */
/** @var array  $org */
/** @var string $module_base */
/** @var string $title */

$h = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');

$module_base = rtrim($module_base ?? ($base ?? '/apps/bizflow'), '/');
$orgName     = $org['name'] ?? '';

$id          = (int)($order['id'] ?? 0);
$no          = $order['order_no']        ?? '';
$extRef      = $order['external_ref']    ?? '';
$date        = $order['date']            ?? ($order['created_at'] ?? '');
$status      = $order['status']          ?? '';
$currency    = $order['currency']        ?? 'BDT';
$grandTotal  = $order['grand_total']     ?? null;
$subTotal    = $order['sub_total']       ?? null;
$discount    = $order['discount_total']  ?? null;
$taxTotal    = $order['tax_total']       ?? null;
$shipTotal   = $order['shipping_total']  ?? null;
$notes       = $order['internal_note']   ?? ($order['notes'] ?? '');
$publicNote  = $order['customer_note']   ?? '';

$customerName     = $order['customer_name']    ?? '';
$customerCode     = $order['customer_code']    ?? '';
$customerCompany  = $order['customer_company'] ?? '';
$customerCity     = $order['customer_city']    ?? '';
$customerDistrict = $order['customer_district'] ?? '';
$customerCountry  = $order['customer_country'] ?? '';
$cLocParts        = array_filter([$customerCity, $customerDistrict, $customerCountry]);
$cLoc             = implode(', ', $cLocParts);

$statusLabel = $status !== '' ? ucfirst(str_replace('_',' ', $status)) : 'Unknown';
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
<div class="space-y-6">

    <!-- Top: breadcrumb + title + actions -->
    <header class="space-y-3">
        <div class="text-xs text-slate-500 flex items-center gap-1">
            <a href="<?= $h($module_base.'/orders') ?>" class="hover:underline">Orders</a>
            <span>/</span>
            <span class="font-medium text-slate-700"><?= $h($no !== '' ? $no : 'Order') ?></span>
        </div>

        <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
            <div>
                <div class="flex items-center gap-3">
                    <div class="flex h-11 w-11 items-center justify-center rounded-full bg-emerald-600 text-sm font-semibold text-white">
                        SO
                    </div>
                    <div>
                        <h1 class="text-2xl md:text-3xl font-semibold tracking-tight">
                            <?= $h($no !== '' ? $no : ('SO-'.$id)) ?>
                        </h1>
                        <p class="text-xs text-slate-500">
                            <?= $h($orgName ?: 'BizFlow tenant') ?>
                        </p>
                    </div>
                </div>

                <div class="mt-3 flex flex-wrap gap-2 text-xs">
                    <?php if ($extRef !== ''): ?>
                        <span class="inline-flex items-center gap-1 rounded-full border border-slate-200 bg-slate-50 px-2 py-[3px] font-mono text-[11px] text-slate-700">
                            <i class="fa fa-hashtag text-[9px]"></i> <?= $h($extRef) ?>
                        </span>
                    <?php endif; ?>
                    <?php if ($date !== ''): ?>
                        <span class="inline-flex items-center gap-1 rounded-full bg-slate-50 px-2 py-[3px] text-[11px] text-slate-600">
                            <i class="fa fa-calendar text-[9px]"></i> <?= $h($date) ?>
                        </span>
                    <?php endif; ?>
                    <span class="inline-flex items-center gap-1 rounded-full px-2 py-[3px] text-[11px] font-medium <?= $statusClass ?>">
                        <span class="h-1.5 w-1.5 rounded-full bg-current"></span>
                        <?= $h($statusLabel) ?>
                    </span>
                </div>
            </div>

            <!-- Right: quick actions -->
            <nav class="flex flex-wrap justify-end gap-2 text-xs md:text-[13px]">
                <a href="<?= $h($module_base.'/orders') ?>"
                   class="inline-flex items-center gap-1 rounded-full px-3 py-1 border border-slate-200 text-slate-600 hover:bg-slate-50">
                    <i class="fa fa-list text-[11px]"></i>
                    <span>All orders</span>
                </a>
                <a href="<?= $h($module_base.'/orders/'.$id.'/edit') ?>"
                   class="inline-flex items-center gap-1 rounded-full px-3 py-1 border border-slate-200 text-slate-700 hover:bg-slate-50">
                    <i class="fa fa-pen text-[11px]"></i>
                    <span>Edit</span>
                </a>
                <!-- Placeholder for future: convert to invoice, print, etc. -->
            </nav>
        </div>
    </header>

    <!-- Main layout: 2 columns on desktop -->
    <div class="grid gap-6 lg:grid-cols-[2fr,1fr]">

        <!-- LEFT: items + history -->
        <section class="space-y-4">

            <!-- Financial summary cards -->
            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                    <div class="text-xs font-medium text-slate-500">Order total</div>
                    <div class="mt-1 text-xl font-semibold text-slate-900">
                        <?php if ($grandTotal !== null && $grandTotal !== ''): ?>
                            <?= $h(number_format((float)$grandTotal, 2)) ?> BDT
                        <?php else: ?>
                            <span class="text-slate-400">—</span>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                    <div class="text-xs font-medium text-slate-500">Subtotal</div>
                    <div class="mt-1 text-xl font-semibold text-slate-900">
                        <?php if ($subTotal !== null && $subTotal !== ''): ?>
                            <?= $h(number_format((float)$subTotal, 2)) ?> BDT
                        <?php else: ?>
                            <span class="text-slate-400">—</span>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                    <div class="text-xs font-medium text-slate-500">Tax / Shipping</div>
                    <div class="mt-1 text-xs text-slate-800 space-y-0.5">
                        <div>Tax:
                            <?php if ($taxTotal !== null && $taxTotal !== ''): ?>
                                <span class="font-semibold"><?= $h(number_format((float)$taxTotal, 2)) ?> BDT</span>
                            <?php else: ?>
                                <span class="text-slate-400">—</span>
                            <?php endif; ?>
                        </div>
                        <div>Shipping:
                            <?php if ($shipTotal !== null && $shipTotal !== ''): ?>
                                <span class="font-semibold"><?= $h(number_format((float)$shipTotal, 2)) ?> BDT</span>
                            <?php else: ?>
                                <span class="text-slate-400">—</span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Line items -->
            <div class="rounded-xl border border-slate-200 bg-white shadow-sm">
                <div class="flex items-center justify-between border-b border-slate-100 px-4 py-3">
                    <h2 class="text-sm font-semibold text-slate-800 flex items-center gap-2">
                        <span class="inline-flex h-6 w-6 items-center justify-center rounded-full bg-slate-900 text-[11px] text-white">
                            IT
                        </span>
                        Line items
                    </h2>
                    <span class="text-xs text-slate-500">
                        <?= $h(count($items ?? [])) ?> rows
                    </span>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-100 text-sm">
                        <thead class="bg-slate-50 text-xs font-semibold text-slate-500 uppercase tracking-wide">
                        <tr>
                            <th class="px-4 py-2 text-left">Item</th>
                            <th class="px-4 py-2 text-left">Code</th>
                            <th class="px-4 py-2 text-right">Qty</th>
                            <th class="px-4 py-2 text-left">Unit</th>
                            <th class="px-4 py-2 text-right">Unit price</th>
                            <th class="px-4 py-2 text-right">Line total</th>
                        </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 bg-white">
                        <?php if (!empty($items)): ?>
                            <?php foreach ($items as $it): ?>
                                <?php
                                $name   = $it['item_name'] ?? ($it['description'] ?? '');
                                $code   = $it['item_code'] ?? ($it['sku'] ?? '');
                                $qty    = $it['qty']       ?? $it['quantity'] ?? null;
                                $unit   = $it['item_unit'] ?? ($it['unit'] ?? '');
                                $price  = $it['unit_price'] ?? $it['price'] ?? null;
                                $total  = $it['line_total'] ?? $it['total'] ?? null;
                                ?>
                                <tr class="hover:bg-emerald-50/40">
                                    <td class="px-4 py-2 text-xs text-slate-800">
                                        <?= $name !== '' ? $h($name) : '<span class="text-slate-400">—</span>' ?>
                                    </td>
                                    <td class="px-4 py-2 text-xs font-mono text-slate-600 whitespace-nowrap">
                                        <?= $code !== '' ? $h($code) : '<span class="text-slate-400">—</span>' ?>
                                    </td>
                                    <td class="px-4 py-2 text-xs text-right text-slate-800 whitespace-nowrap">
                                        <?= $qty !== null && $qty !== '' ? $h(number_format((float)$qty, 2)) : '<span class="text-slate-400">—</span>' ?>
                                    </td>
                                    <td class="px-4 py-2 text-xs text-left text-slate-600 whitespace-nowrap">
                                        <?= $unit !== '' ? $h($unit) : '<span class="text-slate-400">—</span>' ?>
                                    </td>
                                    <td class="px-4 py-2 text-xs text-right text-slate-800 whitespace-nowrap">
                                        <?php if ($price !== null && $price !== ''): ?>
                                            <?= $h(number_format((float)$price, 2)) ?> BDT
                                        <?php else: ?>
                                            <span class="text-slate-400">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-4 py-2 text-xs text-right text-slate-800 whitespace-nowrap">
                                        <?php if ($total !== null && $total !== ''): ?>
                                            <?= $h(number_format((float)$total, 2)) ?> BDT
                                        <?php else: ?>
                                            <span class="text-slate-400">—</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" class="px-4 py-4 text-center text-xs text-slate-500">
                                    No line items recorded for this order.
                                </td>
                            </tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Related invoices -->
            <div class="rounded-xl border border-slate-200 bg-white shadow-sm">
                <div class="flex items-center justify-between border-b border-slate-100 px-4 py-3">
                    <h2 class="text-sm font-semibold text-slate-800 flex items-center gap-2">
                        <span class="inline-flex h-6 w-6 items-center justify-center rounded-full bg-amber-500 text-[11px] text-white">
                            INV
                        </span>
                        Related invoices
                    </h2>
                    <span class="text-xs text-slate-500">
                        <?= $h(count($invoices ?? [])) ?> records
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
                                $ino  = $inv['invoice_no']  ?? $inv['id'] ?? '';
                                $id2  = (int)($inv['id'] ?? 0);
                                $d    = $inv['date']       ?? ($inv['created_at'] ?? '');
                                $st   = $inv['status']     ?? '';
                                $amt  = $inv['grand_total'] ?? null;
                                ?>
                                <tr class="hover:bg-emerald-50/40">
                                    <td class="px-4 py-2 text-xs font-mono text-slate-700">
                                        <?= $h($ino !== '' ? $ino : ('INV-'.$id2)) ?>
                                    </td>
                                    <td class="px-4 py-2 text-xs text-slate-600"><?= $h($d ?: '—') ?></td>
                                    <td class="px-4 py-2 text-xs text-slate-600">
                                        <?= $st !== '' ? $h(ucfirst($st)) : '<span class="text-slate-400">—</span>' ?>
                                    </td>
                                    <td class="px-4 py-2 text-xs text-right text-slate-800">
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
                                    No invoices linked to this order yet.
                                </td>
                            </tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Recent payments (customer-level) -->
            <div class="rounded-xl border border-slate-200 bg-white shadow-sm">
                <div class="flex items-center justify-between border-b border-slate-100 px-4 py-3">
                    <h2 class="text-sm font-semibold text-slate-800 flex items-center gap-2">
                        <span class="inline-flex h-6 w-6 items-center justify-center rounded-full bg-sky-500 text-[11px] text-white">
                            RC
                        </span>
                        Recent payments (customer)
                    </h2>
                    <span class="text-xs text-slate-500">
                        <?= $h(count($payments ?? [])) ?> records
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
                                $d   = $p['date']      ?? ($p['created_at'] ?? '');
                                $mtd = $p['method']    ?? ($p['channel'] ?? '');
                                $ref = $p['reference'] ?? ($p['ref_no'] ?? '');
                                $amt = $p['amount']    ?? null;
                                ?>
                                <tr class="hover:bg-emerald-50/40">
                                    <td class="px-4 py-2 text-xs text-slate-600"><?= $h($d ?: '—') ?></td>
                                    <td class="px-4 py-2 text-xs text-slate-600">
                                        <?= $mtd !== '' ? $h($mtd) : '<span class="text-slate-400">—</span>' ?>
                                    </td>
                                    <td class="px-4 py-2 text-xs text-slate-600">
                                        <?= $ref !== '' ? $h($ref) : '<span class="text-slate-400">—</span>' ?>
                                    </td>
                                    <td class="px-4 py-2 text-xs text-right text-slate-800">
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
                                    No payments recorded yet for this customer.
                                </td>
                            </tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </section>

        <!-- RIGHT: customer + notes -->
        <aside class="space-y-4">
            <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                <h2 class="text-sm font-semibold text-slate-800 mb-3">Customer</h2>
                <dl class="space-y-2 text-xs text-slate-600">
                    <div class="flex justify-between gap-4">
                        <dt class="text-slate-500">Name</dt>
                        <dd class="text-right text-slate-800">
                            <?= $customerName !== '' ? $h($customerName) : '—' ?>
                        </dd>
                    </div>
                    <?php if ($customerCompany !== ''): ?>
                        <div class="flex justify-between gap-4">
                            <dt class="text-slate-500">Company</dt>
                            <dd class="text-right text-slate-800"><?= $h($customerCompany) ?></dd>
                        </div>
                    <?php endif; ?>
                    <?php if ($customerCode !== ''): ?>
                        <div class="flex justify-between gap-4">
                            <dt class="text-slate-500">Customer code</dt>
                            <dd class="text-right font-mono text-[11px] text-slate-800"><?= $h($customerCode) ?></dd>
                        </div>
                    <?php endif; ?>
                    <?php if ($cLoc !== ''): ?>
                        <div class="flex justify-between gap-4">
                            <dt class="text-slate-500">Location</dt>
                            <dd class="text-right text-slate-800"><?= $h($cLoc) ?></dd>
                        </div>
                    <?php endif; ?>
                </dl>
                <div class="mt-3 text-right">
                    <a href="<?= $h($module_base.'/customers') ?>"
                       class="inline-flex items-center gap-1 rounded-full border border-slate-200 px-3 py-1 text-[11px] text-slate-700 hover:bg-slate-50">
                        <i class="fa fa-users text-[10px]"></i>
                        <span>Open customers</span>
                    </a>
                </div>
            </div>

            <?php if ($publicNote !== '' || $notes !== ''): ?>
                <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm text-xs text-slate-700 space-y-3">
                    <?php if ($publicNote !== ''): ?>
                        <div>
                            <h2 class="text-sm font-semibold text-slate-800 mb-1">Customer note</h2>
                            <p class="whitespace-pre-line"><?= nl2br($h($publicNote)) ?></p>
                        </div>
                    <?php endif; ?>
                    <?php if ($notes !== ''): ?>
                        <div>
                            <h2 class="text-sm font-semibold text-slate-800 mb-1">Internal note</h2>
                            <p class="whitespace-pre-line"><?= nl2br($h($notes)) ?></p>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <!-- How to read this screen -->
            <div class="rounded-xl border border-dashed border-emerald-400 bg-emerald-50/60 px-4 py-4 text-xs text-slate-800">
                <h2 class="mb-2 flex items-center gap-2 text-sm font-semibold text-emerald-900">
                    <span class="inline-flex h-6 w-6 items-center justify-center rounded-full bg-emerald-600 text-[11px] text-white">
                        ?
                    </span>
                    How to read this order
                </h2>
                <ul class="ml-6 list-disc space-y-1 text-[12px]">
                    <li>The top badges show order number, external reference, date and lifecycle <strong>status</strong>.</li>
                    <li><strong>Line items</strong> summarise quantities, unit prices and totals in BDT.</li>
                    <li><strong>Related invoices</strong> and <strong>payments</strong> give you a 360° view of revenue and collections.</li>
                    <li>The <strong>Customer</strong> card helps you quickly jump back to customer-level analytics.</li>
                    <li>All amounts are tenant-scoped via <code>org_id</code> and safe for multi-tenant BizFlow reporting.</li>
                </ul>
            </div>
        </aside>
    </div>
</div>