<?php
declare(strict_types=1);
/**
 * Brands → Index (CONTENT-ONLY)
 * Inputs: $base, $brands (array)
 */
$h     = fn($x)=>htmlspecialchars((string)$x, ENT_QUOTES, 'UTF-8');
$brand = '#228B22';
?>
<style>
  .brand-page { max-width: 1120px; margin: 0 auto; }
  .brand-table { width: 100%; border-collapse: collapse; font-size: 0.875rem; }
  .brand-table th,
  .brand-table td { padding: 0.5rem 0.75rem; border-bottom: 1px solid #e5e7eb; }
  .brand-table th { text-align: left; font-weight: 600; font-size: 0.75rem; color: #6b7280; }
  .brand-pill {
    display: inline-block;
    padding: 0.125rem 0.5rem;
    border-radius: 999px;
    font-size: 0.7rem;
  }
</style>

<div class="px-6 py-6 brand-page space-y-6">
  <div class="flex items-center justify-between">
    <div>
      <h1 class="text-2xl font-semibold">Brands</h1>
      <p class="text-sm text-gray-500">
        Manage the brands you use on products. Active brands are available in the product form.
      </p>
    </div>
    <div class="flex items-center gap-2">
      <a href="<?= $base ?>/products"
         class="inline-flex items-center gap-2 px-3 py-2 rounded-lg border border-gray-300 text-sm hover:bg-gray-100">
        <i class="fa fa-box"></i> Products
      </a>
      <a href="<?= $base ?>/brands/create"
         class="inline-flex items-center gap-2 px-3 py-2 rounded-lg text-sm text-white"
         style="background: <?= $brand ?>;">
        <i class="fa fa-plus"></i> New Brand
      </a>
    </div>
  </div>

  <div class="grid grid-cols-1 md:grid-cols-[2fr,1fr] gap-4">
    <!-- list -->
    <div class="rounded-xl border border-gray-200 bg-white p-4">
      <div class="flex items-center justify-between mb-3">
        <h2 class="font-semibold text-sm tracking-wide text-gray-600">BRAND LIST</h2>
        <span class="text-xs text-gray-500">
          Total: <?= count($brands) ?>
        </span>
      </div>

      <?php if (empty($brands)): ?>
        <p class="text-sm text-gray-500">No brands yet. Create your first brand.</p>
      <?php else: ?>
        <table class="brand-table">
          <thead>
            <tr>
              <th style="width: 40%;">Name</th>
              <th>Slug</th>
              <th>Status</th>
              <th style="width: 8rem;">Created</th>
            </tr>
          </thead>
          <tbody>
          <?php foreach ($brands as $b): ?>
            <tr>
              <td>
                <a href="<?= $base ?>/brands/<?= (int)$b['id'] ?>"
                   class="text-sm text-gray-900 hover:underline">
                  <?= $h($b['name'] ?? '') ?>
                </a>
              </td>
              <td class="text-xs text-gray-500"><?= $h($b['slug'] ?? '') ?></td>
              <td>
                <?php $active = (int)($b['is_active'] ?? 1) === 1; ?>
                <span class="brand-pill"
                      style="background: <?= $active ? $brand : '#e5e7eb' ?>;
                             color: <?= $active ? '#fff' : '#374151' ?>;">
                  <?= $active ? 'Active' : 'Inactive' ?>
                </span>
              </td>
              <td class="text-xs text-gray-500">
                <?= $h(substr((string)($b['created_at'] ?? ''), 0, 10)) ?>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>
    </div>

    <!-- guidance -->
    <aside class="rounded-xl border border-gray-200 bg-white p-4 text-sm space-y-2">
      <h2 class="font-semibold text-sm tracking-wide text-gray-600">Tips</h2>
      <ul class="list-disc pl-4 space-y-1 text-gray-600">
        <li>Use clear, customer-facing names like <strong>Samsung</strong> or <strong>Apple</strong>.</li>
        <li>Keep brands <strong>Active</strong> to use them on new products.</li>
        <li>You can also create brands on the fly from the product form.</li>
        <li>Deactivating a brand won’t change existing invoices; it just hides it from future selection.</li>
      </ul>
    </aside>
  </div>
</div>