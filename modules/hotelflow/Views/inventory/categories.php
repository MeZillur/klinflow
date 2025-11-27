<?php
$h=fn($v)=>htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');
$base=rtrim((string)($module_base ?? '/apps/hotelflow'),'/');
?>
<div class="max-w-[800px] mx-auto space-y-6">
  <div class="flex items-center justify-between">
    <h1 class="text-2xl font-extrabold">Categories</h1>
    <form method="post" action="<?= $h($base) ?>/inventory/categories/create" class="flex gap-2">
      <input name="name" placeholder="Category name" required class="px-3 py-2 rounded-lg border"/>
      <input name="parent_id" type="number" min="0" placeholder="Parent ID" class="px-3 py-2 rounded-lg border"/>
      <button class="px-4 py-2 rounded-lg text-white" style="background:var(--brand)">Add</button>
    </form>
  </div>

  <?php if(!$rows && !empty($ddl)): ?>
    <div class="rounded-lg border border-amber-300 bg-amber-50 p-4 text-sm">
      <div class="font-semibold mb-1">Table hms_categories not found.</div>
      <pre class="text-xs overflow-auto"><?= $h(implode("\n\n",$ddl)) ?></pre>
    </div>
  <?php endif; ?>

  <div class="rounded-xl border overflow-auto">
    <table class="min-w-[600px] w-full text-sm">
      <thead class="bg-slate-50"><tr><th class="px-3 py-2 text-left">ID</th><th class="px-3 py-2 text-left">Name</th><th class="px-3 py-2">Parent</th></tr></thead>
      <tbody>
        <?php if(!$rows): ?><tr><td colspan="3" class="px-3 py-6 text-center text-slate-500">No categories.</td></tr><?php endif; ?>
        <?php foreach($rows as $r): ?>
          <tr class="border-t"><td class="px-3 py-2"><?= (int)$r['id'] ?></td><td class="px-3 py-2"><?= $h((string)$r['name']) ?></td><td class="px-3 py-2 text-center"><?= (int)($r['parent_id']??0) ?></td></tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>