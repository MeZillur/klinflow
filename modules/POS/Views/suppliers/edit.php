<?php
$h    = $h    ?? fn($s)=>htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
$base = $base ?? '/apps/pos';
$have = array_flip($haveCols ?? []);
$sup  = $sup ?? ['id'=>0,'name'=>''];
?>
<section class="max-w-3xl">
  <h1 class="text-2xl font-bold mb-4">Edit Supplier</h1>
  <form method="post" action="<?= $h($base) ?>/suppliers/<?= (int)($sup['id'] ?? 0) ?>" class="space-y-4 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl p-4">
    <div>
      <label class="block text-sm mb-1">Name <span class="text-red-500">*</span></label>
      <input name="name" value="<?= $h($sup['name'] ?? '') ?>" required
             class="w-full px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-900">
    </div>
    <?php if (isset($have['code'])): ?>
    <div><label class="block text-sm mb-1">Code</label>
      <input name="code" value="<?= $h($sup['code'] ?? '') ?>" class="w-full px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-900">
    </div>
    <?php endif; ?>
    <?php if (isset($have['phone'])): ?>
    <div><label class="block text-sm mb-1">Phone</label>
      <input name="phone" value="<?= $h($sup['phone'] ?? '') ?>" class="w-full px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-900">
    </div>
    <?php endif; ?>
    <?php if (isset($have['email'])): ?>
    <div><label class="block text-sm mb-1">Email</label>
      <input name="email" type="email" value="<?= $h($sup['email'] ?? '') ?>" class="w-full px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-900">
    </div>
    <?php endif; ?>
    <?php if (isset($have['address'])): ?>
    <div><label class="block text-sm mb-1">Address</label>
      <textarea name="address" class="w-full px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-900"><?= $h($sup['address'] ?? '') ?></textarea>
    </div>
    <?php endif; ?>

    <div class="flex items-center justify-between">
      <label class="inline-flex items-center gap-2 text-sm">
        <input type="checkbox" name="is_active" value="1" <?= ((int)($sup['is_active'] ?? 1))===1 ? 'checked' : '' ?> class="rounded border-gray-300">
        Active
      </label>
      <div class="flex gap-2">
        <a href="<?= $h($base) ?>/suppliers" class="px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-700">Cancel</a>
        <button class="px-4 py-2 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white">Update</button>
      </div>
    </div>
  </form>
</section>