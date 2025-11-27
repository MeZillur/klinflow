<?php
declare(strict_types=1);
/** DMS · Products → Tiers (HTML page) */
$org         = $org ?? [];
$module_base = $module_base ?? ('/t/'.rawurlencode($org['slug'] ?? '').'/apps/dms');
$h = fn($s)=>htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
$p = $product ?? ['id'=>0,'name_canonical'=>'Product'];
?>
<div class="h-1 w-full bg-emerald-600 dark:bg-emerald-500 -mt-4 mb-4"></div>
<div class="max-w-6xl mx-auto px-4 py-6">
  <div class="flex items-center justify-between mb-4">
    <h1 class="text-2xl font-semibold text-emerald-700 dark:text-emerald-300">
      Price Tiers · <?= $h((string)$p['name_canonical']) ?>
    </h1>
    <div class="flex gap-2">
      <a href="<?= $h($module_base.'/products/'.$p['id']) ?>" class="px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-700">Back</a>
    </div>
  </div>

  <!-- Create Tier -->
  <section class="rounded-xl bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 p-6 mb-6">
    <h3 class="font-semibold text-emerald-700 dark:text-emerald-300 mb-2">Add a tier</h3>
    <form id="tierForm" class="grid md:grid-cols-4 gap-3" method="post" action="<?= $h($module_base.'/products/'.$p['id'].'/tiers') ?>">
      <?= function_exists('csrf_field') ? csrf_field() : '' ?>
      <input type="hidden" name="channel" value="default">
      <input type="hidden" name="customer_segment" value="default">

      <div>
        <label class="block text-sm font-medium mb-1">Effective From</label>
        <input name="effective_from" type="datetime-local" class="w-full rounded-lg border border-gray-300 dark:border-gray-700 px-3 py-2">
      </div>
      <div>
        <label class="block text-sm font-medium mb-1">Base Price (BDT)</label>
        <input name="base_price" type="number" min="0" step="0.01" class="w-full rounded-lg border border-gray-300 dark:border-gray-700 px-3 py-2" required>
      </div>
      <div>
        <label class="block text-sm font-medium mb-1">Min Qty</label>
        <input name="min_qty" type="number" min="1" step="1" value="1" class="w-full rounded-lg border border-gray-300 dark:border-gray-700 px-3 py-2" required>
      </div>
      <div>
        <label class="block text-sm font-medium mb-1">Max Qty (optional)</label>
        <input name="max_qty" type="number" min="1" step="1" class="w-full rounded-lg border border-gray-300 dark:border-gray-700 px-3 py-2">
      </div>

      <div>
        <label class="block text-sm font-medium mb-1">Discount %</label>
        <input name="discount_pct" type="number" min="0" step="0.01" class="w-full rounded-lg border">
      </div>
      <div>
        <label class="block text-sm font-medium mb-1">Discount Abs</label>
        <input name="discount_abs" type="number" min="0" step="0.01" class="w-full rounded-lg border">
      </div>
      <div>
        <label class="block text-sm font-medium mb-1">Commission %</label>
        <input name="commission_pct" type="number" min="0" step="0.01" class="w-full rounded-lg border">
      </div>
      <div>
        <label class="block text-sm font-medium mb-1">Commission Abs</label>
        <input name="commission_abs" type="number" min="0" step="0.01" class="w-full rounded-lg border">
      </div>

      <div>
        <label class="block text-sm font-medium mb-1">Priority</label>
        <input name="priority" type="number" step="1" value="10" class="w-full rounded-lg border">
      </div>
      <div>
        <label class="block text-sm font-medium mb-1">Tax Included?</label>
        <select name="tax_included" class="w-full rounded-lg border">
          <option value="0" selected>No</option>
          <option value="1">Yes</option>
        </select>
      </div>

      <div class="md:col-span-4 mt-1">
        <button type="submit" class="px-4 py-2 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white">Create Draft</button>
      </div>
    </form>
  </section>

  <!-- Tiers table -->
  <section class="rounded-xl bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 p-6">
    <div class="flex items-center justify-between mb-3">
      <h3 class="font-semibold text-emerald-700 dark:text-emerald-300">Existing Tiers</h3>
      <div class="flex gap-2 text-sm">
        <button id="refreshBtn" class="px-3 py-1.5 rounded-lg border border-gray-300 dark:border-gray-700">Refresh</button>
        <a id="quoteBtn" href="#" class="px-3 py-1.5 rounded-lg bg-gray-100 dark:bg-gray-900">Quote (qty=1)</a>
      </div>
    </div>
    <div class="rounded-lg border border-gray-200 dark:border-gray-700 overflow-auto">
      <table class="w-full text-sm" id="tiersTable">
        <thead class="bg-gray-50 dark:bg-gray-900">
          <tr>
            <th class="text-left px-3 py-2">ID</th>
            <th class="text-left px-3 py-2">State</th>
            <th class="text-left px-3 py-2">From → To</th>
            <th class="text-left px-3 py-2">Qty</th>
            <th class="text-right px-3 py-2">Base</th>
            <th class="text-right px-3 py-2">Discount</th>
            <th class="text-right px-3 py-2">Commission</th>
            <th class="text-right px-3 py-2">Priority</th>
            <th class="text-right px-3 py-2">Actions</th>
          </tr>
        </thead>
        <tbody id="tiersBody" class="divide-y divide-gray-200 dark:divide-gray-800"></tbody>
      </table>
    </div>
  </section>
</div>

<script>
(() => {
  const productId = <?= (int)($p['id'] ?? 0) ?>;
  const base = <?= json_encode($module_base, JSON_UNESCAPED_SLASHES) ?>;

  const bodyEl = document.getElementById('tiersBody');
  const refreshBtn = document.getElementById('refreshBtn');
  const quoteBtn = document.getElementById('quoteBtn');

  async function fetchTiers(){
    bodyEl.innerHTML = '<tr><td colspan="9" class="px-3 py-4 text-center text-gray-500">Loading…</td></tr>';
    const r = await fetch(`${base}/products/${productId}/tiers.json`, {headers:{'Accept':'application/json'}});
    const j = await r.json();
    if(!j.ok){ bodyEl.innerHTML = '<tr><td colspan="9" class="px-3 py-4 text-center text-rose-600">Failed to load tiers</td></tr>'; return; }
    const tiers = j.tiers || [];
    if(!tiers.length){ bodyEl.innerHTML = '<tr><td colspan="9" class="px-3 py-4 text-center text-gray-500">No tiers yet.</td></tr>'; return; }
    bodyEl.innerHTML = '';
    for(const t of tiers){
      const tr = document.createElement('tr');
      const disc = (t.discount_pct!=null?`${t.discount_pct}%`:'')+(t.discount_abs!=null?` / ${Number(t.discount_abs).toFixed(2)}`:'');
      const comm = (t.commission_pct!=null?`${t.commission_pct}%`:'')+(t.commission_abs!=null?` / ${Number(t.commission_abs).toFixed(2)}`:'');
      tr.innerHTML = `
        <td class="px-3 py-2">${t.id}</td>
        <td class="px-3 py-2">${t.state}</td>
        <td class="px-3 py-2">${t.effective_from||''}${t.effective_to?` → ${t.effective_to}`:''}</td>
        <td class="px-3 py-2">${t.min_qty}${t.max_qty?`–${t.max_qty}`:''}</td>
        <td class="px-3 py-2 text-right">${Number(t.base_price).toFixed(2)}</td>
        <td class="px-3 py-2 text-right">${disc||'—'}</td>
        <td class="px-3 py-2 text-right">${comm||'—'}</td>
        <td class="px-3 py-2 text-right">${t.priority}</td>
        <td class="px-3 py-2 text-right space-x-2">
          ${t.state==='published'
            ? `<form class="inline" method="post" action="${base}/tiers/${t.id}/retire"><?= function_exists('csrf_field') ? str_replace("\n", "", csrf_field()) : '' ?><button class="underline text-amber-700" onclick="return confirm('Retire tier ${t.id}?')">retire</button></form>`
            : `<form class="inline" method="post" action="${base}/tiers/${t.id}/publish"><?= function_exists('csrf_field') ? str_replace("\n", "", csrf_field()) : '' ?><button class="underline text-emerald-700" onclick="return confirm('Publish tier ${t.id}? (auto-bounds overlaps)')">publish</button></form>`
          }
          <form class="inline" method="post" action="${base}/tiers/${t.id}/delete"><?= function_exists('csrf_field') ? str_replace("\n", "", csrf_field()) : '' ?><button class="underline text-rose-700" onclick="return confirm('Delete tier ${t.id}?')">delete</button></form>
        </td>`;
      bodyEl.appendChild(tr);
    }
  }

  refreshBtn?.addEventListener('click', (e)=>{ e.preventDefault(); fetchTiers(); });
  quoteBtn?.addEventListener('click', async (e)=>{
    e.preventDefault();
    const r = await fetch(`${base}/products/${productId}/quote.json?qty=1`, {headers:{'Accept':'application/json'}});
    const j = await r.json();
    if(j.ok && j.quote){ alert(`Current price: ${j.quote.final_price} (tier ${j.quote.source_tier_id})`); }
    else { alert('No active tier for qty=1 right now.'); }
  });

  // enhance: AJAX submit for draft creation (non-blocking). Still works without JS via normal POST.
  document.getElementById('tierForm')?.addEventListener('submit', async (e)=>{
    e.preventDefault();
    const fd = new FormData(e.target);
    const r = await fetch(e.target.action, {method:'POST', body: fd});
    const j = await r.json().catch(()=>null);
    if (j && j.ok) { alert(`Draft tier created: ${j.tier_id}`); e.target.reset(); fetchTiers(); }
    else { alert(`Failed: ${(j&&j.error)||'Unknown error'}`); }
  });

  fetchTiers();
})();
</script>