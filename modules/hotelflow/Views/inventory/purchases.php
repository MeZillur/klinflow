<?php
$h=fn($v)=>htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');
$base=rtrim((string)($module_base ?? '/apps/hotelflow'),'/');
?>
<div class="max-w-[1100px] mx-auto space-y-4">
  <div class="flex items-center justify-between">
    <h1 class="text-2xl font-extrabold">Purchases</h1>
    <a class="px-3 py-2 rounded-lg border hover:bg-slate-50" href="<?= $h($base) ?>/inventory/purchases/create">New Purchase</a>
  </div>

  <?php if(!$rows && !empty($ddl)): ?>
    <div class="rounded-lg border border-amber-300 bg-amber-50 p-4 text-sm">
      <div class="font-semibold mb-1">Purchase tables missing.</div>
      <pre class="text-xs overflow-auto"><?= $h(implode("\n\n",$ddl)) ?></pre>
    </div>
  <?php endif; ?>

  <div class="rounded-xl border overflow-auto">
    <table class="min-w-[900px] w-full text-sm">
      <thead class="bg-slate-50"><tr>
        <th class="px-3 py-2 text-left"># / Ref</th><th class="px-3 py-2">Date</th><th class="px-3 py-2">Supplier</th><th class="px-3 py-2 text-right">Total</th><th class="px-3 py-2">Curr</th>
      </tr></thead>
      <tbody>
        <?php if(!$rows): ?><tr><td colspan="5" class="px-3 py-6 text-center text-slate-500">No purchases yet.</td></tr><?php endif; ?>
        <?php foreach($rows as $r): ?>
          <tr class="border-t">
            <td class="px-3 py-2">#<?= (int)$r['id'] ?> — <?= $h((string)$r['reference']) ?></td>
            <td class="px-3 py-2 text-center"><?= $h((string)$r['doc_date']) ?></td>
            <td class="px-3 py-2"><?= $h((string)$r['supplier']) ?></td>
            <td class="px-3 py-2 text-right"><?= number_format((float)($r['total']??0),2) ?></td>
            <td class="px-3 py-2 text-center"><?= $h((string)($r['currency']??'')) ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>