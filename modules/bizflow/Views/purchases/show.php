<?php
/** @var array  $purchase */
/** @var array  $items */
/** @var array  $grns */
/** @var array  $inventory_events */
/** @var array  $org */
/** @var string $module_base */
/** @var string $title */

$h = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');

$module_base = rtrim($module_base ?? ($base ?? '/apps/bizflow'), '/');

$poId   = (int)($purchase['id'] ?? 0);
$poNo   = (string)($purchase['po_no'] ?? ('#' . $poId));
$status = (string)($purchase['status'] ?? 'draft');

$currency = (string)($purchase['currency'] ?? ($purchase['currency_code'] ?? 'BDT'));
$grand   = (float)($purchase['grand_total']    ?? 0);
$sub     = (float)($purchase['subtotal']       ?? 0);
$disc    = (float)($purchase['discount_total'] ?? 0);
$tax     = (float)($purchase['tax_total']      ?? 0);
$ship    = (float)($purchase['shipping_total'] ?? 0);

$poDate  = $purchase['date']           ?? null;
$etaDate = $purchase['expected_date']  ?? null;
$created = $purchase['created_at']     ?? null;
$updated = $purchase['updated_at']     ?? null;

$supplierName = (string)($purchase['supplier_name'] ?? '');
$supplierType = (string)($purchase['supplier_type'] ?? '');
$externalRef  = (string)($purchase['external_ref']  ?? '');
$purchaseType = (string)($purchase['purchase_type'] ?? '');
$isInventoryImpact = (int)($purchase['is_inventory_impact'] ?? 1) === 1;
$isExpenseOnly     = (int)($purchase['is_expense_only']     ?? 0) === 1;

$qtyOrdered  = (float)($purchase['qty_ordered_total']  ?? 0);
$qtyReceived = (float)($purchase['qty_received_total'] ?? 0);
$receiptPct  = $qtyOrdered > 0 ? max(0.0, min(100.0, ($qtyReceived / $qtyOrdered) * 100.0)) : 0.0;

$canReceive = $qtyOrdered > 0
    && $qtyReceived < $qtyOrdered
    && in_array($status, ['draft', 'approved', 'in_transit', 'receiving', 'partially_received'], true);

$orgName = (string)($org['name'] ?? '');

$backUrl    = $module_base . '/purchases';
$editUrl    = $module_base . '/purchases/' . $poId . '/edit';
$receiveUrl = $module_base . '/purchases/' . $poId . '/receive';

function bf_format_amount(float $v): string {
    return number_format($v, 2, '.', ',');
}
?>
<div class="space-y-6">

    <!-- Page header -->
    <header class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
        <div class="space-y-1">
            <p class="text-xs text-slate-500">
                <a href="<?= $h($backUrl) ?>" class="hover:underline">Purchases</a>
                <span class="mx-1">/</span>
                <span class="text-slate-700">PO <?= $h($poNo) ?></span>
            </p>
            <h1 class="text-2xl md:text-3xl font-semibold tracking-tight flex items-center gap-2">
                <span>Purchase <?= $h($poNo) ?></span>
                <?php if ($status !== ''): ?>
                    <span class="inline-flex items-center rounded-full border px-2 py-0.5 text-xs font-medium
                                 border-slate-200 text-slate-700 bg-slate-50">
                        <?= $h(ucfirst($status)) ?>
                    </span>
                <?php endif; ?>
                <?php if ($isInventoryImpact): ?>
                    <span class="inline-flex items-center gap-1 rounded-full bg-emerald-50 px-2 py-0.5 text-[11px] font-medium text-emerald-700 border border-emerald-100">
                        <span class="h-1.5 w-1.5 rounded-full bg-emerald-600"></span>
                        Inventory-impact
                    </span>
                <?php else: ?>
                    <span class="inline-flex items-center gap-1 rounded-full bg-slate-50 px-2 py-0.5 text-[11px] font-medium text-slate-600 border border-slate-200">
                        Non-inventory
                    </span>
                <?php endif; ?>
            </h1>
            <?php if ($orgName !== ''): ?>
                <p class="text-xs text-slate-500">
                    Local • Tenant — <?= $h($orgName) ?>
                </p>
            <?php endif; ?>
        </div>

        <div class="flex flex-wrap items-center justify-end gap-2">
            <a href="<?= $h($backUrl) ?>"
               class="inline-flex items-center gap-1 rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50">
                <i class="fa fa-list-ul text-[11px]"></i>
                <span>All purchases</span>
            </a>

            <a href="<?= $h($editUrl) ?>"
               class="inline-flex items-center gap-1 rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50">
                <i class="fa fa-pen text-[11px]"></i>
                <span>Edit</span>
            </a>

            <?php if ($canReceive): ?>
                <a href="<?= $h($receiveUrl) ?>"
                   class="inline-flex items-center gap-1 rounded-lg bg-emerald-600 px-3.5 py-1.5 text-xs font-semibold text-white shadow-sm hover:bg-emerald-700 focus:outline-none focus:ring-2 focus:ring-emerald-600 focus:ring-offset-1">
                    <i class="fa fa-truck-ramp-box text-[11px]"></i>
                    <span>Receive / GRN</span>
                </a>
            <?php endif; ?>
        </div>
    </header>

    <!-- ROW 1: Metrics (Grand, Receipt, Key dates) -->
    <section class="grid gap-4 md:grid-cols-3">
        <!-- Grand total -->
        <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
            <p class="text-xs font-medium text-slate-500 mb-1">Grand total</p>
            <p class="text-xl font-semibold text-slate-900">
                <?= $h(bf_format_amount($grand)) ?> <?= $h($currency) ?>
            </p>
            <p class="mt-1 text-[11px] text-slate-500">
                Subtotal <?= $h(bf_format_amount($sub)) ?>,
                Discount <?= $h(bf_format_amount($disc)) ?>,
                Tax <?= $h(bf_format_amount($tax)) ?>,
                Shipping <?= $h(bf_format_amount($ship)) ?>.
            </p>
        </div>

        <!-- Receipt progress -->
        <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
            <p class="text-xs font-medium text-slate-500 mb-1">Receipt progress</p>
            <p class="text-sm font-semibold text-slate-900">
                <?= $h(number_format($qtyReceived, 2)) ?> / <?= $h(number_format($qtyOrdered, 2)) ?>
            </p>
            <p class="mt-1 text-[11px] text-slate-500">
                <?= $h(number_format($receiptPct, 1)) ?>% received into GRN / inventory
            </p>
            <div class="mt-2 h-1.5 w-full rounded-full bg-slate-100 overflow-hidden">
                <div class="h-1.5 bg-emerald-500" style="width: <?= $receiptPct ?>%;"></div>
            </div>
        </div>

        <!-- Key dates -->
        <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
            <p class="text-xs font-medium text-slate-500 mb-2">Key dates</p>
            <dl class="space-y-1 text-sm">
                <div class="flex items-center justify-between">
                    <dt class="text-slate-500 text-xs">PO date</dt>
                    <dd class="text-slate-900 text-sm">
                        <?= $poDate ? $h($poDate) : '—' ?>
                    </dd>
                </div>
                <div class="flex items-center justify-between">
                    <dt class="text-slate-500 text-xs">ETA</dt>
                    <dd class="text-slate-900 text-sm">
                        <?= $etaDate ? $h($etaDate) : '—' ?>
                    </dd>
                </div>
            </dl>
        </div>
    </section>

    <!-- ROW 2: Supplier + Document info + Internal notes -->
    <section class="grid gap-4 md:grid-cols-12">
        <!-- Supplier block -->
        <div class="md:col-span-7 rounded-xl border border-slate-200 bg-white p-4 shadow-sm space-y-2">
            <div class="flex items-center gap-2 text-xs font-semibold text-slate-600 uppercase tracking-wide">
                <span class="inline-flex h-6 w-6 items-center justify-center rounded-full bg-emerald-600 text-[11px] text-white">
                    <?= $h('PO') ?>
                </span>
                <span>Supplier</span>
            </div>

            <div class="mt-1">
                <p class="text-sm font-semibold text-slate-900 flex items-center gap-2">
                    <?= $supplierName !== '' ? $h($supplierName) : 'Supplier not set' ?>
                    <?php if ($supplierType !== ''): ?>
                        <span class="inline-flex items-center rounded-full bg-slate-50 px-2 py-0.5 text-[11px] font-medium text-slate-600 border border-slate-200">
                            <?= $h(strtoupper($supplierType)) ?>
                        </span>
                    <?php endif; ?>
                </p>
                <?php if ($externalRef !== ''): ?>
                    <p class="mt-0.5 text-xs text-slate-500">
                        Supplier / external reference: <?= $h($externalRef) ?>
                    </p>
                <?php endif; ?>
                <?php if ($purchaseType !== ''): ?>
                    <p class="mt-0.5 text-xs text-slate-500">
                        Purchase type: <?= $h(ucfirst(str_replace('_', ' ', $purchaseType))) ?>
                    </p>
                <?php endif; ?>
                <?php if ($isExpenseOnly): ?>
                    <p class="mt-0.5 text-[11px] text-amber-700">
                        Expense-only: this PO is treated as service / OPEX.
                    </p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Right column: document info + internal notes stacked -->
        <div class="md:col-span-5 space-y-4">
            <!-- Document info -->
            <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-slate-600">
                    Document info
                </p>
                <dl class="space-y-1.5 text-xs">
                    <div class="flex items-center justify-between">
                        <dt class="text-slate-500">PO ID</dt>
                        <dd class="text-slate-900">#<?= $h((string)$poId) ?></dd>
                    </div>
                    <div class="flex items-center justify-between">
                        <dt class="text-slate-500">Created</dt>
                        <dd class="text-slate-900"><?= $created ? $h($created) : '—' ?></dd>
                    </div>
                    <div class="flex items-center justify-between">
                        <dt class="text-slate-500">Updated</dt>
                        <dd class="text-slate-900"><?= $updated ? $h($updated) : '—' ?></dd>
                    </div>
                </dl>
            </div>

            <!-- Internal notes -->
            <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm min-h-[90px]">
                <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-slate-600">
                    Internal notes
                </p>
                <?php $notes = trim((string)($purchase['notes'] ?? '')); ?>
                <?php if ($notes !== ''): ?>
                    <p class="text-sm text-slate-800 whitespace-pre-wrap">
                        <?= $h($notes) ?>
                    </p>
                <?php else: ?>
                    <p class="text-xs text-slate-500">
                        No internal notes recorded yet for this purchase.
                    </p>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <!-- ROW 3: Line items (full width) -->
    <section class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm space-y-3">
        <div class="flex items-center justify-between gap-2">
            <div class="flex items-center gap-2">
                <span class="inline-flex h-6 w-6 items-center justify-center rounded-full bg-emerald-600 text-[11px] text-white">
                    PO
                </span>
                <h2 class="text-sm font-semibold text-slate-900">
                    Line items
                </h2>
            </div>
            <p class="text-[11px] text-slate-500">
                Quantities are tracked in GRNs. Totals shown in <?= $h($currency) ?>.
            </p>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-xs">
                <thead class="bg-slate-50 text-[11px] font-semibold uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="px-3 py-2 text-left">Item</th>
                        <th class="px-3 py-2 text-left">Description</th>
                        <th class="px-3 py-2 text-center">UOM</th>
                        <th class="px-3 py-2 text-right">Qty ordered</th>
                        <th class="px-3 py-2 text-right">Qty received</th>
                        <th class="px-3 py-2 text-right">Unit price</th>
                        <th class="px-3 py-2 text-right">Line total</th>
                        <th class="px-3 py-2 text-center">Inv?</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 bg-white">
                <?php if (!empty($items)): ?>
                    <?php foreach ($items as $line): ?>
                        <?php
                        $itemName = $line['item_name'] ?? $line['product_name'] ?? '';
                        $itemCode = $line['item_code'] ?? $line['product_code'] ?? '';
                        $desc     = $line['description'] ?? '';
                        $uom      = $line['unit'] ?? $line['uom'] ?? '';
                        $qtyO     = (float)($line['qty_ordered'] ?? $line['qty'] ?? 0);
                        $qtyR     = (float)($line['qty_received'] ?? 0);
                        $price    = (float)($line['unit_price'] ?? 0);
                        $totalLn  = (float)($line['line_total'] ?? ($qtyO * $price));
                        $invFlag  = isset($line['is_inventory_item'])
                            ? (int)$line['is_inventory_item'] === 1
                            : $isInventoryImpact;
                        ?>
                        <tr class="hover:bg-emerald-50/40">
                            <td class="px-3 py-2 align-top text-xs">
                                <?php if ($itemName !== ''): ?>
                                    <div class="font-medium text-slate-900"><?= $h($itemName) ?></div>
                                <?php endif; ?>
                                <?php if ($itemCode !== ''): ?>
                                    <div class="text-[11px] text-slate-500"><?= $h($itemCode) ?></div>
                                <?php endif; ?>
                            </td>
                            <td class="px-3 py-2 align-top text-xs text-slate-700">
                                <?= $desc !== '' ? nl2br($h($desc)) : '&mdash;' ?>
                            </td>
                            <td class="px-3 py-2 align-top text-center text-xs text-slate-700">
                                <?= $uom !== '' ? $h($uom) : '&mdash;' ?>
                            </td>
                            <td class="px-3 py-2 align-top text-right text-xs text-slate-700">
                                <?= $h(number_format($qtyO, 3)) ?>
                            </td>
                            <td class="px-3 py-2 align-top text-right text-xs text-slate-700">
                                <?= $h(number_format($qtyR, 3)) ?>
                            </td>
                            <td class="px-3 py-2 align-top text-right text-xs text-slate-700">
                                <?= $h(bf_format_amount($price)) ?>
                            </td>
                            <td class="px-3 py-2 align-top text-right text-xs text-slate-900">
                                <?= $h(bf_format_amount($totalLn)) ?>
                            </td>
                            <td class="px-3 py-2 align-top text-center text-xs">
                                <?php if ($invFlag): ?>
                                    <span class="inline-flex items-center rounded-full bg-emerald-50 px-2 py-0.5 text-[11px] font-medium text-emerald-700 border border-emerald-100">
                                        Inv
                                    </span>
                                <?php else: ?>
                                    <span class="inline-flex items-center rounded-full bg-slate-50 px-2 py-0.5 text-[11px] font-medium text-slate-500 border border-slate-200">
                                        Non-inv
                                    </span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="8" class="px-3 py-4 text-center text-xs text-slate-500">
                            No line items recorded for this purchase.
                        </td>
                    </tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>

    <!-- ROW 4: GRNs + Inventory history (full width cards) -->
    <section class="grid gap-4 md:grid-cols-2">
        <!-- GRNs -->
        <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm space-y-3">
            <div class="flex items-center justify-between">
                <h2 class="text-sm font-semibold text-slate-900 flex items-center gap-2">
                    <span class="inline-flex h-6 w-6 items-center justify-center rounded-full bg-emerald-600 text-[11px] text-white">
                        GRN
                    </span>
                    <span>GRNs for this purchase</span>
                </h2>
            </div>

            <?php if (!empty($grns)): ?>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200 text-xs">
                        <thead class="bg-slate-50 text-[11px] font-semibold uppercase tracking-wide text-slate-500">
                        <tr>
                            <th class="px-3 py-2 text-left">GRN no</th>
                            <th class="px-3 py-2 text-left">Warehouse</th>
                            <th class="px-3 py-2 text-right">Qty</th>
                            <th class="px-3 py-2 text-left">Date</th>
                            <th class="px-3 py-2 text-left">Posted by</th>
                        </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 bg-white">
                        <?php foreach ($grns as $g): ?>
                            <tr class="hover:bg-slate-50">
                                <td class="px-3 py-2 text-xs text-slate-900">
                                    <?= $h($g['grn_no'] ?? ('#' . ($g['id'] ?? ''))) ?>
                                </td>
                                <td class="px-3 py-2 text-xs text-slate-700">
                                    <?= $h($g['warehouse_code'] ?? '') ?>
                                </td>
                                <td class="px-3 py-2 text-xs text-right text-slate-700">
                                    <?= $h(number_format((float)($g['qty_total'] ?? 0), 3)) ?>
                                </td>
                                <td class="px-3 py-2 text-xs text-slate-700">
                                    <?= $h($g['date'] ?? '') ?>
                                </td>
                                <td class="px-3 py-2 text-xs text-slate-700">
                                    <?= $h($g['posted_by_name'] ?? '') ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <p class="text-xs text-slate-500">
                    No GRNs have been created yet for this purchase. Use the
                    <strong>Receive / GRN</strong> button above to record receipts.
                </p>
            <?php endif; ?>
        </div>

        <!-- Inventory events -->
        <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm space-y-3">
            <div class="flex items-center justify-between">
                <h2 class="text-sm font-semibold text-slate-900 flex items-center gap-2">
                    <span class="inline-flex h-6 w-6 items-center justify-center rounded-full bg-emerald-600 text-[11px] text-white">
                        INV
                    </span>
                    <span>Inventory movements</span>
                </h2>
            </div>

            <?php if (!empty($inventory_events)): ?>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200 text-xs">
                        <thead class="bg-slate-50 text-[11px] font-semibold uppercase tracking-wide text-slate-500">
                        <tr>
                            <th class="px-3 py-2 text-left">Date</th>
                            <th class="px-3 py-2 text-left">Warehouse</th>
                            <th class="px-3 py-2 text-left">Item</th>
                            <th class="px-3 py-2 text-right">Qty</th>
                        </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 bg-white">
                        <?php foreach ($inventory_events as $m): ?>
                            <tr class="hover:bg-slate-50">
                                <td class="px-3 py-2 text-xs text-slate-700">
                                    <?= $h($m['movement_date'] ?? '') ?>
                                </td>
                                <td class="px-3 py-2 text-xs text-slate-700">
                                    <?= $h($m['warehouse_code'] ?? '') ?>
                                </td>
                                <td class="px-3 py-2 text-xs text-slate-700">
                                    <?= $h($m['item_name'] ?? '') ?>
                                    <?php if (!empty($m['item_code'])): ?>
                                        <span class="ml-1 text-[11px] text-slate-500">
                                            (<?= $h($m['item_code']) ?>)
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-3 py-2 text-xs text-right text-slate-700">
                                    <?= $h(number_format((float)($m['qty'] ?? 0), 3)) ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <p class="text-xs text-slate-500">
                    No inventory movements recorded yet for this purchase. Once GRNs are posted,
                    stock moves will appear here.
                </p>
            <?php endif; ?>
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
            <li>Review the <strong>Grand total</strong>, <strong>Receipt progress</strong> and <strong>Key dates</strong> in the top row for a quick status overview.</li>
            <li>Check <strong>Supplier</strong> and <strong>Document info</strong> to confirm you are working on the correct PO and organisation.</li>
            <li>Use the <strong>Line items</strong> section to verify quantities, unit prices and whether each line affects inventory.</li>
            <li>Click <strong>Receive / GRN</strong> when goods arrive; GRNs created from this PO will appear in the GRN panel.</li>
            <li>After GRNs are posted, monitor <strong>Inventory movements</strong> to see how stock levels changed from this purchase.</li>
            <li>Use <strong>Internal notes</strong> for approval comments, budget references or any important context about this purchase.</li>
        </ul>
    </section>
</div>