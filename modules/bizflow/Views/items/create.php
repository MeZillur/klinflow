<?php
/**
 * BizFlow — Items create + edit + bulk upload
 *
 * Vars expected:
 * - array       $org
 * - string      $module_base
 * - array       $categories
 * - array       $uoms
 * - string      $title
 * - array|null  $item   (when editing)
 */

$h          = $h ?? fn($s)=>htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
$base       = rtrim($module_base ?? ($base ?? '/apps/bizflow'), '/');
$org        = $org ?? [];
$orgName    = trim((string)($org['name'] ?? ''));
$categories = $categories ?? [];
$uoms       = $uoms ?? [];
$item       = $item ?? null;
$isEdit     = is_array($item) && !empty($item['id'] ?? null);

// Map DB type -> UI type
$uiType = 'product';
if ($isEdit) {
    $dbType = strtolower((string)($item['item_type'] ?? 'stock'));
    $uiType = $dbType === 'service' ? 'service' : 'product';
}

$trackInvVal = $isEdit ? (int)($item['track_inventory'] ?? 1) : 1;
$isActiveVal = $isEdit ? (int)($item['is_active'] ?? 1)        : 1;

// Form action
$formAction = $isEdit
    ? $base . '/items/' . (int)$item['id'] . '/update'
    : $base . '/items';

// Pre-fill helpers
$nameVal        = $isEdit ? ($item['name'] ?? '')        : '';
$codeVal        = $isEdit ? ($item['code'] ?? '')        : '';
$descVal        = $isEdit ? ($item['description'] ?? '') : '';
$purchaseVal    = $isEdit ? ($item['purchase_price'] ?? '') : '';
$marginVal      = $isEdit ? ($item['margin_percent'] ?? '') : '';
$sellingVal     = $isEdit ? ($item['selling_price'] ?? '')  : '';
$barcodeVal     = $isEdit ? ($item['barcode'] ?? '')        : '';
$reorderLevel   = $isEdit ? ($item['reorder_level'] ?? '')  : '';
$reorderQty     = $isEdit ? ($item['reorder_qty'] ?? '')    : '';
$categoryIdCur  = $isEdit ? (int)($item['category_id'] ?? 0) : 0;
$uomIdCur       = $isEdit ? (int)($item['uom_id'] ?? 0)      : 0;
?>
<div
    class="max-w-6xl mx-auto px-4 py-6 space-y-8"
    x-data="{
        mode: window.location.hash === '#bulk' ? 'bulk' : 'single',
        switchMode(m) {
            this.mode = m;
            const id = m === 'bulk' ? 'bulk-upload' : 'single-item-form';
            $nextTick(() => {
                const el = document.getElementById(id);
                if (el) el.scrollIntoView({behavior:'smooth', block:'start'});
                if (m === 'bulk') window.location.hash = 'bulk';
                else history.replaceState(null,'',location.pathname+location.search);
            });
        }
    }"
>

  <!-- Header + right-aligned tabs -->
  <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
    <div>
      <h1 class="text-2xl sm:text-3xl font-bold tracking-tight">
        <?= $h($isEdit ? 'Edit item' : ($title ?? 'Items')) ?>
      </h1>
      <p class="mt-1 text-sm text-slate-600">
        <?= $isEdit ? 'Update this product or service for ' : 'Manage your products and services for ' ?>
        <?= $orgName !== '' ? $h($orgName) : 'this organisation' ?>.
      </p>
    </div>

    <!-- Tabs (right aligned) -->
    <div class="flex items-center gap-1 justify-end">
      <a href="<?= $h($base . '/items') ?>"
         class="px-3 py-1.5 text-xs sm:text-sm border border-slate-200 rounded-md hover:bg-slate-50 text-slate-700">
        Items list
      </a>

      <button type="button"
              @click="switchMode('single')"
              class="px-3 py-1.5 text-xs sm:text-sm rounded-md border text-slate-700"
              :class="mode === 'single'
                ? 'border-emerald-600 bg-emerald-600 text-white'
                : 'border-slate-200 hover:bg-slate-50'">
        <?= $h($isEdit ? 'Edit item' : 'New item') ?>
      </button>

      <button type="button"
              @click="switchMode('bulk')"
              class="px-3 py-1.5 text-xs sm:text-sm rounded-md border text-slate-700"
              :class="mode === 'bulk'
                ? 'border-emerald-600 bg-emerald-600 text-white'
                : 'border-slate-200 hover:bg-slate-50'">
        Bulk upload
      </button>
    </div>
  </div>

  <!-- ============================================================
       SINGLE ITEM FORM
  ============================================================= -->
  <section id="single-item-form"
           x-show="mode === 'single'"
           x-cloak
           class="bg-white border border-slate-200 rounded-lg shadow-sm">
    <div class="px-4 py-3 border-b border-slate-200 flex items-center justify-between">
      <div>
        <h2 class="text-base font-semibold">
          <?= $h($isEdit ? 'Item details' : 'Create a single item') ?>
        </h2>
        <p class="text-xs text-slate-500">
          Use this form for quick one-by-one item setup. Description/specification is required.
        </p>
      </div>
    </div>

    <form action="<?= $h($formAction) ?>"
          method="post"
          class="px-4 py-4 space-y-4"
          data-item-form>
      <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <!-- Name -->
        <div>
          <label class="block text-sm font-medium text-slate-700 mb-1">
            Item name <span class="text-red-500">*</span>
          </label>
          <input type="text" name="name" required
                 value="<?= $h($nameVal) ?>"
                 data-item-name
                 class="block w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-emerald-500 focus:ring-emerald-500">
        </div>

        <!-- Code / SKU -->
        <div>
          <label class="block text-sm font-medium text-slate-700 mb-1">
            Item code / SKU
          </label>
          <input type="text" name="code"
                 value="<?= $h($codeVal) ?>"
                 data-item-code
                 class="block w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-emerald-500 focus:ring-emerald-500"
                 placeholder="Will auto-generate like LAP-2025-00001 if left empty">
          <p class="mt-1 text-xs text-slate-500">
            Optional; if left blank, the system will generate a SKU from the item name.
          </p>
        </div>
      </div>

      <!-- Specification / Description -->
      <div>
        <label class="block text-sm font-medium text-slate-700 mb-1">
          Specification / description <span class="text-red-500">*</span>
        </label>
        <textarea
          name="description"
          rows="3"
          required
          class="block w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-emerald-500 focus:ring-emerald-500 resize-y"
        ><?= $h($descVal) ?></textarea>
        <p class="mt-1 text-xs text-slate-500">
          Short technical specification or notes that help your team identify this item correctly in tenders, quotes, and invoices.
        </p>
      </div>

      <!-- Type + Category + UoM (one row) -->
      <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <!-- Type -->
        <div>
          <label class="block text-sm font-medium text-slate-700 mb-1">
            Type
          </label>
          <select name="item_type"
                  class="block w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-emerald-500 focus:ring-emerald-500">
            <option value="product" <?= $uiType === 'product' ? 'selected' : '' ?>>Product</option>
            <option value="service" <?= $uiType === 'service' ? 'selected' : '' ?>>Service</option>
          </select>
        </div>

        <!-- Category -->
        <div>
          <label class="block text-sm font-medium text-slate-700 mb-1">
            Category
          </label>
          <select name="category_id"
                  class="block w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-emerald-500 focus:ring-emerald-500"
                  data-choices>
            <option value="">— No category —</option>
            <?php foreach ($categories as $cat): ?>
              <option value="<?= (int)$cat['id'] ?>"
                <?= $categoryIdCur === (int)$cat['id'] ? 'selected' : '' ?>>
                <?= $h($cat['name']) ?>
                <?php if (!empty($cat['code'])): ?>
                  (<?= $h($cat['code']) ?>)
                <?php endif; ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <!-- UoM -->
        <div>
          <label class="block text-sm font-medium text-slate-700 mb-1">
            Unit of measure
          </label>
          <select name="uom_id"
                  class="block w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-emerald-500 focus:ring-emerald-500"
                  data-choices>
            <option value="">— No unit —</option>
            <?php foreach ($uoms as $uom): ?>
              <option value="<?= (int)$uom['id'] ?>"
                <?= $uomIdCur === (int)$uom['id'] ? 'selected' : '' ?>>
                <?= $h($uom['code'] ?: $uom['name']) ?>
                <?php if (!empty($uom['code']) && !empty($uom['name'])): ?>
                  — <?= $h($uom['name']) ?>
                <?php endif; ?>
              </option>
            <?php endforeach; ?>
          </select>
          <p class="mt-1 text-xs text-slate-500">
            Need another unit?
            <a href="<?= $h($base . '/uoms') ?>" class="text-emerald-700 underline">Manage units of measure</a>.
          </p>
        </div>
      </div>

      <!-- Pricing block -->
      <div class="md:col-span-2">
        <label class="block text-sm font-medium text-slate-700 mb-2">
          Pricing (BDT)
        </label>
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
          <!-- Purchase price -->
          <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">
              Purchase price
            </label>
            <input type="number" step="0.01" min="0" name="purchase_price"
                   value="<?= $h($purchaseVal) ?>"
                   data-item-purchase
                   class="block w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-emerald-500 focus:ring-emerald-500"
                   placeholder="0.00">
          </div>

          <!-- Margin % -->
          <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">
              Margin % on purchase
            </label>
            <div class="relative">
              <input type="number" step="0.01" min="0" max="1000"
                     name="margin_percent"
                     value="<?= $h($marginVal) ?>"
                     data-item-margin
                     class="block w-full rounded-md border border-slate-300 px-3 py-2 pr-8 text-sm focus:border-emerald-500 focus:ring-emerald-500"
                     placeholder="e.g. 20">
              <span class="absolute inset-y-0 right-2 flex items-center text-xs text-slate-500">%</span>
            </div>
          </div>

          <!-- Selling price -->
          <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">
              Selling price <span class="text-red-500">*</span>
            </label>
            <input type="number" step="0.01" min="0" name="selling_price"
                   value="<?= $h($sellingVal) ?>"
                   data-item-selling
                   required
                   class="block w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-emerald-500 focus:ring-emerald-500"
                   placeholder="0.00">
          </div>
        </div>
        <p class="mt-1 text-xs text-slate-500">
          You can either type the selling price directly, or enter purchase price + margin % and the system will calculate the selling price for you.
        </p>
      </div>

      <!-- Optional barcode + reorder -->
      <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div>
          <label class="block text-sm font-medium text-slate-700 mb-1">
            Barcode
          </label>
          <input type="text" name="barcode"
                 value="<?= $h($barcodeVal) ?>"
                 class="block w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-emerald-500 focus:ring-emerald-500">
        </div>
        <div>
          <label class="block text-sm font-medium text-slate-700 mb-1">
            Reorder level
          </label>
          <input type="number" step="0.001" min="0" name="reorder_level"
                 value="<?= $h($reorderLevel) ?>"
                 class="block w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-emerald-500 focus:ring-emerald-500">
        </div>
        <div>
          <label class="block text-sm font-medium text-slate-700 mb-1">
            Reorder quantity
          </label>
          <input type="number" step="0.001" min="0" name="reorder_qty"
                 value="<?= $h($reorderQty) ?>"
                 class="block w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-emerald-500 focus:ring-emerald-500">
        </div>
      </div>

      <!-- Flags -->
      <div class="flex items-center gap-4 mt-2 md:mt-4">
        <label class="inline-flex items-center gap-2 text-sm text-slate-700">
          <input type="checkbox" name="is_stocked" value="1"
                 class="rounded border-slate-300 text-emerald-600 focus:ring-emerald-500"
            <?= $trackInvVal ? 'checked' : '' ?>>
          <span>Track stock</span>
        </label>
        <label class="inline-flex items-center gap-2 text-sm text-slate-700">
          <input type="checkbox" name="is_active" value="1"
                 class="rounded border-slate-300 text-emerald-600 focus:ring-emerald-500"
            <?= $isActiveVal ? 'checked' : '' ?>>
          <span>Active</span>
        </label>
      </div>

      <div class="pt-3 flex items-center justify-end gap-3 border-t border-slate-100 mt-4">
        <a href="<?= $h($base . '/items') ?>"
           class="px-3 py-2 text-xs sm:text-sm rounded-md border border-slate-200 text-slate-700 hover:bg-slate-50">
          Cancel
        </a>
        <button type="submit"
                class="px-4 py-2 text-xs sm:text-sm rounded-md bg-emerald-600 text-white font-medium hover:bg-emerald-700">
          <?= $h($isEdit ? 'Save changes' : 'Save item') ?>
        </button>
      </div>
    </form>
  </section>

  <!-- ============================================================
       BULK UPLOAD (simple HTML form → server preview)
  ============================================================= -->
  <section id="bulk-upload"
           x-show="mode === 'bulk'"
           x-cloak
           class="bg-white border border-slate-200 rounded-lg shadow-sm">
    <div class="px-4 py-3 border-b border-slate-200 flex items-center justify-between">
      <div>
        <h2 class="text-base font-semibold">Bulk upload items</h2>
        <p class="text-xs text-slate-500">
          Upload a CSV exported from Excel. After upload, BizFlow will show a preview page before importing.
        </p>
      </div>
      <div class="flex flex-col items-end gap-1">
        <div class="text-[11px] rounded-full bg-slate-100 px-3 py-1 text-slate-600">
          Required columns:
          <span class="font-mono">name</span>,
          <span class="font-mono">unit</span>,
          <span class="font-mono">selling_price</span>
        </div>
        <!-- Downloadable format -->
        <a href="<?= $h($base . '/items/bulk-template.csv') ?>"
           download="bizflow-items-template.csv"
           class="inline-flex items-center gap-1 text-[11px] text-emerald-700 hover:text-emerald-800">
          <i class="fa fa-download text-[10px]"></i>
          <span>Download CSV template</span>
        </a>
      </div>
    </div>

    <form
        action="<?= $h($base . '/items/bulk-preview') ?>"
        method="post"
        enctype="multipart/form-data"
        class="px-4 py-4 space-y-4"
    >
      <!-- File picker -->
      <div class="grid gap-4 md:grid-cols-3">
        <div class="md:col-span-2">
          <label class="block text-sm font-medium text-slate-700 mb-1">
            CSV file
          </label>

          <label class="flex flex-col items-center justify-center gap-2 rounded-lg border-2 border-dashed px-4 py-6 text-center cursor-pointer transition border-slate-300 hover:border-emerald-400 hover:bg-emerald-50/20">
            <input type="file"
                   name="items_file"
                   class="hidden"
                   accept=".csv"
                   required
                   onchange="this.closest('form').querySelector('[data-file-name]').textContent = this.files.length ? this.files[0].name : 'Drop CSV here or click to browse';">

            <i class="fa fa-file-upload text-xl text-emerald-600"></i>

            <div class="space-y-1">
              <p class="text-sm font-medium text-slate-800" data-file-name>
                Drop CSV here or click to browse
              </p>
              <p class="text-xs text-slate-500">
                Max ~5MB. Use UTF-8 CSV. Excel → Save As → CSV (UTF-8).
              </p>
            </div>
          </label>
        </div>

        <div class="md:col-span-1">
          <div class="rounded-lg border border-slate-200 bg-slate-50 p-3 text-xs text-slate-700 space-y-1">
            <div class="font-semibold text-slate-800 mb-1">Template columns</div>
            <p><span class="font-mono">name</span> — item name (required).</p>
            <p><span class="font-mono">code</span> — optional; leave empty to auto-generate.</p>
            <p><span class="font-mono">item_type</span> — <span class="font-mono">product</span> or <span class="font-mono">service</span>.</p>
            <p><span class="font-mono">unit</span> — e.g. <span class="font-mono">pcs</span>, <span class="font-mono">box</span>, <span class="font-mono">hour</span>.</p>
            <p><span class="font-mono">purchase_price</span> — optional, in BDT.</p>
            <p><span class="font-mono">selling_price</span> — required, in BDT.</p>
            <p><span class="font-mono">category</span> — optional category name (must match existing if used).</p>
            <p class="pt-1">
              Or just download the ready format above and fill it.
            </p>
          </div>
        </div>
      </div>

      <!-- Actions -->
      <div class="pt-3 flex items-center justify-end gap-3 border-t border-slate-100 mt-4">
        <button type="button"
                onclick="const f=this.closest('form').querySelector('input[type=file]'); if(f){f.value=''; this.closest('form').querySelector('[data-file-name]').textContent='Drop CSV here or click to browse';}"
                class="px-3 py-2 text-xs sm:text-sm rounded-md border border-slate-200 text-slate-700 hover:bg-slate-50">
          Clear file
        </button>

        <button type="submit"
                class="px-4 py-2 text-xs sm:text-sm rounded-md font-medium text-white shadow-sm bg-emerald-600 hover:bg-emerald-700">
          Upload
        </button>
      </div>
    </form>
  </section>

  <!-- ============================================================
       How to use this page
  ============================================================= -->
  <section class="rounded-2xl border border-dashed border-slate-300 bg-slate-50 p-4 text-sm text-slate-700 space-y-2">
    <h2 class="text-base font-semibold mb-1">How to use this page</h2>
    <ol class="list-decimal space-y-1 pl-5">
      <li>
        Use <span class="font-medium">New item</span> for one-off products or services where you want full control of every field.
      </li>
      <li>
        For large catalogs, switch to the <span class="font-medium">Bulk upload</span> tab and download the
        <span class="font-medium">CSV template</span>. Fill it in Excel and save as UTF-8 CSV.
      </li>
      <li>
        From the <span class="font-medium">Bulk upload</span> tab, choose your CSV and click
        <span class="font-medium">Upload</span>. BizFlow will show a separate preview page.
      </li>
      <li>
        On the preview page, review status (OK / warning / error) for each line.
        Only OK rows will be imported when you confirm.
      </li>
      <li>
        All prices are treated as <span class="font-medium">Bangladeshi Taka (BDT)</span> so your sales,
        purchase and tender documents stay consistent.
      </li>
    </ol>
  </section>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
  const form = document.querySelector('[data-item-form]');
  if (!form) return;

  const nameEl     = form.querySelector('[data-item-name]');
  const codeEl     = form.querySelector('[data-item-code]');
  const purchaseEl = form.querySelector('[data-item-purchase]');
  const marginEl   = form.querySelector('[data-item-margin]');
  const sellingEl  = form.querySelector('[data-item-selling]');

  function updateSkuPlaceholder() {
    if (!nameEl || !codeEl) return;
    if (codeEl.value.trim() !== '') return;
    const raw  = (nameEl.value || '').toUpperCase().replace(/[^A-Z]/g, '');
    if (!raw) {
      codeEl.placeholder = 'Will auto-generate like LAP-2025-00001 if left empty';
      return;
    }
    const base = (raw.slice(0, 3) || 'PRD').padEnd(3, 'X');
    const year = new Date().getFullYear();
    codeEl.placeholder = base + '-' + year + '-00001';
  }

  if (nameEl && codeEl) {
    nameEl.addEventListener('input', updateSkuPlaceholder);
    nameEl.addEventListener('blur', updateSkuPlaceholder);
    updateSkuPlaceholder();
  }

  function recalcSelling() {
    if (!purchaseEl || !marginEl || !sellingEl) return;
    const p = parseFloat((purchaseEl.value || '').replace(',', '.'));
    const m = parseFloat((marginEl.value || '').replace(',', '.'));
    if (!isFinite(p) || !isFinite(m)) return;
    const val = p * (1 + m / 100);
    if (!isFinite(val)) return;
    sellingEl.value = val.toFixed(2);
  }

  if (purchaseEl && marginEl && sellingEl) {
    purchaseEl.addEventListener('input', recalcSelling);
    marginEl.addEventListener('input', recalcSelling);
  }
});
</script>