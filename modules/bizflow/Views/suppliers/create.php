<?php
/** @var array|null $supplier */
/** @var string|null $mode */
/** @var string $module_base */
/** @var array $org */

$h = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');

/* ---- Safe defaults so create() can call this without passing extras ---- */
if (!isset($supplier) || !is_array($supplier)) {
    $supplier = [];
}

$mode   = isset($mode) ? (string)$mode : 'create';
$isEdit = ($mode === 'edit' && !empty($supplier['id'] ?? null));

$module_base = rtrim($module_base ?? ($base ?? '/apps/bizflow'), '/');
$orgName     = $org['name'] ?? '';

$action = $isEdit
    ? $module_base . '/suppliers/' . (int)$supplier['id']
    : $module_base . '/suppliers';

/** Small helper for sticky form values + existing supplier values */
$val = function (string $key, $fallback = '') use (&$supplier) {
    if (isset($_POST[$key])) {
        return trim((string)$_POST[$key]);
    }
    if (is_array($supplier) && array_key_exists($key, $supplier)) {
        return trim((string)$supplier[$key]);
    }
    return $fallback;
};

$is_active = array_key_exists('is_active', $supplier)
    ? ((int)$supplier['is_active'] === 1)
    : true;

$type = (string)$val('type', 'local');
if (!in_array($type, ['local', 'international'], true)) {
    $type = 'local';
}
?>
<div class="space-y-6"
     x-data="{
        name: '<?= $h($val('name')) ?>',
        code: '<?= $h($val('code')) ?>',
        type: '<?= $h($type) ?>',
        city: '<?= $h($val('city')) ?>',
        district: '<?= $h($val('district')) ?>',
        country: '<?= $h($val('country','Bangladesh')) ?>',
        phone: '<?= $h($val('phone')) ?>',
        email: '<?= $h($val('email')) ?>'
     }">

    <!-- Top bar + tabs -->
    <header class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
        <div class="space-y-1">
            <h1 class="text-2xl md:text-3xl font-semibold tracking-tight">
                <?= $isEdit ? 'Edit supplier' : 'New supplier' ?>
            </h1>
            <p class="text-sm text-slate-500">
                <?= $isEdit
                    ? 'Update supplier details safely for ' . $h($orgName ?: 'your organisation') . '.'
                    : 'Create a new supplier record for ' . $h($orgName ?: 'your organisation') . '.' ?>
            </p>
        </div>

        <!-- Right-aligned app tabs -->
        <nav class="flex flex-wrap justify-end gap-1 text-sm">
            <?php
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
            $current = $module_base.'/suppliers';
            foreach ($tabs as [$label, $url]):
                $active = $url === $current;
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

    <!-- Layout: form + preview -->
    <section class="grid gap-6 lg:grid-cols-[minmax(0,2fr),minmax(0,1.2fr)]">
        <!-- Form card -->
        <form method="post"
              action="<?= $h($action) ?>"
              class="space-y-5 rounded-xl border border-slate-200 bg-white p-4 shadow-sm">

            <!-- Identity -->
            <div class="space-y-3">
                <div class="flex items-center justify-between gap-3">
                    <h2 class="text-sm font-semibold text-slate-800">Supplier identity</h2>
                    <?php if ($isEdit): ?>
                        <span class="rounded-full bg-slate-900 px-3 py-1 text-[11px] font-medium uppercase tracking-wide text-white">
                            ID: <?= (int)$supplier['id'] ?>
                        </span>
                    <?php endif; ?>
                </div>

                <div class="grid gap-3 md:grid-cols-[1.1fr,0.9fr]">
                    <div>
                        <label class="mb-1 block text-xs font-medium text-slate-700">
                            Name <span class="text-rose-500">*</span>
                        </label>
                        <input type="text"
                               name="name"
                               x-model="name"
                               value="<?= $h($val('name')) ?>"
                               required
                               placeholder="e.g. ABC Trading Ltd."
                               class="block w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500">
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-medium text-slate-700">
                            Code
                        </label>
                        <input type="text"
                               name="code"
                               x-model="code"
                               value="<?= $h($val('code')) ?>"
                               placeholder="Auto-generated if empty"
                               class="block w-full rounded-lg border border-slate-200 px-3 py-2 text-sm font-mono focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500">
                    </div>
                </div>

                <div class="grid gap-3 md:grid-cols-3">
                    <div>
                        <label class="mb-1 block text-xs font-medium text-slate-700">Type</label>
                        <select name="type"
                                x-model="type"
                                class="block w-full rounded-lg border border-slate-200 px-3 py-2 text-sm bg-white focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500">
                            <option value="local" <?= $type === 'local' ? 'selected' : '' ?>>Local</option>
                            <option value="international" <?= $type === 'international' ? 'selected' : '' ?>>International</option>
                        </select>
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-medium text-slate-700">Tax / BIN / VAT reg. no.</label>
                        <input type="text"
                               name="tax_reg_no"
                               value="<?= $h($val('tax_reg_no')) ?>"
                               class="block w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500">
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-medium text-slate-700">Status</label>
                        <label class="inline-flex items-center gap-2 text-xs text-slate-700">
                            <input type="checkbox"
                                   name="is_active"
                                   value="1"
                                   class="h-3.5 w-3.5 rounded border-slate-300 text-emerald-600 focus:ring-emerald-500"
                                   <?= $is_active ? 'checked' : '' ?>>
                            <span>Active supplier</span>
                        </label>
                    </div>
                </div>
            </div>

            <!-- Contacts -->
            <div class="space-y-3 pt-2 border-t border-dashed border-slate-200">
                <h2 class="text-sm font-semibold text-slate-800">Contact & communication</h2>

                <div class="grid gap-3 md:grid-cols-3">
                    <div>
                        <label class="mb-1 block text-xs font-medium text-slate-700">Primary contact name</label>
                        <input type="text"
                               name="contact_name"
                               value="<?= $h($val('contact_name')) ?>"
                               placeholder="Contact person at supplier"
                               class="block w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500">
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-medium text-slate-700">Email</label>
                        <input type="email"
                               name="email"
                               x-model="email"
                               value="<?= $h($val('email')) ?>"
                               placeholder="name@example.com"
                               class="block w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500">
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-medium text-slate-700">Phone</label>
                        <input type="text"
                               name="phone"
                               x-model="phone"
                               value="<?= $h($val('phone')) ?>"
                               placeholder="+8801..."
                               class="block w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500">
                    </div>
                </div>

                <div class="grid gap-3 md:grid-cols-2">
                    <div>
                        <label class="mb-1 block text-xs font-medium text-slate-700">Alternate phone</label>
                        <input type="text"
                               name="alt_phone"
                               value="<?= $h($val('alt_phone')) ?>"
                               class="block w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500">
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-medium text-slate-700">Payment terms</label>
                        <input type="text"
                               name="payment_terms"
                               value="<?= $h($val('payment_terms')) ?>"
                               placeholder="e.g. Net 30 days"
                               class="block w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500">
                    </div>
                </div>
            </div>

            <!-- Finance & address -->
            <div class="space-y-3 pt-2 border-t border-dashed border-slate-200">
                <h2 class="text-sm font-semibold text-slate-800">Finance & address</h2>

                <div class="grid gap-3 md:grid-cols-[1.1fr,0.9fr]">
                    <div>
                        <label class="mb-1 block text-xs font-medium text-slate-700">Credit limit (BDT)</label>
                        <input type="number"
                               step="0.01"
                               min="0"
                               inputmode="decimal"
                               name="credit_limit"
                               value="<?= $h($val('credit_limit')) ?>"
                               placeholder="Optional – 0 for no specific limit"
                               class="block w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500">
                    </div>
                    <div></div>
                </div>

                <div class="space-y-2">
                    <label class="mb-1 block text-xs font-medium text-slate-700">Address</label>
                    <input type="text"
                           name="address_line1"
                           value="<?= $h($val('address_line1')) ?>"
                           placeholder="Street / house / building"
                           class="mb-2 block w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500">
                    <input type="text"
                           name="address_line2"
                           value="<?= $h($val('address_line2')) ?>"
                           placeholder="Area / additional details (optional)"
                           class="mb-2 block w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500">

                    <div class="grid gap-3 md:grid-cols-3">
                        <div>
                            <label class="mb-1 block text-xs font-medium text-slate-700">City</label>
                            <input type="text"
                                   name="city"
                                   x-model="city"
                                   value="<?= $h($val('city')) ?>"
                                   class="block w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500">
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-medium text-slate-700">District</label>
                            <input type="text"
                                   name="district"
                                   x-model="district"
                                   value="<?= $h($val('district')) ?>"
                                   class="block w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500">
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-medium text-slate-700">Country</label>
                            <input type="text"
                                   name="country"
                                   x-model="country"
                                   value="<?= $h($val('country','Bangladesh')) ?>"
                                   class="block w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500">
                        </div>
                    </div>
                </div>
            </div>

            <!-- Actions -->
            <div class="flex flex-col gap-3 pt-2 border-t border-dashed border-slate-200 md:flex-row md:items-center md:justify-between">
                <div class="text-[11px] text-slate-500">
                    Fields marked with <span class="text-rose-500">*</span> are required.
                </div>
                <div class="flex flex-wrap items-center justify-end gap-2">
                    <a href="<?= $h($module_base.'/suppliers') ?>"
                       class="inline-flex items-center gap-1 rounded-lg border border-slate-200 px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50">
                        <i class="fa fa-arrow-left text-[10px]"></i>
                        <span>Back to list</span>
                    </a>
                    <button type="submit"
                            class="inline-flex items-center gap-1 rounded-lg bg-emerald-600 px-4 py-1.5 text-sm font-medium text-white shadow-sm hover:bg-emerald-700 focus:outline-none focus:ring-2 focus:ring-emerald-600 focus:ring-offset-1">
                        <i class="fa fa-floppy-disk text-xs"></i>
                        <span><?= $isEdit ? 'Save changes' : 'Create supplier' ?></span>
                    </button>
                </div>
            </div>
        </form>

        <!-- Live preview card -->
        <aside class="space-y-3 rounded-xl border border-emerald-200 bg-emerald-50/70 p-4 shadow-sm">
            <div class="flex items-center justify-between gap-2">
                <div>
                    <h2 class="text-sm font-semibold text-emerald-900">Live preview</h2>
                    <p class="text-[11px] text-emerald-800">
                        How this supplier will feel in the BizFlow documents.
                    </p>
                </div>
                <span class="inline-flex items-center gap-1 rounded-full bg-white/80 px-2.5 py-1 text-[11px] font-medium text-emerald-800">
                    <i class="fa fa-eye text-[10px]"></i>
                    Realtime
                </span>
            </div>

            <div class="rounded-lg bg-white/80 p-3 text-sm text-slate-800 shadow-inner">
                <div class="flex items-start justify-between gap-2">
                    <div>
                        <div class="text-xs font-semibold uppercase tracking-wide text-emerald-700">
                            Supplier
                        </div>
                        <div class="mt-1 text-base font-semibold" x-text="name || 'New supplier'"></div>
                        <div class="mt-0.5 text-[11px] text-slate-500">
                            <span class="font-mono text-xs" x-text="code || 'AUTO'"></span>
                            <span class="mx-1">•</span>
                            <span x-text="type === 'international' ? 'International' : 'Local'"></span>
                        </div>
                    </div>
                    <div class="text-right text-[11px] text-slate-500">
                        <div x-text="phone || 'Phone not set'"></div>
                        <div x-text="email || 'Email not set'"></div>
                    </div>
                </div>

                <div class="mt-3 border-t border-dashed border-slate-200 pt-2 text-[11px] text-slate-600">
                    <div class="font-semibold text-slate-700 mb-1">Location</div>
                    <div x-text="[
                          city || null,
                          district || null,
                          country || null
                        ].filter(Boolean).join(', ') || 'Address not set yet'"></div>
                </div>
            </div>

            <p class="text-[11px] text-emerald-900">
                When you save, this profile becomes available to quotes, orders,
                and purchases for this tenant only.
            </p>
        </aside>
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
            <li>Fill in the <strong>supplier name</strong> and optional <strong>code</strong>; if code is empty BizFlow can auto-generate one later.</li>
            <li>Use <strong>Local / International</strong> type to separate domestic vendors from global partners.</li>
            <li>Add contact person, email, and phone so your team can reach the supplier directly from documents.</li>
            <li>Optionally set <strong>payment terms</strong> and a <strong>credit limit (BDT)</strong> for financial control.</li>
            <li>The <strong>Live preview</strong> on the right updates as you type so you can sanity-check the profile before saving.</li>
            <li>All data is stored with your <strong>org_id</strong> and is fully tenant-safe inside BizFlow.</li>
        </ul>
    </section>
</div>