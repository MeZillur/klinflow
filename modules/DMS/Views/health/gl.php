<?php declare(strict_types=1);
$h = fn($v)=>htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
?>
<div class="max-w-6xl mx-auto p-4">
  <h1 class="text-2xl font-semibold mb-4" style="color:#228B22">Unbalanced Journals</h1>
  <div class="rounded-xl border overflow-x-auto">
    <table class="min-w-full text-sm">
      <thead class="bg-slate-50">
        <tr>
          <th class="p-2 text-left">Jno</th>
          <th class="p-2 text-left">Type</th>
          <th class="p-2 text-left">Posted</th>
          <th class="p-2 text-right">Dr</th>
          <th class="p-2 text-right">Cr</th>
          <th class="p-2 text-right">Imbalance</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach (($rows ?? []) as $r): ?>
        <tr class="border-t">
          <td class="p-2"><?= $h($r['jno'] ?? '') ?></td>
          <td class="p-2"><?= $h($r['jtype'] ?? '') ?></td>
          <td class="p-2"><?= $h($r['posted_at'] ?? '') ?></td>
          <td class="p-2 text-right"><?= number_format((float)($r['total_dr'] ?? 0),2) ?></td>
          <td class="p-2 text-right"><?= number_format((float)($r['total_cr'] ?? 0),2) ?></td>
          <td class="p-2 text-right font-semibold text-rose-600">
            <?= number_format((float)($r['imbalance'] ?? 0),2) ?>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if (empty($rows)): ?>
        <tr><td colspan="6" class="p-3 text-slate-500">All good — no imbalances.</td></tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
  <div class="mt-4">
    <a href="<?= $h(($module_base ?? '/apps/dms').'/reports/health') ?>" class="text-sm underline text-slate-600 hover:text-slate-900">← Back to Health</a>
  </div>
</div>
