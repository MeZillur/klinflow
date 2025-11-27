<?php
declare(strict_types=1);
/** @var array $categories  expected: [{id, code, name}] */
/** @var array $suppliers   expected: [{id, code, name}] */
/** @var array $uoms        optional; if empty we’ll show pills */
$org         = $org ?? [];
$module_base = $module_base ?? ('/t/'.rawurlencode($org['slug'] ?? '').'/apps/dms');

$unitPills = ['pcs','box','kg','ltr','meter','set','pack','carton'];
$h = fn($s)=>htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
?>
<div class="max-w-7xl mx-auto px-4 py-6">
  <div class="flex items-center justify-between mb-4">
    <h1 class="text-2xl font-semibold">Add Product</h1>

    <!-- Tabs -->
    <div class="inline-flex rounded-xl border border-gray-200 bg-white overflow-hidden">
      <button type="button" id="tabSingle" class="px-3 py-1.5 text-sm font-medium bg-emerald-600 text-white">Single</button>
      <button type="button" id="tabBulk"   class="px-3 py-1.5 text-sm font-medium text-gray-700">Bulk upload</button>
    </div>
  </div>

  <?php if (!empty($error)): ?>
    <div class="mb-4 rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-rose-800 text-sm"><?= $h($error) ?></div>
  <?php endif; ?>

  <!-- ========== SINGLE MODE ========== -->
  <form id="singleForm" method="post" action="<?= $h($module_base.'/products') ?>" class="space-y-6">
    <?= function_exists('csrf_field') ? csrf_field() : '' ?>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
      <!-- Left main -->
      <div class="lg:col-span-2 space-y-6">

        <!-- Product & Barcode -->
        <div class="rounded-2xl bg-white border p-6">
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
              <label class="block text-sm font-medium mb-1">Product Name</label>
              <!-- You can type fresh names here -->
              <input id="p_name" name="name" class="w-full rounded-lg border px-3 py-2" placeholder="e.g., Laptop Pro 14" required>
              <!-- Quick pick dropdown (searchable) -->
              <div class="relative mt-2">
                <button type="button" id="btnPickProduct" class="w-full text-left rounded-lg border px-3 py-2 text-sm flex items-center justify-between">
                  <span>— Select Product —</span>
                  <span class="text-gray-500">▾</span>
                </button>
                <div id="productMenu" class="hidden absolute z-10 mt-1 w-full rounded-lg border bg-white shadow">
                  <div class="p-2 border-b">
                    <input id="productSearch" class="w-full rounded-md border px-3 py-2 text-sm" placeholder="Search product…">
                    <p class="text-xs text-gray-500 mt-1">Quick pick from existing names, or just type a new name above.</p>
                  </div>
                  <ul id="productList" class="max-h-60 overflow-y-auto py-1 text-sm"></ul>
                </div>
              </div>
            </div>

            <div>
              <label class="block text-sm font-medium mb-1">Barcode</label>
              <div class="flex gap-2">
                <input id="p_barcode" name="barcode" class="flex-1 rounded-lg border px-3 py-2" placeholder="Scan or type">
                <button type="button" id="scanBtn" class="px-3 py-2 rounded-lg border">Scan</button>
              </div>
              <video id="scanVideo" playsinline class="hidden w-full max-h-64 mt-2 rounded-lg"></video>
            </div>
          </div>
        </div>

        <!-- Supplier & Category & UOM -->
        <div class="rounded-2xl bg-white border p-6">
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <!-- Supplier -->
            <div>
              <label class="block text-sm font-medium mb-1">Supplier</label>
              <div class="relative">
                <button type="button" id="btnSupplier" class="w-full text-left rounded-lg border px-3 py-2 text-sm flex items-center justify-between">
                  <span id="supplierChosen">— Select Supplier —</span>
                  <span class="text-gray-500">▾</span>
                </button>
                <div id="supplierMenu" class="hidden absolute z-20 mt-1 w-full rounded-lg border bg-white shadow">
                  <div class="p-2 border-b">
                    <input id="supplierSearch" class="w-full rounded-md border px-3 py-2 text-sm" placeholder="Search supplier…">
                  </div>
                  <ul id="supplierList" class="max-h-60 overflow-y-auto py-1 text-sm"></ul>
                </div>
                <input type="hidden" id="supplier_id" name="supplier_id">
                <input type="hidden" id="supplier_name" name="supplier_name">
              </div>
              <p class="text-xs text-gray-500 mt-1">Choosing a supplier will store both ID and name.</p>
            </div>

            <div>
              <label class="block text-sm font-medium mb-1">Supplier Code</label>
              <input id="supplier_code_view" class="w-full rounded-lg border px-3 py-2 bg-gray-50" readonly placeholder="—">
            </div>

            <!-- Category -->
            <div>
              <label class="block text-sm font-medium mb-1">Category</label>
              <div class="relative">
                <button type="button" id="btnCategory" class="w-full text-left rounded-lg border px-3 py-2 text-sm flex items-center justify-between">
                  <span id="categoryChosen">— Select Category —</span><span class="text-gray-500">▾</span>
                </button>
                <div id="categoryMenu" class="hidden absolute z-20 mt-1 w-full rounded-lg border bg-white shadow">
                  <div class="p-2 border-b">
                    <input id="categorySearch" class="w-full rounded-md border px-3 py-2 text-sm" placeholder="Search category…">
                  </div>
                  <ul id="categoryList" class="max-h-60 overflow-y-auto py-1 text-sm"></ul>
                </div>
                <input type="hidden" id="category_id" name="category_id">
              </div>
            </div>

            <!-- Unit of Measure -->
            <div>
              <label class="block text-sm font-medium mb-2">Unit of Measure</label>
              <input type="hidden" id="uom_id" name="uom_id">
              <div id="uomPills" class="flex flex-wrap gap-2">
                <?php foreach ($unitPills as $u): ?>
                  <button type="button"
                          data-unit="<?= $h($u) ?>"
                          class="px-3 py-1.5 rounded-full border text-sm hover:bg-emerald-50">
                    <?= strtoupper($h($u)) ?>
                  </button>
                <?php endforeach; ?>
                <!-- optional dropdown if you pass $uoms -->
                <?php if (!empty($uoms)): ?>
                  <div class="relative">
                    <button type="button" id="btnUom" class="px-3 py-1.5 rounded-full border text-sm">— Select Unit —</button>
                    <div id="uomMenu" class="hidden absolute z-20 mt-1 w-56 rounded-lg border bg-white shadow">
                      <div class="p-2 border-b">
                        <input id="uomSearch" class="w-full rounded-md border px-3 py-2 text-sm" placeholder="Search unit…">
                      </div>
                      <ul id="uomList" class="max-h-60 overflow-y-auto py-1 text-sm"></ul>
                    </div>
                  </div>
                <?php endif; ?>
              </div>
            </div>
          </div>
        </div>

        <!-- Price, Qty, Dates, Spec -->
        <div class="rounded-2xl bg-white border p-6">
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
              <label class="block text-sm font-medium mb-1">Unit Price (BDT)</label>
              <input id="p_price" name="unit_price" type="number" min="0" step="0.01" class="w-full rounded-lg border px-3 py-2" value="0">
            </div>
            <div>
              <label class="block text-sm font-medium mb-1">Initial Quantity</label>
              <input id="p_qty" name="initial_qty" type="number" min="0" step="1" class="w-full rounded-lg border px-3 py-2" value="0">
            </div>
            <div>
              <label class="block text-sm font-medium mb-1">Arrival Date</label>
              <input id="p_arrival" name="arrival_date" type="date" value="<?= $h(date('Y-m-d')) ?>" class="w-full rounded-lg border px-3 py-2">
            </div>
            <div>
              <label class="block text-sm font-medium mb-1">Expiry Date</label>
              <input id="p_expiry" name="expiry_date" type="date" class="w-full rounded-lg border px-3 py-2" placeholder="Optional">
            </div>
            <div class="md:col-span-2">
              <label class="block text-sm font-medium mb-1">Specification (JSON)</label>
              <textarea id="p_spec" name="spec_json" rows="4" class="w-full rounded-lg border px-3 py-2 font-mono text-sm" placeholder='{"color":"black","warranty_months":12}'></textarea>
              <p class="text-xs text-gray-500 mt-1">Leave empty for {}. If invalid, we’ll wrap it safely on save.</p>
            </div>
            <div>
              <label class="block text-sm font-medium mb-1">Status</label>
              <select name="status" class="w-full rounded-lg border px-3 py-2">
                <option value="active" selected>Active</option>
                <option value="inactive">Inactive</option>
              </select>
            </div>
          </div>
        </div>

        <div class="flex items-center gap-3">
          <a href="<?= $h($module_base.'/products') ?>" class="px-3 py-2 rounded-lg border">Cancel</a>
          <button type="submit" class="px-4 py-2 rounded-lg bg-emerald-600 text-white hover:bg-emerald-700">Save Product</button>
        </div>
      </div>

      <!-- Preview -->
      <aside class="lg:sticky lg:top-4 self-start">
        <div class="rounded-2xl bg-white border p-6 w-full">
          <h3 class="font-semibold mb-2">Preview</h3>
          <dl class="text-sm space-y-2">
            <div class="flex justify-between"><dt class="text-gray-500">Supplier</dt><dd id="pvSupplier" class="text-gray-900">—</dd></div>
            <div class="flex justify-between"><dt class="text-gray-500">Category</dt><dd id="pvCategory" class="text-gray-900">—</dd></div>
            <div class="flex justify-between"><dt class="text-gray-500">Unit</dt><dd id="pvUnit" class="text-gray-900">—</dd></div>
            <div class="flex justify-between"><dt class="text-gray-500">Price</dt><dd id="pvPrice" class="text-gray-900">0</dd></div>
            <div class="flex justify-between"><dt class="text-gray-500">Arrival</dt><dd id="pvArr" class="text-gray-900"><?= $h(date('Y-m-d')) ?></dd></div>
            <div class="flex justify-between"><dt class="text-gray-500">Expiry</dt><dd id="pvExp" class="text-gray-900">—</dd></div>
          </dl>
        </div>
      </aside>
    </div>
  </form>

  <!-- ========== BULK MODE ========== -->
  <form id="bulkForm" method="post" action="<?= $h($module_base.'/products/bulk') ?>" class="hidden space-y-4">
    <?= function_exists('csrf_field') ? csrf_field() : '' ?>
    <div class="rounded-2xl bg-white border p-6">
      <div class="flex items-center justify-between flex-wrap gap-3">
        <div>
          <h3 class="font-semibold">Bulk upload</h3>
          <p class="text-sm text-gray-500">Paste CSV or upload a .csv file. We’ll preview and validate before submit.</p>
        </div>
        <a id="dlTpl" class="px-3 py-2 rounded-lg border" href="#" download="products_template.csv">Download CSV template</a>
      </div>

      <div class="grid md:grid-cols-2 gap-4 mt-4">
        <div>
          <label class="block text-sm font-medium mb-1">Paste CSV</label>
          <textarea id="csvPaste" rows="10" class="w-full rounded-lg border px-3 py-2 font-mono text-sm"
            placeholder="name,category_id,uom_id,unit_price,barcode,initial_qty,arrival_date,expiry_date,status,supplier_name&#10;Laptop,1,1,90000,1234567890123,0,2025-01-10,,active,Acme Supplies"></textarea>
          <p class="text-xs text-gray-500 mt-1">Required: <b>name</b>. Recommended: category_id, uom_id, unit_price.</p>
        </div>
        <div>
          <label class="block text-sm font-medium mb-1">Or upload .csv</label>
          <input id="csvFile" type="file" accept=".csv" class="w-full rounded-lg border px-3 py-2">
        </div>
      </div>

      <div class="flex items-center gap-2 mt-3">
        <button type="button" id="parseBtn" class="px-3 py-2 rounded-lg border">Preview</button>
        <button type="submit" id="bulkSubmit" class="px-4 py-2 rounded-lg bg-emerald-600 text-white disabled:opacity-50" disabled>Submit (0)</button>
      </div>

      <div id="bulkWarn" class="hidden mt-3 text-sm text-amber-700 bg-amber-50 border border-amber-200 rounded-lg p-3"></div>

      <div id="bulkPreviewWrap" class="hidden mt-3 rounded-xl border overflow-auto">
        <table class="w-full text-sm">
          <thead class="bg-gray-50 sticky top-0">
            <tr>
              <th class="text-left px-3 py-2">#</th>
              <th class="text-left px-3 py-2">name*</th>
              <th class="text-left px-3 py-2">category_id</th>
              <th class="text-left px-3 py-2">uom_id</th>
              <th class="text-left px-3 py-2">unit_price</th>
              <th class="text-left px-3 py-2">barcode</th>
              <th class="text-left px-3 py-2">initial_qty</th>
              <th class="text-left px-3 py-2">arrival_date</th>
              <th class="text-left px-3 py-2">expiry_date</th>
              <th class="text-left px-3 py-2">status</th>
              <th class="text-left px-3 py-2">supplier_name</th>
            </tr>
          </thead>
          <tbody id="bulkTbody" class="divide-y"></tbody>
        </table>
      </div>
      <input type="hidden" id="bulk_json" name="bulk_json">
    </div>
  </form>
</div>

<script>
(() => {
  const BASE = <?= json_encode($module_base) ?>;

  // Tab switching
  const tabSingle = document.getElementById('tabSingle');
  const tabBulk   = document.getElementById('tabBulk');
  const singleForm= document.getElementById('singleForm');
  const bulkForm  = document.getElementById('bulkForm');
  function setTab(mode){
    const isSingle = mode==='single';
    singleForm.classList.toggle('hidden', !isSingle);
    bulkForm.classList.toggle('hidden', isSingle);
    tabSingle.classList.toggle('bg-emerald-600', isSingle);
    tabSingle.classList.toggle('text-white', isSingle);
    tabBulk.classList.toggle('bg-emerald-600', !isSingle);
    tabBulk.classList.toggle('text-white', !isSingle);
  }
  tabSingle.addEventListener('click', ()=>setTab('single'));
  tabBulk.addEventListener('click',   ()=>setTab('bulk'));

  // ---------------- Preview mirrors
  function setText(id, val){ const el=document.getElementById(id); if(el) el.textContent = val||'—'; }
  function mirror(){
    setText('pvPrice', document.getElementById('p_price').value || '0');
    setText('pvArr',   document.getElementById('p_arrival').value || '—');
    setText('pvExp',   document.getElementById('p_expiry').value || '—');
  }
  ['p_price','p_arrival','p_expiry'].forEach(id=>{
    const el=document.getElementById(id); if(el){ el.addEventListener('input', mirror); el.addEventListener('change', mirror); }
  });

  // ------------- Simple searchable dropdown factory
  function buildSearchable(buttonEl, menuEl, searchEl, listEl, items, renderLabel, onPick){
    function open(){ menuEl.classList.remove('hidden'); searchEl.focus(); filter(''); }
    function close(){ menuEl.classList.add('hidden'); }
    function filter(q){
      const needle = (q||'').toLowerCase();
      listEl.innerHTML = '';
      (items||[]).filter(x => renderLabel(x).toLowerCase().includes(needle))
        .forEach((x,i)=>{
          const li=document.createElement('li');
          li.className = 'px-3 py-2 hover:bg-gray-50 cursor-pointer';
          li.textContent = renderLabel(x);
          li.addEventListener('click', ()=>{ onPick(x); close(); });
          listEl.appendChild(li);
        });
      if(!listEl.children.length){
        const li=document.createElement('li');
        li.className='px-3 py-2 text-gray-500';
        li.textContent='No results';
        listEl.appendChild(li);
      }
    }
    buttonEl.addEventListener('click', ()=> menuEl.classList.contains('hidden') ? open() : close());
    searchEl.addEventListener('input', (e)=> filter(e.target.value));
    document.addEventListener('click', (e)=> {
      if (!menuEl.contains(e.target) && e.target!==buttonEl) close();
    });
  }

  // ---------------- Supplier dropdown
  const suppliers = <?= json_encode($suppliers ?? []) ?>;
  const btnSupplier = document.getElementById('btnSupplier');
  buildSearchable(
    btnSupplier,
    document.getElementById('supplierMenu'),
    document.getElementById('supplierSearch'),
    document.getElementById('supplierList'),
    suppliers,
    s => (s.name || '') + (s.code ? ' — '+s.code : ''),
    s => {
      document.getElementById('supplier_id').value = s.id || '';
      document.getElementById('supplier_name').value = s.name || '';
      document.getElementById('supplier_code_view').value = s.code || '';
      document.getElementById('supplierChosen').textContent = s.name || '— Select Supplier —';
      setText('pvSupplier', s.name || '');
    }
  );

  // ---------------- Category dropdown
  const categories = <?= json_encode($categories ?? []) ?>;
  const btnCategory = document.getElementById('btnCategory');
  buildSearchable(
    btnCategory,
    document.getElementById('categoryMenu'),
    document.getElementById('categorySearch'),
    document.getElementById('categoryList'),
    categories,
    c => (c.name || '') + (c.code ? ' — '+c.code : ''),
    c => {
      document.getElementById('category_id').value = c.id || '';
      document.getElementById('categoryChosen').textContent = c.name || '— Select Category —';
      setText('pvCategory', c.code || c.name || '');
    }
  );

  // ---------------- UOM pills (and optional dropdown)
  const uomPills = document.getElementById('uomPills');
  uomPills?.addEventListener('click', (e)=>{
    const btn = e.target.closest('button[data-unit]'); if(!btn) return;
    // Clear styles
    uomPills.querySelectorAll('button[data-unit]').forEach(b=>{
      b.classList.remove('bg-emerald-100','text-emerald-800','border-emerald-300','ring-1','ring-emerald-300');
    });
    btn.classList.add('bg-emerald-100','text-emerald-800','border-emerald-300','ring-1','ring-emerald-300');
    document.getElementById('uom_id').value = ''; // free-text uoms via pills don’t set id
    setText('pvUnit', btn.getAttribute('data-unit').toUpperCase());
  });

  <?php if (!empty($uoms)): ?>
  const btnUom = document.getElementById('btnUom');
  const uoms = <?= json_encode($uoms) ?>;
  buildSearchable(
    btnUom,
    document.getElementById('uomMenu'),
    document.getElementById('uomSearch'),
    document.getElementById('uomList'),
    uoms,
    u => u.name || '',
    u => {
      document.getElementById('uom_id').value = u.id || '';
      btnUom.textContent = u.name || '— Select Unit —';
      setText('pvUnit', u.name || '');
    }
  );
  <?php endif; ?>

  // ---------------- Product quick pick (optional; won’t block typing)
  const btnPickProduct = document.getElementById('btnPickProduct');
  const productMenu = document.getElementById('productMenu');
  const productSearch = document.getElementById('productSearch');
  const productList = document.getElementById('productList');

  buildSearchable(
    btnPickProduct, productMenu, productSearch, productList, [],
    x => x.name || '',
    x => {
      document.getElementById('p_name').value = x.name || '';
      if (x.unit_price != null) document.getElementById('p_price').value = x.unit_price;
      if (x.category_id) {
        const found = categories.find(c=> String(c.id)===String(x.category_id));
        if(found){ document.getElementById('category_id').value = found.id; document.getElementById('categoryChosen').textContent = found.name; setText('pvCategory', found.code || found.name); }
      }
      if (x.uom_name) setText('pvUnit', x.uom_name);
      mirror();
    }
  );

  // Fetch suggestions when user opens product picker
  btnPickProduct.addEventListener('click', async () => {
    try {
      const res = await fetch(`${BASE}/api/products?q=${encodeURIComponent(document.getElementById('p_name').value||'')}`, { headers:{Accept:'application/json'} });
      const js = res.ok ? await res.json() : {items:[]};
      const items = Array.isArray(js.items) ? js.items : [];
      // re-render list
      productList.innerHTML = '';
      (items||[]).slice(0,50).forEach(it=>{
        const li=document.createElement('li');
        li.className='px-3 py-2 hover:bg-gray-50 cursor-pointer';
        li.innerHTML = `<div class="font-medium">${(it.name||'')}</div>
                        <div class="text-xs text-gray-500">${[it.category_name||it.category_code||'', it.uom_name||'', it.unit_price!=null?('৳'+it.unit_price):''].filter(Boolean).join(' · ')}</div>`;
        li.addEventListener('click', ()=>{
          document.getElementById('p_name').value = it.name || '';
          if (it.unit_price != null) document.getElementById('p_price').value = it.unit_price;
          if (it.category_id) {
            const found = categories.find(c=> String(c.id)===String(it.category_id));
            if(found){ document.getElementById('category_id').value = found.id; document.getElementById('categoryChosen').textContent = found.name; setText('pvCategory', found.code || found.name); }
          }
          if (it.uom_name) setText('pvUnit', it.uom_name);
          productMenu.classList.add('hidden');
          mirror();
        });
        productList.appendChild(li);
      });
      if(!productList.children.length){
        const li=document.createElement('li');
        li.className='px-3 py-2 text-gray-500';
        li.textContent='No products yet — type a new name above.';
        productList.appendChild(li);
      }
    } catch(_) {}
  });

  // ---------------- Barcode scanner (optional)
  const scanBtn = document.getElementById('scanBtn');
  const scanVideo = document.getElementById('scanVideo');
  scanBtn?.addEventListener('click', async ()=>{
    try{
      if (!('BarcodeDetector' in window)) { alert('Barcode scanning not supported in this browser.'); return; }
      const detector = new BarcodeDetector({ formats: ['ean_13','ean_8','code_128','code_39','upc_a','upc_e','qr_code'] });
      const stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode:'environment' } });
      scanVideo.srcObject = stream; scanVideo.classList.remove('hidden'); await scanVideo.play();
      const tick = async ()=>{
        if (scanVideo.readyState !== 4){ requestAnimationFrame(tick); return; }
        try{
          const found = await detector.detect(scanVideo);
          if(found && found.length){
            document.getElementById('p_barcode').value = found[0].rawValue || '';
            stream.getTracks().forEach(t=>t.stop()); scanVideo.pause(); scanVideo.classList.add('hidden'); scanVideo.srcObject=null;
          }
        }catch(_){}
        requestAnimationFrame(tick);
      };
      requestAnimationFrame(tick);
    }catch(_){ alert('Camera not available.'); }
  });

  // ---------------- Bulk CSV parser (no overflow)
  const csvFile   = document.getElementById('csvFile');
  const csvPaste  = document.getElementById('csvPaste');
  const parseBtn  = document.getElementById('parseBtn');
  const bulkWarn  = document.getElementById('bulkWarn');
  const bulkPrev  = document.getElementById('bulkPreviewWrap');
  const bulkTbody = document.getElementById('bulkTbody');
  const bulkJson  = document.getElementById('bulk_json');
  const bulkSubmit= document.getElementById('bulkSubmit');
  const dlTpl     = document.getElementById('dlTpl');

  dlTpl.addEventListener('click', (e)=>{
    const hdr='name,category_id,uom_id,unit_price,barcode,initial_qty,arrival_date,expiry_date,status,supplier_name';
    const row='Laptop,1,1,90000,1234567890123,0,2025-01-10,,active,Acme Supplies';
    const blob = new Blob([hdr+'\n'+row+'\n'], {type:'text/csv'});
    const url = URL.createObjectURL(blob);
    dlTpl.href = url;
  });

  function parseCSV(text){
    const lines = text.replace(/\r\n/g,'\n').replace(/\r/g,'\n').split('\n');
    const rows=[]; for(const line of lines){ if(line.trim()) rows.push(line); }
    return rows.map(l=>{
      const out=[]; let cur=''; let q=false;
      for(let i=0;i<l.length;i++){
        const ch=l[i];
        if(q){ if(ch=='"' && l[i+1]=='"'){ cur+='"'; i++; } else if(ch=='"'){ q=false; } else cur+=ch; }
        else { if(ch==','){ out.push(cur); cur=''; } else if(ch=='"'){ q=true; } else cur+=ch; }
      }
      out.push(cur); return out;
    });
  }
  function toObjects(rows){
    if(!rows.length) return [];
    const hdr = rows[0].map(h=>h.trim().toLowerCase());
    const data=[];
    for(let i=1;i<rows.length;i++){
      const r=rows[i]; if(!r || (r.length===1 && !r[0].trim())) continue;
      const o={}; hdr.forEach((h,idx)=> o[h]=(r[idx]??'').trim() ); data.push(o);
    }
    return data;
  }
  function validateAndPreview(items){
    const out=[]; let hasErr=false;
    bulkTbody.innerHTML='';
    items.forEach((it,ix)=>{
      const o = {
        name: it.name || '',
        category_id: it.category_id || '',
        uom_id: it.uom_id || '',
        unit_price: it.unit_price ? Number(it.unit_price) : '',
        barcode: it.barcode || '',
        initial_qty: it.initial_qty || '',
        arrival_date: it.arrival_date || '',
        expiry_date: it.expiry_date || '',
        status: (it.status||'active').toLowerCase()==='inactive' ? 'inactive' : 'active',
        supplier_name: it.supplier_name || ''
      };
      const tr=document.createElement('tr');
      const errs=[];
      if(!o.name) errs.push('name');
      if(o.expiry_date && o.arrival_date && (new Date(o.expiry_date) < new Date(o.arrival_date))) errs.push('dates');
      tr.innerHTML = `
        <td class="px-3 py-2">${ix+1}</td>
        <td class="px-3 py-2 ${!o.name?'text-rose-600':''}">${o.name||'—'}</td>
        <td class="px-3 py-2">${o.category_id||'—'}</td>
        <td class="px-3 py-2">${o.uom_id||'—'}</td>
        <td class="px-3 py-2">${o.unit_price!==''?Number(o.unit_price).toLocaleString():'—'}</td>
        <td class="px-3 py-2">${o.barcode||'—'}</td>
        <td class="px-3 py-2">${o.initial_qty||'—'}</td>
        <td class="px-3 py-2">${o.arrival_date||'—'}</td>
        <td class="px-3 py-2">${o.expiry_date||'—'}</td>
        <td class="px-3 py-2">${o.status}</td>
        <td class="px-3 py-2">${o.supplier_name||'—'}</td>`;
      if (errs.length) tr.classList.add('bg-rose-50');
      bulkTbody.appendChild(tr);
      out.push(o);
      hasErr = hasErr || errs.length>0;
    });
    bulkWarn.classList.toggle('hidden', !hasErr);
    bulkWarn.textContent = hasErr ? 'Some rows have issues (highlighted). Fix before submitting.' : '';
    bulkPrev.classList.remove('hidden');
    bulkJson.value = JSON.stringify(out);
    bulkSubmit.disabled = out.length===0 || hasErr;
    bulkSubmit.textContent = `Submit (${out.length})`;
  }
  async function handleFile(file){ const text = await file.text(); validateAndPreview(toObjects(parseCSV(text))); }
  csvFile?.addEventListener('change', (e)=>{ const f=e.target.files?.[0]; if(f) handleFile(f); });
  parseBtn?.addEventListener('click', ()=>{
    const txt = csvPaste.value.trim();
    if (!txt) { bulkWarn.classList.remove('hidden'); bulkWarn.textContent='Paste CSV first or choose a file.'; return; }
    validateAndPreview(toObjects(parseCSV(txt)));
  });

  // Init preview defaults
  mirror();
})();
</script>