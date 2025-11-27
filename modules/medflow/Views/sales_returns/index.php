<?php
declare(strict_types=1);
$h = fn($v)=>htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
?>
<div class="max-w-5xl mx-auto p-4">
  <div class="flex items-center justify-between mb-4">
    <h1 class="text-2xl font-semibold">Sales Returns</h1>
    <a class="inline-flex items-center gap-2 px-3 py-2 rounded-lg text-white" style="background:#10b981"
       href="<?= $h(($module_base ?? '').'/sales/returns/create') ?>">
      <i class="fa fa-plus"></i><span>Start Return</span>
    </a>
  </div>

  <div class="bg-white dark:bg-gray-800 border dark:border-gray-700 rounded-xl overflow-hidden">
    <table class="w-full text-sm">
      <thead class="bg-gray-50 dark:bg-gray-700/40 text-gray-600 dark:text-gray-300">
        <tr>
          <th class="p-2 text-left">ID</th>
          <th class="p-2 text-left">Sale</th>
          <th class="p-2 text-left">Status</th>
          <th class="p-2 text-right">Items</th>
          <th class="p-2 text-right">Refund</th>
          <th class="p-2 text-left">Created</th>
          <th class="p-2"></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach (($rows ?? []) as $r): ?>
          <tr class="border-t dark:border-gray-700">
            <td class="p-2">#<?= (int)$r['id'] ?></td>
            <td class="p-2">Sale #<?= (int)$r['sale_id'] ?></td>
            <td class="p-2"><span class="px-2 py-0.5 rounded-full text-xs bg-emerald-100 text-emerald-700"><?= $h((string)$r['status']) ?></span></td>
            <td class="p-2 text-right"><?= (int)($r['item_count'] ?? 0) ?></td>
            <td class="p-2 text-right"><?= number_format((float)($r['total_refund'] ?? 0),2) ?></td>
            <td class="p-2"><?= $h((string)($r['created_at'] ?? '')) ?></td>
            <td class="p-2 text-right">
              <a class="text-emerald-700 hover:underline" href="<?= $h(($module_base ?? '').'/sales/returns/'.(int)$r['id']) ?>">Open</a>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if (empty($rows)): ?>
          <tr><td class="p-4 text-center text-gray-500" colspan="7">No returns yet.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>