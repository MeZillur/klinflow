<?php
declare(strict_types=1);

/**
 * Products index — grid & list
 *
 * Expected from controller per row (when available):
 *  - id, name, sku, category
 *  - brand or brand_name (optional)
 *  - price_like or sale_price
 *  - barcode (optional)
 *  - is_active_like or is_active (optional)
 *
 * Any missing field falls back to 0 / '—'.
 */

$h     = $h     ?? fn($s)=>htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
$base  = $base  ?? ($ctx['module_base'] ?? '/apps/pos');
$q     = isset($q)     ? (string)$q     : (string)($_GET['q'] ?? '');
$page  = isset($page)  ? (int)$page     : max(1, (int)($_GET['page'] ?? 1));
$pages = isset($pages) ? (int)$pages    : 1;
$total = isset($total) ? (int)$total    : 0;
$rows  = $rows ?? [];

if ($rows instanceof Traversable) $rows = iterator_to_array($rows, true);
if (!is_array($rows)) $rows = [];

// Probe first non-empty row for optional keys
$probe = null;
foreach ($rows as $r) {
    if (is_array($r) && $r) { $probe = $r; break; }
}

$hasBrand   = $probe && (array_key_exists('brand', $probe) || array_key_exists('brand_name', $probe));
$hasBarcode = $probe && array_key_exists('barcode', $probe);
$hasActive  = $probe && (array_key_exists('is_active_like', $probe) || array_key_exists('is_active', $probe));
?>
<style>
:root { --brand:#228B22; }

.kf-btn{
  background:var(--brand);color:#fff;border-radius:.6rem;
  padding:.55rem .9rem;font-weight:600;transition:.2s;
}
.kf-btn:hover{filter:brightness(.95);}
.kf-ghost{
  border:1px solid var(--brand);color:var(--brand);
  border-radius:.6rem;padding:.5rem .85rem;font-weight:600;
}
.field{
  border:1px solid #e5e7eb;border-radius:.6rem;
  padding:.55rem .7rem;
}
.field:focus{
  outline:none;border-color:var(--brand);
  box-shadow:0 0 0 3px rgba(34,139,34,.16);
}
.toggle-btn{
  border:1px solid #e5e7eb;padding:.45rem .7rem;
  border-radius:.5rem;cursor:pointer;
}
.toggle-btn.active{
  background:var(--brand);color:#fff;border-color:var(--brand);
}
.prod-card{
  border:1px solid #e5e7eb;border-radius:1rem;
  overflow:hidden;background:#fff;
  transition:box-shadow .15s, transform .08s;
}
.prod-card:hover{
  box-shadow:0 8px 24px rgba(0,0,0,.06);
  transform:translateY(-1px);
}
.status-pill{
  font-size:.75rem;border-radius:999px;
  padding:.15rem .6rem;border:1px solid #bbf7d0;
  background:#ecfdf3;color:#166534;
}
.status-pill.off{
  border-color:#e5e7eb;background:#f3f4f6;color:#4b5563;
}
.mono-small{
  font-family:ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace;
  font-size:.75rem;
}
.kf-link{color:var(--brand);font-weight:600;}
.export-link{
  border:1px solid #e5e7eb;border-radius:.6rem;
  padding:.45rem .7rem;font-size:.85rem;
}
.export-link:hover{background:#f9fafb;}
</style>

<div class="max-w-6xl mx-auto px-4 py-6" x-data="{view:'grid'}">
  <!-- Header + toolbar -->
  <div class="flex items-center justify-between gap-3 flex-wrap">
    <h1 class="text-2xl font-bold">Products</h1>

    <div class="flex items-center gap-2 flex-wrap">
      <!-- Grid/List toggle -->
      <div class="flex items-center gap-1 mr-2">
        <button type="button"
                @click="view='grid'"
                :class="{'active':view==='grid'}"
                class="toggle-btn">
          <i class="fa-solid fa-border-all mr-1"></i> Grid
        </button>
        <button type="button"
                @click="view='list'"
                :class="{'active':view==='list'}"
                class="toggle-btn">
          <i class="fa-solid fa-list mr-1"></i> List
        </button>
      </div>

      <!-- Export buttons (routes to implement in controller) -->
      <a href="<?= $h($base) ?>/products/export?format=csv"  class="export-link">CSV</a>
      <a href="<?= $h($base) ?>/products/export?format=xlsx" class="export-link">Excel</a>

      <!-- Related menu shortcuts -->
      <a href="<?= $h($base) ?>/categories" class="export-link">Categories</a>
      <a href="<?= $h($base) ?>/brands"     class="export-link">Brands</a>
      <a href="<?= $h($base) ?>/inventory"  class="export-link">Inventory</a>

      <!-- New product -->
      <a class="kf-btn" href="<?= $h($base) ?>/products/create">+ New Product</a>
    </div>
  </div>

  <!-- Search row -->
  <div class="mt-4 flex flex-wrap items-center gap-3">
    <form method="get" class="flex items-center gap-2 flex-1 min-w-[220px]">
      <input class="field flex-1" type="search" name="q"
             value="<?= $h($q) ?>" placeholder="Search products… (name / SKU / barcode)">
      <button class="kf-ghost" type="submit">
        <i class="fa-solid fa-magnifying-glass mr-1"></i>Search
      </button>
    </form>

    <div class="text-sm text-gray-600">
      Total: <?= (int)$total ?> products • Page <?= (int)$page ?> / <?= max(1,(int)$pages) ?>
    </div>
  </div>

  <!-- GRID VIEW -->
  <div x-show="view==='grid'" class="mt-6 grid sm:grid-cols-2 md:grid-cols-3 gap-5">
    <?php if (empty($rows)): ?>
      <div class="col-span-full text-center text-gray-500 py-12">No products found.</div>
    <?php else: ?>
      <?php foreach ($rows as $r): if (!is_array($r)) continue; ?>
        <?php
          $id    = (int)($r['id'] ?? 0);
          $name  = (string)($r['name'] ?? '');
          $sku   = (string)($r['sku'] ?? '');
          $cat   = (string)($r['category'] ?? '—');
          $brand = (string)($r['brand'] ?? ($r['brand_name'] ?? '—'));
          $price = (float)($r['price_like'] ?? ($r['sale_price'] ?? 0));
          $barcode = (string)($r['barcode'] ?? '');
          $activeRaw = $r['is_active_like'] ?? ($r['is_active'] ?? 1);
          $isActive  = (int)$activeRaw === 1;
        ?>
        <div class="prod-card flex flex-col justify-between">
          <div class="p-3 border-b border-gray-100 flex items-start justify-between gap-2">
            <div class="mono-small text-gray-500">
              <?= $sku !== '' ? $h($sku) : 'No SKU' ?>
            </div>
            <span class="status-pill <?= $isActive ? '' : 'off' ?>">
              <?= $isActive ? 'Active' : 'Inactive' ?>
            </span>
          </div>

          <div class="p-3 space-y-2">
            <h3 class="font-semibold text-gray-900 leading-snug line-clamp-2">
              <?= $h($name ?: 'Untitled product') ?>
            </h3>
            <div class="text-xs text-gray-600">
              <span class="font-medium"><?= $h($brand) ?></span>
              <span class="mx-1 text-gray-300">·</span>
              <span><?= $h($cat) ?></span>
            </div>

            <div class="flex items-center justify-between text-sm pt-1">
              <div>
                <div class="text-gray-500 text-xs uppercase tracking-wide">Sale price</div>
                <div class="font-semibold">
                  <?= number_format($price, 2) ?>
                </div>
              </div>
              <?php if ($hasBarcode): ?>
                <div class="text-xs text-gray-500 text-right max-w-[140px] truncate">
                  <div class="uppercase tracking-wide mb-0.5">Barcode</div>
                  <span class="mono-small"><?= $barcode !== '' ? $h($barcode) : '—' ?></span>
                </div>
              <?php endif; ?>
            </div>
          </div>

          <div class="px-3 py-2 border-t border-gray-100 flex items-center justify-end text-sm">
            <a class="kf-link hover:underline mr-2" href="<?= $h($base) ?>/products/<?= $id ?>">View</a>
            <span class="text-gray-400">·</span>
            <a class="text-gray-700 hover:underline ml-2" href="<?= $h($base) ?>/products/<?= $id ?>/edit">Edit</a>
          </div>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>

  <!-- LIST VIEW -->
  <div x-show="view==='list'" class="mt-6 overflow-x-auto border rounded-xl bg-white">
    <table class="min-w-full text-sm">
      <thead class="bg-gray-50">
        <tr>
          <th class="text-left px-3 py-2">#</th>
          <th class="text-left px-3 py-2">Name</th>
          <th class="text-left px-3 py-2">SKU</th>
          <?php if ($hasBrand):   ?><th class="text-left px-3 py-2">Brand</th><?php endif; ?>
          <th class="text-left px-3 py-2">Category</th>
          <th class="text-right px-3 py-2">Sale Price</th>
          <?php if ($hasActive): ?><th class="text-center px-3 py-2">Active</th><?php endif; ?>
          <th class="text-right px-3 py-2">Actions</th>
        </tr>
      </thead>
      <tbody class="divide-y divide-gray-100">
        <?php if (empty($rows)): ?>
          <tr>
            <td colspan="<?= 6 + (int)$hasBrand + (int)$hasActive ?>"
                class="px-3 py-6 text-center text-gray-500">
              No products
            </td>
          </tr>
        <?php else: ?>
          <?php foreach ($rows as $r): if (!is_array($r)) continue; ?>
            <?php
              $id    = (int)($r['id'] ?? 0);
              $name  = (string)($r['name'] ?? '');
              $sku   = (string)($r['sku'] ?? '');
              $cat   = (string)($r['category'] ?? '—');
              $brand = (string)($r['brand'] ?? ($r['brand_name'] ?? '—'));
              $price = (float)($r['price_like'] ?? ($r['sale_price'] ?? 0));
              $activeRaw = $r['is_active_like'] ?? ($r['is_active'] ?? 1);
              $isActive  = (int)$activeRaw === 1;
            ?>
            <tr>
              <td class="px-3 py-2 text-gray-500"><?= $id ?></td>
              <td class="px-3 py-2 font-medium text-gray-900">
                <a href="<?= $h($base) ?>/products/<?= $id ?>" class="hover:underline">
                  <?= $h($name ?: 'Untitled product') ?>
                </a>
              </td>
              <td class="px-3 py-2 mono-small text-gray-700"><?= $h($sku ?: '—') ?></td>
              <?php if ($hasBrand): ?>
                <td class="px-3 py-2 text-gray-700"><?= $h($brand) ?></td>
              <?php endif; ?>
              <td class="px-3 py-2 text-gray-700"><?= $h($cat) ?></td>
              <td class="px-3 py-2 text-right">
                <?= number_format($price, 2) ?>
              </td>
              <?php if ($hasActive): ?>
                <td class="px-3 py-2 text-center">
                  <?php if ($isActive): ?>
                    <span class="status-pill">Yes</span>
                  <?php else: ?>
                    <span class="status-pill off">No</span>
                  <?php endif; ?>
                </td>
              <?php endif; ?>

              <!-- Inline show/edit on the right -->
              <td class="px-3 py-2 text-right whitespace-nowrap">
                <a class="kf-link hover:underline" href="<?= $h($base) ?>/products/<?= $id ?>">View</a>
                <span class="mx-1 text-gray-400">·</span>
                <a class="text-gray-700 hover:underline" href="<?= $h($base) ?>/products/<?= $id ?>/edit">Edit</a>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>

  <!-- Pagination -->
  <?php if ($pages > 1): ?>
    <div class="mt-6 flex items-center gap-2 flex-wrap justify-center">
      <?php for ($p=1; $p<=$pages; $p++): ?>
        <a href="?page=<?= $p ?>&q=<?= urlencode($q) ?>"
           class="px-3 py-1 border rounded-lg <?= $page===$p
              ? 'bg-[var(--brand)] text-white border-[var(--brand)]'
              : 'border-gray-200' ?>">
          <?= $p ?>
        </a>
      <?php endfor; ?>
    </div>
  <?php endif; ?>
</div>