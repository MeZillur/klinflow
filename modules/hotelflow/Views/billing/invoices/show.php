<?php
/** @var array $invoice @var array $lines @var array $branding @var string $module_base */
$h=fn($v)=>htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');
$base=rtrim((string)($module_base ?? '/apps/hotelflow'),'/');
$active='invoices'; include __DIR__.'/../_tabs.php';
$invNo = (string)($invoice['invoice_no'] ?? ('#'.(int)($invoice['id'] ?? 0)));
?>
<div class="flex items-center justify-between mb-4">
  <h1 class="text-2xl font-extrabold">Invoice <?= $h($invNo) ?></h1>
  <div class="flex gap-2">
    <a href="<?= $h($base) ?>/billing/invoices/print/<?= (int)($invoice['id'] ?? 0) ?>" target="_blank"
       class="px-3 py-2 rounded-lg border hover:bg-slate-50">Print</a>
  </div>
</div>

<div class="rounded-xl border border-slate-200 overflow-hidden bg-white">
  <div class="p-4 border-b">
    <div class="flex items-center gap-3">
      <img src="<?= $h($branding['logo_path']) ?>" class="h-10 w-auto" alt="Logo">
      <div class="leading-tight">
        <div class="font-bold"><?= $h($branding['org_name']) ?></div>
        <div class="text-xs text-slate-600"><?= $h($branding['org_address']) ?></div>
        <div class="text-xs text-slate-600"><?= $h($branding['org_phone']) ?> • <?= $h($branding['org_web']) ?> • <?= $h($branding['org_email']) ?></div>
      </div>
    </div>
  </div>

  <div class="p-4">
    <div class="overflow-auto rounded-lg border border-slate-200">
      <table class="min-w-[700px] w-full text-sm">
        <thead class="bg-slate-50">
          <tr>
            <th class="px-3 py-2 text-left">Description</th>
            <th class="px-3 py-2 text-right">Qty</th>
            <th class="px-3 py-2 text-right">Unit</th>
            <th class="px-3 py-2 text-right">Tax</th>
            <th class="px-3 py-2 text-right">Line Total</th>
          </tr>
        </thead>
        <tbody>
          <?php $sub=0; $tax=0; foreach ($lines as $l):
            $qty=(float)($l['qty'] ?? 1); $unit=(float)($l['unit_price'] ?? 0); $tx=(float)($l['tax_amount'] ?? 0);
            $sub += $qty*$unit; $tax += $tx;
            ?>
            <tr class="border-t">
              <td class="px-3 py-2"><?= $h((string)($l['description'] ?? '')) ?></td>
              <td class="px-3 py-2 text-right"><?= number_format($qty,2) ?></td>
              <td class="px-3 py-2 text-right"><?= number_format($unit,2) ?></td>
              <td class="px-3 py-2 text-right"><?= number_format($tx,2) ?></td>
              <td class="px-3 py-2 text-right"><?= number_format($qty*$unit+$tx,2) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
        <tfoot>
          <tr class="border-t bg-slate-50">
            <td class="px-3 py-2 text-right font-medium" colspan="4">Subtotal</td>
            <td class="px-3 py-2 text-right font-medium"><?= number_format($sub,2) ?></td>
          </tr>
          <tr>
            <td class="px-3 py-2 text-right" colspan="4">Tax</td>
            <td class="px-3 py-2 text-right"><?= number_format($tax,2) ?></td>
          </tr>
          <tr class="border-t">
            <td class="px-3 py-2 text-right text-lg font-extrabold" colspan="4">Total</td>
            <td class="px-3 py-2 text-right text-lg font-extrabold"><?= number_format($sub+$tax,2) ?></td>
          </tr>
        </tfoot>
      </table>
    </div>

    <?php if (!empty($branding['invoice_footer'])): ?>
      <div class="mt-4 text-sm text-slate-600"><?= nl2br($h($branding['invoice_footer'])) ?></div>
    <?php endif; ?>
  </div>
</div>