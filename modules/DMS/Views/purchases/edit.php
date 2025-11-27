<?php
/** @var array  $purchase */
/** @var array  $items */
/** @var string $module_base */

$h = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');

// Build initial items array for Alpine
$clientItems = [];
foreach ($items as $i) {
  $clientItems[] = [
    'key'          => bin2hex(random_bytes(4)),
    'product_id'   => (string)($i['product_id'] ?? ''),
    'product_name' => (string)($i['product_name'] ?? ''),
    'qty'          => (float)($i['qty'] ?? 0),
    'unit_price'   => (float)($i['unit_price'] ?? 0),
    'line_total'   => (float)($i['line_total'] ?? ((float)($i['qty'] ?? 0) * (float)($i['unit_price'] ?? 0))),
  ];
}
?>
<div class="p-6" x-data="purchaseEdit()">
  <!-- Header -->
  <div class="flex items-start justify-between gap-3 mb-6">
    <div class="flex items-center gap-3">
      <div class="h-10 w-10 rounded-xl bg-emerald-600 text-white grid place-items-center">
        <i class="fa-solid fa-pen-to-square text-lg"></i>
      </div>
      <div>
        <h1 class="text-2xl font-extrabold tracking-tight">Edit Purchase</h1>
        <p class="text-sm text-slate-500">Update bill details & items</p>
      </div>
    </div>
    <a href="<?= $h($module_base) ?>/purchases/<?= (int)$purchase['id'] ?>"
       class="inline-flex items-center gap-2 px-3 py-2 rounded-xl border border-slate-300 hover:bg-slate-50">
      <i class="fa-solid fa-eye"></i> View
    </a>
  </div>

  <form method="post" action="<?= $h($module_base) ?>/purchases/<?= (int)$purchase['id'] ?>" @submit="return beforeSubmit($event)">
    <!-- Bill meta -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-5">
      <label class="block">
        <span class="text-sm font-medium text-slate-700">Bill No <span class="text-rose-600">*</span></span>
        <input type="text" name="bill_no" x-model="form.bill_no" required
               class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 focus:outline-none focus:ring-2 focus:ring-emerald-400">
      </label>

      <label class="block">
        <span class="text-sm font-medium text-slate-700">Bill Date</span>
        <input type="date" name="bill_date" x-model="form.bill_date"
               class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 focus:outline-none focus:ring-2 focus:ring-emerald-400">
      </label>

      <label class="block">
        <span class="text-sm font-medium text-slate-700">Status</span>
        <select name="status" x-model="form.status"
                class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 focus:outline-none focus:ring-2 focus:ring-emerald-400">
          <option value="draft">Draft (don’t post stock)</option>
          <option value="confirmed">Confirmed (post stock-in)</option>
          <option value="cancelled">Cancelled</option>
        </select>
      </label>
    </div>

    <!-- Dealer -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
      <div class="md:col-span-2">
        <span class="text-sm font-medium text-slate-700">Dealer</span>
        <div class="relative mt-1" @keydown.escape="dealer.suggestions=[]">
          <input type="hidden" name="dealer_id" :value="form.dealer_id">
          <input type="text" name="dealer_name" x-model="form.dealer_name" autocomplete="off"
                 @input.debounce.250ms="lookupDealer()"
                 @focus="form.dealer_name && lookupDealer()"
                 class="w-full rounded-xl border border-slate-300 px-3 py-2 pr-9 focus:outline-none focus:ring-2 focus:ring-emerald-400">
          <i class="fa-solid fa-building-user text-slate-400 absolute right-3 top-1/2 -translate-y-1/2"></i>

          <template x-if="dealer.suggestions.length">
            <div class="absolute z-10 mt-2 w-full rounded-xl border border-slate-200 bg-white shadow-lg max-h-64 overflow-auto">
              <template x-for="d in dealer.suggestions" :key="d.id">
                <button type="button"
                        class="w-full text-left px-3 py-2 hover:bg-emerald-50"
                        @click="pickDealer(d)">
                  <div class="font-medium" x-text="d.name"></div>
                  <div class="text-xs text-slate-500" x-text="(d.code?('#'+d.code+' · '):'') + (d.phone||'')"></div>
                </button>
              </template>
            </div>
          </template>
        </div>
      </div>

      <label class="block">
        <span class="text-sm font-medium text-slate-700">Notes</span>
        <textarea name="notes" x-model="form.notes" rows="2"
                  class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 focus:outline-none focus:ring-2 focus:ring-emerald-400"></textarea>
      </label>
    </div>

    <!-- Items -->
    <div class="rounded-2xl border border-slate-200 overflow-hidden bg-white">
      <div class="px-4 py-3 bg-slate-50 flex items-center justify-between">
        <div class="font-semibold text-slate-700"><i class="fa-solid fa-list-ul mr-2"></i>Items</div>
        <button type="button" @click="addRow()"
                class="inline-flex items-center gap-2 px-3 py-1.5 rounded-lg bg-emerald-600 text-white hover:bg-emerald-700">
          <i class="fa-solid fa-plus"></i> Add line
        </button>
      </div>

      <div class="overflow-x-auto">
        <table class="min-w-full text-sm">
          <thead class="bg-white">
            <tr class="text-left text-slate-600">
              <th class="px-4 py-2">Product / Description</th>
              <th class="px-4 py-2 w-28">Qty</th>
              <th class="px-4 py-2 w-32">Unit Price</th>
              <th class="px-4 py-2 w-36 text-right">Line Total</th>
              <th class="px-2 py-2 w-10"></th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100" x-ref="tbody">
            <template x-for="(row, i) in form.items" :key="row.key">
              <tr class="hover:bg-slate-50">
                <td class="px-4 py-2">
                  <input type="hidden" :name="`items[${i}][product_id]`" :value="row.product_id">
                  <input type="text" :name="`items[${i}][product_name]`" x-model="row.product_name"
                         class="w-full rounded-lg border border-slate-300 px-3 py-2 focus:outline-none focus:ring-2 focus:ring-emerald-300">
                </td>
                <td class="px-4 py-2">
                  <input type="number" min="0" step="0.01" :name="`items[${i}][qty]`" x-model.number="row.qty"
                         @input="recalc(i)"
                         class="w-24 rounded-lg border border-slate-300 px-3 py-2 text-right tabular-nums focus:outline-none focus:ring-2 focus:ring-emerald-300">
                </td>
                <td class="px-4 py-2">
                  <input type="number" min="0" step="0.01" :name="`items[${i}][unit_price]`" x-model.number="row.unit_price"
                         @input="recalc(i)"
                         class="w-28 rounded-lg border border-slate-300 px-3 py-2 text-right tabular-nums focus:outline-none focus:ring-2 focus:ring-emerald-300">
                </td>
                <td class="px-4 py-2 text-right tabular-nums">
                  <input type="hidden" :name="`items[${i}][line_total]`" :value="row.line_total.toFixed(2)">
                  ৳ <span x-text="money(row.line_total)"></span>
                </td>
                <td class="px-2 py-2">
                  <button type="button" class="h-8 w-8 grid place-items-center rounded-lg hover:bg-rose-50"
                          @click="removeRow(i)" title="Remove">
                    <i class="fa-solid fa-xmark text-rose-600"></i>
                  </button>
                </td>
              </tr>
            </template>
          </tbody>
          <tfoot class="bg-slate-50">
            <tr>
              <td class="px-4 py-3 font-semibold text-slate-600" colspan="3">Subtotal</td>
              <td class="px-4 py-3 text-right font-bold">৳ <span x-text="money(totals.subtotal)"></span></td>
              <td></td>
            </tr>
            <tr>
              <td class="px-4 py-3 font-semibold text-slate-600" colspan="3">Grand Total</td>
              <td class="px-4 py-3 text-right text-xl font-extrabold">৳ <span x-text="money(totals.grand)"></span></td>
              <td></td>
            </tr>
          </tfoot>
        </table>
      </div>
    </div>

    <!-- Actions -->
    <div class="mt-6 flex flex-col sm:flex-row gap-3 sm:items-center sm:justify-between">
      <p class="text-sm text-slate-500">
        Tip: Press <span class="px-1.5 py-0.5 rounded bg-slate-100 border border-slate-200">Enter</span> to add a row.
      </p>
      <div class="flex gap-3">
        <button type="submit" name="status" value="draft"
                class="inline-flex items-center gap-2 px-4 py-2 rounded-xl border border-slate-300 hover:bg-slate-50">
          <i class="fa-regular fa-floppy-disk"></i> Save as Draft
        </button>
        <button type="submit" name="status" value="confirmed"
                class="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-emerald-600 text-white hover:bg-emerald-700">
          <i class="fa-solid fa-check-circle"></i> Save & Confirm
        </button>
      </div>
    </div>
  </form>
</div>

<!-- Alpine (if shell didn’t already load it) -->
<script>window.Alpine||document.write('<script src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js" defer><\/script>');</script>

<script>
function purchaseEdit(){
  const init = {
    id: <?= (int)$purchase['id'] ?>,
    bill_no: <?= json_encode((string)($purchase['bill_no'] ?? '')) ?>,
    bill_date: <?= json_encode((string)($purchase['bill_date'] ?? date('Y-m-d'))) ?>,
    status: <?= json_encode((string)($purchase['status'] ?? 'draft')) ?>,
    dealer_id: <?= json_encode((string)($purchase['dealer_id'] ?? '')) ?>,
    dealer_name: <?= json_encode((string)($purchase['dealer_name'] ?? '')) ?>,
    notes: <?= json_encode((string)($purchase['notes'] ?? '')) ?>,
    items: <?= json_encode($clientItems, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) ?>,
  };

  return {
    form: structuredClone(init),
    dealer: { suggestions: [], timer: null },
    totals: { subtotal: 0, grand: 0 },

    addRow(){
      this.form.items.push({ key: randKey(), product_id:'', product_name:'', qty:1, unit_price:0, line_total:0 });
      this.$nextTick(()=> this.$refs.tbody.querySelector('tr:last-child input[type=text]')?.focus());
      this.recalc();
    },
    removeRow(i){
      this.form.items.splice(i,1);
      if(this.form.items.length===0) this.addRow();
      this.recalc();
    },
    recalc(i){
      if(Number.isInteger(i)){
        const r = this.form.items[i];
        r.qty = parseFloat(r.qty||0);
        r.unit_price = parseFloat(r.unit_price||0);
        r.line_total = +(r.qty * r.unit_price).toFixed(2);
      }
      this.totals.subtotal = this.form.items.reduce((s,r)=> s + (parseFloat(r.line_total)||0), 0);
      this.totals.grand = this.totals.subtotal;
    },
    money(n){ return (parseFloat(n)||0).toFixed(2); },

    lookupDealer(){
      const q = (this.form.dealer_name||'').trim();
      if(!q){ this.dealer.suggestions=[]; return; }
      clearTimeout(this.dealer.timer);
      this.dealer.timer = setTimeout(async ()=>{
        try{
          const res = await fetch(<?= json_encode($module_base) ?> + '/api/lookup/dealers?q=' + encodeURIComponent(q));
          const j = await res.json();
          this.dealer.suggestions = Array.isArray(j.items) ? j.items : [];
        }catch(e){ this.dealer.suggestions=[]; }
      }, 120);
    },
    pickDealer(d){
      this.form.dealer_id = d.id || '';
      this.form.dealer_name = d.name || '';
      this.dealer.suggestions = [];
    },

    beforeSubmit(ev){
      if(!this.form.bill_no.trim()){ alert('Bill no is required'); return false; }
      const hasLine = this.form.items.some(r => (r.product_name||'').trim() && parseFloat(r.qty)>0);
      if(!hasLine){ alert('Please add at least one line.'); return false; }
      this.form.items.forEach((_,i)=> this.recalc(i));
      const clicked = ev.submitter?.value;
      if(clicked){ document.querySelector('select[name="status"]').value = clicked; }
      return true;
    }
  };
}
function randKey(){ try{ return crypto.getRandomValues(new Uint32Array(1))[0].toString(36); }catch(e){ return Math.random().toString(36).slice(2); } }
</script>