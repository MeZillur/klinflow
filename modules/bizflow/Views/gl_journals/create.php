<?php
declare(strict_types=1);

/** @var array  $org */
/** @var string $module_base */
/** @var array  $journal */
/** @var string $mode */
/** @var string $title */

$h = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');

$module_base = rtrim($module_base ?? '/apps/bizflow', '/');
$orgName     = trim((string)($org['name'] ?? '')) ?: 'your organisation';

$isEdit = $mode === 'edit';
$action = $isEdit ? $module_base.'/journals/'.$journal['id'] : $module_base.'/journals';
?>
<div class="space-y-6">

    <!-- Header + tabs -->
    <header class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
        <div>
            <h1 class="text-2xl md:text-3xl font-semibold tracking-tight">
                <?= $h($isEdit ? 'Edit journal' : 'New journal') ?>
            </h1>
            <p class="text-sm text-slate-500">
                <?= $h($orgName) ?> — manual GL posting for adjustments or non-module entries.
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

    <section class="rounded-2xl border border-slate-200 bg-white px-4 py-4 shadow-sm">
        <form method="post" action="<?= $h($action) ?>" class="space-y-4">
            <div class="grid gap-3 md:grid-cols-4 text-xs">
                <div>
                    <label class="mb-1 block text-[11px] font-medium text-slate-700">Journal no</label>
                    <input type="text"
                           name="journal_no"
                           value="<?= $h($journal['journal_no'] ?? '') ?>"
                           class="w-full rounded-lg border border-slate-200 px-2 py-1.5 text-xs focus:border-emerald-600 focus:outline-none focus:ring-1 focus:ring-emerald-600">
                </div>
                <div>
                    <label class="mb-1 block text-[11px] font-medium text-slate-700">Date</label>
                    <input type="date"
                           name="journal_date"
                           value="<?= $h($journal['journal_date'] ?? '') ?>"
                           class="w-full rounded-lg border border-slate-200 px-2 py-1.5 text-xs focus:border-emerald-600 focus:outline-none focus:ring-1 focus:ring-emerald-600">
                </div>
                <div class="md:col-span-2">
                    <label class="mb-1 block text-[11px] font-medium text-slate-700">Reference</label>
                    <input type="text"
                           name="reference"
                           value="<?= $h($journal['reference'] ?? '') ?>"
                           placeholder="e.g. INV-2025-001, PAY-2025-11"
                           class="w-full rounded-lg border border-slate-200 px-2 py-1.5 text-xs focus:border-emerald-600 focus:outline-none focus:ring-1 focus:ring-emerald-600">
                </div>
            </div>

            <div>
                <label class="mb-1 block text-[11px] font-medium text-slate-700">Description</label>
                <textarea name="description"
                          rows="2"
                          class="w-full rounded-lg border border-slate-200 px-2 py-1.5 text-xs focus:border-emerald-600 focus:outline-none focus:ring-1 focus:ring-emerald-600"><?= $h($journal['description'] ?? '') ?></textarea>
            </div>

            <!-- Lines (simple two-row grid for now) -->
            <div class="rounded-xl border border-slate-200 bg-slate-50 px-3 py-3">
                <div class="mb-2 flex items-center justify-between text-[11px] text-slate-600">
                    <span>Lines (account-wise debit / credit)</span>
                    <span>Ensure total debit equals total credit</span>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full text-xs">
                        <thead>
                            <tr class="text-left text-[11px] text-slate-500">
                                <th class="px-2 py-1">Account code</th>
                                <th class="px-2 py-1">Account name</th>
                                <th class="px-2 py-1">Line memo</th>
                                <th class="px-2 py-1 text-right">Debit</th>
                                <th class="px-2 py-1 text-right">Credit</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $lines = $journal['lines'] ?? []; ?>
                            <?php foreach ($lines as $i => $line): ?>
                                <tr class="border-t border-slate-200">
                                    <td class="px-2 py-1">
                                        <input type="text"
                                               name="lines[<?= $i ?>][account_code]"
                                               value="<?= $h($line['account_code'] ?? '') ?>"
                                               class="w-full rounded-lg border border-slate-200 px-2 py-1 text-xs focus:border-emerald-600 focus:outline-none focus:ring-1 focus:ring-emerald-600">
                                    </td>
                                    <td class="px-2 py-1">
                                        <input type="text"
                                               name="lines[<?= $i ?>][account_name]"
                                               value="<?= $h($line['account_name'] ?? '') ?>"
                                               class="w-full rounded-lg border border-slate-200 px-2 py-1 text-xs focus:border-emerald-600 focus:outline-none focus:ring-1 focus:ring-emerald-600">
                                    </td>
                                    <td class="px-2 py-1">
                                        <input type="text"
                                               name="lines[<?= $i ?>][description]"
                                               value="<?= $h($line['description'] ?? '') ?>"
                                               class="w-full rounded-lg border border-slate-200 px-2 py-1 text-xs focus:border-emerald-600 focus:outline-none focus:ring-1 focus:ring-emerald-600">
                                    </td>
                                    <td class="px-2 py-1 text-right">
                                        <input type="number" step="0.01"
                                               name="lines[<?= $i ?>][debit]"
                                               value="<?= $h($line['debit'] ?? '') ?>"
                                               class="w-24 rounded-lg border border-slate-200 px-2 py-1 text-right text-xs focus:border-emerald-600 focus:outline-none focus:ring-1 focus:ring-emerald-600">
                                    </td>
                                    <td class="px-2 py-1 text-right">
                                        <input type="number" step="0.01"
                                               name="lines[<?= $i ?>][credit]"
                                               value="<?= $h($line['credit'] ?? '') ?>"
                                               class="w-24 rounded-lg border border-slate-200 px-2 py-1 text-right text-xs focus:border-emerald-600 focus:outline-none focus:ring-1 focus:ring-emerald-600">
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="flex justify-end gap-2 text-xs">
                <a href="<?= $h($module_base.'/journals') ?>"
                   class="rounded-lg border border-slate-200 px-3 py-2 text-slate-600 hover:bg-slate-50">
                    Cancel
                </a>
                <button type="submit"
                        class="rounded-lg bg-emerald-600 px-3 py-2 font-medium text-white hover:bg-emerald-700">
                    <?= $h($isEdit ? 'Save changes (preview)' : 'Save journal (preview)') ?>
                </button>
            </div>
        </form>
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
            <li>Use this only for entries that are not coming from sales, purchases, payroll or LC modules.</li>
            <li>Always keep <strong>total debits</strong> equal to <strong>total credits</strong> for each journal.</li>
            <li>Once GL posting is wired, this page will save into <code>biz_gl_journals</code> and <code>biz_gl_journal_lines</code>.</li>
        </ul>
    </section>
</div>