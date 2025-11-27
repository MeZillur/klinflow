<div class="max-w-6xl mx-auto p-6">
  <div class="flex items-center justify-between mb-4">
    <h1 class="text-2xl font-bold">Payments</h1>
    <form method="post" action="<?= $base ?>/accounts/payments" class="flex items-end gap-2">
      <div>
        <label class="text-sm block">Ref Type</label>
        <select name="ref_type" class="border rounded p-2">
          <option value="sale">Sale</option><option value="purchase">Purchase</option>
        </select>
      </div>
      <input name="ref_id" type="number" placeholder="Ref ID" class="border rounded p-2">
      <input name="method" placeholder="Method" class="border rounded p-2" value="Cash">
      <input name="amount" type="number" step="0.01" placeholder="Amount" class="border rounded p-2">
      <input name="payment_date" type="date" class="border rounded p-2" value="<?= date('Y-m-d') ?>">
      <button class="px-4 py-2 rounded bg-emerald-600 text-white">Add</button>
    </form>
  </div>

  <div class="overflow-x-auto bg-white rounded-xl border">
    <table class="min-w-full text-sm">
      <thead class="bg-gray-50"><tr>
        <th class="px-3 py-2">ID</th><th class="px-3 py-2">Type</th><th class="px-3 py-2">Ref</th>
        <th class="px-3 py-2">Method</th><th class="px-3 py-2 text-right">Amount</th><th class="px-3 py-2">Date</th>
      </tr></thead>
      <tbody>
      <?php if (!$rows): ?>
        <tr><td colspan="6" class="px-3 py-6 text-center text-gray-500">No payments</td></tr>
      <?php else: foreach ($rows as $r): ?>
        <tr class="border-t">
          <td class="px-3 py-2"><?= (int)$r['id'] ?></td>
          <td class="px-3 py-2"><?= htmlspecialchars($r['ref_type'] ?? '') ?></td>
          <td class="px-3 py-2"><?= (int)($r['ref_id'] ?? 0) ?></td>
          <td class="px-3 py-2"><?= htmlspecialchars($r['method'] ?? '') ?></td>
          <td class="px-3 py-2 text-right"><?= number_format((float)$r['amount'],2) ?></td>
          <td class="px-3 py-2"><?= htmlspecialchars($r['payment_date'] ?? '') ?></td>
        </tr>
      <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>