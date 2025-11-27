<?php
declare(strict_types=1);

/** @var array        $org */
/** @var string       $module_base */
/** @var array        $employees */
/** @var array        $metrics */
/** @var array        $filters */
/** @var bool         $storage_ready */
/** @var string|null  $flash */
/** @var string       $title */

$h = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');

$module_base = rtrim($module_base ?? '/apps/bizflow', '/');
$orgName     = trim((string)($org['name'] ?? '')) ?: 'your organisation';
?>
<div class="space-y-6">

    <!-- Header + tabs -->
    <header class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
        <div>
            <h1 class="text-2xl md:text-3xl font-semibold tracking-tight">
                <?= $h($title ?? 'Employees') ?>
            </h1>
            <p class="text-sm text-slate-500">
                Employees master data for <?= $h($orgName) ?> — basis for payroll and cost centres.
            </p>
        </div>

        <?php
        $tabs = [
            ['Employees',      $module_base.'/employees',        true],
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

    <?php if ($flash): ?>
        <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2 text-xs text-emerald-800">
            <?= $h($flash) ?>
        </div>
    <?php endif; ?>

    <?php if (!$storage_ready): ?>
        <div class="rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-900">
            Employees table <code>biz_employees</code> is not created yet. You are viewing demo records only.
            Schema + posting will come after UI is locked.
        </div>
    <?php endif; ?>

    <!-- Metrics row -->
    <section class="grid gap-3 md:grid-cols-4 text-xs">
        <div class="rounded-2xl border border-slate-200 bg-white px-4 py-3 shadow-sm">
            <div class="text-[11px] text-slate-500">Total employees</div>
            <div class="mt-1 text-xl font-semibold text-slate-900">
                <?= $h((string)($metrics['total'] ?? 0)) ?>
            </div>
        </div>
        <div class="rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 shadow-sm">
            <div class="text-[11px] text-emerald-700">Active / probation</div>
            <div class="mt-1 text-xl font-semibold text-emerald-900">
                <?= $h((string)($metrics['active'] ?? 0)) ?>
            </div>
        </div>
        <div class="rounded-2xl border border-slate-200 bg-white px-4 py-3 shadow-sm">
            <div class="text-[11px] text-slate-500">Inactive / resigned</div>
            <div class="mt-1 text-xl font-semibold text-slate-900">
                <?= $h((string)($metrics['inactive'] ?? 0)) ?>
            </div>
        </div>
        <div class="rounded-2xl border border-sky-200 bg-sky-50 px-4 py-3 shadow-sm">
            <div class="text-[11px] text-sky-700">Today</div>
            <div class="mt-1 text-xl font-semibold text-sky-900">
                <?= $h($metrics['today'] ?? '') ?>
            </div>
        </div>
    </section>

    <div class="grid gap-6 lg:grid-cols-[2.2fr,1.1fr]">

        <!-- LEFT: table + filters -->
        <section class="rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="flex flex-col gap-3 border-b border-slate-100 px-4 py-3 md:flex-row md:items-center md:justify-between">
                <div>
                    <h2 class="text-sm font-semibold text-slate-800">Employees master</h2>
                    <p class="text-xs text-slate-500">
                        Filter by name, code, department or status.
                    </p>
                </div>
                <a href="<?= $h($module_base.'/employees/create') ?>"
                   class="inline-flex items-center gap-1 rounded-lg bg-slate-900 px-3 py-2 text-xs font-medium text-white shadow-sm hover:bg-slate-800">
                    <span>+ New employee</span>
                </a>
            </div>

            <!-- Filters -->
            <form method="get" action="<?= $h($module_base.'/employees') ?>" class="border-b border-slate-100 px-4 py-3 text-xs">
                <div class="grid gap-2 md:grid-cols-4">
                    <div class="md:col-span-2">
                        <input type="text"
                               name="q"
                               value="<?= $h($filters['q'] ?? '') ?>"
                               placeholder="Search name, code, mobile, email…"
                               class="w-full rounded-lg border border-slate-200 px-3 py-1.5 text-xs focus:border-emerald-600 focus:outline-none focus:ring-1 focus:ring-emerald-600">
                    </div>
                    <div>
                        <select name="status"
                                class="w-full rounded-lg border border-slate-200 px-2 py-1.5 text-xs focus:border-emerald-600 focus:outline-none focus:ring-1 focus:ring-emerald-600">
                            <option value="">Status</option>
                            <?php
                            $optStatus = [
                                'active'    => 'Active',
                                'probation' => 'Probation',
                                'inactive'  => 'Inactive / resigned',
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
                        <input type="text"
                               name="department"
                               value="<?= $h($filters['department'] ?? '') ?>"
                               placeholder="Department"
                               class="w-full rounded-lg border border-slate-200 px-3 py-1.5 text-xs focus:border-emerald-600 focus:outline-none focus:ring-1 focus:ring-emerald-600">
                    </div>
                </div>

                <div class="mt-2 flex justify-end gap-2">
                    <a href="<?= $h($module_base.'/employees') ?>"
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
                            <th class="px-3 py-2 text-left font-semibold text-slate-600">Employee</th>
                            <th class="px-3 py-2 text-left font-semibold text-slate-600">Department / Designation</th>
                            <th class="px-3 py-2 text-left font-semibold text-slate-600">Joining</th>
                            <th class="px-3 py-2 text-right font-semibold text-slate-600">Gross salary (BDT)</th>
                            <th class="px-3 py-2 text-left font-semibold text-slate-600">Status</th>
                            <th class="px-3 py-2 text-left font-semibold text-slate-600"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                    <?php if (!empty($employees)): ?>
                        <?php foreach ($employees as $row): ?>
                            <?php
                            $id       = (int)($row['id'] ?? 0);
                            $status   = strtolower((string)($row['status'] ?? ''));
                            $badgeCls = 'bg-slate-100 text-slate-700';

                            if (in_array($status, ['active','probation'], true)) {
                                $badgeCls = 'bg-emerald-50 text-emerald-700 border border-emerald-100';
                            } elseif (in_array($status, ['inactive','resigned'], true)) {
                                $badgeCls = 'bg-rose-50 text-rose-700 border border-rose-100';
                            }

                            $gross = (float)($row['gross_salary'] ?? 0);
                            ?>
                            <tr class="hover:bg-slate-50/60">
                                <td class="px-3 py-2 align-top">
                                    <div class="font-semibold text-slate-800">
                                        <a href="<?= $h($module_base.'/employees/'.$id) ?>" class="hover:underline">
                                            <?= $h($row['name'] ?? '—') ?>
                                        </a>
                                    </div>
                                    <div class="text-[11px] text-slate-500">
                                        Code: <?= $h($row['emp_code'] ?? '—') ?>
                                    </div>
                                    <div class="text-[11px] text-slate-500">
                                        <?= $h($row['mobile'] ?? '') ?>
                                        <?php if (!empty($row['mobile']) && !empty($row['email'])): ?>
                                            ·
                                        <?php endif; ?>
                                        <?= $h($row['email'] ?? '') ?>
                                    </div>
                                </td>
                                <td class="px-3 py-2 align-top">
                                    <div class="text-xs font-medium text-slate-800">
                                        <?= $h($row['department'] ?? '—') ?>
                                    </div>
                                    <div class="text-[11px] text-slate-500">
                                        <?= $h($row['designation'] ?? '') ?>
                                    </div>
                                </td>
                                <td class="px-3 py-2 align-top text-[11px] text-slate-700">
                                    <?= $h($row['joining_date'] ?? '—') ?>
                                </td>
                                <td class="px-3 py-2 align-top text-right">
                                    <div class="text-xs font-semibold text-slate-900">
                                        <?= $gross > 0 ? $h(number_format($gross, 2)) : '—' ?>
                                    </div>
                                    <div class="text-[11px] text-slate-500">
                                        Monthly
                                    </div>
                                </td>
                                <td class="px-3 py-2 align-top">
                                    <span class="inline-flex items-center rounded-full px-2 py-0.5 text-[11px] <?= $badgeCls ?>">
                                        <?= $h(ucfirst($status ?: 'n/a')) ?>
                                    </span>
                                </td>
                                <td class="px-3 py-2 align-top text-right">
                                    <a href="<?= $h($module_base.'/employees/'.$id.'/edit') ?>"
                                       class="text-[11px] text-slate-500 hover:text-slate-800 hover:underline">
                                        Edit
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" class="px-3 py-6 text-center text-xs text-slate-500">
                                No employees found yet. Use <strong>New employee</strong> to add your first record.
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
                    <li>Use <strong>New employee</strong> to add staff with basic HR fields and gross salary in BDT.</li>
                    <li>Keep <strong>status</strong> updated (active, probation, inactive) so payroll and cost centres stay clean.</li>
                    <li>Filter by <strong>department</strong> to see headcount and salary exposure by function.</li>
                    <li>This master will later feed the <strong>Payroll sheet</strong> page to post salary journals to the GL.</li>
                    <li>For now, all records are kept inside <code>biz_employees</code> (once created) under this organisation.</li>
                </ul>
            </section>
        </aside>
    </div>
</div>