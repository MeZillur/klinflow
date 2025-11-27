<?php
declare(strict_types=1);

/** @var array  $org */
/** @var string $module_base */
/** @var array  $lcs */
/** @var array  $metrics */
/** @var array  $filters */
/** @var string $title */
/** @var bool   $storage_ready */

$h = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');

$module_base = rtrim($module_base ?? '/apps/bizflow', '/');
$orgName     = trim((string)($org['name'] ?? '')) ?: 'your organisation';
$brand       = '#228B22';

// Flash (from controller or store/update)
$flash = null;
if (\PHP_SESSION_ACTIVE !== \session_status()) {
    @\session_start();
}
if (!empty($_SESSION['bizflow_lc_flash'])) {
    $flash = (string)$_SESSION['bizflow_lc_flash'];
    unset($_SESSION['bizflow_lc_flash']);
}
?>
<div class="space-y-6">

    <!-- Header + tabs -->
    <header class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
        <div>
            <h1 class="text-2xl md:text-3xl font-semibold tracking-tight">
                <?= $h($title ?? 'Import LCs') ?>
            </h1>
            <p class="text-sm text-slate-500">
                Import Letters of Credit for <?= $h($orgName) ?> — from opening to retirement.
            </p>
        </div>

        <?php
        $tabs = [
            ['LC register', $module_base.'/lcs',          true],
            ['New LC',      $module_base.'/lcs/create',   false],
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
            LC storage table <code>biz_lcs</code> is not created yet. You are viewing demo records only.
            Schema + posting will come after the front-end is locked.
        </div>
    <?php endif; ?>

    <!-- Metrics row -->
    <section class="grid gap-3 md:grid-cols-4 text-xs">
        <div class="rounded-2xl border border-slate-200 bg-white px-4 py-3 shadow-sm">
            <div class="text-[11px] text-slate-500">Total LCs</div>
            <div class="mt-1 text-xl font-semibold text-slate-900">
                <?= $h((string)($metrics['total'] ?? 0)) ?>
            </div>
        </div>
        <div class="rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 shadow-sm">
            <div class="text-[11px] text-emerald-700">Open / active</div>
            <div class="mt-1 text-xl font-semibold text-emerald-900">
                <?= $h((string)($metrics['open'] ?? 0)) ?>
            </div>
        </div>
        <div class="rounded-2xl border border-slate-200 bg-white px-4 py-3 shadow-sm">
            <div class="text-[11px] text-slate-500">Retired</div>
            <div class="mt-1 text-xl font-semibold text-slate-900">
                <?= $h((string)($metrics['retired'] ?? 0)) ?>
            </div>
        </div>
        <div class="rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 shadow-sm">
            <div class="text-[11px] text-rose-700">Expired / high risk</div>
            <div class="mt-1 text-xl font-semibold text-rose-900">
                <?= $h((string)($metrics['high_risk'] ?? 0)) ?>
            </div>
        </div>
    </section>

    <div class="grid gap-6 lg:grid-cols-[2.2fr,1.1fr]">

        <!-- LEFT: LC table -->
        <section class="rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="flex flex-col gap-3 border-b border-slate-100 px-4 py-3 md:flex-row md:items-center md:justify-between">
                <div>
                    <h2 class="text-sm font-semibold text-slate-800">LC register</h2>
                    <p class="text-xs text-slate-500">
                        Filter by status, bank, date or keyword (LC no, contract, PI, applicant, beneficiary).
                    </p>
                </div>
                <a href="<?= $h($module_base.'/lcs/create') ?>"
                   class="inline-flex items-center gap-1 rounded-lg bg-slate-900 px-3 py-2 text-xs font-medium text-white shadow-sm hover:bg-slate-800">
                    <span>+ New LC</span>
                </a>
            </div>

            <!-- Filters -->
            <form method="get" action="<?= $h($module_base.'/lcs') ?>" class="border-b border-slate-100 px-4 py-3 text-xs">
                <div class="grid gap-2 md:grid-cols-5">
                    <div class="md:col-span-2">
                        <input type="text"
                               name="q"
                               value="<?= $h($filters['q'] ?? '') ?>"
                               placeholder="Search LC no, contract, PI, applicant, beneficiary…"
                               class="w-full rounded-lg border border-slate-200 px-3 py-1.5 text-xs focus:border-emerald-600 focus:outline-none focus:ring-1 focus:ring-emerald-600">
                    </div>
                    <div>
                        <select name="status"
                                class="w-full rounded-lg border border-slate-200 px-2 py-1.5 text-xs focus:border-emerald-600 focus:outline-none focus:ring-1 focus:ring-emerald-600">
                            <option value="">Status</option>
                            <?php
                            $statuses = [
                                'open'              => 'Open',
                                'documents_pending' => 'Docs pending',
                                'matured'           => 'Matured',
                                'retired'           => 'Retired',
                                'cancelled'         => 'Cancelled',
                            ];
                            $selStatus = (string)($filters['status'] ?? '');
                            foreach ($statuses as $key => $label):
                            ?>
                                <option value="<?= $h($key) ?>" <?= $selStatus === $key ? 'selected' : '' ?>>
                                    <?= $h($label) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <input type="text"
                               name="bank"
                               value="<?= $h($filters['bank'] ?? '') ?>"
                               placeholder="Issuing / advising bank"
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
                </div>

                <div class="mt-2 flex justify-end gap-2">
                    <a href="<?= $h($module_base.'/lcs') ?>"
                       class="rounded-lg border border-slate-200 px-3 py-1.5 text-[11px] text-slate-600 hover:bg-slate-50">
                        Reset
                    </a>
                    <button type="submit"
                            class="rounded-lg bg-emerald-600 px-3 py-1.5 text-[11px] font-medium text-white hover:bg-emerald-700">
                        Apply filters
                    </button>
                </div>
            </form>

            <!-- Table -->
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-100 text-xs">
                    <thead class="bg-slate-50">
                        <tr>
                            <th class="px-3 py-2 text-left font-semibold text-slate-600">LC no</th>
                            <th class="px-3 py-2 text-left font-semibold text-slate-600">Applicant / Beneficiary</th>
                            <th class="px-3 py-2 text-left font-semibold text-slate-600">Issuing / Advising bank</th>
                            <th class="px-3 py-2 text-right font-semibold text-slate-600">Amount</th>
                            <th class="px-3 py-2 text-left font-semibold text-slate-600">Opened</th>
                            <th class="px-3 py-2 text-left font-semibold text-slate-600">Expiry</th>
                            <th class="px-3 py-2 text-left font-semibold text-slate-600">Status</th>
                            <th class="px-3 py-2 text-left font-semibold text-slate-600"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                    <?php if (!empty($lcs)): ?>
                        <?php foreach ($lcs as $row): ?>
                            <?php
                            $id     = (int)($row['id'] ?? 0);
                            $status = strtolower((string)($row['status'] ?? ''));
                            $badge  = 'bg-slate-100 text-slate-700';
                            if (in_array($status, ['open','active','documents_pending'], true)) {
                                $badge = 'bg-emerald-50 text-emerald-700 border border-emerald-100';
                            } elseif ($status === 'retired') {
                                $badge = 'bg-sky-50 text-sky-700 border border-sky-100';
                            } elseif (in_array($status, ['cancelled','expired'], true)) {
                                $badge = 'bg-rose-50 text-rose-700 border border-rose-100';
                            }
                            ?>
                            <tr class="hover:bg-slate-50/60">
                                <td class="px-3 py-2 align-top">
                                    <div class="font-semibold text-slate-800">
                                        <a href="<?= $h($module_base.'/lcs/'.$id) ?>" class="hover:underline">
                                            <?= $h($row['lc_no'] ?? '') ?>
                                        </a>
                                    </div>
                                    <div class="text-[11px] text-slate-500">
                                        Contract: <?= $h($row['contract_no'] ?? '—') ?> · PI: <?= $h($row['pi_no'] ?? '—') ?>
                                    </div>
                                </td>
                                <td class="px-3 py-2 align-top">
                                    <div class="text-xs font-medium text-slate-800">
                                        <?= $h($row['applicant_name'] ?? '—') ?>
                                    </div>
                                    <div class="text-[11px] text-slate-500">
                                        → <?= $h($row['beneficiary_name'] ?? '—') ?>
                                    </div>
                                </td>
                                <td class="px-3 py-2 align-top">
                                    <div class="text-[11px] text-slate-700">
                                        <?= $h($row['issuing_bank'] ?? '—') ?>
                                    </div>
                                    <div class="text-[11px] text-slate-500">
                                        <?= $h($row['advising_bank'] ?? '') ?>
                                    </div>
                                </td>
                                <td class="px-3 py-2 align-top text-right">
                                    <div class="text-xs font-semibold text-slate-900">
                                        <?= $h((string)number_format((float)($row['lc_amount'] ?? 0), 2)) ?>
                                    </div>
                                    <div class="text-[11px] text-slate-500">
                                        <?= $h($row['currency'] ?? 'USD') ?>
                                    </div>
                                </td>
                                <td class="px-3 py-2 align-top text-[11px] text-slate-700">
                                    <?= $h($row['opened_at'] ?? '—') ?>
                                </td>
                                <td class="px-3 py-2 align-top text-[11px] text-slate-700">
                                    <?= $h($row['expiry_date'] ?? '—') ?>
                                </td>
                                <td class="px-3 py-2 align-top">
                                    <span class="inline-flex items-center rounded-full px-2 py-0.5 text-[11px] <?= $badge ?>">
                                        <?= $h(ucfirst($status ?: 'n/a')) ?>
                                    </span>
                                </td>
                                <td class="px-3 py-2 align-top text-right">
                                    <a href="<?= $h($module_base.'/lcs/'.$id.'/edit') ?>"
                                       class="text-[11px] text-slate-500 hover:text-slate-800 hover:underline">
                                        Edit
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="8" class="px-3 py-6 text-center text-xs text-slate-500">
                                No LCs found yet. Use <strong>New LC</strong> to design your opening workflow.
                            </td>
                        </tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <!-- RIGHT: How to / notes -->
        <aside class="space-y-4">
            <section class="rounded-2xl border border-dashed border-emerald-400 bg-emerald-50/60 px-4 py-4 text-sm text-slate-800">
                <h2 class="mb-2 flex items-center gap-2 text-sm font-semibold text-emerald-900">
                    <span class="inline-flex h-6 w-6 items-center justify-center rounded-full bg-emerald-600 text-[11px] text-white">
                        ?
                    </span>
                    How to use this page
                </h2>
                <ul class="ml-6 list-disc space-y-1 text-[13px]">
                    <li>Use <strong>New LC</strong> when a contract, PI/indent and LCA are in place.</li>
                    <li>Track key dates: opening, last shipment, expiry, document arrival, maturity and retirement.</li>
                    <li>Filter by <strong>status</strong> and <strong>bank</strong> to see which LCs are at risk.</li>
                    <li>Later, this register will drive <strong>LC margin utilisation</strong> and <strong>FX exposure</strong> reports.</li>
                    <li>All records will eventually live in <code>biz_lcs</code>, but the UI is designed now so schema stays stable until 2035.</li>
                </ul>
            </section>
        </aside>

    </div>
</div>