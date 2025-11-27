<?php
declare(strict_types=1);
/**
 * DMS · Products → Create
 * Controller provides:
 *   - array $categories [{id, code, name}]
 *   - array $suppliers  [{id, code, name, display?}]
 *   - array $uoms       [{id,name}]
 *   - array $productsQuick (optional)
 */
$org         = $org ?? [];
$module_base = $module_base ?? ('/t/'.rawurlencode($org['slug'] ?? '').'/apps/dms');
$h = fn($s)=>htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
$unitPills = ['pcs','box','kg','ltr','meter','set','pack','carton'];
?>
<div class="h-1 w-full bg-emerald-600 dark:bg-emerald-500 -mt-4 mb-4"></div>

<div class="max-w-7xl mx-auto px-4 py-6">
  <div class="flex items-center justify-between mb-4">
    <h1 class="text-2xl font-semibold text-emerald-700 dark:text-emerald-300">Add Product</h1>
    <div class="inline-flex rounded-lg border border-gray-200 dark:border-gray-700 overflow-hidden bg-white dark:bg-gray-800">
      <button id="tabSingle" type="button" class="px-3 py-1.5 text-sm font-medium bg-emerald-600 text-white">Single</button>
      <button id="tabBulk"   type="button" class="px-3 py-1.5 text-sm font-medium text-gray-700 dark:text-gray-200">Bulk upload</button>
    </div>
  </div>

  <?php if (!empty($error)): ?>
    <div class="mb-4 rounded-lg border border-rose-200 dark:border-rose-700 bg-rose-50 dark:bg-rose-900/30 px-4 py-3 text-rose-800 dark:text-rose-200 text-sm">
      <?= $h($error) ?>
    </div>
  <?php endif; ?>

  <!-- ================= Single ================= -->
  <form id="singleForm" method="post" action="<?= $h($module_base.'/products') ?>" class="space-y-6">
    <?= function_exists('csrf_field') ? csrf_field() : '' ?>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
      <!-- LEFT -->
      <div class="lg:col-span-2 space-y-6">

        <!-- Product + Barcode -->
        <section class="rounded-xl bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 p-6">
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div class="relative">
              <label class="block text-sm font-medium mb-1">Product Name</label>
              <input id="p_name" name="name" class="w-full rounded-lg border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-900 px-3 py-2"
                     placeholder="Type a new name or pick…" autocomplete="off"
                     data-kf-lookup="products" data-kf-target-id="#p_chosen_id" data-kf-target-price="#p_purchase" required>
              <input type="hidden" id="p_chosen_id">
              <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Search existing or type a new name.</p>
            </div>

            <div>
              <label class="block text-sm font-medium mb-1">Barcode</label>
              <div class="flex gap-2">
                <input id="p_barcode" name="barcode" class="flex-1 rounded-lg border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-900 px-3 py-2" placeholder="Scan or type">
                <button id="scanBtn" type="button" class="px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-700 hover:bg-emerald-50 dark:hover:bg-emerald-900/20">Scan</button>
              </div>
              <video id="scanVideo" playsinline class="hidden w-full max-h-64 mt-2 rounded-lg"></video>
            </div>
          </div>
        </section>

        <!-- Supplier / Category / UOM -->
        <section class="rounded-xl bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 p-6">
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div class="relative">
              <label class="block text-sm font-medium mb-1">Supplier</label>
              <input id="ac_supplier" class="w-full rounded-lg border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-900 px-3 py-2 text-sm"
                     placeholder="Search suppliers…" autocomplete="off"
                     data-kf-lookup="suppliers" data-kf-target-id="#supplier_id" data-kf-target-name="#supplier_name" data-kf-target-code="#supplier_code_view">
              <input type="hidden" id="supplier_id"   name="supplier_id">
              <input type="hidden" id="supplier_name" name="supplier_name">
              <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">We’ll save both ID and name.</p>
            </div>

            <div>
              <label class="block text-sm font-medium mb-1">Supplier Code</label>
              <input id="supplier_code_view" class="w-full rounded-lg border border-gray-300 dark:border-gray-700 bg-gray-50 dark:bg-gray-900/60 px-3 py-2" readonly placeholder="—">
            </div>

            <div class="relative">
              <label class="block text-sm font-medium mb-1">Category</label>
              <input id="ac_category" class="w-full rounded-lg border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-900 px-3 py-2 text-sm"
                     placeholder="Search categories…" autocomplete="off"
                     data-kf-lookup="categories" data-kf-target-id="#category_id" data-kf-target-name="#pvCategory">
              <input type="hidden" id="category_id" name="category_id">
            </div>

            <div>
              <label class="block text-sm font-medium mb-2">Unit of Measure</label>
              <input type="hidden" id="uom_id" name="uom_id">
              <div id="uomPills" class="flex flex-wrap gap-2">
                <?php foreach ($unitPills as $u): ?>
                  <button type="button" data-unit="<?= $h($u) ?>" class="px-3 py-1.5 rounded-full border border-gray-300 dark:border-gray-700 text-sm hover:bg-emerald-50 dark:hover:bg-emerald-900/20">
                    <?= strtoupper($h($u)) ?>
                  </button>
                <?php endforeach; ?>
              </div>

              <?php if (!empty($uoms)): ?>
                <div class="mt-2">
                  <label class="block text-xs text-gray-500 dark:text-gray-400 mb-1">Or pick from list</label>
                  <select id="uomSelect" class="w-56 rounded-lg border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-900 px-3 py-1.5 text-sm" data-choices>
                    <option value="">— Select Unit —</option>
                    <?php foreach ($uoms as $u): ?>
                      <option value="<?= $h((string)$u['id']) ?>"><?= $h((string)$u['name']) ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
              <?php endif; ?>
            </div>
          </div>
        </section>

        <!-- Prices / Qty / Dates / Spec / Status -->
        <section class="rounded-xl bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 p-6">
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
              <label class="block text-sm font-medium mb-1">Purchase Price (BDT)</label>
              <input id="p_purchase" name="purchase_price" type="number" min="0" step="0.01"
                     class="w-full rounded-lg border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-900 px-3 py-2" value="0">
              <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Cost/base price.</p>
            </div>

            <div>
              <div class="flex items-center justify-between">
                <label class="block text-sm font-medium mb-1">Sales Price (BDT)</label>
                <div class="flex items-center gap-2 text-xs">
                  <span id="lock_pm" class="px-1.5 py-0.5 rounded border border-emerald-200 bg-emerald-50 text-emerald-800">P+M→S</span>
                  <button type="button" id="toggleLock" class="underline text-emerald-700 dark:text-emerald-300">toggle</button>
                  <span id="lock_sp" class="px-1.5 py-0.5 rounded border border-gray-200 bg-gray-50">P&S→M%</span>
                </div>
              </div>
              <input id="p_sales" name="unit_price" type="number" min="0" step="0.01"
                     class="w-full rounded-lg border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-900 px-3 py-2" value="0">
              <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                Will create/update the <b>active price tier (BDT)</b> on save (backend publishes it automatically).
              </p>
            </div>

            <div>
              <label class="block text-sm font-medium mb-1">Margin %</label>
              <input id="p_margin" type="number" min="0" step="0.01"
                     class="w-full rounded-lg border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-900 px-3 py-2" value="0">
              <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">If locked to P+M→S, changing margin updates Sales.</p>
            </div>

            <div>
              <label class="block text-sm font-medium mb-1">Initial Quantity</label>
              <input id="p_qty" name="initial_qty" type="number" min="0" step="1"
                     class="w-full rounded-lg border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-900 px-3 py-2" value="0">
            </div>

            <div>
              <label class="block text-sm font-medium mb-1">Arrival Date</label>
              <input id="p_arrival" name="arrival_date" type="date"
                     value="<?= $h(date('Y-m-d')) ?>"
                     class="w-full rounded-lg border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-900 px-3 py-2">
            </div>
            <div>
              <label class="block text-sm font-medium mb-1">Expiry Date</label>
              <input id="p_expiry" name="expiry_date" type="date"
                     class="w-full rounded-lg border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-900 px-3 py-2" placeholder="Optional">
            </div>

            <div class="md:col-span-2">
              <label class="block text-sm font-medium mb-1">Specification (JSON)</label>
              <textarea id="p_spec" name="spec_json" rows="4"
                        class="w-full rounded-lg border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-900 px-3 py-2 font-mono text-sm"
                        placeholder='{"color":"black","warranty_months":12}'></textarea>
              <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Leave empty for {}. If invalid, the server will sanitize.</p>
            </div>

            <div>
              <label class="block text-sm font-medium mb-1">Status</label>
              <select name="status" class="w-full rounded-lg border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-900 px-3 py-2">
                <option value="active" selected>Active</option>
                <option value="inactive">Inactive</option>
              </select>
            </div>
          </div>
        </section>

        <div class="flex flex-col gap-1">
          <div class="flex items-center gap-2 text-xs text-emerald-700 dark:text-emerald-300 mb-1">
            <span class="px-1.5 py-0.5 rounded border border-emerald-200 bg-emerald-50 dark:bg-emerald-900/30">Pricing</span>
            <span>Sales Price (BDT) → publishes a live tier (no overlaps).</span>
          </div>
          <div class="flex items-center gap-3">
            <a href="<?= $h($module_base.'/products') ?>" class="px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-800">Cancel</a>
            <button type="submit" class="px-4 py-2 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white">Save Product</button>
          </div>
        </div>
      </div>

      <!-- RIGHT: Preview -->
      <aside class="lg:sticky lg:top-4 self-start">
        <div class="rounded-xl bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 p-6 w-full">
          <h3 class="font-semibold mb-2 text-emerald-700 dark:text-emerald-300">Preview</h3>
          <dl class="text-sm space-y-2">
            <div class="flex justify-between"><dt class="text-gray-500 dark:text-gray-400">Supplier</dt><dd id="pvSupplier" class="text-gray-900 dark:text-gray-100">—</dd></div>
            <div class="flex justify-between"><dt class="text-gray-500 dark:text-gray-400">Category</dt><dd id="pvCategory" class="text-gray-900 dark:text-gray-100">—</dd></div>
            <div class="flex justify-between"><dt class="text-gray-500 dark:text-gray-400">Unit</dt><dd id="pvUnit" class="text-gray-900 dark:text-gray-100">—</dd></div>
            <div class="flex justify-between"><dt class="text-gray-500 dark:text-gray-400">Sales Price</dt><dd id="pvPrice" class="text-gray-900 dark:text-gray-100">0.00</dd></div>
            <div class="flex justify-between"><dt class="text-gray-500 dark:text-gray-400">Arrival</dt><dd id="pvArr" class="text-gray-900 dark:text-gray-100"><?= $h(date('Y-m-d')) ?></dd></div>
            <div class="flex justify-between"><dt class="text-gray-500 dark:text-gray-400">Expiry</dt><dd id="pvExp" class="text-gray-900 dark:text-gray-100">—</dd></div>
          </dl>
        </div>
      </aside>
    </div>
  </form>


  <!-- ================= Bulk ================= -->
  <form id="bulkForm" method="post" action="<?= $h($module_base.'/products/bulk') ?>" class="hidden space-y-4">
    <?= function_exists('csrf_field') ? csrf_field() : '' ?>
    <section class="rounded-xl bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 p-6">
      <div class="flex items-center justify-between flex-wrap gap-3">
        <div>
          <h3 class="font-semibold text-emerald-700 dark:text-emerald-300">Bulk upload</h3>
          <p class="text-sm text-gray-500 dark:text-gray-400">Paste CSV or upload a .csv file. We’ll preview and validate before submit.</p>
        </div>
        <a id="dlTpl" class="px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-800" href="#" download="products_template.csv">Download CSV template</a>
      </div>

      <div class="grid md:grid-cols-2 gap-4 mt-4">
        <div>
          <label class="block text-sm font-medium mb-1">Paste CSV</label>
          <textarea id="csvPaste" rows="10"
            class="w-full rounded-lg border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-900 px-3 py-2 font-mono text-sm"
            placeholder="name,category_id,uom_id,purchase_price,unit_price,barcode,initial_qty,arrival_date,expiry_date,status,supplier_name&#10;Laptop,1,1,75000,90000,1234567890123,0,2025-01-10,,active,Acme Supplies"></textarea>
          <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
            Required: <b>name</b>. Recommended: category_id, uom_id, <b>purchase_price</b>, <b>unit_price</b>.
          </p>
        </div>
        <div>
          <label class="block text-sm font-medium mb-1">Or upload .csv</label>
          <input id="csvFile" type="file" accept=".csv" class="w-full rounded-lg border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-900 px-3 py-2">
        </div>
      </div>

      <div class="flex items-center gap-2 mt-3">
        <button type="button" id="parseBtn" class="px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-700">Preview</button>
        <button type="submit" id="bulkSubmit" class="px-4 py-2 rounded-lg bg-emerald-600 text-white disabled:opacity-50" disabled>Submit (0)</button>
      </div>

      <div id="bulkWarn" class="hidden mt-3 text-sm text-amber-700 dark:text-amber-300 bg-amber-50 dark:bg-amber-900/30 border border-amber-200 dark:border-amber-700 rounded-lg p-3"></div>

      <div id="bulkPreviewWrap" class="hidden mt-3 rounded-xl border border-gray-200 dark:border-gray-700 overflow-auto">
        <table class="w-full text-sm">
          <thead class="bg-gray-50 dark:bg-gray-900 sticky top-0">
            <tr>
              <th class="text-left px-3 py-2">#</th>
              <th class="text-left px-3 py-2">name*</th>
              <th class="text-left px-3 py-2">category_id</th>
              <th class="text-left px-3 py-2">uom_id</th>
              <th class="text-left px-3 py-2">purchase_price</th>
              <th class="text-left px-3 py-2">unit_price</th>
              <th class="text-left px-3 py-2">barcode</th>
              <th class="text-left px-3 py-2">initial_qty</th>
              <th class="text-left px-3 py-2">arrival_date</th>
              <th class="text-left px-3 py-2">expiry_date</th>
              <th class="text-left px-3 py-2">status</th>
              <th class="text-left px-3 py-2">supplier_name</th>
            </tr>
          </thead>
          <tbody id="bulkTbody" class="divide-y divide-gray-200 dark:divide-gray-800"></tbody>
        </table>
      </div>
      <input type="hidden" id="bulk_json" name="bulk_json">
    </section>
  </form>
</div>

<script>
(() => {
  const setText = (id,v)=>{ const el=document.getElementById(id); if(el) el.textContent=v||'—'; };

  /* ---------- tabs ---------- */
  const tabSingle = document.getElementById('tabSingle');
  const tabBulk   = document.getElementById('tabBulk');
  const singleForm= document.getElementById('singleForm');
  const bulkForm  = document.getElementById('bulkForm');
  function setTab(which){
    const s = which === 'single';
    singleForm.classList.toggle('hidden', !s);
    bulkForm.classList.toggle('hidden', s);
    tabSingle.classList.toggle('bg-emerald-600', s);
    tabSingle.classList.toggle('text-white', s);
    tabBulk.classList.toggle('bg-emerald-600', !s);
    tabBulk.classList.toggle('text-white', !s);
  }
  tabSingle?.addEventListener('click', ()=>setTab('single'));
  tabBulk?.addEventListener('click',   ()=>setTab('bulk'));
  setTab('single');

  /* ---------- preview mirrors ---------- */
  function mirror(){
    setText('pvPrice', (document.getElementById('p_sales')?.value || '0.00'));
    setText('pvArr',   document.getElementById('p_arrival')?.value || '—');
    setText('pvExp',   document.getElementById('p_expiry')?.value || '—');
  }
  ['p_sales','p_arrival','p_expiry'].forEach(id=>{
    const el=document.getElementById(id);
    if(el){ el.addEventListener('input', mirror); el.addEventListener('change', mirror); }
  });
  mirror();

  /* ---------- price lock logic ---------- */
  let lock = 'pm'; // pm = purchase+margin -> sales; sp = sales & purchase -> margin
  const elPM = document.getElementById('lock_pm');
  const elSP = document.getElementById('lock_sp');
  document.getElementById('toggleLock').addEventListener('click', () => {
    lock = (lock === 'pm') ? 'sp' : 'pm';
    elPM.className = 'px-1.5 py-0.5 rounded border ' + (lock==='pm'?'bg-emerald-50 border-emerald-200 text-emerald-800':'bg-gray-50 border-gray-200');
    elSP.className = 'px-1.5 py-0.5 rounded border ' + (lock==='sp'?'bg-emerald-50 border-emerald-200 text-emerald-800':'bg-gray-50 border-gray-200');
    recalc();
  });
  ['p_purchase','p_sales','p_margin'].forEach(id => {
    const el = document.getElementById(id);
    el && el.addEventListener('input', recalc);
  });
  function recalc(){
    const p = Number(document.getElementById('p_purchase').value||0);
    const sEl = document.getElementById('p_sales');
    const mEl = document.getElementById('p_margin');
    let s = Number(sEl.value||0);
    let m = Number(mEl.value||0);
    if (lock==='pm'){
      s = p * (1 + m/100);
      sEl.value = s.toFixed(2);
    } else {
      m = p>0 ? ((s - p)/p)*100 : 0;
      mEl.value = m.toFixed(2);
    }
    setText('pvPrice', sEl.value);
  }
  recalc();

  /* ---------- UOM pills + select sync ---------- */
  const uomPills = document.getElementById('uomPills');
  const uomSelect= document.getElementById('uomSelect');
  function setUomPreview(text){ setText('pvUnit', text || '—'); }
  uomPills?.addEventListener('click', (e)=>{
    const btn=e.target.closest('button[data-unit]'); if(!btn) return;
    uomPills.querySelectorAll('button[data-unit]').forEach(b=>b.classList.remove('bg-emerald-100','text-emerald-800','border-emerald-300','ring-1','ring-emerald-300'));
    btn.classList.add('bg-emerald-100','text-emerald-800','border-emerald-300','ring-1','ring-emerald-300');
    document.getElementById('uom_id').value='';
    setUomPreview(btn.getAttribute('data-unit').toUpperCase());
    if (uomSelect) uomSelect.value='';
  });
  uomSelect?.addEventListener('change', ()=>{
    document.getElementById('uom_id').value = uomSelect.value || '';
    if (uomSelect.value) setUomPreview(uomSelect.selectedOptions[0].textContent.trim());
  });

  /* ---------- Supplier/Category preview via input changes (KF.lookup fills hidden fields already) ---------- */
  document.getElementById('ac_supplier')?.addEventListener('change', ()=> {
    const name = document.getElementById('supplier_name')?.value || '';
    setText('pvSupplier', name || '—');
  });
  document.getElementById('ac_category')?.addEventListener('change', ()=> {
    // pvCategory text is updated by KF.lookup binder
  });

  /* ---------- barcode scan (native) ---------- */
  const scanBtn  = document.getElementById('scanBtn');
  const scanVideo= document.getElementById('scanVideo');
  scanBtn?.addEventListener('click', async ()=>{
    try{
      if(!('BarcodeDetector' in window)){ alert('Barcode scanning not supported.'); return; }
      const detector=new BarcodeDetector({formats:['ean_13','ean_8','code_128','code_39','upc_a','upc_e','qr_code']});
      const stream=await navigator.mediaDevices.getUserMedia({video:{facingMode:'environment'}});
      scanVideo.srcObject=stream; scanVideo.classList.remove('hidden'); await scanVideo.play();
      const tick=async()=>{
        if(scanVideo.readyState!==4){ requestAnimationFrame(tick); return; }
        try{
          const codes=await detector.detect(scanVideo);
          if(codes?.length){
            document.getElementById('p_barcode').value=codes[0].rawValue||'';
            stream.getTracks().forEach(t=>t.stop()); scanVideo.pause(); scanVideo.classList.add('hidden'); scanVideo.srcObject=null;
          }
        }catch{}
        requestAnimationFrame(tick);
      };
      requestAnimationFrame(tick);
    }catch{ alert('Camera not available.'); }
  });

  /* ---------- Bulk CSV (no external libs) ---------- */
  const csvFile   = document.getElementById('csvFile');
  const csvPaste  = document.getElementById('csvPaste');
  const parseBtn  = document.getElementById('parseBtn');
  const bulkWarn  = document.getElementById('bulkWarn');
  const bulkPrev  = document.getElementById('bulkPreviewWrap');
  const bulkTbody = document.getElementById('bulkTbody');
  const bulkJson  = document.getElementById('bulk_json');
  const bulkSubmit= document.getElementById('bulkSubmit');
  const dlTpl     = document.getElementById('dlTpl');

  dlTpl?.addEventListener('click', ()=>{
    const hdr='name,category_id,uom_id,purchase_price,unit_price,barcode,initial_qty,arrival_date,expiry_date,status,supplier_name';
    const row='Laptop,1,1,75000,90000,1234567890123,0,2025-01-10,,active,Acme Supplies';
    const blob=new Blob([hdr+'\n'+row+'\n'],{type:'text/csv'});
    dlTpl.href=URL.createObjectURL(blob);
  });

  function parseCSV(text){
    const lines=text.replace(/\r\n/g,'\n').replace(/\r/g,'\n').split('\n').filter(l=>l.trim());
    return lines.map(l=>{
      const out=[]; let cur=''; let q=false;
      for(let i=0;i<l.length;i++){
        const ch=l[i];
        if(q){ if(ch=='"'&&l[i+1]=='"'){cur+='"';i++;} else if(ch=='"'){ q=false; } else cur+=ch; }
        else { if(ch==','){ out.push(cur); cur=''; } else if(ch=='"'){ q=true; } else cur+=ch; }
      }
      out.push(cur);
      return out;
    });
  }
  function toObjects(rows){
    if(!rows.length) return [];
    const hdr=rows[0].map(h=>h.trim().toLowerCase());
    return rows.slice(1).map(r=>{
      const o={}; hdr.forEach((h,i)=>o[h]=(r[i]??'').trim()); return o;
    }).filter(o=>Object.values(o).some(v=>v));
  }
  function preview(items){
    bulkTbody.innerHTML=''; let hasErr=false; const out=[];
    items.forEach((it,ix)=>{
      const o={
        name: it.name||'',
        category_id: it.category_id||'',
        uom_id: it.uom_id||'',
        purchase_price: it.purchase_price?Number(it.purchase_price):'',
        unit_price: it.unit_price?Number(it.unit_price):'',
        barcode: it.barcode||'',
        initial_qty: it.initial_qty||'',
        arrival_date: it.arrival_date||'',
        expiry_date: it.expiry_date||'',
        status: (it.status||'active').toLowerCase()==='inactive'?'inactive':'active',
        supplier_name: it.supplier_name||''
      };
      const tr=document.createElement('tr');
      const bad = !o.name || (o.expiry_date && o.arrival_date && new Date(o.expiry_date) < new Date(o.arrival_date));
      tr.className = bad ? 'bg-rose-50 dark:bg-rose-900/20' : '';
      tr.innerHTML = `
        <td class="px-3 py-2">${ix+1}</td>
        <td class="px-3 py-2 ${!o.name?'text-rose-600 dark:text-rose-300':''}">${o.name||'—'}</td>
        <td class="px-3 py-2">${o.category_id||'—'}</td>
        <td class="px-3 py-2">${o.uom_id||'—'}</td>
        <td class="px-3 py-2">${o.purchase_price!==''?Number(o.purchase_price).toLocaleString():'—'}</td>
        <td class="px-3 py-2">${o.unit_price!==''?Number(o.unit_price).toLocaleString():'—'}</td>
        <td class="px-3 py-2">${o.barcode||'—'}</td>
        <td class="px-3 py-2">${o.initial_qty||'—'}</td>
        <td class="px-3 py-2">${o.arrival_date||'—'}</td>
        <td class="px-3 py-2">${o.expiry_date||'—'}</td>
        <td class="px-3 py-2">${o.status}</td>
        <td class="px-3 py-2">${o.supplier_name||'—'}</td>`;
      bulkTbody.appendChild(tr);
      hasErr = hasErr || bad; out.push(o);
    });
    bulkWarn.classList.toggle('hidden', !hasErr);
    bulkWarn.textContent = hasErr ? 'Some rows have issues (highlighted).' : '';
    bulkPrev.classList.remove('hidden');
    bulkJson.value = JSON.stringify(out);
    bulkSubmit.disabled = out.length===0 || hasErr;
    bulkSubmit.textContent = `Submit (${out.length})`;
  }
  document.getElementById('parseBtn')?.addEventListener('click', ()=>{
    const t=csvPaste?.value.trim();
    if(!t){ bulkWarn.classList.remove('hidden'); bulkWarn.textContent='Paste CSV first or choose a file.'; return; }
    preview(toObjects(parseCSV(t)));
  });
  document.getElementById('csvFile')?.addEventListener('change', async (e)=>{
    const f=e.target.files?.[0]; if(!f) return;
    preview(toObjects(parseCSV(await f.text())));
  });
})();
</script>