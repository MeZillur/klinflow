<?php
/** @var array  $customers */
/** @var array  $org */
/** @var string $module_base */
/** @var string $title */
/** @var string $today */

$h = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');

$module_base = rtrim($module_base ?? ($base ?? '/apps/bizflow'), '/');
$orgName     = $org['name'] ?? '';
?>
<div class="space-y-6">

    <!-- Header -->
    <header class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
        <div class="space-y-1">
            <div class="text-xs text-slate-500 flex items-center gap-1">
                <a href="<?= $h($module_base.'/payments') ?>" class="hover:underline">Payments</a>
                <span>/</span>
                <span class="font-medium text-slate-700">Record payment</span>
            </div>

            <h1 class="text-2xl md:text-3xl font-semibold tracking-tight">
                Record payment
            </h1>
            <p class="text-sm text-slate-500">
                Capture a receipt from your customer for <?= $h($orgName ?: 'your organisation') ?>.
            </p>
        </div>

        <nav class="flex flex-wrap justify-end gap-1 text-xs md:text-[13px]">
            <a href="<?= $h($module_base.'/payments') ?>"
               class="inline-flex items-center gap-1 rounded-full px-3 py-1 border border-slate-200 text-slate-600 hover:bg-slate-50">
                <i class="fa fa-arrow-left text-[11px]"></i>
                <span>Back to payments</span>
            </a>
        </nav>
    </header>

    <!-- Form layout -->
    <form method="post" action="<?= $h($module_base.'/payments') ?>" class="grid gap-6 lg:grid-cols-[2.1fr,1.1fr]">
        <!-- LEFT -->
        <section class="space-y-4">

            <!-- Payment header -->
            <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm space-y-4">
                <div class="grid gap-4 sm:grid-cols-3">
                    <div>
                        <label class="block text-xs font-medium text-slate-600 mb-1">
                            Date
                        </label>
                        <input type="date" name="date" value="<?= $h($today) ?>"
                               class="block w-full rounded-lg border border-slate-200 px-2.5 py-2 text-sm
                                      focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-600 mb-1">
                            Amount (BDT)
                        </label>
                        <input type="number" step="0.01" min="0" name="amount"
                               placeholder="0.00"
                               class="block w-full rounded-lg border border-slate-200 px-3 py-2 text-sm text-right
                                      focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-600 mb-1">
                            Method
                        </label>
                        <select name="method"
                                class="block w-full rounded-lg border border-slate-200 px-3 py-2 text-sm bg-white
                                       focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500">
                            <option value="cash" selected>Cash</option>
                            <option value="bank_transfer">Bank transfer</option>
                            <option value="mobile_money">Mobile money (bKash/Nagad)</option>
                            <option value="cheque">Cheque</option>
                            <option value="lc_settlement">LC settlement</option>
                            <option value="adjustment">Adjustment</option>
                        </select>
                    </div>
                </div>

                <!-- Customer + reference -->
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
                                    $id   = (int)$c['id'];
                                    $name = $c['name'] ?? '';
                                    $code = $c['code'] ?? '';
                                    ?>
                                    <option value="<?= $h($id) ?>">
                                        <?= $h(($code !== '' ? $code.' — ' : '').$name) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <p class="mt-1 text-[11px] text-slate-400">
                                This is a compact list for now. Later we will use KF.lookup for full search.
                            </p>
                        <?php else: ?>
                            <input type="text" name="customer_name"
                                   placeholder="Customer name (temporary while customers table is missing)"
                                   class="block w-full rounded-lg border border-slate-200 px-3 py-2 text-sm
                                          focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500">
                            <p class="mt-1 text-[11px] text-amber-600">
                                Customer master not ready yet. This field is a placeholder for now.
                            </p>
                        <?php endif; ?>
                    </div>

                    <div>
                        <label class="block text-xs font-medium text-slate-600 mb-1">
                            Reference
                        </label>
                        <input type="text" name="reference"
                               placeholder="Receipt #, bank slip, transaction ID"
                               class="block w-full rounded-lg border border-slate-200 px-3 py-2 text-sm
                                      focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500">
                    </div>
                </div>

                <!-- Notes -->
                <div>
                    <label class="block text-xs font-medium text-slate-600 mb-1">
                        Notes (internal)
                    </label>
                    <textarea name="notes" rows="3"
                              placeholder="Internal notes for your team, not printed on documents."
                              class="block w-full rounded-lg border border-slate-200 px-3 py-2 text-sm
                                     focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500"></textarea>
                </div>
            </div>

            <!-- Allocations shell (future) -->
            <div class="rounded-xl border border-slate-200 bg-white shadow-sm">
                <div class="flex items-center justify-between border-b border-slate-100 px-4 py-3">
                    <h2 class="text-sm font-semibold text-slate-800 flex items-center gap-2">
                        <span class="inline-flex h-6 w-6 items-center justify-center rounded-full bg-sky-500 text-[11px] text-white">
                            AL
                        </span>
                        Allocate to invoices (UI preview)
                    </h2>
                    <span class="text-xs text-slate-400">
                        Behaviour will be wired after <code>biz_payment_allocations</code> is defined.
                    </span>
                </div>
                <div class="px-4 py-3 text-xs text-slate-500">
                    This is a 2035-style shell. Later, you’ll pick invoices and allocate this amount across them.
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200 text-sm">
                        <thead class="bg-slate-50 text-xs font-semibold text-slate-500 uppercase tracking-wide">
                        <tr>
                            <th class="px-3 py-2 text-left">Invoice</th>
                            <th class="px-3 py-2 text-left">Date</th>
                            <th class="px-3 py-2 text-right">Outstanding</th>
                            <th class="px-3 py-2 text-right">Allocate</th>
                        </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 bg-white">
                        <?php for ($i = 0; $i < 3; $i++): ?>
                            <tr>
                                <td class="px-3 py-2 align-top text-xs text-slate-600">
                                    <input type="text" name="allocations[<?= $i ?>][invoice_no]"
                                           placeholder="Invoice number (future lookup)"
                                           class="block w-full rounded-md border border-slate-200 px-2 py-1 text-xs
                                                  focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500">
                                </td>
                                <td class="px-3 py-2 align-top text-xs text-slate-400">
                                    —
                                </td>
                                <td class="px-3 py-2 align-top text-right text-xs text-slate-400">
                                    0.00 BDT
                                </td>
                                <td class="px-3 py-2 align-top text-right text-xs">
                                    <input type="number" step="0.01" min="0"
                                           name="allocations[<?= $i ?>][amount]"
                                           placeholder="0.00"
                                           class="block w-full rounded-md border border-slate-200 px-2 py-1 text-right text-xs
                                                  focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500">
                                </td>
                            </tr>
                        <?php endfor; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </section>

        <!-- RIGHT: summary + help -->
        <aside class="space-y-4">
            <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm space-y-3">
                <h2 class="text-sm font-semibold text-slate-800">Receipt summary (preview)</h2>
                <p class="text-xs text-slate-600">
                    Once schema is ready, this form will post into <code>biz_payments</code> and allocations
                    into <code>biz_payment_allocations</code>.
                </p>

                <div class="pt-3 border-t border-slate-100 flex flex-col gap-2">
                    <button type="submit"
                            class="inline-flex items-center justify-center gap-2 rounded-lg bg-emerald-600 px-3 py-2 text-sm font-medium text-white shadow-sm hover:bg-emerald-700 focus:outline-none focus:ring-2 focus:ring-emerald-600 focus:ring-offset-1">
                        <i class="fa fa-save text-xs"></i>
                        <span>Save payment (future)</span>
                    </button>
                    <p class="text-[11px] text-slate-400">
                        Backend <code>store()</code> is intentionally not wired yet — UI first, schema next.
                    </p>
                </div>
            </div>

            <!-- How to use -->
            <div class="rounded-xl border border-dashed border-emerald-400 bg-emerald-50/60 px-4 py-4 text-sm text-slate-800">
                <h2 class="mb-2 flex items-center gap-2 text-sm font-semibold text-emerald-900">
                    <span class="inline-flex h-6 w-6 items-center justify-center rounded-full bg-emerald-600 text-[11px] text-white">
                        ?
                    </span>
                    How to use this page
                </h2>
                <ul class="ml-6 list-disc space-y-1 text-[13px]">
                    <li>Pick the <strong>date</strong>, <strong>amount</strong> and <strong>method</strong> of receipt.</li>
                    <li>Select the <strong>customer</strong> (or type name while customer schema is not ready).</li>
                    <li>Add a clear <strong>reference</strong> (bank slip, transaction ID, LC settlement note).</li>
                    <li>Use the <strong>Allocate to invoices</strong> area as a visual guide for how future allocations will work.</li>
                    <li>Later, this flow will drive your accounts receivable and cash/bank GL postings in BizFlow.</li>
                </ul>
            </div>
        </aside>
    </form>
</div>