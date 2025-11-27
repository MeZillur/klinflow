<?php
declare(strict_types=1);
$h = fn($v)=>htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
$bdt = fn($n)=> '৳'.number_format((float)$n, 2);
?>
<div class="max-w-7xl mx-auto space-y-5">
  <div class="flex items-center justify-between">
    <h1 class="text-2xl font-bold">Refunds</h1>
    <nav class="flex items-center gap-2">
      <a href="<?= $h($base) ?>/sales" class="px-3 py-2 rounded-md border" style="border-color:#228B22;color:#228B22">All Sales</a>
      <a href="<?= $h($base) ?>/sales/refunds" class="px-3 py-2 rounded-md text-white" style="background:#228B22">Refunds</a>
    </nav>
  </div>

  <p class="text-sm text-gray-500">Showing refunded/voided sales or negative totals.</p>

  <div class="overflow-x-auto bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-xl">
    <table class="min-w-full text-sm">
      <thead class="bg-gray-50 dark:bg-gray-800">
        <tr>
          <th class="px-3 py-2 text-left">Invoice</th>
          <th class="px-3 py-2 text-left">Customer</th>
          <th class="px-3 py-2 text-right">Amount</th>
          <th class="px-3 py-2 text-left">Status</th>
          <th class="px-3 py-2 text-left">Created</th>
          <th class="px-3 py-2 text-center">Action</th>
        </tr>
      </thead>
      <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
      <?php if (empty($rows)): ?>
        <tr><td colspan="6" class="px-3 py-8 text-center text-gray-500">No refunds found.</td></tr>
      <?php else: foreach ($rows as $r): ?>
        <tr>
          <td class="px-3 py-2"><?= $h($r['invoice_no'] ?? $r['sale_no'] ?? $r['code'] ?? '') ?></td>
          <td class="px-3 py-2"><?= $h($r['customer_name'] ?? $r['customer'] ?? '—') ?></td>
          <td class="px-3 py-2 text-right"><?= $bdt($r['total_amount'] ?? $r['grand_total'] ?? $r['total'] ?? 0) ?></td>
          <td class="px-3 py-2">
            <span class="px-2 py-1 rounded text-white" style="background:#B91C1C">
              <?= $h(strtolower((string)($r['status'] ?? $r['invoice_status'] ?? 'refunded'))) ?>
            </span>
          </td>
          <td class="px-3 py-2"><?= $h($r['created_at'] ?? $r['sale_date'] ?? '') ?></td>
          <td class="px-3 py-2 text-center">
            <a class="font-medium" style="color:#228B22" href="<?= $h($base) ?>/sales/<?= (int)($r['id'] ?? 0) ?>">Open</a>
          </td>
        </tr>
      <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>