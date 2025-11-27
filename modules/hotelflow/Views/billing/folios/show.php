<?php
/** @var array $folio @var array $lines @var array $branding @var string $module_base */
$h=fn($v)=>htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');
$base=rtrim((string)($module_base ?? '/apps/hotelflow'),'/');
$active='folios'; include __DIR__.'/../_tabs.php';
?>
<div class="flex items-center justify-between mb-4">
  <h1 class="text-2xl font-extrabold">Folio #<?= (int)$folio['id'] ?></h1>
  <div class="flex gap-2">
    <a href="<?= $h($base) ?>/billing/invoices/print/<?= (int)($folio['invoice_id'] ?? $folio['id']) ?>" target="_blank"
       class="px-3 py-2 rounded-lg border hover:bg-slate-50">Print</a>
  </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
  <div class="lg:col-span-2 overflow-auto rounded-xl border border-slate-200">
    <table class="min-w-[700px] w-full text-sm">
      <thead class="bg-slate-50">
        <tr>
          <th class="px-3 py-2 text-left">Date</th>
          <th class="px-3 py-2 text-left">Code</th>
          <th class="px-3 py-2 text-left">Description</th>
          <th class="px-3 py-2 text-right">Amount</th>
          <th class="px-3 py-2 text-right">Tax</th>
        </tr>
      </thead>
      <tbody>
        <?php if (!$lines): ?>
          <tr><td colspan="5" class="px-3 py-6 text-center text-slate-500">No lines.</td></tr>
        <?php endif; foreach ($lines as $l): ?>
          <tr class="border-t">
            <td class="px-3 py-2"><?= $h((string)($l['post_date'] ?? '')) ?></td>
            <td class="px-3 py-2"><?= $h((string)($l['code'] ?? '')) ?></td>
            <td class="px-3 py-2"><?= $h((string)($l['description'] ?? '')) ?></td>
            <td class="px-3 py-2 text-right"><?= number_format((float)($l['amount'] ?? 0),2) ?></td>
            <td class="px-3 py-2 text-right"><?= number_format((float)($l['tax_amount'] ?? 0),2) ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <div class="rounded-xl border border-slate-200 p-4">
    <div class="font-semibold mb-2">Summary</div>
    <?php
      $total = 0; $tax=0;
      foreach ($lines as $l) { $total += (float)($l['amount'] ?? 0); $tax += (float)($l['tax_amount'] ?? 0); }
    ?>
    <div class="flex justify-between py-1"><span>Subtotal</span><span><?= number_format($total,2) ?></span></div>
    <div class="flex justify-between py-1"><span>Tax</span><span><?= number_format($tax,2) ?></span></div>
    <div class="border-t my-2"></div>
    <div class="flex justify-between font-semibold"><span>Total</span><span><?= number_format($total+$tax,2) ?></span></div>
  </div>
</div>