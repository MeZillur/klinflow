<?php
/** @var array $rows @var string $module_base */
$h=fn($v)=>htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');
$base=rtrim((string)($module_base??'/apps/hotelflow'),'/');
?>
<div class="max-w-[900px] mx-auto">
  <div class="flex items-center justify-between mb-4">
    <h1 class="text-2xl font-extrabold">Companies</h1>
    <a href="<?= $h($base) ?>/companies/create" class="px-3 py-2 rounded-lg text-white" style="background:var(--brand)">Add Company</a>
  </div>
  <div class="rounded-xl border overflow-hidden">
    <table class="min-w-full text-sm">
      <thead class="bg-slate-50"><tr><th class="px-3 py-2 text-left">Name</th><th class="px-3 py-2"></th></tr></thead>
      <tbody>
        <?php if (!$rows): ?><tr><td colspan="2" class="px-3 py-6 text-center text-slate-500">No companies.</td></tr><?php endif; ?>
        <?php foreach($rows as $r): ?>
          <tr class="border-t">
            <td class="px-3 py-2"><?= $h((string)$r['name']) ?></td>
            <td class="px-3 py-2 text-right"><a href="<?= $h($base) ?>/companies/<?= (int)$r['id'] ?>" class="text-emerald-700 hover:underline">Open</a></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>