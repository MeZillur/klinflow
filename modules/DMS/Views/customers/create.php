<?php
declare(strict_types=1);

if (!function_exists('h')) { function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); } }

$title = $title ?? 'New Customer';
// If you post back with validation later, you can pass $old = $_POST-like
$old = $old ?? [];
?>
<div class="max-w-5xl mx-auto">
  <div class="flex items-center justify-between mb-6">
    <h1 class="text-2xl font-bold">Create Customer</h1>
    <a href="<?= h($module_base) ?>/customers" class="text-sm text-slate-600 hover:underline">Back to Customers</a>
  </div>

  <form method="POST" action="<?= h($module_base) ?>/customers" class="space-y-8">
    <?php if (function_exists('csrf_field')) echo csrf_field(); ?>

    <!-- Customer Code (auto) -->
    <section class="space-y-2">
      <label class="block text-sm font-medium">Customer Code</label>

      <!-- Hidden posted value (empty on CREATE so DB trigger generates code) -->
      <input type="hidden" name="code" value="">

      <input type="text"
             readonly
             class="w-full rounded-lg border px-3 py-2 bg-slate-50 text-slate-700 cursor-not-allowed select-none"
             placeholder="Will be generated on save (CID-<?= date('Y') ?>-00001)">
      <p class="text-xs text-slate-500">
        Code will be auto-generated per year and organization when you save (e.g., CID-<?= date('Y') ?>-00001).
      </p>
    </section>

    <!-- Basics -->
    <section class="grid grid-cols-1 lg:grid-cols-2 gap-4">
      <div>
        <label class="block text-sm font-medium mb-1">Full Name <span class="text-rose-600">*</span></label>
        <input type="text" name="name" required
               value="<?= h($old['name'] ?? '') ?>"
               class="w-full rounded-lg border px-3 py-2" placeholder="Customer name">
      </div>
      <div>
        <label class="block text-sm font-medium mb-1">Phone</label>
        <input type="text" name="phone"
               value="<?= h($old['phone'] ?? '') ?>"
               class="w-full rounded-lg border px-3 py-2" placeholder="e.g., +1 555 123 4567">
        <p class="text-xs text-slate-500 mt-1">We index phone per organization for quick lookups.</p>
      </div>
      <div>
        <label class="block text-sm font-medium mb-1">Email</label>
        <input type="email" name="email"
               value="<?= h($old['email'] ?? '') ?>"
               class="w-full rounded-lg border px-3 py-2" placeholder="name@example.com">
      </div>
      <div>
        <label class="block text-sm font-medium mb-1">Status</label>
        <select name="status" class="w-full rounded-lg border px-3 py-2">
          <?php $st = (string)($old['status'] ?? 'active'); ?>
          <option value="active"   <?= $st==='active'?'selected':'' ?>>Active</option>
          <option value="inactive" <?= $st==='inactive'?'selected':'' ?>>Inactive</option>
        </select>
      </div>
      <div class="lg:col-span-2">
        <label class="block text-sm font-medium mb-1">Address</label>
        <input type="text" name="address"
               value="<?= h($old['address'] ?? '') ?>"
               class="w-full rounded-lg border px-3 py-2" placeholder="Street, City, ZIP">
      </div>
    </section>

    <!-- Notes -->
    <section>
      <label class="block text-sm font-medium mb-1">Notes</label>
      <textarea name="notes" rows="4"
                class="w-full rounded-lg border px-3 py-2"
                placeholder="Internal note…"><?= h($old['notes'] ?? '') ?></textarea>
    </section>

    <!-- Actions -->
    <div class="flex items-center justify-end gap-3">
      <a href="<?= h($module_base) ?>/customers" class="px-4 py-2 rounded-lg border hover:bg-slate-50">Cancel</a>
      <button class="px-4 py-2 rounded-lg bg-emerald-600 text-white hover:bg-emerald-700">Save Customer</button>
    </div>
  </form>
</div>