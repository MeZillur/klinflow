<?php
/** @var array  $org */
/** @var array  $grn */
/** @var array  $items */
/** @var bool   $storage_ready */
/** @var string $module_base */
/** @var string $title */

$h = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');

$module_base = rtrim($module_base ?? ($base ?? '/apps/bizflow'), '/');

$orgName = (string)($org['name'] ?? '');

$id        = (int)($grn['id']        ?? 0);
$grnNo     = (string)($grn['grn_no'] ?? ('GRN-' . $id));
$grnDate   = (string)($grn['grn_date'] ?? ($grn['date'] ?? ''));
$refType   = (string)($grn['ref_type'] ?? 'purchase'); // purchase | lc | manual
$refNo     = (string)($grn['ref_no']   ?? '');
$suppName  = (string)($grn['supplier_name']  ?? '');
$whName    = (string)($grn['warehouse_name'] ?? ($grn['warehouse_code'] ?? ''));
$currency  = (string)($grn['currency'] ?? 'BDT');
$status    = (string)($grn['status']   ?? 'draft');
$notes     = trim((string)($grn['notes'] ?? ''));

$totalQty    = (float)($grn['total_qty']    ?? 0);
$totalAmount = (float)($grn['total_amount'] ?? 0);

$createdAt = $grn['created_at']      ?? null;
$updatedAt = $grn['updated_at']      ?? null;
$postedAt  = $grn['posted_at']       ?? null;
$postedBy  = $grn['posted_by_name']  ?? null;

$purchaseId = $grn['purchase_id'] ?? null;
$purchaseNo = $grn['purchase_no'] ?? null;

$backUrl     = $module_base . '/grn';
$postUrl     = $module_base . '/grn/' . $id . '/post';
$purchaseUrl = $purchaseId ? ($module_base . '/purchases/' . (int)$purchaseId) : null;

/**
 * Derive totals from items if header total is missing.
 * We keep the logic, just read from whatever qty fields exist.
 */
if ($totalQty === 0.0 || $totalAmount === 0.0) {
    $q = 0.0;
    $t = 0.0;
    foreach ($items as $it) {
        $lineQty = (float)($it['qty_received']
            ?? $it['qty_this_grn']
            ?? $it['qty']
            ?? 0);

        $lineTotal = (float)($it['line_total'] ?? $it['total_cost'] ?? 0);

        $q += $lineQty;
        $t += $lineTotal;
    }
    if ($totalQty === 0.0)    $totalQty    = $q;
    if ($totalAmount === 0.0) $totalAmount = $t;
}

function grn_fmt_amount(float $v): string {
    return number_format($v, 2, '.', ',');
}
?>
<div class="space-y-6">

    <!-- Header -->
    <header class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
        <div class="space-y-1">
            <p class="text-xs text-slate-500">
                <a href="<?= $h($backUrl) ?>" class="hover:underline">GRN register</a>
                <span class="mx-1">/</span>
                <span class="text-slate-700"><?= $h($grnNo) ?></span>
            </p>
            <h1 class="text-2xl md:text-3xl font-semibold tracking-tight flex items-center gap-2">
                <span>GRN <?= $h($grnNo) ?></span>
                <span class="inline-flex items-center rounded-full border px-2 py-0.5 text-xs font-medium
                             border-slate-200 text-slate-700 bg-slate-50">
                    <?= $h(ucfirst($status)) ?>
                </span>
                <?php if ($postedAt): ?>
                    <span class="inline-flex items-center gap-1 rounded-full bg-emerald-50 px-2 py-0.5 text-[11px] font-medium text-emerald-700 border border-emerald-100">
                        <span class="h-1.5 w-1.5 rounded-full bg-emerald-600"></span>
                        Posted to inventory
                    </span>
                <?php else: ?>
                    <span class="inline-flex items-center gap-1 rounded-full bg-amber-50 px-2 py-0.5 text-[11px] font-medium text-amber-700 border border-amber-100">
                        <span class="h-1.5 w-1.5 rounded-full bg-amber-500"></span>
                        Not yet posted
                    </span>
                <?php endif; ?>
            </h1>
            <?php if ($orgName !== ''): ?>
                <p class="text-xs text-slate-500">
                    Tenant — <?= $h($orgName) ?>
                </p>
            <?php endif; ?>
            <?php if (!$storage_ready): ?>
                <p class="mt-1 text-[11px] text-amber-700">
                    GRN storage tables (biz_grn / biz_grn_items) not found.
                </p>
            <?php endif; ?>
        </div>

        <div class="flex flex-wrap items-center justify-end gap-2">
            <a href="<?= $h($backUrl) ?>"
               class="inline-flex items-center gap-1 rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50">
                <i class="fa fa-list-ul text-[11px]"></i>
                <span>All GRNs</span>
            </a>

            <?php if ($purchaseUrl): ?>
                <a href="<?= $h($purchaseUrl) ?>"
                   class="inline-flex items-center gap-1 rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50">
                    <i class="fa fa-file-lines text-[11px]"></i>
                    <span>View purchase</span>
                </a>
            <?php endif; ?>

            <?php if (!$postedAt): ?>
                <form method="post" action="<?= $h($postUrl) ?>">
                    <button type="submit"
                            class="inline-flex items-center gap-1 rounded-lg bg-emerald-600 px-3.5 py-1.5 text-xs font-semibold text-white shadow-sm hover:bg-emerald-700 focus:outline-none focus:ring-2 focus:ring-emerald-600 focus:ring-offset-1">
                        <i class="fa fa-arrow-up-right-from-square text-[11px]"></i>
                        <span>Post to inventory</span>
                    </button>
                </form>
            <?php endif; ?>
        </div>
    </header>

    <!-- Row 1: Metrics -->
    <section class="grid gap-4 md:grid-cols-3">
        <!-- Qty / amount -->
        <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
            <p class="text-xs font-medium text-slate-500 mb-1">Received quantity</p>
            <p class="text-xl font-semibold text-slate-900">
                <?= $h(number_format($totalQty, 3)) ?>
            </p>
            <p class="mt-2 text-xs font-medium text-slate-500 mb-1">Total value</p>
            <p class="text-lg font-semibold text-slate-900">
                <?= $h(grn_fmt_amount($totalAmount)) ?> <?= $h($currency) ?>
            </p>
        </div>

        <!-- Reference -->
        <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm space-y-2">
            <p class="text-xs font-medium text-slate-500 mb-1">Source reference</p>
            <p class="text-sm text-slate-900">
                <?php if ($refType === 'purchase'): ?>
                    Purchase order
                    <?= $purchaseNo
                        ? $h($purchaseNo)
                        : ($purchaseId ? '#' . $h((string)$purchaseId) : 'Not set') ?>
                <?php elseif ($refType === 'lc'): ?>
                    LC / import: <?= $refNo !== '' ? $h($refNo) : 'Not set' ?>
                <?php else: ?>
                    Manual reference: <?= $refNo !== '' ? $h($refNo) : 'Not set' ?>
                <?php endif; ?>
            </p>
            <?php if ($refNo !== '' && $refType !== 'purchase'): ?>
                <p class="text-[11px] text-slate-500">
                    Ref no: <?= $h($refNo) ?>
                </p>
            <?php endif; ?>
            <p class="mt-2 text-xs text-slate-500">
                GRN date:
                <span class="font-medium text-slate-900">
                    <?= $grnDate !== '' ? $h($grnDate) : '—' ?>
                </span>
            </p>
        </div>

        <!-- Document info -->
        <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
            <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-slate-600">
                Document info
            </p>
            <dl class="space-y-1.5 text-xs">
                <div class="flex items-center justify-between">
                    <dt class="text-slate-500">GRN ID</dt>
                    <dd class="text-slate-900">#<?= $h((string)$id) ?></dd>
                </div>
                <div class="flex items-center justify-between">
                    <dt class="text-slate-500">Created</dt>
                    <dd class="text-slate-900"><?= $createdAt ? $h($createdAt) : '—' ?></dd>
                </div>
                <div class="flex items-center justify-between">
                    <dt class="text-slate-500">Updated</dt>
                    <dd class="text-slate-900"><?= $updatedAt ? $h($updatedAt) : '—' ?></dd>
                </div>
                <div class="flex items-center justify-between">
                    <dt class="text-slate-500">Posted at</dt>
                    <dd class="text-slate-900"><?= $postedAt ? $h($postedAt) : '—' ?></dd>
                </div>
                <div class="flex items-center justify-between">
                    <dt class="text-slate-500">Posted by</dt>
                    <dd class="text-slate-900"><?= $postedBy ? $h($postedBy) : '—' ?></dd>
                </div>
            </dl>
        </div>
    </section>

    <!-- Row 2: Supplier / Warehouse + Notes -->
    <section class="grid gap-4 md:grid-cols-12">
        <!-- Supplier & warehouse -->
        <div class="md:col-span-7 rounded-xl border border-slate-200 bg-white p-4 shadow-sm space-y-3">
            <div class="flex items-center gap-2 text-xs font-semibold text-slate-600 uppercase tracking-wide">
                <span class="inline-flex h-6 w-6 items-center justify-center rounded-full bg-emerald-600 text-[11px] text-white">
                    GRN
                </span>
                <span>Supplier &amp; warehouse</span>
            </div>
            <div class="grid gap-3 md:grid-cols-2 text-sm">
                <div>
                    <p class="text-xs font-medium text-slate-500 mb-1">Supplier</p>
                    <p class="text-sm font-semibold text-slate-900">
                        <?= $suppName !== '' ? $h($suppName) : 'Not set' ?>
                    </p>
                </div>
                <div>
                    <p class="text-xs font-medium text-slate-500 mb-1">Warehouse / location</p>
                    <p class="text-sm font-semibold text-slate-900">
                        <?= $whName !== '' ? $h($whName) : 'Not set' ?>
                    </p>
                </div>
            </div>
        </div>

        <!-- Notes -->
        <div class="md:col-span-5 rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
            <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-slate-600">
                Notes / inspection summary
            </p>
            <?php if ($notes !== ''): ?>
                <p class="text-sm text-slate-800 whitespace-pre-wrap">
                    <?= $h($notes) ?>
                </p>
            <?php else: ?>
                <p class="text-xs text-slate-500">
                    No notes recorded for this GRN.
                </p>
            <?php endif; ?>
        </div>
    </section>

    <!-- Row 3: Line items (full width) -->
    <section class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm space-y-3">
        <div class="flex items-center justify-between gap-2">
            <div class="flex items-center gap-2">
                <span class="inline-flex h-6 w-6 items-center justify-center rounded-full bg-emerald-600 text-[11px] text-white">
                    IT
                </span>
                <h2 class="text-sm font-semibold text-slate-900">Line items</h2>
            </div>
            <p class="text-[11px] text-slate-500">
                Quantities here are what physically arrived and will be posted to inventory.
            </p>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-xs">
                <thead class="bg-slate-50 text-[11px] font-semibold uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="px-3 py-2 text-left">Item</th>
                        <th class="px-3 py-2 text-left">Description</th>
                        <th class="px-3 py-2 text-center">UOM</th>
                        <th class="px-3 py-2 text-right">Qty received</th>
                        <th class="px-3 py-2 text-right">Unit cost</th>
                        <th class="px-3 py-2 text-right">Line total</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 bg-white">
                <?php if (!empty($items)): ?>
                    <?php foreach ($items as $it): ?>
                        <?php
                        $itemId = (int)($it['item_id'] ?? 0);

                        $name = $it['item_name']
                            ?? $it['product_name']
                            ?? '';

                        $code = $it['item_code']
                            ?? $it['product_code']
                            ?? '';

                        if ($name === '' && $code === '' && $itemId > 0) {
                            $code = 'Item #' . $itemId;
                        }

                        // DESCRIPTION: use row description if present,
                        // otherwise fall back to item name so column is never blank.
                        $desc = $it['description'] ?? '';
                        if ($desc === '' && $name !== '') {
                            $desc = $name;
                        }

                        $uom = $it['unit']
                            ?? $it['uom']
                            ?? '';

                        // KEEP logic: try qty_received, then qty_this_grn, then qty
                        $qty = (float)($it['qty_received']
                            ?? $it['qty_this_grn']
                            ?? $it['qty']
                            ?? 0);

                        $price = (float)($it['unit_cost']
                            ?? $it['unit_price']
                            ?? 0);

                        $totalL = (float)($it['line_total']
                            ?? $it['total_cost']
                            ?? ($qty * $price));
                        ?>
                        <tr class="hover:bg-emerald-50/40">
                            <td class="px-3 py-2 align-top">
                                <?php if ($name !== ''): ?>
                                    <div class="font-medium text-slate-900"><?= $h($name) ?></div>
                                <?php endif; ?>
                                <?php if ($code !== ''): ?>
                                    <div class="text-[11px] text-slate-500"><?= $h($code) ?></div>
                                <?php endif; ?>
                                <?php if ($name === '' && $code === ''): ?>
                                    <div class="text-[11px] text-slate-400">&mdash;</div>
                                <?php endif; ?>
                            </td>
                            <td class="px-3 py-2 align-top text-slate-700">
                                <?= $desc !== '' ? nl2br($h($desc)) : '&mdash;' ?>
                            </td>
                            <td class="px-3 py-2 align-top text-center text-slate-700">
                                <?= $uom !== '' ? $h($uom) : '&mdash;' ?>
                            </td>
                            <td class="px-3 py-2 align-top text-right text-slate-700">
                                <?= $h(number_format($qty, 3)) ?>
                            </td>
                            <td class="px-3 py-2 align-top text-right text-slate-700">
                                <?= $h(grn_fmt_amount($price)) ?>
                            </td>
                            <td class="px-3 py-2 align-top text-right text-slate-900">
                                <?= $h(grn_fmt_amount($totalL)) ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="6" class="px-3 py-4 text-center text-xs text-slate-500">
                            No line items found for this GRN.
                        </td>
                    </tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>
</div>