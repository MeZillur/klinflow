<?php
declare(strict_types=1);
/** @var array $rows */
/** @var array $ctx */
/** @var array $org */
/** @var string $base */

$h = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
?>
<div class="max-w-5xl mx-auto px-4 py-6">
  <h1 class="text-2xl font-bold text-green-800 mb-4">🧱 Moulding Entries</h1>

  <form method="post" action="<?= $h($base) ?>/production/moulding"
        class="bg-white rounded-2xl shadow p-4 mb-6 border border-gray-200">
    <div class="grid grid-cols-1 sm:grid-cols-4 gap-4">
      <div>
        <label class="block text-sm font-medium text-gray-600 mb-1">Date</label>
        <input type="date" name="entry_date" value="<?= date('Y-m-d') ?>"
               class="w-full border-gray-300 rounded-lg focus:ring-green-500 focus:border-green-500">
      </div>
      <div>
        <label class="block text-sm font-medium text-gray-600 mb-1">Gang / Team</label>
        <input type="text" name="gang" placeholder="Team name"
               class="w-full border-gray-300 rounded-lg focus:ring-green-500 focus:border-green-500">
      </div>
      <div>
        <label class="block text-sm font-medium text-gray-600 mb-1">Qty (Green Bricks)</label>
        <input type="number" name="qty_green" min="0"
               class="w-full border-gray-300 rounded-lg focus:ring-green-500 focus:border-green-500">
      </div>
      <div>
        <label class="block text-sm font-medium text-gray-600 mb-1">Remarks</label>
        <input type="text" name="remarks" placeholder="Optional"
               class="w-full border-gray-300 rounded-lg focus:ring-green-500 focus:border-green-500">
      </div>
    </div>
    <div class="mt-4 text-right">
      <button type="submit"
              class="bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700">
        Save Entry
      </button>
    </div>
  </form>

  <div class="bg-white rounded-2xl shadow border border-gray-200 overflow-hidden">
    <table class="min-w-full text-sm">
      <thead class="bg-green-100 text-green-900">
        <tr>
          <th class="px-3 py-2 text-left">Date</th>
          <th class="px-3 py-2 text-left">Gang</th>
          <th class="px-3 py-2 text-right">Qty</th>
          <th class="px-3 py-2 text-left">Remarks</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($rows)): ?>
          <tr><td colspan="4" class="px-3 py-3 text-gray-500 text-center">No entries found</td></tr>
        <?php else: foreach ($rows as $r): ?>
          <tr class="border-t border-gray-100 hover:bg-gray-50">
            <td class="px-3 py-2"><?= $h($r['entry_date']) ?></td>
            <td class="px-3 py-2"><?= $h($r['gang']) ?></td>
            <td class="px-3 py-2 text-right"><?= number_format((float)$r['qty_green']) ?></td>
            <td class="px-3 py-2"><?= $h($r['remarks']) ?></td>
          </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>