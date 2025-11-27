<?php
declare(strict_types=1);

/** @var array  $org */
/** @var string $module_base */
/** @var string $title */
$h = fn($s)=>htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');

$orgName = $org['name'] ?? 'Organization';
$today   = date('Y-m-d');
$invoice = 'TEMP-'.date('His'); // your server will replace after save
?>
<!doctype html>
<meta charset="utf-8">
<title><?= $h($title ?? 'New Sale') ?></title>

<style>
  :root{ --brand:#228B22 }
  html,body{font-family:ui-sans-serif,system-ui,-apple-system,Segoe UI,Roboto,Arial,sans-serif;background:#fff;color:#111827}
  .page{max-width:1100px;margin:16px auto;padding:16px}
  .card{border:1px solid #E5E7EB;border-radius:12px;background:#fff}
  .row{display:flex;gap:12px;flex-wrap:wrap}
  .col{flex:1 1 280px}
  .label{font-size:12px;color:#6B7280;margin-bottom:6px}
  .text{padding:10px 12px;border:1px solid #E5E7EB;border-radius:10px;width:100%}
  .btn{display:inline-flex;align-items:center;gap:8px;padding:10px 14px;border-radius:10px;border:1px solid #E5E7EB;background:#fff;cursor:pointer}
  .btn.primary{background:var(--brand);color:#fff;border-color:var(--brand)}
  .btn.ghost{background:#fff}
  .btn.warn{background:#FEE2E2;border-color:#FCA5A5;color:#991B1B}
  .toolbar{display:flex;gap:8px;justify-content:flex-end}
  .table{width:100%;border-collapse:collapse}
  .table th,.table td{padding:10px;border-bottom:1px solid #F3F4F6;text-align:left}
  .table th{font-size:12px;color:#6B7280;font-weight:600}
  .right{text-align:right}
  .muted{color:#6B7280}
  .pill{display:inline-block;padding:3px 8px;border-radius:999px;background:#EEF2FF;color:#3730A3;font-size:12px}
  .suggest{position:absolute;z-index:40;background:#fff;border:1px solid #E5E7EB;border-radius:10px;overflow:hidden;box-shadow:0 10px 30px rgba(0,0,0,.08)}
  .suggest ul{list-style:none;padding:4px;margin:0;max-height:260px;overflow:auto}
  .suggest li{padding:8px 10px;cursor:pointer}
  .suggest li:hover{background:#F3F4F6}
  .hint{font-size:12px;color:#9CA3AF}
  .total-card{padding:12px;border:1px dashed #E5E7EB;border-radius:12px}

  /* ---------- 80mm receipt print ---------- */
  @media print {
    @page { size: 80mm auto; margin: 0 }
    body{background:#fff}
    .page{max-width:none;margin:0;padding:0}
    .no-print{display:none!important}
    .receipt{display:block!important}
  }
  /* simple receipt (hidden on screen) */
  .receipt{display:none;padding:8px}
  .r-line{display:flex;justify-content:space-between}
  .r-table{width:100%;border-collapse:collapse}
  .r-table th,.r-table td{padding:4px 0;border-bottom:1px dashed #DDD;font-size:12px}
  .r-center{text-align:center}
</style>

<div class="page">
  <div class="row no-print">
    <div class="col">
      <h2 style="margin:0 0 6px 0"><?= $h($orgName) ?></h2>
      <div class="muted">Sales → Create</div>
    </div>
    <div class="col toolbar" style="align-items:flex-start">
      <button class="btn ghost" type="button" id="btn-hold">Hold</button>
      <button class="btn warn"  type="button" id="btn-clear">Clear</button>
      <button class="btn"       type="button" id="btn-print">Print</button>
      <button class="btn primary" type="submit" form="sale-form">Save &amp; Charge</button>
    </div>
  </div>

  <form id="sale-form" class="card" method="post" action="<?= $h($module_base.'/sales') ?>" style="padding:16px">
    <!-- header -->
    <div class="row">
      <div class="col" style="min-width:320px;position:relative">
        <div class="label">Customer</div>
        <input class="text" id="c-name" name="customer_name" autocomplete="off" placeholder="Search or add customer (type ≥ 2)…">
        <div id="c-suggest" class="suggest" style="display:none;width:100%"></div>
        <input type="hidden" id="c-id"   name="customer_id">
        <div class="hint">Enter name / mobile / code</div>
      </div>

      <div class="col">
        <div class="label">Invoice #</div>
        <input class="text" name="invoice_no" value="<?= $h($invoice) ?>">
      </div>
      <div class="col">
        <div class="label">Sale Date</div>
        <input class="text" type="datetime-local" name="sold_at" value="<?= $h(date('Y-m-d\TH:i')) ?>">
      </div>
    </div>

    <!-- products -->
    <div class="row" style="margin-top:14px">
      <div class="col" style="position:relative;min-width:420px;flex:2">
        <div class="label">Scan / Product</div>
        <input class="text" id="p-search" autocomplete="off" placeholder="Scan barcode or type product name / SKU…">
        <div id="p-suggest" class="suggest" style="display:none;width:100%"></div>
        <div class="hint">Press Enter to add selected item</div>
      </div>
      <div class="col" style="max-width:180px">
        <div class="label">Quantity</div>
        <input class="text" id="p-qty" value="1">
      </div>
      <div class="col" style="max-width:180px">
        <div class="label">Price</div>
        <input class="text" id="p-price" value="">
      </div>
      <div class="col" style="max-width:160px;display:flex;align-items:flex-end">
        <button class="btn" type="button" id="btn-add">Add Item</button>
      </div>
    </div>

    <!-- line items table -->
    <div style="margin-top:14px;overflow:auto">
      <table class="table" id="items">
        <thead>
          <tr>
            <th style="width:36px">#</th>
            <th>Item</th>
            <th style="width:110px" class="right">Qty</th>
            <th style="width:140px" class="right">Price</th>
            <th style="width:140px" class="right">Line Total</th>
            <th style="width:44px"></th>
          </tr>
        </thead>
        <tbody></tbody>
      </table>
      <!-- post back payload -->
      <input type="hidden" name="items_json" id="items-json">
    </div>

    <!-- totals + payment -->
    <div class="row" style="margin-top:14px">
      <div class="col" style="flex:2;min-width:360px">
        <div class="label">Notes</div>
        <textarea class="text" name="notes" rows="4" placeholder="Optional notes…"></textarea>
      </div>

      <div class="col" style="min-width:320px">
        <div class="total-card">
          <div style="display:flex;justify-content:space-between;margin-bottom:6px">
            <div class="muted">Subtotal</div>
            <div id="t-subtotal">0.00</div>
          </div>
          <div style="display:flex;justify-content:space-between;margin-bottom:6px">
            <div class="muted">Discount</div>
            <div>
              <input class="text" style="width:120px;text-align:right" id="t-discount" value="0.00">
            </div>
          </div>
          <div style="display:flex;justify-content:space-between;margin-bottom:6px">
            <div class="muted">Tax</div>
            <div>
              <input class="text" style="width:120px;text-align:right" id="t-tax" value="0.00">
            </div>
          </div>
          <div style="height:1px;background:#E5E7EB;margin:10px 0"></div>
          <div style="display:flex;justify-content:space-between;font-weight:700">
            <div>Total</div>
            <div id="t-total">0.00</div>
          </div>
        </div>

        <div class="row" style="margin-top:10px">
          <div class="col">
            <div class="label">Paid Now</div>
            <input class="text" id="t-paid" value="0.00">
          </div>
          <div class="col">
            <div class="label">Balance</div>
            <div class="text" id="t-balance" style="background:#F9FAFB">0.00</div>
          </div>
        </div>
      </div>
    </div>
  </form>

  <!-- Receipt (print only) -->
  <div class="receipt">
    <div class="r-center" style="font-weight:700"><?= $h($orgName) ?></div>
    <div class="r-center" style="font-size:12px;margin-bottom:6px">Invoice <?= $h($invoice) ?> — <?= $h($today) ?></div>
    <table class="r-table" id="r-items">
      <thead><tr><th>Item</th><th class="right">Qty</th><th class="right">Total</th></tr></thead>
      <tbody></tbody>
    </table>
    <div style="margin-top:6px">
      <div class="r-line"><span>Subtotal</span><strong id="r-subtotal">0.00</strong></div>
      <div class="r-line"><span>Discount</span><strong id="r-discount">0.00</strong></div>
      <div class="r-line"><span>Tax</span><strong id="r-tax">0.00</strong></div>
      <div class="r-line" style="border-top:1px dashed #DDD;margin-top:6px;padding-top:4px">
        <span>Total</span><strong id="r-total">0.00</strong>
      </div>
    </div>
    <div class="r-center" style="margin-top:8px;font-size:12px">Thanks for your purchase!</div>
  </div>
</div>

<script>
(function(){
  const $ = sel => document.querySelector(sel);
  const $$ = sel => document.querySelectorAll(sel);

  // -------- state --------
  const items = []; // {id, name, sku, price, qty}
  const fmt = n => (Number(n||0)).toFixed(2);

  const els = {
    itemsBody:   $('#items tbody'),
    itemsJson:   $('#items-json'),
    subtotal:    $('#t-subtotal'),
    discount:    $('#t-discount'),
    tax:         $('#t-tax'),
    total:       $('#t-total'),
    paid:        $('#t-paid'),
    balance:     $('#t-balance'),
    // product search
    pSearch:     $('#p-search'),
    pSuggest:    $('#p-suggest'),
    pQty:        $('#p-qty'),
    pPrice:      $('#p-price'),
    // customer
    cName:       $('#c-name'),
    cId:         $('#c-id'),
    cSuggest:    $('#c-suggest'),
  };

  // ---------- totals ----------
  function recalc(){
    let sub = 0;
    items.forEach(it => sub += it.qty * it.price);
    const disc = Number(els.discount.value||0);
    const tax  = Number(els.tax.value||0);
    const total = sub - disc + tax;
    const paid  = Number(els.paid.value||0);
    const bal   = total - paid;

    els.subtotal.textContent = fmt(sub);
    els.total.textContent    = fmt(total);
    els.balance.textContent  = fmt(bal);
    els.itemsJson.value      = JSON.stringify(items);

    // receipt mirror
    $('#r-subtotal').textContent = fmt(sub);
    $('#r-discount').textContent = fmt(disc);
    $('#r-tax').textContent      = fmt(tax);
    $('#r-total').textContent    = fmt(total);

    // receipt rows
    const rBody = $('#r-items tbody');
    rBody.innerHTML = '';
    items.forEach(it=>{
      const tr = document.createElement('tr');
      tr.innerHTML = `<td>${escapeHtml(it.name)}</td><td class="right">${fmt(it.qty)}</td><td class="right">${fmt(it.qty*it.price)}</td>`;
      rBody.appendChild(tr);
    });
  }

  // ---------- table render ----------
  function renderTable(){
    els.itemsBody.innerHTML = '';
    items.forEach((it, i) => {
      const tr = document.createElement('tr');
      tr.innerHTML = `
        <td>${i+1}</td>
        <td><div style="font-weight:600">${escapeHtml(it.name)}</div><div class="hint">${escapeHtml(it.sku||'')}</div></td>
        <td class="right"><input class="text" style="width:100px;text-align:right" value="${fmt(it.qty)}" data-idx="${i}" data-f="qty"></td>
        <td class="right"><input class="text" style="width:120px;text-align:right" value="${fmt(it.price)}" data-idx="${i}" data-f="price"></td>
        <td class="right">${fmt(it.qty*it.price)}</td>
        <td class="right"><button class="btn warn" type="button" data-rm="${i}">×</button></td>
      `;
      els.itemsBody.appendChild(tr);
    });
    recalc();
  }

  // inline edits & remove
  els.itemsBody.addEventListener('input', (e)=>{
    const idx = e.target.getAttribute('data-idx');
    const f   = e.target.getAttribute('data-f');
    if (idx!==null && f) {
      items[idx][f] = Number(e.target.value||0);
      renderTable();
    }
  });
  els.itemsBody.addEventListener('click', (e)=>{
    const rm = e.target.getAttribute('data-rm');
    if (rm!==null){ items.splice(Number(rm),1); renderTable(); }
  });

  // totals edit
  [els.discount, els.tax, els.paid].forEach(el=>el.addEventListener('input', recalc));

  // ---------- customer typeahead ----------
  wireTypeahead({
    input: els.cName,
    box:   els.cSuggest,
    endpoint: '<?= $h($module_base) ?>/api/customers/search', // implement server-side
    toLabel: r => `${r.name} ${r.mobile?('· '+r.mobile):''}`,
    onPick:  r => { els.cId.value = r.id; els.cName.value = r.name; }
  });

  // ---------- product typeahead ----------
  wireTypeahead({
    input: els.pSearch,
    box:   els.pSuggest,
    endpoint: '<?= $h($module_base) ?>/api/items/search', // implement server-side
    toLabel: r => `${r.name} ${r.sku?('· '+r.sku):''}`,
    onPick:  r => {
      els.pSearch.value = r.name;
      els.pPrice.value  = r.price ?? r.mrp ?? '';
      els.pQty.focus();
    }
  });

  // add item
  $('#btn-add').addEventListener('click', addFromInputs);
  els.pSearch.addEventListener('keydown', (e)=>{
    if (e.key==='Enter'){ e.preventDefault(); addFromInputs(); }
  });

  function addFromInputs(){
    const name = els.pSearch.value.trim();
    const qty  = Number(els.pQty.value||1);
    const price= Number(els.pPrice.value||0);
    if (!name || qty<=0) return;
    items.push({id:null, name, sku:'', qty, price});
    els.pSearch.value = ''; els.pQty.value = '1'; els.pPrice.value = '';
    renderTable();
    els.pSearch.focus();
  }

  // clear / hold / print
  $('#btn-clear').addEventListener('click', ()=>{
    if (confirm('Clear current cart?')) { items.splice(0); renderTable(); }
  });
  $('#btn-hold').addEventListener('click', ()=>{
    alert('Hold queue will be wired later.');
  });
  $('#btn-print').addEventListener('click', ()=>{ window.print(); });

  // helpers
  function wireTypeahead({input, box, endpoint, toLabel, onPick}){
    let timer=null, lastQ='';
    function hide(){ box.style.display='none'; box.innerHTML=''; }
    function show(){ box.style.display='block'; }
    input.addEventListener('input', ()=>{
      const q = input.value.trim();
      if (q.length<2){ hide(); return; }
      if (q===lastQ) return; lastQ=q;
      clearTimeout(timer);
      timer = setTimeout(async ()=>{
        try{
          const res = await fetch(endpoint+'?q='+encodeURIComponent(q), {headers:{'Accept':'application/json'}});
          if (!res.ok) throw new Error('HTTP '+res.status);
          const list = await res.json();
          if (!Array.isArray(list) || !list.length){ hide(); return; }
          box.innerHTML = '<ul>'+list.slice(0,20).map(r=>(
            `<li data-row='${JSON.stringify(r).replace(/'/g,'&apos;')}'>${escapeHtml(toLabel(r))}</li>`
          )).join('')+'</ul>';
          show();
        }catch(e){ hide(); }
      }, 120);
    });
    box.addEventListener('click', e=>{
      const li = e.target.closest('li'); if (!li) return;
      const row = JSON.parse(li.getAttribute('data-row').replace(/&apos;/g,"'"));
      onPick(row); hide();
    });
    document.addEventListener('click', (e)=>{ if (!box.contains(e.target) && e.target!==input) hide(); });
  }

  function escapeHtml(s){
    return String(s??'')
      .replaceAll('&','&amp;')
      .replaceAll('<','&lt;')
      .replaceAll('>','&gt;')
      .replaceAll('"','&quot;')
      .replaceAll("'",'&#039;');
  }

  // kick things off
  renderTable();
})();
</script>