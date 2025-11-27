<?php
declare(strict_types=1);
/** @var array $product */
/** @var string $module_base */
/** @var array $categories (optional) */
if (!function_exists('h')) { function h($v){ return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8'); } }

$p = $product ?? [];
$unit = $p['unit'] ?? 'pcs';
$unitTypes = ['pcs','box','kg','ltr','meter','set','pack','carton'];

/* Build a light category list for initial datalist */
$catList = [];
if (!empty($categories) && is_array($categories)) {
  foreach ($categories as $c) {
    $code  = trim((string)($c['code'] ?? ($c['sku_prefix'] ?? ($c['category_code'] ?? ''))));
    $name  = trim((string)($c['name'] ?? ''));
    if ($code === '' && $name === '') continue;
    $label = $code ?: strtoupper(substr($name,0,3));
    if ($name !== '' && strtoupper($name) !== strtoupper($code)) $label .= ' — '.$name;
    $catList[] = ['code'=>$code, 'name'=>$name, 'label'=>$label];
  }
}

/* Precompute a category label for initial display */
$catCode  = trim((string)($p['category_code'] ?? ($p['category'] ?? ($p['category_name'] ?? ''))));
$catLabel = $catCode;
if ($catCode !== '' && !empty($categories)) {
  foreach ($catList as $c) {
    if (strtoupper($c['code']) === strtoupper($catCode)) { $catLabel = $c['label']; break; }
  }
}
?>
<div class="max-w-4xl">
  <h2 class="text-xl font-semibold mb-5">Edit Product</h2>

  <form method="post" action="<?= h($module_base) ?>/products/<?= (int)($p['id'] ?? 0) ?>" id="productForm" autocomplete="off" class="space-y-6">
    <?= function_exists('csrf_field') ? csrf_field() : '' ?>
    
    <!-- just inside the <form> -->
<input type="hidden" name="category_id" value="<?= h($p['category_id'] ?? '') ?>">
<input type="hidden" name="unit"        id="unit_type" value="<?= h($unit) ?>">

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
      <!-- Left: form -->
      <div class="lg:col-span-2">
        <div class="rounded-2xl bg-white ring-1 ring-slate-200 p-6">
          <div class="grid grid-cols-1 md:grid-cols-2 gap-6">

            <!-- BARCODE -->
            <div class="md:col-span-2">
              <label class="block text-sm font-medium mb-1">Barcode</label>
              <input type="text" name="barcode" id="p_barcode" class="input" placeholder="Scan or type"
                     value="<?= h($p['barcode'] ?? '') ?>">
              <p class="text-xs text-slate-500 mt-1">If present, we’ll keep it; you can update it here.</p>
            </div>

            <!-- SUPPLIER (typeahead) -->
            <div class="md:col-span-2">
              <label class="block text-sm font-medium mb-1">Supplier</label>
              <input type="hidden" name="supplier_id" id="supplier_id" value="<?= h($p['supplier_id'] ?? '') ?>">
              <div class="relative">
                <input type="text" id="supplier_name" class="input" placeholder="Type to search supplier"
                       value="<?= h($p['supplier_name'] ?? '') ?>">
                <div id="supplier_suggest" class="typeahead"></div>
              </div>
              <p class="text-xs text-slate-500 mt-1">Search your supplier directory.</p>
            </div>

            <!-- DATES -->
            <div>
              <label class="block text-sm font-medium mb-1">Date of Arrival</label>
              <input type="date" name="arrival_date" id="p_arrival" class="input"
                     value="<?= h(substr((string)($p['arrival_date'] ?? ''),0,10)) ?>">
            </div>
            <div>
              <label class="block text-sm font-medium mb-1">Expiry Date</label>
              <input type="date" name="expiry_date" id="p_expiry" class="input"
                     value="<?= h(substr((string)($p['expiry_date'] ?? ''),0,10)) ?>">
            </div>

            <!-- NAME -->
            <div class="md:col-span-2">
              <label class="block text-sm font-medium mb-1">Product Name</label>
              <input type="text" name="name" id="p_name" class="input" required
                     value="<?= h($p['name'] ?? '') ?>">
            </div>

            <!-- CATEGORY (typeahead + code) -->
            <div>
              <label class="block text-sm font-medium mb-1">Category (typeahead)</label>
              <div class="relative">
                <input type="text" id="p_cat_label" list="categoryList" class="input"
                       placeholder="e.g., ICT — Information Tech" value="<?= h($catLabel) ?>">
                <div id="category_suggest" class="typeahead"></div>
              </div>
              <datalist id="categoryList">
                <?php foreach ($catList as $c): ?>
                  <option value="<?= h($c['label']) ?>"></option>
                <?php endforeach; ?>
              </datalist>
              <p class="text-xs text-slate-500 mt-1">Selecting a suggestion will auto-fill the code.</p>
            </div>

            <div>
              <label class="block text-sm font-medium mb-1">Category Code</label>
              <input type="text" name="category_code" id="p_cat" class="input uppercase" maxlength="12"
       value="<?= h($catCode) ?>" placeholder="e.g., ICT">
            </div>

            <!-- UNIT PILLS -->
            <div>
              <label class="block text-sm font-medium mb-2">Unit</label>
              <input type="hidden" name="unit" id="unit_type" value="<?= h($unit) ?>">
              <div id="unitTabs" class="flex flex-wrap gap-2">
                <?php foreach ($unitTypes as $u): ?>
                  <button type="button" data-unit="<?= h($u) ?>"
                          class="unit-pill px-3 py-1.5 rounded-full border text-sm <?= $u===$unit ? 'bg-emerald-100 text-emerald-800 border-emerald-300 ring-1 ring-emerald-300' : 'border-slate-300 text-slate-700' ?>">
                    <?= strtoupper(h($u)) ?>
                  </button>
                <?php endforeach; ?>
              </div>
            </div>

            <!-- PRICE -->
            <div>
              <label class="block text-sm font-medium mb-1">Unit Price</label>
              <div class="relative">
                <input type="number" step="0.01" min="0" name="price" id="p_price" class="input pr-14"
                       value="<?= h((string)($p['price'] ?? '')) ?>" placeholder="90000">
                <div class="absR">BDT</div>
              </div>
            </div>

            <!-- SKU + regenerate + Product Code -->
            <div class="md:col-span-2 grid grid-cols-1 md:grid-cols-3 gap-4">
              <div class="md:col-span-2">
                <label class="block text-sm font-medium mb-1">SKU</label>
                <div class="flex">
                  <input type="text" name="sku" id="p_sku" class="input flex-1"
                         value="<?= h($p['sku'] ?? '') ?>" placeholder="SKU-LAP-ICT-90000">
                  <button type="button" id="btn_regen_sku"
                          class="ml-2 px-3 py-2 rounded-lg border border-slate-300 hover:bg-slate-50"
                          title="Regenerate SKU">
                    <i class="fa-solid fa-wand-magic-sparkles"></i>
                  </button>
                </div>
                <p class="text-xs text-slate-500 mt-1">
                  SKU auto-updates from Name/Category/Price until you type here manually.
                </p>
              </div>
              <div>
                <label class="block text-sm font-medium mb-1">Product Code</label>
                <input type="text" name="product_code" id="p_code" class="input"
                       value="<?= h($p['product_code'] ?? ($p['product_id'] ?? '')) ?>" placeholder="auto or custom">
              </div>
            </div>

            <!-- SPEC -->
            <div class="md:col-span-2">
              <label class="block text-sm font-medium mb-1">Specification</label>
              <textarea name="spec" id="p_spec" rows="4" class="input" placeholder="Short details…"><?= h($p['spec'] ?? '') ?></textarea>
            </div>

            <!-- STATUS -->
            <div>
              <label class="block text-sm font-medium mb-1">Status</label>
              <?php $status = strtolower((string)($p['status'] ?? 'active'))==='inactive'?'inactive':'active'; ?>
              <select name="status" class="input">
                <option value="active"   <?= $status==='active'?'selected':'' ?>>Active</option>
                <option value="inactive" <?= $status==='inactive'?'selected':'' ?>>Inactive</option>
              </select>
            </div>

          </div>
        </div>
      </div>

      <!-- Right: preview -->
      <div>
        <div class="rounded-2xl bg-white ring-1 ring-slate-200 p-6">
          <h3 class="font-semibold mb-2">Preview</h3>
          <div class="space-y-1 text-sm">
            <div class="flex justify-between"><span>Supplier</span> <span id="pvSupplier" class="text-slate-700"><?= h($p['supplier_name'] ?? '—') ?></span></div>
            <div class="flex justify-between"><span>SKU</span> <span id="pvSku" class="font-mono text-slate-700"><?= h($p['sku'] ?? '—') ?></span></div>
            <div class="flex justify-between"><span>Product Code</span> <span id="pvCode" class="font-mono text-slate-700"><?= h($p['product_code'] ?? ($p['product_id'] ?? '—')) ?></span></div>
            <div class="flex justify-between"><span>Unit</span> <span id="pvUnit" class="text-slate-700 uppercase"><?= h($unit) ?></span></div>
            <div class="flex justify-between"><span>Price</span> <span id="pvPrice" class="text-slate-700"><?= isset($p['price']) ? '৳ '.number_format((float)$p['price'],2) : '—' ?></span></div>
            <div class="flex justify-between"><span>Arrival</span> <span id="pvArr" class="text-slate-700"><?= h(substr((string)($p['arrival_date'] ?? ''),0,10) ?: '—') ?></span></div>
            <div class="flex justify-between"><span>Expiry</span> <span id="pvExp" class="text-slate-700"><?= h(substr((string)($p['expiry_date'] ?? ''),0,10) ?: '—') ?></span></div>
          </div>
        </div>
      </div>
    </div>

    <div class="flex items-center justify-end gap-2">
      <a href="<?= h($module_base) ?>/products" class="btn">Cancel</a>
      <button class="btn-primary">Update</button>
    </div>
  </form>
</div>

<style>
  html, body, .input, .btn, .btn-primary, select, textarea { font-family: 'Inter', ui-sans-serif, system-ui, -apple-system, Segoe UI, Roboto, Arial, "Noto Sans", "Liberation Sans", sans-serif; }
  .input{width:100%;padding:.6rem .75rem;border:1px solid #d1d5db;border-radius:.75rem}
  .btn{padding:.6rem .9rem;border:1px solid #d1d5db;border-radius:.75rem;background:#fff}
  .btn-primary{padding:.6rem 1rem;border-radius:.75rem;background:#2563eb;color:#fff;border:none}
  .typeahead{position:absolute;z-index:20;background:#fff;border:1px solid #e5e7eb;border-radius:.6rem;display:none;max-height:220px;overflow:auto;width:100%;top:100%;left:0;margin-top:.25rem;box-shadow:0 10px 30px rgba(0,0,0,.06)}
  .typeahead a{display:block;padding:.55rem .7rem;text-decoration:none;color:#111}
  .typeahead a:hover{background:#f3f4f6}
  .absR{position:absolute;right:.6rem;top:0;bottom:0;display:flex;align-items:center;color:#9ca3af;font-size:.8rem}
  .unit-pill{transition:all .12s}
</style>

<script>
(() => {
  if (window.__productEditBound) return; // avoid double-binding after save/nav
  window.__productEditBound = true;

  const BASE = <?= json_encode($module_base) ?>;

  const $ = s => document.querySelector(s);
  const setText = (el,val)=>{ if(el) el.textContent = val ?? '—'; };

  const nameEl   = $('#p_name');
  const priceEl  = $('#p_price');
  const catEl    = $('#p_cat');
  const catLabel = $('#p_cat_label');
  const skuEl    = $('#p_sku');
  const codeEl   = $('#p_code');
  const arrEl    = $('#p_arrival');
  const expEl    = $('#p_expiry');
  const unitHidden = $('#unit_type');
  const unitPills  = document.querySelectorAll('#unitTabs .unit-pill');

  const pvSupplier = $('#pvSupplier');
  const pvSku   = $('#pvSku');
  const pvCode  = $('#pvCode');
  const pvPrice = $('#pvPrice');
  const pvArr   = $('#pvArr');
  const pvExp   = $('#pvExp');
  const pvUnit  = $('#pvUnit');

  /* ---------- Unit pills ---------- */
  function selectUnit(btn){
    unitPills.forEach(b=>{
      b.classList.remove('bg-emerald-100','text-emerald-800','border-emerald-300','ring-1','ring-emerald-300');
      b.classList.add('border-slate-300','text-slate-700');
    });
    btn.classList.add('bg-emerald-100','text-emerald-800','border-emerald-300','ring-1','ring-emerald-300');
    unitHidden.value = btn.dataset.unit;
    setText(pvUnit, btn.dataset.unit.toUpperCase());
  }
  unitPills.forEach(btn=>{
    btn.addEventListener('click', ()=>selectUnit(btn), { once:false });
    btn.addEventListener('keydown', e=>{ if(e.key===' '||e.key==='Enter'){ e.preventDefault(); selectUnit(btn);} });
  });

  /* ---------- Preview sync ---------- */
  function syncDates(){
    setText(pvArr, arrEl?.value || '—');
    setText(pvExp, expEl?.value || '—');
    if (arrEl?.value && expEl?.value && new Date(expEl.value) < new Date(arrEl.value)) {
      expEl.setCustomValidity('Expiry cannot be earlier than arrival.');
    } else expEl?.setCustomValidity('');
  }
  if (arrEl) ['input','change'].forEach(ev=>arrEl.addEventListener(ev, syncDates));
  if (expEl) ['input','change'].forEach(ev=>expEl.addEventListener(ev, syncDates));

  priceEl?.addEventListener('input',()=> setText(pvPrice, priceEl.value ? '৳ ' + Number(priceEl.value).toLocaleString() : '—'));
  codeEl ?.addEventListener('input', ()=> setText(pvCode, codeEl.value || '—'));

  /* =========================================================
     Lightweight Typeahead helper with AbortController
     ========================================================= */
  function makeTypeahead({input, box, fetchItems, onPick, minLen=2, debounceMs=250, maxRows=12}) {
    let timer=null, ac=null;

    function hide(){ box.style.display='none'; box.innerHTML=''; }
    function show(html){ box.innerHTML = html; box.style.display='block'; }

    input.addEventListener('input', () => {
      const q = input.value.trim();
      if (timer) clearTimeout(timer);
      if (q.length < minLen) { if (ac) ac.abort(); hide(); return; }
      timer = setTimeout(async ()=>{
        if (ac) ac.abort();
        ac = new AbortController();
        try {
          const items = await fetchItems(q, ac.signal);
          if (!Array.isArray(items) || items.length===0) { hide(); return; }
          const rows = items.slice(0, maxRows).map(item => onPick.renderRow(item)).join('');
          show(rows);
        } catch (e) {
          // ignore AbortError or network hiccup
        }
      }, debounceMs);
    });

    box.addEventListener('click', e=>{
      const a = e.target.closest('a[data-val]');
      if(!a) return;
      e.preventDefault();
      onPick.select(a.dataset);
      hide();
    });

    input.addEventListener('keydown', e=>{
      if (e.key === 'Escape') hide();
    });

    document.addEventListener('click', e=>{
      if(!box.contains(e.target) && e.target!==input) hide();
    });

    return { hide };
  }

  /* =========================================================
     Supplier Typeahead (single primary endpoint + fallback)
     ========================================================= */
  const sInput = $('#supplier_name');
  const sBox   = $('#supplier_suggest');
  const sId    = $('#supplier_id');

  async function fetchSuppliers(q, signal){
    // primary
    let urls = [`${BASE}/purchases.suppliers.lookup.json?q=${encodeURIComponent(q)}`];
    // fallback only if needed
    try {
      let res = await fetch(urls[0], { headers:{'Accept':'application/json'}, signal });
      if (!res.ok) throw new Error('bad');
      let data = await res.json();
      let items = Array.isArray(data?.items) ? data.items : (Array.isArray(data) ? data : []);
      if (items.length) return items;
    } catch(_) {
      // fallback
      try {
        const res2 = await fetch(`${BASE}/api/suppliers?q=${encodeURIComponent(q)}`, { headers:{'Accept':'application/json'}, signal });
        if (!res2.ok) return [];
        const data2 = await res2.json();
        return Array.isArray(data2?.items) ? data2.items : (Array.isArray(data2) ? data2 : []);
      } catch(e){ return []; }
    }
    return [];
  }

  if (sInput && sBox && sId) {
    makeTypeahead({
      input: sInput,
      box: sBox,
      fetchItems: fetchSuppliers,
      onPick: {
        renderRow: r => {
          const sub = [r.code?('#'+r.code):'', r.phone||'', r.email||''].filter(Boolean).join(' · ');
          return `<a href="#" data-val="${String(r.id||'')}" data-name="${String(r.name||'')}">
                    <div class="font-medium">${String(r.name||'')}</div>
                    ${sub?`<div class="text-xs text-slate-500">${String(sub)}</div>`:''}
                  </a>`;
        },
        select: d => {
          sId.value = d.val || '';
          sInput.value = d.name || '';
          setText(pvSupplier, sInput.value || '—');
        }
      }
    });
  }

  /* =========================================================
     Category Typeahead → fills #p_cat (code)
     ========================================================= */
  const cInput = $('#p_cat_label');
  const cBox   = $('#category_suggest');

  function catLocalLabelToCode(label){
    if (!label) return '';
    const up = label.toUpperCase();
    const CAT_DATA = <?= json_encode($catList, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) ?>;
    for (const c of CAT_DATA) {
      if ((c.label||'').toUpperCase() === up) return (c.code||'').toUpperCase();
    }
    const beforeDash = up.split('—')[0].trim();
    if (/^[A-Z0-9]{2,6}$/.test(beforeDash)) return beforeDash;
    return up.replace(/[^A-Z0-9]/g,'').slice(0,3);
  }
  function syncCategoryFromLabel(){
    const code = catLocalLabelToCode(cInput.value.trim());
    if (code) { catEl.value = code; maybeAutoSku(); }
  }
  cInput?.addEventListener('input', syncCategoryFromLabel);
  cInput?.addEventListener('change', syncCategoryFromLabel);

  async function fetchCategories(q, signal){
    try{
      const res = await fetch(`${BASE}/api/categories?q=${encodeURIComponent(q)}`, {headers:{'Accept':'application/json'}, signal});
      if (!res.ok) return [];
      const data = await res.json();
      const items = Array.isArray(data?.items) ? data.items : (Array.isArray(data) ? data : []);
      return items.map(r=>{
        const label = r.code ? (r.name && r.name.toUpperCase()!==r.code ? `${r.code} — ${r.name}` : r.code) : (r.name||'');
        return { code: r.code || '', label };
      });
    }catch(_){ return []; }
  }

  if (cInput && cBox) {
    makeTypeahead({
      input: cInput,
      box: cBox,
      fetchItems: fetchCategories,
      onPick: {
        renderRow: r => `<a href="#" data-val="${String(r.code||'')}" data-label="${String(r.label||'')}">
                           <div class="font-medium">${String(r.label||'')}</div>
                         </a>`,
        select: d => { cInput.value = d.label || ''; syncCategoryFromLabel(); }
      }
    });
  }

  /* =========================================================
     Smart auto-SKU (locks if user edits manually)
     ========================================================= */
  let skuLockedByUser = false;

  function slug3(s){
    const x = (s||'').toUpperCase().replace(/[^A-Z0-9]+/g,'');
    return x.slice(0,3) || 'GEN';
  }
  function priceBlock(){
    const v = parseFloat(priceEl.value||'0');
    if (!isFinite(v) || v<=0) return '';
    if (v >= 1_000_000) return Math.round(v/1_000_000)+'M';
    if (v >=   1_000)   return Math.round(v/1_000)+'K';
    return String(Math.round(v));
  }
  function generateSKU(){
    const n = slug3(nameEl?.value);
    const c = (catEl?.value||'').toUpperCase() || slug3(cInput?.value);
    const p = priceBlock();
    const d = new Date();
    const ym = String(d.getFullYear()).slice(2) + String(d.getMonth()+1).padStart(2,'0');
    return ['SKU', n, c, ym, p].filter(Boolean).join('-');
  }
  function maybeAutoSku(){
    if (skuLockedByUser) return;
    const next = generateSKU();
    if (skuEl) skuEl.value = next;
    setText(pvSku, next || '—');
  }

  // Initial fill if empty
  if (skuEl && !skuEl.value.trim()) { maybeAutoSku(); }

  // Inputs that influence SKU
  ['input','change'].forEach(ev=>{
    nameEl   && nameEl.addEventListener(ev, maybeAutoSku);
    priceEl  && priceEl.addEventListener(ev, maybeAutoSku);
    catEl    && catEl.addEventListener(ev, maybeAutoSku);
    cInput   && cInput.addEventListener(ev, maybeAutoSku);
  });

  // User typed in SKU → lock
  skuEl?.addEventListener('input', ()=> {
    skuLockedByUser = true;
    setText(pvSku, skuEl.value || '—');
  });

  // Wand button → re-sync & unlock
  const btnRegen = document.getElementById('btn_regen_sku');
  btnRegen?.addEventListener('click', ()=>{
    skuLockedByUser = false;
    maybeAutoSku();
  });

  // Initialize previews once
  setText(pvSku,  skuEl?.value || '—');
  setText(pvCode, codeEl?.value || '—');
  syncDates();
})();
</script>