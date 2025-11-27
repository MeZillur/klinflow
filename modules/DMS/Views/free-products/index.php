<?php declare(strict_types=1);
if(!function_exists('h')){function h($v){return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');}}
$rows = $rows ?? [];
$base = $module_base ?? '';
?>
<div class="flex items-center justify-between mb-4">
  <h1 class="text-xl font-semibold">Free Products</h1>
  <div class="flex gap-2">
    <a href="<?= h($base.'/free-products/create') ?>" class="px-3 py-2 rounded-lg bg-emerald-600 text-white hover:bg-emerald-700">New</a>
    <a href="<?= h($base.'/free-products/receive') ?>" class="px-3 py-2 rounded-lg bg-slate-100 hover:bg-slate-200">Receive</a>
    <a href="<?= h($base.'/free-products/issue') ?>" class="px-3 py-2 rounded-lg bg-slate-100 hover:bg-slate-200">Issue</a>
  </div>
</div>

<div class="overflow-x-auto border rounded-xl">
  <table class="min-w-full text-sm">
    <thead class="bg-slate-50">
      <tr>
        <th class="px-3 py-2 text-left">Code</th>
        <th class="px-3 py-2 text-left">Name</th>
        <th class="px-3 py-2 text-left">Unit</th>
        <th class="px-3 py-2 text-right">Stock</th>
        <th class="px-3 py-2 text-right"></th>
      </tr>
    </thead>
    <tbody>
      <?php foreach($rows as $r): ?>
        <tr class="border-t">
          <td class="px-3 py-2"><?= h($r['code']) ?></td>
          <td class="px-3 py-2"><?= h($r['name']) ?></td>
          <td class="px-3 py-2"><?= h($r['unit']) ?></td>
          <td class="px-3 py-2 text-right"><?= number_format((float)$r['stock'],2) ?></td>
          <td class="px-3 py-2 text-right">
            <a class="text-emerald-700 hover:underline" href="<?= h($base.'/free-products/'.$r['id']) ?>">View</a>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if(!$rows): ?>
        <tr><td colspan="5" class="px-3 py-8 text-center text-slate-500">No free products yet.</td></tr>
      <?php endif; ?>
    </tbody>
  </table>
</div>