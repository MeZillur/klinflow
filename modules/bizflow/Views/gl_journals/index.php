<?php
declare(strict_types=1);

/** @var array       $org */
/** @var string      $module_base */
/** @var array       $journals */
/** @var array       $filters */
/** @var string|null $flash */
/** @var bool        $storage_ready */
/** @var string      $title */

$h = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');

$module_base = rtrim($module_base ?? '/apps/bizflow', '/');
$orgName     = trim((string)($org['name'] ?? '')) ?: 'your organisation';
?>
<div class="space-y-6">

    <!-- Header + tabs -->
    <header class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
        <div>
            <h1 class="text-2xl md:text-3xl font-semibold tracking-tight">
                <?= $h($title ?? 'GL journals') ?>
            </h1>
            <p class="text-sm text-slate-500">
                Manual and system-posted journals for <?= $h($orgName) ?>.
            </p>
        </div>

        <?php
        $tabs = [
            ['Overview',       $module_base.'/accounting',              false],
            ['Journals',       $module_base.'/journals',                true],
            ['Trial balance',  $module_base.'/accounting/trial-balance', false],
            ['Balance sheet',  $module_base.'/accounting/balance-sheet', false],
            ['Bank reco',      $module_base.'/accounting/bank-reco',    false],
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

    <?php if ($flash): ?>
        <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2 text-xs text-emerald-800">
            <?= $h($flash) ?>
        </div>
    <?php endif; ?>

    <?php if (!$storage_ready): ?>
        <div class="rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-900">
            GL journals table <code>biz_gl_journals</code> is not created yet. You are viewing demo journals only.
        </div>
    <?php endif; ?>

    <section class="rounded-2xl border border-slate-200 bg-white shadow-sm">
        <div class="flex flex-col gap-3 border-b border-slate-100 px-4 py-3 md:flex-row md:items-center md:justify-between">
            <div>
                <h2 class="text-sm font-semibold text-slate-800">Journal register</h2>
                <p class="text-xs text-slate-500">
                    Filter by date range and search by journal no, reference or description.
                </p>
            </div>
            <a href="<?= $h($module_base.'/journals/create') ?>"
               class="inline-flex items-center gap-1 rounded-lg bg-slate-900 px-3 py-2 text-xs font-medium text-white shadow-sm hover:bg-slate-800">
                <span>+ New journal</span>
            </a>
        </div>

        <!-- Filters -->
        <form method="get" action="<?= $h($module_base.'/journals') ?>" class="border-b border-slate-100 px-4 py-3 text-xs">
            <div class="grid gap-2 md:grid-cols-4">
                <div class="md:col-span-2">
                    <input type="text"
                           name="q"
                           value="<?= $h($filters['q'] ?? '') ?>"
                           placeholder="Search journal no, reference, description…"
                           class="w-full rounded-lg border border-slate-200 px-3 py-1.5 text-xs focus:border-emerald-600 focus:outline-none focus:ring-1 focus:ring-emerald-600">
                </div>
                <div class="flex gap-2">
                    <input type="date"
                           name="from"
                           value="<?= $h($filters['from'] ?? '') ?>"
                           class="w-1/2 rounded-lg border border-slate-200 px-2 py-1.5 text-xs focus:border-emerald-600 focus:outline-none focus:ring-1 focus:ring-emerald-600">
                    <input type="date"
                           name="to"
                           value="<?= $h($filters['to'] ?? '') ?>"
                           class="w-1/2 rounded-lg border border-slate-200 px-2 py-1.5 text-xs focus:border-emerald-600 focus:outline-none focus:ring-1 focus:ring-emerald-600">
                </div>
                <div class="flex justify-end gap-2">
                    <a href="<?= $h($module_base.'/journals') ?>"
                       class="rounded-lg border border-slate-200 px-3 py-1.5 text-[11px] text-slate-600 hover:bg-slate-50">
                        Reset
                    </a>
                    <button type="submit"
                            class="rounded-lg bg-emerald-600 px-3 py-1.5 text-[11px] font-medium text-white hover:bg-emerald-700">
                        Apply filters
                    </button>
                </div>
            </div>
        </form>

        <!-- Table -->
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-100 text-xs">
                <thead class="bg-slate-50">
                    <tr>
                        <th class="px-3 py-2 text-left font-semibold text-slate-600">Journal</th>
                        <th class="px-3 py-2 text-left font-semibold text-slate-600">Reference / Description</th>
                        <th class="px-3 py-2 text-right font-semibold text-slate-600">Debit</th>
                        <th class="px-3 py-2 text-right font-semibold text-slate-600">Credit</th>
                        <th class="px-3 py-2 text-left font-semibold text-slate-600">Posted by</th>
                        <th class="px-3 py-2 text-left font-semibold text-slate-600"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                <?php if (!empty($journals)): ?>
                    <?php foreach ($journals as $row): ?>
                        <?php
                        $id   = (int)($row['id'] ?? 0);
                        $de   = (float)($row['total_debit']  ?? 0);
                        $cr   = (float)($row['total_credit'] ?? 0);
                        $ok   = round($de - $cr, 2) === 0.00;
                        ?>
                        <tr class="hover:bg-slate-50/60">
                            <td class="px-3 py-2 align-top">
                                <div class="font-semibold text-slate-800">
                                    <a href="<?= $h($module_base.'/journals/'.$id) ?>" class="hover:underline">
                                        <?= $h($row['journal_no'] ?? '') ?>
                                    </a>
                                </div>
                                <div class="text-[11px] text-slate-500">
                                    <?= $h($row['journal_date'] ?? '') ?>
                                </div>
                            </td>
                            <td class="px-3 py-2 align-top">
                                <div class="text-xs text-slate-800">
                                    <?= $h($row['reference'] ?? '—') ?>
                                </div>
                                <div class="text-[11px] text-slate-500">
                                    <?= $h($row['description'] ?? '') ?>
                                </div>
                            </td>
                            <td class="px-3 py-2 align-top text-right">
                                <?= $h(number_format($de, 2)) ?>
                            </td>
                            <td class="px-3 py-2 align-top text-right">
                                <?= $h(number_format($cr, 2)) ?>
                            </td>
                            <td class="px-3 py-2 align-top text-[11px] text-slate-600">
                                <?= $h($row['posted_by'] ?? '—') ?>
                            </td>
                            <td class="px-3 py-2 align-top text-right">
                                <?php if ($ok): ?>
                                    <span class="inline-flex items-center rounded-full bg-emerald-50 px-2 py-0.5 text-[10px] text-emerald-700">
                                        Balanced
                                    </span>
                                <?php else: ?>
                                    <span class="inline-flex items-center rounded-full bg-rose-50 px-2 py-0.5 text-[10px] text-rose-700">
                                        Imbalanced
                                    </span>
                                <?php endif; ?>
                                <a href="<?= $h($module_base.'/journals/'.$id.'/edit') ?>"
                                   class="ml-2 text-[11px] text-slate-500 hover:text-slate-800 hover:underline">
                                    Edit
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="6" class="px-3 py-6 text-center text-xs text-slate-500">
                            No journals found. Use <strong>New journal</strong> to add a manual posting.
                        </td>
                    </tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>

    <!-- How to use -->
    <section class="rounded-2xl border border-dashed border-emerald-400 bg-emerald-50/60 px-4 py-4 text-sm text-slate-800">
        <h2 class="mb-2 flex items-center gap-2 text-sm font-semibold text-emerald-900">
            <span class="inline-flex h-6 w-6 items-center justify-center rounded-full bg-emerald-600 text-[11px] text-white">
                ?
            </span>
            How to use this page
        </h2>
        <ul class="ml-6 list-disc space-y-1 text-[13px]">
            <li>Use <strong>New journal</strong> for adjustments that are not coming from other modules.</li>
            <li>Review system-generated journals from sales, purchases, payroll and LC once posting is wired.</li>
            <li>Check that every journal is <strong>balanced</strong> (debit = credit) before closing the month.</li>
        </ul>
    </section>
</div>