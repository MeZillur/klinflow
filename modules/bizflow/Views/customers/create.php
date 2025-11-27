<?php
/** @var array       $org */
/** @var string      $module_base */
/** @var string|null $title */
/** @var array|null  $old */
/** @var array|null  $errors */

$h = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');

$module_base = rtrim($module_base ?? ($base ?? '/apps/bizflow'), '/');
$orgName     = $org['name'] ?? '';

$old    = $old    ?? [];
$errors = $errors ?? [];

$val = function(string $key, $default = '') use ($old) {
    return $old[$key] ?? $default;
};

$isActive = array_key_exists('is_active', $old)
    ? (int)$old['is_active'] === 1
    : true; // default active

// Tabs (BizFlow-wide)
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
$currentTab = $module_base.'/customers';
$formAction = $module_base.'/customers';
?>
<div class="space-y-6" x-data="{ showMore:false }">
    <!-- Top: title + tabs -->
    <header class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
        <div class="space-y-1">
            <h1 class="text-2xl md:text-3xl font-semibold tracking-tight">
                <?= $h($title ?? 'New customer') ?>
            </h1>
            <p class="text-sm text-slate-500">
                Add a new customer for <?= $h($orgName ?: 'your organisation') ?> with clean contact, address and credit terms.
            </p>
        </div>

        <!-- Right-aligned app tabs -->
        <nav class="flex flex-wrap justify-end gap-1 text-sm">
            <?php foreach ($tabs as [$label, $url]): ?>
                <?php $active = ($url === $currentTab); ?>
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

    <!-- Form wrapper -->
    <section class="rounded-xl border border-slate-200 bg-white shadow-sm">
        <form method="post" action="<?= $h($formAction) ?>" class="space-y-6 px-4 py-4 md:px-6 md:py-5">
            <!-- If your controller injects CSRF, it will be used -->
            <?php if (isset($csrf) && $csrf !== ''): ?>
                <input type="hidden" name="_token" value="<?= $h($csrf) ?>">
            <?php endif; ?>

            <!-- Top row: status + quick info -->
            <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                <div class="space-y-1">
                    <div class="inline-flex items-center gap-2 rounded-full bg-emerald-50 px-3 py-1 text-[11px] text-emerald-700">
                        <span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>
                        New customer profile
                    </div>
                    <p class="text-xs text-slate-500">
                        Capture at least name and phone/email. You can refine segments and credit terms later.
                    </p>
                </div>

                <label class="inline-flex items-center gap-2 text-xs text-slate-700">
                    <input type="checkbox"
                           name="is_active"
                           value="1"
                           class="h-3.5 w-3.5 rounded border-slate-300 text-emerald-600 focus:ring-emerald-500"
                           <?= $isActive ? 'checked' : '' ?>>
                    <span>Active customer (can transact)</span>
                </label>
            </div>

            <!-- Grid: left (profile) / right (financial) -->
            <div class="grid gap-6 lg:grid-cols-[2fr,1.2fr]">

                <!-- LEFT: core profile + contact -->
                <div class="space-y-5">
                    <!-- Core profile -->
                    <div>
                        <h2 class="mb-3 text-sm font-semibold text-slate-800">Core profile</h2>
                        <div class="grid gap-3 sm:grid-cols-2">
                            <div>
                                <label class="block text-xs font-medium text-slate-600 mb-1">
                                    Customer name <span class="text-red-500">*</span>
                                </label>
                                <input type="text"
                                       name="name"
                                       value="<?= $h($val('name')) ?>"
                                       required
                                       class="block w-full rounded-lg border border-slate-200 px-3 py-2 text-sm
                                              focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500">
                            </div>

                            <div>
                                <label class="block text-xs font-medium text-slate-600 mb-1">
                                    Customer code
                                </label>
                                <input type="text"
                                       name="code"
                                       value="<?= $h($val('code')) ?>"
                                       placeholder="Optional internal code (e.g. CUST-001)"
                                       class="block w-full rounded-lg border border-slate-200 px-3 py-2 text-sm
                                              font-mono placeholder:text-slate-300
                                              focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500">
                            </div>

                            <div>
                                <label class="block text-xs font-medium text-slate-600 mb-1">
                                    Segment
                                </label>
                                <select name="segment"
                                        class="block w-full rounded-lg border border-slate-200 px-3 py-2 text-sm bg-white
                                               focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500">
                                    <?php
                                    $segmentVal = (string)$val('segment');
                                    $segments   = [
                                        ''            => 'Not set',
                                        'retail'      => 'Retail',
                                        'wholesale'   => 'Wholesale',
                                        'corporate'   => 'Corporate',
                                        'ngo'         => 'NGO / INGO',
                                        'government'  => 'Government',
                                        'other'       => 'Other',
                                    ];
                                    foreach ($segments as $key => $label):
                                        $sel = ($segmentVal === $key) ? 'selected' : '';
                                    ?>
                                        <option value="<?= $h($key) ?>" <?= $sel ?>>
                                            <?= $h($label) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div>
                                <label class="block text-xs font-medium text-slate-600 mb-1">
                                    Company / organisation
                                </label>
                                <input type="text"
                                       name="company_name"
                                       value="<?= $h($val('company_name')) ?>"
                                       placeholder="e.g. TRT Solution"
                                       class="block w-full rounded-lg border border-slate-200 px-3 py-2 text-sm
                                              focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500">
                            </div>
                        </div>
                    </div>

                    <!-- Contact -->
                    <div>
                        <h2 class="mb-3 text-sm font-semibold text-slate-800">Contact</h2>
                        <div class="grid gap-3 sm:grid-cols-2">
                            <div>
                                <label class="block text-xs font-medium text-slate-600 mb-1">
                                    Primary phone
                                </label>
                                <input type="text"
                                       name="phone"
                                       value="<?= $h($val('phone')) ?>"
                                       placeholder="+8801XXXXXXXXX"
                                       class="block w-full rounded-lg border border-slate-200 px-3 py-2 text-sm
                                              focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500">
                            </div>

                            <div>
                                <label class="block text-xs font-medium text-slate-600 mb-1">
                                    Alternate phone
                                </label>
                                <input type="text"
                                       name="alt_phone"
                                       value="<?= $h($val('alt_phone')) ?>"
                                       placeholder="Optional backup number"
                                       class="block w-full rounded-lg border border-slate-200 px-3 py-2 text-sm
                                              focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500">
                            </div>

                            <div class="sm:col-span-2">
                                <label class="block text-xs font-medium text-slate-600 mb-1">
                                    Email
                                </label>
                                <input type="email"
                                       name="email"
                                       value="<?= $h($val('email')) ?>"
                                       placeholder="customer@example.com"
                                       class="block w-full rounded-lg border border-slate-200 px-3 py-2 text-sm
                                              focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500">
                            </div>
                        </div>
                    </div>

                    <!-- Address -->
                    <div>
                        <h2 class="mb-3 text-sm font-semibold text-slate-800">Address</h2>
                        <div class="grid gap-3 sm:grid-cols-2">
                            <div>
                                <label class="block text-xs font-medium text-slate-600 mb-1">
                                    City
                                </label>
                                <input type="text"
                                       name="city"
                                       value="<?= $h($val('city')) ?>"
                                       class="block w-full rounded-lg border border-slate-200 px-3 py-2 text-sm
                                              focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-slate-600 mb-1">
                                    District
                                </label>
                                <input type="text"
                                       name="district"
                                       value="<?= $h($val('district')) ?>"
                                       class="block w-full rounded-lg border border-slate-200 px-3 py-2 text-sm
                                              focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500">
                            </div>
                            <div class="sm:col-span-2">
                                <label class="block text-xs font-medium text-slate-600 mb-1">
                                    Country
                                </label>
                                <input type="text"
                                       name="country"
                                       value="<?= $h($val('country', 'Bangladesh')) ?>"
                                       class="block w-full rounded-lg border border-slate-200 px-3 py-2 text-sm
                                              focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- RIGHT: financial / meta -->
                <div class="space-y-5">
                    <!-- Financial terms -->
                    <div>
                        <h2 class="mb-3 text-sm font-semibold text-slate-800">Financial terms</h2>
                        <div class="space-y-3">
                            <div>
                                <label class="block text-xs font-medium text-slate-600 mb-1">
                                    Credit limit (BDT)
                                </label>
                                <input type="number"
                                       step="0.01"
                                       name="credit_limit"
                                       value="<?= $h($val('credit_limit')) ?>"
                                       placeholder="0.00"
                                       class="block w-full rounded-lg border border-slate-200 px-3 py-2 text-sm text-right
                                              focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500">
                            </div>

                            <div>
                                <label class="block text-xs font-medium text-slate-600 mb-1">
                                    Payment terms
                                </label>
                                <input type="text"
                                       name="payment_terms"
                                       value="<?= $h($val('payment_terms')) ?>"
                                       placeholder="e.g. 30 days, advance, partial"
                                       class="block w-full rounded-lg border border-slate-200 px-3 py-2 text-sm
                                              focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500">
                            </div>

                            <div>
                                <label class="block text-xs font-medium text-slate-600 mb-1">
                                    Tax / VAT registration
                                </label>
                                <input type="text"
                                       name="tax_number"
                                       value="<?= $h($val('tax_number')) ?>"
                                       placeholder="e.g. BIN or VAT number"
                                       class="block w-full rounded-lg border border-slate-200 px-3 py-2 text-sm
                                              font-mono placeholder:text-slate-300
                                              focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500">
                            </div>
                        </div>
                    </div>

                    <!-- Extra meta -->
                    <div>
                        <div class="flex items-center justify-between mb-2">
                            <h2 class="text-sm font-semibold text-slate-800">Additional notes</h2>
                            <button type="button"
                                    class="text-[11px] font-medium text-emerald-700 hover:underline"
                                    @click="showMore = !showMore">
                                <span x-show="!showMore">Show more fields</span>
                                <span x-show="showMore">Hide extra fields</span>
                            </button>
                        </div>

                        <textarea name="notes"
                                  rows="3"
                                  placeholder="Any extra context about pricing, contracts or restrictions."
                                  class="block w-full rounded-lg border border-slate-200 px-3 py-2 text-sm
                                         focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500"><?= $h($val('notes')) ?></textarea>

                        <div class="mt-3 space-y-3" x-show="showMore" x-cloak>
                            <div>
                                <label class="block text-xs font-medium text-slate-600 mb-1">
                                    Billing address (override)
                                </label>
                                <textarea name="billing_address"
                                          rows="2"
                                          class="block w-full rounded-lg border border-slate-200 px-3 py-2 text-sm
                                                 focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500"><?= $h($val('billing_address')) ?></textarea>
                            </div>

                            <div>
                                <label class="block text-xs font-medium text-slate-600 mb-1">
                                    Internal tags
                                </label>
                                <input type="text"
                                       name="tags"
                                       value="<?= $h($val('tags')) ?>"
                                       placeholder="e.g. key, repeat, risk, VIP"
                                       class="block w-full rounded-lg border border-slate-200 px-3 py-2 text-sm
                                              focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500">
                                <p class="mt-1 text-[11px] text-slate-400">
                                    Comma-separated labels for internal analytics.
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Footer actions -->
            <div class="flex flex-col gap-3 border-t border-slate-100 pt-4 sm:flex-row sm:items-center sm:justify-between">
                <p class="text-[11px] text-slate-500">
                    When you save, this customer becomes available in <strong>quotes, orders, invoices &amp; payments</strong> in BizFlow.
                </p>
                <div class="flex flex-wrap gap-2 justify-end">
                    <a href="<?= $h($module_base.'/customers') ?>"
                       class="inline-flex items-center gap-1 rounded-lg border border-slate-200 px-3 py-2 text-xs font-medium text-slate-700 hover:bg-slate-50">
                        <i class="fa fa-arrow-left text-[10px]"></i>
                        <span>Back to customers</span>
                    </a>
                    <button type="submit"
                            class="inline-flex items-center gap-2 rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-emerald-700 focus:outline-none focus:ring-2 focus:ring-emerald-600 focus:ring-offset-1">
                        <i class="fa fa-floppy-disk text-xs"></i>
                        <span>Save customer</span>
                    </button>
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
            <li>Fill in at least <strong>Customer name</strong> and one contact method (phone or email).</li>
            <li>Use <strong>Segment</strong> to classify by business type (Retail, Corporate, NGO, etc.).</li>
            <li>Set a <strong>Credit limit</strong> and <strong>Payment terms</strong> if you extend credit in BDT.</li>
            <li>Use extra notes and tags to capture contract details or internal flags (VIP, at-risk, etc.).</li>
            <li>After saving, this customer is immediately available across BizFlow for quotes, orders and invoices.</li>
        </ul>
    </section>
</div>