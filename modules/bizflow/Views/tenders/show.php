<?php
/** @var array  $tender */
/** @var array  $bids */
/** @var array  $tasks */
/** @var array  $files */
/** @var array  $org */
/** @var string $module_base */
/** @var string $title */

$h = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');

$module_base = rtrim($module_base ?? ($base ?? '/apps/bizflow'), '/');
$orgName     = $org['name'] ?? '';

$id          = (int)($tender['id'] ?? 0);
$code        = $tender['code']        ?? $tender['tender_no'] ?? '';
$subject     = $tender['title']       ?? $tender['subject']   ?? '';
$type        = strtolower((string)($tender['type'] ?? 'rfq'));
$status      = strtolower((string)($tender['status'] ?? 'draft'));
$customer    = $tender['customer_name'] ?? '';
$customerRef = $tender['customer_ref']  ?? '';
$channel     = $tender['channel']       ?? ''; // e.g. 'Email', 'e-GP', 'Web portal'
$publishDate = $tender['publish_date']  ?? '';
$dueDate     = $tender['due_date']      ?? '';
$openingDate = $tender['opening_date']  ?? '';
$awardDate   = $tender['award_date']    ?? '';
$currency    = $tender['currency']      ?? 'BDT';
$budget      = $tender['estimated_value'] ?? $tender['budget_amount'] ?? null;
$location    = $tender['location']      ?? '';
$country     = $tender['country']       ?? '';
$owner       = $tender['owner_name']    ?? '';
$remarks     = $tender['internal_notes'] ?? '';
$scope       = $tender['scope']        ?? $tender['description'] ?? '';
$createdAt   = $tender['created_at']   ?? '';
$lastUpdated = $tender['updated_at']   ?? $createdAt;

$locParts    = array_filter([$location, $country]);
$loc         = implode(', ', $locParts);

$typeLabel   = match ($type) {
    'tender'     => 'Tender',
    'rfp'        => 'RFP',
    'framework'  => 'Framework Agreement',
    default      => 'RFQ',
};

$stLabel  = ucfirst($status ?: 'Draft');
$stClass  = match ($status) {
    'published','bidding'   => 'bg-sky-50 text-sky-700 border-sky-200',
    'evaluation'            => 'bg-amber-50 text-amber-700 border-amber-200',
    'awarded'               => 'bg-emerald-50 text-emerald-700 border-emerald-200',
    'lost'                  => 'bg-rose-50 text-rose-700 border-rose-200',
    'cancelled'             => 'bg-slate-100 text-slate-600 border-slate-200',
    default                 => 'bg-slate-50 text-slate-600 border-slate-200',
};
?>
<div class="space-y-6">

    <!-- Breadcrumb + header -->
    <header class="space-y-3">
        <div class="text-xs text-slate-500 flex items-center gap-1">
            <a href="<?= $h($module_base.'/tenders') ?>" class="hover:underline">Tenders</a>
            <span>/</span>
            <span class="font-medium text-slate-700"><?= $h($code ?: 'Tender #'.$id) ?></span>
        </div>

        <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
            <div>
                <div class="flex items-center gap-3">
                    <div class="flex h-11 w-11 items-center justify-center rounded-full bg-emerald-600 text-sm font-semibold text-white">
                        <?= $h(strtoupper(substr($subject ?: 'TN', 0, 2))) ?>
                    </div>
                    <div>
                        <h1 class="text-2xl md:text-3xl font-semibold tracking-tight">
                            <?= $h($subject ?: 'Untitled tender') ?>
                        </h1>
                        <?php if ($customer !== ''): ?>
                            <p class="text-xs text-slate-500"><?= $h($customer) ?></p>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="mt-3 flex flex-wrap gap-2 text-xs">
                    <?php if ($code !== ''): ?>
                        <span class="inline-flex items-center gap-1 rounded-full border border-slate-200 bg-slate-50 px-2 py-[3px] font-mono text-[11px] text-slate-700">
                            <i class="fa fa-hashtag text-[9px]"></i> <?= $h($code) ?>
                        </span>
                    <?php endif; ?>
                    <span class="inline-flex items-center gap-1 rounded-full bg-emerald-50 px-2 py-[3px] text-[11px] font-medium text-emerald-700">
                        <i class="fa fa-sheet-plastic text-[9px]"></i> <?= $h($typeLabel) ?>
                    </span>
                    <?php if ($loc !== ''): ?>
                        <span class="inline-flex items-center gap-1 rounded-full bg-slate-50 px-2 py-[3px] text-[11px] text-slate-600">
                            <i class="fa fa-location-dot text-[9px]"></i> <?= $h($loc) ?>
                        </span>
                    <?php endif; ?>
                    <span class="inline-flex items-center gap-1 rounded-full px-2 py-[3px] text-[11px] border <?= $stClass ?>">
                        <span class="h-1.5 w-1.5 rounded-full <?= in_array($status,['draft','published','bidding','evaluation'],true) ? 'bg-emerald-500' : 'bg-slate-400' ?>"></span>
                        <?= $h($stLabel) ?>
                    </span>
                </div>
            </div>

            <!-- Mini header actions -->
            <nav class="flex flex-wrap justify-end gap-1 text-xs md:text-[13px]">
                <a href="<?= $h($module_base.'/tenders') ?>"
                   class="inline-flex items-center gap-1 rounded-full px-3 py-1 border border-slate-200 text-slate-600 hover:bg-slate-50">
                    <i class="fa fa-list text-[11px]"></i>
                    <span>All tenders</span>
                </a>
                <a href="<?= $h($module_base.'/tenders/'.$id.'/edit') ?>"
                   class="inline-flex items-center gap-1 rounded-full px-3 py-1 border border-slate-200 text-slate-700 hover:bg-slate-50">
                    <i class="fa fa-pen text-[11px]"></i>
                    <span>Edit</span>
                </a>
            </nav>
        </div>
    </header>

    <!-- Layout -->
    <div class="grid gap-6 lg:grid-cols-[2fr,1fr]">

        <!-- LEFT: main info + history -->
        <section class="space-y-4">

            <!-- Top metrics -->
            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                    <div class="text-xs font-medium text-slate-500">Estimated value</div>
                    <div class="mt-1 text-xl font-semibold text-slate-900">
                        <?php if ($budget !== null && $budget !== ''): ?>
                            <?= $h(number_format((float)$budget, 2)) ?> <?= $h($currency ?: 'BDT') ?>
                        <?php else: ?>
                            <span class="text-slate-400">—</span>
                        <?php endif; ?>
                    </div>
                    <div class="mt-1 text-[11px] text-slate-500">Budget as per RFP / RFQ</div>
                </div>

                <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                    <div class="text-xs font-medium text-slate-500">Bid closing date</div>
                    <div class="mt-1 text-xl font-semibold text-slate-900">
                        <?= $dueDate !== '' ? $h($dueDate) : '<span class="text-slate-400">—</span>' ?>
                    </div>
                    <div class="mt-1 text-[11px] text-slate-500">
                        <?= $publishDate !== '' ? 'Published: '.$h($publishDate) : 'Publish date not recorded' ?>
                    </div>
                </div>

                <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                    <div class="text-xs font-medium text-slate-500">Stage</div>
                    <div class="mt-1 text-xl font-semibold text-slate-900">
                        <?= $h($stLabel) ?>
                    </div>
                    <?php if ($lastUpdated !== ''): ?>
                        <div class="mt-1 text-[11px] text-slate-500">
                            Last updated: <?= $h($lastUpdated) ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Scope / description -->
            <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                <h2 class="text-sm font-semibold text-slate-800 mb-2 flex items-center gap-2">
                    <i class="fa fa-clipboard-list text-xs text-emerald-600"></i>
                    Scope of work
                </h2>
                <?php if ($scope !== ''): ?>
                    <div class="prose prose-sm max-w-full text-slate-700">
                        <?= nl2br($h($scope)) ?>
                    </div>
                <?php else: ?>
                    <p class="text-xs text-slate-500">No scope / description captured for this tender yet.</p>
                <?php endif; ?>
            </div>

            <!-- Bids summary -->
            <div class="rounded-xl border border-slate-200 bg-white shadow-sm">
                <div class="flex items-center justify-between border-b border-slate-100 px-4 py-3">
                    <h2 class="text-sm font-semibold text-slate-800 flex items-center gap-2">
                        <span class="inline-flex h-6 w-6 items-center justify-center rounded-full bg-sky-500 text-[11px] text-white">
                            B
                        </span>
                        Bids / offers
                    </h2>
                    <span class="text-xs text-slate-500">
                        <?= isset($bids) && is_array($bids) ? $h(count($bids)) : 0 ?> records
                    </span>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-100 text-sm">
                        <thead class="bg-slate-50 text-xs font-semibold text-slate-500 uppercase tracking-wide">
                        <tr>
                            <th class="px-4 py-2 text-left">Vendor</th>
                            <th class="px-4 py-2 text-left">Submitted at</th>
                            <th class="px-4 py-2 text-left">Status</th>
                            <th class="px-4 py-2 text-right">Amount (BDT)</th>
                            <th class="px-4 py-2 text-left">Rank</th>
                        </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 bg-white">
                        <?php if (!empty($bids)): ?>
                            <?php foreach ($bids as $b): ?>
                                <?php
                                $vName  = $b['vendor_name']   ?? '';
                                $bDate  = $b['submitted_at']  ?? '';
                                $bSt    = strtolower((string)($b['status'] ?? 'submitted'));
                                $bAmt   = $b['amount']        ?? null;
                                $rank   = $b['ranking']       ?? '';
                                $bStLbl = ucfirst($bSt);
                                $bCls   = match ($bSt) {
                                    'won'      => 'bg-emerald-50 text-emerald-700 border-emerald-200',
                                    'lost'     => 'bg-rose-50 text-rose-700 border-rose-200',
                                    'draft'    => 'bg-slate-50 text-slate-600 border-slate-200',
                                    default    => 'bg-sky-50 text-sky-700 border-sky-200',
                                };
                                ?>
                                <tr class="hover:bg-emerald-50/40">
                                    <td class="px-4 py-2 text-xs text-slate-700">
                                        <?= $vName !== '' ? $h($vName) : '<span class="text-slate-400">—</span>' ?>
                                    </td>
                                    <td class="px-4 py-2 text-xs text-slate-600">
                                        <?= $bDate !== '' ? $h($bDate) : '<span class="text-slate-400">—</span>' ?>
                                    </td>
                                    <td class="px-4 py-2 text-xs">
                                        <span class="inline-flex items-center gap-1 rounded-full border px-2 py-[3px] text-[11px] <?= $bCls ?>">
                                            <?= $h($bStLbl) ?>
                                        </span>
                                    </td>
                                    <td class="px-4 py-2 text-xs text-right text-slate-700">
                                        <?php if ($bAmt !== null && $bAmt !== ''): ?>
                                            <?= $h(number_format((float)$bAmt, 2)) ?> BDT
                                        <?php else: ?>
                                            <span class="text-slate-400">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-4 py-2 text-xs text-slate-600">
                                        <?= $rank !== '' ? $h($rank) : '<span class="text-slate-400">—</span>' ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5" class="px-4 py-4 text-center text-xs text-slate-500">
                                    No bids captured yet for this tender.
                                </td>
                            </tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Internal tasks -->
            <div class="rounded-xl border border-slate-200 bg-white shadow-sm">
                <div class="flex items-center justify-between border-b border-slate-100 px-4 py-3">
                    <h2 class="text-sm font-semibold text-slate-800 flex items-center gap-2">
                        <span class="inline-flex h-6 w-6 items-center justify-center rounded-full bg-slate-900 text-[11px] text-white">
                            T
                        </span>
                        Internal tasks
                    </h2>
                    <span class="text-xs text-slate-500">
                        <?= isset($tasks) && is_array($tasks) ? $h(count($tasks)) : 0 ?> tasks
                    </span>
                </div>
                <div class="divide-y divide-slate-100">
                    <?php if (!empty($tasks)): ?>
                        <?php foreach ($tasks as $t): ?>
                            <?php
                            $titleTask = $t['title'] ?? '';
                            $ownerTask = $t['owner_name'] ?? '';
                            $dueTask   = $t['due_date'] ?? '';
                            $state     = strtolower((string)($t['status'] ?? 'open'));
                            $done      = ($state === 'done' || $state === 'completed');
                            ?>
                            <div class="flex items-start gap-3 px-4 py-3 text-xs">
                                <div class="mt-[3px]">
                                    <input type="checkbox" disabled <?= $done ? 'checked' : '' ?>
                                           class="h-3.5 w-3.5 rounded border-slate-300 text-emerald-600">
                                </div>
                                <div class="flex-1">
                                    <div class="font-medium text-slate-800"><?= $h($titleTask ?: 'Untitled task') ?></div>
                                    <div class="mt-0.5 text-[11px] text-slate-500">
                                        <?= $ownerTask !== '' ? 'Owner: '.$h($ownerTask).' • ' : '' ?>
                                        <?= $dueTask !== '' ? 'Due: '.$h($dueTask) : 'No due date' ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p class="px-4 py-4 text-xs text-slate-500">
                            No internal tasks defined for this tender yet.
                        </p>
                    <?php endif; ?>
                </div>
            </div>

        </section>

        <!-- RIGHT: meta, customer & files -->
        <aside class="space-y-4">

            <!-- Customer & channel -->
            <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                <h2 class="text-sm font-semibold text-slate-800 mb-3">Customer & channel</h2>
                <dl class="space-y-2 text-xs text-slate-600">
                    <div class="flex justify-between gap-4">
                        <dt class="text-slate-500">Customer</dt>
                        <dd class="text-right text-slate-800">
                            <?= $customer !== '' ? $h($customer) : '<span class="text-slate-400">—</span>' ?>
                        </dd>
                    </div>
                    <?php if ($customerRef !== ''): ?>
                        <div class="flex justify-between gap-4">
                            <dt class="text-slate-500">Customer ref</dt>
                            <dd class="text-right text-slate-800"><?= $h($customerRef) ?></dd>
                        </div>
                    <?php endif; ?>
                    <?php if ($channel !== ''): ?>
                        <div class="flex justify-between gap-4">
                            <dt class="text-slate-500">Channel</dt>
                            <dd class="text-right text-slate-800"><?= $h($channel) ?></dd>
                        </div>
                    <?php endif; ?>
                    <?php if ($owner !== ''): ?>
                        <div class="flex justify-between gap-4">
                            <dt class="text-slate-500">Owner</dt>
                            <dd class="text-right text-slate-800"><?= $h($owner) ?></dd>
                        </div>
                    <?php endif; ?>
                    <?php if ($createdAt !== ''): ?>
                        <div class="flex justify-between gap-4">
                            <dt class="text-slate-500">Created</dt>
                            <dd class="text-right text-slate-800"><?= $h($createdAt) ?></dd>
                        </div>
                    <?php endif; ?>
                    <?php if ($awardDate !== ''): ?>
                        <div class="flex justify-between gap-4">
                            <dt class="text-slate-500">Awarded</dt>
                            <dd class="text-right text-slate-800"><?= $h($awardDate) ?></dd>
                        </div>
                    <?php endif; ?>
                </dl>
            </div>

            <!-- Internal notes -->
            <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                <h2 class="text-sm font-semibold text-slate-800 mb-2">Internal notes</h2>
                <?php if ($remarks !== ''): ?>
                    <div class="text-xs text-slate-700 whitespace-pre-line">
                        <?= nl2br($h($remarks)) ?>
                    </div>
                <?php else: ?>
                    <p class="text-xs text-slate-500">No internal notes recorded.</p>
                <?php endif; ?>
            </div>

            <!-- Files -->
            <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                <h2 class="text-sm font-semibold text-slate-800 mb-2">Attachments</h2>
                <?php if (!empty($files)): ?>
                    <ul class="space-y-1 text-xs text-slate-600">
                        <?php foreach ($files as $f): ?>
                            <?php
                            $fname = $f['name'] ?? '';
                            $url   = $f['url']  ?? '#';
                            ?>
                            <li class="flex items-center gap-2">
                                <i class="fa fa-paperclip text-[11px] text-slate-400"></i>
                                <a href="<?= $h($url) ?>" target="_blank" class="hover:underline">
                                    <?= $h($fname ?: basename($url)) ?>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php else: ?>
                    <p class="text-xs text-slate-500">No files attached to this tender yet.</p>
                <?php endif; ?>
            </div>
        </aside>
    </div>

    <!-- How to use this page -->
    <section class="rounded-xl border border-dashed border-emerald-400 bg-emerald-50/60 px-4 py-4 text-sm text-slate-800">
        <h2 class="mb-2 flex items-center gap-2 text-sm font-semibold text-emerald-900">
            <span class="inline-flex h-6 w-6 items-center justify-center rounded-full bg-emerald-600 text-[11px] text-white">
                ?
            </span>
            How to use this page
        </h2>
        <ul class="ml-6 list-disc space-y-1 text-[13px]">
            <li>Review the <strong>scope, dates, and budget</strong> at the top to understand the tender context.</li>
            <li>Use the <strong>Bids</strong> section to compare competitors, amounts in BDT, and outcome status.</li>
            <li>Track internal work with the <strong>Tasks</strong> list so nothing is missed before submission.</li>
            <li>Keep <strong>internal notes</strong> for pricing strategy, risk, and lessons learned for future bids.</li>
            <li>All information is stored under this tenant’s <strong>org_id</strong> as part of your BizFlow pipeline.</li>
        </ul>
    </section>
</div>