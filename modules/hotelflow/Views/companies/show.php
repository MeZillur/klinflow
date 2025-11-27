<?php $h=fn($v)=>htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8'); $base=rtrim((string)($module_base??'/apps/hotelflow'),'/'); ?>
<div class="max-w-[800px] mx-auto space-y-4">
  <div class="flex items-center justify-between">
    <h1 class="text-2xl font-extrabold"><?= $h((string)($c['name'] ?? 'Company')) ?></h1>
    <a href="<?= $h($base) ?>/companies" class="px-3 py-2 rounded-lg border">Back</a>
  </div>
  <div class="rounded-xl border p-4 text-sm text-slate-600">Details placeholder</div>
</div>