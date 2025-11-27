<?php
/** @var array  $account */
/** @var array  $currencies */
/** @var array  $types */
/** @var array  $org */
/** @var string $module_base */
/** @var string $title */

$h = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');

$module_base = rtrim($module_base ?? ($base ?? '/apps/bizflow'), '/');
$orgName     = $org['name'] ?? '';

$account    = $account ?? [];
$currencies = $currencies ?? ['BDT'];
$types      = $types ?? ['bank' => 'Bank account'];

$name            = $account['name']            ?? '';
$bankName        = $account['bank_name']       ?? '';
$branchName      = $account['branch_name']     ?? '';
$accountNo       = $account['account_no']      ?? '';
$iban            = $account['iban']            ?? '';
$swift           = $account['swift_bic']       ?? '';
$currency        = $account['currency']        ?? 'BDT';
$type            = $account['type']            ?? 'bank';
$openingBalance  = $account['opening_balance'] ?? null;
$openingDate     = $account['opening_date']    ?? (new DateTimeImmutable('now'))->format('Y-m-d');
$isActive        = (int)($account['is_active'] ?? 1) === 1;
$isDefault       = (int)($account['is_default'] ?? 0) === 1;
$notes           = $account['notes']           ?? '';
?>
<div class="space-y-6">

    <!-- Breadcrumb + title -->
    <header class="space-y-3">
        <div class="text-xs text-slate-500 flex items-center gap-1">
            <a href="<?= $h($module_base.'/banking') ?>" class="hover:underline">Banking</a>
            <span>/</span>
            <span class="font-medium text-slate-700">New account</span>
        </div>

        <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
            <div>
                <h1 class="text-2xl md:text-3xl font-semibold tracking-tight">
                    <?= $h($title ?? 'New bank account') ?>
                </h1>
                <p class="text-sm text-slate-500">
                    Define a bank or cash account for <?= $h($orgName ?: 'your organisation') ?>.
                    This will power payments, reconciliations and cash-flow reporting.
                </p>
            </div>

            <!-- Right mini-nav -->
            <nav class="flex flex-wrap justify-end gap-1 text-xs md:text-[13px]">
                <a href="<?= $h($module_base.'/banking') ?>"
                   class="inline-flex items-center gap-1 rounded-full px-3 py-1 border border-slate-200 text-slate-600 hover:bg-slate-50">
                    <i class="fa fa-arrow-left text-[11px]"></i>
                    <span>Back to banking</span>
                </a>
            </nav>
        </div>
    </header>

    <!-- Main form layout -->
    <section class="rounded-xl border border-slate-200 bg-white shadow-sm">
        <form action="<?= $h($module_base.'/banking/accounts/create') ?>"
              method="post"
              class="grid gap-6 border-t border-slate-100 px-4 py-4 md:px-6 md:py-6 lg:grid-cols-[2.1fr,1.4fr]">

            <!-- LEFT: core account details -->
            <div class="space-y-5">
                <div>
                    <h2 class="text-sm font-semibold text-slate-800 mb-1">Account details</h2>
                    <p class="text-xs text-slate-500">
                        Basic information used on payment screens, reports and reconciliations.
                    </p>
                </div>

                <div class="grid gap-4 md:grid-cols-2">
                    <div class="md:col-span-2">
                        <label class="block text-xs font-medium text-slate-600 mb-1">
                            Account name <span class="text-rose-500">*</span>
                        </label>
                        <input type="text"
                               name="name"
                               value="<?= $h($name) ?>"
                               required
                               placeholder="e.g. BRAC Bank - Corporate Current"
                               class="block w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500">
                    </div>

                    <div>
                        <label class="block text-xs font-medium text-slate-600 mb-1">
                            Bank name
                        </label>
                        <input type="text"
                               name="bank_name"
                               value="<?= $h($bankName) ?>"
                               placeholder="e.g. BRAC Bank"
                               class="block w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500">
                    </div>

                    <div>
                        <label class="block text-xs font-medium text-slate-600 mb-1">
                            Branch
                        </label>
                        <input type="text"
                               name="branch_name"
                               value="<?= $h($branchName) ?>"
                               placeholder="e.g. Gulshan Branch"
                               class="block w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500">
                    </div>

                    <div>
                        <label class="block text-xs font-medium text-slate-600 mb-1">
                            Account number
                        </label>
                        <input type="text"
                               name="account_no"
                               value="<?= $h($accountNo) ?>"
                               placeholder="e.g. 0123 4567 8901"
                               class="block w-full rounded-lg border border-slate-200 px-3 py-2 text-sm font-mono focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500">
                    </div>

                    <div>
                        <label class="block text-xs font-medium text-slate-600 mb-1">
                            Currency
                        </label>
                        <select name="currency"
                                class="block w-full rounded-lg border border-slate-200 px-3 py-2 text-sm bg-white focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500">
                            <?php foreach ($currencies as $ccy): ?>
                                <option value="<?= $h($ccy) ?>" <?= $ccy === $currency ? 'selected' : '' ?>>
                                    <?= $h($ccy) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-medium text-slate-600 mb-1">
                            Account type
                        </label>
                        <select name="type"
                                class="block w-full rounded-lg border border-slate-200 px-3 py-2 text-sm bg-white focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500">
                            <?php foreach ($types as $key => $label): ?>
                                <option value="<?= $h($key) ?>" <?= $key === $type ? 'selected' : '' ?>>
                                    <?= $h($label) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="grid gap-4 md:grid-cols-2">
                    <div>
                        <label class="block text-xs font-medium text-slate-600 mb-1">
                            IBAN (optional)
                        </label>
                        <input type="text"
                               name="iban"
                               value="<?= $h($iban) ?>"
                               placeholder="For international accounts"
                               class="block w-full rounded-lg border border-slate-200 px-3 py-2 text-sm font-mono uppercase focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-600 mb-1">
                            SWIFT / BIC
                        </label>
                        <input type="text"
                               name="swift_bic"
                               value="<?= $h($swift) ?>"
                               placeholder="e.g. BRAKBDDHXXX"
                               class="block w-full rounded-lg border border-slate-200 px-3 py-2 text-sm font-mono uppercase focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500">
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-medium text-slate-600 mb-1">
                        Internal notes
                    </label>
                    <textarea name="notes"
                              rows="3"
                              placeholder="e.g. Dedicated for LC settlements and large vendor payments."
                              class="block w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500"><?= $h($notes) ?></textarea>
                </div>
            </div>

            <!-- RIGHT: opening & status -->
            <div class="space-y-5">
                <div>
                    <h2 class="text-sm font-semibold text-slate-800 mb-1">Opening & status</h2>
                    <p class="text-xs text-slate-500">
                        Set the starting balance and control whether this account is visible for posting.
                    </p>
                </div>

                <div class="grid gap-4">
                    <div>
                        <label class="block text-xs font-medium text-slate-600 mb-1">
                            Opening balance (BDT)
                        </label>
                        <input type="number"
                               step="0.01"
                               name="opening_balance"
                               value="<?= $openingBalance !== null && $openingBalance !== '' ? $h((string)$openingBalance) : '' ?>"
                               placeholder="e.g. 250000.00"
                               class="block w-full rounded-lg border border-slate-200 px-3 py-2 text-sm text-right focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500">
                    </div>

                    <div>
                        <label class="block text-xs font-medium text-slate-600 mb-1">
                            Opening date
                        </label>
                        <input type="date"
                               name="opening_date"
                               value="<?= $h($openingDate) ?>"
                               class="block w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500">
                    </div>
                </div>

                <div class="space-y-3">
                    <label class="inline-flex items-start gap-2 text-xs text-slate-700">
                        <input type="checkbox"
                               name="is_active"
                               value="1"
                               class="mt-0.5 h-3.5 w-3.5 rounded border-slate-300 text-emerald-600 focus:ring-emerald-500"
                               <?= $isActive ? 'checked' : '' ?>>
                        <span>
                            <span class="font-medium text-slate-800">Account is active</span>
                            <span class="block text-[11px] text-slate-500">
                                Active accounts show up on payment, receipt and reconciliation screens.
                            </span>
                        </span>
                    </label>

                    <label class="inline-flex items-start gap-2 text-xs text-slate-700">
                        <input type="checkbox"
                               name="is_default"
                               value="1"
                               class="mt-0.5 h-3.5 w-3.5 rounded border-slate-300 text-emerald-600 focus:ring-emerald-500"
                               <?= $isDefault ? 'checked' : '' ?>>
                        <span>
                            <span class="font-medium text-slate-800">Use as default reconciliation account</span>
                            <span class="block text-[11px] text-slate-500">
                                Mark this if you primarily reconcile statements for this currency through this account.
                            </span>
                        </span>
                    </label>
                </div>

                <div class="pt-3 border-t border-dashed border-slate-200">
                    <p class="text-[11px] text-slate-500 mb-2">
                        Saving this form will later create a <strong>biz_bank_accounts</strong> row
                        once the BizFlow banking schema is finalised. For now it is UI-only and safe to experiment.
                    </p>

                    <div class="flex flex-wrap gap-2 justify-end">
                        <a href="<?= $h($module_base.'/banking') ?>"
                           class="inline-flex items-center gap-1 rounded-lg border border-slate-200 px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50">
                            <i class="fa fa-xmark text-[11px]"></i>
                            <span>Cancel</span>
                        </a>
                        <button type="submit"
                                class="inline-flex items-center gap-2 rounded-lg bg-emerald-600 px-3 py-1.5 text-xs font-medium text-white shadow-sm hover:bg-emerald-700">
                            <i class="fa fa-save text-[11px]"></i>
                            <span>Save account</span>
                        </button>
                    </div>
                </div>
            </div>
        </form>
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
            <li>Fill in a clear <strong>Account name</strong> and link it to the real-world bank and branch.</li>
            <li>Set <strong>Currency</strong> and <strong>Account type</strong> to control how the account appears in payments and reports.</li>
            <li>Use <strong>Opening balance</strong> and <strong>Opening date</strong> to align BizFlow with your existing ledger.</li>
            <li>Tick <strong>Account is active</strong> so users can select this account when receiving or paying money.</li>
            <li>Mark <strong>Default reconciliation account</strong> if this is your main account for that currency.</li>
            <li>Later, once the banking schema is live, this form will write to <code>biz_bank_accounts</code> without any changes to the UI.</li>
        </ul>
    </section>
</div>