<?php
declare(strict_types=1);
/** DMS · Products → Index
 * expects from controller:
 * - $rows        array of rows [{id, sku, name, supplier_name, category_name, price, stock_qty, status}]
 * - $q           optional initial search term
 * - $org, $module_base
 */
$org         = $org ?? [];
$module_base = $module_base ?? ('/t/'.rawurlencode($org['slug'] ?? '').'/apps/dms');
$h = fn($s)=>htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
$q = (string)($q ?? '');
?>
<!-- brand edge -->
<div class="h-1 w-full bg-emerald-600 dark:bg-emerald-500 -mt-4 mb-4"></div>

<div class="max-w-7xl mx-auto px-4 py-6 space-y-4">
  <div class="flex items-center justify-between gap-3 flex-wrap">
    <h1 class="text-2xl font-semibold text-emerald-700 dark:text-emerald-300">Products</h1>
    <div class="flex items-center gap-2">
      <button id="btnExport"
              class="px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-900 hover:bg-gray-50 dark:hover:bg-gray-800 text-sm">
        Export CSV
      </button>
      <a href="<?= $h($module_base.'/products/create') ?>"
         class="px-3 py-2 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white text-sm">Add Product</a>
    </div>
  </div>

  <!-- FILTER BAR -->
  <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 p-3">
    <div class="flex flex-col md:flex-row md:items-end gap-3">
      <!-- search -->
      <div class="flex-1">
        <label class="block text-xs font-medium mb-1 text-gray-600 dark:text-gray-300">Search</label>
        <div class="relative">
          <input id="q" type="text" value="<?= $h($q) ?>" placeholder="Search name / code / barcode…"
                 class="w-full rounded-lg border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-950 px-3 py-2 pr-10">
          <span class="absolute right-2 top-1/2 -translate-y-1/2 text-gray-400">⌘K</span>
        </div>
      </div>

      <!-- status -->
      <div>
        <label class="block text-xs font-medium mb-1 text-gray-600 dark:text-gray-300">Status</label>
        <select id="fStatus" class="rounded-lg border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-950 px-3 py-2">
          <option value="">All</option>
          <option value="active">Active</option>
          <option value="inactive">Inactive</option>
        </select>
      </div>

      <!-- stock -->
      <div>
        <label class="block text-xs font-medium mb-1 text-gray-600 dark:text-gray-300">Stock</label>
        <select id="fStock" class="rounded-lg border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-950 px-3 py-2">
          <option value="">All</option>
          <option value="in">In stock (&gt; 0)</option>
          <option value="out">Out of stock (= 0)</option>
        </select>
      </div>

      <!-- category -->
      <div class="min-w-[12rem]">
        <label class="block text-xs font-medium mb-1 text-gray-600 dark:text-gray-300">Category</label>
        <select id="fCategory" class="rounded-lg border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-950 px-3 py-2">
          <option value="">All</option>
        </select>
      </div>

      <!-- supplier -->
      <div class="min-w-[12rem]">
        <label class="block text-xs font-medium mb-1 text-gray-600 dark:text-gray-300">Supplier</label>
        <select id="fSupplier" class="rounded-lg border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-950 px-3 py-2">
          <option value="">All</option>
        </select>
      </div>

      <div class="flex gap-2">
        <button id="btnClear" type="button"
                class="px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-900 text-sm">
          Clear
        </button>
      </div>
    </div>
  </div>

  <!-- TABLE -->
  <div class="rounded-xl border border-gray-200 dark:border-gray-700 overflow-hidden">
    <div class="overflow-auto">
      <table id="grid" class="w-full text-sm">
        <thead class="bg-gray-50 dark:bg-gray-900 sticky top-0 z-10">
          <tr>
            <?php
              // helper to render sortable th
              $th = function(string $key, string $label, string $align='left') {
                $arrow = '<span class="ml-1 text-gray-400">↕</span>';
                echo '<th data-key="'.$key.'" class="sort px-3 py-2 text-'.$align.' cursor-pointer select-none">'.$label.$arrow.'</th>';
              };
            ?>
            <?php $th('id','#'); ?>
            <?php $th('sku','Code'); ?>
            <?php $th('name','Name'); ?>
            <?php $th('supplier_name','Supplier'); ?>
            <?php $th('category_name','Category'); ?>
            <?php $th('price','Price (BDT)','right'); ?>
            <?php $th('stock_qty','Stock','right'); ?>
            <?php $th('status','Status'); ?>
            <th class="px-3 py-2 text-right">Actions</th>
          </tr>
        </thead>
        <tbody id="tbody" class="divide-y divide-gray-200 dark:divide-gray-800 bg-white dark:bg-gray-950">
          <!-- rows rendered by JS for live filtering/sorting -->
        </tbody>
      </table>
    </div>

    <div id="emptyState" class="hidden py-10 text-center text-gray-500">
      No products match your filters.
    </div>
  </div>
</div>

<script>
(() => {
  const moduleBase = <?= json_encode($module_base) ?>;

  // ---------- Data (embed from php) ----------
  const RAW = <?= json_encode($rows ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;

  // Build distinct options for Category / Supplier
  const catSet = new Set(), supSet = new Set();
  RAW.forEach(r => {
    if (r.category_name) catSet.add(r.category_name);
    if (r.supplier_name) supSet.add(r.supplier_name);
  });
  const fCategory = document.getElementById('fCategory');
  const fSupplier = document.getElementById('fSupplier');
  [...catSet].sort((a,b)=>a.localeCompare(b)).forEach(v=>{
    const o=document.createElement('option'); o.value=v; o.textContent=v; fCategory.appendChild(o);
  });
  [...supSet].sort((a,b)=>a.localeCompare(b)).forEach(v=>{
    const o=document.createElement('option'); o.value=v; o.textContent=v; fSupplier.appendChild(o);
  });

  // ---------- State ----------
  const state = {
    q: document.getElementById('q')?.value?.trim() ?? '',
    status: '',
    stock: '',
    category: '',
    supplier: '',
    sortKey: 'id',
    sortDir: 'desc' // desc by default for id
  };

  // ---------- Helpers ----------
  const tbody = document.getElementById('tbody');
  const empty = document.getElementById('emptyState');

  function fmtNum(n, d=2){ return Number(n||0).toLocaleString(undefined,{minimumFractionDigits:d, maximumFractionDigits:d}); }
  function includesFold(hay, needle){ return (hay||'').toLowerCase().includes((needle||'').toLowerCase()); }

  function applyFilters(){
    let out = RAW.slice();

    // text search: name / code / barcode
    if(state.q){
      out = out.filter(r =>
        includesFold(r.name, state.q) ||
        includesFold(r.sku, state.q)  ||
        includesFold(r.barcode, state.q)
      );
    }

    // status
    if(state.status){
      out = out.filter(r => (r.status||'').toLowerCase() === state.status);
    }

    // stock
    if(state.stock === 'in')    out = out.filter(r => Number(r.stock_qty||0) > 0);
    if(state.stock === 'out')   out = out.filter(r => Number(r.stock_qty||0) === 0);

    // category / supplier
    if(state.category) out = out.filter(r => (r.category_name||'') === state.category);
    if(state.supplier) out = out.filter(r => (r.supplier_name||'') === state.supplier);

    // sort
    const k = state.sortKey;
    const dir = state.sortDir === 'desc' ? -1 : 1;
    out.sort((a,b)=>{
      let A = a[k], B = b[k];
      const num = (x)=> (x===''||x===null||x===undefined)?0:Number(x);
      if(k==='price'||k==='stock_qty'||k==='id'){ A=num(A); B=num(B); }
      else { A=(A||'').toString().toLowerCase(); B=(B||'').toString().toLowerCase(); }
      return A < B ? -1*dir : A > B ? 1*dir : 0;
    });

    render(out);
    return out;
  }

  function render(rows){
    tbody.innerHTML = '';
    if(!rows.length){ empty.classList.remove('hidden'); return; }
    empty.classList.add('hidden');

    const frag=document.createDocumentFragment();
    rows.forEach(r=>{
      const tr=document.createElement('tr');
      tr.className='hover:bg-gray-50 dark:hover:bg-gray-900/40';
      tr.innerHTML = `
        <td class="px-3 py-2">${r.id}</td>
        <td class="px-3 py-2">${escapeHtml(r.sku||'')}</td>
        <td class="px-3 py-2">${escapeHtml(r.name||'')}</td>
        <td class="px-3 py-2">${escapeHtml(r.supplier_name||'')}</td>
        <td class="px-3 py-2">${escapeHtml(r.category_name||'')}</td>
        <td class="px-3 py-2 text-right">${fmtNum(r.price,2)}</td>
        <td class="px-3 py-2 text-right">${fmtNum(r.stock_qty,2)}</td>
        <td class="px-3 py-2">
          <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs
            ${ (r.status||'').toLowerCase()==='active'
                ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-900/20 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800'
                : 'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-300 border border-gray-200 dark:border-gray-700' }">
            ${escapeHtml(r.status||'')}
          </span>
        </td>
        <td class="px-3 py-2 text-right whitespace-nowrap">
          <a class="text-emerald-700 dark:text-emerald-300 underline" href="${moduleBase}/products/${r.id}">view</a>
          <span class="text-gray-400">·</span>
          <a class="text-emerald-700 dark:text-emerald-300 underline" href="${moduleBase}/products/${r.id}/edit">edit</a>
          <span class="text-gray-400">·</span>
          <a class="text-emerald-700 dark:text-emerald-300 underline" href="${moduleBase}/products/${r.id}/tiers">tiers</a>
        </td>`;
      frag.appendChild(tr);
    });
    tbody.appendChild(frag);
  }

  function escapeHtml(s){
    return (s??'').toString()
      .replaceAll('&','&amp;').replaceAll('<','&lt;')
      .replaceAll('>','&gt;').replaceAll('"','&quot;').replaceAll("'","&#39;");
  }

  // ---------- Events ----------
  // sort toggles
  document.querySelectorAll('th.sort').forEach(th=>{
    th.addEventListener('click', ()=>{
      const k = th.getAttribute('data-key');
      if(state.sortKey === k){ state.sortDir = (state.sortDir === 'asc') ? 'desc' : 'asc'; }
      else { state.sortKey = k; state.sortDir='asc'; }
      applyFilters();
    });
  });

  // filters
  const fStatus = document.getElementById('fStatus');
  const fStock  = document.getElementById('fStock');
  fStatus.addEventListener('change', ()=>{ state.status = fStatus.value; applyFilters(); });
  fStock .addEventListener('change', ()=>{ state.stock  = fStock.value; applyFilters(); });
  fCategory.addEventListener('change', ()=>{ state.category = fCategory.value; applyFilters(); });
  fSupplier.addEventListener('change', ()=>{ state.supplier = fSupplier.value; applyFilters(); });

  // search (debounced)
  const qEl = document.getElementById('q');
  let t=null;
  function setQ(){
    state.q = qEl.value.trim();
    applyFilters();
  }
  qEl.addEventListener('input', ()=>{ clearTimeout(t); t=setTimeout(setQ, 160); });
  // cmd/ctrl+k to focus
  window.addEventListener('keydown', (e)=>{
    if((e.metaKey||e.ctrlKey) && e.key.toLowerCase()==='k'){ e.preventDefault(); qEl.focus(); qEl.select(); }
  });

  // clear
  document.getElementById('btnClear')?.addEventListener('click', ()=>{
    qEl.value=''; fStatus.value=''; fStock.value='';
    fCategory.value=''; fSupplier.value='';
    state.q=''; state.status=''; state.stock=''; state.category=''; state.supplier='';
    state.sortKey='id'; state.sortDir='desc';
    applyFilters();
  });

  // CSV export (current filtered+sorted rows)
  document.getElementById('btnExport')?.addEventListener('click', ()=>{
    const rows = applyFilters();
    const hdr = ['id','code','name','supplier','category','price_bdt','stock','status'];
    const csv = [hdr.join(',')].concat(rows.map(r=>[
      r.id,
      safeCsv(r.sku),
      safeCsv(r.name),
      safeCsv(r.supplier_name),
      safeCsv(r.category_name),
      String(r.price ?? 0),
      String(r.stock_qty ?? 0),
      safeCsv(r.status)
    ].join(','))).join('\n');

    const blob=new Blob([csv],{type:'text/csv;charset=utf-8;'});
    const a=document.createElement('a');
    a.href=URL.createObjectURL(blob);
    a.download='products.csv';
    document.body.appendChild(a); a.click(); a.remove();
    setTimeout(()=>URL.revokeObjectURL(a.href), 2000);
  });
  function safeCsv(s){
    s = (s??'').toString().replaceAll('"','""');
    return /[",\n]/.test(s) ? `"${s}"` : s;
  }

  // Initial render
  applyFilters();
})();
</script>