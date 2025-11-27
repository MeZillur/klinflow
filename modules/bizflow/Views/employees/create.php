<?php
declare(strict_types=1);

/** @var array       $org */
/** @var string      $module_base */
/** @var array|null  $employee */
/** @var string      $title */
/** @var string      $mode */
/** @var string|null $flash */
/** @var bool        $storage_ready */

$h = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');

$module_base = rtrim($module_base ?? '/apps/bizflow', '/');
$orgName     = trim((string)($org['name'] ?? '')) ?: 'your organisation';

$emp = $employee ?? [];
$get = fn(string $k, string $d = '') => $h((string)($emp[$k] ?? $d));
?>
<div class="space-y-6">

    <!-- Header + tabs -->
    <header class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
        <div>
            <h1 class="text-2xl md:text-3xl font-semibold tracking-tight">
                <?= $h($title ?? 'New employee') ?>
            </h1>
            <p class="text-sm text-slate-500">
                Add a new employee record for <?= $h($orgName) ?> — this will later feed the payroll sheet.
            </p>
        </div>

        <?php
        $tabs = [
            ['Employees',      $module_base.'/employees',        false],
            ['New employee',   $module_base.'/employees/create', true],
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

    <?php if (!empty($flash)): ?>
        <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2 text-xs text-emerald-800">
            <?= $h($flash) ?>
        </div>
    <?php endif; ?>

    <?php if (!$storage_ready): ?>
        <div class="rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-900">
            Employees table <code>biz_employees</code> is not created yet. This form will save only after schema is wired.
        </div>
    <?php endif; ?>

    <!-- Form card -->
    <section class="rounded-2xl border border-slate-200 bg-white shadow-sm">
        <div class="flex items-center justify-between border-b border-slate-100 px-4 py-3">
            <div>
                <h2 class="text-sm font-semibold text-slate-800">
                    Employee details
                </h2>
                <p class="text-xs text-slate-500">
                    Basic HR + salary info — just enough to drive monthly payroll and GL posting.
                </p>
            </div>
        </div>

        <form method="post"
              action="<?= $h($module_base.'/employees'.($mode === 'edit' && !empty($emp['id']) ? '/'.$emp['id'] : '')) ?>"
              class="px-4 py-4 space-y-5">

            <!-- TODO: plug CSRF helper when you wire backend -->
            <!-- <input type="hidden" name="csrf" value="<?= $h($csrf ?? '') ?>"> -->

            <!-- Row: Code + Name + Status -->
            <div class="grid gap-3 md:grid-cols-3">
                <div>
                    <label class="block text-xs font-medium text-slate-700 mb-1">
                        Employee code
                    </label>
                    <input type="text"
                           name="emp_code"
                           value="<?= $get('emp_code') ?>"
                           placeholder="EMP-001"
                           class="w-full rounded-lg border border-slate-200 px-3 py-1.5 text-xs focus:border-emerald-600 focus:outline-none focus:ring-1 focus:ring-emerald-600">
                </div>
                <div class="md:col-span-1">
                    <label class="block text-xs font-medium text-slate-700 mb-1">
                        Full name<span class="text-rose-500">*</span>
                    </label>
                    <input type="text"
                           name="name"
                           value="<?= $get('name') ?>"
                           required
                           class="w-full rounded-lg border border-slate-200 px-3 py-1.5 text-xs focus:border-emerald-600 focus:outline-none focus:ring-1 focus:ring-emerald-600">
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-700 mb-1">
                        Status
                    </label>
                    <?php $status = (string)($emp['status'] ?? 'active'); ?>
                    <select name="status"
                            class="w-full rounded-lg border border-slate-200 px-2 py-1.5 text-xs focus:border-emerald-600 focus:outline-none focus:ring-1 focus:ring-emerald-600">
                        <option value="active"    <?= $status === 'active' ? 'selected' : '' ?>>Active</option>
                        <option value="probation" <?= $status === 'probation' ? 'selected' : '' ?>>Probation</option>
                        <option value="inactive"  <?= $status === 'inactive' ? 'selected' : '' ?>>Inactive / resigned</option>
                    </select>
                </div>
            </div>

            <!-- Row: Department, Designation, Joining -->
            <div class="grid gap-3 md:grid-cols-3">
                <div>
                    <label class="block text-xs font-medium text-slate-700 mb-1">
                        Department
                    </label>
                    <input type="text"
                           name="department"
                           value="<?= $get('department') ?>"
                           placeholder="Accounts, Sales, HR…"
                           class="w-full rounded-lg border border-slate-200 px-3 py-1.5 text-xs focus:border-emerald-600 focus:outline-none focus:ring-1 focus:ring-emerald-600">
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-700 mb-1">
                        Designation
                    </label>
                    <input type="text"
                           name="designation"
                           value="<?= $get('designation') ?>"
                           placeholder="Manager, Executive…"
                           class="w-full rounded-lg border border-slate-200 px-3 py-1.5 text-xs focus:border-emerald-600 focus:outline-none focus:ring-1 focus:ring-emerald-600">
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-700 mb-1">
                        Joining date
                    </label>
                    <input type="date"
                           name="joining_date"
                           value="<?= $get('joining_date') ?>"
                           class="w-full rounded-lg border border-slate-200 px-2 py-1.5 text-xs focus:border-emerald-600 focus:outline-none focus:ring-1 focus:ring-emerald-600">
                </div>
            </div>

            <!-- Row: Contact -->
            <div class="grid gap-3 md:grid-cols-3">
                <div>
                    <label class="block text-xs font-medium text-slate-700 mb-1">
                        Mobile
                    </label>
                    <input type="text"
                           name="mobile"
                           value="<?= $get('mobile') ?>"
                           placeholder="01XXXXXXXXX"
                           class="w-full rounded-lg border border-slate-200 px-3 py-1.5 text-xs focus:border-emerald-600 focus:outline-none focus:ring-1 focus:ring-emerald-600">
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-700 mb-1">
                        Email
                    </label>
                    <input type="email"
                           name="email"
                           value="<?= $get('email') ?>"
                           placeholder="user@example.com"
                           class="w-full rounded-lg border border-slate-200 px-3 py-1.5 text-xs focus:border-emerald-600 focus:outline-none focus:ring-1 focus:ring-emerald-600">
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-700 mb-1">
                        National ID / NID
                    </label>
                    <input type="text"
                           name="national_id"
                           value="<?= $get('national_id') ?>"
                           class="w-full rounded-lg border border-slate-200 px-3 py-1.5 text-xs focus:border-emerald-600 focus:outline-none focus:ring-1 focus:ring-emerald-600">
                </div>
            </div>

            <!-- Row: Salary -->
            <div class="grid gap-3 md:grid-cols-4">
                <div>
                    <label class="block text-xs font-medium text-slate-700 mb-1">
                        Gross salary (BDT)<span class="text-rose-500">*</span>
                    </label>
                    <input type="number"
                           step="0.01"
                           name="gross_salary"
                           value="<?= $get('gross_salary') ?>"
                           required
                           class="w-full rounded-lg border border-slate-200 px-3 py-1.5 text-xs text-right focus:border-emerald-600 focus:outline-none focus:ring-1 focus:ring-emerald-600">
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-700 mb-1">
                        Basic (optional)
                    </label>
                    <input type="number"
                           step="0.01"
                           name="basic_salary"
                           value="<?= $get('basic_salary') ?>"
                           class="w-full rounded-lg border border-slate-200 px-3 py-1.5 text-xs text-right focus:border-emerald-600 focus:outline-none focus:ring-1 focus:ring-emerald-600">
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-700 mb-1">
                        House rent (optional)
                    </label>
                    <input type="number"
                           step="0.01"
                           name="house_rent"
                           value="<?= $get('house_rent') ?>"
                           class="w-full rounded-lg border border-slate-200 px-3 py-1.5 text-xs text-right focus:border-emerald-600 focus:outline-none focus:ring-1 focus:ring-emerald-600">
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-700 mb-1">
                        Other allowances
                    </label>
                    <input type="number"
                           step="0.01"
                           name="other_allowances"
                           value="<?= $get('other_allowances') ?>"
                           class="w-full rounded-lg border border-slate-200 px-3 py-1.5 text-xs text-right focus:border-emerald-600 focus:outline-none focus:ring-1 focus:ring-emerald-600">
                </div>
            </div>

            <!-- Row: Bank -->
            <div class="grid gap-3 md:grid-cols-2">
                <div>
                    <label class="block text-xs font-medium text-slate-700 mb-1">
                        Bank name
                    </label>
                    <input type="text"
                           name="bank_name"
                           value="<?= $get('bank_name') ?>"
                           placeholder="Bank / branch"
                           class="w-full rounded-lg border border-slate-200 px-3 py-1.5 text-xs focus:border-emerald-600 focus:outline-none focus:ring-1 focus:ring-emerald-600">
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-700 mb-1">
                        Bank account no.
                    </label>
                    <input type="text"
                           name="bank_account_no"
                           value="<?= $get('bank_account_no') ?>"
                           class="w-full rounded-lg border border-slate-200 px-3 py-1.5 text-xs focus:border-emerald-600 focus:outline-none focus:ring-1 focus:ring-emerald-600">
                </div>
            </div>

            <!-- Notes -->
            <div>
                <label class="block text-xs font-medium text-slate-700 mb-1">
                    Notes / remarks
                </label>
                <textarea name="notes"
                          rows="3"
                          class="w-full rounded-lg border border-slate-200 px-3 py-1.5 text-xs focus:border-emerald-600 focus:outline-none focus:ring-1 focus:ring-emerald-600"><?= $get('notes') ?></textarea>
            </div>

            <div class="mt-4 flex items-center justify-between gap-2">
                <a href="<?= $h($module_base.'/employees') ?>"
                   class="text-[11px] text-slate-500 hover:text-slate-800 hover:underline">
                    ← Back to employees
                </a>
                <button type="submit"
                        class="inline-flex items-center gap-2 rounded-lg bg-emerald-600 px-4 py-2 text-xs font-semibold text-white shadow-sm hover:bg-emerald-700">
                    <span><?= $mode === 'edit' ? 'Save changes' : 'Save employee' ?></span>
                </button>
            </div>
        </form>
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
            <li>Fill in <strong>name, department, designation and joining date</strong> for each employee.</li>
            <li>Set a unique <strong>employee code</strong> if you already use one in HR or payroll.</li>
            <li>Enter <strong>gross salary in BDT</strong>; basic / house rent / other allowances are optional for now.</li>
            <li>Bank details will be used later to generate <strong>salary payment advice</strong> and GL postings.</li>
            <li>Employee status controls who appears on the <strong>monthly payroll sheet</strong>.</li>
        </ul>
    </section>
</div>