<?php
/** @var array       $tender */
/** @var array       $org */
/** @var string      $module_base */
/** @var string      $title */
/** @var string      $mode */
/** @var string|null $next_no */
/** @var string|null $today */

$h = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');

$module_base = rtrim($module_base ?? ($base ?? '/apps/bizflow'), '/');
$orgName     = $org['name'] ?? '';

$mode        = $mode ?? 'create';
$isEdit      = ($mode === 'edit');

$id          = (int)($tender['id'] ?? 0);
$actionUrl   = $isEdit
    ? $module_base.'/tenders/'.$id
    : $module_base.'/tenders';

$httpMethod  = $isEdit ? 'POST' : 'POST'; // controller may treat POST with id as update
$today       = $today ?? date('Y-m-d');

$code        = $tender['code']        ?? $tender['tender_no'] ?? ($next_no ?? '');
$subject     = $tender['title']       ?? $tender['subject']   ?? '';
$type        = strtolower((string)($tender['type'] ?? 'rfq'));
$status      = strtolower((string)($tender['status'] ?? 'draft'));
$customerId  = $tender['customer_id'] ?? null;
$customerName= $tender['customer_name'] ?? '';
$customerRef = $tender['customer_ref'] ?? '';
$channel     = $tender['channel']     ?? '';
$publishDate = $tender['publish_date']?? $today;
$dueDate     = $tender['due_date']    ?? '';
$openingDate = $tender['opening_date']?? '';
$currency    = $tender['currency']    ?? 'BDT';
$budget      = $tender['estimated_value'] ?? $tender['budget_amount'] ?? '';
$location    = $tender['location']    ?? '';
$country     = $tender['country']     ?? '';
$scope       = $tender['scope']       ?? $tender['description'] ?? '';
$notes       = $tender['internal_notes'] ?? '';
?>
<div class="space-y-6">

    <!-- Header -->
    <header class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
        <div class="space-y-1.5">
            <h1 class="text-2xl md:text-3xl font-semibold tracking-tight">
                <?= $isEdit ? $h('Edit tender') : $h('New tender / RFQ') ?>
            </h1>
            <p class="text-xs md:text-sm text-slate-500">
                Capture opportunity details so BizFlow can follow the tender from draft to award for <?= $h($orgName ?: 'your organisation') ?>.
            </p>
        </div>

        <!-- Simple breadcrumb back -->
        <nav class="flex flex-wrap justify-end gap-1 text-xs md:text-[13px]">
            <a href="<?= $h($module_base.'/tenders') ?>"
               class="inline-flex items-center gap-1 rounded-full px-3 py-1 border border-slate-200 text-slate-600 hover:bg-slate-50">
                <i class="fa fa-arrow-left text-[11px]"></i>
                <span>Back to tenders</span>
            </a>
        </nav>
    </header>

    <!-- Form -->
    <form action="<?= $h($actionUrl) ?>" method="post" enctype="multipart/form-data" class="space-y-6">
        <!-- If you use method override for PUT/PATCH, add hidden _method here -->

        <!-- Top layout -->
        <section class="grid gap-6 lg:grid-cols-[2fr,1fr]">
            <!-- LEFT: core fields -->
            <div class="space-y-4 rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                <div class="grid gap-3 sm:grid-cols-2">
                    <div>
                        <label class="block text-xs font-medium text-slate-600 mb-1">Tender / RFQ code</label>
                        <input type="text"
                               name="code"
                               value="<?= $h($code) ?>"
                               placeholder="Auto or manual (e.g. RFQ-2025-001)"
                               class="block w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-600 mb-1">Type</label>
                        <select name="type"
                                class="block w-full rounded-lg border border-slate-200 px-3 py-2 text-sm bg-white focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500">
                            <option value="rfq"        <?= $type === 'rfq'        ? 'selected' : '' ?>>RFQ</option>
                            <option value="tender"     <?= $type === 'tender'     ? 'selected' : '' ?>>Tender</option>
                            <option value="rfp"        <?= $type === 'rfp'        ? 'selected' : '' ?>>RFP</option>
                            <option value="framework"  <?= $type === 'framework'  ? 'selected' : '' ?>>Framework agreement</option>
                        </select>
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-medium text-slate-600 mb-1">Title / subject</label>
                    <input type="text"
                           name="title"
                           value="<?= $h($subject) ?>"
                           placeholder="e.g. Supply of medical equipment for emergency response"
                           required
                           class="block w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500">
                </div>

                <!-- Customer -->
                <div class="grid gap-3 sm:grid-cols-[2fr,1fr]">
                    <div>
                        <label class="block text-xs font-medium text-slate-600 mb-1">Customer</label>
                        <div class="relative">
                            <input type="hidden" name="customer_id" id="customer_id" value="<?= $h((string)($customerId ?? '')) ?>">
                            <input type="text"
                                   name="customer_name"
                                   id="customer_name"
                                   value="<?= $h($customerName) ?>"
                                   placeholder="Type to search customer"
                                   data-kf-lookup="customers"
                                   data-kf-target-id="#customer_id"
                                   data-kf-target-name="#customer_name"
                                   class="block w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500">
                            <span class="pointer-events-none absolute inset-y-0 right-3 flex items-center text-slate-400">
                                <i class="fa fa-search text-[11px]"></i>
                            </span>
                        </div>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-600 mb-1">Customer ref #</label>
                        <input type="text"
                               name="customer_ref"
                               value="<?= $h($customerRef) ?>"
                               placeholder="RFP ref as per client"
                               class="block w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500">
                    </div>
                </div>

                <!-- Dates -->
                <div class="grid gap-3 sm:grid-cols-3">
                    <div>
                        <label class="block text-xs font-medium text-slate-600 mb-1">Publish date</label>
                        <input type="date"
                               name="publish_date"
                               value="<?= $h($publishDate) ?>"
                               class="block w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-600 mb-1">Bid closing date</label>
                        <input type="date"
                               name="due_date"
                               value="<?= $h($dueDate) ?>"
                               required
                               class="block w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-600 mb-1">Opening / evaluation date</label>
                        <input type="date"
                               name="opening_date"
                               value="<?= $h($openingDate) ?>"
                               class="block w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500">
                    </div>
                </div>

                <!-- Budget / location -->
                <div class="grid gap-3 sm:grid-cols-3">
                    <div>
                        <label class="block text-xs font-medium text-slate-600 mb-1">Estimated value</label>
                        <input type="number"
                               step="0.01"
                               min="0"
                               name="estimated_value"
                               value="<?= $h((string)$budget) ?>"
                               placeholder="0.00"
                               class="block w-full rounded-lg border border-slate-200 px-3 py-2 text-sm text-right focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500">
                        <p class="mt-1 text-[11px] text-slate-500">Always store in BDT equivalent.</p>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-600 mb-1">Currency (display)</label>
                        <input type="text"
                               name="currency"
                               value="<?= $h($currency) ?>"
                               class="block w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-600 mb-1">Channel</label>
                        <input type="text"
                               name="channel"
                               value="<?= $h($channel) ?>"
                               placeholder="e.g. e-GP, Email, NGO Portal"
                               class="block w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500">
                    </div>
                </div>

                <div class="grid gap-3 sm:grid-cols-2">
                    <div>
                        <label class="block text-xs font-medium text-slate-600 mb-1">City / location</label>
                        <input type="text"
                               name="location"
                               value="<?= $h($location) ?>"
                               placeholder="e.g. Dhaka, Cox's Bazar"
                               class="block w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-600 mb-1">Country</label>
                        <input type="text"
                               name="country"
                               value="<?= $h($country) ?>"
                               placeholder="e.g. Bangladesh"
                               class="block w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500">
                    </div>
                </div>

                <!-- Scope -->
                <div>
                    <label class="block text-xs font-medium text-slate-600 mb-1">Scope / description</label>
                    <textarea name="scope"
                              rows="5"
                              placeholder="Paste key scope items, lots, and any mandatory requirements."
                              class="block w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500"><?= $h($scope) ?></textarea>
                </div>
            </div>

            <!-- RIGHT: status + internal notes + files -->
            <aside class="space-y-4">

                <!-- Status card -->
                <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm space-y-3">
                    <div>
                        <label class="block text-xs font-medium text-slate-600 mb-1">Status</label>
                        <select name="status"
                                class="block w-full rounded-lg border border-slate-200 px-3 py-2 text-sm bg-white focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500">
                            <option value="draft"      <?= $status === 'draft'      ? 'selected' : '' ?>>Draft</option>
                            <option value="published"  <?= $status === 'published'  ? 'selected' : '' ?>>Published</option>
                            <option value="bidding"    <?= $status === 'bidding'    ? 'selected' : '' ?>>Bidding</option>
                            <option value="evaluation" <?= $status === 'evaluation' ? 'selected' : '' ?>>Evaluation</option>
                            <option value="awarded"    <?= $status === 'awarded'    ? 'selected' : '' ?>>Awarded</option>
                            <option value="lost"       <?= $status === 'lost'       ? 'selected' : '' ?>>Lost</option>
                            <option value="cancelled"  <?= $status === 'cancelled'  ? 'selected' : '' ?>>Cancelled</option>
                        </select>
                    </div>
                    <p class="text-[11px] text-slate-500">
                        Draft → Published → Bidding → Evaluation → Awarded / Lost. You can update any time as the opportunity progresses.
                    </p>
                </div>

                <!-- Internal notes -->
                <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                    <label class="block text-xs font-medium text-slate-600 mb-1">Internal notes (not shared with customer)</label>
                    <textarea name="internal_notes"
                              rows="4"
                              placeholder="Pricing assumptions, competitor info, risk notes..."
                              class="block w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500"><?= $h($notes) ?></textarea>
                </div>

                <!-- Files (basic, backend will handle) -->
                <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                    <label class="block text-xs font-medium text-slate-600 mb-1">Attach documents</label>
                    <input type="file"
                           name="files[]"
                           multiple
                           class="block w-full text-xs text-slate-600 file:mr-3 file:rounded-md file:border-0 file:bg-emerald-600 file:px-3 file:py-1.5 file:text-xs file:font-medium file:text-white hover:file:bg-emerald-700">
                    <p class="mt-1 text-[11px] text-slate-500">
                        Attach RFP, BOQ, clarifications, or any supporting files. Max size limits will be enforced server-side.
                    </p>
                </div>
            </aside>
        </section>

        <!-- Footer actions -->
        <section class="flex flex-col-reverse gap-3 md:flex-row md:items-center md:justify-between">
            <p class="text-[11px] text-slate-500">
                BizFlow will store this tender under your current organisation. Amounts are tracked in BDT for reporting.
            </p>
            <div class="flex gap-2 justify-end">
                <a href="<?= $h($module_base.'/tenders') ?>"
                   class="inline-flex items-center gap-1 rounded-lg border border-slate-200 px-3 py-2 text-xs font-medium text-slate-700 hover:bg-slate-50">
                    <i class="fa fa-xmark text-[11px]"></i>
                    <span>Cancel</span>
                </a>
                <button type="submit"
                        class="inline-flex items-center gap-2 rounded-lg bg-emerald-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-emerald-700 focus:outline-none focus:ring-2 focus:ring-emerald-600 focus:ring-offset-1">
                    <i class="fa fa-floppy-disk text-xs"></i>
                    <span><?= $isEdit ? 'Save changes' : 'Save tender' ?></span>
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
            <li>Capture the <strong>title, customer, dates, and budget</strong> to anchor the opportunity.</li>
            <li>Use the <strong>status</strong> field to move the tender through your pipeline (Draft → Awarded).</li>
            <li>Always keep the value in <strong>BDT equivalent</strong> even if the RFP is in USD/EUR.</li>
            <li>Store <strong>internal notes</strong> for your team’s strategy and do not share them with the client.</li>
            <li>Attach <strong>all key documents</strong> so your team can work from BizFlow instead of scattered drives.</li>
        </ul>
    </section>
</div>