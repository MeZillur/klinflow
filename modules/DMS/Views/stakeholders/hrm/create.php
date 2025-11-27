<?php declare(strict_types=1); if(!function_exists('h')){function h($v){return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');}}
$isEdit = isset($s) && is_array($s); $action = $module_base.'/stakeholders'.($isEdit?('/'.(int)$s['id']):''); ?>
<h1 class="text-xl font-semibold mb-4"><?= $isEdit?'Edit Stakeholder':'Create Stakeholder' ?></h1>

<form method="POST" action="<?= h($action) ?>" class="space-y-6">
  <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
    <div>
      <label class="block text-sm font-medium mb-1">Code</label>
      <input name="code" value="<?= h($s['code']??'') ?>" placeholder="Auto if blank" class="w-full rounded-lg border px-3 py-2" />
      <p class="text-xs text-slate-500 mt-1">Auto: SR-YYYY-00001 / DSR-YYYY-00001</p>
    </div>
    <div>
      <label class="block text-sm font-medium mb-1">Role</label>
      <select name="role" class="w-full rounded-lg border px-3 py-2">
        <option value="sr"  <?= (($s['role']??'sr')==='sr')?'selected':'' ?>>SR</option>
        <option value="dsr" <?= (($s['role']??'sr')==='dsr')?'selected':'' ?>>DSR</option>
      </select>
    </div>
    <div>
      <label class="block text-sm font-medium mb-1">Status</label>
      <select name="status" class="w-full rounded-lg border px-3 py-2">
        <option value="active"   <?= (($s['status']??'active')==='active')?'selected':'' ?>>Active</option>
        <option value="inactive" <?= (($s['status']??'active')==='inactive')?'selected':'' ?>>Inactive</option>
      </select>
    </div>
  </div>

  <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
    <div>
      <label class="block text-sm font-medium mb-1">Name</label>
      <input name="name" value="<?= h($s['name']??'') ?>" required class="w-full rounded-lg border px-3 py-2">
    </div>
    <div>
      <label class="block text-sm font-medium mb-1">Phone</label>
      <input name="phone" value="<?= h($s['phone']??'') ?>" class="w-full rounded-lg border px-3 py-2">
    </div>
    <div>
      <label class="block text-sm font-medium mb-1">Email</label>
      <input name="email" value="<?= h($s['email']??'') ?>" type="email" class="w-full rounded-lg border px-3 py-2">
    </div>
  </div>

  <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
    <div>
      <label class="block text-sm font-medium mb-1">Territory</label>
      <input name="territory" value="<?= h($s['territory']??'') ?>" class="w-full rounded-lg border px-3 py-2">
    </div>
    <div>
      <label class="block text-sm font-medium mb-1">Notes</label>
      <input name="notes" value="<?= h($s['notes']??'') ?>" class="w-full rounded-lg border px-3 py-2">
    </div>
  </div>

  <div class="flex justify-end gap-2">
    <a href="<?= h($module_base) ?>/stakeholders" class="px-3 py-2 rounded-lg border">Cancel</a>
    <button class="px-4 py-2 rounded-lg bg-emerald-600 text-white hover:bg-emerald-700"><?= $isEdit?'Update':'Save' ?></button>
  </div>
</form>