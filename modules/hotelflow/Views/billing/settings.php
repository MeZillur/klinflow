<?php
/** @var array $branding @var string $module_base */
$h = fn($v)=>htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
$base = rtrim((string)($module_base ?? '/apps/hotelflow'), '/');
$active = 'settings';
include __DIR__ . '/_tabs.php';
?>
<div class="max-w-[800px] mx-auto space-y-6">
  <h1 class="text-2xl font-extrabold">Billing Settings</h1>

  <form method="post" action="<?= $h($base) ?>/billing/settings" enctype="multipart/form-data" class="grid grid-cols-1 md:grid-cols-2 gap-4">
    <div class="md:col-span-2">
      <label class="text-sm text-slate-600">Invoice Logo</label>
      <div class="mt-1 flex items-center gap-3">
        <img src="<?= $h($branding['logo_path']) ?>" alt="Logo" class="h-12 w-auto border rounded p-1 bg-white">
        <input type="file" name="logo_file" accept=".png,.jpg,.jpeg,.svg">
        <input type="hidden" name="logo_path" value="<?= $h($branding['logo_path']) ?>">
      </div>
    </div>

    <div>
      <label class="text-sm text-slate-600">Organization Name</label>
      <input name="org_name" value="<?= $h($branding['org_name']) ?>" class="mt-1 w-full px-3 py-2 rounded-lg border border-slate-300">
    </div>
    <div>
      <label class="text-sm text-slate-600">Phone</label>
      <input name="org_phone" value="<?= $h($branding['org_phone']) ?>" class="mt-1 w-full px-3 py-2 rounded-lg border border-slate-300">
    </div>
    <div class="md:col-span-2">
      <label class="text-sm text-slate-600">Address</label>
      <input name="org_address" value="<?= $h($branding['org_address']) ?>" class="mt-1 w-full px-3 py-2 rounded-lg border border-slate-300">
    </div>

    <div>
      <label class="text-sm text-slate-600">Website</label>
      <input name="org_web" value="<?= $h($branding['org_web']) ?>" class="mt-1 w-full px-3 py-2 rounded-lg border border-slate-300">
    </div>
    <div>
      <label class="text-sm text-slate-600">Email</label>
      <input name="org_email" value="<?= $h($branding['org_email']) ?>" class="mt-1 w-full px-3 py-2 rounded-lg border border-slate-300">
    </div>

    <div class="md:col-span-2">
      <label class="text-sm text-slate-600">Invoice Footer</label>
      <textarea name="invoice_footer" rows="3" class="mt-1 w-full px-3 py-2 rounded-lg border border-slate-300"><?= $h($branding['invoice_footer']) ?></textarea>
    </div>

    <div>
      <label class="text-sm text-slate-600">Default Print Size</label>
      <select name="print_size" class="mt-1 w-full px-3 py-2 rounded-lg border border-slate-300">
        <?php foreach (['A4','A5','POS'] as $sz): ?>
          <option value="<?= $sz ?>" <?= ($branding['print_size']===$sz?'selected':'') ?>><?= $sz ?></option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="md:col-span-2 flex justify-end gap-2 pt-2">
      <a href="<?= $h($base) ?>/billing/folios" class="px-4 py-2 rounded-lg border">Cancel</a>
      <button class="px-4 py-2 rounded-lg text-white" style="background:var(--brand)">Save</button>
    </div>
  </form>
</div>