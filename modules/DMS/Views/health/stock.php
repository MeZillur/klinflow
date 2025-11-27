<?php declare(strict_types=1);
$h = fn($v)=>htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
?>
<div class="max-w-6xl mx-auto p-4">
  <h1 class="text-2xl font-semibold mb-4" style="color:#228B22">Negative Stock Items</h1>
  <div class="rounded-xl border overflow-x-auto">
    <table class="min-w-full text-sm">
      <thead class="bg-slate-50">
        <tr>
          <th class="p-2 text-left">Product ID</th>
          <th class="p-2 text-right">On Hand</th>
          <th class="p-2 text-left">Last Moved</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach (($rows ?? []) as $r): ?>
        <tr class="border-t">
          <td class="p-2"><?= (int)($r['product_id'] ?? 0) ?></td>
          <td class="p-2 text-right font-semibold text-rose-600"><?= number_format((float)($r['on_hand'] ?? 0),2) ?></td>
          <td class="p-2"><?= $h($r['last_moved_at'] ?? '') ?></td>
        </tr>
      <?php endforeach; ?>
      <?php if (empty($rows)): ?>
        <tr><td colspan="3" class="p-3 text-slate-500">All good — no negatives.</td></tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
  <div class="mt-4">
    <a href="<?= $h(($module_base ?? '/apps/dms').'/reports/health') ?>" class="text-sm underline text-slate-600 hover:text-slate-900">← Back to Health</a>
  </div>
</div>
