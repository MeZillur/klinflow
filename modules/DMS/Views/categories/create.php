<?php
declare(strict_types=1);
/** @var array|null $cat */
/** @var string $module_base */
$cat    = $cat ?? null;
$base   = $module_base ?? '';
$isEdit = is_array($cat);
$action = $isEdit ? ($base.'/categories/'.(int)$cat['id']) : ($base.'/categories');

$code   = $isEdit ? (string)($cat['code'] ?? '') : '';
$name   = $isEdit ? (string)($cat['name'] ?? '') : '';
$active = $isEdit ? (int)($cat['is_active'] ?? 1) : 1;

$h = fn($s)=>htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
?>
<div class="max-w-3xl mx-auto">
  <div class="flex items-center justify-between mb-4">
    <h1 class="text-2xl font-semibold"><?= $isEdit ? 'Edit Category' : 'Create Category' ?></h1>
    <a href="<?= $h($base.'/categories') ?>" class="px-3 py-2 rounded-lg border">Back</a>
  </div>

  <form method="post" action="<?= $h($action) ?>" class="space-y-6">
    <?= function_exists('csrf_field') ? csrf_field() : '' ?>

    <div>
      <label class="block text-sm font-medium mb-1">Category Code</label>
      <input name="code" value="<?= $h($code) ?>" class="w-full rounded-lg border px-3 py-2 bg-gray-50"
             placeholder="Auto (CAT-<?= date('Y') ?>-00001)">
      <p class="text-xs text-gray-500 mt-1">Leave blank to auto-generate.</p>
    </div>

    <div>
      <label class="block text-sm font-medium mb-1">Name</label>
      <input name="name" value="<?= $h($name) ?>" class="w-full rounded-lg border px-3 py-2" required>
    </div>

    <div class="flex items-center gap-3">
      <input type="checkbox" id="is_active" name="is_active" value="1" <?= $active ? 'checked' : '' ?>>
      <label for="is_active" class="text-sm">Active</label>
    </div>

    <div class="flex justify-end">
      <button class="px-4 py-2 rounded-lg bg-emerald-600 text-white hover:bg-emerald-700">
        <?= $isEdit ? 'Update Category' : 'Save Category' ?>
      </button>
    </div>
  </form>
</div>