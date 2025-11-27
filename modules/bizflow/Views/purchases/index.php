<?php
/** @var array  $org */
/** @var array  $purchases */
/** @var string $module_base */
/** @var int    $total_count */
/** @var int    $open_count */
/** @var int    $receiving_count */
/** @var int    $no_inventory_cnt */
/** @var string $search */
/** @var string $filter_status */
/** @var string $filter_type */
/** @var string $filter_inv */

$h = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');

$module_base   = rtrim($module_base ?? ($base ?? '/apps/bizflow'), '/');
$orgName       = $org['name'] ?? '';
$search        = (string)($search ?? '');
$filterStatus  = (string)($filter_status ?? 'all');
$filterType    = (string)($filter_type ?? 'all');
$filterInv     = (string)($filter_inv ?? '');
?>
<div class="space-y-6">

    <!-- Header + nav tabs -->
    <header class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
        <div class="space-y-1">
            <h1 class="text-2xl md:text-3xl font-semibold tracking-tight">
                Purchases
            </h1>
            <p class="text-sm text-slate-500">
                Track local and import purchases, LC links, GRNs and inventory impact for
                <?= $h($orgName ?: 'your organisation') ?>.
            </p>
        </div>

        <!-- Right-aligned BizFlow tabs -->
        <nav class="flex flex-wrap justify-end gap-1 text-sm">
            <?php
            $tabs = [
                ['Items',      $module_base.'/items'],
                ['LC',    	   $module_base.'/lcs'],
                ['GRN',    	   $module_base.'/grn'],
                ['Orders',     $module_base.'/orders'],
                ['Invoices',   $module_base.'/invoices'],
                ['Inventory',  $module_base.'/inventory'],
            ];
            $current = $module_base.'/purchases';
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

    <!-- KPI cards -->
    <section class="grid gap-3 md:grid-cols-4">
        <div class="rounded-xl border border-slate-200 bg-white p-3 shadow-sm">
            <div class="text-xs text-slate-500">Purchases</div>
            <div class="mt-1 text-2xl font-semibold text-slate-900"><?= (int)$total_count ?></div>
            <p class="mt-1 text-[11px] text-slate-500">Total documents in this tenant.</p>
        </div>
        <div class="rounded-xl border border-slate-200 bg-white p-3 shadow-sm">
            <div class="text-xs text-slate-500">Open pipeline</div>
            <div class="mt-1 text-2xl font-semibold text-amber-700"><?= (int)$open_count ?></div>
            <p class="mt-1 text-[11px] text-slate-500">Draft / approved / LC / in-transit.</p>
        </div>
        <div class="rounded-xl border border-slate-200 bg-white p-3 shadow-sm">
            <div class="text-xs text-slate-500">Receiving</div>
            <div class="mt-1 text-2xl font-semibold text-sky-700"><?= (int)$receiving_count ?></div>
            <p class="mt-1 text-[11px] text-slate-500">Partial or active GRN in progress.</p>
        </div>
        <div class="rounded-xl border border-slate-200 bg-white p-3 shadow-sm">
            <div class="text-xs text-slate-500">Non-inventory</div>
            <div class="mt-1 text-2xl font-semibold text-slate-800"><?= (int)$no_inventory_cnt ?></div>
            <p class="mt-1 text-[11px] text-slate-500">Service / expense-only purchases.</p>
        </div>
    </section>

    <!-- Filters row -->
    <section class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm space-y-3">
        <form method="get" class="flex flex-col gap-3 md:flex-row md:items-end md:justify-between">
            <div class="flex-1 space-y-2">
                <label class="block text-xs font-medium text-slate-700">
                    Search
                </label>
                <div class="relative">
                    <input type="text"
                           name="q"
                           value="<?= $h($search) ?>"
                           placeholder="Search by PO no, supplier, LC no, external ref…"
                           class="block w-full rounded-lg border border-slate-200 px-3 py-2 pl-8 text-sm
                                  focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500">
                    <i class="fa fa-search absolute left-2.5 top-1/2 -translate-y-1/2 text-slate-400 text-xs"></i>
                </div>
            </div>

            <div class="flex flex-col gap-2 md:flex-row md:items-end">
                <!-- Status -->
                <div>
                    <label class="block text-xs font-medium text-slate-700 mb-1">
                        Status
                    </label>
                    <select name="status"
                            class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm
                                   focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500">
                        <?php
                        $statusOptions = [
                            'all'                => 'All',
                            'draft'              => 'Draft',
                            'approved'           => 'Approved',
                            'lc_open_pending'    => 'LC open pending',
                            'lc_opened'          => 'LC opened',
                            'in_transit'         => 'In transit',
                            'receiving'          => 'Receiving',
                            'partially_received' => 'Partially received',
                            'completed'          => 'Completed',
                            'cancelled'          => 'Cancelled',
                        ];
                        foreach ($statusOptions as $val => $label):
                        ?>
                            <option value="<?= $h($val) ?>"
                                <?= $val === $filterStatus ? 'selected' : '' ?>>
                                <?= $h($label) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Type -->
                <div>
                    <label class="block text-xs font-medium text-slate-700 mb-1">
                        Purchase type
                    </label>
                    <select name="type"
                            class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm
                                   focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500">
                        <?php
                        $typeOptions = [
                            'all'     => 'All',
                            'local'   => 'Local',
                            'import'  => 'Import',
                            'service' => 'Service / expense-only',
                        ];
                        foreach ($typeOptions as $val => $label):
                        ?>
                            <option value="<?= $h($val) ?>"
                                <?= $val === $filterType ? 'selected' : '' ?>>
                                <?= $h($label) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Inventory filter -->
                <div>
                    <label class="block text-xs font-medium text-slate-700 mb-1">
                        View
                    </label>
                    <select name="inv"
                            class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm
                                   focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500">
                        <option value="" <?= $filterInv === '' ? 'selected' : '' ?>>All purchases</option>
                        <option value="inventory" <?= $filterInv === 'inventory' ? 'selected' : '' ?>>
                            Inventory-impact only
                        </option>
                        <option value="no_inventory" <?= $filterInv === 'no_inventory' ? 'selected' : '' ?>>
                            Non-inventory only
                        </option>
                    </select>
                </div>

                <div class="flex gap-2">
                    <a href="<?= $h($module_base.'/purchases') ?>"
                       class="inline-flex items-center justify-center rounded-lg border border-slate-200 px-3 py-2 text-xs font-medium text-slate-700 hover:bg-slate-50">
                        Reset
                    </a>
                    <button type="submit"
                            class="inline-flex items-center justify-center rounded-lg bg-slate-900 px-3 py-2 text-xs font-semibold text-white hover:bg-slate-800">
                        Apply
                    </button>
                </div>
            </div>
        </form>
    </section>

    <!-- Purchases table -->
    <section class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm space-y-3">
        <div class="flex items-center justify-between gap-2">
            <h2 class="text-sm font-semibold text-slate-800">
                Purchases
            </h2>
            <a href="<?= $h($module_base.'/purchases/create') ?>"
               class="inline-flex items-center gap-2 rounded-lg bg-emerald-600 px-3 py-1.5 text-xs font-semibold text-white shadow-sm hover:bg-emerald-700">
                <i class="fa fa-plus text-[11px]"></i>
                <span>New purchase</span>
            </a>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-xs">
                <thead class="bg-slate-50 text-[11px] font-semibold uppercase tracking-wide text-slate-500">
                <tr>
                    <th class="px-2 py-2 text-left">PO #</th>
                    <th class="px-2 py-2 text-left">Supplier</th>
                    <th class="px-2 py-2 text-left">Type / LC</th>
                    <th class="px-2 py-2 text-left">Dates</th>
                    <th class="px-2 py-2 text-left">Receipt</th>
                    <th class="px-2 py-2 text-right">Total</th>
                    <th class="px-2 py-2 text-left">Inventory</th>
                    <th class="px-2 py-2 text-right">Actions</th>
                </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 bg-white">
                <?php if (empty($purchases)): ?>
                    <tr>
                        <td colspan="8" class="px-2 py-6 text-center text-sm text-slate-500">
                            No purchases found for the current filters.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($purchases as $p):
                        $id         = (int)($p['id'] ?? 0);
                        $poNo       = trim((string)($p['po_no'] ?? ''));
                        $poNo       = $poNo !== '' ? $poNo : 'PO-'.$id;
                        $supplier   = trim((string)($p['supplier_name'] ?? ''));
                        $status     = strtolower((string)($p['status'] ?? 'draft'));
                        $date       = (string)($p['date'] ?? '');
                        $eta        = (string)($p['expected_date'] ?? '');
                        $curr       = (string)($p['currency'] ?? 'BDT');
                        $total      = (float)($p['grand_total'] ?? ($p['subtotal'] ?? 0));
                        $qtyOrd     = (float)($p['qty_ordered_total']  ?? 0);
                        $qtyRec     = (float)($p['qty_received_total'] ?? 0);
                        $invImpact  = (int)($p['is_inventory_impact'] ?? 1);
                        $purchaseType = (string)($p['purchase_type'] ?? 'local');
                        $awardId    = (int)($p['award_id'] ?? 0);

                        $perc = $qtyOrd > 0 ? round(($qtyRec / $qtyOrd) * 100, 0) : 0;

                        $canReceive = $invImpact !== 0
                                      && $qtyRec < $qtyOrd
                                      && in_array($status, [
                                          'draft','approved','lc_open_pending','lc_opened',
                                          'in_transit','receiving','partially_received'
                                      ], true);
                    ?>
                        <tr class="hover:bg-emerald-50/40">
                            <!-- PO -->
                            <td class="px-2 py-2 align-top">
                                <div class="flex flex-col gap-1">
                                    <div class="text-xs font-semibold text-slate-900">
                                        <a href="<?= $h($module_base.'/purchases/'.$id) ?>"
                                           class="hover:underline">
                                            <?= $h($poNo) ?>
                                        </a>
                                    </div>
                                    <div class="flex flex-wrap gap-1 text-[10px]">
                                        <?php if ($awardId > 0): ?>
                                            <span class="inline-flex items-center rounded-full bg-emerald-50 px-1.5 py-0.5 text-[10px] font-medium text-emerald-700 border border-emerald-200">
                                                <span class="mr-1 h-1.5 w-1.5 rounded-full bg-emerald-600"></span>
                                                From award
                                            </span>
                                        <?php endif; ?>
                                        <span class="inline-flex items-center rounded-full bg-slate-100 px-1.5 py-0.5 text-[10px] text-slate-700">
                                            <?= $h(ucfirst($status)) ?>
                                        </span>
                                    </div>
                                </div>
                            </td>

                            <!-- Supplier -->
                            <td class="px-2 py-2 align-top">
                                <div class="text-xs font-medium text-slate-900">
                                    <?= $supplier !== '' ? $h($supplier) : '<span class="text-slate-400">—</span>' ?>
                                </div>
                                <?php if (!empty($p['external_ref'])): ?>
                                    <div class="text-[11px] text-slate-500">
                                        Ref: <?= $h($p['external_ref']) ?>
                                    </div>
                                <?php endif; ?>
                            </td>

                            <!-- Type / LC -->
                            <td class="px-2 py-2 align-top">
                                <div class="text-[11px] font-medium text-slate-800">
                                    <?php
                                    $labelType = match ($purchaseType) {
                                        'import'  => 'Import',
                                        'service' => 'Service / expense-only',
                                        default   => 'Local',
                                    };
                                    ?>
                                    <?= $h($labelType) ?>
                                </div>
                                <?php if (!empty($p['lc_no'])): ?>
                                    <div class="text-[11px] text-slate-500">
                                        LC: <?= $h($p['lc_no']) ?>
                                    </div>
                                <?php endif; ?>
                            </td>

                            <!-- Dates -->
                            <td class="px-2 py-2 align-top text-[11px] text-slate-600">
                                <div>PO date: <?= $date !== '' ? $h($date) : '—' ?></div>
                                <div>ETA: <?= $eta !== '' ? $h($eta) : '—' ?></div>
                            </td>

                            <!-- Receipt -->
                            <td class="px-2 py-2 align-top text-[11px] text-slate-600">
                                <div><?= number_format($qtyRec, 3) ?> / <?= number_format($qtyOrd, 3) ?></div>
                                <div><?= $perc ?>% received</div>
                            </td>

                            <!-- Total -->
                            <td class="px-2 py-2 align-top text-right whitespace-nowrap">
                                <div class="text-xs font-semibold text-slate-900">
                                    <?= number_format($total, 2) ?> <?= $h($curr ?: 'BDT') ?>
                                </div>
                            </td>

                            <!-- Inventory -->
                            <td class="px-2 py-2 align-top">
                                <?php if ($invImpact !== 0): ?>
                                    <span class="inline-flex items-center rounded-full bg-emerald-50 px-2 py-0.5 text-[11px] font-medium text-emerald-700 border border-emerald-200">
                                        <i class="fa fa-boxes-stacked mr-1 text-[9px]"></i>
                                        Inventory
                                    </span>
                                <?php else: ?>
                                    <span class="inline-flex items-center rounded-full bg-slate-100 px-2 py-0.5 text-[11px] font-medium text-slate-700 border border-slate-200">
                                        <i class="fa fa-receipt mr-1 text-[9px]"></i>
                                        Non-inventory
                                    </span>
                                <?php endif; ?>
                            </td>

                            <!-- Actions -->
                            <td class="px-2 py-2 align-top text-right">
                                <div class="inline-flex flex-wrap justify-end gap-1">

                                    <!-- View -->
                                    <a href="<?= $h($module_base.'/purchases/'.$id) ?>"
                                       class="inline-flex items-center gap-1 rounded-lg border border-slate-200 px-2.5 py-1 text-[11px] text-slate-700 hover:bg-slate-50">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7">
                                            <path d="M2.25 12s3.25-6.75 9.75-6.75S21.75 12 21.75 12 18.5 18.75 12 18.75 2.25 12 2.25 12Z"/>
                                            <circle cx="12" cy="12" r="3.25"/>
                                        </svg>
                                        <span>View</span>
                                    </a>

                                    <!-- Print (HTML, auto window.print in pdf view) -->
                                    <a href="<?= $h($module_base.'/purchases/'.$id.'/print') ?>"
                                       target="_blank"
                                       class="inline-flex items-center gap-1 rounded-lg border border-slate-200 px-2.5 py-1 text-[11px] text-slate-700 hover:bg-slate-50"
                                       title="Print purchase order">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7">
                                            <path d="M7 9V4.5A1.5 1.5 0 0 1 8.5 3h7A1.5 1.5 0 0 1 17 4.5V9"/>
                                            <rect x="6" y="14" width="12" height="7" rx="1.4" ry="1.4"/>
                                            <path d="M5 9h14a2 2 0 0 1 2 2v3h-3.5"/>
                                            <path d="M8 6.5h5"/>
                                        </svg>
                                        <span>Print</span>
                                    </a>

                                    <!-- Download PDF -->
                                    <a href="<?= $h($module_base.'/purchases/'.$id.'/pdf') ?>"
                                       class="inline-flex items-center gap-1 rounded-lg border border-slate-200 px-2.5 py-1 text-[11px] text-slate-700 hover:bg-slate-50"
                                       title="Download PDF">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7">
                                            <path d="M12 3v11.5"/>
                                            <path d="M8.25 11.5 12 15.25 15.75 11.5"/>
                                            <path d="M5 17.5v1A2.5 2.5 0 0 0 7.5 21h9A2.5 2.5 0 0 0 19 18.5v-1"/>
                                        </svg>
                                        <span>PDF</span>
                                    </a>

                                    <?php if ($canReceive): ?>
                                        <a href="<?= $h($module_base.'/purchases/'.$id.'/receive') ?>"
                                           class="inline-flex items-center gap-1 rounded-lg bg-emerald-600 px-2.5 py-1 text-[11px] font-semibold text-white hover:bg-emerald-700">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7">
                                                <path d="M4 9h16l-1.2 8.4A2 2 0 0 1 16.82 19H7.18A2 2 0 0 1 5.2 17.4L4 9Z"/>
                                                <path d="M9 9V5.5A1.5 1.5 0 0 1 10.5 4h3A1.5 1.5 0 0 1 15 5.5V9"/>
                                            </svg>
                                            <span>Receive</span>
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>

</div>