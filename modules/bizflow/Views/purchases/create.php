<?php
/** @var array       $org */
/** @var array       $suppliers */
/** @var array       $items */
/** @var string      $module_base */
/** @var string      $title */
/** @var string      $today */
/** @var bool|null   $has_supplier_master */
/** @var bool|null   $has_item_master */

$h = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');

$module_base       = rtrim($module_base ?? ($base ?? '/apps/bizflow'), '/');
$orgName           = $org['name'] ?? '';
$csrf              = $csrf ?? ($_SESSION['csrf'] ?? null);
$hasSupplierMaster = $has_supplier_master ?? null;
$hasItemMaster     = $has_item_master     ?? null;

$suppliers = is_array($suppliers ?? null) ? $suppliers : [];
$items     = is_array($items ?? null) ? $items : [];
?>
<div class="space-y-6">

    <!-- Top bar: title + tabs -->
    <header class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
        <div class="space-y-1">
            <h1 class="text-2xl md:text-3xl font-semibold tracking-tight">
                <?= $h($title ?? 'New purchase') ?>
            </h1>
            <p class="text-sm text-slate-500">
                Simple purchase order form for <?= $h($orgName ?: 'your organisation') ?> —
                LC / import flow will be handled from the dedicated LC module.
            </p>
        </div>

        <!-- Right-aligned app tabs (unchanged) -->
        <nav class="flex flex-wrap justify-end gap-1 text-sm">
            <?php
            $tabs = [
                ['Items',      $module_base.'/items'],
                ['Customers',  $module_base.'/customers'],
                ['Suppliers',  $module_base.'/suppliers'],
                ['Quotes',     $module_base.'/quotes'],
                ['Orders',     $module_base.'/orders'],
                ['Invoices',   $module_base.'/invoices'],
                ['Purchases',  $module_base.'/purchases'],
                ['Tenders',    $module_base.'/tenders'],
                ['Inventory',  $module_base.'/inventory'],
                ['Reports',    $module_base.'/reports'],
                ['Settings',   $module_base.'/settings'],
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

    <!-- Main form (still single simple form) -->
    <form id="purchase_create_form"
          method="post"
          action="<?= $h($module_base.'/purchases') ?>"
          class="space-y-6">

        <?php if ($csrf): ?>
            <input type="hidden" name="csrf" value="<?= $h($csrf) ?>">
        <?php endif; ?>

        <!-- Fix purchase_type to local; LC handled elsewhere -->
        <input type="hidden" name="purchase_type" value="local">

        <!-- Header section -->
        <section class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm space-y-4">
            <div class="grid gap-4 md:grid-cols-2">
                <!-- Supplier + PO basics -->
                <div class="space-y-4">
                    <!-- Supplier -->
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">
                            Supplier <span class="text-rose-500">*</span>
                        </label>
                        <select name="supplier_id"
                                class="block w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-sm
                                       focus:border-emerald-600 focus:ring-emerald-600"
                                required>
                            <option value="">
                                <?php if (!empty($suppliers)): ?>
                                    <?= $h('Select supplier…') ?>
                                <?php else: ?>
                                    <?= $h('No suppliers yet — add them in Suppliers.') ?>
                                <?php endif; ?>
                            </option>
                            <?php foreach ($suppliers as $s): ?>
                                <option value="<?= $h($s['id']) ?>">
                                    <?= $h(
                                        trim(
                                            (($s['code'] ?? '') !== '' ? '['.($s['code'] ?? '').'] ' : '').
                                            ($s['name'] ?? '').
                                            (isset($s['type']) && $s['type'] === 'international' ? ' (INTL)' : '')
                                        )
                                    ) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>

                        <?php if ($hasSupplierMaster === false): ?>
                            <p class="mt-1 text-[11px] text-amber-600">
                                Supplier master is not yet configured for BizFlow. You can link it later from
                                the <strong>Suppliers</strong> section.
                            </p>
                        <?php elseif (empty($suppliers)): ?>
                            <p class="mt-1 text-[11px] text-rose-500">
                                No suppliers found for this organisation. Please add suppliers under
                                <strong>Suppliers</strong> before creating a purchase.
                            </p>
                        <?php endif; ?>
                    </div>

                    <!-- PO no + dates -->
                    <div class="grid gap-3 md:grid-cols-2">
                        <div>
                            <label class="block text-xs font-medium text-slate-700 mb-1">
                                PO number
                            </label>
                            <input type="text"
                                   name="po_no"
                                   placeholder="Auto or manual (e.g. PO-2025-0001)"
                                   class="block w-full rounded-lg border border-slate-200 px-3 py-2 text-sm
                                          focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-slate-700 mb-1">
                                PO date <span class="text-rose-500">*</span>
                            </label>
                            <input type="date"
                                   name="date"
                                   value="<?= $h($today ?? '') ?>"
                                   required
                                   class="block w-full rounded-lg border border-slate-200 px-3 py-2 text-sm
                                          focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500">
                        </div>
                    </div>

                    <div class="grid gap-3 md:grid-cols-2">
                        <div>
                            <label class="block text-xs font-medium text-slate-700 mb-1">
                                Expected arrival (ETA)
                            </label>
                            <input type="date"
                                   name="expected_date"
                                   class="block w-full rounded-lg border border-slate-200 px-3 py-2 text-sm
                                          focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-slate-700 mb-1">
                                Supplier / external reference
                            </label>
                            <input type="text"
                                   name="external_ref"
                                   placeholder="Supplier PI no, RFQ ref, framework contract, etc."
                                   class="block w-full rounded-lg border border-slate-200 px-3 py-2 text-sm
                                          focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500">
                        </div>
                    </div>
                </div>

                <!-- Currency + inventory flags -->
                <div class="space-y-4">
                    <div class="grid gap-3 md:grid-cols-2">
                        <div>
                            <label class="block text-xs font-medium text-slate-700 mb-1">
                                Currency
                            </label>
                            <input type="text"
                                   name="currency"
                                   value="BDT"
                                   class="block w-full rounded-lg border border-slate-200 px-3 py-2 text-sm
                                          focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-slate-700 mb-1">
                                FX rate (to BDT)
                            </label>
                            <input type="number"
                                   name="fx_rate"
                                   step="0.000001"
                                   value="1.000000"
                                   class="block w-full rounded-lg border border-slate-200 px-3 py-2 text-sm
                                          focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500">
                        </div>
                    </div>

                    <div class="rounded-xl border border-slate-200 bg-white p-3">
                        <h2 class="mb-2 text-xs font-semibold uppercase tracking-wide text-slate-600">
                            Inventory behaviour
                        </h2>
                        <div class="space-y-2 text-xs">
                            <label class="flex items-start gap-2">
                                <input type="checkbox"
                                       name="is_inventory_impact"
                                       value="1"
                                       class="mt-[3px] h-3.5 w-3.5 text-emerald-600 focus:ring-emerald-500"
                                       checked>
                                <span>
                                    <span class="font-medium text-slate-800">Affects inventory</span><br>
                                    <span class="text-slate-500">Will require GRN and update stock balances.</span>
                                </span>
                            </label>
                            <label class="flex items-start gap-2">
                                <input type="checkbox"
                                       name="is_expense_only"
                                       value="1"
                                       class="mt-[3px] h-3.5 w-3.5 text-emerald-600 focus:ring-emerald-500">
                                <span>
                                    <span class="font-medium text-slate-800">Expense-only</span><br>
                                    <span class="text-slate-500">Treat as services / OPEX even if items exist.</span>
                                </span>
                            </label>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Line items (single row, with catalog OR new item option) -->
        <section class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm space-y-3">
            <div class="flex items-center justify-between gap-2 mb-1">
                <h2 class="text-sm font-semibold text-slate-800 flex items-center gap-2">
                    <span class="inline-flex h-6 w-6 items-center justify-center rounded-full bg-emerald-600 text-[11px] text-white">
                        PO
                    </span>
                    Line item
                </h2>
                <span class="text-[11px] text-slate-500">
                    For now a single line only (just to stabilise saving and auto-calc).
                </span>
            </div>

            <div class="grid gap-3 md:grid-cols-5">
                <!-- Kind -->
                <div>
                    <label class="block text-xs font-medium text-slate-700 mb-1">
                        Type
                    </label>
                    <select name="items[0][kind]"
                            class="block w-full rounded-lg border border-slate-200 px-3 py-2 text-xs
                                   focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500">
                        <option value="item">Inventory item</option>
                        <option value="service">Service / non-stock</option>
                        <option value="other">Ad-hoc / non-catalog</option>
                    </select>
                </div>

                <!-- Inventory item select -->
                <div class="md:col-span-2">
                    <label class="block text-xs font-medium text-slate-700 mb-1">
                        Item from inventory
                    </label>
                    <select name="items[0][item_id]"
                            class="block w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-xs
                                   focus:border-emerald-600 focus:ring-emerald-600">
                        <option value="">
                            <?=
                                !empty($items)
                                    ? $h('Select item… (or leave empty for new item)')
                                    : $h('No items yet — add them in Items.')
                            ?>
                        </option>
                        <?php foreach ($items as $it): ?>
                            <option value="<?= (int)$it['id'] ?>">
                                <?= $h($it['name'] ?? '') ?>
                                <?php if (!empty($it['code'])): ?>
                                    (<?= $h($it['code']) ?>)
                                <?php endif; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?php if ($hasItemMaster === false): ?>
                        <p class="mt-1 text-[11px] text-amber-600">
                            Item master is not yet configured for BizFlow. You can connect it later from the
                            <strong>Items</strong> section.
                        </p>
                    <?php elseif (empty($items)): ?>
                        <p class="mt-1 text-[11px] text-rose-500">
                            No items found for this organisation. Please add stock items in
                            <strong>Items</strong> first.
                        </p>
                    <?php endif; ?>
                </div>

                <!-- New / adhoc item name -->
                <div class="md:col-span-2">
                    <label class="block text-xs font-medium text-slate-700 mb-1">
                        Item name / description (for new or override)
                    </label>
                    <input type="text"
                           name="items[0][item_name]"
                           placeholder="Type if item not in inventory, or override name/spec…"
                           class="block w-full rounded-lg border border-slate-200 px-3 py-2 text-xs
                                  focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500">
                </div>
            </div>

            <div class="grid gap-3 md:grid-cols-4 mt-2">
                <div>
                    <label class="block text-xs font-medium text-slate-700 mb-1">
                        Unit
                    </label>
                    <input type="text"
                           name="items[0][unit]"
                           value="pcs"
                           class="block w-full rounded-lg border border-slate-200 px-3 py-2 text-xs text-center
                                  focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500">
                </div>

                <div>
                    <label class="block text-xs font-medium text-slate-700 mb-1">
                        Qty
                    </label>
                    <input type="number"
                           id="item_qty_0"
                           name="items[0][qty_ordered]"
                           step="0.001"
                           min="0"
                           class="block w-full rounded-lg border border-slate-200 px-3 py-2 text-xs text-right
                                  focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500"
                           oninput="bizPurchaseRecalc()">
                </div>

                <div>
                    <label class="block text-xs font-medium text-slate-700 mb-1">
                        Unit price (BDT)
                    </label>
                    <input type="number"
                           id="item_price_0"
                           name="items[0][unit_price]"
                           step="0.01"
                           min="0"
                           class="block w-full rounded-lg border border-slate-200 px-3 py-2 text-xs text-right
                                  focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500"
                           oninput="bizPurchaseRecalc()">
                </div>

                <div>
                    <label class="block text-xs font-medium text-slate-700 mb-1">
                        Line total (BDT)
                    </label>
                    <input type="text"
                           id="item_total_0"
                           name="items[0][line_total]"
                           readonly
                           class="block w-full rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-xs text-right text-slate-700">
                </div>
            </div>

            <div class="mt-3 flex items-center justify-end">
                <div class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-1.5 text-xs">
                    <span class="text-[11px] text-slate-500 mr-1">Estimated total:</span>
                    <span id="purchase_estimated_total"
                          class="text-sm font-semibold text-slate-900">0.00 BDT</span>
                </div>
            </div>
        </section>

        <!-- Notes + terms + submit -->
        <section class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm space-y-4">
            <div class="grid gap-4 md:grid-cols-3">
                <!-- Internal notes -->
                <div class="md:col-span-1">
                    <label class="block text-xs font-medium text-slate-700 mb-1">
                        Internal notes
                    </label>
                    <textarea name="notes"
                              rows="4"
                              placeholder="Any extra instructions, budget reference, or internal comments…"
                              class="block w-full rounded-lg border border-slate-200 px-3 py-2 text-sm
                                     focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500"></textarea>
                    <p class="mt-1 text-[11px] text-slate-500">
                        For your team only — not printed on supplier copy.
                    </p>
                </div>

                <!-- General terms -->
                <div class="md:col-span-1">
                    <label class="block text-xs font-medium text-slate-700 mb-1">
                        General terms &amp; conditions
                    </label>
                    <textarea name="terms_general"
                              rows="4"
                              placeholder="Payment terms, validity, warranty, penalties, dispute resolution, etc."
                              class="block w-full rounded-lg border border-slate-200 px-3 py-2 text-sm
                                     focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500"></textarea>
                    <p class="mt-1 text-[11px] text-slate-500">
                        Long-lived standard terms you expect suppliers to follow (valid up to 2035 and beyond).
                    </p>
                </div>

                <!-- Delivery & shipping -->
                <div class="md:col-span-1">
                    <label class="block text-xs font-medium text-slate-700 mb-1">
                        Delivery &amp; shipping conditions
                    </label>
                    <textarea name="terms_delivery"
                              rows="4"
                              placeholder="Delivery timeline, location, packaging, transport responsibility, insurance, etc."
                              class="block w-full rounded-lg border border-slate-200 px-3 py-2 text-sm
                                     focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500"></textarea>
                    <p class="mt-1 text-[11px] text-slate-500">
                        Conditions related to delivery, shipment, partial delivery, damages, inspection and acceptance.
                    </p>
                </div>
            </div>

            <div class="flex flex-col gap-2 md:flex-row md:items-center md:justify-between">
                <p class="text-[11px] text-slate-500">
                    This purchase will be created in <strong>Draft</strong> status. Receive / GRN and
                    inventory / accounting posting happen from the purchase details page. LC purchases
                    will be created from the LC workflow.
                </p>
                <div class="flex justify-end gap-2">
                    <a href="<?= $h($module_base . '/purchases') ?>"
                       class="inline-flex items-center gap-1 rounded-lg border border-slate-200 px-3 py-2 text-xs font-medium text-slate-700 hover:bg-slate-50">
                        <i class="fa fa-rotate-left text-[11px]" aria-hidden="true"></i>
                        <span>Cancel</span>
                    </a>

                    <!-- Normal HTML submit -->
                    <button type="submit"
                            class="inline-flex items-center gap-2 rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-emerald-700 focus:outline-none focus:ring-2 focus:ring-emerald-600 focus:ring-offset-1">
                        <i class="fa fa-check-circle text-xs" aria-hidden="true"></i>
                        <span>Save purchase</span>
                    </button>
                </div>
            </div>
        </section>

        <!-- How to use this page -->
        <section class="rounded-xl border border-dashed border-emerald-200 bg-emerald-50/40 p-4 text-xs text-slate-700 space-y-1">
            <h2 class="text-xs font-semibold text-emerald-800 mb-1">How to use this page</h2>
            <ul class="list-disc pl-4 space-y-1">
                <li>Select the <strong>supplier</strong> and set <strong>PO date</strong> and (optionally) PO number and ETA.</li>
                <li>Choose an <strong>inventory item</strong> from the dropdown, or leave it empty and type a
                    <strong>new / adhoc item name</strong> in the text field.</li>
                <li>Set <strong>unit</strong>, <strong>quantity</strong> and <strong>unit price</strong>;
                    the line total and estimated total in BDT will auto-calculate.</li>
                <li>Use the three note blocks for internal notes, general terms, and delivery &amp; shipping
                    conditions (these are designed to remain stable up to 2035 without frequent changes).</li>
                <li>Click <strong>Save purchase</strong> to submit. If you see a CSRF error, just refresh the page
                    and try again.</li>
            </ul>
        </section>
    </form>
</div>

<script>
// Tiny vanilla JS helper: auto-calc line total & estimated total
function bizPurchaseRecalc() {
    var qtyEl   = document.getElementById('item_qty_0');
    var priceEl = document.getElementById('item_price_0');
    var totalEl = document.getElementById('item_total_0');
    var estEl   = document.getElementById('purchase_estimated_total');

    if (!qtyEl || !priceEl || !totalEl || !estEl) return;

    var qty   = parseFloat(qtyEl.value || '0');
    var price = parseFloat(priceEl.value || '0');
    if (isNaN(qty))   qty   = 0;
    if (isNaN(price)) price = 0;

    var total = qty * price;
    if (isNaN(total)) total = 0;

    var formatted = total.toFixed(2);

    totalEl.value         = formatted;
    estEl.textContent     = formatted + ' BDT';
}
</script>