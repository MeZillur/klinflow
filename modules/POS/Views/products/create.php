<?php
declare(strict_types=1);
/**
 * Product create view
 * Vars: $base, $categories, $brands, $uoms, $taxes (all optional)
 */
$h      = $h ?? fn($s)=>htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
$cats   = $categories ?? [];
$brs    = $brands ?? [];
$units  = $uoms ?? [];
$txs    = $taxes ?? [];
$base   = $base ?? '/apps/pos';
$action = $base.'/products';
?>
<style>
  :root { --kf:#228B22; }
  .kf-btn-primary {
    background: var(--kf);
    color:#fff;
    border-radius: .75rem;
    padding:.55rem 1.1rem;
    font-weight:600;
    border:1px solid var(--kf);
    display:inline-flex;
    align-items:center;
    gap:.4rem;
  }
  .kf-btn-primary:hover { filter:brightness(.97); }
  .kf-btn-outline {
    background:#fff;
    color:#111827;
    border-radius:.75rem;
    padding:.55rem 1.1rem;
    font-weight:600;
    border:1px solid rgba(34,139,34,.6);
    display:inline-flex;
    align-items:center;
    gap:.4rem;
  }
  .kf-btn-outline:hover { background:rgba(34,139,34,.04); }
  .kf-btn-ghost {
    background:#fff;
    color:#111827;
    border-radius:.75rem;
    padding:.45rem 1rem;
    font-weight:500;
    border:1px solid #e5e7eb;
  }
  .kf-pill  {
    background: rgba(34,139,34,.04);
    color:#065f46;
    border:1px solid rgba(34,139,34,.35);
    border-radius:.75rem;
  }
  .field   {
    border:1px solid #e5e7eb;
    border-radius: .75rem;
    padding:.55rem .75rem;
    font-size:.875rem;
    width:100%;
  }
  .field:focus {
    outline:none;
    border-color: var(--kf);
    box-shadow:0 0 0 3px rgba(34,139,34,.18);
  }
  .badge   {
    font-size:.75rem;
    background:#ecfdf3;
    color:#166534;
    border:1px solid rgba(22,101,52,.15);
    padding:.15rem .5rem;
    border-radius:999px;
  }
  .modal-bg{
    position:fixed; inset:0; background:rgba(0,0,0,.35);
    display:flex; align-items:center; justify-content:center;
    z-index:60;
  }
  .card    {
    background:#fff;
    border:1px solid #e5e7eb;
    border-radius:1rem;
    box-shadow:0 18px 45px rgba(15,23,42,.15);
  }
  .tbl th  { font-weight:600; color:#374151; font-size:.8rem; }
  .tbl td  { font-size:.8rem; }
</style>

<div x-data="CreateProductPage()" class="max-w-screen-xl mx-auto px-4 py-6 space-y-6">
  <form :action="action" method="post" enctype="multipart/form-data"
        @submit="serializeVariants" class="space-y-6">

    <!-- Header / actions -->
    <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
      <div>
        <h1 class="text-2xl font-bold">New Product</h1>
        <p class="mt-1 text-sm text-gray-600">
          Add a single product or use <span class="badge">Bulk Upload</span> for many.
          Purchase price + margin will auto-calc sales price.
        </p>
      </div>
      <div class="flex flex-wrap gap-2 justify-end">
        <a href="<?= $h($base) ?>/products" class="kf-btn-ghost">Cancel</a>
        <button type="button" class="kf-btn-outline" @click="openBulkModal()">
          Bulk Upload
        </button>
        <button type="submit" class="kf-btn-primary">
          Save Product
        </button>
      </div>
    </div>

    <!-- 3-column layout -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
      <!-- Column 1 -->
      <div class="space-y-4">
        <div>
          <label class="block text-sm font-medium mb-1">Name <span class="text-red-600">*</span></label>
          <input name="name" x-model="f.name" class="field" required
                 @input="onCoreChange()">
        </div>

        <div>
          <label class="block text-sm font-medium mb-1">Category <span class="text-red-600">*</span></label>
          <div class="flex gap-2">
            <select name="category_id" x-model.number="f.category_id" class="field" required
                    @change="onCoreChange()">
              <option value="">Select Category</option>
              <?php foreach ($cats as $c): ?>
                <option value="<?= (int)$c['id'] ?>"><?= $h($c['name']) ?></option>
              <?php endforeach; ?>
            </select>
            <button type="button" @click="openQuickModal('category')" class="w-10 kf-pill flex items-center justify-center">
              +
            </button>
          </div>
        </div>

        <div>
          <label class="block text-sm font-medium mb-1">Alert Quantity</label>
          <input name="low_stock_threshold" x-model.number="f.low" type="number"
                 min="0" step="1" class="field">
        </div>

        <div class="flex items-center gap-2">
          <input id="active" type="checkbox" name="is_active" value="1"
                 x-model="f.is_active" class="w-4 h-4">
          <label for="active" class="text-sm">Active</label>
        </div>
      </div>

      <!-- Column 2 -->
      <div class="space-y-4">
        <div>
          <label class="block text-sm font-medium mb-1">SKU (Auto) </label>
          <input name="sku" x-model="f.sku" class="field bg-gray-50"
                 readonly>
          <p class="mt-1 text-xs text-gray-500">
            Generated from name &amp; date. Unique SKU will be stored.
          </p>
        </div>

        <div>
          <label class="block text-sm font-medium mb-1">Base Unit <span class="text-red-600">*</span></label>
          <div class="flex gap-2">
            <select name="unit" x-model="f.unit" class="field" required>
              <option value="">Select Unit</option>
              <?php foreach ($units as $u): ?>
                <option value="<?= $h($u['code'] ?? $u['name'] ?? '') ?>">
                  <?= $h($u['name'] ?? ($u['code'] ?? '')) ?>
                </option>
              <?php endforeach; ?>
            </select>
            <button type="button" @click="openQuickModal('unit')" class="w-10 kf-pill flex items-center justify-center">
              +
            </button>
          </div>
        </div>

        <div>
          <label class="block text-sm font-medium mb-1">Purchase Price <span class="text-red-600">*</span></label>
          <input name="cost_price" x-model.number="f.cost_price" type="number" min="0" step="0.01"
                 class="field" required
                 @input="onBaseCostChange()">
        </div>

        <div>
          <label class="block text-sm font-medium mb-1">Margin (%)</label>
          <input name="margin_percent" x-model.number="f.margin" type="number" min="0" step="0.01"
                 class="field"
                 @input="onBaseMarginChange()">
        </div>

        <div>
          <label class="block text-sm font-medium mb-1">Sales Price</label>
          <input name="sale_price" x-model.number="f.sale_price" type="number" min="0" step="0.01"
                 class="field"
                 @input="onBaseSaleChange()">
          <p class="mt-1 text-xs text-gray-500">
            Changing purchase or margin will recalc this, unless you type a custom value.
          </p>
        </div>
      </div>

      <!-- Column 3 -->
      <div class="space-y-4">
        <div>
          <label class="block text-sm font-medium mb-1">Brand <span class="text-red-600">*</span></label>
          <div class="flex gap-2">
            <select name="brand_id" x-model.number="f.brand_id" class="field" required
                    @change="onCoreChange()">
              <option value="">Brand</option>
              <?php foreach ($brs as $b): ?>
                <option value="<?= (int)$b['id'] ?>"><?= $h($b['name']) ?></option>
              <?php endforeach; ?>
            </select>
            <button type="button" @click="openQuickModal('brand')" class="w-10 kf-pill flex items-center justify-center">
              +
            </button>
          </div>
        </div>

        <div>
          <label class="block text-sm font-medium mb-1">Tax</label>
          <div class="flex gap-2">
            <select name="tax_rate" x-model.number="f.tax_rate" class="field">
              <option value="">Tax</option>
              <?php foreach ($txs as $t): ?>
                <option value="<?= $h($t['rate'] ?? 0) ?>">
                  <?= $h(($t['name'] ?? 'Tax').' ('.($t['rate'] ?? 0).'%)') ?>
                </option>
              <?php endforeach; ?>
            </select>
            <button type="button" @click="openQuickModal('tax')" class="w-10 kf-pill flex items-center justify-center">
              +
            </button>
          </div>
        </div>

        <div>
          <label class="block text-sm font-medium mb-1">Barcode</label>
          <div class="flex gap-2">
            <input name="barcode" x-model="f.barcode" class="field">
            <button type="button" class="kf-btn-ghost text-xs"
                    @click="generateBarcode(true)">Auto</button>
          </div>
          <p class="mt-1 text-xs text-gray-500">
            Generated automatically after key fields, or use “Auto” to regenerate.
          </p>
        </div>

        <div>
          <label class="block text-sm font-medium mb-1">Product Image</label>
          <input type="file" name="image" accept="image/*" class="block w-full text-sm">
        </div>

        <div>
          <label class="block text-sm font-medium mb-1">Description</label>
          <textarea name="description" x-model="f.desc" rows="3" class="field"></textarea>
        </div>
      </div>
    </div>

    <!-- Attributes / Variants -->
    <div class="mt-4 border rounded-2xl p-4 bg-white shadow-sm">
      <div class="flex items-center justify-between mb-3">
        <div>
          <h2 class="font-semibold text-gray-800 text-sm">Attributes &amp; Variants</h2>
          <p class="text-xs text-gray-500">
            Keep it simple with one SKU, or define variants (size, color, etc).
          </p>
        </div>
        <label class="inline-flex items-center gap-2 text-sm">
          <input type="checkbox" name="has_variants" x-model="f.has_variants" class="w-4 h-4">
          <span>Product has variants</span>
        </label>
      </div>

      <template x-if="f.has_variants">
        <div class="space-y-4">
          <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
            <div>
              <label class="block text-sm font-medium mb-1">Variant</label>
              <div class="flex gap-2">
                <select x-model="vSel.name" class="field">
                  <option value="">Variant</option>
                  <option>Color</option>
                  <option>Size</option>
                  <option>Capacity</option>
                  <option>Style</option>
                </select>
                <button type="button" @click="openQuickModal('variant')"
                        class="w-10 kf-pill flex items-center justify-center">
                  +
                </button>
              </div>
            </div>
            <div>
              <label class="block text-sm font-medium mb-1">Variant Value</label>
              <div class="flex gap-2">
                <input x-model="vSel.value" class="field" placeholder="e.g. Red, XL, 256GB">
                <button type="button" @click="addVariant()" class="kf-btn-primary text-sm">
                  + Add
                </button>
              </div>
            </div>
          </div>

          <div class="overflow-x-auto">
            <table class="min-w-full tbl border border-gray-200 rounded-xl">
              <thead class="bg-gray-50">
                <tr>
                  <th class="px-3 py-2 text-left">Variant</th>
                  <th class="px-3 py-2 text-right">Purchase *</th>
                  <th class="px-3 py-2 text-right">Margin (%)</th>
                  <th class="px-3 py-2 text-right">Sales Price</th>
                  <th class="px-3 py-2 text-left">Barcode</th>
                  <th class="px-3 py-2"></th>
                </tr>
              </thead>
              <tbody>
                <template x-if="variants.length === 0">
                  <tr>
                    <td colspan="6" class="px-3 py-4 text-center text-gray-500 text-sm">
                      No variants yet. Add at least one value above.
                    </td>
                  </tr>
                </template>
                <template x-for="(it,i) in variants" :key="i">
                  <tr class="border-t">
                    <td class="px-3 py-2" x-text="it.name"></td>
                    <td class="px-3 py-2 text-right">
                      <input x-model.number="it.cost" type="number" min="0" step="0.01"
                             class="field w-28 text-right"
                             @input="onVarCostChange(i)">
                    </td>
                    <td class="px-3 py-2 text-right">
                      <input x-model.number="it.margin" type="number" min="0" step="0.01"
                             class="field w-24 text-right"
                             @input="onVarMarginChange(i)">
                    </td>
                    <td class="px-3 py-2 text-right">
                      <input x-model.number="it.price" type="number" min="0" step="0.01"
                             class="field w-28 text-right"
                             @input="onVarSaleChange(i)">
                    </td>
                    <td class="px-3 py-2">
                      <input x-model="it.barcode" class="field w-40 text-xs">
                    </td>
                    <td class="px-3 py-2 text-right">
                      <button type="button" @click="variants.splice(i,1)"
                              class="kf-btn-ghost text-xs">
                        Remove
                      </button>
                    </td>
                  </tr>
                </template>
              </tbody>
            </table>
          </div>
        </div>
      </template>

      <input type="hidden" name="variants_json" x-model="variantsJson">
    </div>
  </form>

  <!-- Quick-add modal (brand/category/unit/tax/variant label only – persistence wired via controllers) -->
  <template x-if="quickModal.open">
    <div class="modal-bg" @click.self="closeQuickModal()">
      <div class="card w-full max-w-md p-5">
        <div class="flex items-center justify-between">
          <h3 class="text-lg font-semibold" x-text="quickModal.title"></h3>
          <button class="kf-btn-ghost text-xs" @click="closeQuickModal()">✕</button>
        </div>
        <div class="mt-4 space-y-3">
          <label class="block text-sm font-medium">Name</label>
          <input x-model="quickModal.payload.name" class="field" placeholder="Enter name">
          <template x-if="quickModal.kind==='unit'">
            <div>
              <label class="block text-sm font-medium">Code (optional)</label>
              <input x-model="quickModal.payload.code" class="field" placeholder="e.g. pcs, kg">
            </div>
          </template>
        </div>
        <div class="mt-5 flex justify-end gap-2">
          <button class="kf-btn-ghost text-sm" @click="closeQuickModal()">Cancel</button>
          <button class="kf-btn-primary text-sm" @click="applyQuickModal()">Save</button>
        </div>
      </div>
    </div>
  </template>

  <!-- Bulk upload modal -->
  <template x-if="bulk.open">
    <div class="modal-bg" @click.self="closeBulkModal()">
      <div class="card w-full max-w-lg p-6">
        <div class="flex items-center justify-between mb-3">
          <h3 class="text-lg font-semibold">Bulk Upload Products</h3>
          <button class="kf-btn-ghost text-xs" @click="closeBulkModal()">✕</button>
        </div>
        <p class="text-sm text-gray-600 mb-3">
          Upload a CSV or Excel file. You can add or adjust attributes for each item after import.
        </p>

        <form :action="bulk.action" method="post" enctype="multipart/form-data" class="space-y-4">
          <div>
            <label class="block text-sm font-medium mb-1">File (CSV or XLSX)</label>
            <input type="file" name="file" accept=".csv,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,application/vnd.ms-excel"
                   class="block w-full text-sm">
          </div>
          <div class="flex items-center justify-between text-xs text-gray-500">
            <div>
              Columns: <span class="badge">name</span> <span class="badge">category</span>
              <span class="badge">brand</span> <span class="badge">unit</span>
              <span class="badge">purchase_price</span> <span class="badge">margin</span>
            </div>
            <!-- Template link can be wired later -->
            <a href="<?= $h($base) ?>/products/bulk-template" class="text-brand underline">
              Download template
            </a>
          </div>
          <div class="mt-4 flex justify-end gap-2">
            <button type="button" class="kf-btn-ghost text-sm" @click="closeBulkModal()">Cancel</button>
            <button type="submit" class="kf-btn-primary text-sm">Upload &amp; Import</button>
          </div>
        </form>
      </div>
    </div>
  </template>
</div>

<script>
function CreateProductPage(){
  return {
    action: <?= json_encode($action) ?>,
    bulk: { open:false, action: <?= json_encode($base.'/products/bulk-upload') ?> },
    f: {
      name:'', category_id:'', unit:'', brand_id:'',
      cost_price:null, margin:null, sale_price:null,
      sku:'', barcode:'', low:0, tax_rate:null, desc:'',
      is_active:true, has_variants:false
    },
    vSel: { name:'', value:'' },
    variants: [],
    variantsJson: '[]',
    quickModal: { open:false, kind:'', title:'', payload:{ name:'', code:'' } },

    /* ---------- SKU & barcode helpers ---------- */
    onCoreChange(){
      this.generateSku();
      this.generateBarcode(false);
    },
    generateSku(){
      if (!this.f.name) return;
      const prefix = this.f.name.replace(/[^a-zA-Z0-9]/g,'').toUpperCase().slice(0,4) || 'SKU';
      const y = (new Date()).getFullYear();
      const rand = String(Math.floor(Math.random()*99999)).padStart(5,'0');
      this.f.sku = `${prefix}-${y}-${rand}`;
    },
    generateBarcode(force){
      if (!this.f.name || !this.f.category_id || !this.f.brand_id) return;
      if (this.f.barcode && !force) return;
      const t = Date.now().toString();
      const base = t.slice(-11); // 11 digits
      const rand = String(Math.floor(Math.random()*9));
      this.f.barcode = base + rand + '0'; // dumb checksum placeholder
    },

    /* ---------- base price <-> margin ---------- */
    onBaseCostChange(){
      if (this.f.margin != null && this.f.margin !== '') {
        this.f.sale_price = this.calcPrice(this.f.cost_price, this.f.margin);
      }
      this.onCoreChange();
    },
    onBaseMarginChange(){
      if (this.f.cost_price != null && this.f.cost_price !== '') {
        this.f.sale_price = this.calcPrice(this.f.cost_price, this.f.margin);
      }
    },
    onBaseSaleChange(){
      if (this.f.cost_price != null && this.f.cost_price > 0 && this.f.sale_price != null) {
        this.f.margin = this.calcMargin(this.f.cost_price, this.f.sale_price);
      }
      this.onCoreChange();
    },
    calcPrice(cost, margin){
      cost = Number(cost)||0; margin = Number(margin)||0;
      return Number((cost * (1 + margin/100)).toFixed(2));
    },
    calcMargin(cost, price){
      cost = Number(cost)||0; price = Number(price)||0;
      if (!cost) return 0;
      return Number(((price - cost) / cost * 100).toFixed(2));
    },

    /* ---------- variants price <-> margin ---------- */
    onVarCostChange(i){
      const it = this.variants[i];
      if (!it) return;
      if (it.margin != null && it.margin !== '') {
        it.price = this.calcPrice(it.cost, it.margin);
      }
    },
    onVarMarginChange(i){
      const it = this.variants[i];
      if (!it) return;
      if (it.cost != null && it.cost !== '') {
        it.price = this.calcPrice(it.cost, it.margin);
      }
    },
    onVarSaleChange(i){
      const it = this.variants[i];
      if (!it) return;
      if (it.cost != null && it.cost > 0 && it.price != null) {
        it.margin = this.calcMargin(it.cost, it.price);
      }
    },

    /* ---------- quick-add modal (UI only) ---------- */
    openQuickModal(kind){
      const titles = {
        category:'New Category',
        brand:'New Brand',
        unit:'New Unit',
        tax:'New Tax',
        variant:'New Variant Name'
      };
      this.quickModal = {
        open:true,
        kind,
        title: titles[kind] || 'New',
        payload:{ name:'', code:'' }
      };
    },
    closeQuickModal(){ this.quickModal.open=false; },
    applyQuickModal(){
      const k = this.quickModal.kind;
      const p = this.quickModal.payload;
      if (!p.name && k!=='unit') return;

      if (k==='category'){
        const sel = document.querySelector('select[name="category_id"]');
        const opt = new Option(p.name, 'new:'+p.name, true, true);
        sel.add(opt);
        this.f.category_id = opt.value;
      } else if (k==='brand'){
        const sel = document.querySelector('select[name="brand_id"]');
        const opt = new Option(p.name, 'new:'+p.name, true, true);
        sel.add(opt);
        this.f.brand_id = opt.value;
      } else if (k==='unit'){
        const sel = document.querySelector('select[name="unit"]');
        const val = p.code || p.name;
        if (!val) return;
        const opt = new Option(p.name || val, val, true, true);
        sel.add(opt);
        this.f.unit = val;
      } else if (k==='tax'){
        const sel = document.querySelector('select[name="tax_rate"]');
        const opt = new Option(`${p.name} (custom)`, '0', true, true);
        sel.add(opt);
        this.f.tax_rate = 0;
      } else if (k==='variant'){
        if (!this.vSel.name) this.vSel.name = p.name;
      }

      this.closeQuickModal();
      this.onCoreChange();
    },

    /* ---------- variants ---------- */
    addVariant(){
      const label = (this.vSel.name && this.vSel.value)
          ? `${this.vSel.name}: ${this.vSel.value}`
          : (this.vSel.value || this.vSel.name);
      if (!label) return;
      this.variants.push({ name: label, cost:null, margin:null, price:null, barcode:'' });
      this.vSel.value='';
    },
    serializeVariants(){
      this.variantsJson = JSON.stringify(this.variants || []);
    },

    /* ---------- bulk upload ---------- */
    openBulkModal(){ this.bulk.open = true; },
    closeBulkModal(){ this.bulk.open = false; },

    /* exposed manual barcode button */
    generateBarcode(force){ this.generateBarcodeInternal(force); },
    generateBarcodeInternal(force){ this.generateBarcode(force); },
  };
}
</script>