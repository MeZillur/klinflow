<?php
/** @var array $order */
/** @var array $org */
/** @var string|null $download */
/** @var bool $no_layout */

// If the renderer expects explicit shell, honor $no_layout:
if (!empty($no_layout)) { /* emit plain content only */ }
$h = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');

$code = $order['code'] ?: ('#'.$order['id']);
$total = (float)($order['total'] ?? 0);
$discount = (float)($order['discount'] ?? 0);
$tax = (float)($order['tax'] ?? 0);
$paid = (float)($order['pay'] ?? 0);
$net  = max(0, $total - $discount + $tax);
$due  = max(0, $net - $paid);

// Brand color utility (KlinFlow green)
$brandBtn = 'bg-emerald-600 hover:bg-emerald-700 text-white';
?>
<?php if (!empty($no_layout)): ?>
<!doctype html><html class="h-full"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"></head><body class="h-full bg-white text-slate-900">
<?php endif; ?>

<div class="mx-auto max-w-4xl p-6 print:p-0">
  <!-- Header -->
  <div class="flex items-start justify-between gap-6 border-b border-slate-200 pb-5">
    <div class="flex items-center gap-4">
      <?php if (!empty($org['logo'])): ?>
        <img src="<?= $h($org['logo']) ?>" alt="Logo" class="h-12 w-auto rounded">
      <?php endif; ?>
      <div>
        <h1 class="text-xl font-semibold text-slate-900 dark:text-slate-100">Invoice</h1>
        <p class="text-sm text-slate-500">#<?= $h($code) ?> · <?= $h(substr((string)$order['date'],0,10)) ?></p>
      </div>
    </div>

    <div class="text-right">
      <div class="font-semibold"><?= $h($org['name'] ?? 'Workspace') ?></div>
      <?php if (!empty($org['address'])): ?><div class="text-sm text-slate-600"><?= nl2br($h($org['address'])) ?></div><?php endif; ?>
      <div class="text-xs text-slate-500">
        <?php if (!empty($org['phone'])): ?>☎ <?= $h($org['phone']) ?><?php endif; ?>
        <?php if (!empty($org['email'])): ?><?= !empty($org['phone']) ? ' · ' : '' ?>✉ <?= $h($org['email']) ?><?php endif; ?>
      </div>
      <?php if (!empty($download)): ?>
        <a href="<?= $h($download) ?>" class="inline-flex items-center mt-2 rounded-md px-3 py-1.5 text-sm font-medium <?= $brandBtn ?> print:hidden">Download PDF</a>
      <?php endif; ?>
    </div>
  </div>

  <!-- Bill To + Status -->
  <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mt-6">
    <div class="rounded-xl border border-slate-200 p-4">
      <div class="text-xs uppercase tracking-wide text-slate-500 mb-1">Bill To</div>
      <div class="font-medium"><?= $h($order['customer'] ?: 'Customer') ?></div>
      <?php if (!empty($order['note'])): ?>
        <div class="mt-2 text-sm text-slate-600"><?= nl2br($h($order['note'])) ?></div>
      <?php endif; ?>
    </div>
    <div class="rounded-xl border border-slate-200 p-4">
      <div class="text-xs uppercase tracking-wide text-slate-500 mb-1">Status</div>
      <div class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-medium
                  <?= (strtolower((string)$order['status'])==='paid'?'bg-emerald-100 text-emerald-700':'bg-amber-100 text-amber-700') ?>">
        <?= $h(ucfirst($order['status'] ?? 'Unpaid')) ?>
      </div>
    </div>
  </div>

  <!-- Items -->
  <div class="mt-6 overflow-hidden rounded-xl border border-slate-200">
    <table class="w-full text-sm">
      <thead class="bg-slate-50">
        <tr class="text-left text-slate-600">
          <th class="px-3 py-2 font-medium">Item</th>
          <th class="px-3 py-2 font-medium">SKU</th>
          <th class="px-3 py-2 font-medium text-right">Qty</th>
          <th class="px-3 py-2 font-medium text-right">Unit</th>
          <th class="px-3 py-2 font-medium text-right">Line</th>
        </tr>
      </thead>
      <tbody>
        <?php if (!empty($order['items'])): foreach ($order['items'] as $it): ?>
          <tr class="border-t border-slate-200">
            <td class="px-3 py-2"><?= $h($it['name'] ?? '') ?></td>
            <td class="px-3 py-2 text-slate-500"><?= $h($it['sku'] ?? '') ?></td>
            <td class="px-3 py-2 text-right"><?= number_format((float)($it['qty'] ?? 0), 2) ?></td>
            <td class="px-3 py-2 text-right"><?= number_format((float)($it['price'] ?? 0), 2) ?></td>
            <td class="px-3 py-2 text-right"><?= number_format((float)($it['line'] ?? 0), 2) ?></td>
          </tr>
        <?php endforeach; else: ?>
          <tr class="border-t border-slate-200"><td colspan="5" class="px-3 py-6 text-center text-slate-500">No items.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>

  <!-- Totals -->
  <div class="mt-6 grid grid-cols-1 md:grid-cols-2 gap-6">
    <div></div>
    <div class="rounded-xl border border-slate-200 p-4">
      <dl class="space-y-2 text-sm">
        <div class="flex justify-between"><dt class="text-slate-600">Subtotal</dt><dd><?= number_format($total,2) ?></dd></div>
        <div class="flex justify-between"><dt class="text-slate-600">Discount</dt><dd><?= number_format($discount,2) ?></dd></div>
        <div class="flex justify-between"><dt class="text-slate-600">Tax/VAT</dt><dd><?= number_format($tax,2) ?></dd></div>
        <div class="flex justify-between font-medium border-t pt-2"><dt>Net Total</dt><dd><?= number_format($net,2) ?></dd></div>
        <div class="flex justify-between"><dt class="text-slate-600">Paid</dt><dd><?= number_format($paid,2) ?></dd></div>
        <div class="flex justify-between font-semibold text-rose-700"><dt>Amount Due</dt><dd><?= number_format($due,2) ?></dd></div>
      </dl>
    </div>
  </div>

  <!-- Footer -->
  <div class="mt-8 border-t border-slate-200 pt-3 text-xs text-slate-500">
    This is a system-generated document from KlinFlow. No signature is required.
  </div>
</div>

<?php if (!empty($no_layout)): ?>
</body></html>
<?php endif; ?>