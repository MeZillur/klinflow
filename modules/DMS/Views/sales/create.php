<?php
declare(strict_types=1);
/**
 * DMS · Sales → Create/Edit Invoice (shared)
 * Requires: $module_base, $today, $st ('draft'|'confirmed'|'cancelled'), $dt ('amount'|'percent'), $next_no
 * Optional (for edit): $existing array with header+items (see controller)
 * Brand: #228B22, square corners, mobile-first.
 */
$base = rtrim((string)($module_base ?? ''), '/');
function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

/* --- Edit mode detection & top-value overrides --- */
$__editing = isset($existing) && is_array($existing);
if ($__editing) {
  $today   = $existing['sale_date']      ?? ($today ?? date('Y-m-d'));
  $st      = $existing['status']         ?? ($st ?? 'draft');
  $dt      = $existing['discount_type']  ?? ($dt ?? 'amount');
  $next_no = $existing['sale_no']        ?? ($next_no ?? '');
}
?>
<style>
  :root { --bg:#ffffff; --card:#f8fafc; --text:#0f172a; --muted:#475569; --border:#e5e7eb; --brand:#228B22; }
  @media (prefers-color-scheme: dark) {
    :root { --bg:#0b1020; --card:#0f172a; --text:#e5e7eb; --muted:#94a3b8; --border:#1f2937; --brand:#228B22; }
  }
  .ip { width:100%; height:40px; line-height:40px; padding:0 12px;
        border:1px solid var(--border); border-radius:0; background:var(--bg); color:var(--text); font-size:.875rem; }
  .btn { border-radius:0; padding:8px 12px; border:1px solid var(--border); background:var(--bg); }
  .btn-brand { background:var(--brand); color:#fff; border-color:var(--brand); }
  .btn-info  { background:#0ea5e9; color:#fff; border-color:#0ea5e9; }
  .box { background:var(--card); border:1px solid var(--border); }
  .tab { background:var(--bg); border:1px solid var(--border); padding:6px 12px; cursor:pointer; }
  .tab--on { background:var(--brand); color:#fff; border-color:var(--brand); }
  .badge { font-size:12px; padding:2px 8px; background:var(--card); color:var(--muted); border:1px solid var(--border); }
  .tbl { width:100%; border-collapse:separate; border-spacing:0; }
  .tbl th, .tbl td { padding:10px 12px; border-bottom:1px solid var(--border); vertical-align:middle; }
  .tbl thead th { background:var(--card); font-weight:600; }
  .qty, .money { text-align:right; }
  .rm { color:#ef4444; cursor:pointer; }
  .rm:hover { text-decoration:underline; }
</style>

<form method="POST" action="<?= h($base) ?>/sales" class="space-y-5" id="invForm" autocomplete="off">
  <?= function_exists('csrf_field') ? csrf_field() : '' ?>

  <!-- mode + identities -->
  <input type="hidden" name="sale_id" id="sale_id" value="<?= $__editing ? (int)($existing['id'] ?? 0) : '' ?>">
  <input type="hidden" name="action" id="__action" value="save">

  <!-- cross-refs -->
  <input type="hidden" name="order_id" id="order_id" value="<?= $__editing ? (int)($existing['order_id'] ?? 0) : '' ?>">
  <input type="hidden" name="customer_id" id="customer_id" value="<?= $__editing ? (int)($existing['customer_id'] ?? 0) : '' ?>">
  <input type="hidden" name="customer_name" id="customer_name" value="<?= $__editing ? h($existing['customer_name'] ?? '') : '' ?>">
  <input type="hidden" name="delivery_user_id" id="delivery_user_id" value="<?= $__editing ? (int)($existing['delivery_user_id'] ?? 0) : '' ?>">

  <!-- status/discount -->
  <input type="hidden" name="discount_type" id="__inv_disc_type" value="<?= h($dt ?? 'amount') ?>">
  <input type="hidden" name="status"        id="__inv_status"     value="<?= h($st ?? 'draft') ?>">

  <!-- Meta -->
  <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
    <div>
      <label class="block text-sm font-medium mb-1">Invoice Date</label>
      <input type="date" name="sale_date" value="<?= h($today ?? date('Y-m-d')) ?>" class="ip">
    </div>
    <div>
      <label class="block text-sm font-medium mb-1">Invoice Number</label>
      <input type="text" name="sale_no" id="sale_no" class="ip" value="<?= h($next_no ?? '') ?>" readonly>
    </div>
    <div>
      <label class="block text-sm font-medium mb-1">Status</label>
      <div id="__inv_status_tabs" class="flex flex-wrap gap-2">
        <?php foreach (['draft'=>'DRAFT','confirmed'=>'CONFIRMED','cancelled'=>'CANCELLED'] as $k=>$label): $on = ($st ?? 'draft') === $k; ?>
          <button type="button" data-val="<?= h($k) ?>" class="tab <?= $on ? 'tab--on':'' ?>"><?= h($label) ?></button>
        <?php endforeach; ?>
      </div>
    </div>
  </div>

  <!-- Order + Delivery -->
  <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
    <div>
      <label class="block text-sm font-medium mb-1">Order</label>
      <input id="order_search" class="ip"
             placeholder="Type order number or search…"
             data-kf-lookup="orders"
             data-kf-target-id="#order_id"
             data-kf-target-name="#order_search"
             value="<?= $__editing && !empty($existing['order_no'] ?? '') ? h($existing['order_no']) : '' ?>">
      <div class="text-xs" style="color:var(--muted)">Pick an order (e.g., ORD-2025-00001) to auto-fill items and customer.</div>
    </div>
    <div>
      <label class="block text-sm font-medium mb-1">Delivery Person</label>
      <input id="delivery_search" class="ip"
             placeholder="Search delivery person…"
             data-kf-lookup="users"
             data-kf-target-id="#delivery_user_id"
             data-kf-target-name="#delivery_search">
    </div>
  </div>

  <!-- Customer -->
  <div class="box p-3">
    <div class="text-xs" style="color:var(--muted)">Customer</div>
    <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-3">
      <div class="flex-1">
        <input id="customer_search" class="ip"
               placeholder="Type name / phone / code…"
               data-kf-lookup="customers"
               data-kf-target-id="#customer_id"
               data-kf-target-name="#customer_name"
               data-kf-target-code="#customer_cid_box"
               value="<?= $__editing ? h($existing['customer_name'] ?? '') : '' ?>">
      </div>
      <input id="customer_cid_box" class="ip" value="<?= $__editing && ($existing['customer_id'] ?? 0) ? 'CID-'.str_pad((string)$existing['customer_id'],6,'0',STR_PAD_LEFT) : 'CID-000000' ?>" readonly style="max-width:180px">
    </div>
    <div id="cust_meta" class="text-sm mt-2" style="color:var(--muted)">Mobile: — · Address: —</div>
  </div>

  <!-- Lines -->
  <div class="overflow-x-auto">
    <table class="tbl box">
      <thead>
        <tr>
          <th class="text-left w-[42%]">Product</th>
          <th class="text-right w-[14%]">Qty</th>
          <th class="text-right w-[20%]">Unit Price</th>
          <th class="text-right w-[20%]">Line Total</th>
          <th class="w-[4%]"></th>
        </tr>
      </thead>
      <tbody id="lines"></tbody>
      <tfoot>
        <tr>
          <td colspan="5">
            <button type="button" id="addLine" class="btn btn-brand">+ Add line</button>
            <span class="text-xs ml-2" style="color:var(--muted)">(Price auto-fills from product; you can edit.)</span>
          </td>
        </tr>
      </tfoot>
    </table>
  </div>

  <!-- Discount -->
  <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 items-end">
    <div>
      <label class="block text-sm font-medium mb-1">Discount Type</label>
      <div id="__inv_disc_tabs" class="flex flex-wrap gap-2">
        <?php $dtSel = $dt ?? 'amount'; foreach (['amount'=>'Amount','percent'=>'Percent'] as $k=>$label): ?>
          <button type="button" data-val="<?= h($k) ?>" class="tab <?= $dtSel===$k ? 'tab--on':'' ?>"><?= h($label) ?></button>
        <?php endforeach; ?>
      </div>
    </div>
    <div>
      <label class="block text-sm font-medium mb-1">Discount Value</label>
      <input type="number" step="0.01" min="0" name="discount_value" id="discount_value" class="ip" value="<?= $__editing ? h((string)($existing['discount_value'] ?? '0')) : '0' ?>">
    </div>
  </div>

  <!-- Totals -->
  <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
    <div class="p-3 box">
      <div class="text-xs" style="color:var(--muted)">Items Subtotal</div>
      <div class="text-lg font-semibold" id="t_sub">0.00</div>
    </div>
    <div class="p-3 box">
      <div class="text-xs" style="color:var(--muted)">Discount</div>
      <div class="text-lg font-semibold" id="t_disc">0.00</div>
    </div>
    <div class="p-3 box">
      <div class="text-xs" style="color:var(--muted)">Grand Total</div>
      <div class="text-lg font-semibold" id="t_grand">0.00</div>
    </div>
  </div>

  <!-- hidden totals (fallback) -->
  <input type="hidden" name="__items_subtotal" id="__items_subtotal" value="0">
  <input type="hidden" name="__discount_amount" id="__discount_amount" value="0">
  <input type="hidden" name="__grand_total"    id="__grand_total"    value="0">

  <div class="flex justify-end gap-2">
    <button type="submit" class="btn btn-brand" onclick="document.getElementById('__action').value='save'">Save Invoice</button>
    <button type="submit" class="btn btn-info"  onclick="document.getElementById('__action').value='save_print'">Save & Print</button>
  </div>
</form>

<?php if (!empty($existing) && is_array($existing)): ?>
<script>window.__existingInvoice = <?= json_encode($existing, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) ?>;</script>
<?php endif; ?>

<script>
(function(){
  const BASE = <?= json_encode($base) ?>;
  const $ = s => document.querySelector(s);
  const fmt2 = n => (Math.round((+n||0)*100)/100).toFixed(2);
  const EX = window.__existingInvoice || null; // when editing

  /* ---------- pills ---------- */
  function hookPills(holderSel, hiddenSel, onChange){
    const holder = $(holderSel), hidden = $(hiddenSel);
    holder?.addEventListener('click', (e)=>{
      const b = e.target.closest('button[data-val]'); if (!b) return;
      holder.querySelectorAll('button').forEach(x=>x.classList.remove('tab--on'));
      b.classList.add('tab--on'); hidden.value = b.getAttribute('data-val') || '';
      onChange?.();
    });
  }
  hookPills('#__inv_status_tabs', '#__inv_status');
  hookPills('#__inv_disc_tabs', '#__inv_disc_type', recalcTotals);

  /* ---------- robust price fetch (fallback) ---------- */
  async function fetchPriceForProduct(pid){
    if (!pid) return null;
    const urls = [
      `${BASE}/api/lookup/products?id=${encodeURIComponent(String(pid))}`,
      `${BASE}/sales.products.lookup.json?id=${encodeURIComponent(String(pid))}`
    ];
    for (const url of urls){
      try{
        const r = await fetch(url, { headers:{Accept:'application/json'} });
        if (!r.ok) continue;
        const j = await r.json();
        const rec = Array.isArray(j) ? j[0]
                  : (j?.items && Array.isArray(j.items) ? j.items[0]
                  : (j?.data && Array.isArray(j.data) ? j.data[0]
                  : (j?.result && Array.isArray(j.result) ? j.result[0] : j)));
        const val = rec?.unit_price ?? rec?.price ?? rec?.unitPrice ?? null;
        if (val != null) return Number(val);
      }catch(_){}
    }
    return null;
  }

  /* ---------- totals ---------- */
  function recalcTotals(){
    let sub = 0;
    document.querySelectorAll('#lines tr').forEach(tr=>{
      const v = tr.querySelector('input.ip.money[readonly]')?.value || '0';
      sub += Number(v.replace(/[^\d.-]/g,'')) || 0;
    });
    const t = $('#__inv_disc_type').value || 'amount';
    const dv = Number($('#discount_value').value || 0);
    const disc = t==='percent' ? Math.min(sub, sub*(dv/100)) : Math.min(sub, dv);
    const grand = Math.max(0, sub - disc);
    $('#t_sub').textContent = fmt2(sub);
    $('#t_disc').textContent = fmt2(disc);
    $('#t_grand').textContent= fmt2(grand);
    $('#__items_subtotal').value = fmt2(sub);
    $('#__discount_amount').value= fmt2(disc);
    $('#__grand_total').value    = fmt2(grand);
  }
  $('#discount_value').addEventListener('input', recalcTotals);

  /* ---------- table rows ---------- */
  const tbody = $('#lines');
  $('#addLine').addEventListener('click', ()=> addLine());

  function addLine(pref){
    const tr = document.createElement('tr');

    // Product input (+ hidden pid)
    const tdName = document.createElement('td');
    const nameIp = document.createElement('input');
    nameIp.className = 'ip';
    nameIp.placeholder = 'Type to search product…';
    nameIp.setAttribute('data-kf-lookup','products');
    nameIp.setAttribute('data-kf-target-id','.pid');
    nameIp.setAttribute('data-kf-target-price','.price'); // KF.lookup should populate .price
    if (pref?.name) nameIp.value = pref.name;

    const pidIp = document.createElement('input');
    pidIp.type='hidden'; pidIp.className='pid'; pidIp.name='items[][product_id]';
    pidIp.value = pref?.product_id || pref?.id || '';

    tdName.appendChild(nameIp); tdName.appendChild(pidIp);

    // Qty
    const tdQty = document.createElement('td'); tdQty.className='qty';
    const qtyIp = document.createElement('input');
    qtyIp.type='number'; qtyIp.min='0'; qtyIp.step='0.01'; qtyIp.className='ip qty';
    qtyIp.name='items[][qty]'; qtyIp.value = pref?.qty ?? 1;
    tdQty.appendChild(qtyIp);

    // Unit Price
    const tdPrice = document.createElement('td'); tdPrice.className='money';
    const priceIp = document.createElement('input');
    priceIp.type='number'; priceIp.min='0'; priceIp.step='0.01'; priceIp.className='ip money price';
    priceIp.name='items[][unit_price]'; priceIp.value = pref?.price ?? 0;
    tdPrice.appendChild(priceIp);

    // Line Total (readonly input for consistent visuals)
    const tdTotal = document.createElement('td'); tdTotal.className='money';
    const totalIp = document.createElement('input');
    totalIp.type='text'; totalIp.readOnly = true; totalIp.className='ip money'; totalIp.value='0.00';
    tdTotal.appendChild(totalIp);

    // Remove
    const tdRm = document.createElement('td');
    const rm = document.createElement('span'); rm.className='rm'; rm.textContent='Remove';
    tdRm.appendChild(rm);

    tr.append(tdName, tdQty, tdPrice, tdTotal, tdRm);
    tbody.appendChild(tr);

    /* --- row helpers --- */
    const recalcRow = ()=>{
      const q = +qtyIp.value || 0, p = +priceIp.value || 0;
      totalIp.value = fmt2(q * p);
      recalcTotals();
    };

    // Autofill price when product is chosen (KF.lookup → custom event)
    nameIp.addEventListener('kf:select', (ev)=>{
      // ev.detail should contain the selected item
      const d = (ev && ev.detail) || {};
      const pid = d.id ?? d.product_id;
      const p   = d.unit_price ?? d.price ?? null;
      if (pid) pidIp.value = String(pid);
      if (p != null && !isNaN(+p)) priceIp.value = fmt2(+p);
      recalcRow();
    });

    // Fallback: when KF populates hidden pid but didn’t carry price
    const pidObserver = new MutationObserver(async ()=>{
      if ((+priceIp.value || 0) > 0) { recalcRow(); return; }
      const pid = pidIp.value;
      if (!pid) return;
      const hinted = Number(nameIp.dataset.price || nameIp.getAttribute('data-price') || 0);
      if (hinted > 0) { priceIp.value = fmt2(hinted); recalcRow(); return; }
      const fetched = await fetchPriceForProduct(pid);
      if (fetched != null) { priceIp.value = fmt2(fetched); }
      recalcRow();
    });
    pidObserver.observe(pidIp, { attributes:true, attributeFilter:['value'] });

    // Normal input/change handlers
    qtyIp.addEventListener('input', recalcRow);
    priceIp.addEventListener('input', recalcRow);
    priceIp.addEventListener('change', recalcRow);
    nameIp.addEventListener('change', recalcRow);
    rm.addEventListener('click', ()=>{ tr.remove(); recalcTotals(); });

    // Bind global lookups on the new row
    window.KF?.rescan?.(tr);

    // If we prefed values, compute now (and try to fetch price when missing)
    setTimeout(()=>{ // allows KF to wire listeners first
      if ((+priceIp.value || 0) === 0) {
        const hinted = Number(nameIp.dataset.price || nameIp.getAttribute('data-price') || 0);
        if (hinted > 0) priceIp.value = fmt2(hinted);
      }
      recalcRow();
    }, 0);
  }

  // Initial rows
  if (!EX || !Array.isArray(EX.items) || EX.items.length === 0) {
    addLine();
  }

  /* ---------- hydrate from ORDER ---------- */
  const orderIdHidden = $('#order_id');
  let lastOrderId = orderIdHidden.value || '';
  function hydrateOrderById(id){
    if (!id) return;
    fetch(`${BASE}/api/orders/${encodeURIComponent(String(id))}/detail`, { headers:{Accept:'application/json'} })
      .then(r=> r.ok ? r.json() : null)
      .then(async res=>{
        if (!res) return;
        // Customer
        if (res.order?.customer_id || res.order?.customer_name){
          $('#customer_id').value    = res.order.customer_id || '';
          $('#customer_name').value  = res.order.customer_name || '';
          $('#customer_search').value= res.order.customer_name || '';
          const cid = 'CID-' + String(res.order.customer_id||0).padStart(6,'0');
          $('#customer_cid_box').value = cid;
          $('#cust_meta').textContent = 'Mobile: —';
        }
        // Lines from order
        tbody.innerHTML = '';
        const items = Array.isArray(res.items) ? res.items : [];
        for (const it of items){
          addLine({
            id: it.product_id || null,
            product_id: it.product_id || null,
            name: it.product_name || it.name || '',
            qty: it.qty || 1,
            price: (it.unit_price!=null ? it.unit_price : (it.price!=null ? it.price : 0)) || 0
          });
        }
        if (items.length === 0) addLine();
        window.KF?.rescan?.(tbody);
        recalcTotals();
      }).catch(()=>{});
  }

  const mo = new MutationObserver(()=>{
    const cur = orderIdHidden.value;
    if (cur && cur !== lastOrderId){
      lastOrderId = cur;
      hydrateOrderById(cur);
    }
  });
  mo.observe(orderIdHidden, { attributes:true, attributeFilter:['value'] });

  $('#order_search')?.addEventListener('change', ()=> {
    if (orderIdHidden.value && orderIdHidden.value !== lastOrderId){
      lastOrderId = orderIdHidden.value; hydrateOrderById(lastOrderId);
    }
  });

  /* ---------- EDIT hydration ---------- */
  if (EX) {
    const sd = document.querySelector('input[name="sale_date"]');
    if (sd) sd.value = EX.sale_date || sd.value || '';
    const sno = document.querySelector('#sale_no');
    if (sno) sno.value = EX.sale_no || sno.value || '';

    document.querySelectorAll('#__inv_status_tabs .tab')
      .forEach(b=> b.classList.toggle('tab--on', b.dataset.val === (EX.status||'draft')));
    document.querySelectorAll('#__inv_disc_tabs .tab')
      .forEach(b=> b.classList.toggle('tab--on', b.dataset.val === (EX.discount_type||'amount')));

    if (EX.customer_id) $('#customer_id').value = EX.customer_id;
    if (EX.customer_name) {
      $('#customer_name').value  = EX.customer_name;
      $('#customer_search').value= EX.customer_name;
      $('#customer_cid_box').value = 'CID-' + String(EX.customer_id||0).padStart(6,'0');
    }
    if (EX.delivery_user_id) $('#delivery_user_id').value = EX.delivery_user_id;
    if (EX.order_id) $('#order_id').value = EX.order_id;

    tbody.innerHTML = '';
    (EX.items || []).forEach(it => addLine({
      id: it.product_id,
      product_id: it.product_id,
      name: it.name,
      qty: it.qty,
      price: it.price
    }));
    if ((EX.items||[]).length === 0) addLine();

    window.KF?.rescan?.(tbody);
    recalcTotals();
  }

  /* ---------- submit guard ---------- */
  $('#invForm').addEventListener('submit', (e)=>{
    let ok=false;
    tbody.querySelectorAll('tr').forEach(tr=>{
      const pid = tr.querySelector('.pid')?.value;
      const q   = Number(tr.querySelector('.qty')?.value||0);
      if (pid && q>0) ok=true;
    });
    if (!ok){ e.preventDefault(); alert('Please add at least one valid line item.'); }
  });

  // Initial bind of global lookups
  window.KF?.rescan?.(document);
})();
</script>
