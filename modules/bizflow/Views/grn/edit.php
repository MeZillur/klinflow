<?php
declare(strict_types=1);

/** @var array  $org */
/** @var string $module_base */
/** @var array  $grn */
/** @var string $title */

$h = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');

$module_base = rtrim($module_base ?? '/apps/bizflow', '/');
$orgName     = trim((string)($org['name'] ?? '')) ?: 'your organisation';
$id          = (int)($grn['id'] ?? 0);
?>
<div class="space-y-6">

    <!-- Header + tabs -->
    <header class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
        <div>
            <h1 class="text-2xl md:text-3xl font-semibold tracking-tight">
                <?= $h($title ?? 'Edit GRN') ?>
            </h1>
            <p class="text-sm text-slate-500">
                Adjust Goods Receipt Note <?= $h($grn['grn_no'] ?? '') ?> for <?= $h($orgName) ?>.
            </p>
        </div>

        <?php
        $tabs = [
            ['GRN register', $module_base.'/grn',              false],
            ['New GRN',      $module_base.'/grn/create',       false],
        ];
        ?>
        <nav class="flex flex-wrap justify-end gap-1 text-sm">
            <?php foreach ($tabs as [$label, $url, $active]): ?>
                <a href="<?= $h($url) ?>"
                   class="inline-flex items-center gap-1 rounded-full px-3 py-1 border text-xs md:text-[13px]
                          border-slate-200 text-slate-600 hover:bg-slate-50">
                    <span><?= $h($label) ?></span>
                </a>
            <?php endforeach; ?>
        </nav>
    </header>

    <div class="grid gap-6 lg:grid-cols-[2.1fr,1.1fr]">

        <!-- LEFT: GRN form (same as create, but posts to /grn/{id}) -->
        <section class="rounded-2xl border border-slate-200 bg-white shadow-sm">
            <form method="post" action="<?= $h($module_base.'/grn/'.$id) ?>" class="space-y-5 px-4 py-4">

                <!-- Header block -->
                <div class="grid gap-4 md:grid-cols-3">
                    <div class="md:col-span-1">
                        <label class="block text-xs font-medium text-slate-700">
                            GRN No
                        </label>
                        <input type="text"
                               name="grn_no"
                               value="<?= $h($grn['grn_no'] ?? '') ?>"
                               class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-1.5 text-xs focus:border-emerald-600 focus:outline-none focus:ring-1 focus:ring-emerald-600">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-700">
                            GRN Date
                        </label>
                        <input type="date"
                               name="grn_date"
                               value="<?= $h($grn['grn_date'] ?? '') ?>"
                               class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-1.5 text-xs focus:border-emerald-600 focus:outline-none focus:ring-1 focus:ring-emerald-600">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-700">
                            Currency
                        </label>
                        <?php $curr = $grn['currency'] ?? 'USD'; ?>
                        <select name="currency"
                                class="mt-1 w-full rounded-lg border border-slate-200 px-2 py-1.5 text-xs focus:border-emerald-600 focus:outline-none focus:ring-1 focus:ring-emerald-600">
                            <?php foreach (['USD','BDT','EUR','CNY','INR'] as $cOpt): ?>
                                <option value="<?= $h($cOpt) ?>" <?= $curr === $cOpt ? 'selected' : '' ?>>
                                    <?= $h($cOpt) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <!-- Source doc -->
                <div class="grid gap-4 md:grid-cols-3">
                    <div>
                        <label class="block text-xs font-medium text-slate-700">
                            Source
                        </label>
                        <?php $refType = $grn['ref_type'] ?? 'manual'; ?>
                        <select name="ref_type"
                                class="mt-1 w-full rounded-lg border border-slate-200 px-2 py-1.5 text-xs focus:border-emerald-600 focus:outline-none focus:ring-1 focus:ring-emerald-600">
                            <option value="manual"   <?= $refType === 'manual'   ? 'selected' : '' ?>>Manual</option>
                            <option value="purchase" <?= $refType === 'purchase' ? 'selected' : '' ?>>Local Purchase</option>
                            <option value="lc"       <?= $refType === 'lc'       ? 'selected' : '' ?>>LC Shipment</option>
                        </select>
                    </div>
                    <div class="md:col-span-2">
                        <label class="block text-xs font-medium text-slate-700">
                            Source reference (PO/LC/Manual ref)
                        </label>
                        <input type="text"
                               name="ref_no"
                               value="<?= $h($grn['ref_no'] ?? '') ?>"
                               class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-1.5 text-xs focus:border-emerald-600 focus:outline-none focus:ring-1 focus:ring-emerald-600">
                    </div>
                </div>

                <!-- Supplier / warehouse -->
                <div class="grid gap-4 md:grid-cols-2">
                    <div>
                        <label class="block text-xs font-medium text-slate-700">
                            Supplier name
                        </label>
                        <input type="text"
                               name="supplier_name"
                               value="<?= $h($grn['supplier_name'] ?? '') ?>"
                               class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-1.5 text-xs focus:border-emerald-600 focus:outline-none focus:ring-1 focus:ring-emerald-600">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-700">
                            Warehouse / location
                        </label>
                        <input type="text"
                               name="warehouse_name"
                               value="<?= $h($grn['warehouse_name'] ?? '') ?>"
                               class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-1.5 text-xs focus:border-emerald-600 focus:outline-none focus:ring-1 focus:ring-emerald-600">
                    </div>
                </div>

                <!-- Notes -->
                <div>
                    <label class="block text-xs font-medium text-slate-700">
                        Notes
                    </label>
                    <textarea name="notes"
                              rows="2"
                              class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-1.5 text-xs focus:border-emerald-600 focus:outline-none focus:ring-1 focus:ring-emerald-600"><?= $h($grn['notes'] ?? '') ?></textarea>
                </div>

                <!-- Placeholder for line items (same structure as create; you can later hydrate from items table) -->
                <div class="rounded-xl border border-dashed border-slate-200 bg-slate-50 px-3 py-3 text-[11px] text-slate-500">
                    Line item editing will use <code>biz_grn_items</code> once persistence is turned on.
                    For now, this form is preview-only.
                </div>

                <!-- Footer buttons -->
                <div class="flex items-center justify-end gap-3 pt-2">
                    <a href="<?= $h($module_base.'/grn/'.$id) ?>"
                       class="rounded-lg border border-slate-200 px-3 py-1.5 text-[11px] text-slate-600 hover:bg-slate-50">
                        Cancel
                    </a>
                    <button type="submit"
                            class="inline-flex items-center gap-1 rounded-lg bg-slate-900 px-3 py-2 text-xs font-medium text-white shadow-sm hover:bg-slate-800">
                        <span>Save changes (preview)</span>
                    </button>
                </div>
            </form>
        </section>

        <!-- RIGHT: How to / notes -->
        <aside class="space-y-4">
            <section class="rounded-2xl border border-dashed border-emerald-400 bg-emerald-50/60 px-4 py-4 text-sm text-slate-800">
                <h2 class="mb-2 flex items-center gap-2 text-sm font-semibold text-emerald-900">
                    <span class="inline-flex h-6 w-6 items-center justify-center rounded-full bg-emerald-600 text-[11px] text-white">
                        ?
                    </span>
                    How to use this page
                </h2>
                <ul class="ml-6 list-disc space-y-1 text-[13px]">
                    <li>Use this screen for minor corrections to GRN headers (date, reference, warehouse, notes).</li>
                    <li>Once item-level persistence is ready, quantities and rates will also be editable here.</li>
                    <li>After verifying data, go back to the detail page and <strong>post</strong> to update stock.</li>
                </ul>
            </section>
        </aside>
    </div>
</div>