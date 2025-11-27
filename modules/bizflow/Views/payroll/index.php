<?php
declare(strict_types=1);

/** @var array       $org */
/** @var string      $module_base */
/** @var array       $sheet */
/** @var array       $metrics */
/** @var string      $period */
/** @var array       $filters */
/** @var bool        $storage_ready */
/** @var string|null $flash */
/** @var string      $title */

$h = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');

$module_base = rtrim($module_base ?? '/apps/bizflow', '/');
$orgName     = trim((string)($org['name'] ?? '')) ?: 'your organisation';

// Period label (YYYY-MM → "Month YYYY")
$periodLabel = 'Current period';
if ($period && preg_match('/^(\d{4})-(\d{2})$/', $period, $m)) {
    $y = (int)$m[1];
    $mIdx = (int)$m[2];
    $months = [
        1=>'January',2=>'February',3=>'March',4=>'April',5=>'May',6=>'June',
        7=>'July',8=>'August',9=>'September',10=>'October',11=>'November',12=>'December'
    ];
    $periodLabel = ($months[$mIdx] ?? $period) . ' ' . $y;
}
?>
<div class="space-y-6">

    <!-- Header + tabs -->
    <header class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
        <div>
            <h1 class="text-2xl md:text-3xl font-semibold tracking-tight">
                <?= $h($title ?? 'Payroll sheet') ?>
            </h1>
            <p class="text-sm text-slate-500">
                Monthly salary sheet for <?= $h($orgName) ?> — posts to GL &amp; cost centres.
            </p>
        </div>

        <?php
        $tabs = [
            ['Employees',     $module_base.'/employees',       false],
            ['Payroll sheet', $module_base.'/payroll',         true],
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
            Payroll tables <code>biz_payroll_runs</code> / <code>biz_payroll_lines</code> are not created yet.
            You are viewing demo rows only. Posting to GL will come after schema is final.
        </div>
    <?php endif; ?>

    <!-- Metrics row -->
    <section class="grid gap-3 md:grid-cols-4 text-xs">
        <div class="rounded-2xl border border-slate-200 bg-white px-4 py-3 shadow-sm">
            <div class="text-[11px] text-slate-500">Period</div>
            <div class="mt-1 text-sm font-semibold text-slate-900">
                <?= $h($periodLabel) ?>
            </div>
        </div>
        <div class="rounded-2xl border border-slate-200 bg-white px-4 py-3 shadow-sm">
            <div class="text-[11px] text-slate-500">Employees in sheet</div>
            <div class="mt-1 text-xl font-semibold text-slate-900">
                <?= $h((string)($metrics['employees'] ?? 0)) ?>
            </div>
        </div>
        <div class="rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 shadow-sm">
            <div class="text-[11px] text-emerald-700">Total gross (BDT)</div>
            <div class="mt-1 text-xl font-semibold text-emerald-900">
                <?= $h(number_format((float)($metrics['total_gross'] ?? 0), 2)) ?>
            </div>
        </div>
        <div class="rounded-2xl border border-sky-200 bg-sky-50 px-4 py-3 shadow-sm">
            <div class="text-[11px] text-sky-700">Total net pay (BDT)</div>
            <div class="mt-1 text-xl font-semibold text-sky-900">
                <?= $h(number_format((float)($metrics['total_net'] ?? 0), 2)) ?>
            </div>
        </div>
    </section>

    <div class="grid gap-6 lg:grid-cols-[2.2fr,1.1fr]">

        <!-- LEFT: sheet + filters -->
        <section class="rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="flex flex-col gap-3 border-b border-slate-100 px-4 py-3 md:flex-row md:items-center md:justify-between">
                <div>
                    <h2 class="text-sm font-semibold text-slate-800">Monthly payroll sheet</h2>
                    <p class="text-xs text-slate-500">
                        Choose period, filter by department or status, then lock for posting.
                    </p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <!-- Recalc (preview only for now) -->
                    <form method="post"
                          action="<?= $h($module_base.'/payroll/recalc') ?>">
                        <input type="hidden" name="period" value="<?= $h($period) ?>">
                        <button type="submit"
                                class="inline-flex items-center gap-1 rounded-lg border border-slate-200 px-3 py-2 text-xs font-medium text-slate-700 hover:bg-slate-50">
                            Recalculate sheet
                        </button>
                    </form>

                    <!-- Lock (preview only) -->
                    <form method="post"
                          action="<?= $h($module_base.'/payroll/lock') ?>">
                        <input type="hidden" name="period" value="<?= $h($period) ?>">
                        <button type="submit"
                                class="inline-flex items-center gap-1 rounded-lg bg-slate-900 px-3 py-2 text-xs font-medium text-white shadow-sm hover:bg-slate-800">
                            Lock &amp; post (preview)
                        </button>
                    </form>
                </div>
            </div>

            <!-- Filters -->
            <form method="get"
                  action="<?= $h($module_base.'/payroll') ?>"
                  class="border-b border-slate-100 px-4 py-3 text-xs">
                <div class="grid gap-2 md:grid-cols-4">
                    <div>
                        <label class="mb-1 block text-[11px] font-medium text-slate-600">
                            Period
                        </label>
                        <input type="month"
                               name="period"
                               value="<?= $h($filters['period'] ?? $period) ?>"
                               class="w-full rounded-lg border border-slate-200 px-2 py-1.5 text-xs focus:border-emerald-600 focus:outline-none focus:ring-1 focus:ring-emerald-600">
                    </div>
                    <div>
                        <label class="mb-1 block text-[11px] font-medium text-slate-600">
                            Department
                        </label>
                        <input type="text"
                               name="department"
                               value="<?= $h($filters['department'] ?? '') ?>"
                               placeholder="Department"
                               class="w-full rounded-lg border border-slate-200 px-3 py-1.5 text-xs focus:border-emerald-600 focus:outline-none focus:ring-1 focus:ring-emerald-600">
                    </div>
                    <div>
                        <label class="mb-1 block text-[11px] font-medium text-slate-600">
                            Status
                        </label>
                        <select name="status"
                                class="w-full rounded-lg border border-slate-200 px-2 py-1.5 text-xs focus:border-emerald-600 focus:outline-none focus:ring-1 focus:ring-emerald-600">
                            <option value="">Any</option>
                            <?php
                            $optStatus = [
                                'draft'     => 'Draft',
                                'locked'    => 'Locked',
                                'posted'    => 'Posted',
                            ];
                            $selStatus = (string)($filters['status'] ?? '');
                            foreach ($optStatus as $key => $label):
                            ?>
                                <option value="<?= $h($key) ?>" <?= $selStatus === $key ? 'selected' : '' ?>>
                                    <?= $h($label) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="mb-1 block text-[11px] font-medium text-slate-600">
                            Employee filter
                        </label>
                        <input type="text"
                               name="q"
                               value="<?= $h($filters['q'] ?? '') ?>"
                               placeholder="Name, code, mobile, email…"
                               class="w-full rounded-lg border border-slate-200 px-3 py-1.5 text-xs focus:border-emerald-600 focus:outline-none focus:ring-1 focus:ring-emerald-600">
                    </div>
                </div>

                <div class="mt-2 flex justify-end gap-2">
                    <a href="<?= $h($module_base.'/payroll') ?>"
                       class="rounded-lg border border-slate-200 px-3 py-1.5 text-[11px] text-slate-600 hover:bg-slate-50">
                        Reset
                    </a>
                    <button type="submit"
                            class="rounded-lg bg-emerald-600 px-3 py-1.5 text-[11px] font-medium text-white hover:bg-emerald-700">
                        Apply filters
                    </button>
                </div>
            </form>

            <!-- Sheet table -->
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-100 text-xs">
                    <thead class="bg-slate-50">
                        <tr>
                            <th class="px-3 py-2 text-left font-semibold text-slate-600">Employee</th>
                            <th class="px-3 py-2 text-left font-semibold text-slate-600">Dept / Designation</th>
                            <th class="px-3 py-2 text-right font-semibold text-slate-600">Gross (BDT)</th>
                            <th class="px-3 py-2 text-right font-semibold text-slate-600">Deductions (BDT)</th>
                            <th class="px-3 py-2 text-right font-semibold text-slate-600">Net pay (BDT)</th>
                            <th class="px-3 py-2 text-left font-semibold text-slate-600">Status</th>
                            <th class="px-3 py-2 text-left font-semibold text-slate-600"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                    <?php if (!empty($sheet)): ?>
                        <?php foreach ($sheet as $row): ?>
                            <?php
                            $status = strtolower((string)($row['status'] ?? 'draft'));
                            $badge  = 'bg-slate-100 text-slate-700';
                            if ($status === 'draft') {
                                $badge = 'bg-amber-50 text-amber-700 border border-amber-100';
                            } elseif ($status === 'locked') {
                                $badge = 'bg-sky-50 text-sky-700 border border-sky-100';
                            } elseif ($status === 'posted') {
                                $badge = 'bg-emerald-50 text-emerald-700 border border-emerald-100';
                            }
                            ?>
                            <tr class="hover:bg-slate-50/60">
                                <td class="px-3 py-2 align-top">
                                    <div class="font-medium text-slate-800">
                                        <?= $h($row['emp_name'] ?? '—') ?>
                                    </div>
                                    <div class="text-[11px] text-slate-500">
                                        <?= $h($row['emp_code'] ?? '') ?>
                                    </div>
                                </td>
                                <td class="px-3 py-2 align-top">
                                    <div class="text-xs text-slate-800">
                                        <?= $h($row['department'] ?? '—') ?>
                                    </div>
                                    <div class="text-[11px] text-slate-500">
                                        <?= $h($row['designation'] ?? '') ?>
                                    </div>
                                </td>
                                <td class="px-3 py-2 align-top text-right">
                                    <div class="text-xs font-semibold text-slate-900">
                                        <?= $h(number_format((float)($row['gross'] ?? 0), 2)) ?>
                                    </div>
                                </td>
                                <td class="px-3 py-2 align-top text-right">
                                    <div class="text-xs text-slate-800">
                                        <?= $h(number_format((float)($row['deductions'] ?? 0), 2)) ?>
                                    </div>
                                </td>
                                <td class="px-3 py-2 align-top text-right">
                                    <div class="text-xs font-semibold text-slate-900">
                                        <?= $h(number_format((float)($row['net_pay'] ?? 0), 2)) ?>
                                    </div>
                                </td>
                                <td class="px-3 py-2 align-top">
                                    <span class="inline-flex items-center rounded-full px-2 py-0.5 text-[11px] <?= $badge ?>">
                                        <?= $h(ucfirst($status)) ?>
                                    </span>
                                </td>
                                <td class="px-3 py-2 align-top text-right">
                                    <!-- Future: link to employee or detail sheet -->
                                    <span class="text-[11px] text-slate-400">
                                        Sheet only
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" class="px-3 py-6 text-center text-xs text-slate-500">
                                No payroll rows found for this period. Once employees are added, you can recalculate
                                the sheet and see gross / net pay here.
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
                    <li>First, maintain your <strong>Employees</strong> master (name, department, gross salary, status).</li>
                    <li>Select the <strong>period (YYYY-MM)</strong> you want to run payroll for, then apply filters if needed.</li>
                    <li>Click <strong>Recalculate sheet</strong> to refresh the preview from employees and salary structure.</li>
                    <li>When you are satisfied, use <strong>Lock &amp; post (preview)</strong> — in Phase-2 this will hit GL and cost centres.</li>
                    <li>All amounts are in <strong>BDT</strong>; later we can add cost centre splits and statutory deductions details.</li>
                </ul>
            </section>
        </aside>
    </div>
</div>