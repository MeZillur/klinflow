<div class="max-w-5xl mx-auto p-6">
  <h1 class="text-2xl font-bold mb-4">Bank Accounts</h1>
  <div class="overflow-x-auto bg-white rounded-xl border">
    <table class="min-w-full text-sm">
      <thead class="bg-gray-50"><tr>
        <th class="px-3 py-2 text-left">Code</th>
        <th class="px-3 py-2 text-left">Name</th>
        <th class="px-3 py-2 text-left">Type</th>
        <th class="px-3 py-2 text-left">Currency</th>
        <th class="px-3 py-2 text-right">Current Balance</th>
      </tr></thead>
      <tbody>
      <?php if (!$rows): ?>
        <tr><td colspan="5" class="px-3 py-6 text-center text-gray-500">No bank accounts</td></tr>
      <?php else: foreach($rows as $r): ?>
        <tr class="border-t">
          <td class="px-3 py-2"><?= htmlspecialchars($r['code']??'') ?></td>
          <td class="px-3 py-2"><?= htmlspecialchars($r['name']??'') ?></td>
          <td class="px-3 py-2"><?= htmlspecialchars($r['type']??'') ?></td>
          <td class="px-3 py-2"><?= htmlspecialchars($r['currency']??'') ?></td>
          <td class="px-3 py-2 text-right"><?= number_format(((int)($r['current_balance_cents']??0))/100,2) ?></td>
        </tr>
      <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>