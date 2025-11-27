<?php
declare(strict_types=1);
/**
 * modules/DMS/Views/sales/create.php
 * Invoice form with robust customer + product typeahead.
 * - Product suggest shows CODE — NAME + price, fills unit price on pick.
 * - Suggest popups sit above table (z-index fix).
 * - Totals auto-recompute; clean items[] payload on submit.
 */
$base = $module_base ?? '';
function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
?>
<div class="flex items-center justify-between mb-4">
  <h1 class="text-xl font-semibold">Create Invoice</h1>
  <a href="<?= h($base) ?>/sales" class="text-sm" style="color:var(--brand)">↩ Invoices list</a>
</div>

<form id="invForm" method="POST" action="<?= h($base) ?>/sales" class="space-y-6">
  <?= function_exists('csrf_field') ? csrf_field() : '' ?>
  <input type="hidden" name="action" id="__action" value="save">
  <input type="hidden" name="customer_id" id="customer_id" value="">
  <input type="hidden" name="customer_name" id="customer_name" value="">
  <input type="hidden" name="discount_type" id="__disc_type" value="amount">
  <input type="hidden" name="grand_total" id="grand_total" value="0.00">

  <!-- Header Row -->
  <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
    <div>
      <label class="block text-sm mb-1">Customer</label>
      <div class="flex items-center gap-2">
        <div class="relative flex-1">
          <input id="customer_search" type="text" class="w-full rounded-lg border px-3 py-2 pr-10"
                 placeholder="Type name / CID / phone…" autocomplete="off">
          <i class="fa-solid fa-magnifying-glass absolute right-3 top-1/2 -translate-y-1/2 text-slate-400"></i>
          <div id="customer_suggest" class="mt-1 hidden rounded-lg border bg-white shadow max-h-56 overflow-auto"></div>
        </div>
        <span id="customer_cid" class="text-xs font-mono text-slate-500">CID-000000</span>
      </div>
    </div>
    <div>
      <label class="block text-sm mb-1">Invoice No</label>
      <input type="text" name="sale_no" id="sale_no"
             value="INV-<?= date('Y') ?>-" class="w-full rounded-lg border px-3 py-2" readonly>
    </div>
    <div>
      <label class="block text-sm mb-1">Date</label>
      <input type="date" name="sale_date" value="<?= h(date('Y-m-d')) ?>" class="w-full rounded-lg border px-3 py-2">
    </div>
  </div>

  <!-- Status & Discount -->
  <div class="grid grid-cols-1 lg:grid-cols-2 gap-3">
    <div>
      <label class="block text-sm mb-1">Status</label>
      <div class="flex gap-2">
        <label class="status-tab tab--on" data-status="draft">Draft</label>
        <label class="status-tab" data-status="confirmed">Confirmed</label>
        <label class="status-tab" data-status="cancelled">Cancelled</label>
      </div>
      <input type="hidden" name="status" id="status" value="draft">
    </div>
    <div class="grid grid-cols-2 gap-3">
      <div>
        <label class="block text-sm mb-1">Discount Type</label>
        <div class="flex gap-2">
          <button type="button" class="tab tab--on" data-disc="amount">Amount</button>
          <button type="button" class="tab" data-disc="percent">Percent</button>
        </div>
      </div>
      <div>
        <label class="block text-sm mb-1">Discount Value</label>
        <input type="number" step="0.01" min="0" name="discount_value" id="discount_value"
               class="w-full rounded-lg border px-3 py-2" value="0">
      </div>
    </div>
  </div>

  <!-- Product Lines -->
  <div class="overflow-x-auto">
    <table class="min-w-full text-sm border rounded-lg overflow-hidden">
      <thead class="bg-slate-50">
        <tr>
          <th class="px-3 py-2 text-left w-[42%]">Product</th>
          <th class="px-3 py-2 text-right w-[14%]">Qty</th>
          <th class="px-3 py-2 text-right w-[20%]">Unit Price</th>
          <th class="px-3 py-2 text-right w-[20%]">Line Total</th>
          <th class="px-3 py-2 w-[4%]"></th>
        </tr>
      </thead>
      <tbody id="lines"></tbody>
      <tfoot>
        <tr>
          <td colspan="5" class="px-3 py-2">
            <button type="button" id="addLine" class="px-3 py-2 rounded-lg bg-slate-100 hover:bg-slate-200">
              + Add line
            </button>
          </td>
        </tr>
      </tfoot>
    </table>
  </div>

  <!-- Totals -->
  <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
    <div class="p-3 rounded-lg bg-slate-50">
      <div class="text-xs text-slate-500">Items Subtotal</div>
      <div class="text-lg font-semibold" id="t_sub">0.00</div>
    </div>
    <div class="p-3 rounded-lg bg-slate-50">
      <div class="text-xs text-slate-500">Discount</div>
      <div class="text-lg font-semibold" id="t_disc">0.00</div>
    </div>
    <div class="p-3 rounded-lg bg-slate-50">
      <div class="text-xs text-slate-500">Grand Total</div>
      <div class="text-lg font-semibold" id="t_grand">0.00</div>
    </div>
  </div>

  <!-- Actions -->
  <div class="flex items-center justify-end gap-3">
    <button type="submit"
            class="px-4 py-2 rounded-lg btn-brand"
            onclick="document.getElementById('__action').value='save'">
      Save Invoice
    </button>
    <button type="submit"
            class="px-4 py-2 rounded-lg btn-brand"
            onclick="document.getElementById('__action').value='save_print'">
      Save & Print
    </button>
  </div>
</form>

<style>
  .tab, .status-tab { background:#fff; border:1px solid #e5e7eb; padding:6px 12px; border-radius:8px; cursor:pointer; }
  .tab--on, .status-tab.tab--on { background:var(--brand); color:#fff; border-color:var(--brand); }

  .btn-brand {
    background: var(--brand);
    color: #fff;
    transition: background-color .15s ease, color .15s ease;
  }
  .btn-brand:hover {
    background: #dc2626; /* red hover */
    color: #fff;
  }

  /* Suggest popups above table rows */
  .psuggest{ z-index:9999 !important; max-height:14rem; overflow:auto; box-shadow:0 8px 20px rgba(0,0,0,.08); }
  td .relative{ position:relative; }
  .badge-id { font-size:10px; padding:.125rem .375rem; border-radius:.375rem; background:#f1f5f9; color:#475569; }
</style>

<script>
/* ======================== Helpers & constants ======================== */
const money = n => (Math.round((+n || 0)*100)/100).toFixed(2);
const debounce = (fn,ms=250)=>{let t;return(...a)=>{clearTimeout(t);t=setTimeout(()=>fn(...a),ms)}};
const API_BASE = '<?=h($base)?>';

/* Robust endpoints (customers/products). Products first tries OrdersController::productsJson */
const API_PATHS = {
  customers: [
    '/api/lookup/customers','/lookup/customers',
    '/api/lookup?entity=customers','/api/lookup?type=customers',
    '/api/customers','/customers.json'
  ],
  products: [
    '/api/products',             // canonical (with strong price fallbacks)
    '/products.json',
    '/products/lookup.json',
    '/api/lookup/products','/lookup/products',
    '/api/lookup?entity=products','/api/lookup?type=products'
  ]
};

function extractItems(json){
  if (!json) return [];
  if (Array.isArray(json)) return json;
  if (Array.isArray(json.items)) return json.items;
  if (Array.isArray(json.data)) return json.data;
  if (Array.isArray(json.results)) return json.results;
  if (Array.isArray(json.products)) return json.products;
  return [];
}

async function fetchLookup(kind, qOrParams) {
  const paths = API_PATHS[kind] || [];
  for (const p of paths) {
    const u = new URL(API_BASE + p, location.origin);
    if (typeof qOrParams === 'string') {
      if (!p.includes('?')) u.searchParams.set('q', qOrParams);
      else u.search = (u.search ? u.search + '&' : '?') + 'q=' + encodeURIComponent(qOrParams);
    } else if (qOrParams && typeof qOrParams === 'object') {
      Object.entries(qOrParams).forEach(([k,v])=>u.searchParams.set(k, String(v)));
    }
    try {
      const r = await fetch(u.toString(), { headers: { 'Accept':'application/json', 'X-Requested-With':'fetch' } });
      if (!r.ok) continue;
      const js = await r.json().catch(()=>null);
      const arr = extractItems(js);
      if (arr.length) return arr;
    } catch { /* try next */ }
  }
  return [];
}

/* Normalize product to {id, name, code, price, unit_price,...} */
function normalizeProduct(p){
  if (!p || typeof p!=='object') return null;
  const id   = Number(p.id ?? p.product_id ?? 0);
  const name = String(p.name ?? p.product_name ?? p.title ?? '').trim();
  const price = Number(p.price ?? p.unit_price ?? p.sale_price ?? p.selling_price ?? p.mrp ?? p.rate ?? 0);
  const sku   = String(p.sku ?? p.product_sku ?? '').trim();
  const code  = String(p.code ?? p.product_code ?? p.pid ?? '').trim();
  const unit  = String(p.unit ?? p.uom ?? p.uom_name ?? '').trim();
  const barcode = String(p.barcode ?? p.ean ?? p.upc ?? '').trim();
  const stock = (p.stock_qty != null) ? Number(p.stock_qty) : null;
  if (!name && !id) return null;
  return { id, name, code, price, unit_price: price, sku, unit, barcode, stock_qty: stock };
}

/* Probe by id once if price came as 0 */
async function probePriceById(productId){
  const rows = await fetchLookup('products', { id: productId, limit: 1 });
  const p = rows && rows[0] ? normalizeProduct(rows[0]) : null;
  return p ? (p.price || p.unit_price || 0) : 0;
}

/* ======================== Customer Typeahead ======================== */
const custBox  = document.getElementById('customer_suggest');
const custIn   = document.getElementById('customer_search');
const custId   = document.getElementById('customer_id');
const custName = document.getElementById('customer_name');
const custCID  = document.getElementById('customer_cid');
let C_SUG = [];

function renderCustomerSuggest(list){
  C_SUG = Array.isArray(list) ? list : [];
  if (!C_SUG.length){ custBox.classList.add('hidden'); custBox.innerHTML=''; return; }
  custBox.innerHTML = C_SUG.map(c=>`
    <div data-id="${c.id}" class="px-3 py-2 hover:bg-slate-50 cursor-pointer">
      <div class="font-medium">${c.name||''}</div>
      <div class="text-xs text-slate-500">${c.phone||''}${c.address?(' · '+(c.address||'')) : ''}</div>
    </div>
  `).join('');
  custBox.classList.remove('hidden');
}

async function searchCustomers(q){
  const hits = await fetchLookup('customers', q);
  renderCustomerSuggest(hits.slice(0,30));
}

custIn.addEventListener('input', debounce(()=>{
  const q = (custIn.value||'').trim();
  if (!q){ renderCustomerSuggest([]); return; }
  searchCustomers(q);
}, 300));

custBox.addEventListener('click', e=>{
  const row = e.target.closest('[data-id]'); if(!row) return;
  const id = row.dataset.id;
  const c  = C_SUG.find(x=>String(x.id)===String(id)) || {};
  custIn.value  = c.name || '';
  custId.value  = c.id || '';
  custName.value= c.name || '';
  custCID.textContent = 'CID-' + String(c.id||0).padStart(6,'0');
  custBox.classList.add('hidden');
});
document.addEventListener('click', e=>{
  if (!custBox.contains(e.target) && e.target !== custIn) custBox.classList.add('hidden');
});

/* ======================== Invoice Auto Number ======================== */
(async function nextInvoiceNo(){
  try{
    const res = await fetch('<?=h($base)?>/api/nextno?type=invoice');
    const js = await res.json();
    if(js?.no) document.getElementById('sale_no').value = js.no;
  }catch(e){}
})();

/* ======================== Tabs ======================== */
document.querySelectorAll('.tab').forEach(btn=>{
  btn.addEventListener('click',()=>{
    document.querySelectorAll('.tab').forEach(x=>x.classList.remove('tab--on'));
    btn.classList.add('tab--on');
    document.getElementById('__disc_type').value = btn.dataset.disc;
    recomputeTotals();
  });
});
document.querySelectorAll('.status-tab').forEach(btn=>{
  btn.addEventListener('click',()=>{
    document.querySelectorAll('.status-tab').forEach(x=>x.classList.remove('tab--on'));
    btn.classList.add('tab--on');
    document.getElementById('status').value = btn.dataset.status;
  });
});

/* ======================== Product Lines with Typeahead ======================== */
const tbody = document.getElementById('lines');

document.addEventListener('click', (e)=>{
  document.querySelectorAll('.psuggest').forEach(box=>{
    const input = box.parentElement?.querySelector('.psearch');
    if (!box.contains(e.target) && e.target !== input) box.classList.add('hidden');
  });
}, true);

async function lookupProducts(q){
  let hits = await fetchLookup('products', q);
  // normalize + dedupe
  const out=[], seen=new Set();
  for (const raw of hits) {
    const np = normalizeProduct(raw); if(!np) continue;
    const key = np.id ? 'id:'+np.id : 'n:'+np.name.toLowerCase();
    if (seen.has(key)) continue;
    seen.add(key); out.push(np);
  }
  return out.slice(0,30);
}

function addLine(pid=0, pname='', qty=1, price=0){
  const esc = s => String(s ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/"/g,'&quot;');

  const tr = document.createElement('tr');
  tr.innerHTML = `
    <td class="px-3 py-2">
      <div class="relative">
        <input type="text" class="psearch w-full rounded-lg border px-2 py-1" placeholder="Search product…" value="${esc(pname)}" autocomplete="off">
        <div class="psuggest hidden absolute left-0 right-0 mt-1 rounded-lg border bg-white shadow"></div>
      </div>
      <input type="hidden" class="pid" name="items[][product_id]" value="${pid||0}">
      <input type="hidden" class="pname-hidden" name="items[][product_name]" value="${esc(pname)}">
    </td>
    <td class="px-3 py-2 text-right">
      <input name="items[][qty]" type="number" step="0.01" min="0" value="${qty||1}" class="qty w-24 text-right border rounded-lg px-2 py-1">
    </td>
    <td class="px-3 py-2 text-right">
      <input name="items[][unit_price]" type="number" step="0.01" min="0" value="${price||0}" class="price w-28 text-right border rounded-lg px-2 py-1">
    </td>
    <td class="px-3 py-2 text-right">
      <span class="line">0.00</span>
      <input type="hidden" name="items[][line_total]" class="line_input" value="0.00">
    </td>
    <td class="px-3 py-2 text-right">
      <button type="button" class="rm px-2 py-1 rounded bg-red-50 text-red-600">✕</button>
    </td>
  `;

  const pidInp  = tr.querySelector('.pid');
  const nameInp = tr.querySelector('.psearch');
  const nameHid = tr.querySelector('.pname-hidden');
  const box     = tr.querySelector('.psuggest');
  const qInp    = tr.querySelector('.qty');
  const pInp    = tr.querySelector('.price');
  const ln      = tr.querySelector('.line');
  const li      = tr.querySelector('.line_input');
  const rm      = tr.querySelector('.rm');

  const sync = () => {
    const val = (+qInp.value||0) * (+pInp.value||0);
    ln.textContent = money(val);
    li.value = money(val);
    recomputeTotals();
  };
  qInp.addEventListener('input', sync);
  pInp.addEventListener('input', sync);
  rm.addEventListener('click', ()=>{ tr.remove(); recomputeTotals(); });

  let P_SUG = [];
  const renderPSuggest = (rows) => {
    const list = Array.isArray(rows) ? rows : [];
    if (!list.length){ box.classList.add('hidden'); box.innerHTML=''; return; }
    box.innerHTML = list.map(p=>{
      const labelCode = (p.code||'').trim() ? p.code : ('PID-'+(p.id||''));
      const unitPrice = +(p.price||p.unit_price||0);
      const nm = (p.name||'').replace(/"/g,'&quot;'); // safe for data-attr
      return `
        <div class="ps-item px-3 py-2 hover:bg-slate-50 cursor-pointer flex items-center justify-between"
             data-id="${p.id||''}" data-name="${nm}" data-price="${unitPrice}">
          <div>
            <div class="font-medium">${labelCode ? labelCode : ''} — ${p.name||''}</div>
            <div class="text-xs text-slate-500">৳ ${money(unitPrice)}${p.sku?(' · '+p.sku) : ''}</div>
          </div>
          <span class="badge-id">${labelCode}</span>
        </div>
      `;
    }).join('');
    box.classList.remove('hidden');
  };

  const doSearch = debounce(async ()=>{
    const q = (nameInp.value||'').trim().toLowerCase();
    if (!q){ box.classList.add('hidden'); box.innerHTML=''; return; }
    const hits = await lookupProducts(q);
    renderPSuggest(hits);
  }, 200);

  nameInp.addEventListener('input', doSearch);
  nameInp.addEventListener('focus', doSearch);

  box.addEventListener('click', async e=>{
    const row = e.target.closest('.ps-item'); if(!row) return;
    const id    = +row.dataset.id;
    const name  = row.dataset.name || '';
    let   price = +row.dataset.price || 0;

    // probe once by id if price unknown
    if (price <= 0 && id > 0) {
      price = await probePriceById(id);
    }

    pidInp.value   = id || 0;
    nameInp.value  = name;
    nameHid.value  = name;
    pInp.value     = price; // always fill picked price
    box.classList.add('hidden');
    sync();
  });

  tbody.appendChild(tr);
  sync();
}

/* Add line button + initial row */
document.getElementById('addLine').addEventListener('click', ()=> addLine());
addLine();

/* ======================== Totals ======================== */
function recomputeTotals(){
  let sub=0;
  tbody.querySelectorAll('tr').forEach(tr=>{
    const q=+(tr.querySelector('.qty')?.value||0);
    const p=+(tr.querySelector('.price')?.value||0);
    sub+=q*p;
    const li=tr.querySelector('.line_input'); const ln=tr.querySelector('.line');
    const v=q*p; if(li) li.value=money(v); if(ln) ln.textContent=money(v);
  });
  const dt=document.getElementById('__disc_type').value;
  const dv=+(document.getElementById('discount_value').value||0);
  const disc=dt==='percent'?Math.min(sub,sub*(dv/100)):Math.min(sub,dv);
  const grand=Math.max(0,sub-disc);
  document.getElementById('t_sub').textContent=money(sub);
  document.getElementById('t_disc').textContent=money(disc);
  document.getElementById('t_grand').textContent=money(grand);
  document.getElementById('grand_total').value=money(grand);
}
document.getElementById('discount_value').addEventListener('input',recomputeTotals);

/* ======================== Submit: ensure names/ids ======================== */
const form = document.getElementById('invForm');
form.addEventListener('submit', ()=>{
  // Ensure we store a customer_name even if user typed only display text
  if (!document.getElementById('customer_name').value) {
    document.getElementById('customer_name').value = (document.getElementById('customer_search').value||'').trim();
  }
  // (Fields are already named items[][field] in DOM rows, so no extra packing is needed)
  recomputeTotals();
});
</script>