<?php
declare(strict_types=1);

if (!function_exists('h')) { function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); } }

/** Injected by BaseController->view() */
$module_base = $module_base ?? '';   // e.g. /t/{slug}/apps/dms
$tenant_slug = $tenant_slug ?? '';   // e.g. ipsita

/** From controller */
$order     = is_array($order ?? null) ? $order : [];
$items     = is_array($items ?? null) ? $items : [];

$isEdit    = !empty($order) && !empty($order['id']);
$oid       = $isEdit ? (int)$order['id'] : 0;

$srId      = $isEdit ? ($order['sr_user_id']  ?? '') : '';
$dsrId     = $isEdit ? ($order['dsr_user_id'] ?? '') : '';

$st        = $isEdit ? (string)($order['status'] ?? ($st ?? 'draft')) : ($st ?? 'draft');
$dt        = $isEdit ? (string)($order['discount_type'] ?? ($dt ?? 'amount')) : ($dt ?? 'amount');
$dv        = $isEdit ? (float) ($order['discount_value'] ?? 0) : 0.0;

$od        = $isEdit ? (string)($order['order_date'] ?? date('Y-m-d')) : (string)($today ?? date('Y-m-d'));
$dd        = $isEdit ? (string)($order['delivery_date'] ?? $od) : (string)($today ?? date('Y-m-d'));

$cid       = $isEdit ? (int)($order['customer_id'] ?? 0) : 0;

$orderNo   = $isEdit ? (string)($order['order_no'] ?? '') : '';
$notesVal  = $isEdit ? (string)($order['notes'] ?? '')     : '';

$customers = $customers ?? [];
$products  = $products  ?? [];
$srs       = $srs       ?? [];

/** visible customer text */
$custDisplay = '';
if ($cid > 0) {
    foreach ($customers as $c) {
        if ((int)$c['id'] === $cid) {
            $code = !empty($c['code']) ? (string)$c['code'] : ('CID-' . str_pad((string)$c['id'], 6, '0', STR_PAD_LEFT));
            $custDisplay = $code . ' — ' . (string)($c['name'] ?? '');
            break;
        }
    }
    if ($custDisplay === '' && !empty($order['customer_name'])) $custDisplay = (string)$order['customer_name'];
}

/** normalize lines from $items */
$normItems = [];
foreach ($items as $ln) {
    $qty   = (float)($ln['qty'] ?? $ln['quantity'] ?? 0);
    $price = (float)($ln['price'] ?? $ln['unit_price'] ?? $ln['rate'] ?? 0);
    $normItems[] = [
        'product_id'   => (int)($ln['product_id'] ?? 0),
        'qty'          => $qty,
        'price'        => $price,
        'line_total'   => $qty * $price,
        'product_name' => (string)($ln['product_name'] ?? ''),
    ];
}
?>
<form id="orderForm" method="POST" action="<?= $isEdit ? h($module_base.'/orders/'.$oid) : h($module_base.'/orders') ?>" class="space-y-6">
  <?= function_exists('csrf_field') ? csrf_field() : '' ?>

  <!-- hidden fields that JS updates -->
  <input type="hidden" name="customer_id"   id="customer_id" value="<?= $cid ?: '' ?>">
  <input type="hidden" name="customer_name" id="customer_name" value="">
  <input type="hidden" name="discount_type" id="__disc_type" value="<?= h($dt) ?>">
  <input type="hidden" name="status"        id="__status"    value="<?= h($st) ?>">
  <input type="hidden" name="grand_total"   id="grand_total" value="0.00">

  <!-- Top row: Order No + SR/DSR -->
  <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
    <div>
      <label class="block text-sm font-medium mb-1">Order No</label>
      <input type="text" name="order_no" value="<?= h($orderNo) ?>" placeholder="Auto / manual"
             class="w-full rounded-lg border px-3 py-2">
      <p class="text-xs text-slate-500 mt-1">Optional. Leave empty to keep existing value.</p>
    </div>

    <div>
      <label class="block text-sm font-medium mb-1">SR</label>
      <div class="relative">
        <select name="sr_user_id" id="sr_user_id" class="w-full appearance-none rounded-lg border px-3 py-2 pr-9 bg-white">
          <option value="">— None —</option>
          <?php foreach ($srs as $u): $sel = (string)$srId !== '' && (int)$srId === (int)$u['id']; ?>
            <option value="<?= (int)$u['id'] ?>" <?= $sel ? 'selected' : '' ?>><?= h($u['name'] ?? '') ?></option>
          <?php endforeach; ?>
        </select>
        <i class="fa-solid fa-user-tie absolute right-3 top-1/2 -translate-y-1/2 text-slate-400"></i>
      </div>
    </div>

    <div>
      <label class="block text-sm font-medium mb-1">DSR (Optional)</label>
      <div class="relative">
        <select name="dsr_user_id" id="dsr_user_id" class="w-full appearance-none rounded-lg border px-3 py-2 pr-9 bg-white">
          <option value="">— None —</option>
          <?php foreach ($srs as $u): $sel = (string)$dsrId !== '' && (int)$dsrId === (int)$u['id']; ?>
            <option value="<?= (int)$u['id'] ?>" <?= $sel ? 'selected' : '' ?>><?= h($u['name'] ?? '') ?></option>
          <?php endforeach; ?>
        </select>
        <i class="fa-solid fa-people-carry-box absolute right-3 top-1/2 -translate-y-1/2 text-slate-400"></i>
      </div>
    </div>
  </div>

  <!-- Customer + Dates -->
  <div class="grid grid-cols-1 lg:grid-cols-4 gap-4">
    <div class="lg:col-span-2">
      <label class="block text-sm font-medium mb-1">Customer</label>
      <div class="flex items-center gap-2">
        <div class="relative flex-1">
          <input id="customer_search" type="text" class="w-full rounded-lg border px-3 py-2 pr-10"
                 placeholder="Type name / CID / phone…" autocomplete="off"
                 value="<?= h($custDisplay) ?>">
          <i class="fa-solid fa-magnifying-glass absolute right-3 top-1/2 -translate-y-1/2 text-slate-400"></i>
        </div>
        <input id="customer_code" type="text" readonly class="w-40 rounded-lg border bg-slate-50 px-3 py-2 text-slate-700 cursor-not-allowed select-none"
               placeholder="CID-000000" value="<?= $cid ? h('CID-'.str_pad((string)$cid,6,'0',STR_PAD_LEFT)) : '' ?>">
      </div>
      <div id="customer_suggest" class="mt-1 hidden rounded-lg border bg-white shadow max-h-56 overflow-auto"></div>
      <div class="mt-2">
        <button type="button" id="toggle_new_customer" class="text-sm text-blue-600 hover:underline">+ Add new customer</button>
      </div>
    </div>

    <div>
      <label class="block text-sm font-medium mb-1">Order Date</label>
      <input type="date" name="order_date" value="<?= h(substr($od,0,10)) ?>" class="w-full rounded-lg border px-3 py-2">
    </div>
    <div>
      <label class="block text-sm font-medium mb-1">Delivery Date</label>
      <input type="date" name="delivery_date" value="<?= h(substr($dd,0,10)) ?>" class="w-full rounded-lg border px-3 py-2">
    </div>
  </div>

  <!-- New customer mini-form -->
  <div id="new_customer_box" class="hidden rounded-lg border p-4 bg-slate-50">
    <div class="text-sm font-medium mb-2">New Customer</div>
    <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
      <div><label class="block text-xs text-slate-600">Name</label><input type="text" name="new_customer[name]" class="mt-1 w-full rounded-lg border px-3 py-2"></div>
      <div><label class="block text-xs text-slate-600">Phone</label><input type="text" name="new_customer[phone]" class="mt-1 w-full rounded-lg border px-3 py-2"></div>
      <div><label class="block text-xs text-slate-600">Address</label><input type="text" name="new_customer[address]" class="mt-1 w-full rounded-lg border px-3 py-2"></div>
    </div>
  </div>

  <!-- Status + Discount -->
  <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
    <div>
      <label class="block text-sm font-medium mb-1">Status</label>
      <div id="__status_tabs" class="flex flex-wrap gap-2">
        <?php foreach (['draft'=>'DRAFT','confirmed'=>'CONFIRMED','cancelled'=>'CANCELLED'] as $k=>$label): $on = $st === $k; ?>
          <button type="button" data-val="<?= $k ?>" class="px-3 py-2 rounded-lg border text-sm transition <?= $on ? 'bg-green-600 text-white border-green-600' : 'bg-white hover:bg-slate-50' ?>"><?= $label ?></button>
        <?php endforeach; ?>
      </div>
    </div>
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
      <div>
        <label class="block text-sm font-medium mb-1">Discount Type</label>
        <div id="__disc_tabs" class="flex flex-wrap gap-2">
          <?php foreach (['amount'=>'Amount','percent'=>'Percent'] as $k=>$label): $on = $dt === $k; ?>
            <button type="button" data-val="<?= $k ?>" class="px-3 py-2 rounded-lg border text-sm transition <?= $on ? 'bg-green-600 text-white border-green-600' : 'bg-white hover:bg-slate-50' ?>"><?= $label ?></button>
          <?php endforeach; ?>
        </div>
      </div>
      <div>
        <label class="block text-sm font-medium mb-1">Discount Value</label>
        <input type="number" step="0.01" min="0" name="discount_value" id="discount_value" class="w-full rounded-lg border px-3 py-2" value="<?= h($dv) ?>">
      </div>
    </div>
  </div>

  <!-- Lines -->
  <div class="overflow-x-auto">
    <table class="min-w-full text-sm border rounded-lg overflow-hidden">
      <thead class="bg-slate-50">
        <tr>
          <th class="px-3 py-2 text-left w-[42%]">Product</th>
          <th class="px-3 py-2 text-right w-[14%]">Qty</th>
          <th class="px-3 py-2 text-right w-[20%]">Price</th>
          <th class="px-3 py-2 text-right w-[20%]">Line Total</th>
          <th class="px-3 py-2 w-[4%]"></th>
        </tr>
      </thead>
      <tbody id="lines"></tbody>
      <tfoot>
        <tr>
          <td colspan="5" class="px-3 py-2">
            <button type="button" id="addLine" class="px-3 py-2 rounded-lg bg-slate-100 hover:bg-slate-200">+ Add line</button>
          </td>
        </tr>
      </tfoot>
    </table>
  </div>

  <!-- Notes -->
  <div>
    <label class="block text-sm font-medium mb-1">Notes</label>
    <textarea name="notes" rows="3" class="w-full rounded-lg border px-3 py-2" placeholder="Optional"><?= h($notesVal) ?></textarea>
  </div>

  <!-- Totals -->
  <div class="grid grid-cols-1 sm-grid-cols-3 sm:grid-cols-3 gap-4">
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

  <div class="flex justify-end">
    <button class="px-4 py-2 rounded-lg bg-blue-600 text-white hover:bg-blue-700"><?= $isEdit ? 'Update Order' : 'Save Order' ?></button>
  </div>
</form>

<script>
// ================ Helpers & globals =================
const money = n => (Math.round((+n || 0)*100)/100).toFixed(2);
const formatCID = (c) => c?.code ? String(c.code) : ('CID-' + String(c?.id||0).padStart(6,'0'));

window.APP = window.APP || {};
APP.customers = <?= json_encode(array_values($customers)) ?>;
APP.products  = <?= json_encode(array_values($products))  ?>;
APP.order     = <?= json_encode([
                    'id'=>$oid, 'customer_id'=>$cid, 'status'=>$st,
                    'discount_type'=>$dt, 'discount_value'=>$dv,
                    'order_date'=>substr($od,0,10), 'delivery_date'=>substr($dd,0,10),
                    'items'=>$normItems,
                  ]) ?>;

const form        = document.getElementById('orderForm');
const custIdInp   = document.getElementById('customer_id');
const custNameInp = document.getElementById('customer_name');
const cInput      = document.getElementById('customer_search');
const cBox        = document.getElementById('customer_suggest');
const cidBox      = document.getElementById('customer_code');
const newBtn      = document.getElementById('toggle_new_customer');
const newBox      = document.getElementById('new_customer_box');

const statusHidden = document.getElementById('__status');
const statusTabs   = document.getElementById('__status_tabs');
const discHidden   = document.getElementById('__disc_type');
const discTabs     = document.getElementById('__disc_tabs');

const tbody   = document.getElementById('lines');
const tSub    = document.getElementById('t_sub');
const tDisc   = document.getElementById('t_disc');
const tGrand  = document.getElementById('t_grand');
const gTotal  = document.getElementById('grand_total');

// ================ Tabs =================
function attachTabs(box, hidden){
  if(!box||!hidden) return;
  box.addEventListener('click', e=>{
    const b = e.target.closest('button[data-val]'); if(!b) return;
    hidden.value = b.dataset.val;
    box.querySelectorAll('button[data-val]').forEach(x=>{
      x.classList.remove('bg-green-600','text-white','border-green-600');
      x.classList.add('bg-white');
    });
    b.classList.add('bg-green-600','text-white','border-green-600');
    b.classList.remove('bg-white');
    recomputeTotals(); // reflect new mode
  });
}
attachTabs(statusTabs, statusHidden);
attachTabs(discTabs,   discHidden);

// ================ Customer typeahead =================
function renderCustomerSuggest(list){
  if (!Array.isArray(list) || list.length===0){ cBox.classList.add('hidden'); cBox.innerHTML=''; return; }
  cBox.innerHTML = list.map(c=>`
    <div data-id="${c.id}" class="px-3 py-2 hover:bg-slate-50 cursor-pointer">
      <div class="font-medium">${formatCID(c)} — ${c.name||''}</div>
      <div class="text-xs text-slate-500">${c.phone||''}${c.address?(' · '+c.address):''}</div>
    </div>
  `).join('');
  cBox.classList.remove('hidden');
}

if (cInput){
  cInput.addEventListener('input', ()=>{
    const q = (cInput.value||'').trim().toLowerCase();
    if (!q){ custIdInp.value=''; cidBox.value=''; renderCustomerSuggest([]); return; }
    const pool = Array.isArray(APP.customers) ? APP.customers : [];
    const m = pool.filter(c => (`${formatCID(c)} ${c.name||''} ${c.phone||''}`).toLowerCase().includes(q)).slice(0,30);
    renderCustomerSuggest(m);
  });
}
if (cBox){
  cBox.addEventListener('click', e=>{
    const row = e.target.closest('[data-id]'); if(!row) return;
    const id = +row.dataset.id;
    const pool = Array.isArray(APP.customers) ? APP.customers : [];
    const c = pool.find(x=>+x.id===id); if(!c) return;
    custIdInp.value = id;
    cInput.value = `${formatCID(c)} — ${c.name||''}`;
    custNameInp.value = c.name||'';
    cidBox.value = formatCID(c);
    cBox.classList.add('hidden'); newBox?.classList.add('hidden');
  });
  document.addEventListener('click', e=>{
    if (!cBox.contains(e.target) && e.target !== cInput) cBox.classList.add('hidden');
  });
}
newBtn?.addEventListener('click', ()=> newBox.classList.toggle('hidden'));

// hydrate customer visible fields
(function hydrateCustomerOnLoad(){
  const preId = Number(custIdInp?.value||0);
  if (!preId) return;
  const pool = Array.isArray(APP.customers) ? APP.customers : [];
  const c = pool.find(x=>Number(x.id)===preId);
  if (!c) return;
  cInput.value = `${formatCID(c)} — ${c.name||''}`;
  custNameInp.value = c.name||'';
  cidBox.value = formatCID(c);
})();

// ================ Lines =================
document.addEventListener('click', (e)=>{
  document.querySelectorAll('.psuggest').forEach(box=>{
    const input = box.parentElement?.querySelector('.psearch');
    if (!box.contains(e.target) && e.target !== input) box.classList.add('hidden');
  });
}, true);

function addLine(pid=0, pname='', qty=1, price=0){
  if ((!pname||!pname.trim()) && pid>0 && Array.isArray(APP.products)){
    const hit = APP.products.find(p=>Number(p.id)===Number(pid));
    if (hit){ pname = hit.name||''; if (!price || price<=0) price = Number(hit.price||hit.unit_price||hit.rate||0); }
  }
  const esc = s => String(s ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/"/g,'&quot;');

  const tr = document.createElement('tr');
  tr.innerHTML = `
    <td class="px-3 py-2">
      <div class="relative">
        <input type="text" class="psearch w-full rounded-lg border px-2 py-1" placeholder="Search product…" value="${esc(pname)}" autocomplete="off">
        <div class="psuggest hidden absolute left-0 right-0 z-10 mt-1 rounded-lg border bg-white shadow max-h-56 overflow-auto"></div>
      </div>
      <input type="hidden" class="pid" value="${pid||0}">
    </td>
    <td class="px-3 py-2 text-right"><input type="number" step="0.01" min="0" class="qty w-28 text-right rounded-lg border px-2 py-1" value="${qty||1}"></td>
    <td class="px-3 py-2 text-right"><input type="number" step="0.01" min="0" class="price w-32 text-right rounded-lg border px-2 py-1" value="${price||0}"></td>
    <td class="px-3 py-2 text-right"><span class="line">0.00</span></td>
    <td class="px-3 py-2 text-right"><button type="button" class="rm px-2 py-1 rounded bg-red-50 text-red-600 hover:bg-red-100">✕</button></td>
  `;

  const pidInp  = tr.querySelector('.pid');
  const nameInp = tr.querySelector('.psearch');
  const box     = tr.querySelector('.psuggest');
  const qInp    = tr.querySelector('.qty');
  const pInp    = tr.querySelector('.price');
  const ln      = tr.querySelector('.line');
  const rm      = tr.querySelector('.rm');

  const sync = () => { ln.textContent = money((+qInp.value||0) * (+pInp.value||0)); recomputeTotals(); };
  qInp.addEventListener('input', sync);
  pInp.addEventListener('input', sync);
  rm.addEventListener('click', ()=>{ tr.remove(); recomputeTotals(); });

  const renderPSuggest = (list) => {
    if (!list.length){ box.classList.add('hidden'); box.innerHTML=''; return; }
    box.innerHTML = list.map(p=>`
      <div class="ps-item px-3 py-2 hover:bg-slate-50 cursor-pointer flex items-center justify-between" data-id="${p.id}">
        <div>
          <div class="font-medium">${esc(p.name)}</div>
          <div class="text-xs text-slate-500">৳ ${money(p.price||p.unit_price||0)}</div>
        </div>
        <span class="text-[10px] px-1.5 py-0.5 rounded bg-slate-100 text-slate-600">#${p.id}</span>
      </div>
    `).join('');
    box.classList.remove('hidden');
  };

  nameInp.addEventListener('input', ()=>{
    const q = nameInp.value.trim().toLowerCase();
    if (!q){ renderPSuggest([]); return; }
    const pool = Array.isArray(APP.products) ? APP.products : [];
    renderPSuggest(pool.filter(p=>(p.name||'').toLowerCase().includes(q)).slice(0,30));
  });
  nameInp.addEventListener('focus', ()=>{
    const q = nameInp.value.trim().toLowerCase();
    if (!q) return;
    const pool = Array.isArray(APP.products) ? APP.products : [];
    renderPSuggest(pool.filter(p=>(p.name||'').toLowerCase().includes(q)).slice(0,30));
  });
  box.addEventListener('click', e=>{
    const row = e.target.closest('.ps-item'); if(!row) return;
    const id = +row.dataset.id;
    const pool = Array.isArray(APP.products) ? APP.products : [];
    const p = pool.find(x=>+x.id===id); if(!p) return;
    pidInp.value = p.id; nameInp.value = p.name||'';
    if ((+pInp.value||0) <= 0) pInp.value = (p.price||p.unit_price||p.rate||0);
    box.classList.add('hidden'); sync();
  });

  tbody.appendChild(tr);
  ln.textContent = money((+qInp.value||0) * (+pInp.value||0));
}

// ================ Totals =================
function recomputeTotals(){
  let sub=0;
  tbody.querySelectorAll('tr').forEach(tr=>{
    const q=+(tr.querySelector('.qty')?.value||0);
    const p=+(tr.querySelector('.price')?.value||0);
    sub+=q*p;
    const ln=tr.querySelector('.line'); if(ln) ln.textContent = money(q*p);
  });
  const dt = document.getElementById('__disc_type').value;
  const dv = +(document.getElementById('discount_value').value||0);
  const disc = dt==='percent' ? Math.min(sub, sub*(dv/100)) : Math.min(sub, dv);
  tSub.textContent   = money(sub);
  tDisc.textContent  = money(disc);
  const grand = Math.max(0, sub - disc);
  tGrand.textContent = money(grand);
  gTotal.value       = money(grand);
}
document.getElementById('discount_value').addEventListener('input', recomputeTotals);
window.recomputeTotals = recomputeTotals;

// ================ Build items[] on submit =================
form.addEventListener('submit', (e)=>{
  // Set customer_name if empty (from visible text)
  if (!custNameInp.value) {
    const txt = (cInput.value||'').trim();
    const name = txt.includes('—') ? txt.split('—')[1]?.trim() : txt;
    custNameInp.value = name || '';
  }
  // Remove any previous dynamic inputs
  form.querySelectorAll('.dyn-item').forEach(n=>n.remove());

  // Build items[i][...] inputs
  let idx = 0;
  tbody.querySelectorAll('tr').forEach(tr=>{
    const pid   = +(tr.querySelector('.pid')?.value||0);
    const pname = (tr.querySelector('.psearch')?.value||'').trim();
    const qty   = +(tr.querySelector('.qty')?.value||0);
    const price = +(tr.querySelector('.price')?.value||0);
    if (!pname || qty<=0) return;
    const line  = qty*price;

    const add = (n,v) => {
      const inp = document.createElement('input');
      inp.type = 'hidden';
      inp.className = 'dyn-item';
      inp.name  = `items[${idx}][${n}]`;
      inp.value = String(v ?? '');
      form.appendChild(inp);
    };

    add('product_id',   pid||0);
    add('product_name', pname);
    add('qty',          qty.toFixed(2));
    add('unit_price',   price.toFixed(2));
    add('line_total',   line.toFixed(2));

    idx++;
  });

  // Ensure totals are up to date
  recomputeTotals();
});

// ================ Boot / Hydration =================
document.getElementById('addLine').addEventListener('click', ()=> addLine());

(function boot(){
  const pack = APP.order;

  if (pack && Array.isArray(pack.items) && pack.items.length){
    tbody.innerHTML='';
    pack.items.forEach(ln=>{
      addLine(
        Number(ln.product_id||0),
        ln.product_name||'',
        Number(ln.qty||ln.quantity||1) || 1,
        Number(ln.price||ln.unit_price||ln.rate||0) || 0
      );
    });
    recomputeTotals();
  } else {
    addLine();
    recomputeTotals();
  }
})();
</script>