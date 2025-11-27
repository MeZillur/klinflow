<?php
/** @var array  $purchase */
/** @var array  $items */
/** @var string $module_base */

$h     = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
$money = fn($n) => number_format((float)$n, 2);

// Status badge
$badge = function (string $status) use ($h): string {
  $map = [
    'draft'     => 'bg-slate-100 text-slate-700 border-slate-200 dark:bg-slate-800/70 dark:text-slate-200 dark:border-slate-700',
    'confirmed' => 'bg-emerald-100 text-emerald-700 border-emerald-200 dark:bg-emerald-900/30 dark:text-emerald-300 dark:border-emerald-800',
    'cancelled' => 'bg-rose-100 text-rose-700 border-rose-200 dark:bg-rose-900/30 dark:text-rose-300 dark:border-rose-800',
  ];
  $cls = $map[strtolower($status) ?: 'draft'] ?? $map['draft'];
  return '<span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs border '.$cls.'">'
       . '<i class="fa-solid fa-circle-dot text-[10px]"></i> '.$h(ucfirst($status)).'</span>';
};

/* ─────────────────── Smart fallbacks so legacy/new columns work ─────────────────── */

// Bill number/date
$billNo = $purchase['bill_no']
      ?? $purchase['purchase_no']
      ?? $purchase['invoice_no']
      ?? $purchase['ref_no']
      ?? $purchase['number']
      ?? $purchase['code']
      ?? $purchase['id'];

$billDate = $purchase['bill_date']
        ?? $purchase['purchase_date']
        ?? $purchase['date']
        ?? $purchase['created_at']
        ?? '';

// Supplier label (prefer name, then generic/legacy, finally #id)
$supplier =
    $purchase['supplier_name']  ??
    $purchase['supplier']       ??
    $purchase['dealer_name']    ??
    (isset($purchase['supplier_id']) && $purchase['supplier_id'] !== null
      ? '#'.$purchase['supplier_id'] : null);

// Grand total fallback keys
$grandTotal = $purchase['grand_total']
          ?? $purchase['total']
          ?? $purchase['amount']
          ?? $purchase['net_total']
          ?? $purchase['payable']
          ?? 0.0;

// Optional link to supplier profile if we have the id
$supplierLink = (!empty($purchase['supplier_id']))
  ? rtrim($module_base, '/').'/suppliers/'.(int)$purchase['supplier_id']
  : null;
?>
<div class="p-6 text-slate-900 dark:text-slate-100">
  <!-- Header -->
  <div class="flex items-start justify-between gap-3 mb-6">
    <div class="flex items-center gap-3">
      <div class="h-10 w-10 rounded-xl bg-emerald-600 text-white grid place-items-center shadow-sm">
        <i class="fa-solid fa-file-invoice text-lg"></i>
      </div>
      <div>
        <h1 class="text-2xl font-extrabold tracking-tight">
          Purchase #<?= $h($billNo) ?>
        </h1>
        <div class="flex flex-wrap items-center gap-2 text-sm text-slate-500 dark:text-slate-400">
          <span><i class="fa-regular fa-calendar-days"></i> <?= $h($billDate) ?></span>
          <span>·</span>
          <span>
            <i class="fa-solid fa-building-user"></i>
            <?php if ($supplierLink && $supplier): ?>
              <a class="hover:underline text-emerald-700 dark:text-emerald-300" href="<?= $h($supplierLink) ?>">
                <?= $h($supplier) ?>
              </a>
            <?php else: ?>
              <?= $h($supplier ?? '—') ?>
            <?php endif; ?>
          </span>
          <span>·</span>
          <?= $badge((string)($purchase['status'] ?? 'draft')) ?>
        </div>
      </div>
    </div>

    <div class="flex gap-2">
      <a href="<?= $h($module_base) ?>/purchases"
         class="inline-flex items-center gap-2 px-3 py-2 rounded-xl border border-slate-300 hover:bg-slate-50 dark:border-slate-700 dark:hover:bg-slate-800">
        <i class="fa-solid fa-arrow-left"></i> Back
      </a>
      <a href="<?= $h($module_base) ?>/purchases/<?= (int)$purchase['id'] ?>/edit"
         class="inline-flex items-center gap-2 px-3 py-2 rounded-xl bg-emerald-600 text-white hover:bg-emerald-700">
        <i class="fa-solid fa-pen-to-square"></i> Edit
      </a>
    </div>
  </div>

  <!-- Meta cards -->
  <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
    <div class="rounded-2xl border border-slate-200 bg-white p-4 dark:bg-slate-900 dark:border-slate-700">
      <div class="text-xs text-slate-500 dark:text-slate-400">Bill No</div>
      <div class="mt-1 font-semibold"><?= $h($billNo ?? '—') ?></div>
    </div>
    <div class="rounded-2xl border border-slate-200 bg-white p-4 dark:bg-slate-900 dark:border-slate-700">
      <div class="text-xs text-slate-500 dark:text-slate-400">Bill Date</div>
      <div class="mt-1 font-semibold"><?= $h($billDate ?? '—') ?></div>
    </div>
    <div class="rounded-2xl border border-slate-200 bg-white p-4 dark:bg-slate-900 dark:border-slate-700">
      <div class="text-xs text-slate-500 dark:text-slate-400">Supplier</div>
      <div class="mt-1 font-semibold">
        <?php if ($supplierLink && $supplier): ?>
          <a class="hover:underline text-emerald-700 dark:text-emerald-300" href="<?= $h($supplierLink) ?>">
            <?= $h($supplier) ?>
          </a>
        <?php else: ?>
          <?= $h($supplier ?? '—') ?>
        <?php endif; ?>
      </div>
    </div>
    <div class="rounded-2xl border border-slate-200 bg-white p-4 dark:bg-slate-900 dark:border-slate-700">
      <div class="text-xs text-slate-500 dark:text-slate-400">Grand Total</div>
      <div class="mt-1 text-xl font-extrabold">৳ <?= $money($grandTotal) ?></div>
    </div>
  </div>

  <?php if (!empty($purchase['notes'])): ?>
    <div class="mb-6 rounded-2xl border border-slate-200 bg-white p-4 dark:bg-slate-900 dark:border-slate-700">
      <div class="text-xs text-slate-500 dark:text-slate-400 mb-1"><i class="fa-regular fa-note-sticky"></i> Notes</div>
      <div class="text-slate-700 dark:text-slate-200"><?= nl2br($h($purchase['notes'])) ?></div>
    </div>
  <?php endif; ?>

  <!-- Items -->
  <div class="rounded-2xl border border-slate-200 overflow-hidden bg-white dark:bg-slate-900 dark:border-slate-700">
    <div class="px-4 py-3 bg-slate-50 dark:bg-slate-800/60 font-semibold text-slate-700 dark:text-slate-200">
      <i class="fa-solid fa-list-check mr-2"></i>Items
    </div>
    <div class="overflow-x-auto">
      <table class="min-w-full text-sm">
        <thead>
          <tr class="text-left text-slate-600 dark:text-slate-300">
            <th class="px-4 py-2">Product</th>
            <th class="px-4 py-2 w-28">Qty</th>
            <th class="px-4 py-2 w-32">Unit Price</th>
            <th class="px-4 py-2 w-36 text-right">Line Total</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
          <?php
            $subtotal = 0.0;
            foreach ($items as $ln):
              // Field fallbacks for mixed schemas
              $qty   = (float)($ln['qty'] ?? $ln['quantity'] ?? $ln['qty_in'] ?? 0);
              $price = (float)($ln['unit_price'] ?? $ln['price'] ?? $ln['rate'] ?? 0);
              $name  = $ln['product_name'] ?? $ln['name'] ?? '';
              $line  = $ln['line_total'] ?? ($qty * $price);
              $subtotal += (float)$line;
          ?>
          <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/60">
            <td class="px-4 py-2"><?= $h($name) ?></td>
            <td class="px-4 py-2 tabular-nums"><?= $h($qty) ?></td>
            <td class="px-4 py-2 tabular-nums">৳ <?= $money($price) ?></td>
            <td class="px-4 py-2 tabular-nums text-right">৳ <?= $money($line) ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
        <tfoot class="bg-slate-50 dark:bg-slate-800/60">
          <tr>
            <td class="px-4 py-3 font-semibold text-slate-600 dark:text-slate-300" colspan="3">Subtotal</td>
            <td class="px-4 py-3 text-right font-bold">৳ <?= $money($subtotal) ?></td>
          </tr>
          <tr>
            <td class="px-4 py-3 font-semibold text-slate-600 dark:text-slate-300" colspan="3">Grand Total</td>
            <td class="px-4 py-3 text-right text-xl font-extrabold">
              ৳ <?= $money(($purchase['grand_total'] ?? $purchase['total'] ?? $purchase['amount'] ?? $purchase['net_total'] ?? $purchase['payable'] ?? $subtotal)) ?>
            </td>
          </tr>
        </tfoot>
      </table>
    </div>
  </div>
</div>