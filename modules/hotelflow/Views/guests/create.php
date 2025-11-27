<?php
/** @var string $module_base */
$h=fn($v)=>htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');
$base=rtrim((string)($module_base??'/apps/hotelflow'),'/');
?>
<div class="max-w-[800px] mx-auto space-y-4">
  <div class="flex items-center justify-between">
    <h1 class="text-2xl font-extrabold">Add Guest</h1>
    <a href="<?= $h($base) ?>/guests" class="px-3 py-2 rounded-lg border">Back</a>
  </div>

  <form method="post" action="<?= $h($base) ?>/guests" class="grid grid-cols-1 md:grid-cols-2 gap-4">
    <div>
      <label class="text-sm text-slate-600">Name</label>
      <input name="name" required class="mt-1 w-full px-3 py-2 rounded-lg border border-slate-300">
    </div>
    <div>
      <label class="text-sm text-slate-600">Father's Name</label>
      <input name="father_name" class="mt-1 w-full px-3 py-2 rounded-lg border border-slate-300">
    </div>
    <div>
      <label class="text-sm text-slate-600">Country</label>
      <input name="country" class="mt-1 w-full px-3 py-2 rounded-lg border border-slate-300">
    </div>
    <div>
      <label class="text-sm text-slate-600">Address</label>
      <input name="address" class="mt-1 w-full px-3 py-2 rounded-lg border border-slate-300">
    </div>
    <div>
      <label class="text-sm text-slate-600">Mobile</label>
      <input name="mobile" class="mt-1 w-full px-3 py-2 rounded-lg border border-slate-300">
    </div>
    <div>
      <label class="text-sm text-slate-600">Email</label>
      <input type="email" name="email" class="mt-1 w-full px-3 py-2 rounded-lg border border-slate-300">
    </div>

    <div class="md:col-span-2 flex justify-end gap-2 pt-2">
      <a href="<?= $h($base) ?>/guests" class="px-4 py-2 rounded-lg border">Cancel</a>
      <button class="px-4 py-2 rounded-lg text-white" style="background:var(--brand)">Save</button>
    </div>
  </form>
</div>