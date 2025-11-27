<?php
$h=fn($v)=>htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');
?>
<div class="max-w-[900px] mx-auto space-y-4">
  <h1 class="text-2xl font-extrabold">Stock</h1>

  <?php if(!$rows && !empty($ddl)): ?>
    <div class="rounded-lg border border-amber-300 bg-amber-50 p-4 text-sm">
      <div class="font-semibold mb-1">Stock movements table not found.</div>
      <pre class="text-xs overflow-auto"><?= $h(implode("\n\n",$ddl)) ?></pre>
    </div>
  <?php endif; ?>

  <div class="rounded-xl border overflow-auto">
    <table class="min-w-[700px] w-full text-sm">
      <thead class="bg-slate-50"><tr><th class="px-3 py-2 text-left">SKU</th><th class="px-3 py-2 text-left">Product</th><th class="px-3 py-2">Unit</th><th class="px-3 py-2 text-right">On hand</th></tr></thead>
      <tbody>
        <?php if(!$rows): ?><tr><td colspan="4" class="px-3 py-6 text-center text-slate-500">No stock data.</td></tr><?php endif; ?>
        <?php foreach($rows as $r): ?>
          <tr class="border-t">
            <td class="px-3 py-2"><?= $h((string)$r['sku']) ?></td>
            <td class="px-3 py-2"><?= $h((string)$r['name']) ?></td>
            <td class="px-3 py-2 text-center"><?= $h((string)$r['unit']) ?></td>
            <td class="px-3 py-2 text-right"><?= number_format((float)($r['on_hand']??0),3) ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>