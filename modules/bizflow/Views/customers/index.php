<?php
/** @var array  $customers */
/** @var array  $org */
/** @var string $module_base */
/** @var string $title */
/** @var string|null $search */
/** @var string|null $segment */
/** @var bool|null $only_active */

$h = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');

$module_base = rtrim($module_base ?? ($base ?? '/apps/bizflow'), '/');
$orgName     = $org['name'] ?? '';

$search      = (string)($search ?? ($_GET['q'] ?? ''));
$segment     = (string)($segment ?? ($_GET['seg'] ?? ''));
$only_active = !empty($only_active);

$total       = count($customers ?? []);
$activeCount = array_reduce($customers ?? [], fn($c, $row) => $c + ((int)($row['is_active'] ?? 0) === 1 ? 1 : 0), 0);
?>
<div class="space-y-6">

    <!-- Top bar: title + nav tabs -->
    <header class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
        <div class="space-y-1">
            <h1 class="text-2xl md:text-3xl font-semibold tracking-tight">
                <?= $h($title ?? 'Customers') ?>
            </h1>
            <p class="text-sm text-slate-500">
                See every customer relationship for <?= $h($orgName ?: 'your organisation') ?> in one place.
            </p>
        </div>

        <!-- Right: primary actions -->
        <div class="flex flex-wrap items-center justify-end gap-2">
            <a href="<?= $h($module_base.'/customers/create') ?>"
               class="inline-flex items-center gap-2 rounded-lg bg-emerald-600 px-3 py-2 text-sm font-medium text-white shadow-sm hover:bg-emerald-700 focus:outline-none focus:ring-2 focus:ring-emerald-600 focus:ring-offset-1">
                <i class="fa fa-user-plus text-xs"></i>
                <span>New customer</span>
            </a>
        </div>
        
    </header>

    <!-- Actions + quick stats -->
    <section class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
        <!-- Left: quick stats -->
        <div class="flex flex-wrap gap-3 text-xs md:text-sm">
            <div class="inline-flex items-center gap-2 rounded-lg border border-slate-200 bg-white px-3 py-2 shadow-sm">
                <span class="inline-flex h-6 w-6 items-center justify-center rounded-full bg-emerald-100 text-emerald-700 text-xs font-semibold">
                    <?= $h($total) ?>
                </span>
                <div>
                    <div class="font-medium text-slate-800">Customers</div>
                    <div class="text-[11px] text-slate-500">Total records in this tenant</div>
                </div>
            </div>

            <div class="inline-flex items-center gap-2 rounded-lg border border-slate-200 bg-white px-3 py-2 shadow-sm">
                <span class="inline-flex h-6 w-6 items-center justify-center rounded-full bg-emerald-50 text-emerald-700 text-xs font-semibold">
                    <?= $h($activeCount) ?>
                </span>
                <div>
                    <div class="font-medium text-slate-800">Active</div>
                    <div class="text-[11px] text-slate-500">Customers you can sell to</div>
                </div>
            </div>
        </div>

        
    </section>

    <!-- Filters -->
    <section class="rounded-xl border border-slate-200 bg-white shadow-sm">
        <form method="get"
              class="grid gap-3 border-b border-slate-100 px-4 py-3 md:grid-cols-[1.6fr,1fr,auto] md:items-end">
            <!-- Search -->
            <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">Search</label>
                <div class="relative">
                    <input type="text"
                           name="q"
                           value="<?= $h($search) ?>"
                           placeholder="Search by name, code, phone, email, company"
                           class="block w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500">
                    <span class="pointer-events-none absolute inset-y-0 right-3 flex items-center text-slate-400">
                        <i class="fa fa-search text-xs"></i>
                    </span>
                </div>
            </div>

            <!-- Segment -->
            <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">Segment</label>
                <select name="seg"
                        class="block w-full rounded-lg border border-slate-200 px-3 py-2 text-sm bg-white focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500">
                    <option value="">All segments</option>
                    <option value="b2b"  <?= $segment === 'b2b'  ? 'selected' : '' ?>>B2B (Corporate)</option>
                    <option value="b2c"  <?= $segment === 'b2c'  ? 'selected' : '' ?>>B2C (Retail)</option>
                    <option value="ngo"  <?= $segment === 'ngo'  ? 'selected' : '' ?>>NGO / INGO</option>
                    <option value="govt" <?= $segment === 'govt' ? 'selected' : '' ?>>Government</option>
                    <option value="other"<?= $segment === 'other'? 'selected' : '' ?>>Other</option>
                </select>
            </div>

            <!-- Active toggle + actions -->
            <div class="flex flex-col gap-2 md:items-end md:justify-between">
                <label class="inline-flex items-center gap-2 text-xs text-slate-600">
                    <input type="checkbox"
                           name="active"
                           value="1"
                           class="h-3.5 w-3.5 rounded border-slate-300 text-emerald-600 focus:ring-emerald-500"
                           <?= $only_active ? 'checked' : '' ?>>
                    <span>Only active customers</span>
                </label>

                <div class="flex gap-2 justify-end">
                    <a href="<?= $h($module_base.'/customers') ?>"
                       class="inline-flex items-center gap-1 rounded-lg border border-slate-200 px-2.5 py-1.5 text-xs font-medium text-slate-600 hover:bg-slate-50">
                        <i class="fa fa-rotate-left text-[11px]"></i>
                        <span>Reset</span>
                    </a>
                    <button type="submit"
                            class="inline-flex items-center gap-1 rounded-lg bg-slate-900 px-3 py-1.5 text-xs font-medium text-white shadow-sm hover:bg-slate-800">
                        <i class="fa fa-magnifying-glass text-[11px]"></i>
                        <span>Apply</span>
                    </button>
                </div>
            </div>
        </form>

        <!-- Table -->
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50">
                <tr class="text-xs font-semibold uppercase tracking-wide text-slate-500">
                    <th class="px-4 py-2 text-left">Code</th>
                    <th class="px-4 py-2 text-left">Name</th>
                    <th class="px-4 py-2 text-left">Contact</th>
                    <th class="px-4 py-2 text-left">Segment</th>
                    <th class="px-4 py-2 text-left">Location</th>
                    <th class="px-4 py-2 text-right">Open balance</th>
                    <th class="px-4 py-2 text-center">Status</th>
                    <th class="px-4 py-2 text-right">Actions</th>
                </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 bg-white">
                <?php if (!empty($customers)): ?>
                    <?php foreach ($customers as $row): ?>
                        <?php
                        $id       = (int)($row['id'] ?? 0);
                        $code     = $row['code'] ?? '';
                        $name     = $row['name'] ?? '';
                        $segmentV = $row['segment'] ?? '';
                        $company  = $row['company_name'] ?? '';
                        $email    = $row['email'] ?? '';
                        $phone    = $row['phone'] ?? '';
                        $city     = $row['city'] ?? '';
                        $district = $row['district'] ?? '';
                        $country  = $row['country'] ?? '';
                        $locParts = array_filter([$city, $district, $country]);
                        $loc      = implode(', ', $locParts);
                        $active   = (int)($row['is_active'] ?? 0) === 1;
                        $balance  = $row['ar_balance'] ?? null;
                        ?>
                        <tr class="hover:bg-emerald-50/40">
                            <td class="px-4 py-2 align-top whitespace-nowrap">
                                <div class="font-mono text-xs text-slate-700">
                                    <?= $h($code ?: ('CUST-'.$id)) ?>
                                </div>
                                <div class="mt-0.5 inline-flex items-center gap-1 rounded-full border border-slate-200 bg-slate-50 px-2 py-[2px] text-[10px] uppercase tracking-wide text-slate-500">
                                    <?= $segmentV !== '' ? strtoupper($segmentV) : 'UNSEGMENTED' ?>
                                </div>
                            </td>
                            <td class="px-4 py-2 align-top">
                                <div class="font-medium text-slate-900">
                                    <?= $h($name) ?>
                                </div>
                                <?php if ($company !== ''): ?>
                                    <div class="text-xs text-slate-500"><?= $h($company) ?></div>
                                <?php endif; ?>
                            </td>
                            <td class="px-4 py-2 align-top text-xs text-slate-600">
                                <?php if ($phone !== ''): ?>
                                    <div><?= $h($phone) ?></div>
                                <?php endif; ?>
                                <?php if ($email !== ''): ?>
                                    <div class="text-emerald-700"><?= $h($email) ?></div>
                                <?php endif; ?>
                            </td>
                            <td class="px-4 py-2 align-top text-xs text-slate-600">
                                <?= $segmentV !== '' ? $h(ucfirst($segmentV)) : '<span class="text-slate-400">—</span>' ?>
                            </td>
                            <td class="px-4 py-2 align-top text-xs text-slate-600">
                                <?= $loc !== '' ? $h($loc) : '<span class="text-slate-400">—</span>' ?>
                            </td>
                            <td class="px-4 py-2 align-top text-right text-xs text-slate-700">
                                <?php if ($balance !== null && $balance !== ''): ?>
                                    <span><?= $h(number_format((float)$balance, 2)) ?> BDT</span>
                                <?php else: ?>
                                    <span class="text-slate-400">—</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-4 py-2 align-top text-center">
                                <?php if ($active): ?>
                                    <span class="inline-flex items-center gap-1 rounded-full bg-emerald-50 px-2 py-[3px] text-[11px] font-medium text-emerald-700">
                                        <span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>
                                        Active
                                    </span>
                                <?php else: ?>
                                    <span class="inline-flex items-center gap-1 rounded-full bg-slate-100 px-2 py-[3px] text-[11px] font-medium text-slate-600">
                                        <span class="h-1.5 w-1.5 rounded-full bg-slate-400"></span>
                                        Inactive
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td class="px-4 py-2 align-top text-right text-xs">
                                <div class="inline-flex gap-1">
                                    <a href="<?= $h($module_base.'/customers/'.$id) ?>"
                                       class="inline-flex items-center rounded-lg border border-slate-200 px-2 py-1 text-[11px] text-slate-700 hover:bg-slate-50">
                                        <i class="fa-regular fa-eye mr-1 text-[10px]"></i> View
                                    </a>
                                    <a href="<?= $h($module_base.'/customers/'.$id.'/edit') ?>"
                                       class="inline-flex items-center rounded-lg border border-slate-200 px-2 py-1 text-[11px] text-slate-700 hover:bg-slate-50">
                                        <i class="fa-regular fa-pen-to-square mr-1 text-[10px]"></i> Edit
                                    </a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="8" class="px-4 py-8 text-center text-sm text-slate-500">
                            No customers found yet.
                            <a href="<?= $h($module_base.'/customers/create') ?>"
                               class="text-emerald-700 font-medium hover:underline">
                                Create your first customer
                            </a>.
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
            <li>Use <strong>Search</strong> to find customers by name, code, phone, email or company name.</li>
            <li>Filter by <strong>segment</strong> (B2B, B2C, NGO, Gov) and hide inactive customer records.</li>
            <li>Click <strong>New customer</strong> to onboard a client with full contact and billing details.</li>
            <li>Use <strong>View</strong> to open the 2035-style customer profile with all historical activity.</li>
            <li>All records are strictly scoped by your <strong>org_id</strong> so BizFlow stays tenant-safe.</li>
        </ul>
    </section>
</div>