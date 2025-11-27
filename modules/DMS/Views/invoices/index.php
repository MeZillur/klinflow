<?php
declare(strict_types=1);
/** @var array $rows */
$base = $module_base ?? '';
?>
<div class="flex items-center justify-between mb-4">
  <h1 class="text-xl font-semibold">Invoices</h1>
  <a href="<?= htmlspecialchars($base) ?>/invoices/create"
     class="px-3 py-2 rounded-lg bg-blue-600 text-white hover:bg-blue-700">New Invoice</a>
</div>

<div class="overflow-x-auto">
  <table class="min-w-full text-sm border rounded-lg overflow-hidden">
    <thead class="bg-slate-50">
      <tr>
        <th class="px-3 py-2 text-left">Date</th>
        <th class="px-3 py-2 text-left">No</th>
        <th class="px-3 py-2 text-left">Customer</th>
        <th class="px-3 py-2 text-right">Total</th>
        <th class="px-3 py-2 text-left">Status</th>
        <th class="px-3 py-2"></th>
      </tr>
    </thead>
    <tbody>
      <?php if (empty($rows)): ?>
        <tr><td colspan="6" class="px-3 py-6 text-center text-slate-500">No invoices yet.</td></tr>
      <?php else: foreach ($rows as $r): ?>
        <tr class="border-t">
          <td class="px-3 py-2"><?= htmlspecialchars(substr((string)$r['invoice_date'],0,10)) ?></td>
          <td class="px-3 py-2"><?= htmlspecialchars((string)$r['invoice_no']) ?></td>
          <td class="px-3 py-2"><?= htmlspecialchars((string)$r['customer_name']) ?></td>
          <td class="px-3 py-2 text-right"><?= number_format((float)$r['grand_total'],2) ?></td>
          <td class="px-3 py-2"><?= htmlspecialchars((string)$r['status']) ?></td>
          <td class="px-3 py-2 text-right">
            <a class="text-blue-600 hover:underline" href="<?= htmlspecialchars($base) ?>/invoices/<?= (int)$r['id'] ?>">View</a>
          </td>
        </tr>
      <?php endforeach; endif; ?>
    </tbody>
  </table>
</div>