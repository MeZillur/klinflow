<?php
/**
 * BizFlow — Inventory → Transfer stock (create)
 *
 * Expected vars (from controller):
 * @var array  $org
 * @var string $module_base
 * @var string $title
 * @var array  $warehouses   // each: ['id','code','name']
 * @var string $next_no      // optional: next transfer number
 * @var string $today        // optional: Y-m-d
 * @var string $csrf_token   // optional
 * @var array  $endpoints    // optional: ['items' => '/t/{slug}/apps/bizflow/items.lookup.json']
 */

$h = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');

$module_base = rtrim($module_base ?? ($base ?? '/apps/bizflow'), '/');
$orgName     = (string)($org['name'] ?? '');

$warehouses  = $warehouses ?? [];
$next_no     = $next_no ?? '';
$today       = $today   ?? date('Y-m-d');

$endpoints   = $endpoints ?? [];
$epItems     = $endpoints['items'] ?? ($module_base.'/items.lookup.json');
?>
<div class="space-y-6"
     x-data="{
        rows: [
            {item_id:'', code:'', name:'', unit:'', from_qty:'', to_qty:'', note:''}
        ],
        addRow() { this.rows.push({item_id:'', code:'', name:'', unit:'', from_qty:'', to_qty:'', note:''}); this.$nextTick(()=>{ if(window.KF && KF.rescan) KF.rescan(document); }); },
        removeRow(i) { if(this.rows.length>1) this.rows.splice(i,1); },
     }">

    <!-- Header + tabs -->
    <header class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
        <div class="space-y-1">
            <div class="text-xs text-slate-500 flex items-center gap-1">
                <a href="<?= $h($module_base.'/inventory') ?>" class="hover:underline">Inventory</a>
                <span>/</span>
                <span class="font-medium text-slate-700">Transfer stock</span>
            </div>
            <h1 class="text-2xl md:text-3xl font-semibold tracking-tight">
                <?= $h($title ?? 'Transfer stock') ?>
            </h1>
            <p class="text-sm text-slate-500">
                Move quantities between warehouses with full traceability for <?= $h($orgName ?: 'your organisation') ?>.
            </p>
        </div>

        <!-- App tabs -->
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
            $current = $module_base.'/inventory';
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

    <!-- Main form -->
    <form method="post" action="<?= $h($module_base.'/inventory/transfers') ?>" class="space-y-6">
        <?php if (!empty($csrf_token)): ?>
            <input type="hidden" name="_token" value="<?= $h($csrf_token) ?>">
        <?php endif; ?>

        <!-- Doc meta -->
        <section class="grid gap-4 rounded-xl border border-slate-200 bg-white p-4 shadow-sm md:grid-cols-[minmax(0,2fr),minmax(0,1.4fr)]">
            <div class="space-y-3">
                <h2 class="text-sm font-semibold text-slate-800 flex items-center gap-2">
                    <span class="inline-flex h-6 w-6 items-center justify-center rounded-full bg-emerald-600 text-[11px] text-white">
                        1
                    </span>
                    Transfer details
                </h2>

                <div class="grid gap-3 sm:grid-cols-2">
                    <div>
                        <label class="block text-xs font-medium text-slate-600 mb-1">Transfer no.</label>
                        <input type="text"
                               name="transfer_no"
                               value="<?= $h($next_no) ?>"
                               placeholder="Auto or manual"
                               class="block w-full rounded-lg border border-slate-200 px-3 py-2 text-sm font-mono focus:border-emerald-500 focus:ring-emerald-500">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-600 mb-1">Date</label>
                        <input type="date"
                               name="date"
                               value="<?= $h($today) ?>"
                               class="block w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-emerald-500 focus:ring-emerald-500">
                    </div>
                </div>

                <div class="grid gap-3 sm:grid-cols-2">
                    <div>
                        <label class="block text-xs font-medium text-slate-600 mb-1">Reason</label>
                        <select name="reason"
                                class="block w-full rounded-lg border border-slate-200 px-3 py-2 text-sm bg-white focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500">
                            <option value="">Select reason</option>
                            <option value="replenishment">Replenishment between warehouses</option>
                            <option value="relocation">Relocation / bin change</option>
                            <option value="store_use">Store use / showroom</option>
                            <option value="correction">Correction of wrong receipt</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-600 mb-1">Reference</label>
                        <input type="text"
                               name="reference"
                               placeholder="Optional reference (e.g. SO-2025-001)"
                               class="block w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-emerald-500 focus:ring-emerald-500">
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-medium text-slate-600 mb-1">Notes</label>
                    <textarea name="notes"
                              rows="3"
                              placeholder="Internal notes for this transfer (visible in inventory history)."
                              class="block w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-emerald-500 focus:ring-emerald-500"></textarea>
                </div>
            </div>

            <!-- Warehouses -->
            <div class="space-y-3">
                <h2 class="text-sm font-semibold text-slate-800 flex items-center gap-2">
                    <span class="inline-flex h-6 w-6 items-center justify-center rounded-full bg-slate-900 text-[11px] text-white">
                        2
                    </span>
                    Warehouses
                </h2>

                <div class="grid gap-3">
                    <div>
                        <label class="block text-xs font-medium text-slate-600 mb-1">From warehouse</label>
                        <select name="from_warehouse_id"
                                class="block w-full rounded-lg border border-slate-200 px-3 py-2 text-sm bg-white focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500"
                                required>
                            <option value="">Select source</option>
                            <?php foreach ($warehouses as $wh): ?>
                                <?php
                                $id   = (string)($wh['id'] ?? '');
                                $code = $wh['code'] ?? '';
                                $name = $wh['name'] ?? '';
                                $label = trim($code !== '' ? "$code — $name" : $name);
                                ?>
                                <option value="<?= $h($id) ?>"><?= $h($label ?: 'Warehouse') ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-medium text-slate-600 mb-1">To warehouse</label>
                        <select name="to_warehouse_id"
                                class="block w-full rounded-lg border border-slate-200 px-3 py-2 text-sm bg-white focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500"
                                required>
                            <option value="">Select destination</option>
                            <?php foreach ($warehouses as $wh): ?>
                                <?php
                                $id   = (string)($wh['id'] ?? '');
                                $code = $wh['code'] ?? '';
                                $name = $wh['name'] ?? '';
                                $label = trim($code !== '' ? "$code — $name" : $name);
                                ?>
                                <option value="<?= $h($id) ?>"><?= $h($label ?: 'Warehouse') ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <p class="text-[11px] text-slate-500">
                    Transfers will reduce stock in the <strong>from</strong> warehouse and increase it in the
                    <strong>to</strong> warehouse using moving-average cost.
                </p>
            </div>
        </section>

        <!-- Line items -->
        <section class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm space-y-3">
            <div class="flex items-center justify-between gap-2">
                <h2 class="text-sm font-semibold text-slate-800 flex items-center gap-2">
                    <span class="inline-flex h-6 w-6 items-center justify-center rounded-full bg-emerald-600 text-[11px] text-white">
                        3
                    </span>
                    Items in this transfer
                </h2>
                <button type="button"
                        class="inline-flex items-center gap-1 rounded-lg border border-emerald-600 bg-emerald-50 px-2.5 py-1.5 text-xs font-medium text-emerald-700 hover:bg-emerald-100"
                        @click="addRow">
                    <i class="fa fa-plus text-[10px]"></i>
                    <span>Add row</span>
                </button>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50">
                    <tr class="text-xs font-semibold uppercase tracking-wide text-slate-500">
                        <th class="px-3 py-2 text-left w-[26rem]">Item</th>
                        <th class="px-3 py-2 text-right w-28">Qty to move</th>
                        <th class="px-3 py-2 text-left w-20">Unit</th>
                        <th class="px-3 py-2 text-left w-40">Note</th>
                        <th class="px-3 py-2 text-center w-10">#</th>
                    </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 bg-white">
                    <template x-for="(row, idx) in rows" :key="idx">
                        <tr class="align-top hover:bg-emerald-50/40">
                            <!-- Item lookup -->
                            <td class="px-3 py-2">
                                <div class="space-y-1">
                                    <input type="hidden" :name="`items[${idx}][item_id]`" x-model="row.item_id">

                                    <input type="text"
                                           class="kf-lookup block w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-emerald-500 focus:ring-emerald-500"
                                           placeholder="Search item by code, name, or barcode"
                                           data-kf-lookup="item"
                                           data-kf-endpoint="<?= $h($epItems) ?>"
                                           :data-kf-target-id="`items[${idx}][item_id]`"
                                           :data-kf-target-code="`items[${idx}][code]`"
                                           :data-kf-target-label="`items[${idx}][name]`"
                                           :data-kf-target-extra-unit="`items[${idx}][unit]`"
                                           x-model="row.name">

                                    <div class="flex flex-wrap gap-2 text-[11px] text-slate-500 mt-1">
                                        <span x-show="row.code" class="font-mono">Code: <span x-text="row.code"></span></span>
                                        <span x-show="row.unit">Unit: <span x-text="row.unit"></span></span>
                                    </div>

                                    <input type="hidden" :name="`items[${idx}][code]`" x-model="row.code">
                                    <input type="hidden" :name="`items[${idx}][name]`" x-model="row.name">
                                    <input type="hidden" :name="`items[${idx}][unit]`" x-model="row.unit">
                                </div>
                            </td>

                            <!-- Qty -->
                            <td class="px-3 py-2 text-right align-top">
                                <input type="number"
                                       min="0"
                                       step="0.01"
                                       :name="`items[${idx}][qty]`"
                                       x-model="row.from_qty"
                                       class="block w-full rounded-lg border border-slate-200 px-2 py-1.5 text-right text-sm font-mono focus:border-emerald-500 focus:ring-emerald-500">
                            </td>

                            <!-- Unit (read-only display) -->
                            <td class="px-3 py-2 align-top text-xs text-slate-600">
                                <span x-text="row.unit || '—'"></span>
                            </td>

                            <!-- Note -->
                            <td class="px-3 py-2 align-top">
                                <input type="text"
                                       :name="`items[${idx}][note]`"
                                       x-model="row.note"
                                       placeholder="Optional line note"
                                       class="block w-full rounded-lg border border-slate-200 px-2 py-1.5 text-xs focus:border-emerald-500 focus:ring-emerald-500">
                            </td>

                            <!-- Remove -->
                            <td class="px-3 py-2 text-center align-top">
                                <button type="button"
                                        class="inline-flex h-7 w-7 items-center justify-center rounded-full border border-slate-200 text-slate-400 hover:text-rose-600 hover:border-rose-400"
                                        @click="removeRow(idx)">
                                    <i class="fa fa-xmark text-[11px]"></i>
                                </button>
                            </td>
                        </tr>
                    </template>
                    </tbody>
                </table>
            </div>
        </section>

        <!-- Footer actions -->
        <section class="flex flex-col gap-3 border-t border-slate-200 pt-4 md:flex-row md:items-center md:justify-between">
            <p class="text-xs text-slate-500">
                On save, BizFlow will create a balanced inventory transfer entry so your stock history remains auditable.
            </p>
            <div class="flex flex-wrap justify-end gap-2">
                <a href="<?= $h($module_base.'/inventory') ?>"
                   class="inline-flex items-center gap-1 rounded-lg border border-slate-200 px-3 py-2 text-xs font-medium text-slate-700 hover:bg-slate-50">
                    <i class="fa fa-rotate-left text-[11px]"></i>
                    <span>Cancel</span>
                </a>
                <button type="submit" name="save_draft" value="1"
                        class="inline-flex items-center gap-1 rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs font-medium text-slate-800 hover:bg-slate-50">
                    <i class="fa fa-save text-[11px]"></i>
                    <span>Save draft</span>
                </button>
                <button type="submit"
                        class="inline-flex items-center gap-2 rounded-lg bg-emerald-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-emerald-700 focus:outline-none focus:ring-2 focus:ring-emerald-600 focus:ring-offset-1">
                    <i class="fa fa-paper-plane text-xs"></i>
                    <span>Post transfer</span>
                </button>
            </div>
        </section>
    </form>

    <!-- How to use this page -->
    <section class="rounded-xl border border-dashed border-emerald-400 bg-emerald-50/60 px-4 py-4 text-sm text-slate-800">
        <h2 class="mb-2 flex items-center gap-2 text-sm font-semibold text-emerald-900">
            <span class="inline-flex h-6 w-6 items-center justify-center rounded-full bg-emerald-600 text-[11px] text-white">
                ?
            </span>
            How to use this page
        </h2>
        <ul class="ml-6 list-disc space-y-1 text-[13px]">
            <li>Select a <strong>from</strong> and <strong>to</strong> warehouse to define the movement path.</li>
            <li>Add items using the global <strong>KF.lookup</strong> field and enter quantities to move.</li>
            <li>Use meaningful <strong>reasons</strong> and <strong>references</strong> to keep audit trails clear for 2035-grade compliance.</li>
            <li>Save as <strong>draft</strong> if you’re planning transfers, or <strong>Post transfer</strong> to immediately impact stock.</li>
            <li>All transfers stay scoped to the current organisation and appear in inventory history and warehouse ledgers.</li>
        </ul>
    </section>
</div>