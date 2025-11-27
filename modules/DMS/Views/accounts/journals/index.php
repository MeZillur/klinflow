<?php
/** @var array $rows */
/** @var array $filters */
/** @var string $module_base */
$base = $module_base ?? '/apps/dms';
$brand = '#0a936b'; // your Apply/brand green
?>
<div class="container mx-auto max-w-6xl px-4 py-6">
  <div class="flex items-center justify-between mb-4">
    <h1 class="text-2xl font-semibold">Journals</h1>
    <div class="flex gap-2">
      <form method="get" class="flex items-center gap-2">
        <input type="date" name="from" value="<?= htmlspecialchars($filters['from'] ?? '') ?>" class="border rounded px-2 py-1">
        <input type="date" name="to"   value="<?= htmlspecialchars($filters['to']   ?? '') ?>" class="border rounded px-2 py-1">
        <input type="text"  name="q"   value="<?= htmlspecialchars($filters['q']    ?? '') ?>" placeholder="Search…" class="border rounded px-2 py-1">
        <button class="px-3 py-1 rounded text-white" style="background:<?= $brand ?>;"><i class="fa fa-filter me-1"></i>Apply</button>
      </form>
      <button onclick="window.print()" class="px-3 py-1 rounded border"><i class="fa fa-print me-1"></i>Print</button>
    </div>
  </div>

  <div class="overflow-auto rounded border">
    <table class="min-w-full text-sm">
      <thead class="bg-gray-50 dark:bg-gray-800">
        <tr>
          <th class="px-3 py-2 text-left">Date</th>
          <th class="px-3 py-2 text-left">No</th>
          <th class="px-3 py-2 text-left">Type</th>
          <th class="px-3 py-2 text-left">Memo</th>
          <th class="px-3 py-2 text-right">Debit</th>
          <th class="px-3 py-2 text-right">Credit</th>
          <th class="px-3 py-2"></th>
        </tr>
      </thead>
      <tbody>
      <?php if (empty($rows)): ?>
        <tr><td colspan="7" class="px-3 py-6 text-center text-gray-500">No journals found.</td></tr>
      <?php else: foreach ($rows as $r): ?>
        <tr class="border-t">
          <td class="px-3 py-2"><?= htmlspecialchars($r['jdate']) ?></td>
          <td class="px-3 py-2 font-medium"><?= htmlspecialchars($r['jno']) ?></td>
          <td class="px-3 py-2"><?= htmlspecialchars($r['jtype']) ?></td>
          <td class="px-3 py-2"><?= htmlspecialchars($r['memo'] ?? '') ?></td>
          <td class="px-3 py-2 text-right"><?= number_format((float)$r['t_debit'], 2) ?></td>
          <td class="px-3 py-2 text-right"><?= number_format((float)$r['t_credit'], 2) ?></td>
          <td class="px-3 py-2 text-right">
            <a class="inline-flex items-center px-2 py-1 rounded text-white" style="background:<?= $brand ?>;"
               href="<?= htmlspecialchars($base.'/accounts/journals/'.$r['id']) ?>">
               <i class="fa fa-eye me-1"></i> View
            </a>
          </td>
        </tr>
      <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>