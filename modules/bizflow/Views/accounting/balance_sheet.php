<?php
declare(strict_types=1);

/** @var array  $org */
/** @var string $module_base */
/** @var array  $groups */
/** @var float  $assets_total */
/** @var float  $liab_total */
/** @var float  $equity_total */
/** @var bool   $balanced */
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
                <?= $h($title ?? 'Balance sheet') ?>
            </h1>
            <p class="text-sm text-slate-500">
                Snapshot of assets, liabilities and equity for <?= $h($orgName) ?>.
            </p>
        </div>

        <?php
        $tabs = [
            ['Overview',       $module_base.'/accounting',              false],
            ['Journals',       $module_base.'/journals',                false],
            ['Trial balance',  $module_base.'/accounting/trial-balance', false],
            ['Balance sheet',  $module_base.'/accounting/balance-sheet', true],
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
            This balance sheet is built from demo figures. Once GL tables are wired, it will be driven directly
            from the trial balance.
        </div>
    <?php endif; ?>

    <!-- Summary row -->
    <section class="grid gap-3 md:grid-cols-3 text-xs">
        <div class="rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 shadow-sm">
            <div class="text-[11px] text-emerald-700">Assets</div>
            <div class="mt-1 text-xl font-semibold text-emerald-900">
                <?= $h(number_format($assets_total, 2)) ?> BDT
            </div>
        </div>
        <div class="rounded-2xl border border-sky-200 bg-sky-50 px-4 py-3 shadow-sm">
            <div class="text-[11px] text-sky-700">Liabilities</div>
            <div class="mt-1 text-xl font-semibold text-sky-900">
                <?= $h(number_format($liab_total, 2)) ?> BDT
            </div>
        </div>
        <div class="rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 shadow-sm">
            <div class="text-[11px] text-rose-700">Equity</div>
            <div class="mt-1 text-xl font-semibold text-rose-900">
                <?= $h(number_format($equity_total, 2)) ?> BDT
                <?php if ($balanced): ?>
                    <span class="ml-2 inline-flex items-center rounded-full bg-emerald-100 px-2 py-0.5 text-[10px] font-semibold text-emerald-700">
                        Balanced
                    </span>
                <?php else: ?>
                    <span class="ml-2 inline-flex items-center rounded-full bg-rose-100 px-2 py-0.5 text-[10px] font-semibold text-rose-700">
                        Imbalanced
                    </span>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <!-- Two-column layout -->
    <div class="grid gap-6 md:grid-cols-2">
        <!-- Assets -->
        <section class="rounded-2xl border border-slate-200 bg-white px-4 py-4 shadow-sm">
            <h2 class="mb-2 text-sm font-semibold text-slate-800">Assets</h2>
            <ul class="space-y-1 text-xs">
                <?php foreach ($groups['assets'] as $row): ?>
                    <li class="flex items-center justify-between">
                        <span><?= $h($row['name']) ?></span>
                        <span class="font-semibold"><?= $h(number_format((float)$row['amount'], 2)) ?></span>
                    </li>
                <?php endforeach; ?>
            </ul>
        </section>

        <!-- Liabilities + Equity -->
        <section class="rounded-2xl border border-slate-200 bg-white px-4 py-4 shadow-sm space-y-4">
            <div>
                <h2 class="mb-2 text-sm font-semibold text-slate-800">Liabilities</h2>
                <ul class="space-y-1 text-xs">
                    <?php foreach ($groups['liabilities'] as $row): ?>
                        <li class="flex items-center justify-between">
                            <span><?= $h($row['name']) ?></span>
                            <span class="font-semibold"><?= $h(number_format((float)$row['amount'], 2)) ?></span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>

            <div>
                <h2 class="mb-2 text-sm font-semibold text-slate-800">Equity</h2>
                <ul class="space-y-1 text-xs">
                    <?php foreach ($groups['equity'] as $row): ?>
                        <li class="flex items-center justify-between">
                            <span><?= $h($row['name']) ?></span>
                            <span class="font-semibold"><?= $h(number_format((float)$row['amount'], 2)) ?></span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </section>
    </div>

    <!-- How to use -->
    <section class="rounded-2xl border border-dashed border-emerald-400 bg-emerald-50/60 px-4 py-4 text-sm text-slate-800">
        <h2 class="mb-2 flex items-center gap-2 text-sm font-semibold text-emerald-900">
            <span class="inline-flex h-6 w-6 items-center justify-center rounded-full bg-emerald-600 text-[11px] text-white">
                ?
            </span>
            How to use this page
        </h2>
        <ul class="ml-6 list-disc space-y-1 text-[13px]">
            <li>Use this as your <strong>position report</strong> at month-end or year-end.</li>
            <li>Assets should equal liabilities + equity (including retained earnings).</li>
            <li>When GL tables are wired, these figures will come directly from the trial balance.</li>
        </ul>
    </section>
</div>