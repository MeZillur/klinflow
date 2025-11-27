<?php
/** @var array  $org */
/** @var array  $grns */
/** @var array  $metrics */
/** @var array  $filters */
/** @var bool   $storage_ready */
/** @var string $module_base */
/** @var string $title */
/** @var ?string $flash */

$h = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');

$module_base = rtrim($module_base ?? ($base ?? '/apps/bizflow'), '/');

$orgName = (string)($org['name'] ?? '');

$metrics = $metrics ?? [];
$total     = (int)($metrics['total']     ?? 0);
$posted    = (int)($metrics['posted']    ?? 0);
$draft     = (int)($metrics['draft']     ?? 0);
$cancelled = (int)($metrics['cancelled'] ?? 0);
$today     = (string)($metrics['today']  ?? date('Y-m-d'));

$filters = $filters ?? [];
$fq   = trim((string)($filters['q']      ?? ''));
$fst  = trim((string)($filters['status'] ?? ''));
$fref = trim((string)($filters['ref']    ?? ''));
$ffrom= trim((string)($filters['from']   ?? ''));
$fto  = trim((string)($filters['to']     ?? ''));

function grn_idx_amount($v): string {
    $n = (float)$v;
    return number_format($n, 2, '.', ',');
}
?>
<div class="space-y-6">

    <!-- Header + tabs -->
    <header class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
        <div class="space-y-1">
            <h1 class="text-2xl md:text-3xl font-semibold tracking-tight">
                <?= $h($title ?? 'Goods Receipts (GRN)') ?>
            </h1>
            <p class="text-sm text-slate-500">
                Track all goods receipts against your purchase orders and LCs for <?= $h($orgName ?: 'your organisation') ?>.
            </p>
         </div>

        <!-- Right-aligned BizFlow tabs -->
        <nav class="flex flex-wrap justify-end gap-1 text-xs md:text-[13px]">
            <?php
            $tabs = [
                ['Purchases', $module_base.'/purchases'],
                ['GRN',       $module_base.'/grn'],
                ['Items',     $module_base.'/items'],
                ['Suppliers', $module_base.'/suppliers'],
                ['Quotes',    $module_base.'/quotes'],
                ['LCs',       $module_base.'/lcs'],
                ['Reports',   $module_base.'/reports'],
                ['Settings',  $module_base.'/settings'],
            ];
            $current = $module_base.'/grn';
            foreach ($tabs as [$label, $url]):
                $active = $url === $current;
            ?>
                <a href="<?= $h($url) ?>"
                   class="inline-flex items-center gap-1 rounded-full px-3 py-1 border
                          <?= $active
                               ? 'border-emerald-600 bg-emerald-50 text-emerald-700 font-semibold'
                               : 'border-slate-200 text-slate-600 hover:bg-slate-50' ?>">
                    <span><?= $h($label) ?></span>
                </a>
            <?php endforeach; ?>
        </nav>
    </header>

    <!-- Flash message -->
    <?php if (!empty($flash)): ?>
        <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm text-emerald-800">
            <?= $h($flash) ?>
        </div>
    <?php endif; ?>

    <!-- Metrics row -->
    <section class="grid gap-3 md:grid-cols-4">
        <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
            <p class="text-xs font-medium text-slate-500">GRNs</p>
            <p class="mt-1 text-2xl font-semibold text-slate-900"><?= $h((string)$total) ?></p>
            <p class="mt-1 text-[11px] text-slate-500">Total documents in this tenant.</p>
        </div>
        <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
            <p class="text-xs font-medium text-slate-500">Posted</p>
            <p class="mt-1 text-2xl font-semibold text-emerald-700"><?= $h((string)$posted) ?></p>
            <p class="mt-1 text-[11px] text-slate-500">Fully posted to inventory.</p>
        </div>
        <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
            <p class="text-xs font-medium text-slate-500">Draft</p>
            <p class="mt-1 text-2xl font-semibold text-amber-600"><?= $h((string)$draft) ?></p>
            <p class="mt-1 text-[11px] text-slate-500">Pending review / posting.</p>
        </div>
        <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
            <p class="text-xs font-medium text-slate-500">Cancelled</p>
            <p class="mt-1 text-2xl font-semibold text-slate-700"><?= $h((string)$cancelled) ?></p>
            <p class="mt-1 text-[11px] text-slate-500">Voided or reversed GRNs.</p>
        </div>
    </section>

    <!-- Filters row -->
    <section class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm space-y-3">
        <form method="get" action="<?= $h($module_base.'/grn') ?>" class="space-y-3">
            <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                <div class="flex-1">
                    <label class="block text-xs font-medium text-slate-600 mb-1">
                        Search
                    </label>
                    <input type="text"
                           name="q"
                           value="<?= $h($fq) ?>"
                           placeholder="Search by GRN no, supplier, warehouse…"
                           class="block w-full rounded-lg border border-slate-200 px-3 py-1.5 text-sm
                                  focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500">
                </div>
                <div class="flex flex-wrap gap-3 md:w-auto">
                    <div>
                        <label class="block text-xs font-medium text-slate-600 mb-1">
                            Status
                        </label>
                        <select name="status"
                                class="block w-40 rounded-lg border border-slate-200 bg-white px-2.5 py-1.5 text-sm
                                       focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500">
                            <?php
                            $statusOptions = [
                                ''        => 'All',
                                'draft'   => 'Draft',
                                'posted'  => 'Posted',
                                'cancelled' => 'Cancelled',
                            ];
                            foreach ($statusOptions as $val => $label):
                            ?>
                                <option value="<?= $h($val) ?>" <?= $fst === $val ? 'selected' : '' ?>>
                                    <?= $h($label) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-600 mb-1">
                            Ref (PO / LC / manual)
                        </label>
                        <input type="text"
                               name="ref"
                               value="<?= $h($fref) ?>"
                               class="block w-44 rounded-lg border border-slate-200 px-3 py-1.5 text-sm
                                      focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500">
                    </div>
                    <div class="flex gap-2">
                        <div>
                            <label class="block text-xs font-medium text-slate-600 mb-1">
                                From
                            </label>
                            <input type="date"
                                   name="from"
                                   value="<?= $h($ffrom) ?>"
                                   class="block w-32 rounded-lg border border-slate-200 px-2 py-1.5 text-sm
                                          focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-slate-600 mb-1">
                                To
                            </label>
                            <input type="date"
                                   name="to"
                                   value="<?= $h($fto) ?>"
                                   class="block w-32 rounded-lg border border-slate-200 px-2 py-1.5 text-sm
                                          focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500">
                        </div>
                    </div>
                </div>
            </div>

            <div class="flex flex-wrap items-center justify-between gap-2 text-xs">
                <p class="text-[11px] text-slate-500">
                    Today: <span class="font-medium text-slate-900"><?= $h($today) ?></span>
                </p>
                <div class="flex gap-2">
                    <a href="<?= $h($module_base.'/grn') ?>"
                       class="inline-flex items-center gap-1 rounded-lg border border-slate-200 px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50">
                        <i class="fa fa-rotate-left text-[11px]"></i>
                        <span>Reset</span>
                    </a>
                    <button type="submit"
                            class="inline-flex items-center gap-1 rounded-lg bg-emerald-600 px-3.5 py-1.5 text-xs font-semibold text-white shadow-sm hover:bg-emerald-700 focus:outline-none focus:ring-2 focus:ring-emerald-600 focus:ring-offset-1">
                        <i class="fa fa-filter text-[11px]"></i>
                        <span>Apply filters</span>
                    </button>
                </div>
            </div>
        </form>
    </section>

    <!-- GRN table -->
    <section class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm space-y-3">
        <div class="flex items-center justify-between gap-2">
            <h2 class="text-sm font-semibold text-slate-900 flex items-center gap-2">
                <span class="inline-flex h-6 w-6 items-center justify-center rounded-full bg-emerald-600 text-[11px] text-white">
                    GRN
                </span>
                <span>GRN register</span>
            </h2>
            <p class="text-[11px] text-slate-500">
                GRNs are normally created by receiving from a purchase (Receive / GRN). Manual GRN will be disabled later.
            </p>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-xs">
                <thead class="bg-slate-50 text-[11px] font-semibold uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="px-3 py-2 text-left">GRN #</th>
                        <th class="px-3 py-2 text-left">Date</th>
                        <th class="px-3 py-2 text-left">Supplier</th>
                        <th class="px-3 py-2 text-left">Warehouse</th>
                        <th class="px-3 py-2 text-left">Ref</th>
                        <th class="px-3 py-2 text-center">Status</th>
                        <th class="px-3 py-2 text-right">Qty</th>
                        <th class="px-3 py-2 text-right">Amount</th>
                        <th class="px-3 py-2 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 bg-white">
                <?php if (!empty($grns)): ?>
                    <?php foreach ($grns as $row): ?>
                        <?php
                        $id      = (int)($row['id'] ?? 0);
                        $grnNo   = (string)($row['grn_no'] ?? ('GRN-'.$id));
                        $gDate   = (string)($row['grn_date'] ?? $row['date'] ?? '');
                        $supp    = (string)($row['supplier_name'] ?? '');
                        $wh      = (string)($row['warehouse_name'] ?? $row['warehouse_code'] ?? '');
                        $refType = (string)($row['ref_type'] ?? '');
                        $refNo   = (string)($row['ref_no'] ?? '');
                        $status  = strtolower((string)($row['status'] ?? 'draft'));
                        $postedAtRow = $row['posted_at'] ?? null;

                        $qty   = (float)($row['total_qty']    ?? 0);
                        $amt   = (float)($row['total_amount'] ?? 0);
                        $curr  = (string)($row['currency']    ?? 'BDT');

                        $viewUrl = $module_base.'/grn/'.$id;
                        $postUrl = $module_base.'/grn/'.$id.'/post';

                        $isPosted = ($status === 'posted') || !empty($postedAtRow);
                        ?>
                        <tr class="hover:bg-emerald-50/40">
                            <td class="px-3 py-2 align-top">
                                <a href="<?= $h($viewUrl) ?>"
                                   class="text-slate-900 font-medium hover:underline">
                                    <?= $h($grnNo) ?>
                                </a>
                                <div class="text-[11px] text-slate-500">#<?= $h((string)$id) ?></div>
                            </td>
                            <td class="px-3 py-2 align-top text-slate-700">
                                <?= $gDate !== '' ? $h($gDate) : '&mdash;' ?>
                            </td>
                            <td class="px-3 py-2 align-top text-slate-700">
                                <?= $supp !== '' ? $h($supp) : '&mdash;' ?>
                            </td>
                            <td class="px-3 py-2 align-top text-slate-700">
                                <?= $wh !== '' ? $h($wh) : '&mdash;' ?>
                            </td>
                            <td class="px-3 py-2 align-top text-slate-700">
                                <?php if ($refNo !== '' || $refType !== ''): ?>
                                    <div><?= $refNo !== '' ? $h($refNo) : '&mdash;' ?></div>
                                    <?php if ($refType !== ''): ?>
                                        <div class="text-[11px] text-slate-500"><?= $h($refType) ?></div>
                                    <?php endif; ?>
                                <?php else: ?>
                                    &mdash;
                                <?php endif; ?>
                            </td>
                            <td class="px-3 py-2 align-top text-center">
                                <?php if ($isPosted): ?>
                                    <span class="inline-flex items-center rounded-full bg-emerald-50 px-2 py-0.5 text-[11px] font-medium text-emerald-700 border border-emerald-100">
                                        Posted
                                    </span>
                                <?php elseif ($status === 'cancelled'): ?>
                                    <span class="inline-flex items-center rounded-full bg-rose-50 px-2 py-0.5 text-[11px] font-medium text-rose-700 border border-rose-100">
                                        Cancelled
                                    </span>
                                <?php else: ?>
                                    <span class="inline-flex items-center rounded-full bg-amber-50 px-2 py-0.5 text-[11px] font-medium text-amber-700 border border-amber-100">
                                        Draft
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td class="px-3 py-2 align-top text-right text-slate-700">
                                <?= $h(number_format($qty, 3)) ?>
                            </td>
                            <td class="px-3 py-2 align-top text-right text-slate-900">
                                <?= $h(grn_idx_amount($amt)) ?> <?= $h($curr) ?>
                            </td>
                            <td class="px-3 py-2 align-top text-right">
                                <div class="flex justify-end gap-1">
                                    <a href="<?= $h($viewUrl) ?>"
                                       class="inline-flex items-center gap-1 rounded-lg border border-slate-200 bg-white px-2.5 py-1 text-[11px] font-medium text-slate-700 hover:bg-slate-50">
                                        <i class="fa fa-eye text-[10px]"></i>
                                        <span>View</span>
                                    </a>
                                    <?php if ($storage_ready && !$isPosted): ?>
                                        <form method="post" action="<?= $h($postUrl) ?>">
                                            <button type="submit"
                                                    class="inline-flex items-center gap-1 rounded-lg bg-emerald-600 px-2.5 py-1 text-[11px] font-semibold text-white shadow-sm hover:bg-emerald-700 focus:outline-none focus:ring-1 focus:ring-emerald-600">
                                                <i class="fa fa-arrow-up-right-from-square text-[10px]"></i>
                                                <span>Post</span>
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="9" class="px-3 py-4 text-center text-xs text-slate-500">
                            No GRNs found for the current filters.
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
            <li>Use the <strong>filters</strong> to narrow GRNs by status, reference, supplier, warehouse or date range.</li>
            <li>GRNs are usually created from the <strong>Purchase “Receive / GRN”</strong> action; manual GRN entry will be phased out.</li>
            <li>Check that <strong>posted</strong> GRNs match your physical stock and purchase records.</li>
            <li>Draft GRNs can be reviewed, corrected and then posted via the <strong>Post</strong> button, which updates inventory balances.</li>
            <li>Use this register as the audit trail between <strong>purchases</strong> and <strong>inventory movements</strong> for each organisation.</li>
        </ul>
    </section>
</div>