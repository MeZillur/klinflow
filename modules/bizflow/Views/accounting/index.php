<?php
declare(strict_types=1);

/** @var array  $org */
/** @var string $module_base */
/** @var array  $metrics */
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
                <?= $h($title ?? 'Accounting overview') ?>
            </h1>
            <p class="text-sm text-slate-500">
                Mini GL view for <?= $h($orgName) ?> — journals, trial balance, balance sheet and bank reconciliation.
            </p>
        </div>

        <?php
        $tabs = [
            ['Overview',       $module_base.'/accounting',           true],
            ['Journals',       $module_base.'/journals',             false],
            ['Trial balance',  $module_base.'/accounting/trial-balance',  false],
            ['Balance sheet',  $module_base.'/accounting/balance-sheet',  false],
            ['Bank reco',      $module_base.'/accounting/bank-reco', false],
  			['Banking',        $module_base.'/banking', false],
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
        GL tables not detected yet. You are seeing demo metrics only.
        Once <code>biz_gl_journals</code>, <code>biz_gl_lines</code> (or <code>biz_gl_journal_lines</code>) 
        and <code>biz_gl_accounts</code> exist, this overview will use real data.
    </div>
<?php endif; ?>

    <!-- Metrics row -->
    <section class="grid gap-3 md:grid-cols-4 text-xs">
        <div class="rounded-2xl border border-slate-200 bg-white px-4 py-3 shadow-sm">
            <div class="text-[11px] text-slate-500">Period</div>
            <div class="mt-1 text-xl font-semibold text-slate-900">
                <?= $h($metrics['month'] ?? '') ?>
            </div>
        </div>
        <div class="rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 shadow-sm">
            <div class="text-[11px] text-emerald-700">Journals this month</div>
            <div class="mt-1 text-xl font-semibold text-emerald-900">
                <?= $h((string)($metrics['journals_count'] ?? 0)) ?>
            </div>
        </div>
        <div class="rounded-2xl border border-sky-200 bg-sky-50 px-4 py-3 shadow-sm">
            <div class="text-[11px] text-sky-700">Debits (this month)</div>
            <div class="mt-1 text-xl font-semibold text-sky-900">
                <?= $h(number_format((float)($metrics['debit_month'] ?? 0), 2)) ?> BDT
            </div>
        </div>
        <div class="rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 shadow-sm">
            <div class="text-[11px] text-rose-700">Credits (this month)</div>
            <div class="mt-1 text-xl font-semibold text-rose-900">
                <?= $h(number_format((float)($metrics['credit_month'] ?? 0), 2)) ?> BDT
            </div>
        </div>
    </section>

    <div class="grid gap-6 lg:grid-cols-[2.1fr,1.1fr]">
        <!-- LEFT: Quick cards -->
        <section class="grid gap-4 md:grid-cols-2">
            <a href="<?= $h($module_base.'/journals') ?>"
               class="rounded-2xl border border-slate-200 bg-white px-4 py-4 shadow-sm hover:border-emerald-500 hover:shadow-md transition">
                <div class="text-xs font-semibold text-slate-500 mb-1">Journals</div>
                <div class="text-lg font-semibold text-slate-900 mb-1">Manual & system journals</div>
                <p class="text-xs text-slate-500">
                    View and add GL journals (sales, purchases, payroll, adjustments) in one place.
                </p>
            </a>

            <a href="<?= $h($module_base.'/accounting/trial-balance') ?>"
               class="rounded-2xl border border-slate-200 bg-white px-4 py-4 shadow-sm hover:border-emerald-500 hover:shadow-md transition">
                <div class="text-xs font-semibold text-slate-500 mb-1">Trial balance</div>
                <div class="text-lg font-semibold text-slate-900 mb-1">Control debits and credits</div>
                <p class="text-xs text-slate-500">
                    Check that every GL posting balances before you finalize financial statements.
                </p>
            </a>

            <a href="<?= $h($module_base.'/accounting/balance-sheet') ?>"
               class="rounded-2xl border border-slate-200 bg-white px-4 py-4 shadow-sm hover:border-emerald-500 hover:shadow-md transition">
                <div class="text-xs font-semibold text-slate-500 mb-1">Balance sheet</div>
                <div class="text-lg font-semibold text-slate-900 mb-1">Position view</div>
                <p class="text-xs text-slate-500">
                    Snapshot of assets, liabilities and equity – always tied back to the trial balance.
                </p>
            </a>

            <a href="<?= $h($module_base.'/accounting/bank-reco') ?>"
               class="rounded-2xl border border-slate-200 bg-white px-4 py-4 shadow-sm hover:border-emerald-500 hover:shadow-md transition">
                <div class="text-xs font-semibold text-slate-500 mb-1">Bank reconciliation</div>
                <div class="text-lg font-semibold text-slate-900 mb-1">Match GL vs bank</div>
                <p class="text-xs text-slate-500">
                    Compare book balances with bank statements and track outstanding cheques and charges.
                </p>
            </a>
        </section>

        <!-- RIGHT: How to use -->
        <aside class="space-y-4">
            <section class="rounded-2xl border border-dashed border-emerald-400 bg-emerald-50/60 px-4 py-4 text-sm text-slate-800">
                <h2 class="mb-2 flex items-center gap-2 text-sm font-semibold text-emerald-900">
                    <span class="inline-flex h-6 w-6 items-center justify-center rounded-full bg-emerald-600 text-[11px] text-white">
                        ?
                    </span>
                    How to use this page
                </h2>
                <ul class="ml-6 list-disc space-y-1 text-[13px]">
                    <li>Start with <strong>Journals</strong> to post manual entries or review system-posted ones.</li>
                    <li>Use <strong>Trial balance</strong> monthly to confirm all debits equal credits.</li>
                    <li>Check <strong>Balance sheet</strong> to understand your company’s financial position.</li>
                    <li>Use <strong>Bank reconciliation</strong> to align GL bank accounts with actual bank statements.</li>
                    <li>Later, all modules (sales, purchases, payroll, LC, expenses) will post directly into this GL base.</li>
                </ul>
            </section>
        </aside>
    </div>
</div>