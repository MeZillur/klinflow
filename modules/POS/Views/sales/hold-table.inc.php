<?php
// expects $rows, $base
$h = fn($v)=>htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
?>
<div class="overflow-x-auto bg-white rounded-xl border">
  <table class="min-w-full text-sm">
    <thead class="bg-gray-50">
      <tr>
        <th class="px-3 py-2 text-left">Invoice</th>
        <th class="px-3 py-2 text-left">Customer</th>
        <th class="px-3 py-2 text-right">Amount</th>
        <th class="px-3 py-2 text-left">Created</th>
        <th class="px-3 py-2">Action</th>
      </tr>
    </thead>
    <tbody>
    <?php if (!$rows): ?>
      <tr><td colspan="5" class="px-3 py-6 text-center text-gray-500">No parked sales.</td></tr>
    <?php else: foreach ($rows as $r): ?>
      <tr class="border-t">
        <td class="px-3 py-2"><?= $h($r['invoice_no'] ?? '') ?></td>
        <td class="px-3 py-2"><?= $h($r['customer_name'] ?? '') ?></td>
        <td class="px-3 py-2 text-right"><?= number_format((float)($r['total_amount'] ?? 0), 2) ?></td>
        <td class="px-3 py-2"><?= $h($r['created_at'] ?? '') ?></td>
        <td class="px-3 py-2 text-center">
          <a class="text-emerald-700 font-medium" href="<?= $h($base) ?>/sales/<?= (int)$r['id'] ?>">Open</a>
        </td>
      </tr>
    <?php endforeach; endif; ?>
    </tbody>
  </table>
</div>