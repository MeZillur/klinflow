<?php
/** @var array  $accounts */
/** @var array  $metrics */
/** @var array  $org */
/** @var string $module_base */
/** @var string $title */

$h = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');

$module_base = rtrim($module_base ?? ($base ?? '/apps/bizflow'), '/');
$orgName     = $org['name'] ?? '';

$accounts = $accounts ?? [];
$m        = $metrics  ?? [];

$totalAccounts    = (int)($m['total_accounts']      ?? 0);
$activeAccounts   = (int)($m['active_accounts']     ?? 0);
$totalBalance     = (float)($m['total_balance']     ?? 0.0);
$unreconciledTxns = $m['unreconciled_txns']         ?? null;

$dataReadyAccounts = !empty($m['data_ready_accounts']);
$dataReadyTxns     = !empty($m['data_ready_txns']);
?>
<div class="space-y-6">

    <!-- Header -->
    <header class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
        <div class="space-y-1">
            <h1 class="text-2xl md:text-3xl font-semibold tracking-tight">
                <?= $h($title ?? 'Banking & Cash') ?>
            </h1>
            <p class="text-sm text-slate-500">
                Central view of bank accounts, balances and reconciliation status for
                <?= $h($orgName ?: 'your organisation') ?>.
            </p>
            <?php if (!$dataReadyAccounts && !$dataReadyTxns): ?>
                <p class="mt-1 text-xs text-amber-700">
                    Banking tables are not created yet. Once you add bank accounts and statements,
                    this dashboard will show live numbers without any schema errors.
                </p>
            <?php endif; ?>
        </div>

        <!-- Right-aligned BizFlow nav tabs (includes Banking) -->
        <nav class="flex flex-wrap justify-end gap-1 text-sm">
            <?php
            $tabs = [
               
               
                ['Expenses',    $module_base.'/expenses'],
                ['Payments',   $module_base.'/payments'],
                ['Reports',    $module_base.'/reports'],
                ['Tax',    $module_base.'/tax'],
                ['Settings',   $module_base.'/settings'],
            ];
            $current = $module_base.'/banking';
            foreach ($tabs as [$label, $url]):
                $active = $url === $current;
            ?>
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

    <!-- Top metric cards -->
    <section class="grid gap-4 md:grid-cols-3 xl:grid-cols-4">
        <!-- Total accounts -->
        <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm space-y-2">
            <div class="flex items-center justify-between">
                <div class="text-xs font-medium uppercase tracking-wide text-slate-500">
                    Bank accounts
                </div>
                <i class="fa fa-piggy-bank text-slate-400 text-xs"></i>
            </div>
            <div class="mt-2 text-2xl font-semibold text-slate-900">
                <?= $h($totalAccounts) ?>
            </div>
            <p class="mt-1 text-[11px] text-slate-500">
                Total bank and cash accounts configured under BizFlow.
            </p>
        </div>

        <!-- Active accounts -->
        <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm space-y-2">
            <div class="flex items-center justify-between">
                <div class="text-xs font-medium uppercase tracking-wide text-slate-500">
                    Active accounts
                </div>
                <span class="inline-flex items-center gap-1 rounded-full bg-emerald-50 px-2 py-[2px] text-[10px] font-medium text-emerald-700">
                    <span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>
                    Live
                </span>
            </div>
            <div class="mt-2 text-2xl font-semibold text-slate-900">
                <?= $h($activeAccounts) ?>
            </div>
            <p class="mt-1 text-[11px] text-slate-500">
                Accounts open for posting and reconciliation.
            </p>
        </div>

        <!-- Total balance -->
        <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm space-y-2">
            <div class="flex items-center justify-between">
                <div class="text-xs font-medium uppercase tracking-wide text-slate-500">
                    Total balance
                </div>
                <i class="fa fa-wallet text-slate-400 text-xs"></i>
            </div>
            <div class="mt-2 text-2xl font-semibold text-slate-900">
                <?= $h(number_format($totalBalance, 2)) ?> BDT
            </div>
            <p class="mt-1 text-[11px] text-slate-500">
                Sum of current balances across all accounts (tenant scoped).
            </p>
        </div>

        <!-- Unreconciled txns -->
        <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm space-y-2">
            <div class="flex items-center justify-between">
                <div class="text-xs font-medium uppercase tracking-wide text-slate-500">
                    Unreconciled transactions
                </div>
                <i class="fa fa-scale-balanced text-slate-400 text-xs"></i>
            </div>
            <div class="mt-2 text-2xl font-semibold text-slate-900">
                <?php if ($unreconciledTxns === null): ?>
                    <span class="text-slate-400">—</span>
                <?php else: ?>
                    <?= $h((int)$unreconciledTxns) ?>
                <?php endif; ?>
            </div>
            <p class="mt-1 text-[11px] text-slate-500">
                Count of bank transactions not yet matched against payments or journals.
            </p>
        </div>
    </section>

    <!-- Main layout: 2 columns -->
    <section class="grid gap-6 lg:grid-cols-[2fr,1.4fr]">

        <!-- LEFT: accounts table -->
        <div class="rounded-xl border border-slate-200 bg-white shadow-sm">
            <div class="flex flex-col gap-3 border-b border-slate-100 px-4 py-3 md:flex-row md:items-center md:justify-between">
                <div>
                    <h2 class="text-sm font-semibold text-slate-800">Accounts</h2>
                    <p class="text-xs text-slate-500">
                        Manage your bank and cash ledgers per bank, branch and currency.
                    </p>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <button type="button"
                            class="inline-flex items-center gap-2 rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-medium text-slate-700 shadow-sm hover:bg-slate-50">
                        <i class="fa fa-file-arrow-up text-[11px]"></i>
                        <span>Import statement</span>
                    </button>
                    <button type="button"
                            class="inline-flex items-center gap-2 rounded-lg bg-emerald-600 px-3 py-1.5 text-xs font-medium text-white shadow-sm hover:bg-emerald-700">
                        <i class="fa fa-plus-circle text-[11px]"></i>
                        <span>New account</span>
                    </button>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50 text-xs font-semibold text-slate-500 uppercase tracking-wide">
                    <tr>
                        <th class="px-4 py-2 text-left">Account</th>
                        <th class="px-4 py-2 text-left">Bank</th>
                        <th class="px-4 py-2 text-left">Type</th>
                        <th class="px-4 py-2 text-left">Currency</th>
                        <th class="px-4 py-2 text-right">Balance</th>
                        <th class="px-4 py-2 text-center">Status</th>
                        <th class="px-4 py-2 text-right">Actions</th>
                    </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 bg-white">
                    <?php if (!empty($accounts)): ?>
                        <?php foreach ($accounts as $row): ?>
                            <?php
                            $id       = (int)($row['id'] ?? 0);
                            $name     = $row['name']      ?? '';
                            $bankName = $row['bank_name'] ?? '';
                            $accNo    = $row['account_no']?? '';
                            $type     = $row['type']      ?? 'bank';
                            $ccy      = $row['currency']  ?? 'BDT';
                            $bal      = (float)($row['current_balance'] ?? 0.0);
                            $active   = (int)($row['is_active'] ?? 0) === 1;
                            ?>
                            <tr class="hover:bg-emerald-50/40">
                                <td class="px-4 py-2 align-top text-xs">
                                    <div class="font-medium text-slate-900"><?= $h($name ?: 'Account '.$id) ?></div>
                                    <?php if ($accNo !== ''): ?>
                                        <div class="mt-0.5 font-mono text-[11px] text-slate-500">
                                            <?= $h($accNo) ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td class="px-4 py-2 align-top text-xs text-slate-600">
                                    <?= $bankName !== '' ? $h($bankName) : '<span class="text-slate-400">—</span>' ?>
                                </td>
                                <td class="px-4 py-2 align-top text-xs text-slate-600">
                                    <?= $h(ucfirst($type)) ?>
                                </td>
                                <td class="px-4 py-2 align-top text-xs text-slate-600">
                                    <?= $h($ccy) ?>
                                </td>
                                <td class="px-4 py-2 align-top text-right text-xs text-slate-800">
                                    <?= $h(number_format($bal, 2)) ?> BDT
                                </td>
                                <td class="px-4 py-2 align-top text-center">
                                    <?php if ($active): ?>
                                        <span class="inline-flex items-center gap-1 rounded-full bg-emerald-50 px-2 py-[3px] text-[11px] font-medium text-emerald-700">
                                            <span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>
                                            Active
                                        </span>
                                    <?php else: ?>
                                        <span class="inline-flex items-center gap-1 rounded-full bg-slate-100 px-2 py-[3px] text-[11px] font-medium text-slate-600">
                                            <span class="h-1.5 w-1.5 rounded-full bg-slate-400"></span>
                                            Inactive
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-4 py-2 align-top text-right text-xs">
                                    <div class="inline-flex gap-1">
                                        <button type="button"
                                                class="inline-flex items-center rounded-lg border border-slate-200 px-2 py-1 text-[11px] text-slate-700 hover:bg-slate-50">
                                            <i class="fa-regular fa-eye mr-1 text-[10px]"></i> View
                                        </button>
                                        <button type="button"
                                                class="inline-flex items-center rounded-lg border border-slate-200 px-2 py-1 text-[11px] text-slate-700 hover:bg-slate-50">
                                            <i class="fa-regular fa-pen-to-square mr-1 text-[10px]"></i> Edit
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" class="px-4 py-8 text-center text-sm text-slate-500">
                                No bank accounts configured yet.
                                <button type="button"
                                        class="ml-1 text-emerald-700 font-medium hover:underline">
                                    Create your first account
                                </button>
                            </td>
                        </tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- RIGHT: reconciliation & data readiness -->
        <div class="space-y-4">
            <!-- Reconciliation panel -->
            <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm space-y-3">
                <div class="flex items-center justify-between">
                    <h2 class="text-sm font-semibold text-slate-800">
                        Reconciliation cockpit
                    </h2>
                    <span class="text-[11px] text-slate-500">
                        Fast glance on matching status
                    </span>
                </div>

                <dl class="space-y-2 text-xs text-slate-600">
                    <div class="flex justify-between gap-4">
                        <dt class="text-slate-500">Unreconciled transactions</dt>
                        <dd class="text-right text-slate-900">
                            <?php if ($unreconciledTxns === null): ?>
                                <span class="text-slate-400">—</span>
                            <?php else: ?>
                                <?= $h((int)$unreconciledTxns) ?>
                            <?php endif; ?>
                        </dd>
                    </div>
                    <div class="flex justify-between gap-4">
                        <dt class="text-slate-500">Data source</dt>
                        <dd class="text-right text-slate-900">
                            <?= $dataReadyTxns
                                ? 'biz_bank_txns'
                                : 'Not connected yet' ?>
                        </dd>
                    </div>
                </dl>

                <div class="mt-3 flex flex-wrap gap-2">
                    <button type="button"
                            class="inline-flex items-center gap-2 rounded-lg bg-slate-900 px-3 py-1.5 text-xs font-medium text-white shadow-sm hover:bg-slate-800">
                        <i class="fa fa-magnifying-glass-chart text-[11px]"></i>
                        <span>Start reconciliation</span>
                    </button>
                    <button type="button"
                            class="inline-flex items-center gap-2 rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-medium text-slate-700 shadow-sm hover:bg-slate-50">
                        <i class="fa fa-file-import text-[11px]"></i>
                        <span>Upload bank statement</span>
                    </button>
                </div>

                <p class="mt-2 text-[11px] text-slate-500">
                    In future, this panel can open a dedicated reconciliation screen where each
                    line from bank statements is matched against BizFlow payments and journals.
                </p>
            </div>

            <!-- Data readiness -->
            <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm space-y-3">
                <h2 class="text-sm font-semibold text-slate-800">Banking data readiness</h2>
                <ul class="space-y-1 text-xs text-slate-600">
                    <li class="flex items-center justify-between">
                        <span>Bank accounts</span>
                        <span class="inline-flex items-center gap-1 rounded-full px-2 py-[2px] text-[10px]
                                     <?= $dataReadyAccounts
                                         ? 'bg-emerald-50 text-emerald-700'
                                         : 'bg-slate-100 text-slate-500' ?>">
                            <?= $dataReadyAccounts ? 'Connected' : 'Pending' ?>
                        </span>
                    </li>
                    <li class="flex items-center justify-between">
                        <span>Bank transactions</span>
                        <span class="inline-flex items-center gap-1 rounded-full px-2 py-[2px] text-[10px]
                                     <?= $dataReadyTxns
                                         ? 'bg-emerald-50 text-emerald-700'
                                         : 'bg-slate-100 text-slate-500' ?>">
                            <?= $dataReadyTxns ? 'Connected' : 'Pending' ?>
                        </span>
                    </li>
                </ul>
                <p class="mt-2 text-[11px] text-slate-500">
                    This section is purely informational and will never break even if the underlying
                    BizFlow banking tables are missing or still evolving.
                </p>
            </div>
        </div>
    </section>

    <!-- How to use this page -->
    <section class="rounded-xl border border-dashed border-emerald-400 bg-emerald-50/60 px-4 py-4 text-sm text-slate-800">
        <h2 class="mb-2 flex items-center gap-2 text-sm font-semibold text-emerald-900">
            <span class="inline-flex h-6 w-6 items-center justify-center rounded-full bg-emerald-600 text-[11px] text-white">
                ?
            </span>
            How to use this page
        </h2>
        <ul class="ml-6 list-disc space-y-1 text-[13px]">
            <li>Use the <strong>Accounts</strong> table to maintain your bank and cash accounts per bank, branch and currency.</li>
            <li>Watch <strong>Total balance</strong> and <strong>Active accounts</strong> to understand your current liquidity spread.</li>
            <li>Use <strong>Import statement</strong> and <strong>Start reconciliation</strong> buttons as the entry point for future LC and bank statement workflows.</li>
            <li>Monitor <strong>Unreconciled transactions</strong> so month-end closing does not leave unmatched items.</li>
            <li>This page is UI-first: once the BizFlow banking schemas are finalised, it will start showing live tenant data automatically.</li>
        </ul>
    </section>
</div>