<?php
declare(strict_types=1);
$h = fn($x)=>htmlspecialchars((string)$x, ENT_QUOTES, 'UTF-8');
?>
<div class="max-w-3xl mx-auto px-6 py-8">

  <div class="flex justify-between items-center mb-6">
    <h1 class="text-2xl font-bold">POS Branding</h1>
    <a href="<?= $h($base.'/apps') ?>" class="text-sm text-blue-600 hover:underline">&larr; Back to POS</a>
  </div>

  <?php if (!empty($_GET['saved'])): ?>
    <div class="p-3 mb-4 bg-green-100 text-green-800 rounded">Branding saved.</div>
  <?php endif; ?>

  <form action="<?= $h($base.'/settings') ?>" method="post" enctype="multipart/form-data"
        class="grid grid-cols-1 md:grid-cols-3 gap-6 bg-white p-6 rounded-xl shadow">

    <!-- Left fields -->
    <div class="md:col-span-2 space-y-4">

      <div>
        <label class="font-semibold">Business Name</label>
        <input type="text" name="business_name"
               value="<?= $h($branding['business_name']) ?>"
               class="mt-1 w-full border rounded px-3 py-2">
      </div>

      <div>
        <label class="font-semibold">Address</label>
        <textarea name="address" rows="3"
                  class="mt-1 w-full border rounded px-3 py-2"><?= $h($branding['address']) ?></textarea>
      </div>

      <div class="grid grid-cols-2 gap-4">
        <div>
          <label class="font-semibold">Phone</label>
          <input type="text" name="phone"
                 value="<?= $h($branding['phone']) ?>"
                 class="mt-1 w-full border rounded px-3 py-2">
        </div>
        <div>
          <label class="font-semibold">Email</label>
          <input type="text" name="email"
                 value="<?= $h($branding['email']) ?>"
                 class="mt-1 w-full border rounded px-3 py-2">
        </div>
      </div>

      <div>
        <label class="font-semibold">Website (optional)</label>
        <input type="text" name="website"
               value="<?= $h($branding['website']) ?>"
               class="mt-1 w-full border rounded px-3 py-2">
      </div>

    </div>

    <!-- Logo -->
    <div class="space-y-2">
      <label class="font-semibold">Logo</label>

      <div class="border rounded-lg p-3 flex justify-center bg-gray-50">
        <?php if (!empty($branding['logo_path'])): ?>
          <img src="<?= $h($branding['logo_path']) ?>" class="max-h-40" alt="Logo preview">
        <?php else: ?>
          <div class="text-gray-400 text-sm">No logo uploaded</div>
        <?php endif; ?>
      </div>

      <input type="file" name="logo" class="mt-2 block">
    </div>

    <!-- Save button -->
    <div class="md:col-span-3 flex justify-end gap-3 pt-4">
      <a href="<?= $h($base.'/apps') ?>" class="px-4 py-2 border rounded">Cancel</a>
      <button class="px-5 py-2 bg-green-600 text-white rounded hover:bg-green-700">
        Save Branding
      </button>
    </div>
  </form>
</div>