<?php
declare(strict_types=1);
/**
 * Brands → Create (CONTENT-ONLY)
 * Inputs: $base
 * Uses session: $_SESSION['pos_errors'], $_SESSION['pos_old']
 */
$h     = fn($x)=>htmlspecialchars((string)$x, ENT_QUOTES, 'UTF-8');
$brand = '#228B22';

$old = $_SESSION['pos_old']   ?? [];
$err = $_SESSION['pos_errors']?? [];
unset($_SESSION['pos_old'], $_SESSION['pos_errors']);

$nameVal         = $old['name'] ?? '';
$isActiveChecked = array_key_exists('is_active', $old) ? (int)$old['is_active'] === 1 : 1;
?>
<style>
  .brand-page { max-width: 1120px; margin: 0 auto; }
  .card-border {
    border: 1px solid #e5e7eb;
    border-radius: 0.75rem;
    background: #ffffff;
  }
</style>

<div class="px-6 py-6 brand-page space-y-6">
  <div class="flex items-center justify-between">
    <div>
      <h1 class="text-2xl font-semibold">New Brand</h1>
      <p class="text-sm text-gray-500">
        Create a brand to group products. You can assign brands from the product form.
      </p>
    </div>
    <a href="<?= $base ?>/brands"
       class="inline-flex items-center gap-2 px-3 py-2 rounded-lg border border-gray-300 text-sm hover:bg-gray-100">
      <i class="fa fa-arrow-left"></i> Back to Brands
    </a>
  </div>

  <div class="grid grid-cols-1 md:grid-cols-[2fr,1fr] gap-4">
    <!-- form -->
    <form method="post" action="<?= $base ?>/brands" class="space-y-4">
      <div class="card-border p-4 space-y-4">
        <h2 class="font-semibold text-sm tracking-wide text-gray-600">BASIC INFORMATION</h2>

        <div>
          <label class="block text-sm font-medium mb-1">
            Brand Name <span class="text-red-500">*</span>
          </label>
          <input
            type="text"
            name="name"
            value="<?= $h($nameVal) ?>"
            required
            class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm"
            placeholder="e.g. Samsung, Apple, Nike"
          >
          <?php if (!empty($err['name'])): ?>
            <div class="text-xs text-red-600 mt-1"><?= $h($err['name']) ?></div>
          <?php endif; ?>
          <p class="text-xs text-gray-500 mt-1">
            The URL slug will be generated automatically from this name.
          </p>
        </div>

        <div class="flex items-center gap-2 pt-1">
          <label class="inline-flex items-center gap-2 text-sm">
            <input type="checkbox" name="is_active" value="1" <?= $isActiveChecked ? 'checked' : '' ?>>
            <span>Active</span>
          </label>
        </div>
      </div>

      <div class="flex items-center gap-2">
        <button
          type="submit"
          class="px-4 py-2 rounded-lg text-sm font-medium text-white"
          style="background: <?= $brand ?>;"
        >
          <i class="fa fa-check"></i> Save Brand
        </button>
        <a href="<?= $base ?>/brands"
           class="px-4 py-2 rounded-lg border border-gray-300 text-sm hover:bg-gray-100">
          Cancel
        </a>
      </div>
    </form>

    <!-- guidance -->
    <aside class="card-border p-4 text-sm space-y-2">
      <h2 class="font-semibold text-sm tracking-wide text-gray-600">How to use brands</h2>
      <ul class="list-disc pl-4 space-y-1 text-gray-600">
        <li>Brands help you filter and report sales by manufacturer or label.</li>
        <li>Keep the name short and consistent with what appears on the box.</li>
        <li>You can quickly create brands from the product form using
            the “+ New brand” option.</li>
        <li>If you stop selling a brand, uncheck <strong>Active</strong> so it
            no longer appears in new product forms.</li>
      </ul>
    </aside>
  </div>
</div>