<?php declare(strict_types=1);
if(!function_exists('h')){function h($v){return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');}}
$p = $p ?? []; $rows = $rows ?? []; $base = $module_base ?? '';
?>
<div class="flex items-center justify-between mb-4">
  <h1 class="text-xl font-semibold">Movements · <?= h($p['name'] ?? '') ?></h1>
  <a class="text-emerald-700 hover:underline" href="<?= h($base.'/free-products/'.$p['id']) ?>">Back</a>
</div>

<div class="overflow-x-auto border rounded-xl">
  <table class="min-w-full text-sm">
    <thead class="bg-slate-50">
      <tr>
        <th class="px-3 py-2 text-left">Type</th>
        <th class="px-3 py-2 text-right">Qty</th>
        <th class="px-3 py-2 text-left">Ref</th>
        <th class="px-3 py-2 text-left">Note</th>
        <th class="px-3 py-2 text-left">Date</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($rows as $r): ?>
        <tr class="border-t">
          <td class="px-3 py-2"><?= h(ucfirst((string)$r['type'])) ?></td>
          <td class="px-3 py-2 text-right"><?= number_format((float)$r['qty'],2) ?></td>
          <td class="px-3 py-2"><?= h($r['ref_no'] ?? '') ?></td>
          <td class="px-3 py-2"><?= h($r['note'] ?? '') ?></td>
          <td class="px-3 py-2"><?= h(substr((string)$r['created_at'],0,19)) ?></td>
        </tr>
      <?php endforeach; ?>
      <?php if(!$rows): ?>
        <tr><td colspan="5" class="px-3 py-8 text-center text-slate-500">No movements yet.</td></tr>
      <?php endif; ?>
    </tbody>
  </table>
</div>