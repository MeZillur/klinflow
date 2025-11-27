<?php
declare(strict_types=1);

/**
 * @var array  $org
 * @var string $module_base
 * @var array  $lc
 * @var string $title
 * @var string $mode  create|edit  (optional, default create)
 */

$h = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');

$module_base = rtrim($module_base ?? '/apps/bizflow', '/');
$orgName     = trim((string)($org['name'] ?? '')) ?: 'your organisation';
$brand       = '#228B22';

$isEdit = (isset($mode) && $mode === 'edit');
$formAction = $isEdit
    ? $module_base . '/lcs/' . ($lc['id'] ?? '')
    : $module_base . '/lcs';

$pageTitle = $isEdit ? 'Edit LC' : 'Open new LC';
?>
<div class="space-y-6">

    <!-- Header + tabs -->
    <header class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
        <div>
            <h1 class="text-2xl md:text-3xl font-semibold tracking-tight">
                <?= $h($title ?? $pageTitle) ?>
            </h1>
            <p class="text-sm text-slate-500">
                Design your import Letter of Credit for <?= $h($orgName) ?> — from contract &amp; PI to shipment and retirement.
            </p>
        </div>

        <?php
        $tabs = [
            ['LC register', $module_base.'/lcs',        false],
            ['New LC',      $module_base.'/lcs/create', true],
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

    <div class="grid gap-6 lg:grid-cols-[2.2fr,1.1fr]">

        <!-- LEFT: main LC form -->
        <section class="rounded-2xl border border-slate-200 bg-white shadow-sm">
            <form method="post" action="<?= $h($formAction) ?>" class="space-y-6 px-4 py-4 text-xs md:text-[13px]">
                <input type="hidden" name="_mode" value="<?= $h($isEdit ? 'edit' : 'create') ?>">

                <!-- 1) Contract & PI block -->
                <div class="space-y-3 border-b border-slate-100 pb-4">
                    <div class="flex items-center justify-between">
                        <h2 class="text-sm font-semibold text-slate-800">
                            1. Contract &amp; PI / Indent
                        </h2>
                        <span class="inline-flex items-center gap-1 rounded-full bg-emerald-50 px-2 py-0.5 text-[11px] text-emerald-700">
                            <span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>
                            Pre-LC stage
                        </span>
                    </div>

                    <div class="grid gap-3 md:grid-cols-3">
                        <div>
                            <label class="mb-1 block font-medium text-slate-700">Contract no</label>
                            <input type="text"
                                   name="contract_no"
                                   value="<?= $h($lc['contract_no'] ?? '') ?>"
                                   placeholder="CNT-2025-001"
                                   class="w-full rounded-lg border border-slate-200 px-3 py-1.5 text-xs focus:border-emerald-600 focus:outline-none focus:ring-1 focus:ring-emerald-600">
                        </div>
                        <div>
                            <label class="mb-1 block font-medium text-slate-700">PI / Indent no</label>
                            <input type="text"
                                   name="pi_no"
                                   value="<?= $h($lc['pi_no'] ?? '') ?>"
                                   placeholder="PI-2401 / IND-01"
                                   class="w-full rounded-lg border border-slate-200 px-3 py-1.5 text-xs focus:border-emerald-600 focus:outline-none focus:ring-1 focus:ring-emerald-600">
                        </div>
                        <div>
                            <label class="mb-1 block font-medium text-slate-700">PI date</label>
                            <input type="date"
                                   name="pi_date"
                                   value="<?= $h($lc['pi_date'] ?? '') ?>"
                                   class="w-full rounded-lg border border-slate-200 px-3 py-1.5 text-xs focus:border-emerald-600 focus:outline-none focus:ring-1 focus:ring-emerald-600">
                        </div>
                    </div>

                    <div class="grid gap-3 md:grid-cols-2">
                        <div>
                            <label class="mb-1 block font-medium text-slate-700">Goods description (short)</label>
                            <input type="text"
                                   name="goods_short"
                                   value="<?= $h($lc['goods_short'] ?? '') ?>"
                                   placeholder="E.g. 100% cotton fabrics, 40ft container"
                                   class="w-full rounded-lg border border-slate-200 px-3 py-1.5 text-xs focus:border-emerald-600 focus:outline-none focus:ring-1 focus:ring-emerald-600">
                        </div>
                        <div>
                            <label class="mb-1 block font-medium text-slate-700">HS code(s)</label>
                            <input type="text"
                                   name="hs_codes"
                                   value="<?= $h($lc['hs_codes'] ?? '') ?>"
                                   placeholder="E.g. 5208.39, 5208.59"
                                   class="w-full rounded-lg border border-slate-200 px-3 py-1.5 text-xs focus:border-emerald-600 focus:outline-none focus:ring-1 focus:ring-emerald-600">
                        </div>
                    </div>
                </div>

                <!-- 2) Parties & banks -->
                <div class="space-y-3 border-b border-slate-100 pb-4">
                    <h2 class="text-sm font-semibold text-slate-800">
                        2. Applicant, beneficiary &amp; banks
                    </h2>

                    <div class="grid gap-3 md:grid-cols-2">
                        <div>
                            <label class="mb-1 block font-medium text-slate-700">Applicant (Importer)</label>
                            <input type="text"
                                   name="applicant_name"
                                   value="<?= $h($lc['applicant_name'] ?? ($org['name'] ?? '')) ?>"
                                   placeholder="Your organisation legal name"
                                   class="w-full rounded-lg border border-slate-200 px-3 py-1.5 text-xs focus:border-emerald-600 focus:outline-none focus:ring-1 focus:ring-emerald-600">
                        </div>
                        <div>
                            <label class="mb-1 block font-medium text-slate-700">Beneficiary (Exporter)</label>
                            <input type="text"
                                   name="beneficiary_name"
                                   value="<?= $h($lc['beneficiary_name'] ?? '') ?>"
                                   placeholder="Exporter / supplier name"
                                   class="w-full rounded-lg border border-slate-200 px-3 py-1.5 text-xs focus:border-emerald-600 focus:outline-none focus:ring-1 focus:ring-emerald-600">
                        </div>
                    </div>

                    <div class="grid gap-3 md:grid-cols-2">
                        <div>
                            <label class="mb-1 block font-medium text-slate-700">Issuing bank (Bangladesh)</label>
                            <input type="text"
                                   name="issuing_bank"
                                   value="<?= $h($lc['issuing_bank'] ?? '') ?>"
                                   placeholder="E.g. XYZ Bank Ltd., Motijheel Branch"
                                   class="w-full rounded-lg border border-slate-200 px-3 py-1.5 text-xs focus:border-emerald-600 focus:outline-none focus:ring-1 focus:ring-emerald-600">
                        </div>
                        <div>
                            <label class="mb-1 block font-medium text-slate-700">Advising / Negotiating bank</label>
                            <input type="text"
                                   name="advising_bank"
                                   value="<?= $h($lc['advising_bank'] ?? '') ?>"
                                   placeholder="Exporter side bank"
                                   class="w-full rounded-lg border border-slate-200 px-3 py-1.5 text-xs focus:border-emerald-600 focus:outline-none focus:ring-1 focus:ring-emerald-600">
                        </div>
                    </div>

                    <div class="grid gap-3 md:grid-cols-3">
                        <div>
                            <label class="mb-1 block font-medium text-slate-700">IRC / BIN</label>
                            <input type="text"
                                   name="irc_no"
                                   value="<?= $h($lc['irc_no'] ?? '') ?>"
                                   placeholder="Importer registration (optional here)"
                                   class="w-full rounded-lg border border-slate-200 px-3 py-1.5 text-xs focus:border-emerald-600 focus:outline-none focus:ring-1 focus:ring-emerald-600">
                        </div>
                        <div>
                            <label class="mb-1 block font-medium text-slate-700">LCA no</label>
                            <input type="text"
                                   name="lca_no"
                                   value="<?= $h($lc['lca_no'] ?? '') ?>"
                                   placeholder="Letter of Credit Authorization"
                                   class="w-full rounded-lg border border-slate-200 px-3 py-1.5 text-xs focus:border-emerald-600 focus:outline-none focus:ring-1 focus:ring-emerald-600">
                        </div>
                        <div>
                            <label class="mb-1 block font-medium text-slate-700">Country of origin</label>
                            <input type="text"
                                   name="country_of_origin"
                                   value="<?= $h($lc['country_of_origin'] ?? '') ?>"
                                   placeholder="China / India / EU…"
                                   class="w-full rounded-lg border border-slate-200 px-3 py-1.5 text-xs focus:border-emerald-600 focus:outline-none focus:ring-1 focus:ring-emerald-600">
                        </div>
                    </div>
                </div>

                <!-- 3) LC terms, amount & dates -->
                <div class="space-y-3 border-b border-slate-100 pb-4">
                    <h2 class="text-sm font-semibold text-slate-800">
                        3. LC terms, amount &amp; dates
                    </h2>

                    <div class="grid gap-3 md:grid-cols-4">
                        <div>
                            <label class="mb-1 block font-medium text-slate-700">LC no</label>
                            <input type="text"
                                   name="lc_no"
                                   value="<?= $h($lc['lc_no'] ?? '') ?>"
                                   placeholder="Auto from bank or internal ref"
                                   class="w-full rounded-lg border border-slate-200 px-3 py-1.5 text-xs focus:border-emerald-600 focus:outline-none focus:ring-1 focus:ring-emerald-600">
                        </div>
                        <div>
                            <label class="mb-1 block font-medium text-slate-700">LC type</label>
                            <?php $lcType = $lc['lc_type'] ?? 'sight'; ?>
                            <select name="lc_type"
                                    class="w-full rounded-lg border border-slate-200 px-2 py-1.5 text-xs focus:border-emerald-600 focus:outline-none focus:ring-1 focus:ring-emerald-600">
                                <option value="sight"   <?= $lcType === 'sight'   ? 'selected' : '' ?>>Sight LC</option>
                                <option value="usance"  <?= $lcType === 'usance'  ? 'selected' : '' ?>>Usance / deferred</option>
                                <option value="back_to_back" <?= $lcType === 'back_to_back' ? 'selected' : '' ?>>Back-to-back</option>
                                <option value="others"  <?= $lcType === 'others'  ? 'selected' : '' ?>>Other</option>
                            </select>
                        </div>
                        <div>
                            <label class="mb-1 block font-medium text-slate-700">Currency</label>
                            <input type="text"
                                   name="currency"
                                   value="<?= $h($lc['currency'] ?? 'USD') ?>"
                                   class="w-full rounded-lg border border-slate-200 px-3 py-1.5 text-xs focus:border-emerald-600 focus:outline-none focus:ring-1 focus:ring-emerald-600">
                        </div>
                        <div>
                            <label class="mb-1 block font-medium text-slate-700">LC amount</label>
                            <input type="number"
                                   step="0.01"
                                   min="0"
                                   name="lc_amount"
                                   value="<?= $h($lc['lc_amount'] ?? '') ?>"
                                   placeholder="0.00"
                                   class="w-full rounded-lg border border-slate-200 px-3 py-1.5 text-xs text-right focus:border-emerald-600 focus:outline-none focus:ring-1 focus:ring-emerald-600">
                        </div>
                    </div>

                    <div class="grid gap-3 md:grid-cols-4">
                        <div>
                            <label class="mb-1 block font-medium text-slate-700">Cash margin %</label>
                            <input type="number"
                                   step="0.01"
                                   min="0"
                                   max="100"
                                   name="margin_percent"
                                   value="<?= $h($lc['margin_percent'] ?? '') ?>"
                                   placeholder="E.g. 25"
                                   class="w-full rounded-lg border border-slate-200 px-3 py-1.5 text-xs text-right focus:border-emerald-600 focus:outline-none focus:ring-1 focus:ring-emerald-600">
                        </div>
                        <div>
                            <label class="mb-1 block font-medium text-slate-700">Margin amount</label>
                            <input type="number"
                                   step="0.01"
                                   min="0"
                                   name="margin_amount"
                                   value="<?= $h($lc['margin_amount'] ?? '') ?>"
                                   placeholder="Auto from % (optional)"
                                   class="w-full rounded-lg border border-slate-200 px-3 py-1.5 text-xs text-right focus:border-emerald-600 focus:outline-none focus:ring-1 focus:ring-emerald-600">
                        </div>
                        <div>
                            <label class="mb-1 block font-medium text-slate-700">Opened at</label>
                            <input type="date"
                                   name="opened_at"
                                   value="<?= $h($lc['opened_at'] ?? '') ?>"
                                   class="w-full rounded-lg border border-slate-200 px-3 py-1.5 text-xs focus:border-emerald-600 focus:outline-none focus:ring-1 focus:ring-emerald-600">
                        </div>
                        <div>
                            <label class="mb-1 block font-medium text-slate-700">Expiry date</label>
                            <input type="date"
                                   name="expiry_date"
                                   value="<?= $h($lc['expiry_date'] ?? '') ?>"
                                   class="w-full rounded-lg border border-slate-200 px-3 py-1.5 text-xs focus:border-emerald-600 focus:outline-none focus:ring-1 focus:ring-emerald-600">
                        </div>
                    </div>
                </div>

                <!-- 4) Shipment & logistics -->
                <div class="space-y-3 border-b border-slate-100 pb-4">
                    <h2 class="text-sm font-semibold text-slate-800">
                        4. Shipment &amp; ports
                    </h2>

                    <div class="grid gap-3 md:grid-cols-3">
                        <div>
                            <label class="mb-1 block font-medium text-slate-700">Last shipment date</label>
                            <input type="date"
                                   name="last_shipment_date"
                                   value="<?= $h($lc['last_shipment_date'] ?? '') ?>"
                                   class="w-full rounded-lg border border-slate-200 px-3 py-1.5 text-xs focus:border-emerald-600 focus:outline-none focus:ring-1 focus:ring-emerald-600">
                        </div>
                        <div>
                            <label class="mb-1 block font-medium text-slate-700">Port of loading</label>
                            <input type="text"
                                   name="port_of_loading"
                                   value="<?= $h($lc['port_of_loading'] ?? '') ?>"
                                   placeholder="Shanghai / Nhava Sheva…"
                                   class="w-full rounded-lg border border-slate-200 px-3 py-1.5 text-xs focus:border-emerald-600 focus:outline-none focus:ring-1 focus:ring-emerald-600">
                        </div>
                        <div>
                            <label class="mb-1 block font-medium text-slate-700">Port of discharge</label>
                            <input type="text"
                                   name="port_of_discharge"
                                   value="<?= $h($lc['port_of_discharge'] ?? '') ?>"
                                   placeholder="Chattogram / Mongla…"
                                   class="w-full rounded-lg border border-slate-200 px-3 py-1.5 text-xs focus:border-emerald-600 focus:outline-none focus:ring-1 focus:ring-emerald-600">
                        </div>
                    </div>

                    <div class="grid gap-3 md:grid-cols-3">
                        <div>
                            <label class="mb-1 block font-medium text-slate-700">Incoterm</label>
                            <input type="text"
                                   name="incoterm"
                                   value="<?= $h($lc['incoterm'] ?? '') ?>"
                                   placeholder="FOB / CFR / CIF…"
                                   class="w-full rounded-lg border border-slate-200 px-3 py-1.5 text-xs focus:border-emerald-600 focus:outline-none focus:ring-1 focus:ring-emerald-600">
                        </div>
                        <div>
                            <label class="mb-1 block font-medium text-slate-700">Partial shipment</label>
                            <?php $partial = $lc['partial_shipment'] ?? ''; ?>
                            <select name="partial_shipment"
                                    class="w-full rounded-lg border border-slate-200 px-2 py-1.5 text-xs focus:border-emerald-600 focus:outline-none focus:ring-1 focus:ring-emerald-600">
                                <option value="">As per LC</option>
                                <option value="allowed"  <?= $partial === 'allowed'  ? 'selected' : '' ?>>Allowed</option>
                                <option value="prohibited" <?= $partial === 'prohibited' ? 'selected' : '' ?>>Prohibited</option>
                            </select>
                        </div>
                        <div>
                            <label class="mb-1 block font-medium text-slate-700">Transshipment</label>
                            <?php $trans = $lc['transshipment'] ?? ''; ?>
                            <select name="transshipment"
                                    class="w-full rounded-lg border border-slate-200 px-2 py-1.5 text-xs focus:border-emerald-600 focus:outline-none focus:ring-1 focus:ring-emerald-600">
                                <option value="">As per LC</option>
                                <option value="allowed"  <?= $trans === 'allowed'  ? 'selected' : '' ?>>Allowed</option>
                                <option value="prohibited" <?= $trans === 'prohibited' ? 'selected' : '' ?>>Prohibited</option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- 5) Insurance & maturity -->
                <div class="space-y-3 border-b border-slate-100 pb-4">
                    <h2 class="text-sm font-semibold text-slate-800">
                        5. Insurance &amp; maturity (Bangladesh practice)
                    </h2>

                    <div class="grid gap-3 md:grid-cols-3">
                        <div>
                            <label class="mb-1 block font-medium text-slate-700">Insurance policy no</label>
                            <input type="text"
                                   name="insurance_policy_no"
                                   value="<?= $h($lc['insurance_policy_no'] ?? '') ?>"
                                   placeholder="Local insurer policy / cover note"
                                   class="w-full rounded-lg border border-slate-200 px-3 py-1.5 text-xs focus:border-emerald-600 focus:outline-none focus:ring-1 focus:ring-emerald-600">
                        </div>
                        <div>
                            <label class="mb-1 block font-medium text-slate-700">Maturity date (if usance)</label>
                            <input type="date"
                                   name="maturity_date"
                                   value="<?= $h($lc['maturity_date'] ?? '') ?>"
                                   class="w-full rounded-lg border border-slate-200 px-3 py-1.5 text-xs focus:border-emerald-600 focus:outline-none focus:ring-1 focus:ring-emerald-600">
                        </div>
                        <div>
                            <label class="mb-1 block font-medium text-slate-700">Docs received at bank</label>
                            <input type="date"
                                   name="docs_received_at"
                                   value="<?= $h($lc['docs_received_at'] ?? '') ?>"
                                   class="w-full rounded-lg border border-slate-200 px-3 py-1.5 text-xs focus:border-emerald-600 focus:outline-none focus:ring-1 focus:ring-emerald-600">
                        </div>
                    </div>
                </div>

                <!-- 6) Status & notes -->
                <div class="space-y-3">
                    <h2 class="text-sm font-semibold text-slate-800">
                        6. Status &amp; internal notes
                    </h2>

                    <div class="grid gap-3 md:grid-cols-3">
                        <div>
                            <label class="mb-1 block font-medium text-slate-700">Status</label>
                            <?php $status = strtolower((string)($lc['status'] ?? 'open')); ?>
                            <select name="status"
                                    class="w-full rounded-lg border border-slate-200 px-2 py-1.5 text-xs focus:border-emerald-600 focus:outline-none focus:ring-1 focus:ring-emerald-600">
                                <option value="open"              <?= $status === 'open'              ? 'selected' : '' ?>>Open</option>
                                <option value="documents_pending" <?= $status === 'documents_pending' ? 'selected' : '' ?>>Docs pending</option>
                                <option value="matured"           <?= $status === 'matured'           ? 'selected' : '' ?>>Matured</option>
                                <option value="retired"           <?= $status === 'retired'           ? 'selected' : '' ?>>Retired</option>
                                <option value="cancelled"         <?= $status === 'cancelled'         ? 'selected' : '' ?>>Cancelled</option>
                            </select>
                        </div>
                        <div>
                            <label class="mb-1 block font-medium text-slate-700">Lifecycle stage</label>
                            <?php $stage = strtolower((string)($lc['stage'] ?? 'contract')); ?>
                            <select name="stage"
                                    class="w-full rounded-lg border border-slate-200 px-2 py-1.5 text-xs focus:border-emerald-600 focus:outline-none focus:ring-1 focus:ring-emerald-600">
                                <option value="contract"           <?= $stage === 'contract'           ? 'selected' : '' ?>>Contract / PI</option>
                                <option value="opened"             <?= $stage === 'opened'             ? 'selected' : '' ?>>LC opened</option>
                                <option value="shipped"            <?= $stage === 'shipped'            ? 'selected' : '' ?>>Goods shipped</option>
                                <option value="documents_received" <?= $stage === 'documents_received' ? 'selected' : '' ?>>Docs received</option>
                                <option value="matured"            <?= $stage === 'matured'            ? 'selected' : '' ?>>Matured</option>
                                <option value="retired"            <?= $stage === 'retired'            ? 'selected' : '' ?>>Retired</option>
                            </select>
                        </div>
                        <div>
                            <label class="mb-1 block font-medium text-slate-700">Internal reference / tag</label>
                            <input type="text"
                                   name="internal_ref"
                                   value="<?= $h($lc['internal_ref'] ?? '') ?>"
                                   placeholder="E.g. FY25-RMG-IMPORT-01"
                                   class="w-full rounded-lg border border-slate-200 px-3 py-1.5 text-xs focus:border-emerald-600 focus:outline-none focus:ring-1 focus:ring-emerald-600">
                        </div>
                    </div>

                    <div>
                        <label class="mb-1 block font-medium text-slate-700">Internal notes (bank conditions, BB margin, special clauses)</label>
                        <textarea name="notes"
                                  rows="3"
                                  class="w-full rounded-lg border border-slate-200 px-3 py-2 text-xs focus:border-emerald-600 focus:outline-none focus:ring-1 focus:ring-emerald-600"
                                  placeholder="E.g. Bangladesh Bank margin category, LC conditions, tolerance %, special instructions…"><?= $h($lc['notes'] ?? '') ?></textarea>
                    </div>
                </div>

                <!-- Actions -->
                <div class="mt-4 flex items-center justify-between border-t border-slate-100 pt-3">
                    <p class="text-[11px] text-slate-500">
                        This is a UI-first preview. Data will be wired to the <code>biz_lcs</code> engine in the next phase.
                    </p>
                    <div class="flex gap-2">
                        <a href="<?= $h($module_base.'/lcs') ?>"
                           class="rounded-lg border border-slate-200 px-3 py-1.5 text-[11px] text-slate-600 hover:bg-slate-50">
                            Cancel
                        </a>
                        <button type="submit"
                                class="rounded-lg bg-emerald-600 px-4 py-1.5 text-[11px] font-semibold text-white shadow-sm hover:bg-emerald-700">
                            <?= $isEdit ? 'Save changes' : 'Save LC draft' ?>
                        </button>
                    </div>
                </div>
            </form>
        </section>

        <!-- RIGHT: Help / How to use -->
        <aside class="space-y-4">
            <section class="rounded-2xl border border-dashed border-emerald-400 bg-emerald-50/60 px-4 py-4 text-sm text-slate-800">
                <h2 class="mb-2 flex items-center gap-2 text-sm font-semibold text-emerald-900">
                    <span class="inline-flex h-6 w-6 items-center justify-center rounded-full bg-emerald-600 text-[11px] text-white">
                        ?
                    </span>
                    How to use this page
                </h2>
                <ul class="ml-6 list-disc space-y-1 text-[13px]">
                    <li>Step-1: Fill in <strong>Contract &amp; PI</strong> details once buyer–seller terms are agreed.</li>
                    <li>Step-2: Capture <strong>applicant, beneficiary and banks</strong> as per the draft LC.</li>
                    <li>Step-3: Enter <strong>LC type, amount, margin and dates</strong> exactly as the bank will issue.</li>
                    <li>Step-4: Define <strong>ports, shipment window, Incoterm</strong> and partial / transshipment rules.</li>
                    <li>Step-5: Record <strong>LCA and insurance policy</strong> so compliance is traceable in one place.</li>
                    <li>Step-6: Use <strong>Status</strong> and <strong>Lifecycle stage</strong> to track from opening up to retirement.</li>
                </ul>
            </section>

            <section class="rounded-2xl border border-slate-200 bg-white px-4 py-4 text-xs text-slate-700">
                <h2 class="mb-2 text-sm font-semibold text-slate-900">Bangladesh LC notes</h2>
                <ul class="ml-5 list-disc space-y-1">
                    <li>Bangladesh Bank may change <strong>cash margin %</strong> per product category — store it here.</li>
                    <li>LC type, shipment window and ports will later link to <strong>imports, GRNs and bill of entry</strong> inside BizFlow.</li>
                    <li>Once the schema is live, this screen will become the single source of truth for every LC in your organisation.</li>
                </ul>
            </section>
        </aside>
    </div>
</div>