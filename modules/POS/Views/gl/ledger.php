<div class="max-w-6xl mx-auto p-6">
  <h1 class="text-2xl font-bold mb-4">Ledger</h1>
  <div class="overflow-x-auto bg-white rounded-xl border">
    <table class="min-w-full text-sm">
      <thead class="bg-gray-50"><tr>
        <th class="px-3 py-2">ID</th><th class="px-3 py-2">Account</th>
        <th class="px-3 py-2 text-right">Debit</th><th class="px-3 py-2 text-right">Credit</th>
        <th class="px-3 py-2">Memo</th><th class="px-3 py-2">Created</th>
      </tr></thead>
      <tbody>
      <?php if (!$rows): ?>
        <tr><td colspan="6" class="px-3 py-6 text-center text-gray-500">No lines</td></tr>
      <?php else: foreach ($rows as $r): ?>
        <tr class="border-t">
          <td class="px-3 py-2"><?= (int)$r['id'] ?></td>
          <td class="px-3 py-2"><?= (int)$r['account_id'] ?></td>
          <td class="px-3 py-2 text-right"><?= number_format((float)$r['debit'],2) ?></td>
          <td class="px-3 py-2 text-right"><?= number_format((float)$r['credit'],2) ?></td>
          <td class="px-3 py-2"><?= htmlspecialchars($r['memo'] ?? '') ?></td>
          <td class="px-3 py-2"><?= htmlspecialchars($r['created_at'] ?? '') ?></td>
        </tr>
      <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>