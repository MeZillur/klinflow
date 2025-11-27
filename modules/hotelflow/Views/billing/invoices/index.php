<?php
/** @var array $rows @var string $module_base */
$h=fn($v)=>htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');
$base=rtrim((string)($module_base ?? '/apps/hotelflow'),'/');
$active='invoices'; include __DIR__.'/../_tabs.php';
?>
<div class="flex items-center justify-between mb-3">
  <h1 class="text-2xl font-extrabold">Invoices</h1>
</div>

<div class="overflow-auto rounded-xl border border-slate-200">
  <table class="min-w-[900px] w-full text-sm">
    <thead class="bg-slate-50">
      <tr>
        <th class="px-3 py-2 text-left">Invoice</th>
        <th class="px-3 py-2">Issued</th>
        <th class="px-3 py-2 text-right">Total</th>
        <th class="px-3 py-2">Currency</th>
        <th class="px-3 py-2">Status</th>
        <th class="px-3 py-2"></th>
      </tr>
    </thead>
    <tbody>
      <?php if (!$rows): ?>
        <tr><td colspan="6" class="px-3 py-6 text-center text-slate-500">No invoices found.</td></tr>
      <?php endif; foreach ($rows as $r): ?>
        <tr class="border-t">
          <td class="px-3 py-2 font-medium"><?= $h((string)$r['invoice_no']) ?></td>
          <td class="px-3 py-2 text-center"><?= $h((string)($r['issued_at'] ?? '')) ?></td>
          <td class="px-3 py-2 text-right"><?= number_format((float)($r['total'] ?? 0),2) ?></td>
          <td class="px-3 py-2 text-center"><?= $h((string)($r['currency'] ?? '')) ?></td>
          <td class="px-3 py-2"><?= $h((string)($r['status'] ?? '')) ?></td>
          <td class="px-3 py-2 text-right">
            <a class="px-3 py-1.5 rounded-lg border hover:bg-slate-50" href="<?= $h($base) ?>/billing/invoices/<?= (int)$r['id'] ?>">Open</a>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>