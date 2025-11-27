<?php
/** @var array  $rows */
/** @var string $base */

$h      = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
$brand  = '#228B22'; // KlinFlow green
$total  = count($rows);
$label  = $total === 1 ? 'held sale' : 'held sales';
?>
<div class="max-w-5xl mx-auto px-4 py-4">
    <!-- Header -->
    <div class="flex items-center justify-between gap-3 mb-4">
        <div>
            <h1 class="text-2xl font-semibold text-slate-900">Held / Parked Sales</h1>
            <p class="mt-1 text-sm text-slate-500">
                Draft sales saved from the register. Open one to resume checkout.
            </p>
        </div>

        <div class="flex items-center gap-2">
            <span class="inline-flex items-center rounded-full border border-slate-200 bg-slate-50 px-3 py-1 text-xs font-medium text-slate-600">
                <?= $total ?> <?= $h($label) ?>
            </span>

            <a href="<?= $h(rtrim($base, '/')) ?>/sales/register"
               class="inline-flex items-center gap-2 px-3 py-2 rounded-lg text-white text-sm font-medium shadow-sm hover:opacity-90 transition"
               style="background:<?= $h($brand) ?>">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
                    <path d="M3 4a1 1 0 011-1h3.382a1 1 0 01.723.305l1.447 1.53H16a1 1 0 011 1v1H3V4z" />
                    <path fill-rule="evenodd" d="M3 8h14v6a2 2 0 01-2 2H5a2 2 0 01-2-2V8zm4 2a1 1 0 100 2h6a1 1 0 100-2H7z" clip-rule="evenodd" />
                </svg>
                <span>Go to register</span>
            </a>
        </div>
    </div>

    <!-- Table -->
    <div class="overflow-x-auto bg-white rounded-xl border border-slate-200 shadow-sm">
        <table class="min-w-full text-sm">
            <thead class="bg-slate-50">
            <tr>
                <th class="px-3 py-2 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Invoice</th>
                <th class="px-3 py-2 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Customer</th>
                <th class="px-3 py-2 text-right text-xs font-semibold text-slate-500 uppercase tracking-wide">Amount</th>
                <th class="px-3 py-2 text-left text-xs font-semibold text-slate-500 uppercase tracking-wide">Created</th>
                <th class="px-3 py-2 text-center text-xs font-semibold text-slate-500 uppercase tracking-wide">Action</th>
            </tr>
            </thead>
            <tbody>
            <?php if (!$rows): ?>
                <tr>
                    <td colspan="5" class="px-3 py-8 text-center text-slate-500 text-sm">
                        No parked sales right now.
                    </td>
                </tr>
            <?php else: ?>
                <?php foreach ($rows as $r): ?>
                    <tr class="border-t border-slate-100 hover:bg-slate-50/60">
                        <td class="px-3 py-2 font-medium text-slate-900">
                            <?= $h($r['invoice_no'] ?? '') ?>
                        </td>
                        <td class="px-3 py-2 text-slate-700">
                            <?= $h($r['customer_name'] ?? 'Walk-in') ?>
                        </td>
                        <td class="px-3 py-2 text-right text-slate-900">
                            <?= number_format((float)($r['total_amount'] ?? 0), 2) ?>
                        </td>
                        <td class="px-3 py-2 text-slate-500">
                            <?= $h($r['created_at'] ?? '') ?>
                        </td>
                        <td class="px-3 py-2 text-center">
                            <a href="<?= $h(rtrim($base, '/')) ?>/sales/<?= (int)($r['id'] ?? 0) ?>"
                               class="text-[<?= $h($brand) ?>] font-medium hover:underline">
                                Open
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>