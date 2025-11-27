<?php
declare(strict_types=1);

/** @var array  $org */
/** @var string $module_base */
/** @var array  $employee */
/** @var string $title */

$h = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');

$module_base = rtrim($module_base ?? '/apps/bizflow', '/');
$orgName     = trim((string)($org['name'] ?? '')) ?: 'your organisation';

$emp   = $employee ?? [];
$id    = (int)($emp['id'] ?? 0);
$name  = trim((string)($emp['name'] ?? ''));
$code  = trim((string)($emp['emp_code'] ?? ''));
$dept  = trim((string)($emp['department'] ?? ''));
$desig = trim((string)($emp['designation'] ?? ''));
$status = strtolower((string)($emp['status'] ?? 'active'));

$gross = (float)($emp['gross_salary'] ?? 0.0);

$badgeCls = 'bg-slate-100 text-slate-700';
if (in_array($status, ['active','probation'], true)) {
    $badgeCls = 'bg-emerald-50 text-emerald-700 border border-emerald-100';
} elseif (in_array($status, ['inactive','resigned'], true)) {
    $badgeCls = 'bg-rose-50 text-rose-700 border border-rose-100';
}
?>
<div class="space-y-6">

    <!-- Header + tabs -->
    <header class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
        <div>
            <h1 class="text-2xl md:text-3xl font-semibold tracking-tight">
                <?= $h($title ?? 'Employee details') ?>
            </h1>
            <p class="text-sm text-slate-500">
                Employee record for <?= $h($name ?: '—') ?> — part of <?= $h($orgName) ?> payroll and cost centres.
            </p>
        </div>

        <?php
        $tabs = [
            ['Employees',      $module_base.'/employees',        false],
            ['New employee',   $module_base.'/employees/create', false],
            ['Payroll sheet',  $module_base.'/payroll',          false],
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

    <!-- Top summary card -->
    <section class="rounded-2xl border border-slate-200 bg-white px-4 py-4 shadow-sm">
        <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
            <div>
                <div class="flex items-center gap-2">
                    <h2 class="text-lg font-semibold text-slate-900">
                        <?= $h($name ?: 'Unnamed employee') ?>
                    </h2>
                    <?php if ($code !== ''): ?>
                        <span class="rounded-full bg-slate-100 px-2 py-0.5 text-[11px] text-slate-600">
                            <?= $h($code) ?>
                        </span>
                    <?php endif; ?>
                </div>
                <div class="mt-1 text-xs text-slate-500">
                    <?= $h($dept ?: 'No department') ?>
                    <?php if ($dept && $desig): ?> · <?php endif; ?>
                    <?= $h($desig) ?>
                </div>
            </div>

            <div class="flex flex-col items-start gap-1 text-xs md:items-end">
                <span class="inline-flex items-center rounded-full px-2 py-0.5 <?= $badgeCls ?>">
                    <?= $h(ucfirst($status ?: 'n/a')) ?>
                </span>
                <div class="text-[11px] text-slate-500">
                    Joining: <?= $h($emp['joining_date'] ?? '—') ?>
                </div>
                <div class="text-[11px] text-slate-500">
                    Gross salary: <strong><?= $gross > 0 ? $h(number_format($gross, 2)).' BDT' : '—' ?></strong>
                </div>
            </div>
        </div>
    </section>

    <div class="grid gap-6 lg:grid-cols-[1.3fr,1.1fr]">

        <!-- LEFT: Details -->
        <section class="space-y-4">

            <!-- Contact info -->
            <div class="rounded-2xl border border-slate-200 bg-white px-4 py-4 shadow-sm">
                <h3 class="mb-2 text-sm font-semibold text-slate-800">Contact</h3>
                <dl class="grid grid-cols-1 gap-2 text-xs md:grid-cols-2">
                    <div>
                        <dt class="text-slate-500">Mobile</dt>
                        <dd class="font-medium text-slate-900">
                            <?= $h($emp['mobile'] ?? '—') ?>
                        </dd>
                    </div>
                    <div>
                        <dt class="text-slate-500">Email</dt>
                        <dd class="font-medium text-slate-900">
                            <?= $h($emp['email'] ?? '—') ?>
                        </dd>
                    </div>
                    <div>
                        <dt class="text-slate-500">National ID (NID)</dt>
                        <dd class="font-medium text-slate-900">
                            <?= $h($emp['national_id'] ?? '—') ?>
                        </dd>
                    </div>
                </dl>
            </div>

            <!-- Salary breakdown -->
            <div class="rounded-2xl border border-slate-200 bg-white px-4 py-4 shadow-sm">
                <h3 class="mb-2 text-sm font-semibold text-slate-800">Salary structure (monthly, BDT)</h3>
                <dl class="grid grid-cols-1 gap-2 text-xs md:grid-cols-2">
                    <div>
                        <dt class="text-slate-500">Gross salary</dt>
                        <dd class="font-semibold text-slate-900">
                            <?= $gross > 0 ? $h(number_format($gross, 2)) : '—' ?>
                        </dd>
                    </div>
                    <div>
                        <dt class="text-slate-500">Basic</dt>
                        <dd class="font-medium text-slate-900">
                            <?= $emp['basic_salary'] !== null && $emp['basic_salary'] !== '' ? $h(number_format((float)$emp['basic_salary'], 2)) : '—' ?>
                        </dd>
                    </div>
                    <div>
                        <dt class="text-slate-500">House rent</dt>
                        <dd class="font-medium text-slate-900">
                            <?= $emp['house_rent'] !== null && $emp['house_rent'] !== '' ? $h(number_format((float)$emp['house_rent'], 2)) : '—' ?>
                        </dd>
                    </div>
                    <div>
                        <dt class="text-slate-500">Other allowances</dt>
                        <dd class="font-medium text-slate-900">
                            <?= $emp['other_allowances'] !== null && $emp['other_allowances'] !== '' ? $h(number_format((float)$emp['other_allowances'], 2)) : '—' ?>
                        </dd>
                    </div>
                </dl>
            </div>

            <!-- Notes -->
            <div class="rounded-2xl border border-slate-200 bg-white px-4 py-4 shadow-sm">
                <h3 class="mb-2 text-sm font-semibold text-slate-800">Notes</h3>
                <p class="text-xs text-slate-700 whitespace-pre-line">
                    <?= $h(trim((string)($emp['notes'] ?? 'No notes added for this employee yet.'))) ?>
                </p>
            </div>
        </section>

        <!-- RIGHT: Bank + actions + how-to -->
        <aside class="space-y-4">

            <!-- Bank details -->
            <section class="rounded-2xl border border-slate-200 bg-white px-4 py-4 shadow-sm">
                <h3 class="mb-2 text-sm font-semibold text-slate-800">Bank details</h3>
                <dl class="space-y-2 text-xs">
                    <div>
                        <dt class="text-slate-500">Bank name</dt>
                        <dd class="font-medium text-slate-900">
                            <?= $h($emp['bank_name'] ?? '—') ?>
                        </dd>
                    </div>
                    <div>
                        <dt class="text-slate-500">Bank account no.</dt>
                        <dd class="font-medium text-slate-900">
                            <?= $h($emp['bank_account_no'] ?? '—') ?>
                        </dd>
                    </div>
                </dl>
            </section>

            <!-- Actions -->
            <section class="rounded-2xl border border-slate-200 bg-white px-4 py-4 shadow-sm">
                <div class="flex flex-col gap-2 text-xs">
                    <a href="<?= $h($module_base.'/employees/'.$id.'/edit') ?>"
                       class="inline-flex items-center justify-center gap-2 rounded-lg bg-emerald-600 px-3 py-2 font-semibold text-white hover:bg-emerald-700">
                        Edit employee
                    </a>
                    <a href="<?= $h($module_base.'/employees') ?>"
                       class="inline-flex items-center justify-center rounded-lg border border-slate-200 px-3 py-2 text-slate-700 hover:bg-slate-50">
                        Back to employees list
                    </a>
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
                    <li>Review <strong>contact</strong> and <strong>salary</strong> details before running monthly payroll.</li>
                    <li>Keep bank info up to date to avoid issues when generating salary <strong>payment advice</strong>.</li>
                    <li>Use the <strong>Edit employee</strong> button to correct status, salary or department as needed.</li>
                    <li>This record will feed into the <strong>Payroll sheet</strong> page and GL salary postings in BizFlow.</li>
                </ul>
            </section>
        </aside>
    </div>
</div>