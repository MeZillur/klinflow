<?php
/** @var array  $grn */
/** @var array  $purchase */
/** @var array  $items */
/** @var array  $org */
/** @var string $module_base */
/** @var string $title */
/** @var string $mode */

$h = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');

$module_base = rtrim($module_base ?? '/apps/bizflow', '/');

$poId   = (int)($purchase['id'] ?? 0);
$poNo   = (string)($purchase['po_no'] ?? ('PO-'.$poId));
$supp   = (string)($purchase['supplier_name'] ?? '');
$curr   = (string)($purchase['currency'] ?? ($grn['currency'] ?? 'BDT'));
$poDate = (string)($purchase['date'] ?? '');
$eta    = (string)($purchase['expected_date'] ?? '');
$grand  = (float)($purchase['grand_total'] ?? 0);

$today  = (string)($grn['grn_date'] ?? date('Y-m-d'));

$action = $module_base . '/grn';
?>
<div class="space-y-6">

    <!-- Header + tabs (BizFlow: right-aligned) -->
    <header class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
        <div class="space-y-1">
            <h1 class="text-2xl md:text-3xl font-semibold tracking-tight">
                <?= $h($title ?: ('New GRN from '.$poNo)) ?>
            </h1>
            <p class="text-sm text-slate-500">
                Record a Goods Receipt Note against purchase
                <span class="font-semibold text-slate-700"><?= $h($poNo) ?></span>
                and prepare it for inventory posting.
            </p>
        </div>

        <nav class="flex flex-wrap justify-end gap-1 text-xs md:text-sm">
            <?php
            $tabs = [
                ['GRN register', $module_base . '/grn'],
                ['New GRN',      $module_base . '/grn/create'],
            ];
            $current = $module_base . '/grn/create';
            foreach ($tabs as [$label, $url]):
                $active = ($url === $current);
            ?>
                <a href="<?= $h($url) ?>"
                   class="inline-flex items-center rounded-full border px-3 py-1
                          <?= $active
                               ? 'border-emerald-600 bg-emerald-50 text-emerald-700 font-semibold'
                               : 'border-slate-200 text-slate-600 hover:bg-slate-50' ?>">
                    <?= $h($label) ?>
                </a>
            <?php endforeach; ?>
        </nav>
    </header>

    <!-- Single form: header + line items -->
    <form method="post"
          action="<?= $h($action) ?>"
          class="space-y-6"
          x-data="{ postingDate: '<?= $h($today) ?>' }">

        <!-- identify source for controller -->
        <input type="hidden" name="purchase_id" value="<?= $h($poId) ?>">
        <input type="hidden" name="ref_type"    value="purchase">
        <input type="hidden" name="ref_no"      value="<?= $h($poNo) ?>">

        <!-- Purchase summary + GRN header -->
        <section class="grid gap-4 lg:grid-cols-[1.5fr,1.8fr]">
            <!-- Purchase summary card -->
            <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm space-y-3">
                <div class="flex items-start justify-between gap-2">
                    <div>
                        <div class="text-xs uppercase tracking-wide text-slate-500">Source purchase</div>
                        <div class="mt-1 text-lg font-semibold text-slate-900 flex items-center gap-2">
                            <a href="<?= $h($module_base . '/purchases/' . $poId) ?>"
                               class="text-emerald-700 hover:text-emerald-800 hover:underline">
                                <?= $h($poNo) ?>
                            </a>
                            <span class="inline-flex items-center rounded-full bg-slate-100 px-2 py-0.5 text-[11px] font-medium text-slate-600">
                                #<?= $h($poId) ?>
                            </span>
                        </div>
                        <?php if ($supp !== ''): ?>
                            <div class="mt-1 text-xs text-slate-600">
                                Supplier:
                                <span class="font-medium text-slate-800"><?= $h($supp) ?></span>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="text-right text-sm">
                        <div class="text-slate-500">PO grand total</div>
                        <div class="text-base font-semibold text-emerald-700">
                            <?= $h(number_format($grand, 2)) . ' ' . $h($curr) ?>
                        </div>
                    </div>
                </div>

                <dl class="grid grid-cols-2 gap-x-4 gap-y-2 text-xs text-slate-600 pt-2 border-t border-slate-100">
                    <div>
                        <dt class="font-medium text-slate-500">PO date</dt>
                        <dd><?= $h($poDate ?: '—') ?></dd>
                    </div>
                    <div>
                        <dt class="font-medium text-slate-500">ETA</dt>
                        <dd><?= $h($eta ?: '—') ?></dd>
                    </div>
                    <div>
                        <dt class="font-medium text-slate-500">Currency</dt>
                        <dd><?= $h($curr) ?></dd>
                    </div>
                    <div>
                        <dt class="font-medium text-slate-500">Status</dt>
                        <dd>
                            <span class="inline-flex items-center rounded-full bg-emerald-50 px-2 py-0.5 text-[11px] font-medium text-emerald-700">
                                <?= $h($purchase['status'] ?? 'Draft') ?>
                            </span>
                        </dd>
                    </div>
                </dl>
            </div>

            <!-- GRN header form -->
            <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm space-y-3">
                <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
                    <div>
                        <label class="block text-xs font-medium text-slate-600 mb-1">
                            GRN No
                        </label>
                        <input name="grn_no"
                               type="text"
                               value=""
                               placeholder="Auto or manual"
                               class="block w-full rounded-lg border border-slate-200 px-3 py-2 text-sm
                                      placeholder:text-slate-400 focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500">
                        <p class="mt-1 text-[11px] text-slate-500">
                            Leave blank for auto-number (future); or type manual GRN no.
                        </p>
                    </div>

                    <div>
                        <label class="block text-xs font-medium text-slate-600 mb-1">
                            GRN date
                        </label>
                        <input name="grn_date"
                               type="date"
                               x-model="postingDate"
                               value="<?= $h($today) ?>"
                               class="block w-full rounded-lg border border-slate-200 px-3 py-2 text-sm
                                      focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500">
                        <p class="mt-1 text-[11px] text-slate-500">
                            Date goods physically arrived at your warehouse.
                        </p>
                    </div>

                    <div>
                        <label class="block text-xs font-medium text-slate-600 mb-1">
                            Currency
                        </label>
                        <select name="currency"
                                class="block w-full rounded-lg border border-slate-200 px-3 py-2 text-sm bg-white
                                       focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500">
                            <option value="<?= $h($curr) ?>" selected><?= $h($curr) ?></option>
                        </select>
                        <p class="mt-1 text-[11px] text-slate-500">
                            Uses purchase currency for now.
                        </p>
                    </div>
                </div>

                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                    <div>
                        <label class="block text-xs font-medium text-slate-600 mb-1">
                            Supplier name
                        </label>
                        <input name="supplier_name"
                               type="text"
                               value="<?= $h($grn['supplier_name'] ?? $supp) ?>"
                               class="block w-full rounded-lg border border-slate-200 px-3 py-2 text-sm
                                      focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500">
                        <p class="mt-1 text-[11px] text-slate-500">
                            Will later be locked to the purchase supplier.
                        </p>
                    </div>

                    <div>
                        <label class="block text-xs font-medium text-slate-600 mb-1">
                            Warehouse / location
                        </label>
                        <input name="warehouse_name"
                               type="text"
                               value="<?= $h($grn['warehouse_name'] ?? '') ?>"
                               placeholder="Main warehouse, depot, etc."
                               class="block w-full rounded-lg border border-slate-200 px-3 py-2 text-sm
                                      focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500">
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-medium text-slate-600 mb-1">
                        Notes
                    </label>
                    <textarea name="notes"
                              rows="2"
                              placeholder="Optional remarks (truck no, seal no, inspection summary, etc.)"
                              class="block w-full rounded-lg border border-slate-200 px-3 py-2 text-sm
                                     resize-y focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500"><?= $h($grn['notes'] ?? '') ?></textarea>
                </div>
            </div>
        </section>

        <!-- Line items from purchase -->
        <section class="rounded-xl border border-slate-200 bg-white shadow-sm overflow-hidden">
            <div class="flex items-center justify-between border-b border-slate-100 px-4 py-3">
                <div>
                    <h2 class="text-sm font-semibold text-slate-800">Line items</h2>
                    <p class="text-xs text-slate-500">
                        Quantities below are copied from purchase <?= $h($poNo) ?>. Adjust
                        <strong>Qty this GRN</strong> to reflect what you are receiving now.
                    </p>
                </div>
                <div class="hidden md:flex flex-col items-end text-xs text-slate-500">
                    <span>Ordered − Received = Remaining</span>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-xs">
                    <thead class="bg-slate-50 text-[11px] font-semibold uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="px-3 py-2 text-left">Item</th>
                        <th class="px-3 py-2 text-left">Code</th>
                        <th class="px-3 py-2 text-left">Description</th>
                        <th class="px-3 py-2 text-right">Qty ordered</th>
                        <th class="px-3 py-2 text-right">Qty received</th>
                        <th class="px-3 py-2 text-right">Qty this GRN</th>
                        <th class="px-3 py-2 text-left">Unit</th>
                        <th class="px-3 py-2 text-right">Unit cost</th>
                        <th class="px-3 py-2 text-right">Line total</th>
                    </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 bg-white">
                    <?php if (!empty($items)): ?>
                        <?php
                        $i = 0;
                        foreach ($items as $ln):
                            $i++;
                            $lineIdx = $i - 1;
                            $lineId   = (int)($ln['id'] ?? 0);
                            $itemId   = (int)($ln['item_id'] ?? 0);
                            $name     = $ln['item_name'] ?? $ln['product_name'] ?? $ln['description'] ?? ('Line '.$i);
                            $code     = $ln['item_code'] ?? $ln['product_code'] ?? '';
                            $desc     = $ln['description'] ?? '';
                            $qtyOrd   = isset($ln['qty_ordered']) ? (float)$ln['qty_ordered'] : (float)($ln['qty'] ?? 0);
                            $qtyRecv  = (float)($ln['qty_received'] ?? 0);
                            $unit     = $ln['unit'] ?? $ln['uom'] ?? 'pcs';
                            $price    = (float)($ln['unit_price'] ?? 0);
                            $defaultQtyThis = max(0.0, $qtyOrd - $qtyRecv);
                        ?>
                            <tr class="hover:bg-emerald-50/40"
                                x-data="{
                                    qtyOrdered: <?= $qtyOrd ?>,
                                    qtyReceived: <?= $qtyRecv ?>,
                                    qtyThis: <?= $defaultQtyThis ?>,
                                    unitPrice: <?= $price ?>,
                                    clamp() {
                                        if (this.qtyThis < 0) this.qtyThis = 0;
                                        const rem = this.qtyOrdered - this.qtyReceived;
                                        if (this.qtyThis > rem) this.qtyThis = rem;
                                    },
                                    get remaining() { return this.qtyOrdered - this.qtyReceived; },
                                    get lineTotal() { return (this.qtyThis || 0) * this.unitPrice; }
                                }">
                                <!-- hidden fields that actually post to PHP -->
                                <td class="hidden">
                                    <input type="hidden"
                                           name="items[<?= $lineIdx ?>][item_id]"
                                           value="<?= $h($itemId) ?>">
                                    <input type="hidden"
                                           name="items[<?= $lineIdx ?>][purchase_line_id]"
                                           value="<?= $h($lineId) ?>">
                                    <input type="hidden"
                                           name="items[<?= $lineIdx ?>][unit]"
                                           value="<?= $h($unit) ?>">
                                    <input type="hidden"
                                           name="items[<?= $lineIdx ?>][unit_cost]"
                                           :value="unitPrice">
                                    <input type="hidden"
                                           name="items[<?= $lineIdx ?>][qty_this_grn]"
                                           :value="qtyThis">
                                    <input type="hidden"
                                           name="items[<?= $lineIdx ?>][line_total]"
                                           :value="lineTotal">
                                </td>

                                <td class="px-3 py-2 align-top">
                                    <div class="font-medium text-slate-900 text-xs">
                                        <?= $h($name) ?>
                                    </div>
                                </td>
                                <td class="px-3 py-2 align-top text-xs text-slate-600 whitespace-nowrap">
                                    <?= $h($code) ?>
                                </td>
                                <td class="px-3 py-2 align-top text-[11px] text-slate-600">
                                    <?= $h($desc) ?>
                                </td>
                                <td class="px-3 py-2 align-top text-right text-xs text-slate-700 whitespace-nowrap">
                                    <span x-text="qtyOrdered.toFixed(3)"></span>
                                </td>
                                <td class="px-3 py-2 align-top text-right text-xs text-slate-700 whitespace-nowrap">
                                    <span x-text="qtyReceived.toFixed(3)"></span>
                                </td>
                                <td class="px-3 py-2 align-top text-right text-xs whitespace-nowrap">
                                    <div class="inline-flex flex-col items-end gap-1">
                                        <input type="number" step="0.001" min="0"
                                               x-model.number="qtyThis"
                                               @input="clamp()"
                                               class="w-20 rounded-lg border border-slate-200 px-2 py-1 text-right text-xs
                                                      focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500">
                                        <span class="text-[10px] text-slate-500">
                                            Rem:
                                            <span x-text="remaining.toFixed(3)"></span>
                                        </span>
                                    </div>
                                </td>
                                <td class="px-3 py-2 align-top text-xs text-slate-700 whitespace-nowrap">
                                    <?= $h($unit) ?>
                                </td>
                                <td class="px-3 py-2 align-top text-right text-xs text-slate-700 whitespace-nowrap">
                                    <span x-text="unitPrice.toFixed(2)"></span>
                                    <?= ' ' . $h($curr) ?>
                                </td>
                                <td class="px-3 py-2 align-top text-right text-xs font-semibold text-slate-900 whitespace-nowrap">
                                    <span x-text="lineTotal.toFixed(2)"></span>
                                    <?= ' ' . $h($curr) ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="9" class="px-4 py-6 text-center text-sm text-slate-500">
                                No line items were found on this purchase.
                            </td>
                        </tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <div class="border-t border-slate-100 px-4 py-3 flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                <p class="text-[11px] text-slate-500">
                    <strong>Qty this GRN</strong> will drive GRN quantities and inventory moves.
                    You can post to inventory after saving.
                </p>

                <button type="submit"
                        class="inline-flex items-center justify-center gap-2 rounded-lg bg-emerald-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-emerald-700 focus:outline-none focus:ring-2 focus:ring-emerald-600 focus:ring-offset-1">
                    <i class="fa fa-truck-loading text-xs"></i>
                    <span>Save GRN</span>
                </button>
            </div>
        </section>
    </form>

    <!-- How to use this page -->
    <section class="rounded-xl border border-dashed border-emerald-400 bg-emerald-50/70 px-4 py-4 text-sm text-slate-800">
        <h2 class="mb-2 flex items-center gap-2 text-sm font-semibold text-emerald-900">
            <span class="inline-flex h-6 w-6 items-center justify-center rounded-full bg-emerald-600 text-[11px] text-white">
                ?
            </span>
            How to use this page
        </h2>
        <ul class="ml-6 list-disc space-y-1 text-[13px]">
            <li>Review the <strong>purchase header</strong> (PO date, supplier, currency, total).</li>
            <li>Set the <strong>GRN date</strong> to when goods physically reached your warehouse.</li>
            <li>Confirm or adjust <strong>supplier</strong> and <strong>warehouse/location</strong> if needed.</li>
            <li>For each line, enter <strong>Qty this GRN</strong>. The remaining quantity is shown for reference.</li>
            <li>Click <strong>Save GRN</strong>. The system will create the GRN (header + lines) and you can then post it to inventory.</li>
        </ul>
    </section>
</div>