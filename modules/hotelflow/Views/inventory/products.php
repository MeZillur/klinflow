<?php
$h=fn($v)=>htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');
$base=rtrim((string)($module_base ?? '/apps/hotelflow'),'/');
?>
<div class="max-w-[1100px] mx-auto space-y-6">
  <div class="flex items-center justify-between">
    <h1 class="text-2xl font-extrabold">Products</h1>
    <form method="post" action="<?= $h($base) ?>/inventory/products/create" class="flex gap-2">
      <input name="sku"  placeholder="SKU"  class="px-3 py-2 rounded-lg border" />
      <input name="name" placeholder="Name" class="px-3 py-2 rounded-lg border" required/>
      <input name="unit" placeholder="Unit (pcs, kg…)" class="px-3 py-2 rounded-lg border" />
      <select name="category_id" class="px-3 py-2 rounded-lg border">
        <option value="">Category</option>
        <?php foreach($cats as $c): ?><option value="<?= (int)$c['id'] ?>"><?= $h((string)$c['name']) ?></option><?php endforeach; ?>
      </select>
      <button class="px-4 py-2 rounded-lg text-white" style="background:var(--brand)">Add</button>
    </form>
  </div>

  <?php if(!$rows && !empty($ddl)): ?>
    <div class="rounded-lg border border-amber-300 bg-amber-50 p-4 text-sm">
      <div class="font-semibold mb-1">Table hms_products not found.</div>
      <pre class="text-xs overflow-auto"><?= $h(implode("\n\n",$ddl)) ?></pre>
    </div>
  <?php endif; ?>

  <div class="overflow-auto rounded-xl border">
    <table class="min-w-[900px] w-full text-sm">
      <thead class="bg-slate-50">
        <tr><th class="px-3 py-2 text-left">SKU</th><th class="px-3 py-2 text-left">Name</th><th class="px-3 py-2">Unit</th><th class="px-3 py-2">Category</th></tr>
      </thead>
      <tbody>
        <?php if(!$rows): ?><tr><td colspan="4" class="px-3 py-6 text-center text-slate-500">No products.</td></tr><?php endif; ?>
        <?php foreach($rows as $r): ?>
          <tr class="border-t">
            <td class="px-3 py-2"><?= $h((string)$r['sku']) ?></td>
            <td class="px-3 py-2"><?= $h((string)$r['name']) ?></td>
            <td class="px-3 py-2 text-center"><?= $h((string)$r['unit']) ?></td>
            <td class="px-3 py-2 text-center"><?= (int)($r['category_id']??0) ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>