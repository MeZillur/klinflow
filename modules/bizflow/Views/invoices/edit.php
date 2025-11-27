<?php
/** @var array  $invoice */
/** @var array  $items */
/** @var array  $customers */
/** @var array  $org */
/** @var string $module_base */
/** @var string $title */

$h = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');

$module_base = rtrim($module_base ?? ($base ?? '/apps/bizflow'), '/');
$orgName     = $org['name'] ?? '';

$id          = (int)($invoice['id'] ?? 0);
$invNo       = $invoice['invoice_no']      ?? $id;
$date        = $invoice['date']            ?? '';
$due         = $invoice['due_date']        ?? '';
$status      = strtolower((string)($invoice['status'] ?? 'draft'));
$externalRef = $invoice['external_ref']    ?? '';
$currency    = $invoice['currency']        ?? 'BDT';
$terms       = $invoice['payment_terms']   ?? '';
$notes       = $invoice['notes']           ?? '';
$custId      = (int)($invoice['customer_id'] ?? 0);
$customer    = $invoice['customer_name']   ?? '';
?>
<div class="space-y-6">

    <!-- Header -->
    <header class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
        <div class="space-y-1">
            <div class="text-xs text-slate-500 flex items-center gap-1">
                <a href="<?= $h($module_base.'/invoices') ?>" class="hover:underline">Invoices</a>
                <span>/</span>
                <a href="<?= $h($module_base.'/invoices/'.$id) ?>" class="hover:underline">
                    <?= $h($invNo !== '' ? $invNo : ('INV-'.$id)) ?>
                </a>
                <span>/</span>
                <span class="font-medium text-slate-700">Edit</span>
            </div>

            <h1 class="text-2xl md:text-3xl font-semibold tracking-tight">
                Edit invoice <?= $h($invNo !== '' ? $invNo : ('#'.$id)) ?>
            </h1>
            <p class="text-sm text-slate-500">
                Adjust header details, status and notes for this invoice. Line items will be wired once schema is final.
            </p>
        </div>

        <!-- Right: mini nav -->
        <nav class="flex flex-wrap justify-end gap-1 text-xs md:text-[13px]">
            <a href="<?= $h($module_base.'/invoices/'.$id) ?>"
               class="inline-flex items-center gap-1 rounded-full px-3 py-1 border border-slate-200 text-slate-600 hover:bg-slate-50">
                <i class="fa-regular fa-eye text-[11px]"></i>
                <span>View invoice</span>
            </a>
            <a href="<?= $h($module_base.'/invoices') ?>"
               class="inline-flex items-center gap-1 rounded-full px-3 py-1 border border-slate-200 text-slate-600 hover:bg-slate-50">
                <i class="fa fa-receipt text-[11px]"></i>
                <span>All invoices</span>
            </a>
        </nav>
    </header>

    <!-- Main layout -->
    <form method="post" action="<?= $h($module_base.'/invoices/'.$id) ?>" class="grid gap-6 lg:grid-cols-[2.1fr,1.1fr]">
        <!-- LEFT -->
        <section class="space-y-4">
            <!-- Header block -->
            <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm space-y-4">
                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label class="block text-xs font-medium text-slate-600 mb-1">
                            Invoice number
                        </label>
                        <input type="text" name="invoice_no"
                               value="<?= $h($invNo) ?>"
                               class="block w-full rounded-lg border border-slate-200 px-3 py-2 text-sm
                                      focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500">
                    </div>

                    <div>
                        <label class="block text-xs font-medium text-slate-600 mb-1">
                            External reference
                        </label>
                        <input type="text" name="external_ref"
                               value="<?= $h($externalRef) ?>"
                               placeholder="PO number or customer reference"
                               class="block w-full rounded-lg border border-slate-200 px-3 py-2 text-sm
                                      focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500">
                    </div>
                </div>

                <div class="grid gap-4 sm:grid-cols-3">
                    <div>
                        <label class="block text-xs font-medium text-slate-600 mb-1">
                            Invoice date
                        </label>
                        <input type="date" name="date" value="<?= $h($date) ?>"
                               class="block w-full rounded-lg border border-slate-200 px-2.5 py-2 text-sm
                                      focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-600 mb-1">
                            Due date
                        </label>
                        <input type="date" name="due_date" value="<?= $h($due) ?>"
                               class="block w-full rounded-lg border border-slate-200 px-2.5 py-2 text-sm
                                      focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-600 mb-1">
                            Status
                        </label>
                        <select name="status"
                                class="block w-full rounded-lg border border-slate-200 px-3 py-2 bg-white text-sm
                                       focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500">
                            <?php
                            $statuses = ['draft','sent','part_paid','paid','void'];
                            foreach ($statuses as $st):
                                $sel = $status === $st ? 'selected' : '';
                            ?>
                                <option value="<?= $h($st) ?>" <?= $sel ?>>
                                    <?= $h(ucfirst(str_replace('_',' ',$st))) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <!-- Customer -->
                <div class="grid gap-4 sm:grid-cols-[minmax(0,2fr),minmax(0,1.1fr)]">
                    <div>
                        <label class="block text-xs font-medium text-slate-600 mb-1">
                            Customer
                        </label>
                        <?php if (!empty($customers)): ?>
                            <select name="customer_id"
                                    class="block w-full rounded-lg border border-slate-200 px-3 py-2 text-sm bg-white
                                           focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500">
                                <option value="">Select customer</option>
                                <?php foreach ($customers as $c): ?>
                                    <?php
                                    $idOpt = (int)$c['id'];
                                    $name  = $c['name'] ?? '';
                                    $code  = $c['code'] ?? '';
                                    $sel   = $idOpt === $custId ? 'selected' : '';
                                    ?>
                                    <option value="<?= $h($idOpt) ?>" <?= $sel ?>>
                                        <?= $h(($code !== '' ? $code.' — ' : '').$name) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        <?php else: ?>
                            <input type="text" name="customer_name"
                                   value="<?= $h($customer) ?>"
                                   class="block w-full rounded-lg border border-slate-200 px-3 py-2 text-sm
                                          focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500">
                            <p class="mt-1 text-[11px] text-amber-600">
                                Customer master table is not available yet. This will be wired to Biz customers later.
                            </p>
                        <?php endif; ?>
                    </div>

                    <div>
                        <label class="block text-xs font-medium text-slate-600 mb-1">
                            Currency
                        </label>
                        <select name="currency"
                                class="block w-full rounded-lg border border-slate-200 px-3 py-2 text-sm bg-white
                                       focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500">
                            <option value="BDT" <?= $currency === 'BDT' ? 'selected' : '' ?>>
                                BDT — Bangladeshi Taka
                            </option>
                            <!-- Future: more currencies -->
                        </select>
                    </div>
                </div>

                <!-- Terms & notes -->
                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label class="block text-xs font-medium text-slate-600 mb-1">
                            Payment terms
                        </label>
                        <textarea name="payment_terms" rows="3"
                                  class="block w-full rounded-lg border border-slate-200 px-3 py-2 text-sm
                                         focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500"><?= $h($terms) ?></textarea>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-600 mb-1">
                            Internal notes
                        </label>
                        <textarea name="notes" rows="3"
                                  class="block w-full rounded-lg border border-slate-200 px-3 py-2 text-sm
                                         focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500"><?= $h($notes) ?></textarea>
                    </div>
                </div>
            </div>

            <!-- Items (readonly / preview) -->
            <div class="rounded-xl border border-slate-200 bg-white shadow-sm">
                <div class="flex items-center justify-between border-b border-slate-100 px-4 py-3">
                    <h2 class="text-sm font-semibold text-slate-800 flex items-center gap-2">
                        <span class="inline-flex h-6 w-6 items-center justify-center rounded-full bg-emerald-600 text-[11px] text-white">
                            IT
                        </span>
                        Line items (read-only shell)
                    </h2>
                    <span class="text-xs text-slate-400">
                        Full editing will be wired when item schema & JS logic are ready.
                    </span>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-100 text-sm">
                        <thead class="bg-slate-50 text-xs font-semibold text-slate-500 uppercase tracking-wide">
                        <tr>
                            <th class="px-3 py-2 text-left">Item</th>
                            <th class="px-3 py-2 text-left">Description</th>
                            <th class="px-3 py-2 text-right">Qty</th>
                            <th class="px-3 py-2 text-right">Unit price</th>
                            <th class="px-3 py-2 text-right">Line total</th>
                        </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 bg-white">
                        <?php if (!empty($items)): ?>
                            <?php foreach ($items as $row): ?>
                                <?php
                                $name   = $row['item_name']   ?? ($row['description'] ?? '');
                                $code   = $row['item_code']   ?? '';
                                $qty    = $row['qty']         ?? $row['quantity'] ?? null;
                                $unit   = $row['unit_price']  ?? null;
                                $total  = $row['line_total']  ?? $row['total'] ?? null;
                                $uom    = $row['uom']         ?? $row['unit'] ?? '';
                                ?>
                                <tr>
                                    <td class="px-3 py-2 align-top text-xs text-slate-800">
                                        <div class="font-medium"><?= $h($name ?: '—') ?></div>
                                        <?php if ($code !== ''): ?>
                                            <div class="mt-0.5 font-mono text-[11px] text-slate-500">
                                                <?= $h($code) ?>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-3 py-2 align-top text-xs text-slate-600">
                                        <!-- description already shown above if present -->
                                    </td>
                                    <td class="px-3 py-2 align-top text-right text-xs text-slate-700 whitespace-nowrap">
                                        <?php if ($qty !== null && $qty !== ''): ?>
                                            <?= $h((string)$qty) ?> <?php if ($uom !== ''): ?><span class="text-slate-400"><?= $h($uom) ?></span><?php endif; ?>
                                        <?php else: ?>
                                            <span class="text-slate-400">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-3 py-2 align-top text-right text-xs text-slate-700">
                                        <?php if ($unit !== null && $unit !== ''): ?>
                                            <?= $h(number_format((float)$unit, 2)) ?> <?= $h($currency) ?>
                                        <?php else: ?>
                                            <span class="text-slate-400">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-3 py-2 align-top text-right text-xs text-slate-700">
                                        <?php if ($total !== null && $total !== ''): ?>
                                            <?= $h(number_format((float)$total, 2)) ?> <?= $h($currency) ?>
                                        <?php else: ?>
                                            <span class="text-slate-400">—</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5" class="px-3 py-4 text-center text-xs text-slate-500">
                                    No line items found for this invoice yet.
                                </td>
                            </tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>

        <!-- RIGHT: summary + save + help -->
        <aside class="space-y-4">
            <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm space-y-3">
                <h2 class="text-sm font-semibold text-slate-800">Update summary</h2>
                <p class="text-xs text-slate-600">
                    Use this form to correct header details, dates, status and notes.
                    Amounts and items remain read-only until the full posting engine is ready.
                </p>

            <div class="pt-3 border-t border-slate-100 flex flex-col gap-2">
                <button type="submit"
                        class="inline-flex items-center justify-center gap-2 rounded-lg bg-emerald-600 px-3 py-2 text-sm font-medium text-white shadow-sm hover:bg-emerald-700 focus:outline-none focus:ring-2 focus:ring-emerald-600 focus:ring-offset-1">
                    <i class="fa fa-save text-xs"></i>
                    <span>Save changes (future)</span>
                </button>
                <p class="text-[11px] text-slate-400">
                    Backend <code>update()</code> will be implemented when BizFlow invoice schema & posting flow are confirmed.
                </p>
            </div>
        </div>

        <!-- How to use this page -->
        <div class="rounded-xl border border-dashed border-emerald-400 bg-emerald-50/60 px-4 py-4 text-sm text-slate-800">
            <h2 class="mb-2 flex items-center gap-2 text-sm font-semibold text-emerald-900">
                <span class="inline-flex h-6 w-6 items-center justify-center rounded-full bg-emerald-600 text-[11px] text-white">
                    ?
                </span>
                How to use this page
            </h2>
            <ul class="ml-6 list-disc space-y-1 text-[13px]">
                <li>Update <strong>invoice number</strong>, <strong>dates</strong> and <strong>status</strong> in the header block.</li>
                <li>Adjust <strong>customer</strong>, <strong>payment terms</strong> and <strong>internal notes</strong> as needed.</li>
                <li>Review the <strong>line items</strong> list for quick reference; editing will come with item schema.</li>
                <li>Use this as a safe 2035-style shell while we finalise backend logic and database structures.</li>
            </ul>
        </div>
        </aside>
    </form>
</div>