<?php
$h = fn($s)=>htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
?>
<div class="p-4">
  <div class="flex items-center justify-between">
    <div class="font-semibold">
      Invoice <?= $h($sale['invoice_no'] ?? ('#'.$sale['id'])) ?>
      <div class="text-xs text-gray-500">
        Date: <?= $sale['sold_at'] ? date('d M Y H:i', strtotime((string)$sale['sold_at'])) : '' ?>
      </div>
      <div class="text-xs text-gray-500">Customer <?= $h($sale['customer_name'] ?? '-') ?></div>
    </div>
    <div class="text-right">
      <div class="text-xs text-gray-500">Total</div>
      <div class="text-xl font-bold"><?= number_format((float)$sale['grand_total'],2) ?></div>
    </div>
  </div>

  <div class="mt-4 overflow-hidden border border-gray-200 rounded">
    <table class="w-full text-sm">
      <thead class="bg-gray-50 text-gray-600 uppercase text-xs">
        <tr>
          <th class="px-3 py-2 text-left">Item</th>
          <th class="px-3 py-2 text-right">Qty</th>
          <th class="px-3 py-2 text-right">Price</th>
          <th class="px-3 py-2 text-right">Line total</th>
        </tr>
      </thead>
      <tbody>
      <?php if (!empty($items)): foreach ($items as $it): ?>
        <tr class="border-t">
          <td class="px-3 py-2"><?= $h($it['name'] ?? '') ?></td>
          <td class="px-3 py-2 text-right"><?= number_format((float)$it['qty'], 4) ?></td>
          <td class="px-3 py-2 text-right"><?= number_format((float)$it['unit_price'], 2) ?></td>
          <td class="px-3 py-2 text-right"><?= number_format((float)$it['line_total'], 2) ?></td>
        </tr>
      <?php endforeach; else: ?>
        <tr><td colspan="4" class="px-3 py-4 text-center text-gray-500">No line items.</td></tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>