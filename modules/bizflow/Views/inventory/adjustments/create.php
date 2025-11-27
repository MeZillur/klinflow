<?php
/**
 * BizFlow — Inventory → Stock adjustment (create)
 *
 * Expected vars:
 * @var array  $org
 * @var string $module_base
 * @var string $title
 * @var array  $warehouses   // ['id','code','name']
 * @var string $next_no      // optional adjustment number
 * @var string $today        // Y-m-d
 * @var string $csrf_token   // optional
 * @var array  $endpoints    // ['items' => '...']
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
        mode: 'delta', // 'delta' or 'recount'
        rows: [
            {item_id:'', code:'', name:'', unit:'', current_qty:null, new_qty:null, delta:null, reason:''}
        ],
        addRow() { this.rows.push({item_id:'', code:'', name:'', unit:'', current_qty:null, new_qty:null, delta:null, reason:''}); this.$nextTick(()=>{ if(window.KF && KF.rescan) KF.rescan(document); }); },
        removeRow(i) { if(this.rows.length>1) this.rows.splice(i,1); },
        updateDelta(row) {
            if(this.mode==='delta') return;
            const cur = parseFloat(row.current_qty ?? 0);
            const neu = parseFloat(row.new_qty ?? 0);
            if (!isNaN(cur) && !isNaN(neu)) row.delta = (neu - cur).toFixed(2);
        }
     }">

    <!-- Header + tabs -->
    <header class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
        <div class="space-y-1">
            <div class="text-xs text-slate-500 flex items-center gap-1">
                <a href="<?= $h($module_base.'/inventory') ?>" class="hover:underline">Inventory</a>
                <span>/</span>
                <span class="font-medium text-slate-700">New adjustment</span>
            </div>
            <h1 class="text-2xl md:text-3xl font-semibold tracking-tight">
                <?= $h($title ?? 'New stock adjustment') ?>
            </h1>
            <p class="text-sm text-slate-500">
                Correct physical counting differences while keeping a clean audit trail for <?= $h($orgName ?: 'your organisation') ?>.
            </p>
        </div>

        <!-- App tabs (same as others, Inventory active) -->
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
    <form method="post" action="<?= $h($module_base.'/inventory/adjustments') ?>" class="space-y-6">
        <?php if (!empty($csrf_token)): ?>
            <input type="hidden" name="_token" value="<?= $h($csrf_token) ?>">
        <?php endif; ?>

        <!-- Meta + warehouse -->
        <section class="grid gap-4 rounded-xl border border-slate-200 bg-white p-4 shadow-sm md:grid-cols-[minmax(0,2fr),minmax(0,1.4fr)]">
            <div class="space-y-3">
                <h2 class="text-sm font-semibold text-slate-800 flex items-center gap-2">
                    <span class="inline-flex h-6 w-6 items-center justify-center rounded-full bg-emerald-600 text-[11px] text-white">
                        1
                    </span>
                    Adjustment details
                </h2>

                <div class="grid gap-3 sm:grid-cols-2">
                    <div>
                        <label class="block text-xs font-medium text-slate-600 mb-1">Adjustment no.</label>
                        <input type="text"
                               name="adjustment_no"
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
                            <option value="cycle_count">Cycle count difference</option>
                            <option value="damage">Damage / expiry</option>
                            <option value="theft_loss">Theft / loss</option>
                            <option value="conversion">Unit conversion</option>
                            <option value="initial_balance">Initial balance setup</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-600 mb-1">Reference</label>
                        <input type="text"
                               name="reference"
                               placeholder="Optional reference (e.g. COUNT-2025-01)"
                               class="block w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-emerald-500 focus:ring-emerald-500">
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-medium text-slate-600 mb-1">Notes</label>
                    <textarea name="notes"
                              rows="3"
                              placeholder="Explain why this adjustment is needed. This appears in stock history."
                              class="block w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-emerald-500 focus:ring-emerald-500"></textarea>
                </div>
            </div>

            <div class="space-y-4">
                <div>
                    <h2 class="text-sm font-semibold text-slate-800 mb-2 flex items-center gap-2">
                        <span class="inline-flex h-6 w-6 items-center justify-center rounded-full bg-slate-900 text-[11px] text-white">
                            2
                        </span>
                        Warehouse & mode
                    </h2>

                    <label class="block text-xs font-medium text-slate-600 mb-1">Warehouse</label>
                    <select name="warehouse_id"
                            class="mb-3 block w-full rounded-lg border border-slate-200 px-3 py-2 text-sm bg-white focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500"
                            required>
                        <option value="">Select warehouse</option>
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

                    <fieldset class="space-y-2 text-xs text-slate-600">
                        <legend class="font-medium text-slate-700 mb-1">Adjustment mode</legend>
                        <label class="flex items-center gap-2">
                            <input type="radio" name="mode" value="delta" class="h-3.5 w-3.5 border-slate-300 text-emerald-600 focus:ring-emerald-500"
                                   x-model="mode" checked>
                            <span>
                                <span class="font-medium">Delta</span> — enter the quantity to increase/decrease directly.
                            </span>
                        </label>
                        <label class="flex items-center gap-2">
                            <input type="radio" name="mode" value="recount" class="h-3.5 w-3.5 border-slate-300 text-emerald-600 focus:ring-emerald-500"
                                   x-model="mode">
                            <span>
                                <span class="font-medium">Recount</span> — enter <strong>current counted quantity</strong>; system will compute the delta.
                            </span>
                        </label>
                    </fieldset>
                </div>

                <p class="text-[11px] text-slate-500">
                    Adjustments will hit your inventory and (optionally later) the GL.
                    Use them carefully and always with a clear reason for 2035-grade audit readiness.
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
                    Lines to adjust
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
                        <th class="px-3 py-2 text-right w-24" x-show="mode==='recount'">Current</th>
                        <th class="px-3 py-2 text-right w-24" x-show="mode==='recount'">New counted</th>
                        <th class="px-3 py-2 text-right w-24" x-show="mode==='delta'">Delta qty</th>
                        <th class="px-3 py-2 text-left w-20">Unit</th>
                        <th class="px-3 py-2 text-left w-40">Line reason</th>
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

                            <!-- Current qty (recount mode) -->
                            <td class="px-3 py-2 text-right align-top" x-show="mode==='recount'">
                                <input type="number"
                                       step="0.01"
                                       :name="`items[${idx}][current_qty]`"
                                       x-model="row.current_qty"
                                       @input="updateDelta(row)"
                                       class="block w-full rounded-lg border border-slate-200 px-2 py-1.5 text-right text-sm font-mono focus:border-emerald-500 focus:ring-emerald-500"
                                       placeholder="0.00">
                            </td>

                            <!-- New counted qty (recount mode) -->
                            <td class="px-3 py-2 text-right align-top" x-show="mode==='recount'">
                                <input type="number"
                                       step="0.01"
                                       :name="`items[${idx}][new_qty]`"
                                       x-model="row.new_qty"
                                       @input="updateDelta(row)"
                                       class="block w-full rounded-lg border border-slate-200 px-2 py-1.5 text-right text-sm font-mono focus:border-emerald-500 focus:ring-emerald-500"
                                       placeholder="0.00">
                            </td>

                            <!-- Delta qty (delta mode, or readonly in recount) -->
                            <td class="px-3 py-2 text-right align-top" x-show="mode==='delta'">
                                <input type="number"
                                       step="0.01"
                                       :name="`items[${idx}][delta_qty]`"
                                       x-model="row.delta"
                                       class="block w-full rounded-lg border border-slate-200 px-2 py-1.5 text-right text-sm font-mono focus:border-emerald-500 focus:ring-emerald-500"
                                       placeholder="+/- qty">
                            </td>

                            <!-- In recount mode, show computed delta but read-only -->
                            <td class="px-3 py-2 text-right align-top" x-show="mode==='recount'">
                                <input type="text"
                                       :value="row.delta"
                                       readonly
                                       class="block w-full rounded-lg border border-slate-100 bg-slate-50 px-2 py-1.5 text-right text-sm font-mono text-slate-700">
                                <input type="hidden" :name="`items[${idx}][delta_qty]`" :value="row.delta">
                            </td>

                            <!-- Unit -->
                            <td class="px-3 py-2 align-top text-xs text-slate-600">
                                <span x-text="row.unit || '—'"></span>
                            </td>

                            <!-- Line reason -->
                            <td class="px-3 py-2 align-top">
                                <input type="text"
                                       :name="`items[${idx}][reason]`"
                                       x-model="row.reason"
                                       placeholder="Short explanation for this line"
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
                Positive deltas will <strong>increase</strong> stock; negative deltas will
                <strong>decrease</strong> stock in the selected warehouse, valued at moving-average cost in BDT.
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
                    <i class="fa fa-check-circle text-xs"></i>
                    <span>Post adjustment</span>
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
            <li>Choose the correct <strong>warehouse</strong> and a clear <strong>reason</strong> for this adjustment.</li>
            <li>In <strong>Delta</strong> mode, type the quantity to add (+) or remove (–) for each item.</li>
            <li>In <strong>Recount</strong> mode, enter the current system quantity and your new counted quantity;
                BizFlow will compute the difference automatically.</li>
            <li>Use precise <strong>line reasons</strong> so auditors in 2035 can understand every movement.</li>
            <li>Post the adjustment only after double-checking; it directly impacts stock and future costing.</li>
        </ul>
    </section>
</div>