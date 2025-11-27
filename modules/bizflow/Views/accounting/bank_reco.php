<?php
declare(strict_types=1);

/** @var array  $org */
/** @var string $module_base */
/** @var int    $account_id */
/** @var float  $book_balance */
/** @var float  $bank_balance */
/** @var float  $adjusted_bank */
/** @var float  $difference */
/** @var array  $items */
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
                <?= $h($title ?? 'Bank reconciliation') ?>
            </h1>
            <p class="text-sm text-slate-500">
                Compare GL bank balance with the bank statement for <?= $h($orgName) ?>.
            </p>
        </div>

        <?php
        $tabs = [
            ['Overview',       $module_base.'/accounting',              false],
            ['Journals',       $module_base.'/journals',                false],
            ['Trial balance',  $module_base.'/accounting/trial-balance', false],
            ['Balance sheet',  $module_base.'/accounting/balance-sheet', false],
            ['Bank reco',      $module_base.'/accounting/bank-reco',    true],
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
            Bank reconciliation is in preview mode using demo values. Later this will pull from GL bank accounts
            and imported bank statements.
        </div>
    <?php endif; ?>

    <!-- Summary row -->
    <section class="grid gap-3 md:grid-cols-4 text-xs">
        <div class="rounded-2xl border border-slate-200 bg-white px-4 py-3 shadow-sm">
            <div class="text-[11px] text-slate-500">GL (book) balance</div>
            <div class="mt-1 text-xl font-semibold text-slate-900">
                <?= $h(number_format($book_balance, 2)) ?> BDT
            </div>
        </div>
        <div class="rounded-2xl border border-sky-200 bg-sky-50 px-4 py-3 shadow-sm">
            <div class="text-[11px] text-sky-700">Bank statement balance</div>
            <div class="mt-1 text-xl font-semibold text-sky-900">
                <?= $h(number_format($bank_balance, 2)) ?> BDT
            </div>
        </div>
        <div class="rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 shadow-sm">
            <div class="text-[11px] text-emerald-700">Adjusted bank balance</div>
            <div class="mt-1 text-xl font-semibold text-emerald-900">
                <?= $h(number_format($adjusted_bank, 2)) ?> BDT
            </div>
        </div>
        <div class="rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 shadow-sm">
            <div class="text-[11px] text-rose-700">Difference (book – adjusted)</div>
            <div class="mt-1 text-xl font-semibold text-rose-900">
                <?= $h(number_format($difference, 2)) ?> BDT
            </div>
        </div>
    </section>

    <!-- Items -->
    <section class="rounded-2xl border border-slate-200 bg-white px-4 py-4 shadow-sm">
        <h2 class="mb-2 text-sm font-semibold text-slate-800">Reconciling items</h2>
        <p class="mb-3 text-xs text-slate-500">
            Items that make up the difference between bank balance and GL book balance.
        </p>

        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-100 text-xs">
                <thead class="bg-slate-50">
                    <tr>
                        <th class="px-3 py-2 text-left font-semibold text-slate-600">Date</th>
                        <th class="px-3 py-2 text-left font-semibold text-slate-600">Reference</th>
                        <th class="px-3 py-2 text-left font-semibold text-slate-600">Type</th>
                        <th class="px-3 py-2 text-left font-semibold text-slate-600">Description</th>
                        <th class="px-3 py-2 text-right font-semibold text-slate-600">Amount (BDT)</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                <?php if (!empty($items)): ?>
                    <?php foreach ($items as $row): ?>
                        <?php
                        $kindLabel = match ($row['kind'] ?? '') {
                            'outstanding_cheque' => 'Outstanding cheque',
                            'bank_charge'        => 'Bank charge',
                            'uncleared_deposit'  => 'Uncleared deposit',
                            default              => (string)($row['kind'] ?? 'Other'),
                        };
                        ?>
                        <tr class="hover:bg-slate-50/60">
                            <td class="px-3 py-2 align-top"><?= $h($row['date'] ?? '—') ?></td>
                            <td class="px-3 py-2 align-top"><?= $h($row['ref'] ?? '—') ?></td>
                            <td class="px-3 py-2 align-top"><?= $h($kindLabel) ?></td>
                            <td class="px-3 py-2 align-top"><?= $h($row['description'] ?? '') ?></td>
                            <td class="px-3 py-2 align-top text-right">
                                <?= $h(number_format((float)($row['amount'] ?? 0), 2)) ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="5" class="px-3 py-6 text-center text-xs text-slate-500">
                            No reconciling items yet.
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
            <li>Start from your <strong>bank statement closing balance</strong>.</li>
            <li>Add / subtract reconciling items (outstanding cheques, bank charges, uncleared deposits).</li>
            <li>Ensure the <strong>adjusted bank balance</strong> matches your GL book balance.</li>
            <li>Later, this page will pull data from imported bank statements and GL bank accounts automatically.</li>
        </ul>
    </section>
</div>