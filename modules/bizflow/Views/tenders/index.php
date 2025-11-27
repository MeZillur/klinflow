<?php
/** @var array  $tenders */
/** @var array  $org */
/** @var string $module_base */
/** @var string $title */
/** @var string|null $search */
/** @var string|null $status */
/** @var string|null $type */
/** @var string|null $customer */
/** @var string|null $due_from */
/** @var string|null $due_to */

$h = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');

$module_base = rtrim($module_base ?? ($base ?? '/apps/bizflow'), '/');
$orgName     = $org['name'] ?? '';

$search   = (string)($search   ?? ($_GET['q']        ?? ''));
$status   = (string)($status   ?? ($_GET['status']   ?? ''));
$type     = (string)($type     ?? ($_GET['type']     ?? ''));
$customer = (string)($customer ?? ($_GET['customer'] ?? ''));
$due_from = (string)($due_from ?? ($_GET['due_from'] ?? ''));
$due_to   = (string)($due_to   ?? ($_GET['due_to']   ?? ''));

$tenders  = $tenders ?? [];

/* Simple stats derived from $tenders */
$total         = count($tenders);
$openStatuses  = ['draft','published','bidding','evaluation'];
$nowTs         = time();
$openCount     = 0;
$awardCount    = 0;
$upcomingCount = 0;

foreach ($tenders as $row) {
    $st = strtolower((string)($row['status'] ?? ''));
    if (in_array($st, $openStatuses, true)) {
        $openCount++;
        $due = (string)($row['due_date'] ?? '');
        if ($due && strtotime($due) >= $nowTs) {
            $upcomingCount++;
        }
    }
    if ($st === 'awarded') {
        $awardCount++;
    }
}

$tabs = [
    ['Items',      $module_base.'/items'],
    ['Customers',  $module_base.'/customers'],
    ['Suppliers',  $module_base.'/suppliers'],
    ['Quotes',     $module_base.'/quotes'],
    ['Settings',   $module_base.'/settings'],
];
$current = $module_base.'/tenders';
?>
<div class="space-y-6" x-data="{ openFilters:false }">

    <!-- Header: title + description + right-aligned tabs -->
    <header class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
        <div class="space-y-1.5">
            <h1 class="text-2xl md:text-3xl font-semibold tracking-tight">
                <?= $h($title ?? 'Tenders & RFQs') ?>
            </h1>
            <p class="text-xs md:text-sm text-slate-500">
                Track RFQs, tenders, and bids for <?= $h($orgName ?: 'your organisation') ?> —
                from publish to award, in a single BizFlow lane.
            </p>
        </div>

        <!-- BizFlow app tabs (right-aligned) -->
        <nav class="flex flex-wrap justify-end gap-1 text-sm">
            <?php foreach ($tabs as [$label, $url]): ?>
                <?php $active = ($url === $current); ?>
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

    <!-- Actions + quick stats -->
    <section class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">

        <!-- Quick stats -->
        <div class="flex flex-wrap gap-3 text-xs md:text-sm">
            <!-- Total tenders -->
            <div class="inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-3 py-2 shadow-sm">
                <span class="inline-flex h-7 w-7 items-center justify-center rounded-full bg-emerald-100 text-emerald-700 text-xs font-semibold">
                    <?= $h($total) ?>
                </span>
                <div>
                    <div class="font-medium text-slate-800">All tenders</div>
                    <div class="text-[11px] text-slate-500">Total records in this tenant</div>
                </div>
            </div>

            <!-- Open / active -->
            <div class="inline-flex items-center gap-2 rounded-xl border border-emerald-100 bg-emerald-50/70 px-3 py-2 shadow-sm">
                <span class="inline-flex h-7 w-7 items-center justify-center rounded-full bg-emerald-600 text-white text-xs font-semibold">
                    <?= $h($openCount) ?>
                </span>
                <div>
                    <div class="font-medium text-emerald-900">Open</div>
                    <div class="text-[11px] text-emerald-800/80">Draft, published, bidding, evaluation</div>
                </div>
            </div>

            <!-- Awarded count -->
            <div class="inline-flex items-center gap-2 rounded-xl border border-amber-100 bg-amber-50 px-3 py-2 shadow-sm">
                <span class="inline-flex h-7 w-7 items-center justify-center rounded-full bg-amber-500 text-white text-xs font-semibold">
                    <?= $h($awardCount) ?>
                </span>
                <div>
                    <div class="font-medium text-slate-800">Awarded</div>
                    <div class="text-[11px] text-slate-500">Closed as won</div>
                </div>
            </div>
        </div>

        <!-- Primary actions -->
        <div class="flex flex-wrap items-center justify-end gap-2">
            <button type="button"
                    class="inline-flex items-center gap-2 rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs font-medium text-slate-700 shadow-sm hover:bg-slate-50 md:hidden"
                    @click="openFilters = !openFilters">
                <i class="fa fa-filter text-[11px]"></i>
                <span>Filters</span>
            </button>

            <a href="<?= $h($module_base.'/tenders/create') ?>"
               class="inline-flex items-center gap-2 rounded-lg bg-emerald-600 px-3 py-2 text-sm font-medium text-white shadow-sm hover:bg-emerald-700 focus:outline-none focus:ring-2 focus:ring-emerald-600 focus:ring-offset-1">
                <i class="fa fa-plus-circle text-xs"></i>
                <span>New tender</span>
            </a>
        </div>
    </section>

    <!-- Filters -->
    <section class="rounded-xl border border-slate-200 bg-white shadow-sm">
        <form method="get"
              class="grid gap-3 border-b border-slate-100 px-4 py-3 md:grid-cols-[1.6fr,1.1fr,1.1fr,auto] md:items-end"
              :class="{'hidden md:grid': !openFilters, 'grid': openFilters}">
            <!-- Search -->
            <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">Search</label>
                <div class="relative">
                    <input type="text"
                           name="q"
                           value="<?= $h($search) ?>"
                           placeholder="Search by title, code, customer, ref no."
                           class="block w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500">
                    <span class="pointer-events-none absolute inset-y-0 right-3 flex items-center text-slate-400">
                        <i class="fa fa-search text-xs"></i>
                    </span>
                </div>
            </div>

            <!-- Status -->
            <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">Status</label>
                <select name="status"
                        class="block w-full rounded-lg border border-slate-200 px-3 py-2 text-sm bg-white focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500">
                    <option value="">All statuses</option>
                    <?php
                    $statuses = [
                        'draft'       => 'Draft',
                        'published'   => 'Published',
                        'bidding'     => 'Bidding',
                        'evaluation'  => 'Evaluation',
                        'awarded'     => 'Awarded',
                        'lost'        => 'Lost',
                        'cancelled'   => 'Cancelled',
                    ];
                    foreach ($statuses as $k => $label):
                    ?>
                        <option value="<?= $h($k) ?>" <?= $status === $k ? 'selected' : '' ?>>
                            <?= $h($label) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Type -->
            <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">Type</label>
                <select name="type"
                        class="block w-full rounded-lg border border-slate-200 px-3 py-2 text-sm bg-white focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500">
                    <option value="">All types</option>
                    <option value="rfq"   <?= $type === 'rfq'   ? 'selected' : '' ?>>RFQ</option>
                    <option value="tender"<?= $type === 'tender'? 'selected' : '' ?>>Tender</option>
                    <option value="rfp"   <?= $type === 'rfp'   ? 'selected' : '' ?>>RFP</option>
                    <option value="framework"<?= $type === 'framework'? 'selected' : '' ?>>Framework</option>
                </select>
            </div>

            <!-- Bottom-right: due date range + actions -->
            <div class="flex flex-col gap-2 md:items-end md:justify-between">
                <div class="flex gap-2 w-full md:w-auto">
                    <div class="flex-1">
                        <label class="block text-xs font-medium text-slate-600 mb-1">Due from</label>
                        <input type="date"
                               name="due_from"
                               value="<?= $h($due_from) ?>"
                               class="block w-full rounded-lg border border-slate-200 px-3 py-1.5 text-xs focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500">
                    </div>
                    <div class="flex-1">
                        <label class="block text-xs font-medium text-slate-600 mb-1">Due to</label>
                        <input type="date"
                               name="due_to"
                               value="<?= $h($due_to) ?>"
                               class="block w-full rounded-lg border border-slate-200 px-3 py-1.5 text-xs focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500">
                    </div>
                </div>

                <div class="flex gap-2 justify-end">
                    <a href="<?= $h($module_base.'/tenders') ?>"
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
                    <th class="px-4 py-2 text-left">Title</th>
                    <th class="px-4 py-2 text-left">Customer</th>
                    <th class="px-4 py-2 text-left">Status</th>
                    <th class="px-4 py-2 text-left">Due date</th>
                    <th class="px-4 py-2 text-right">Est. value</th>
                    <th class="px-4 py-2 text-left">Owner</th>
                    <th class="px-4 py-2 text-right">Actions</th>
                </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 bg-white">
                <?php if (!empty($tenders)): ?>
                    <?php foreach ($tenders as $row): ?>
                        <?php
                        $id       = (int)($row['id'] ?? 0);
                        $code     = $row['code']        ?? $row['tender_no'] ?? '';
                        $titleRow = $row['title']       ?? $row['subject']   ?? '';
                        $cust     = $row['customer_name'] ?? '';
                        $st       = strtolower((string)($row['status'] ?? 'draft'));
                        $due      = $row['due_date']    ?? '';
                        $budget   = $row['estimated_value'] ?? $row['budget_amount'] ?? null;
                        $owner    = $row['owner_name']  ?? '';
                        // Status badge style
                        $stLabel  = ucfirst($st ?: 'Draft');
                        $stClass  = match ($st) {
                            'published','bidding'   => 'bg-sky-50 text-sky-700 border-sky-200',
                            'evaluation'            => 'bg-amber-50 text-amber-700 border-amber-200',
                            'awarded'               => 'bg-emerald-50 text-emerald-700 border-emerald-200',
                            'lost'                  => 'bg-rose-50 text-rose-700 border-rose-200',
                            'cancelled'             => 'bg-slate-100 text-slate-600 border-slate-200',
                            default                 => 'bg-slate-50 text-slate-600 border-slate-200',
                        };
                        ?>
                        <tr class="hover:bg-emerald-50/40">
                            <td class="px-4 py-2 align-top whitespace-nowrap">
                                <div class="font-mono text-xs text-slate-700">
                                    <?= $h($code ?: ('TN-'.$id)) ?>
                                </div>
                                <div class="mt-0.5 text-[10px] uppercase tracking-wide text-slate-400">
                                    ID: <?= $h($id) ?>
                                </div>
                            </td>
                            <td class="px-4 py-2 align-top">
                                <div class="font-medium text-slate-900 line-clamp-2">
                                    <?= $h($titleRow ?: 'Untitled tender') ?>
                                </div>
                            </td>
                            <td class="px-4 py-2 align-top text-xs text-slate-600">
                                <?= $cust !== '' ? $h($cust) : '<span class="text-slate-400">—</span>' ?>
                            </td>
                            <td class="px-4 py-2 align-top text-xs">
                                <span class="inline-flex items-center gap-1 rounded-full border px-2 py-[3px] text-[11px] <?= $stClass ?>">
                                    <span class="h-1.5 w-1.5 rounded-full <?= in_array($st,['draft','published','bidding','evaluation'],true) ? 'bg-emerald-500' : 'bg-slate-400' ?>"></span>
                                    <?= $h($stLabel) ?>
                                </span>
                            </td>
                            <td class="px-4 py-2 align-top text-xs text-slate-600 whitespace-nowrap">
                                <?= $due !== '' ? $h($due) : '<span class="text-slate-400">—</span>' ?>
                            </td>
                            <td class="px-4 py-2 align-top text-right text-xs text-slate-700 whitespace-nowrap">
                                <?php if ($budget !== null && $budget !== ''): ?>
                                    <?= $h(number_format((float)$budget, 2)) ?> BDT
                                <?php else: ?>
                                    <span class="text-slate-400">—</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-4 py-2 align-top text-xs text-slate-600">
                                <?= $owner !== '' ? $h($owner) : '<span class="text-slate-400">—</span>' ?>
                            </td>
                            <td class="px-4 py-2 align-top text-right text-xs">
                                <div class="inline-flex gap-1">
                                    <a href="<?= $h($module_base.'/tenders/'.$id) ?>"
                                       class="inline-flex items-center rounded-lg border border-slate-200 px-2 py-1 text-[11px] text-slate-700 hover:bg-slate-50">
                                        <i class="fa-regular fa-eye mr-1 text-[10px]"></i> View
                                    </a>
                                    <a href="<?= $h($module_base.'/tenders/'.$id.'/edit') ?>"
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
                            No tenders captured yet.
                            <a href="<?= $h($module_base.'/tenders/create') ?>"
                               class="text-emerald-700 font-medium hover:underline">
                                Log your first tender
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
            <li>Use <strong>Search</strong> to find tenders by title, code, customer, or reference.</li>
            <li>Filter by <strong>status</strong>, <strong>type</strong>, and <strong>due date range</strong> to focus on active bids.</li>
            <li>Click <strong>New tender</strong> to register a fresh RFQ or tender from any source (NGO, INGO or corporate).</li>
            <li>Use <strong>View</strong> to open the full timeline and history, or <strong>Edit</strong> to update status and values.</li>
            <li>All records are isolated by <strong>org_id</strong> so each tenant’s tender pipeline stays safe inside BizFlow.</li>
        </ul>
    </section>
</div>