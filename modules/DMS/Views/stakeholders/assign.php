<?php declare(strict_types=1); if(!function_exists('h')){function h($v){return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');}} ?>
<h1 class="text-xl font-semibold mb-4">Assign Customer / Dealer to SR/DSR</h1>

<form method="POST" action="<?= h($module_base) ?>/stakeholders/assign" class="space-y-4">
  <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
    <div>
      <label class="block text-sm font-medium mb-1">Stakeholder</label>
      <input type="number" name="stakeholder_id" class="w-full rounded-lg border px-3 py-2" placeholder="Stakeholder ID" required>
      <p class="text-xs text-slate-500">You can wire a stakeholder typeahead later.</p>
    </div>
    <div>
      <label class="block text-sm font-medium mb-1">Customer (optional)</label>
      <input id="ta_customer" class="w-full rounded-lg border px-3 py-2" placeholder="Search customer…">
      <input type="hidden" name="customer_id" id="customer_id">
    </div>
    <div>
      <label class="block text-sm font-medium mb-1">Dealer (optional)</label>
      <input id="ta_dealer" class="w-full rounded-lg border px-3 py-2" placeholder="Search dealer…">
      <input type="hidden" name="dealer_id" id="dealer_id">
    </div>
  </div>

  <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
    <div>
      <label class="block text-sm font-medium mb-1">Assigned On</label>
      <input type="date" name="assigned_on" value="<?= h(date('Y-m-d')) ?>" class="w-full rounded-lg border px-3 py-2">
    </div>
    <div class="md:col-span-2">
      <label class="block text-sm font-medium mb-1">Notes</label>
      <input name="notes" class="w-full rounded-lg border px-3 py-2" placeholder="Optional notes…">
    </div>
  </div>

  <div class="flex justify-end">
    <button class="px-4 py-2 rounded-lg bg-emerald-600 text-white hover:bg-emerald-700">Save Assignment</button>
  </div>
</form>

<script>
(async function(){
  const cust = document.getElementById('ta_customer');
  const custId = document.getElementById('customer_id');
  const deal = document.getElementById('ta_dealer');
  const dealId = document.getElementById('dealer_id');

  function hookTypeahead(input, hidden, url){
    let timer;
    const box = document.createElement('div');
    box.className = 'mt-1 rounded-lg border bg-white dark:bg-gray-900 shadow max-h-56 overflow-auto hidden';
    input.parentElement.appendChild(box);

    function render(list){
      if(!list || !list.length){ box.classList.add('hidden'); box.innerHTML=''; return; }
      box.innerHTML = list.map(x=>`
        <div data-id="${x.id}" class="px-3 py-2 hover:bg-gray-50 dark:hover:bg-gray-800 cursor-pointer">
          <div class="font-medium">${x.code || ''} — ${x.name || ''}</div>
          <div class="text-xs text-slate-500">${x.phone || ''}</div>
        </div>
      `).join('');
      box.classList.remove('hidden');
    }

    input.addEventListener('input', ()=>{
      clearTimeout(timer);
      const q = input.value.trim();
      if(!q){ hidden.value=''; render([]); return; }
      timer = setTimeout(async ()=>{
        const r = await fetch(url+'?q='+encodeURIComponent(q), {headers:{'Accept':'application/json'}});
        if(r.ok){ render(await r.json()); }
      }, 200);
    });

    box.addEventListener('click', (e)=>{
      const row = e.target.closest('[data-id]');
      if(!row) return;
      const id = row.dataset.id;
      hidden.value = id;
      input.value = row.querySelector('.font-medium').textContent;
      box.classList.add('hidden');
    });

    document.addEventListener('click', e=>{
      if(!box.contains(e.target) && e.target !== input) box.classList.add('hidden');
    }, true);
  }

  hookTypeahead(cust, custId, '<?= h($module_base) ?>/api/customers/search');
  hookTypeahead(deal, dealId, '<?= h($module_base) ?>/api/dealers/search');
})();
</script>