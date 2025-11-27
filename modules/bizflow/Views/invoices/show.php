<?php
/** ============================================================
 * BizFlow — Invoice show page (screen view)
 * ------------------------------------------------------------
 * Variables expected from controller:
 *   - array  $invoice
 *   - array  $items
 *   - array  $org
 *   - string $module_base
 *   - string $title
 * ========================================================== */

/* SEGMENT 1 — Helpers + basic context
   ------------------------------------------------------------ */
$h = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');

$module_base = rtrim((string)($module_base ?? '/apps/bizflow'), '/');
$orgName     = $org['name'] ?? '';
$currency    = $invoice['currency'] ?? 'BDT';
$invId       = (int)($invoice['id'] ?? 0);
$invNo       = $invoice['invoice_no'] ?? ('#' . $invId);

/* SEGMENT 2 — Overdue and balance calculation
   ------------------------------------------------------------ */
$todayStr  = date('Y-m-d');
$dueStr    = (string)($invoice['due_date'] ?? '');
$status    = strtolower((string)($invoice['status'] ?? 'draft'));
$grand     = (float)($invoice['grand_total'] ?? 0);
$paid      = (float)($invoice['paid_total'] ?? 0);
$balance   = (float)($invoice['balance_due'] ?? ($grand - $paid));

$isOverdue   = false;
$overdueDays = 0;

if (
    $dueStr !== '' &&
    preg_match('/^\d{4}-\d{2}-\d{2}$/', $dueStr) &&
    !in_array($status, ['void', 'draft'], true) &&
    $balance > 0.0
) {
    $today = new DateTimeImmutable($todayStr);
    $due   = new DateTimeImmutable($dueStr);
    if ($due < $today) {
        $isOverdue   = true;
        $overdueDays = (int)$today->diff($due)->format('%a');
    }
}

/* SEGMENT 3 — Pre-build URLs for actions + navigation
   ------------------------------------------------------------ */
$printUrl = $module_base . '/invoices/' . $invId . '/print?auto=1';
$pdfUrl   = $module_base . '/invoices/' . $invId . '/pdf?download=1';

$quotesUrl   = $module_base . '/quotes';
$awardsUrl   = $module_base . '/awards';
$indexUrl    = $module_base . '/invoices';
?>
<div class="space-y-6">

    <!-- =======================================================
         SEGMENT 4 — Header: page title + primary actions
         ===================================================== -->
    <header class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
        <div>
            <h1 class="text-2xl md:text-3xl font-semibold tracking-tight">
                <?= $h($invoice['invoice_no'] ?? 'Invoice') ?>
            </h1>
            <p class="mt-1 text-sm text-slate-500">
                Customer invoice for <?= $h($orgName ?: 'your organisation') ?>.
            </p>
        </div>

        <div class="flex flex-col items-end gap-2">
            <!-- Primary actions: PRINT + DOWNLOAD PDF -->
            <div class="flex flex-wrap items-center justify-end gap-2">
                <!-- PRINT: open HTML print layout in new tab and auto-print -->
                <button type="button"
                        onclick="window.open('<?= $h($printUrl) ?>','_blank','noopener')"
                        class="inline-flex items-center gap-1 rounded-md border border-gray-300 bg-white px-3 py-1.5 text-xs font-medium text-gray-700 hover:bg-gray-50">
                    <i class="fa-solid fa-print text-[11px]"></i>
                    <span>Print</span>
                </button>

                <!-- DOWNLOAD: hit dedicated PDF endpoint (Dompdf) -->
                <a href="<?= $h($pdfUrl) ?>"
                   class="inline-flex items-center gap-1 rounded-md border border-emerald-600 bg-emerald-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-emerald-700">
                    <i class="fa-solid fa-file-arrow-down text-[11px]"></i>
                    <span>Download PDF</span>
                </a>
            </div>

            <!-- Local navigation between related workspaces -->
            <nav class="flex flex-wrap justify-end gap-1 text-xs md:text-[13px]">
                <a href="<?= $h($quotesUrl) ?>"
                   class="inline-flex items-center gap-1 rounded-full border border-slate-200 px-3 py-1 hover:bg-slate-50">
                    <i class="fa-regular fa-file-lines text-[11px]"></i>
                    <span>Quotes</span>
                </a>
                <a href="<?= $h($awardsUrl) ?>"
                   class="inline-flex items-center gap-1 rounded-full border border-slate-200 px-3 py-1 hover:bg-slate-50">
                    <i class="fa-regular fa-star text-[11px]"></i>
                    <span>Awards</span>
                </a>
                <a href="<?= $h($indexUrl) ?>"
                   class="inline-flex items-center gap-1 rounded-full border border-emerald-600 bg-emerald-50 px-3 py-1 text-emerald-700">
                    <i class="fa-regular fa-rectangle-list text-[11px]"></i>
                    <span>Invoices list</span>
                </a>
            </nav>
        </div>
    </header>

    <!-- =======================================================
         SEGMENT 5 — Summary panels (header + money)
         ===================================================== -->
    <section class="grid gap-4 md:grid-cols-[2fr,1.4fr]">
        <!-- 5A: Header meta -->
        <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm space-y-2">
            <dl class="grid grid-cols-2 gap-x-4 gap-y-1 text-xs text-slate-600">
                <div>
                    <dt class="font-medium text-slate-500">Invoice no</dt>
                    <dd><?= $h($invNo) ?></dd>
                </div>
                <div>
                    <dt class="font-medium text-slate-500">Invoice date</dt>
                    <dd><?= $h($invoice['date'] ?? '—') ?></dd>
                </div>
                <div>
                    <dt class="font-medium text-slate-500">Due date</dt>
                    <dd class="flex flex-wrap items-center gap-2">
                        <span><?= $h($invoice['due_date'] ?? '—') ?></span>
                        <?php if ($isOverdue): ?>
                            <span class="inline-flex items-center rounded-full bg-red-50 px-2 py-[2px] text-[11px] font-medium text-red-700">
                                Overdue by <?= $h((string)$overdueDays) ?> day<?= $overdueDays === 1 ? '' : 's' ?>
                            </span>
                        <?php endif; ?>
                    </dd>
                </div>
                <div>
                    <dt class="font-medium text-slate-500">Customer</dt>
                    <dd><?= $h($invoice['customer_name'] ?? '—') ?></dd>
                </div>
                <div>
                    <dt class="font-medium text-slate-500">Status</dt>
                    <dd><?= $h($invoice['status'] ?? 'draft') ?></dd>
                </div>
                <div>
                    <dt class="font-medium text-slate-500">External ref</dt>
                    <dd><?= $h($invoice['external_ref'] ?? '—') ?></dd>
                </div>
            </dl>
        </div>

        <!-- 5B: Money summary -->
        <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm space-y-1 text-sm">
            <div class="flex justify-between">
                <span>Sub-total</span>
                <span><?= number_format((float)($invoice['subtotal'] ?? 0), 2) ?> <?= $h($currency) ?></span>
            </div>
            <div class="flex justify-between text-rose-700">
                <span>Discount total</span>
                <span>-<?= number_format((float)($invoice['discount_total'] ?? 0), 2) ?> <?= $h($currency) ?></span>
            </div>
            <div class="flex justify-between">
                <span>Tax total</span>
                <span><?= number_format((float)($invoice['tax_total'] ?? 0), 2) ?> <?= $h($currency) ?></span>
            </div>
            <div class="flex justify-between">
                <span>Shipping</span>
                <span><?= number_format((float)($invoice['shipping_total'] ?? 0), 2) ?> <?= $h($currency) ?></span>
            </div>
            <hr class="my-1">
            <div class="flex justify-between font-semibold text-lg">
                <span>Grand total</span>
                <span><?= number_format((float)($invoice['grand_total'] ?? 0), 2) ?> <?= $h($currency) ?></span>
            </div>
            <div class="flex justify-between text-xs text-slate-600 pt-1">
                <span>Paid</span>
                <span><?= number_format((float)$paid, 2) ?> <?= $h($currency) ?></span>
            </div>
            <div class="flex justify-between text-xs font-medium pt-0.5 <?= $balance > 0 ? 'text-amber-700' : 'text-emerald-700' ?>">
                <span>Balance due</span>
                <span><?= number_format((float)$balance, 2) ?> <?= $h($currency) ?></span>
            </div>
        </div>
    </section>

    <!-- =======================================================
         SEGMENT 6 — Line items table
         ===================================================== -->
    <section class="rounded-xl border border-slate-200 bg-white shadow-sm">
        <div class="border-b border-slate-100 px-4 py-3">
            <h2 class="text-sm font-semibold text-slate-800">Invoice items</h2>
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
                <?php if (!empty($items)): ?>
                    <?php foreach ($items as $ln): ?>
                        <?php
                        $lineNo = $ln['line_no'] ?? $ln['id'];
                        $name   = $ln['item_name'] ?? $ln['product_name'] ?? '';
                        if ($name === '') {
                            $name = $ln['description'] ?? ('Line ' . $lineNo);
                        }
                        $qty   = (float)($ln['qty'] ?? 0);
                        $unit  = $ln['unit'] ?? 'pcs';
                        $price = (float)($ln['unit_price'] ?? 0);
                        $total = (float)($ln['line_total'] ?? ($qty * $price));
                        ?>
                        <tr>
                            <td class="px-4 py-2 text-xs text-slate-600 whitespace-nowrap">
                                <?= $h($lineNo) ?>
                            </td>
                            <td class="px-4 py-2 text-xs">
                                <div class="font-medium text-slate-900"><?= $h($name) ?></div>
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
                            No items found for this invoice.
                        </td>
                    </tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>

    <!-- =======================================================
         SEGMENT 7 — How to use this page (UX helper)
         ===================================================== -->
    <section class="rounded-xl border border-dashed border-emerald-400 bg-emerald-50/60 px-4 py-4 text-sm text-slate-800">
        <h2 class="mb-2 flex items-center gap-2 text-sm font-semibold text-emerald-900">
            <span class="inline-flex h-6 w-6 items-center justify-center rounded-full bg-emerald-600 text-[11px] text-white">
                ?
            </span>
            How to use this page
        </h2>
        <ul class="ml-6 list-disc space-y-1 text-[13px]">
            <li>Verify invoice header: customer, dates, reference, and status.</li>
            <li>Review each line item, quantity and unit price against the quote or award.</li>
            <li>Use the <strong>Print</strong> button to open an A4 layout and send directly to printer.</li>
            <li>Use <strong>Download PDF</strong> to generate a Dompdf invoice file for email or archive.</li>
            <li>Click <strong>Invoices list</strong> to return to the full history for this tenant.</li>
        </ul>
    </section>
</div>