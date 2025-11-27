<?php $rows = $rows ?? []; ?>
<h2 class="text-xl font-semibold mb-4">Customer Credit Summary</h2>

<div class="overflow-x-auto">
  <table class="min-w-full text-sm border rounded-lg overflow-hidden">
    <thead class="bg-slate-50">
      <tr>
        <th class="px-3 py-2 text-left">Customer</th>
        <th class="px-3 py-2 text-right">Invoices</th>
        <th class="px-3 py-2 text-right">Payments</th>
        <th class="px-3 py-2 text-right">Adjustments</th>
        <th class="px-3 py-2 text-right">Net</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($rows as $r):
        $inv = (float)($r['invoices'] ?? 0);
        $pay = (float)($r['payments'] ?? 0);
        $adj = (float)($r['adjustments'] ?? 0);
        $net = $inv + $adj - $pay;
      ?>
      <tr class="border-t">
        <td class="px-3 py-2"><?= htmlspecialchars($r['name'] ?? '') ?></td>
        <td class="px-3 py-2 text-right"><?= number_format($inv,2) ?></td>
        <td class="px-3 py-2 text-right"><?= number_format($pay,2) ?></td>
        <td class="px-3 py-2 text-right"><?= number_format($adj,2) ?></td>
        <td class="px-3 py-2 text-right font-medium"><?= number_format($net,2) ?></td>
      </tr>
      <?php endforeach; if (empty($rows)): ?>
        <tr><td colspan="5" class="px-3 py-6 text-center text-slate-500">No data yet.</td></tr>
      <?php endif; ?>
    </tbody>
  </table>
</div>