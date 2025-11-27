<?php
$h=fn($v)=>htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8'); $base=rtrim((string)($module_base ?? '/apps/hotelflow'),'/');
?>
<div class="max-w-[1000px] mx-auto space-y-4" x-data="poCreate()">
  <div class="flex items-center justify-between">
    <h1 class="text-2xl font-extrabold">New Purchase</h1>
    <a class="px-3 py-2 rounded-lg border hover:bg-slate-50" href="<?= $h($base) ?>/inventory/purchases">Back</a>
  </div>

  <form method="post" action="<?= $h($base) ?>/inventory/purchases/create" class="space-y-4">
    <div class="grid md:grid-cols-4 gap-3">
      <input name="reference" placeholder="Reference #" class="px-3 py-2 rounded-lg border"/>
      <input name="supplier" placeholder="Supplier" class="px-3 py-2 rounded-lg border"/>
      <input name="doc_date" type="date" class="px-3 py-2 rounded-lg border"/>
      <input name="currency" value="USD" class="px-3 py-2 rounded-lg border"/>
    </div>

    <div class="rounded-xl border overflow-auto">
      <table class="min-w-[800px] w-full text-sm">
        <thead class="bg-slate-50"><tr>
          <th class="px-3 py-2 text-left">Product</th><th class="px-3 py-2 text-right">Qty</th><th class="px-3 py-2 text-right">Price</th><th class="px-3 py-2 text-right">Total</th><th class="px-3 py-2"></th>
        </tr></thead>
        <tbody>
          <template x-for="(row,i) in rows" :key="i">
            <tr class="border-t">
              <td class="px-3 py-2">
                <select class="w-full border rounded-lg px-2 py-1.5" :name="'product_id['+i+']'">
                  <option value="">— Select —</option>
                  <?php foreach($products as $p): ?>
                  <option value="<?= (int)$p['id'] ?>"><?= $h((string)$p['name']) ?> (<?= $h((string)$p['sku']) ?>)</option>
                  <?php endforeach; ?>
                </select>
              </td>
              <td class="px-3 py-2">
                <input class="w-full border rounded-lg px-2 py-1.5 text-right" :name="'qty['+i+']'" type="number" min="0" step="0.001" x-model.number="row.qty" @input="recalc"/>
              </td>
              <td class="px-3 py-2">
                <input class="w-full border rounded-lg px-2 py-1.5 text-right" :name="'price['+i+']'" type="number" min="0" step="0.001" x-model.number="row.price" @input="recalc"/>
              </td>
              <td class="px-3 py-2 text-right" x-text="(row.qty*row.price).toFixed(2)"></td>
              <td class="px-3 py-2 text-right"><button type="button" class="px-2 py-1 rounded-lg border" @click="rows.splice(i,1);recalc()">Remove</button></td>
            </tr>
          </template>
        </tbody>
      </table>
    </div>

    <div class="flex items-center justify-between">
      <button type="button" class="px-3 py-2 rounded-lg border" @click="rows.push({qty:1,price:0});recalc()">Add line</button>
      <div class="text-right text-lg font-semibold">Total: <span x-text="total.toFixed(2)"></span></div>
    </div>

    <div class="flex justify-end">
      <button class="px-4 py-2 rounded-lg text-white" style="background:var(--brand)">Save Purchase</button>
    </div>
  </form>
</div>
<script>
function poCreate(){ return { rows:[{qty:1,price:0}], total:0, recalc(){ this.total=this.rows.reduce((s,r)=>s+(Number(r.qty||0)*Number(r.price||0)),0); } } }
</script>