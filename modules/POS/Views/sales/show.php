<?php
declare(strict_types=1);
/**
 * Sales → Show Invoice (CONTENT-ONLY)
 * Inputs: $sale, $items, $base
 */

/** @var array $sale */
/** @var array $items */
/** @var string $base */

$h = fn($x)=>htmlspecialchars((string)$x, ENT_QUOTES, 'UTF-8');

$id   = (int)($sale['id'] ?? 0);
$inv  = $sale['invoice_no']
     ?? $sale['sale_no']
     ?? $sale['code']
     ?? ('INV-'.$id);

$date = $sale['sale_date']
     ?? $sale['created_at']
     ?? date('Y-m-d');

$customer = $sale['customer_name']
         ?? $sale['customer']
         ?? 'Walk-in Customer';

$subtotal = (float)(
    $sale['subtotal_amount']
    ?? ($sale['subtotal'] ?? ($sale['total_before_discount'] ?? 0))
);
$discount = (float)(
    $sale['discount_amount']
    ?? ($sale['discount'] ?? 0)
);
$tax      = (float)(
    $sale['tax_amount']
    ?? ($sale['tax'] ?? 0)
);
$grand    = (float)(
    $sale['grand_total']
    ?? ($sale['total_amount'] ?? ($sale['total'] ?? ($subtotal - $discount + $tax)))
);

$payment  = $sale['payment_method'] ?? $sale['pay_method'] ?? 'Cash'; // will default until you add columns
$ref      = $sale['reference'] ?? $sale['payment_ref'] ?? '';
$notes    = $sale['notes'] ?? '';
?>
<div class="px-6 py-6 space-y-6 max-w-5xl mx-auto">

  <!-- Header -->
  <div class="flex items-start justify-between gap-4">
    <div>
      <h1 class="text-2xl font-bold tracking-tight">Invoice #<?= $h($inv) ?></h1>
      <div class="text-sm text-gray-500 mt-1">
        Date: <?= $h(substr((string)$date,0,10)) ?> · Customer: <?= $h($customer) ?>
      </div>
    </div>

    <div class="flex flex-wrap gap-2">
      <a href="<?= $base ?>/sales"
         class="inline-flex items-center gap-2 px-3 py-2 rounded-lg border border-gray-300 text-sm hover:bg-gray-100">
        <i class="fa fa-arrow-left"></i> Back to Sales
      </a>

      <!-- Print buttons -->
      <a href="<?= $base ?>/sales/<?= (int)$sale['id'] ?>/print/a4"
   class="inline-flex items-center gap-2 px-3 py-2 rounded-lg bg-blue-600 text-white text-sm hover:bg-blue-700">
  <i class="fa fa-print"></i> Print A4
</a>

<a href="<?= $base ?>/sales/<?= (int)$sale['id'] ?>/print/pos"
   class="inline-flex items-center gap-2 px-3 py-2 rounded-lg bg-emerald-600 text-white text-sm hover:bg-emerald-700">
  <i class="fa fa-receipt"></i> Print POS
</a>
    </div>
  </div>

  <!-- Bill-to + meta -->
  <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
    <div class="rounded-xl border border-gray-200 bg-white p-4">
      <h2 class="text-sm font-semibold text-gray-600 mb-2">Billed To</h2>
      <div class="text-gray-900 font-medium"><?= $h($customer) ?></div>
      <?php if (!empty($sale['customer_phone'] ?? '')): ?>
        <div class="text-sm text-gray-500 mt-1">Phone: <?= $h($sale['customer_phone']) ?></div>
      <?php endif; ?>
    </div>
    <div class="rounded-xl border border-gray-200 bg-white p-4 text-sm space-y-1">
      <div class="flex justify-between"><span class="text-gray-500">Invoice No</span><span class="font-medium"><?= $h($inv) ?></span></div>
      <div class="flex justify-between"><span class="text-gray-500">Date</span><span><?= $h(substr((string)$date,0,10)) ?></span></div>
      <div class="flex justify-between"><span class="text-gray-500">Payment Method</span><span><?= $h($payment) ?></span></div>
      <?php if ($ref !== ''): ?>
        <div class="flex justify-between"><span class="text-gray-500">Reference</span><span><?= $h($ref) ?></span></div>
      <?php endif; ?>
    </div>
  </div>

  <!-- Items table -->
  <div class="rounded-xl border border-gray-200 bg-white overflow-x-auto">
    <table class="min-w-full text-sm">
      <thead class="bg-gray-100">
        <tr>
          <th class="px-4 py-2 text-left font-semibold">#</th>
          <th class="px-4 py-2 text-left font-semibold">Product</th>
          <th class="px-4 py-2 text-left font-semibold">Unit</th>
          <th class="px-4 py-2 text-right font-semibold">Price</th>
          <th class="px-4 py-2 text-right font-semibold">Qty</th>
          <th class="px-4 py-2 text-right font-semibold">Total</th>
        </tr>
      </thead>
      <tbody class="divide-y divide-gray-100">
        <?php if (empty($items)): ?>
          <tr><td colspan="6" class="px-4 py-4 text-center text-gray-500">No line items found.</td></tr>
        <?php else: ?>
          <?php $i = 0; ?>
          <?php foreach ($items as $row): ?>
            <?php
              $i++;
              $pname = $row['product_name'] ?? $row['name'] ?? '';
              $sku   = $row['sku'] ?? '';
              $unit  = $row['unit'] ?? ($row['unit_name'] ?? 'pcs');
              $qty   = (float)($row['qty'] ?? $row['quantity'] ?? 0);
              $price = (float)($row['unit_price'] ?? $row['price'] ?? 0);
              $line  = (float)($row['line_total'] ?? ($qty * $price));
            ?>
            <tr>
              <td class="px-4 py-2"><?= $i ?></td>
              <td class="px-4 py-2">
                <div class="font-medium"><?= $h($pname) ?></div>
                <?php if ($sku !== ''): ?>
                  <div class="text-xs text-gray-500">SKU: <?= $h($sku) ?></div>
                <?php endif; ?>
              </td>
              <td class="px-4 py-2"><?= $h($unit) ?></td>
              <td class="px-4 py-2 text-right"><?= number_format($price,2) ?></td>
              <td class="px-4 py-2 text-right"><?= number_format($qty,2) ?></td>
              <td class="px-4 py-2 text-right"><?= number_format($line,2) ?></td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>

  <!-- Totals -->
  <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
    <div>
      <?php if ($notes !== ''): ?>
        <div class="rounded-xl border border-gray-200 bg-white p-4 text-sm">
          <div class="font-semibold mb-1">Notes</div>
          <div class="text-gray-600 whitespace-pre-line"><?= $h($notes) ?></div>
        </div>
      <?php endif; ?>
    </div>
    <div class="rounded-xl border border-gray-200 bg-white p-4 text-sm w-full md:w-80 ml-auto">
      <div class="flex justify-between py-1">
        <span>Subtotal</span><span><?= number_format($subtotal,2) ?></span>
      </div>
      <div class="flex justify-between py-1 text-red-600">
        <span>Discount</span><span>-<?= number_format($discount,2) ?></span>
      </div>
      <div class="flex justify-between py-1">
        <span>Tax</span><span><?= number_format($tax,2) ?></span>
      </div>
      <hr class="my-2">
      <div class="flex justify-between py-1 text-lg font-bold">
        <span>Grand Total</span><span><?= number_format($grand,2) ?></span>
      </div>
    </div>
  </div>
</div>