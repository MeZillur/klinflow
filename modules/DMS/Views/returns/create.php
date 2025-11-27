<?php declare(strict_types=1);
if (!function_exists('h')) { function h($v){ return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8'); } }
$old = $old ?? [];
?>
<h2 class="text-xl font-semibold mb-3">Create Sales Return</h2>
<?php if (!empty($error)): ?>
  <div class="mb-3 rounded-lg border border-rose-200 bg-rose-50 px-3 py-2 text-rose-700 text-sm"><?= h($error) ?></div>
<?php endif; ?>

<form method="POST" action="">
  <?= function_exists('csrf_field') ? csrf_field() : '' ?>

  <div class="grid md:grid-cols-3 gap-3">
    <div>
      <label class="block text-sm mb-1">Return No</label>
      <input name="return_no" value="<?= h($old['return_no'] ?? '') ?>" class="w-full rounded-lg border px-3 py-2">
    </div>
    <div>
      <label class="block text-sm mb-1">Return Date</label>
      <input type="date" name="return_date" value="<?= h($old['return_date'] ?? date('Y-m-d')) ?>" class="w-full rounded-lg border px-3 py-2">
    </div>
    <div>
      <label class="block text-sm mb-1">Customer ID</label>
      <input type="number" name="customer_id" value="<?= h($old['customer_id'] ?? '') ?>" class="w-full rounded-lg border px-3 py-2">
    </div>
    <div>
      <label class="block text-sm mb-1">Against Sale (optional)</label>
      <input type="number" name="sale_id" value="<?= h($old['sale_id'] ?? '') ?>" class="w-full rounded-lg border px-3 py-2">
    </div>
    <div>
      <label class="block text-sm mb-1">Reason</label>
      <input name="reason" value="<?= h($old['reason'] ?? '') ?>" class="w-full rounded-lg border px-3 py-2">
    </div>
    <div class="grid grid-cols-2 gap-2">
      <div>
        <label class="block text-sm mb-1">Discount Type</label>
        <select name="discount_type" class="w-full rounded-lg border px-3 py-2">
          <option value="amount">Amount</option>
          <option value="percent">Percent</option>
        </select>
      </div>
      <div>
        <label class="block text-sm mb-1">Discount Value</label>
        <input type="number" step="0.01" name="discount_value" value="<?= h($old['discount_value'] ?? '0') ?>" class="w-full rounded-lg border px-3 py-2">
      </div>
    </div>
  </div>

  <div class="mt-4 overflow-x-auto">
    <table class="min-w-full text-sm border rounded-xl overflow-hidden" id="tbl">
      <thead class="bg-slate-50">
        <tr>
          <th class="px-3 py-2 text-left">Product</th>
          <th class="px-3 py-2 text-right w-[15%]">Qty</th>
          <th class="px-3 py-2 text-right w-[20%]">Price</th>
          <th class="px-3 py-2 text-right w-[20%]">Line</th>
          <th class="px-3 py-2 w-[6%]"></th>
        </tr>
      </thead>
      <tbody id="lines"></tbody>
      <tfoot>
        <tr><td colspan="5" class="px-3 py-2">
          <button type="button" id="add" class="px-3 py-2 rounded-lg bg-slate-100 hover:bg-slate-200">+ Add line</button>
        </td></tr>
      </tfoot>
    </table>
  </div>

  <div class="mt-4 flex justify-end">
    <button class="px-4 py-2 rounded-lg bg-emerald-600 text-white hover:bg-emerald-700">Save Return</button>
  </div>
</form>

<script>
const tbody=document.getElementById('lines');
function money(n){return (Math.round((+n||0)*100)/100).toFixed(2);}
function addLine(pid=0,name='',qty=1,price=0){
  const tr=document.createElement('tr');
  tr.innerHTML=`
    <td class="px-3 py-2">
      <input name="items[][product_name]" value="${(name||'').replace(/"/g,'&quot;')}" class="w-full rounded-lg border px-2 py-1" placeholder="Product">
      <input type="hidden" name="items[][product_id]" value="${pid||''}">
    </td>
    <td class="px-3 py-2 text-right"><input name="items[][qty]" type="number" step="0.01" value="${qty||1}" class="w-28 text-right rounded-lg border px-2 py-1"></td>
    <td class="px-3 py-2 text-right"><input name="items[][unit_price]" type="number" step="0.01" value="${price||0}" class="w-32 text-right rounded-lg border px-2 py-1"></td>
    <td class="px-3 py-2 text-right"><span class="line">0.00</span></td>
    <td class="px-3 py-2 text-right"><button type="button" class="rm px-2 py-1 rounded bg-red-50 text-red-600 hover:bg-red-100">✕</button></td>
  `;
  const q=tr.querySelector('[name="items[][qty]"]');
  const p=tr.querySelector('[name="items[][unit_price]"]');
  const l=tr.querySelector('.line');
  const u=()=>{l.textContent=money((+q.value||0)*(+p.value||0));};
  q.addEventListener('input',u); p.addEventListener('input',u);
  tr.querySelector('.rm').addEventListener('click',()=>{tr.remove();});
  tbody.appendChild(tr); u();
}
document.getElementById('add').addEventListener('click',()=>addLine());
addLine();
</script>