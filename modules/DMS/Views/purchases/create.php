<?php
/** @var string $module_base */
/** @var string $next_no */
/** @var string $today */
/** @var array  $endpoints */
$h = fn($s)=>htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
$epSup = $endpoints['suppliers'] ?? ($module_base.'/suppliers.lookup.json');
$epPrd = $endpoints['products']  ?? ($module_base.'/products.lookup.json');
?>
<div class="p-6" 
     x-data="OrderCreate(
       '<?= $h($module_base) ?>',
       {sup:'<?= $h($epSup) ?>', prod:'<?= $h($epPrd) ?>'},
       '<?= $h($next_no) ?>',
       '<?= $h($today) ?>'
     )">

  <!-- Header -->
  <div class="flex items-center justify-between mb-6">
    <div>
      <h1 class="text-2xl font-semibold">Create Order</h1>
      <p class="text-sm text-slate-500">Flow: add items → pick supplier → confirm</p>
    </div>
    <a href="<?= $h($module_base) ?>/orders"
       class="inline-flex items-center gap-2 rounded-xl border border-slate-300 px-3 py-2 hover:bg-slate-50">
      <span class="i-lucide-arrow-left"></span> Back
    </a>
  </div>

  <!-- Meta -->
  <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
    <div>
      <label class="text-sm font-medium text-slate-700">Order No</label>
      <input type="text" :value="displayNo" readonly
             class="mt-1 w-full rounded-xl border border-slate-300 bg-slate-50 px-3 py-2">
      <p class="text-xs text-slate-500 mt-1">Auto-generated on save.</p>
    </div>

    <label class="block">
      <span class="text-sm font-medium text-slate-700">Order Date</span>
      <input type="date" x-model="form.order_date" name="order_date"
             class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 focus:ring-2 focus:ring-emerald-400">
    </label>

    <label class="block">
      <span class="text-sm font-medium text-slate-700">Status</span>
      <select x-model="form.status" name="status"
              class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 focus:ring-2 focus:ring-emerald-400">
        <option value="draft">Draft</option>
        <option value="confirmed">Confirmed</option>
        <option value="cancelled">Cancelled</option>
      </select>
    </label>
  </div>

  <!-- Order Form -->
  <form method="post" action="<?= $h($module_base) ?>/orders" @submit="return beforeSubmit($event)">

    <!-- Items -->
    <div class="rounded-2xl border border-slate-200 bg-white">
      <div class="flex items-center justify-between px-4 py-3 bg-slate-50">
        <div class="font-medium">Order Items</div>
        <button type="button" @click="addRow()"
                class="inline-flex items-center gap-2 rounded-lg bg-emerald-600 px-3 py-1.5 text-white hover:bg-emerald-700">
          <span class="i-lucide-plus"></span> Add line
        </button>
      </div>

      <div class="overflow-x-auto">
        <table class="min-w-full text-sm">
          <thead class="bg-white">
            <tr class="text-left text-slate-600">
              <th class="px-4 py-2 w-64">Product (search)</th>
              <th class="px-4 py-2">Name</th>
              <th class="px-4 py-2 w-28">Qty</th>
              <th class="px-4 py-2 w-32">Unit Price</th>
              <th class="px-4 py-2 w-36 text-right">Line Total</th>
              <th class="px-2 py-2 w-10"></th>
            </tr>
          </thead>

          <tbody class="divide-y divide-slate-100" x-ref="tbody">
            <template x-for="(row, i) in form.items" :key="row.key">
              <tr>
                <!-- Product lookup -->
                <td class="px-4 py-2">
                  <input type="hidden" :name="`items[${i}][product_id]`"   x-model="row.product_id">
                  <input type="hidden" :name="`items[${i}][product_code]`" x-model="row.product_code">
                  <input type="hidden" :name="`items[${i}][unit]`"         x-model="row.unit">
                  <input type="hidden" :name="`items[${i}][unit_price]`"   x-model="row.unit_price">

                  <input type="text"
                         class="w-full rounded-lg border border-slate-300 px-3 py-2"
                         placeholder="Search product..."
                         :data-kf-lookup="endpoints.prod"
                         :data-kf-target-id="`items[${i}][product_id]`"
                         :data-kf-target-name="`items[${i}][product_name]`"
                         :data-kf-target-code="`items[${i}][product_code]`"
                         :data-kf-target-unit="`items[${i}][unit]`"
                         :data-kf-target-price="`items[${i}][unit_price]`"
                         @kf-picked.window="
                           $nextTick(()=>{
                             const f = new FormData($el.form);
                             row.product_id = f.get(`items[${i}][product_id]`) || '';
                             row.product_name = f.get(`items[${i}][product_name]`) || '';
                             row.product_code = f.get(`items[${i}][product_code]`) || '';
                             row.unit = f.get(`items[${i}][unit]`) || 'PCS';
                             row.unit_price = parseFloat(f.get(`items[${i}][unit_price]`)||0);
                             recalc(i);
                           });
                         ">
                </td>

                <!-- Name -->
                <td class="px-4 py-2">
                  <input type="text" :name="`items[${i}][product_name]`" x-model="row.product_name"
                         class="w-full rounded-lg border border-slate-300 px-3 py-2">
                </td>

                <!-- Qty -->
                <td class="px-4 py-2">
                  <input type="number" min="0" step="0.01" :name="`items[${i}][qty]`"
                         x-model.number="row.qty" @input="recalc(i)"
                         class="w-24 rounded-lg border border-slate-300 px-3 py-2 text-right">
                </td>

                <!-- Unit Price -->
                <td class="px-4 py-2">
                  <input type="number" min="0" step="0.01" :name="`items[${i}][unit_price]`"
                         x-model.number="row.unit_price" @input="recalc(i)"
                         class="w-28 rounded-lg border border-slate-300 px-3 py-2 text-right">
                </td>

                <!-- Line Total -->
                <td class="px-4 py-2 text-right tabular-nums">
                  <input type="hidden" :name="`items[${i}][line_total]`" :value="row.line_total.toFixed(2)">
                  ৳ <span x-text="money(row.line_total)"></span>
                </td>

                <td class="px-2 py-2">
                  <button type="button" @click="removeRow(i)"
                          class="h-8 w-8 grid place-items-center rounded-lg hover:bg-rose-50">
                    <span class="i-lucide-x text-rose-600"></span>
                  </button>
                </td>
              </tr>
            </template>
          </tbody>

          <tfoot class="bg-slate-50">
            <tr>
              <td colspan="4" class="px-4 py-3 text-right font-medium text-slate-600">Subtotal</td>
              <td class="px-4 py-3 text-right font-semibold">৳ <span x-text="money(totals.subtotal)"></span></td>
              <td></td>
            </tr>
            <tr>
              <td colspan="4" class="px-4 py-3 text-right font-medium text-slate-600">Grand Total</td>
              <td class="px-4 py-3 text-right text-lg font-extrabold">৳ <span x-text="money(totals.grand)"></span></td>
              <td></td>
            </tr>
          </tfoot>
        </table>
      </div>
    </div>

    <!-- Supplier -->
    <div class="mt-6 rounded-2xl border border-slate-200 bg-white">
      <div class="px-4 py-3 bg-slate-50 font-medium">Supplier</div>
      <div class="p-4 grid grid-cols-1 md:grid-cols-3 gap-4">
        <div>
          <label class="text-sm font-medium text-slate-700">Supplier</label>
          <input type="hidden" name="supplier_id" x-model="form.supplier_id">
          <input type="text"
                 class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2"
                 placeholder="Search suppliers..."
                 :data-kf-lookup="endpoints.sup"
                 data-kf-limit="30"
                 :data-kf-target-id="'supplier_id'"
                 :data-kf-target-name="'supplier_name'"
                 @kf-picked.window="
                   $nextTick(()=>{
                     const f = new FormData($el.form);
                     form.supplier_id = f.get('supplier_id') || '';
                     form.supplier_name = f.get('supplier_name') || '';
                   });
                 ">
        </div>
        <div class="md:col-span-2">
          <label class="text-sm font-medium text-slate-700">Supplier Name</label>
          <input type="text" name="supplier_name" x-model="form.supplier_name"
                 class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2">
        </div>
      </div>
    </div>

    <!-- Actions -->
    <div class="mt-6 flex flex-col sm:flex-row gap-3 sm:items-center sm:justify-between">
      <p class="text-sm text-slate-500">
        Tip: Press <kbd class="px-1.5 py-0.5 rounded border border-slate-300 bg-slate-100">Enter</kbd> to add a row.
      </p>
      <div class="flex gap-3">
        <button type="submit" name="status" value="draft"
                class="inline-flex items-center gap-2 rounded-xl border border-slate-300 px-4 py-2 hover:bg-slate-50">
          <span class="i-lucide-save"></span> Save as Draft
        </button>
        <button type="submit" name="status" value="confirmed"
                class="inline-flex items-center gap-2 rounded-xl bg-emerald-600 px-4 py-2 text-white hover:bg-emerald-700">
          <span class="i-lucide-check-circle-2"></span> Save & Confirm
        </button>
      </div>
    </div>
  </form>
</div>

<script>
function OrderCreate(base, endpoints, prefillNo, today){
  return {
    base, endpoints,
    displayNo: prefillNo,
    form: {
      order_date: today,
      status: 'draft',
      supplier_id: '',
      supplier_name: '',
      items: [
        { key: rnd(), product_id:'', product_name:'', product_code:'', unit:'PCS',
          qty:1, unit_price:0, line_total:0 }
      ]
    },
    totals: { subtotal:0, grand:0 },

    money(n){ return (parseFloat(n)||0).toFixed(2); },

    addRow(){
      this.form.items.push({ key: rnd(), product_id:'', product_name:'', product_code:'', unit:'PCS', qty:1, unit_price:0, line_total:0 });
      this.$nextTick(()=>{
        if (window.KF && typeof KF.rescan === 'function') KF.rescan(this.$refs.tbody);
      });
    },
    removeRow(i){
      this.form.items.splice(i,1);
      if(this.form.items.length===0) this.addRow();
      this.recalc();
    },
    recalc(i){
      if(Number.isInteger(i)){
        const r = this.form.items[i];
        r.line_total = +(r.qty * r.unit_price).toFixed(2);
      }
      this.totals.subtotal = this.form.items.reduce((s,r)=> s + (+r.line_total||0), 0);
      this.totals.grand = this.totals.subtotal;
    },
    beforeSubmit(){
      const ok = this.form.items.some(r => (r.product_name && parseFloat(r.qty)>0));
      if(!ok){ alert('Add at least one valid product.'); return false; }
      this.form.items.forEach((_,i)=> this.recalc(i));
      return true;
    },
    init(){
      if(window.KF && typeof KF.rescan==='function') KF.rescan(this.$el);
    }
  }
}
function rnd(){ try{ return crypto.getRandomValues(new Uint32Array(1))[0].toString(36); }catch{ return Math.random().toString(36).slice(2); } }
</script>