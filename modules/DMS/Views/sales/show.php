<?php
declare(strict_types=1);

/** @var array $sale */
/** @var array $items */
/** @var string $module_base */
/** @var string $title */

if (!function_exists('h')) {
    function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
}

$sale   = is_array($sale ?? null) ? $sale : [];
$items  = is_array($items ?? null) ? $items : [];
$base   = (string)($module_base ?? '/apps/dms');

$money = static function($n): string {
    $n = (float)$n;
    return '৳ ' . number_format($n, 2);
};

?>
<div class="max-w-5xl mx-auto p-4 space-y-6">
  <div class="flex items-center justify-between">
    <h1 class="text-2xl font-semibold">
      <?= h($title ?? ('Invoice '.($sale['sale_no'] ?? ''))) ?>
    </h1>
    <div class="space-x-2">
      <a href="<?= h($base.'/sales') ?>" class="px-3 py-2 rounded border">Back</a>
      <a href="<?= h($base.'/sales/'.(int)($sale['id'] ?? 0).'/print') ?>" class="px-3 py-2 rounded bg-emerald-600 text-white">Print</a>
    </div>
  </div>

  <div class="rounded-xl border p-4 grid grid-cols-1 md:grid-cols-2 gap-4 bg-white">
    <div>
      <div class="text-sm text-slate-500">Invoice No</div>
      <div class="font-medium"><?= h($sale['sale_no'] ?? '') ?></div>
    </div>
    <div>
      <div class="text-sm text-slate-500">Date</div>
      <div class="font-medium"><?= h($sale['sale_date'] ?? '') ?></div>
    </div>
    <div>
      <div class="text-sm text-slate-500">Customer</div>
      <div class="font-medium"><?= h($sale['customer_name'] ?? '') ?><?= isset($sale['customer_id']) && $sale['customer_id'] ? ' · CID-'.str_pad((string)$sale['customer_id'], 6, '0', STR_PAD_LEFT) : '' ?></div>
    </div>
    <div>
      <div class="text-sm text-slate-500">Status</div>
      <div class="font-medium">
        <?= h($sale['status'] ?? 'confirmed') ?>
        <?php if (!empty($sale['invoice_status'])): ?>
          <span class="text-slate-500"> · <?= h($sale['invoice_status']) ?></span>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <div class="rounded-xl border overflow-x-auto bg-white">
    <table class="min-w-full text-sm">
      <thead class="bg-slate-50">
        <tr>
          <th class="px-3 py-2 text-left">#</th>
          <th class="px-3 py-2 text-left">Product</th>
          <th class="px-3 py-2 text-right">Qty</th>
          <th class="px-3 py-2 text-right">Unit Price</th>
          <th class="px-3 py-2 text-right">Line Total</th>
        </tr>
      </thead>
      <tbody>
        <?php
        $sub = 0.0; $i = 1;
        foreach ($items as $row):
          $qty   = (float)($row['qty'] ?? 0);
          $price = (float)($row['unit_price'] ?? 0);
          $line  = (float)($row['line_total'] ?? ($qty * $price));
          $sub  += $line;
        ?>
          <tr class="border-t">
            <td class="px-3 py-2"><?= $i++ ?></td>
            <td class="px-3 py-2"><?= h($row['product_name'] ?? '') ?></td>
            <td class="px-3 py-2 text-right"><?= h((string)$qty) ?></td>
            <td class="px-3 py-2 text-right"><?= h($money($price)) ?></td>
            <td class="px-3 py-2 text-right"><?= h($money($line)) ?></td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$items): ?>
          <tr><td colspan="5" class="px-3 py-6 text-center text-slate-500">No items.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>

  <?php
    $discType = (string)($sale['discount_type'] ?? 'amount');
    $discVal  = (float)($sale['discount_value'] ?? 0);
    $discAmt  = $discType === 'percent' ? min($sub, $sub * ($discVal/100)) : min($sub, $discVal);
    $grand    = max(0, $sub - $discAmt);
  ?>
  <div class="ml-auto w-full md:w-80 rounded-xl border p-4 bg-white">
    <div class="flex justify-between py-1">
      <div class="text-slate-600">Subtotal</div>
      <div class="font-medium"><?= h($money($sub)) ?></div>
    </div>
    <div class="flex justify-between py-1">
      <div class="text-slate-600">Discount<?= $discType==='percent' ? ' ('.$discVal.'%)' : '' ?></div>
      <div class="font-medium"><?= h($money($discAmt)) ?></div>
    </div>
    <div class="flex justify-between py-2 border-t mt-2">
      <div class="font-semibold">Grand Total</div>
      <div class="font-semibold"><?= h($money($grand)) ?></div>
    </div>
  </div>

  <?php if (!empty($sale['notes'])): ?>
    <div class="rounded-xl border p-4 bg-white">
      <div class="text-sm text-slate-500 mb-1">Notes</div>
      <div><?= nl2br(h($sale['notes'])) ?></div>
    </div>
  <?php endif; ?>
</div>