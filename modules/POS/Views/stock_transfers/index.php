<?php
declare(strict_types=1);
/** @var array $rows @var array $branches @var string $base */
$h = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');

$branchNames = [];
foreach ($branches as $b) {
    $branchNames[(int)$b['id']] = (string)($b['name'] ?? ('Branch #'.$b['id']));
}
?>
<div class="max-w-5xl mx-auto py-4">
  <div class="flex items-center justify-between mb-4">
    <div>
      <h1 class="text-2xl font-bold tracking-tight">Stock Transfers</h1>
      <p class="text-sm text-gray-500">Movement of stock between branches.</p>
    </div>
    <a href="<?= $h($base) ?>/stock-transfers/create"
       class="inline-flex items-center px-4 py-2 rounded-lg bg-emerald-600 text-white text-sm font-semibold">
      + New Transfer
    </a>
  </div>

  <div class="bg-white border border-gray-200 rounded-2xl overflow-hidden">
    <table class="min-w-full text-sm">
      <thead class="bg-gray-900 text-gray-100">
        <tr>
          <th class="px-3 py-2 text-left">ID</th>
          <th class="px-3 py-2 text-left">Date</th>
          <th class="px-3 py-2 text-left">From</th>
          <th class="px-3 py-2 text-left">To</th>
          <th class="px-3 py-2 text-left">Reference</th>
          <th class="px-3 py-2 text-right">Items</th>
        </tr>
      </thead>
      <tbody>
      <?php if (!$rows): ?>
        <tr>
          <td colspan="6" class="px-3 py-6 text-center text-gray-400">
            No transfers recorded yet.
          </td>
        </tr>
      <?php else: ?>
        <?php foreach ($rows as $r): ?>
          <?php
            $fromId = (int)($r['from_branch_id'] ?? 0);
            $toId   = (int)($r['to_branch_id'] ?? 0);
            $items  = $r['total_items'] ?? '';
            $date   = $r['transfer_date'] ?? ($r['date'] ?? ($r['created_at'] ?? ''));
            $ref    = $r['reference'] ?? ($r['ref_no'] ?? '');
          ?>
          <tr class="border-t border-gray-100">
            <td class="px-3 py-2"><?= (int)$r['id'] ?></td>
            <td class="px-3 py-2"><?= $h($date) ?></td>
            <td class="px-3 py-2">
              <?= $h($branchNames[$fromId] ?? ('#'.$fromId)) ?>
            </td>
            <td class="px-3 py-2">
              <?= $h($branchNames[$toId] ?? ('#'.$toId)) ?>
            </td>
            <td class="px-3 py-2"><?= $h($ref) ?></td>
            <td class="px-3 py-2 text-right"><?= $h($items) ?></td>
          </tr>
        <?php endforeach; ?>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>