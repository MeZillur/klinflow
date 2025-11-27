<?php /** @var array $rows */ ?>
<h1 class="text-xl font-semibold mb-3">Sales Returns</h1>
<a class="btn btn-brand mb-3" href="./sales-returns/create">New Return</a>
<div class="space-y-2">
  <?php foreach ($rows as $r): ?>
    <div class="border rounded p-3 flex items-center justify-between">
      <div>
        <div class="font-medium"><?= htmlspecialchars($r['return_no'] ?: ('#'.$r['id'])) ?></div>
        <div class="text-sm text-gray-500"><?= htmlspecialchars($r['return_date']) ?> · <?= htmlspecialchars($r['status']) ?></div>
      </div>
      <div class="font-semibold"><?= number_format((float)$r['grand_total'],2) ?></div>
    </div>
  <?php endforeach; ?>
  <?php if (!$rows): ?>
    <div class="text-sm text-gray-500">No returns yet.</div>
  <?php endif; ?>
</div>