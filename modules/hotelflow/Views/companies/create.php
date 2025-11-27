<?php $h=fn($v)=>htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8'); $base=rtrim((string)($module_base??'/apps/hotelflow'),'/'); ?>
<div class="max-w-[700px] mx-auto space-y-4">
  <div class="flex items-center justify-between">
    <h1 class="text-2xl font-extrabold">Add Company</h1>
    <a href="<?= $h($base) ?>/companies" class="px-3 py-2 rounded-lg border">Back</a>
  </div>
  <form method="post" action="<?= $h($base) ?>/companies" class="grid grid-cols-1 gap-3">
    <div>
      <label class="text-sm text-slate-600">Name</label>
      <input name="name" required class="mt-1 w-full px-3 py-2 rounded-lg border border-slate-300">
    </div>
    <div class="flex justify-end gap-2">
      <a href="<?= $h($base) ?>/companies" class="px-4 py-2 rounded-lg border">Cancel</a>
      <button class="px-4 py-2 rounded-lg text-white" style="background:var(--brand)">Save</button>
    </div>
  </form>
</div>