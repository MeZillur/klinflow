<?php declare(strict_types=1);
use Shared\Csrf;
$h=fn($v)=>htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');
$base=rtrim((string)($module_base ?? '/apps/dms'),'/');
$csrf = class_exists(Csrf::class)? Csrf::token() : '';
?>
<div class="p-5 space-y-4">
  <div class="flex items-center justify-between">
    <h1 class="text-xl font-semibold text-slate-900 dark:text-slate-100">Master Challan (Multiple Invoices)</h1>
    <a href="<?= $h($base) ?>/challan" class="px-3 py-1.5 rounded-lg border text-sm">Back to Dispatch Board</a>
  </div>

  <!-- Invoice lookup (uses global KF.lookup if present) -->
  <div class="rounded-lg border p-4 space-y-3">
    <div class="text-sm opacity-80">Add invoices to include:</div>
    <div class="flex gap-2">
      <input id="inv_add" type="number" placeholder="Invoice ID" class="border rounded px-3 py-1.5 w-40 bg-white dark:bg-slate-900">
      <button id="btn_add" class="px-3 py-1.5 rounded bg-[#228B22] text-white">Add</button>
    </div>
    <div id="inv_list" class="text-sm text-slate-700 dark:text-slate-200"></div>
  </div>

  <form method="post" action="<?= $h($base) ?>/challan/master" class="space-y-4" id="frm">
    <input type="hidden" name="_csrf" value="<?= $h($csrf) ?>">

    <div class="overflow-x-auto rounded-xl border">
      <table class="min-w-full text-sm" id="tbl">
        <thead class="bg-slate-50 dark:bg-slate-800/60 text-slate-600 dark:text-slate-300">
          <tr>
            <th class="px-3 py-2"><input type="checkbox" id="chk_all"></th>
            <th class="px-3 py-2">Invoice</th>
            <th class="px-3 py-2">SL</th>
            <th class="px-3 py-2">Item</th>
            <th class="px-3 py-2 text-right">Unit Cost</th>
            <th class="px-3 py-2 text-right">Ordered</th>
            <th class="px-3 py-2 text-right">Dispatch Qty</th>
            <th class="px-3 py-2 text-right">Total Cost</th>
          </tr>
        </thead>
        <tbody id="lines" class="divide-y"></tbody>
        <tfoot class="bg-slate-50 dark:bg-slate-800/60">
          <tr>
            <td colspan="7" class="px-3 py-2 text-right font-semibold">Payment received now</td>
            <td class="px-3 py-2 text-right">
              <input id="pay_now" name="payment_received" type="number" step="0.01" min="0"
                     class="w-28 text-right border rounded px-2 py-1 bg-white dark:bg-slate-900">
            </td>
          </tr>
        </tfoot>
      </table>
    </div>

    <div class="flex justify-end">
      <button class="px-4 py-2 rounded-lg bg-[#228B22] text-white">Create Master Challan</button>
    </div>
  </form>
</div>

<script>
(function(){
  const $ = s=>document.querySelector(s);
  const $$= s=>Array.from(document.querySelectorAll(s));
  const toNum=v=>parseFloat(v||'0')||0;
  const base = <?= json_encode($base) ?>;

  let seq=1;

  async function fetchLines(invoiceId){
    // reuse prepare endpoint as JSON source by adding &as=json
    const url = base + '/challan/prepare?invoice_id=' + encodeURIComponent(invoiceId) + '&as=json';
    const res = await fetch(url, {headers:{'Accept':'application/json'}});
    if(!res.ok) throw new Error('load failed');
    return await res.json(); // {invoice:{...}, lines:[...]}
  }

  function addRows(inv, lines){
    const tbody = $('#lines');
    lines.forEach((ln, idx)=>{
      const sid=ln.sale_item_id, up=toNum(ln.unit_price), qty=toNum(ln.qty);
      const tr=document.createElement('tr');
      tr.innerHTML = `
        <td class="px-3 py-2">
          <input type="checkbox" class="chk" checked>
          <input type="hidden" name="master[${inv.id}][${sid}][product_id]"   value="${ln.product_id??''}">
          <input type="hidden" name="master[${inv.id}][${sid}][product_name]" value="${(ln.product_name||'').replace(/"/g,'&quot;')}">
          <input type="hidden" name="master[${inv.id}][${sid}][unit_price]"   value="${up}">
        </td>
        <td class="px-3 py-2">${inv.sale_no || ('#'+inv.id)}</td>
        <td class="px-3 py-2">${seq++}</td>
        <td class="px-3 py-2">${ln.product_name||''}</td>
        <td class="px-3 py-2 text-right">${up.toFixed(2)}</td>
        <td class="px-3 py-2 text-right">${qty.toFixed(2)}</td>
        <td class="px-3 py-2 text-right">
          <input type="number" step="0.01" min="0" max="${qty}" value="${qty}"
                 name="master[${inv.id}][${sid}][qty]"
                 class="w-24 text-right border rounded px-2 py-1 bg-white dark:bg-slate-900">
        </td>
        <td class="px-3 py-2 text-right total">0.00</td>
      `;
      tr.dataset.up=String(up);
      tbody.appendChild(tr);
    });
    recalc();
  }

  function recalc(){
    $$('#lines tr').forEach(tr=>{
      const chk=tr.querySelector('.chk');
      const up = toNum(tr.dataset.up);
      const qty= toNum(tr.querySelector('input[name$="[qty]"]')?.value);
      const cell=tr.querySelector('.total');
      const val = chk?.checked ? (up*qty) : 0;
      if (cell) cell.textContent = val.toFixed(2);
    });
  }

  $('#btn_add')?.addEventListener('click', async e=>{
    e.preventDefault();
    const id = parseInt($('#inv_add')?.value||'0',10);
    if (!id) return;
    try {
      const data = await fetchLines(id);
      if (!data || !data.invoice) return alert('Invoice not found');
      // badge
      const item = document.createElement('div');
      item.className='inline-flex items-center gap-2 bg-slate-100 dark:bg-slate-800 px-2 py-1 rounded';
      item.textContent = (data.invoice.sale_no || ('#'+data.invoice.id));
      $('#inv_list').appendChild(item);
      addRows(data.invoice, data.lines||[]);
      $('#inv_add').value='';
    } catch(err){ alert('Failed to load invoice'); }
  });

  $('#chk_all')?.addEventListener('change', e=>{
    $$('.chk').forEach(c=>c.checked=e.target.checked);
    recalc();
  });
  document.addEventListener('input', e=>{
    if (e.target.matches('.chk, input[type="number"]')) recalc();
  });
})();
</script>