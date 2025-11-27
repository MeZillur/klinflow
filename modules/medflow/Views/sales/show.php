<?php
declare(strict_types=1);
/** @var array $sale */
/** @var array $items */
$h = fn($s)=>htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
$brand = '#228B22';
?>
<div class="p-6 text-sm" style="font-family: ui-sans-serif, system-ui, -apple-system;">
  <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:1rem">
    <div>
      <div style="font-size:1.125rem;font-weight:700;display:flex;align-items:center;gap:.5rem">
        <i class="fa-solid fa-file-invoice" style="color:<?= $brand ?>"></i>
        Invoice <?= $h($sale['invoice_no']) ?>
      </div>
      <div style="color:#6b7280">Date: <?= $h(date('d M Y H:i', strtotime((string)($sale['sold_at'] ?: $sale['created_at'])))) ?></div>
    </div>
    <div style="text-align:right">
      <div style="font-weight:600">Total</div>
      <div style="font-size:1.25rem;font-weight:800"><?= number_format((float)$sale['grand_total'], 2) ?></div>
    </div>
  </div>

  <div style="margin:.5rem 0 1rem">
    <div style="font-weight:600">Customer</div>
    <div><?= $h($sale['customer_name'] ?: '-') ?></div>
  </div>

  <div style="border:1px solid #e5e7eb;border-radius:.5rem;overflow:hidden">
    <table style="width:100%;border-collapse:collapse">
      <thead style="background:#f9fafb;color:#6b7280">
        <tr>
          <th style="text-align:left;padding:.5rem .75rem">Item</th>
          <th style="text-align:right;padding:.5rem .75rem">Qty</th>
          <th style="text-align:right;padding:.5rem .75rem">Price</th>
          <th style="text-align:right;padding:.5rem .75rem">Line total</th>
        </tr>
      </thead>
      <tbody>
        <?php if ($items): foreach ($items as $it): ?>
          <tr style="border-top:1px solid #f3f4f6">
            <td style="padding:.5rem .75rem"><?= $h($it['name'] ?? '') ?></td>
            <td style="padding:.5rem .75rem;text-align:right"><?= $h($it['qty'] ?? '1') ?></td>
            <td style="padding:.5rem .75rem;text-align:right"><?= number_format((float)($it['unit_price'] ?? 0), 2) ?></td>
            <td style="padding:.5rem .75rem;text-align:right"><?= number_format((float)($it['line_total'] ?? 0), 2) ?></td>
          </tr>
        <?php endforeach; else: ?>
          <tr><td colspan="4" style="padding:1rem;color:#6b7280;text-align:center">No line items.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>

  <div style="display:flex;justify-content:flex-end;margin-top:1rem">
    <button onclick="window.print()" style="display:inline-flex;align-items:center;gap:.5rem;border:1px solid #e5e7eb;border-radius:.5rem;padding:.5rem .75rem">
      <i class="fa-solid fa-print" style="color:<?= $brand ?>"></i> Print
    </button>
  </div>
</div>