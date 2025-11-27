<?php
/** @var array $currencies @var string $module_base */
$h=fn($v)=>htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');
$base=rtrim((string)($module_base??'/apps/hotelflow'),'/');
$curr=$currencies ?? ['USD'];
?>
<div class="max-w-[1000px] mx-auto" x-data="inv()">
  <div class="flex items-center justify-between mb-4">
    <h1 class="text-2xl font-extrabold">Create Invoice</h1>
    <a href="<?= $h($base) ?>/billing/invoices" class="px-3 py-2 rounded-lg border">Cancel</a>
  </div>

  <form method="post" action="<?= $h($base) ?>/billing/invoices" class="space-y-6">
    <!-- Header -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 rounded-xl border p-4">
      <div>
        <label class="text-sm text-slate-600">Issued on</label>
        <input type="date" name="issued_at" value="<?= $h(date('Y-m-d')) ?>" class="mt-1 w-full px-3 py-2 rounded-lg border border-slate-300">
      </div>
      <div>
        <label class="text-sm text-slate-600">Currency</label>
        <select name="currency" class="mt-1 w-full px-3 py-2 rounded-lg border border-slate-300">
          <?php foreach ($curr as $ccy): ?>
            <option value="<?= $h($ccy) ?>"><?= $h($ccy) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div>
        <label class="text-sm text-slate-600">Status</label>
        <select name="status" class="mt-1 w-full px-3 py-2 rounded-lg border border-slate-300">
          <option value="issued">Issued</option>
          <option value="draft">Draft</option>
          <option value="paid">Paid</option>
          <option value="void">Void</option>
        </select>
      </div>
    </div>

    <!-- Bill To -->
    <div class="rounded-xl border p-4">
      <div class="font-semibold mb-3">Bill To</div>
      <div class="grid grid-cols-1 md:grid-cols-4 gap-3">
        <div>
          <label class="text-sm text-slate-600">Type</label>
          <select name="bill_to_type" x-model="billType" class="mt-1 w-full px-3 py-2 rounded-lg border border-slate-300">
            <option value="walkin">Walk-in (Name only)</option>
            <option value="guest">Guest (ID)</option>
            <option value="company">Company (ID)</option>
          </select>
        </div>
        <div x-show="billType==='guest'">
          <label class="text-sm text-slate-600">Guest ID</label>
          <input name="guest_id" placeholder="e.g. 123"
                 class="mt-1 w-full px-3 py-2 rounded-lg border border-slate-300">
        </div>
        <div x-show="billType==='company'">
          <label class="text-sm text-slate-600">Company ID</label>
          <input name="company_id" placeholder="e.g. 45"
                 class="mt-1 w-full px-3 py-2 rounded-lg border border-slate-300">
        </div>
        <div>
          <label class="text-sm text-slate-600">Bill-to Name</label>
          <input name="bill_to_name" placeholder="Walk-in / display name"
                 class="mt-1 w-full px-3 py-2 rounded-lg border border-slate-300">
        </div>
      </div>
    </div>

    <!-- Lines -->
    <div class="rounded-xl border p-4 overflow-auto">
      <div class="flex items-center justify-between mb-2">
        <div class="font-semibold">Line Items</div>
        <button type="button" @click="addLine()" class="px-3 py-1.5 rounded-lg border hover:bg-slate-50">
          <i class="fa-solid fa-plus mr-1"></i> Add Line
        </button>
      </div>

      <table class="min-w-[900px] w-full text-sm">
        <thead class="bg-slate-50">
          <tr>
            <th class="px-2 py-2 text-left w-[40%]">Description</th>
            <th class="px-2 py-2 text-right w-[10%]">Qty</th>
            <th class="px-2 py-2 text-right w-[15%]">Rate</th>
            <th class="px-2 py-2 text-right w-[10%]">Tax %</th>
            <th class="px-2 py-2 text-right w-[15%]">Amount</th>
            <th class="px-2 py-2 w-[10%]"></th>
          </tr>
        </thead>
        <tbody>
          <template x-for="(l,i) in lines" :key="i">
            <tr class="border-t">
              <td class="px-2 py-2">
                <input :name="`li_desc[${i}]`" x-model="l.desc" placeholder="Room charge / F&B / etc."
                       class="w-full px-2 py-1.5 rounded border border-slate-300">
              </td>
              <td class="px-2 py-2 text-right">
                <input type="number" step="0.01" min="0" :name="`li_qty[${i}]`" x-model.number="l.qty"
                       class="w-full text-right px-2 py-1.5 rounded border border-slate-300">
              </td>
              <td class="px-2 py-2 text-right">
                <input type="number" step="0.01" min="0" :name="`li_rate[${i}]`" x-model.number="l.rate"
                       class="w-full text-right px-2 py-1.5 rounded border border-slate-300">
              </td>
              <td class="px-2 py-2 text-right">
                <input type="number" step="0.01" min="0" :name="`li_tax[${i}]`" x-model.number="l.tax"
                       class="w-full text-right px-2 py-1.5 rounded border border-slate-300">
              </td>
              <td class="px-2 py-2 text-right align-middle">
                <span x-text="fmt(lineTotal(l))"></span>
              </td>
              <td class="px-2 py-2 text-center">
                <button type="button" @click="removeLine(i)" class="px-2 py-1 rounded border hover:bg-slate-50">
                  <i class="fa-solid fa-trash"></i>
                </button>
              </td>
            </tr>
          </template>

          <tr class="border-t bg-slate-50">
            <td class="px-2 py-2 text-right font-semibold" colspan="4">Subtotal</td>
            <td class="px-2 py-2 text-right"><span x-text="fmt(subtotal())"></span></td>
            <td></td>
          </tr>
          <tr class="border-t bg-slate-50">
            <td class="px-2 py-2 text-right font-semibold" colspan="4">Tax</td>
            <td class="px-2 py-2 text-right"><span x-text="fmt(taxTotal())"></span></td>
            <td></td>
          </tr>
          <tr class="border-t bg-slate-100">
            <td class="px-2 py-2 text-right font-bold" colspan="4">Total</td>
            <td class="px-2 py-2 text-right font-bold"><span x-text="fmt(grandTotal())"></span></td>
            <td></td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Notes + Actions -->
    <div class="grid grid-cols-1 md:grid-cols-[1fr_auto] gap-4">
      <textarea name="notes" rows="3" placeholder="Notes (optional)"
                class="w-full px-3 py-2 rounded-lg border border-slate-300"></textarea>

      <div class="flex md:flex-col gap-2 justify-end">
        <button class="px-4 py-2 rounded-lg text-white w-full md:w-auto" style="background:var(--brand)">
          Save Invoice
        </button>
      </div>
    </div>
  </form>
</div>

<script>
function inv(){
  return {
    billType: 'walkin',
    lines: [{desc:'Room Charge', qty:1, rate:0, tax:0}],
    lineTotal(l){
      const net = (Number(l.qty)||0) * (Number(l.rate)||0);
      const tax = net * ((Number(l.tax)||0)/100);
      return net + tax;
    },
    subtotal(){
      return this.lines.reduce((s,l)=> s + ((Number(l.qty)||0)*(Number(l.rate)||0)), 0);
    },
    taxTotal(){
      return this.lines.reduce((s,l)=>{
        const net = (Number(l.qty)||0) * (Number(l.rate)||0);
        return s + net*((Number(l.tax)||0)/100);
      },0);
    },
    grandTotal(){ return this.subtotal() + this.taxTotal(); },
    addLine(){ this.lines.push({desc:'',qty:1,rate:0,tax:0}); },
    removeLine(i){ this.lines.splice(i,1); },
    fmt(v){ return (Number(v)||0).toFixed(2); }
  }
}
</script>