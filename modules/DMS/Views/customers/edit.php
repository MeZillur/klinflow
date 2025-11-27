<?php
declare(strict_types=1);

if (!function_exists('h')) { function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); } }

$customer = $customer ?? [];
$id   = (int)($customer['id'] ?? 0);
$code = (string)($customer['code'] ?? '');
?>
<div class="max-w-5xl mx-auto">
  <div class="flex items-center justify-between mb-6">
    <h1 class="text-2xl font-bold">Edit Customer</h1>
    <a href="<?= h($module_base) ?>/customers" class="text-sm text-slate-600 hover:underline">Back to Customers</a>
  </div>

  <form method="POST" action="<?= h($module_base) ?>/customers/<?= $id ?>" class="space-y-8">
    <?php if (function_exists('csrf_field')) echo csrf_field(); ?>

    <!-- Customer Code (immutable) -->
    <section class="space-y-2">
      <label class="block text-sm font-medium">Customer Code</label>

      <!-- Hidden to keep value flowing through posts, even though we won't update it in SQL -->
      <input type="hidden" name="code" value="<?= h($code) ?>">

      <input type="text" readonly
             value="<?= h($code) ?>"
             class="w-full rounded-lg border px-3 py-2 bg-slate-50 text-slate-700 cursor-not-allowed select-none">
      <p class="text-xs text-slate-500">Code is immutable.</p>
    </section>

    <!-- Basics -->
    <section class="grid grid-cols-1 lg:grid-cols-2 gap-4">
      <div>
        <label class="block text-sm font-medium mb-1">Full Name <span class="text-rose-600">*</span></label>
        <input type="text" name="name" required
               value="<?= h($customer['name'] ?? '') ?>"
               class="w-full rounded-lg border px-3 py-2">
      </div>
      <div>
        <label class="block text-sm font-medium mb-1">Phone</label>
        <input type="text" name="phone"
               value="<?= h($customer['phone'] ?? '') ?>"
               class="w-full rounded-lg border px-3 py-2">
      </div>
      <div>
        <label class="block text-sm font-medium mb-1">Email</label>
        <input type="email" name="email"
               value="<?= h($customer['email'] ?? '') ?>"
               class="w-full rounded-lg border px-3 py-2">
      </div>
      <div>
        <label class="block text-sm font-medium mb-1">Status</label>
        <?php $st = (string)($customer['status'] ?? 'active'); ?>
        <select name="status" class="w-full rounded-lg border px-3 py-2">
          <option value="active"   <?= $st==='active'?'selected':'' ?>>Active</option>
          <option value="inactive" <?= $st==='inactive'?'selected':'' ?>>Inactive</option>
        </select>
      </div>
      <div class="lg:col-span-2">
        <label class="block text-sm font-medium mb-1">Address</label>
        <input type="text" name="address"
               value="<?= h($customer['address'] ?? '') ?>"
               class="w-full rounded-lg border px-3 py-2">
      </div>
    </section>

    <!-- Notes -->
    <section>
      <label class="block text-sm font-medium mb-1">Notes</label>
      <textarea name="notes" rows="4"
                class="w-full rounded-lg border px-3 py-2"><?= h($customer['notes'] ?? '') ?></textarea>
    </section>

    <!-- Actions -->
    <div class="flex items-center justify-end gap-3">
      <a href="<?= h($module_base) ?>/customers" class="px-4 py-2 rounded-lg border hover:bg-slate-50">Cancel</a>
      <button class="px-4 py-2 rounded-lg bg-emerald-600 text-white hover:bg-emerald-700">Update Customer</button>
    </div>
  </form>
</div>