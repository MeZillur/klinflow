<?php
declare(strict_types=1);

/** @var array  $org */
/** @var string $module_base */
/** @var array  $rows */
/** @var string $from */
/** @var string $to */
/** @var float  $total_debit */
/** @var float  $total_credit */
/** @var bool   $imbalanced */
/** @var bool   $storage_ready */
/** @var string $title */

$h = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');

$module_base = rtrim($module_base ?? '/apps/bizflow', '/');
$orgName     = trim((string)($org['name'] ?? '')) ?: 'your organisation';
?>
<div class="space-y-6">

    <!-- Header + tabs -->
    <header class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
        <div>
            <h1 class="text-2xl md:text-3xl font-semibold tracking-tight">
                <?= $h($title ?? 'Trial balance') ?>
            </h1>
            <p class="text-sm text-slate-500">
                Debits and credits for <?= $h($orgName) ?> — every GL account in one table.
            </p>
        </div>

        <?php
        $tabs = [
            ['Overview',       $module_base.'/accounting',              false],
            ['Journals',       $module_base.'/journals',                false],
            ['Trial balance',  $module_base.'/accounting/trial-balance', true],
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

    <?php if (!$storage_ready): ?>
        <div class="rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-900">
            GL tables are not wired yet. You are seeing a demo trial balance only.
        </div>
    <?php endif; ?>

    <!-- Filters + summary -->
    <section class="rounded-2xl border border-slate-200 bg-white shadow-sm">
        <div class="flex flex-col gap-3 border-b border-slate-100 px-4 py-3 md:flex-row md:items-center md:justify-between">
            <div>
                <h2 class="text-sm font-semibold text-slate-800">Trial balance</h2>
                <p class="text-xs text-slate-500">
                    Check that <strong>total debits</strong> and <strong>total credits</strong> match for the selected range.
                </p>
            </div>
        </div>

        <form method="get" action="<?= $h($module_base.'/accounting/trial-balance') ?>" class="border-b border-slate-100 px-4 py-3 text-xs">
            <div class="flex flex-col gap-2 md:flex-row md:items-center md:justify-between">
                <div class="flex gap-2">
                    <input type="date"
                           name="from"
                           value="<?= $h($from) ?>"
                           class="w-32 rounded-lg border border-slate-200 px-2 py-1.5 text-xs focus:border-emerald-600 focus:outline-none focus:ring-1 focus:ring-emerald-600">
                    <span class="self-center text-[11px] text-slate-500">to</span>
                    <input type="date"
                           name="to"
                           value="<?= $h($to) ?>"
                           class="w-32 rounded-lg border border-slate-200 px-2 py-1.5 text-xs focus:border-emerald-600 focus:outline-none focus:ring-1 focus:ring-emerald-600">
                </div>

                <div class="flex items-center gap-3">
                    <div class="text-[11px] text-slate-500">
                        Debit: <span class="font-semibold text-slate-900"><?= $h(number_format($total_debit, 2)) ?></span> ·
                        Credit: <span class="font-semibold text-slate-900"><?= $h(number_format($total_credit, 2)) ?></span>
                        <?php if ($imbalanced): ?>
                            <span class="ml-2 inline-flex items-center rounded-full bg-rose-100 px-2 py-0.5 text-[10px] font-semibold text-rose-700">
                                Imbalanced
                            </span>
                        <?php else: ?>
                            <span class="ml-2 inline-flex items-center rounded-full bg-emerald-100 px-2 py-0.5 text-[10px] font-semibold text-emerald-700">
                                Balanced
                            </span>
                        <?php endif; ?>
                    </div>

                    <div class="flex gap-2">
                        <a href="<?= $h($module_base.'/accounting/trial-balance') ?>"
                           class="rounded-lg border border-slate-200 px-3 py-1.5 text-[11px] text-slate-600 hover:bg-slate-50">
                            Reset
                        </a>
                        <button type="submit"
                                class="rounded-lg bg-emerald-600 px-3 py-1.5 text-[11px] font-medium text-white hover:bg-emerald-700">
                            Apply
                        </button>
                    </div>
                </div>
            </div>
        </form>

        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-100 text-xs">
                <thead class="bg-slate-50">
                    <tr>
                        <th class="px-3 py-2 text-left font-semibold text-slate-600">Account</th>
                        <th class="px-3 py-2 text-right font-semibold text-slate-600">Opening</th>
                        <th class="px-3 py-2 text-right font-semibold text-slate-600">Debit</th>
                        <th class="px-3 py-2 text-right font-semibold text-slate-600">Credit</th>
                        <th class="px-3 py-2 text-right font-semibold text-slate-600">Closing</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                <?php if (!empty($rows)): ?>
                    <?php foreach ($rows as $r): ?>
                        <?php
                        $code   = (string)($r['account_code'] ?? '');
                        $name   = (string)($r['account_name'] ?? '');
                        $open   = (float)($r['opening']       ?? 0);
                        $pd     = (float)($r['period_debit']  ?? 0);
                        $pc     = (float)($r['period_credit'] ?? 0);
                        $closing = $open + $pd - $pc;
                        ?>
                        <tr class="hover:bg-slate-50/60">
                            <td class="px-3 py-2 align-top">
                                <div class="font-medium text-slate-800"><?= $h($code) ?> — <?= $h($name) ?></div>
                            </td>
                            <td class="px-3 py-2 align-top text-right">
                                <?= $h(number_format($open, 2)) ?>
                            </td>
                            <td class="px-3 py-2 align-top text-right">
                                <?= $h(number_format($pd, 2)) ?>
                            </td>
                            <td class="px-3 py-2 align-top text-right">
                                <?= $h(number_format($pc, 2)) ?>
                            </td>
                            <td class="px-3 py-2 align-top text-right">
                                <?= $h(number_format($closing, 2)) ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="5" class="px-3 py-6 text-center text-xs text-slate-500">
                            No GL activity found for this period yet.
                        </td>
                    </tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>

    <!-- How to use this page -->
    <section class="rounded-2xl border border-dashed border-emerald-400 bg-emerald-50/60 px-4 py-4 text-sm text-slate-800">
        <h2 class="mb-2 flex items-center gap-2 text-sm font-semibold text-emerald-900">
            <span class="inline-flex h-6 w-6 items-center justify-center rounded-full bg-emerald-600 text-[11px] text-white">
                ?
            </span>
            How to use this page
        </h2>
        <ul class="ml-6 list-disc space-y-1 text-[13px]">
            <li>Select a <strong>from</strong> and <strong>to</strong> date matching your reporting period.</li>
            <li>Confirm that <strong>total debit</strong> equals <strong>total credit</strong> for a balanced GL.</li>
            <li>Use this as your base control before preparing Balance Sheet / P&amp;L externally or in a later phase.</li>
        </ul>
    </section>
</div>