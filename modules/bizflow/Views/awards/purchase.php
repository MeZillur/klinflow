<?php
/** @var array       $award */
/** @var array       $lines */
/** @var array       $suppliers */
/** @var array       $org */
/** @var string      $module_base */
/** @var string      $title */
/** @var int|null    $selected_supplier_id */
/** @var string|null $error */

$h = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');

$module_base = rtrim($module_base ?? ($base ?? '/apps/bizflow'), '/');
$orgName     = $org['name'] ?? '';
$currency    = $award['currency'] ?? 'BDT';
$awardId     = (int)($award['id'] ?? 0);
$awardNo     = $award['award_no'] ?? ('#'.$awardId);

// --- IMPORTANT: keep full current URL as form action (preserves ?_debug=1 etc.) ---
$reqUri = $_SERVER['REQUEST_URI'] ?? '';
if ($reqUri === '') {
    $reqUri = $module_base . '/awards/' . $awardId . '/purchase';
}
$action = $reqUri;
?>
<div class="space-y-6">
    <!-- Header -->
    <header class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
        <div class="space-y-1">
            <h1 class="text-2xl md:text-3xl font-semibold tracking-tight">
                <?= $h($title ?? 'Create purchase from award') ?>
            </h1>
            <p class="text-sm text-slate-500">
                Convert this award into a purchase order for
                <?= $h($orgName ?: 'your organisation') ?>.
            </p>
        </div>

        <!-- Right aligned tabs (BizFlow convention) -->
        <nav class="flex flex-wrap justify-end gap-1 text-sm">
            <?php
            $tabs = [
                ['Awards',    $module_base.'/awards'],
                ['Purchases', $module_base.'/purchases'],
                ['Quotes',    $module_base.'/quotes'],
                ['Items',     $module_base.'/items'],
            ];
            $current = $module_base.'/awards/'.$awardId.'/purchase';
            foreach ($tabs as [$label, $url]):
                $active = ($url === $current);
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

    <!-- Award summary + supplier selection -->
    <section class="grid gap-4 md:grid-cols-[2fr,1.6fr]">
        <!-- Award summary -->
        <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm space-y-2">
            <div class="flex items-center justify-between">
                <div>
                    <div class="text-xs uppercase tracking-wide text-slate-500">Award</div>
                    <div class="text-lg font-semibold text-slate-900">
                        <?= $h($awardNo) ?>
                    </div>
                </div>
                <div class="text-right text-sm">
                    <div class="text-slate-500">Total value</div>
                    <div class="text-base font-semibold text-emerald-700">
                        <?php
                        $grand = (float)($award['grand_total'] ?? 0);
                        echo $h(number_format($grand, 2)).' '.$h($currency ?: 'BDT');
                        ?>
                    </div>
                </div>
            </div>
            <dl class="grid grid-cols-2 gap-x-4 gap-y-1 text-xs text-slate-600 mt-2">
                <div>
                    <dt class="font-medium text-slate-500">Customer</dt>
                    <dd><?= $h($award['customer_name'] ?? '—') ?></dd>
                </div>
                <div>
                    <dt class="font-medium text-slate-500">Award date</dt>
                    <dd><?= $h($award['award_date'] ?? $award['date'] ?? '—') ?></dd>
                </div>
                <div>
                    <dt class="font-medium text-slate-500">External ref</dt>
                    <dd><?= $h($award['external_ref'] ?? '—') ?></dd>
                </div>
                <div>
                    <dt class="font-medium text-slate-500">Quote #</dt>
                    <dd><?= $h($award['quote_id'] ?? '—') ?></dd>
                </div>
            </dl>
        </div>

        <!-- Supplier selector + action -->
        <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
            <form method="post" action="<?= $h($action) ?>" class="space-y-3">
                <?php
                // Keep debug flag on POST as well (so _debug stacktrace works)
                if (!empty($_GET['_debug'])): ?>
                    <input type="hidden" name="_debug" value="<?= $h($_GET['_debug']) ?>">
                <?php endif; ?>

                <?php if (!empty($error)): ?>
                    <div class="rounded-md border border-red-200 bg-red-50 px-3 py-2 text-xs text-red-700">
                        <?= $h($error) ?>
                    </div>
                <?php endif; ?>

                <div>
                    <label class="block text-xs font-medium text-slate-600 mb-1">
                        Supplier for this purchase order
                    </label>
                    <select name="supplier_id"
                            class="block w-full rounded-lg border border-slate-200 px-3 py-2 text-sm bg-white focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500">
                        <option value="">Select supplier…</option>
                        <?php foreach ($suppliers as $sup): ?>
                            <?php
                            $sid   = (int)$sup['id'];
                            $label = trim((string)$sup['name']);
                            $code  = trim((string)($sup['code'] ?? ''));
                            $isSel = ($selected_supplier_id && $sid === (int)$selected_supplier_id);
                            ?>
                            <option value="<?= $h($sid) ?>" <?= $isSel ? 'selected' : '' ?>>
                                <?= $h($label . ($code !== '' ? " [{$code}]" : '')) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <p class="mt-1 text-[11px] text-slate-500">
                        This supplier will be saved on the award and used as the vendor for the purchase order.
                    </p>
                </div>

                <button type="submit"
                        class="inline-flex w-full items-center justify-center gap-2 rounded-lg bg-emerald-600 px-3 py-2 text-sm font-medium text-white shadow-sm hover:bg-emerald-700 focus:outline-none focus:ring-2 focus:ring-emerald-600 focus:ring-offset-1">
                    <i class="fa fa-file-invoice-dollar text-xs"></i>
                    <span>Create purchase order</span>
                </button>
            </form>
        </div>
    </section>

    <!-- Lines table -->
    <section class="rounded-xl border border-slate-200 bg-white shadow-sm">
        <div class="border-b border-slate-100 px-4 py-3">
            <h2 class="text-sm font-semibold text-slate-800">Award lines</h2>
            <p class="text-xs text-slate-500">
                These items will be copied into the purchase order.
            </p>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50 text-xs font-semibold uppercase tracking-wide text-slate-500">
                <tr>
                    <th class="px-4 py-2 text-left">Line</th>
                    <th class="px-4 py-2 text-left">Item</th>
                    <th class="px-4 py-2 text-left">Description</th>
                    <th class="px-4 py-2 text-right">Qty</th>
                    <th class="px-4 py-2 text-left">Unit</th>
                    <th class="px-4 py-2 text-right">Unit price</th>
                    <th class="px-4 py-2 text-right">Line total</th>
                </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 bg-white">
                <?php if (!empty($lines)): ?>
                    <?php foreach ($lines as $ln): ?>
                        <?php
                        $lineNo  = $ln['line_no'] ?? $ln['id'];
                        $name    = $ln['item_name'] ?? $ln['product_name'] ?? '';
                        if ($name === '') {
                            $name = $ln['description'] ?? 'Line '.$lineNo;
                        }
                        $code   = $ln['item_code'] ?? $ln['product_code'] ?? '';
                        $qty    = (float)($ln['qty'] ?? 0);
                        $unit   = $ln['unit'] ?? 'pcs';
                        $price  = (float)($ln['unit_price'] ?? 0);
                        $total  = (float)($ln['line_total'] ?? 0);
                        ?>
                        <tr class="hover:bg-emerald-50/40">
                            <td class="px-4 py-2 text-xs text-slate-600 whitespace-nowrap">
                                <?= $h($lineNo) ?>
                            </td>
                            <td class="px-4 py-2 text-xs">
                                <div class="font-medium text-slate-900">
                                    <?= $h($name) ?>
                                </div>
                                <?php if ($code !== ''): ?>
                                    <div class="text-[11px] text-slate-500">
                                        <?= $h($code) ?>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td class="px-4 py-2 text-xs text-slate-700">
                                <?= $h($ln['description'] ?? '—') ?>
                            </td>
                            <td class="px-4 py-2 text-right text-xs text-slate-800 whitespace-nowrap">
                                <?= $h(number_format($qty, 2)) ?>
                            </td>
                            <td class="px-4 py-2 text-left text-xs text-slate-600 whitespace-nowrap">
                                <?= $h($unit) ?>
                            </td>
                            <td class="px-4 py-2 text-right text-xs text-slate-800 whitespace-nowrap">
                                <?= $h(number_format($price, 2)) ?> <?= $h($currency) ?>
                            </td>
                            <td class="px-4 py-2 text-right text-xs text-slate-900 font-medium whitespace-nowrap">
                                <?= $h(number_format($total, 2)) ?> <?= $h($currency) ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="7" class="px-4 py-6 text-center text-sm text-slate-500">
                            No lines found on this award.
                        </td>
                    </tr>
                <?php endif; ?>
                </tbody>
            </table>
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
            <li>Review the <strong>award lines</strong> to confirm quantities, units and prices.</li>
            <li>Pick the <strong>supplier</strong> who will receive this purchase order.</li>
            <li>Click <strong>Create purchase order</strong> – KlinFlow will create a PO and link it back to this award.</li>
            <li>After creation, you’ll be redirected to the <strong>Purchases</strong> section, where you can manage GRNs and inventory impact.</li>
        </ul>
    </section>
</div>