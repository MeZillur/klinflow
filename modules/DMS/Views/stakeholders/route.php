<?php if(!function_exists('h')){function h($v){return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');}} ?>
<h1 class="text-xl font-semibold mb-4">Route Planning</h1>

<form method="POST" action="<?= h($module_base) ?>/stakeholders/route" class="space-y-4" id="routeForm">
  <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
    <div>
      <label class="block text-sm font-medium mb-1">Route Name</label>
      <input name="name" class="w-full rounded-lg border px-3 py-2" placeholder="e.g., Monday North Loop" required>
    </div>
    <div>
      <label class="block text-sm font-medium mb-1">Stakeholder ID</label>
      <input name="stakeholder_id" class="w-full rounded-lg border px-3 py-2" placeholder="SR/DSR ID" required>
    </div>
  </div>

  <div class="p-3 rounded-lg border bg-white dark:bg-gray-800">
    <div class="flex items-center justify-between mb-2">
      <div class="font-semibold">Stops</div>
      <button type="button" id="addStop" class="px-3 py-1.5 rounded-lg bg-slate-100 hover:bg-slate-200">+ Add stop</button>
    </div>
    <div id="stops" class="space-y-2"></div>
  </div>

  <div class="flex justify-end">
    <button class="px-4 py-2 rounded-lg bg-emerald-600 text-white hover:bg-emerald-700">Save Route</button>
  </div>
</form>

<script>
(function(){
  const list = document.getElementById('stops');
  document.getElementById('addStop').addEventListener('click', ()=> add());
  function add(){
    const row = document.createElement('div');
    row.className = 'grid grid-cols-1 md:grid-cols-4 gap-2';
    row.innerHTML = `
      <input name="stops[][customer_id]" class="rounded-lg border px-3 py-2" placeholder="Customer ID">
      <input name="stops[][dealer_id]"   class="rounded-lg border px-3 py-2" placeholder="Dealer ID">
      <select name="stops[][weekday]" class="rounded-lg border px-3 py-2">
        <option value="">— Weekday —</option>
        <option value="1">Mon</option><option value="2">Tue</option><option value="3">Wed</option>
        <option value="4">Thu</option><option value="5">Fri</option><option value="6">Sat</option><option value="7">Sun</option>
      </select>
      <input name="stops[][notes]" class="rounded-lg border px-3 py-2" placeholder="Notes">
    `;
    list.appendChild(row);
  }
})();
</script>