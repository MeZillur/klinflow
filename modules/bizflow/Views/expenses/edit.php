<?php
declare(strict_types=1);

/** @var array  $org */
/** @var string $module_base */
/** @var array  $expense */
/** @var string $title */
/** @var string $mode */

$h = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');

$module_base = rtrim($module_base ?? '/apps/bizflow', '/');
$orgName     = trim((string)($org['name'] ?? '')) ?: 'your organisation';
$id          = (int)($expense['id'] ?? 0);
?>
<div class="space-y-6">

    <!-- Header + tabs -->
    <header class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
        <div>
            <h1 class="text-2xl md:text-3xl font-semibold tracking-tight">
                <?= $h($title ?? 'Edit expense') ?>
            </h1>
            <p class="text-sm text-slate-500">
                Update an existing expense voucher for <?= $h($orgName) ?>.
            </p>
        </div>

        <?php
        $tabs = [
            ['Expenses',    $module_base.'/expenses',       false],
            ['New expense', $module_base.'/expenses/create', false],
        ];
        ?>
        <nav class="flex flex-wrap justify-end gap-1 text-sm">
            <?php foreach ($tabs as [$label, $url, $active]): ?>
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

    <div class="grid gap-6 lg:grid-cols-[2.1fr,1.1fr]">

        <!-- LEFT: form -->
        <section class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
            <form method="post"
                  action="<?= $h($module_base.'/expenses/'.$id) ?>"
                  class="space-y-4 text-xs">
                <input type="hidden" name="mode" value="edit">

                <div class="grid gap-3 md:grid-cols-3">
                    <div class="md:col-span-1">
                        <label class="mb-1 block text-[11px] font-medium text-slate-700">
                            Voucher no
                        </label>
                        <input type="text"
                               name="voucher_no"
                               value="<?= $h($expense['voucher_no'] ?? '') ?>"
                               class="w-full rounded-lg border border-slate-200 px-3 py-1.5 text-xs focus:border-emerald-600 focus:outline-none focus:ring-1 focus:ring-emerald-600">
                    </div>
                    <div class="md:col-span-1">
                        <label class="mb-1 block text-[11px] font-medium text-slate-700">
                            Date
                        </label>
                        <input type="date"
                               name="expense_date"
                               value="<?= $h($expense['expense_date'] ?? '') ?>"
                               class="w-full rounded-lg border border-slate-200 px-2 py-1.5 text-xs focus:border-emerald-600 focus:outline-none focus:ring-1 focus:ring-emerald-600">
                    </div>
                    <div class="md:col-span-1">
                        <label class="mb-1 block text-[11px] font-medium text-slate-700">
                            Category
                        </label>
                        <input type="text"
                               name="category_name"
                               value="<?= $h($expense['category_name'] ?? '') ?>"
                               class="w-full rounded-lg border border-slate-200 px-3 py-1.5 text-xs focus:border-emerald-600 focus:outline-none focus:ring-1 focus:ring-emerald-600">
                    </div>
                </div>

                <div class="grid gap-3 md:grid-cols-2">
                    <div>
                        <label class="mb-1 block text-[11px] font-medium text-slate-700">
                            Payee name
                        </label>
                        <input type="text"
                               name="payee_name"
                               value="<?= $h($expense['payee_name'] ?? '') ?>"
                               class="w-full rounded-lg border border-slate-200 px-3 py-1.5 text-xs focus:border-emerald-600 focus:outline-none focus:ring-1 focus:ring-emerald-600">
                    </div>
                    <div>
                        <label class="mb-1 block text-[11px] font-medium text-slate-700">
                            Reference
                        </label>
                        <input type="text"
                               name="reference"
                               value="<?= $h($expense['reference'] ?? '') ?>"
                               class="w-full rounded-lg border border-slate-200 px-3 py-1.5 text-xs focus:border-emerald-600 focus:outline-none focus:ring-1 focus:ring-emerald-600">
                    </div>
                </div>

                <div>
                    <label class="mb-1 block text-[11px] font-medium text-slate-700">
                        Description / note
                    </label>
                    <textarea name="description"
                              rows="3"
                              class="w-full rounded-lg border border-slate-200 px-3 py-2 text-xs focus:border-emerald-600 focus:outline-none focus:ring-1 focus:ring-emerald-600"><?= $h($expense['description'] ?? '') ?></textarea>
                </div>

                <div class="grid gap-3 md:grid-cols-3">
                    <div>
                        <label class="mb-1 block text-[11px] font-medium text-slate-700">
                            Payment method
                        </label>
                        <?php $pm = (string)($expense['payment_method'] ?? 'cash'); ?>
                        <select name="payment_method"
                                class="w-full rounded-lg border border-slate-200 px-2 py-1.5 text-xs focus:border-emerald-600 focus:outline-none focus:ring-1 focus:ring-emerald-600">
                            <option value="cash"   <?= $pm === 'cash'   ? 'selected' : '' ?>>Cash</option>
                            <option value="bank"   <?= $pm === 'bank'   ? 'selected' : '' ?>>Bank</option>
                            <option value="mobile" <?= $pm === 'mobile' ? 'selected' : '' ?>>Mobile</option>
                        </select>
                    </div>
                    <div>
                        <label class="mb-1 block text-[11px] font-medium text-slate-700">
                            Gross amount (BDT)
                        </label>
                        <input type="number"
                               step="0.01"
                               name="amount_gross"
                               value="<?= $h($expense['amount_gross'] ?? '') ?>"
                               class="w-full rounded-lg border border-slate-200 px-3 py-1.5 text-xs text-right focus:border-emerald-600 focus:outline-none focus:ring-1 focus:ring-emerald-600">
                    </div>
                    <div>
                        <label class="mb-1 block text-[11px] font-medium text-slate-700">
                            Status
                        </label>
                        <?php $st = (string)($expense['status'] ?? 'draft'); ?>
                        <select name="status"
                                class="w-full rounded-lg border border-slate-200 px-2 py-1.5 text-xs focus:border-emerald-600 focus:outline-none focus:ring-1 focus:ring-emerald-600">
                            <option value="draft"    <?= $st === 'draft'    ? 'selected' : '' ?>>Draft</option>
                            <option value="posted"   <?= $st === 'posted'   ? 'selected' : '' ?>>Posted</option>
                            <option value="cancelled"<?= $st === 'cancelled'? 'selected' : '' ?>>Cancelled</option>
                        </select>
                    </div>
                </div>

                <div class="grid gap-3 md:grid-cols-3">
                    <div>
                        <label class="mb-1 block text-[11px] font-medium text-slate-700">
                            VAT amount (BDT)
                        </label>
                        <input type="number"
                               step="0.01"
                               name="vat_amount"
                               value="<?= $h($expense['vat_amount'] ?? '') ?>"
                               class="w-full rounded-lg border border-slate-200 px-3 py-1.5 text-xs text-right focus:border-emerald-600 focus:outline-none focus:ring-1 focus:ring-emerald-600">
                    </div>
                    <div>
                        <label class="mb-1 block text-[11px] font-medium text-slate-700">
                            WHT amount (BDT)
                        </label>
                        <input type="number"
                               step="0.01"
                               name="wht_amount"
                               value="<?= $h($expense['wht_amount'] ?? '') ?>"
                               class="w-full rounded-lg border border-slate-200 px-3 py-1.5 text-xs text-right focus:border-emerald-600 focus:outline-none focus:ring-1 focus:ring-emerald-600">
                    </div>
                    <div class="flex items-end text-[11px] text-slate-500">
                        Adjust VAT/WHT if you corrected the underlying bill or tax treatment.
                    </div>
                </div>

                <div class="flex justify-end gap-2 pt-3">
                    <a href="<?= $h($module_base.'/expenses') ?>"
                       class="inline-flex items-center rounded-lg border border-slate-200 px-3 py-1.5 text-[11px] text-slate-600 hover:bg-slate-50">
                        Cancel
                    </a>
                    <button type="submit"
                            class="inline-flex items-center rounded-lg bg-emerald-600 px-3 py-1.5 text-[11px] font-medium text-white shadow-sm hover:bg-emerald-700">
                        Update expense (preview)
                    </button>
                </div>
            </form>
        </section>

        <!-- RIGHT: How to -->
        <aside class="space-y-4">
            <section class="rounded-2xl border border-dashed border-emerald-400 bg-emerald-50/60 px-4 py-4 text-sm text-slate-800">
                <h2 class="mb-2 flex items-center gap-2 text-sm font-semibold text-emerald-900">
                    <span class="inline-flex h-6 w-6 items-center justify-center rounded-full bg-emerald-600 text-[11px] text-white">
                        ?
                    </span>
                    How to use this page
                </h2>
                <ul class="ml-6 list-disc space-y-1 text-[13px]">
                    <li>Only edit vouchers when you have a clear audit trail (bill corrections, tax adjustments, etc.).</li>
                    <li>Change <strong>status</strong> to cancelled instead of deleting vouchers to keep history clean.</li>
                    <li>VAT and WHT amounts here will later sync to your Tax/VAT/WHT registers.</li>
                </ul>
            </section>
        </aside>
    </div>
</div>