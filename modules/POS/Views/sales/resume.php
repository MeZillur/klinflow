<?php
/** @var string $base */
/** @var array  $rows */

$h      = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
$brand  = '#228B22';
?>
<div class="max-w-5xl mx-auto space-y-4">

    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-semibold text-slate-900">Resume Held Sales</h1>
            <p class="mt-1 text-sm text-slate-500">
                Pick a parked sale to resume checkout at the register.
            </p>
        </div>

        <div class="flex items-center gap-2">
            <a
                href="<?= $h($base) ?>/sales/hold"
                class="text-xs sm:text-sm font-medium text-emerald-700 hover:text-emerald-800"
            >
                View all holds →
            </a>
            <a
                href="<?= $h($base) ?>/sales/register"
                class="inline-flex items-center gap-2 px-3 py-2 rounded-lg text-white text-xs sm:text-sm shadow-sm hover:shadow-md transition"
                style="background:<?= $h($brand) ?>"
            >
                <span class="hidden sm:inline-block">
                    Go to Register
                </span>
                <span class="sm:hidden">Register</span>
            </a>
        </div>
    </div>

    <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-x-auto">
        <table class="min-w-full text-sm">
            <thead class="bg-slate-50">
            <tr>
                <th class="px-3 py-2 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">
                    Invoice
                </th>
                <th class="px-3 py-2 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">
                    Customer
                </th>
                <th class="px-3 py-2 text-right text-xs font-semibold text-slate-500 uppercase tracking-wide">
                    Amount
                </th>
                <th class="px-3 py-2 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">
                    Created
                </th>
                <th class="px-3 py-2 text-center text-xs font-semibold text-slate-500 uppercase tracking-wide">
                    Action
                </th>
            </tr>
            </thead>
            <tbody>
            <?php if (!$rows): ?>
                <tr>
                    <td colspan="5" class="px-3 py-6 text-center text-slate-500">
                        No held sales at the moment.
                    </td>
                </tr>
            <?php else: ?>
                <?php foreach ($rows as $r): ?>
                    <tr class="border-t border-slate-100 hover:bg-slate-50/60">
                        <td class="px-3 py-2 font-mono text-xs text-slate-800">
                            <?= $h($r['invoice_no'] ?? '') ?>
                        </td>
                        <td class="px-3 py-2">
                            <span class="text-slate-800 text-sm">
                                <?= $h($r['customer_name'] ?? 'Walk-in') ?>
                            </span>
                        </td>
                        <td class="px-3 py-2 text-right">
                            <span class="font-medium text-slate-900">
                                <?= number_format((float)($r['total_amount'] ?? 0), 2) ?>
                            </span>
                        </td>
                        <td class="px-3 py-2 text-sm text-slate-500">
                            <?= $h($r['created_at'] ?? '') ?>
                        </td>
                        <td class="px-3 py-2 text-center">
                            <a
                                href="<?= $h($base) ?>/sales/<?= (int)($r['id'] ?? 0) ?>"
                                class="inline-flex items-center justify-center px-3 py-1.5 rounded-full text-xs font-medium text-white hover:shadow-sm"
                                style="background:<?= $h($brand) ?>"
                            >
                                Resume
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>