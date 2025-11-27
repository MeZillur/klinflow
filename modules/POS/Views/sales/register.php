<?php
declare(strict_types=1);
/**
 * POS — Sales Register (content-only view)
 * Expects:
 *   $ctx, $base, $invoice_no, $customers, $payment_methods
 * Optional:
 *   $branches, $current_branch_id
 */
$h        = fn($s)=>htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
$base     = $base ?? ($ctx['module_base'] ?? '/apps/pos');
$branches = $branches ?? [];
$currentBranchId = (int)($current_branch_id ?? ($ctx['branch_id'] ?? 0));
?>
<style>
  :root{
    --kf:#228B22;
    --kf-soft:#f0fdf4;
    --sr-border:#e5e7eb;
    --sr-bg:#f9fafb;
    --sr-text:#111827;
  }

  body { color: var(--sr-text); }

  .sr-shell      { max-width:1400px; margin:0 auto; padding:1rem 1.5rem; }
  .sr-panel      { border:1px solid var(--sr-border); border-radius:18px; background:var(--sr-bg); }
  .sr-panel-inner{ padding:12px 14px; }

  /* Header row */
  .sr-header{
    display:flex;
    align-items:flex-start;
    justify-content:space-between;
    gap:12px;
    margin-bottom:16px;
  }
  .sr-header-title h1{
    margin:0;
    font-size:24px;
    font-weight:900;
    letter-spacing:-0.02em;
  }
  .sr-header-title .hint{
    margin-top:4px;
  }
  .sr-header-actions{
    display:flex;
    flex-wrap:wrap;
    align-items:center;
    justify-content:flex-end;
    gap:8px;
  }

  /* Buttons */
  .btn{
    border-radius:10px;
    height:40px;
    padding:0 16px;
    display:inline-flex;
    align-items:center;
    justify-content:center;
    gap:8px;
    font-weight:700;
    cursor:pointer;
    border:none;
    font-size:13px;
    text-decoration:none;
  }
  .btn-primary{ background:var(--kf); color:#fff; }
  .btn-muted  { background:#f3f4f6; color:#111827; }
  .btn-danger { background:#fee2e2; color:#b91c1c; }

  /* Branch select */
  .sr-branch-wrap{
    display:flex;
    align-items:center;
    gap:4px;
  }
  .sr-branch-label{
    font-size:12px;
    color:#6b7280;
  }
  .sr-branch-select{
    border-radius:999px;
    border:1px solid #d1d5db;
    height:32px;
    padding:0 10px;
    font-size:13px;
    background:#fff;
  }

  /* Inputs */
  .sr-input{
    border:1px solid #d1d5db;
    border-radius:10px;
    height:40px;
    padding:.35rem .75rem;
    width:100%;
    background:#fff;
    font-size:13px;
  }
  .sr-input:focus{
    outline:2px solid rgba(34,139,34,.25);
    border-color:var(--kf);
  }
  .sr-input--ro{
    background:#f9fafb;
  }

  .hint{ color:#6b7280; font-size:12px; }

  /* Layout left/right */
  .sr-main{
    display:flex;
    flex-direction:column;
    gap:24px;
  }
  .sr-main-left,
  .sr-main-right{
    width:100%;
  }
  @media (min-width:1024px){
    .sr-main{ flex-direction:row; align-items:flex-start; }
    .sr-main-left{ width:36%; }
    .sr-main-right{ width:64%; }
  }

  /* Tabs */
  .sr-tab-wrap{ display:flex; gap:12px; margin-bottom:10px; }
  .sr-tab{
    flex:1 1 0;
    border-radius:12px;
    padding:.55rem 1rem;
    font-weight:700;
    border:1px solid transparent;
    background:#111827;
    color:#e5e7eb;
    text-align:center;
    cursor:pointer;
    font-size:13px;
  }
  .sr-tab.active{
    background:var(--kf);
    color:#fff;
    border-color:var(--kf);
  }

  /* Left product list */
  .sr-tiles-wrap{
    margin-top:10px;
    border-radius:14px;
    background:#ecf4ef;
    padding:10px;
    max-height:430px;
    overflow:auto;
  }
  .sr-empty{
    grid-column:1/-1;
    text-align:center;
    font-size:12px;
    color:#9ca3af;
    padding:12px 4px;
  }
  .sr-item{
    text-align:left;
    border-radius:10px;
    padding:8px 10px;
    border:1px solid transparent;
    cursor:pointer;
    width:100%;
    background:#fff;
    box-shadow:0 1px 3px rgba(15,23,42,.08);
    display:flex;
    flex-direction:column;
    gap:2px;
    font-size:13px;
  }
  .sr-item:hover{
    border-color:var(--kf);
    box-shadow:0 6px 16px rgba(15,23,42,.18);
  }
  .sr-item-name{ font-size:13px; font-weight:600; }
  .sr-item-sub { font-size:11px; color:#6b7280; }
  .sr-item-meta{
    font-size:11px;
    display:flex;
    gap:10px;
    color:#4b5563;
  }

  /* Simple grid helpers (no Tailwind) */
  .sr-row{
    display:flex;
    flex-wrap:wrap;
    gap:12px;
  }
  .sr-col-3{
    flex:1 1 0;
    min-width:180px;
  }

  /* Discount pills */
  .pill{
    border-radius:999px;
    border:1px solid #d1d5db;
    padding:.2rem .7rem;
    font-size:.8rem;
    cursor:pointer;
    background:#fff;
  }
  .pill.active{
    background:var(--kf);
    border-color:var(--kf);
    color:#fff;
  }

  /* Line inputs */
  .line-input{
    border:1px solid #d1d5db;
    border-radius:8px;
    height:34px;
    padding:0 .45rem;
    width:100%;
    background:#fff;
    font-size:13px;
  }
  .line-input:focus{
    outline:2px solid rgba(34,139,34,.25);
    border-color:var(--kf);
  }

  /* Line items table */
  .sr-lines-head,
  .sr-line-row{
    display:grid;
    grid-template-columns:minmax(0,4fr) minmax(0,1.2fr) minmax(0,2fr) minmax(0,1.2fr) minmax(0,2fr) 40px;
    column-gap:8px;
    align-items:center;
  }
  .sr-lines-head{
    background:#111827;
    color:#f9fafb;
    font-weight:700;
    font-size:13px;
    border-radius:18px 18px 0 0;
  }
  .sr-lines-head div{
    padding:8px 10px;
  }
  .sr-lines-body{
    padding:4px 10px 6px;
  }
  .sr-line-row{
    border-bottom:1px solid #e5e7eb;
    padding:6px 0;
    font-size:13px;
  }
  .sr-line-name{
    font-weight:600;
    font-size:13px;
  }
  .sr-line-sku{
    font-size:11px;
    color:#6b7280;
  }
  .sr-text-right{
    text-align:right;
  }

</style>

<div class="sr-shell">
  <!-- Header -->
  <div class="sr-header">
    <div class="sr-header-title">
      <h1>Sales Register</h1>
      <div class="hint">Fast entry • Category / Brand filter • Barcode • Keyboard shortcuts</div>
    </div>
    <div class="sr-header-actions">
      <a class="btn btn-muted" href="<?= $h($base) ?>/sales">Sales</a>
      <a class="btn btn-muted" href="<?= $h($base) ?>/sales/hold">Held</a>
      <a class="btn btn-muted" href="<?= $h($base) ?>/sales/refunds">Refunds</a>

      <form method="post" action="<?= $h($base) ?>/sales/branch/switch" class="sr-branch-wrap">
        <span class="sr-branch-label">Branch</span>
        <select name="branch_id" id="branch_id" class="sr-branch-select" onchange="this.form.submit()">
          <option value="0"<?= $currentBranchId === 0 ? ' selected' : '' ?>>— Select —</option>
          <?php foreach ($branches as $b): ?>
            <option value="<?= (int)$b['id'] ?>"
              <?= (int)$b['id'] === $currentBranchId ? ' selected' : '' ?>>
              <?= $h($b['name']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </form>
    </div>
  </div>

  <div class="sr-main">
    <!-- Left: Category / Brand product list -->
    <aside class="sr-main-left">
      <div class="sr-panel">
        <div class="sr-panel-inner">
          <div class="sr-tab-wrap">
            <button id="tabCat"  type="button" class="sr-tab active">Category</button>
            <button id="tabBrand" type="button" class="sr-tab">Brand</button>
          </div>

          <div style="display:flex;flex-direction:column;gap:8px;margin-bottom:8px;">
            <input id="productSearch" class="sr-input" placeholder="Type to search by name / SKU">
            <input id="barcodeInput" class="sr-input" placeholder="Scan Barcode">
          </div>

          <div class="sr-tiles-wrap">
            <div id="productTiles" style="display:grid;grid-template-columns:1fr;gap:8px;">
              <div class="sr-empty">Start typing to load products.</div>
            </div>
          </div>

          <div class="hint" style="margin-top:8px;">
            Tip: Choose <strong>Category</strong> or <strong>Brand</strong>, type “iphone” etc.
            Click a row to add it. A chime will play when the item is added.
          </div>
        </div>
      </div>
    </aside>

    <!-- Right: Register -->
    <section class="sr-main-right">
      <form id="saleForm" class="space-y-4">
        <!-- Invoice + customer -->
        <div class="sr-panel">
          <div class="sr-panel-inner">
            <div class="sr-row">
              <div class="sr-col-3">
                <label class="block text-sm font-semibold mb-1" style="font-size:13px;font-weight:600;margin-bottom:4px;">Invoice No</label>
                <input class="sr-input sr-input--ro" value="<?= $h($invoice_no) ?>" name="invoice_no" readonly>
              </div>
              <div class="sr-col-3">
                <label class="block text-sm font-semibold mb-1" style="font-size:13px;font-weight:600;margin-bottom:4px;">Sale Date</label>
                <input class="sr-input" type="date" name="sale_date" value="<?= $h(date('Y-m-d')) ?>">
              </div>
              <div class="sr-col-3">
                <label class="block text-sm font-semibold mb-1" style="font-size:13px;font-weight:600;margin-bottom:4px;">Customer</label>
                <div style="display:flex;gap:8px;">
                  <select id="customer_id" name="customer_id" class="sr-input">
                    <option value="">— Walk-in —</option>
                    <?php foreach ($customers as $cu): ?>
                      <option value="<?= (int)$cu['id'] ?>"><?= $h($cu['name']) ?></option>
                    <?php endforeach; ?>
                  </select>
                  <button type="button" id="btnNewCustomer" class="btn btn-muted" style="width:44px;flex:0 0 auto;">+</button>
                </div>
                <input class="sr-input" style="margin-top:8px;" name="customer_name" placeholder="(optional) name for receipt">
              </div>
            </div>
          </div>
        </div>

        <!-- Line items -->
        <div class="sr-panel">
          <div class="sr-lines-head">
            <div>Product</div>
            <div class="sr-text-right">Unit</div>
            <div class="sr-text-right">Price</div>
            <div class="sr-text-right">Qty</div>
            <div class="sr-text-right">Total</div>
            <div></div>
          </div>
          <div id="lineItems" class="sr-lines-body"></div>
        </div>

        <!-- Discount / payment / totals -->
        <div class="sr-row">
          <div class="sr-col-3">
            <div class="sr-panel">
              <div class="sr-panel-inner" style="display:flex;flex-direction:column;gap:12px;">
                <div>
                  <div style="display:flex;align-items:center;gap:8px;margin-bottom:4px;">
                    <span style="font-size:13px;font-weight:600;">Discount</span>
                    <button type="button" id="discAmt" class="pill active">Amount</button>
                    <button type="button" id="discPct" class="pill">% Percent</button>
                  </div>
                  <input id="discount" name="discount" class="sr-input" value="0">
                  <input type="hidden" id="discount_type" name="discount_type" value="amount">
                  <div class="hint" style="margin-top:4px;">BDT or % based on the toggle.</div>
                </div>

                <div>
                  <label class="block text-sm font-semibold mb-1" style="font-size:13px;font-weight:600;margin-bottom:4px;">Payment Method</label>
                  <select class="sr-input" name="payment_method">
                    <?php foreach ($payment_methods as $pm): ?>
                      <option value="<?= $h($pm['code']) ?>"><?= $h($pm['label']) ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>

                <div>
                  <label class="block text-sm font-semibold mb-1" style="font-size:13px;font-weight:600;margin-bottom:4px;">Reference</label>
                  <input class="sr-input" name="reference" placeholder="Txn / Ref">
                </div>

                <div>
                  <label class="block text-sm font-semibold mb-1" style="font-size:13px;font-weight:600;margin-bottom:4px;">Notes</label>
                  <textarea class="sr-input" style="height:92px;resize:vertical;" name="notes"></textarea>
                </div>
              </div>
            </div>
          </div>

          <div class="sr-col-3">
            <div class="sr-panel">
              <div class="sr-panel-inner">
                <div style="display:flex;align-items:center;justify-content:space-between;padding:4px 0;">
                  <div>Sub Total (Before Discount)</div><div id="t_subtotal">0.00</div>
                </div>
                <div style="display:flex;align-items:center;justify-content:space-between;padding:4px 0;">
                  <div style="color:#b91c1c;">Total Discount</div><div id="t_discount" style="color:#b91c1c;">0.00</div>
                </div>
                <div style="display:flex;align-items:center;justify-content:space-between;padding:4px 0;">
                  <div>Total Tax (5%)</div><div id="t_tax">0.00</div>
                </div>
                <hr style="margin:8px 0;">
                <div style="display:flex;align-items:center;justify-content:space-between;font-size:18px;font-weight:900;">
                  <div>Grand Total</div><div id="t_total">0.00</div>
                </div>

                <div style="display:flex;gap:12px;margin-top:16px;justify-content:flex-end;">
                  <a href="<?= $h($base) ?>/sales" class="btn btn-danger">Cancel</a>
                  <button id="completeBtn" type="submit" class="btn btn-primary">Pay</button>
                </div>
              </div>
            </div>
          </div>
        </div>
      </form>
    </section>
  </div>
</div>

<script>
/* ================================================================
   CORE CONSTANTS / HELPERS
==================================================================*/
const BASE = <?= json_encode($base, JSON_UNESCAPED_SLASHES) ?>;
const TAX_RATE = 0.05;

let items = [];
let currentProducts = [];

const $ = (s,sc)=> (sc||document).querySelector(s);
const $$ = (s,sc)=> Array.from((sc||document).querySelectorAll(s));

function money(v){
  return (Math.round((+v)*100)/100).toFixed(2);
}

/* ================================================================
   SOUNDS
==================================================================*/
function addSound(){ try{ const C=new AudioContext(); const o=C.createOscillator(); const g=C.createGain(); o.type='triangle'; o.frequency.value=880; g.gain.value=0.08; o.connect(g).connect(C.destination); o.start(); o.stop(C.currentTime+0.18);}catch(e){}}
function errSound(){ try{ const C=new AudioContext(); const o=C.createOscillator(); const g=C.createGain(); o.type='sine'; o.frequency.value=240; g.gain.value=0.09; o.connect(g).connect(C.destination); o.start(); o.frequency.linearRampToValueAtTime(160,C.currentTime+0.18); o.stop(C.currentTime+0.20);}catch(e){}}
function paySound(){ try{ const C=new AudioContext(); const o=C.createOscillator(); const g=C.createGain(); o.type='triangle'; o.frequency.value=660; g.gain.value=0.08; o.connect(g).connect(C.destination); o.start(); o.frequency.linearRampToValueAtTime(980,C.currentTime+0.18); o.stop(C.currentTime+0.25);}catch(e){}}

/* ================================================================
   PRODUCT PRICE RESOLVER
==================================================================*/
function productPrice(p){
  const v = Number(
    p.sale_price ??
    p.unit_price ??
    p.price ??
    p.sale_unit_price ??
    p.mrp ??
    p.price_like ??
    0
  );
  return Number.isFinite(v) ? v : 0;
}

/* ================================================================
   RENDER LINE ITEMS & TOTALS
==================================================================*/
function render(){
  const host = $("#lineItems");
  host.innerHTML = "";

  let sub = 0;

  items.forEach((it,idx)=>{
    const line = (it.sale_price||0) * (it.qty||0);
    sub += line;

    const row = document.createElement("div");
    row.className = "sr-line-row";
    row.innerHTML = `
      <div>
        <div class="sr-line-name" title="${it.name}">${it.name}</div>
        <div class="sr-line-sku">SKU: ${it.sku}</div>
      </div>

      <div class="sr-text-right">
        <input class="line-input sr-text-right" value="${it.unit_name}" readonly>
      </div>

      <div class="sr-text-right">
        <input class="line-input sr-text-right li-price" data-i="${idx}" type="number" step="0.01" value="${money(it.sale_price)}">
      </div>

      <div class="sr-text-right">
        <input class="line-input sr-text-right li-qty"   data-i="${idx}" type="number" min="1" step="1" value="${it.qty}">
      </div>

      <div class="sr-text-right">
        <input class="line-input sr-text-right" value="${money(line)}" readonly>
      </div>

      <div class="sr-text-right">
        <button class="btn btn-danger" data-del="${idx}" style="height:30px;padding:0 10px;">✕</button>
      </div>
    `;
    host.appendChild(row);
  });

  // totals
  const discVal = parseFloat($("#discount").value||"0");
  const discType = $("#discount_type").value;
  const discAmt = discType === "percent" ? (sub * discVal/100) : discVal;

  const taxable = Math.max(0, sub - Math.max(0,discAmt));
  const tax = taxable * TAX_RATE;
  const total = taxable + tax;

  $("#t_subtotal").textContent = money(sub);
  $("#t_discount").textContent = money(discAmt ? -discAmt : 0);
  $("#t_tax").textContent      = money(tax);
  $("#t_total").textContent    = money(total);
}

/* ================================================================
   INLINE EDIT HANDLERS
==================================================================*/
$("#lineItems").addEventListener("input", e=>{
  let i = +e.target.dataset.i;
  if(isNaN(i) || !items[i]) return;

  if(e.target.classList.contains("li-price")){
    items[i].sale_price = parseFloat(e.target.value)||0;
  }
  if(e.target.classList.contains("li-qty")){
    items[i].qty = parseFloat(e.target.value)||1;
  }
  render();
});

$("#lineItems").addEventListener("click", e=>{
  const del = e.target.closest("[data-del]");
  if(!del) return;
  items.splice(+del.dataset.del,1);
  errSound();
  render();
});

/* ================================================================
   DISCOUNT SWITCH
==================================================================*/
$("#discAmt").onclick = ()=>{
  $("#discount_type").value = "amount";
  $("#discAmt").classList.add("active");
  $("#discPct").classList.remove("active");
  render();
};
$("#discPct").onclick = ()=>{
  $("#discount_type").value = "percent";
  $("#discPct").classList.add("active");
  $("#discAmt").classList.remove("active");
  render();
};
$("#discount").addEventListener("input", render);

/* ================================================================
   FETCH HELPERS
==================================================================*/
async function fetchJSON(url,opts){
  const r = await fetch(url, Object.assign({credentials:"include"},opts||{}));
  if(!r.ok) return null;
  try { return await r.json(); } catch { return null; }
}
function unwrapList(res){
  if(!res) return [];
  if(Array.isArray(res)) return res;
  return res.items || res.rows || res.data || [];
}

/* ================================================================
   PRODUCT SEARCH / CATEGORY / BRAND
==================================================================*/
async function searchProductsByMode(q, mode){
  const p = new URLSearchParams({ q, mode });
  return unwrapList(await fetchJSON(`${BASE}/sales/api/products?${p}`));
}

function addOrBump(prod, qty=1){
  const basePrice = productPrice(prod);
  const ex = items.find(x=>x.id===prod.id);
  if(ex){
    ex.qty += qty;
    if(!ex.sale_price) ex.sale_price = basePrice;
  }else{
    items.push({
      id: prod.id,
      sku: prod.sku || prod.code || "",
      code: prod.code || "",
      name: prod.name || "",
      unit_name: prod.unit_name || prod.unit || "pcs",
      qty: qty,
      sale_price: basePrice
    });
  }
}

/* LEFT TABS */
const mode = { value: "category" };

$("#tabCat").onclick = ()=>{
  mode.value="category";
  $("#tabCat").classList.add("active");
  $("#tabBrand").classList.remove("active");
  loadTiles();
};

$("#tabBrand").onclick = ()=>{
  mode.value="brand";
  $("#tabBrand").classList.add("active");
  $("#tabCat").classList.remove("active");
  loadTiles();
};

let tilesTimer = null;
function loadTilesDebounced(){
  clearTimeout(tilesTimer);
  tilesTimer = setTimeout(loadTiles, 220);
}

async function loadTiles(){
  const q = $("#productSearch").value.trim();
  const host = $("#productTiles");
  host.innerHTML = '<div class="sr-empty">Loading…</div>';

  const rows = await searchProductsByMode(q, mode.value);
  currentProducts = rows;
  host.innerHTML = "";

  if(!rows.length){
    host.innerHTML = '<div class="sr-empty">No products found.</div>';
    return;
  }

  rows.forEach(p=>{
    const price = productPrice(p);
    const btn = document.createElement("button");
    btn.type="button";
    btn.className="sr-item";
    btn.innerHTML = `
      <div class="sr-item-name">${p.name}</div>
      <div class="sr-item-sub">${p.sku||p.code||""}</div>
      <div class="sr-item-meta">
        <span><strong>${money(price)}</strong></span>
        <span>${p.unit_name||p.unit||"pcs"}</span>
      </div>
    `;
    btn.onclick = ()=>{
      addOrBump(p,1);
      addSound();
      render();
    };
    host.appendChild(btn);
  });
}

/* search input */
$("#productSearch").addEventListener("input", loadTilesDebounced);
$("#productSearch").addEventListener("keydown", e=>{
  if((e.key==="Enter" || e.key==="Tab") && currentProducts.length){
    e.preventDefault();
    addOrBump(currentProducts[0],1);
    addSound();
    render();
    $("#productSearch").select();
  }
});

/* ================================================================
   BARCODE ADD
==================================================================*/
$("#barcodeInput").addEventListener("keydown", async(e)=>{
  if(e.key!=="Enter") return;
  e.preventDefault();

  let code = e.target.value.trim();
  if(!code) return;

  const res = await fetchJSON(
    `${BASE}/sales/api/products?` + new URLSearchParams({q:code, exact_barcode:"1"})
  );

  let rows = unwrapList(res);
  if(rows.length){
    addOrBump(rows[0],1);
    addSound();
    render();
    e.target.value="";
  }else errSound();
});

/* ================================================================
   NEW CUSTOMER QUICK ADD
==================================================================*/
$("#btnNewCustomer").onclick = async ()=>{
  const name = prompt("Customer name"); if(!name) return;
  const phone= prompt("Mobile"); 

  const res = await fetchJSON(`${BASE}/sales/api/customers.create`, {
    method:"POST",
    headers:{ "Content-Type":"application/json" },
    body:JSON.stringify({ name, phone })
  });

  if(res && res.ok && res.id){
    const o = document.createElement("option");
    o.value = res.id;
    o.textContent = name;
    $("#customer_id").appendChild(o);
    $("#customer_id").value = res.id;
  }
};

/* ================================================================
   PAYMENT MODAL (FULL WORKING)
==================================================================*/
let payModal=null, payCash=null, payChange=null, payTotal=null, payItems=null, payConfirm=null, payCancel=null;

function ensurePayModal(){
  if(payModal) return;

  const wrap = document.createElement("div");
  wrap.innerHTML = `
  <div id="payModal" class="fixed inset-0 z-40 hidden items-center justify-center bg-black/40">
    <div class="bg-white rounded-2xl shadow-xl w-full max-w-md mx-4 border border-green-600 overflow-hidden">
      <div class="px-5 py-3 bg-green-600 text-white flex items-center justify-between">
        <strong>Payment</strong>
        <button id="pmClose" class="text-xl font-bold">×</button>
      </div>

      <div class="px-5 py-4 space-y-3">
        <textarea id="pmItems" rows="3" class="w-full border p-2 text-sm bg-gray-50" readonly></textarea>

        <div class="flex justify-between text-lg font-bold">
          <span>Total:</span><span id="pmTotal">0.00</span>
        </div>

        <div>
          <label>Cash Received</label>
          <input id="pmCash" type="number" min="0" step="0.01" class="w-full border p-2 text-right font-bold">
        </div>

        <div class="flex justify-between font-bold text-blue-700">
          <span>Change:</span><span id="pmChange">0.00</span>
        </div>
      </div>

      <div class="px-5 py-3 bg-gray-100 flex justify-between">
        <button id="pmCancel" class="btn btn-muted">Cancel</button>
        <button id="pmConfirm" class="btn btn-primary">Confirm & Save</button>
      </div>
    </div>
  </div>`;

  document.body.appendChild(wrap.firstElementChild);

  payModal   = $("#payModal");
  payCash    = $("#pmCash", payModal);
  payChange  = $("#pmChange", payModal);
  payTotal   = $("#pmTotal", payModal);
  payItems   = $("#pmItems", payModal);
  payConfirm = $("#pmConfirm", payModal);
  payCancel  = $("#pmCancel", payModal);

  $("#pmClose").onclick = closePayModal;
  payCancel.onclick = closePayModal;

  payCash.oninput = updatePayChange;

  payConfirm.onclick = ()=>{
    performSave();
  };

  payModal.addEventListener("click", e=>{
    if(e.target === payModal) closePayModal();
  });
}

function openPayModal(){
  ensurePayModal();

  const total = $("#t_total").textContent.trim();
  payTotal.textContent = total;

  payItems.value = items.map(it=>
    `${it.name} × ${it.qty} @ ${money(it.sale_price)}`
  ).join("\n");

  payCash.value = "";
  updatePayChange();

  payModal.classList.remove("hidden");
  payModal.classList.add("flex");

  setTimeout(()=> payCash.focus(), 100);
}

function closePayModal(){
  payModal.classList.add("hidden");
  payModal.classList.remove("flex");
}

function updatePayChange(){
  const total = parseFloat(payTotal.textContent||"0")||0;
  const cash  = parseFloat(payCash.value||"0")||0;
  const change = cash-total;
  payChange.textContent = money(change>0?change:0);
}

/* ================================================================
   FINAL SAVE (WITH MODAL)
==================================================================*/
async function performSave(){
  if(!items.length){
    alert("Add items first");
    return;
  }

  const fd = new FormData($("#saleForm"));

  const discountType = $("#discount_type").value;
  const discVal = parseFloat($("#discount").value||"0");

  const payload={
    invoice_no: fd.get("invoice_no"),
    sale_date: fd.get("sale_date"),
    customer_id: parseInt(fd.get("customer_id")||"0"),
    customer_name: fd.get("customer_name")||"",
    payment_method: fd.get("payment_method")||"Cash",
    reference: fd.get("reference")||"",
    discount_amount:  discountType==="amount"?discVal:0,
    discount_percent: discountType==="percent"?discVal:0,
    tax_percent:5,
    notes: fd.get("notes")||"",
    items: items.map(it=>({
      id:it.id, sku:it.sku, name:it.name, unit_name:it.unit_name,
      qty:it.qty, price:it.sale_price, code:it.code
    }))
  };

  const r = await fetch(`${BASE}/sales`,{
    method:"POST",
    headers:{ "Content-Type":"application/json" },
    credentials:"include",
    body:JSON.stringify(payload)
  });

  if(r.ok){
    paySound();
    closePayModal();
    let j = await r.json().catch(()=> null);
    let id = j?.id;
    location.href = id ? `${BASE}/sales/${id}` : `${BASE}/sales`;
  }else{
    errSound();
    alert("Save failed!");
  }
}

/* ================================================================
   PAY BUTTON + SHORTCUTS
==================================================================*/
$("#completeBtn").onclick = e=>{
  e.preventDefault();
  if(!items.length){ alert("Add items first");return; }
  openPayModal();
};

document.addEventListener("keydown", e=>{
  if(e.ctrlKey && e.key==="Enter"){
    e.preventDefault();
    if(items.length) openPayModal();
  }
});

/* ================================================================
   INITIAL LOAD
==================================================================*/
loadTiles();
render();
</script>