<h1 class="text-xl font-semibold mb-4">Route & Customer Mapping</h1>
<p class="text-sm text-slate-600 mb-4">Attach customers/dealers to routes. (You can evolve this into a drag-drop UI later.)</p>
<form method="POST" action="<?= h($module_base) ?>/stakeholders/mapping" class="space-y-4">
  <div class="grid grid-cols-1 md:grid-cols-4 gap-3">
    <input name="route_id" class="rounded-lg border px-3 py-2" placeholder="Route ID">
    <input name="customer_id" class="rounded-lg border px-3 py-2" placeholder="Customer ID">
    <input name="dealer_id" class="rounded-lg border px-3 py-2" placeholder="Dealer ID">
    <input name="notes" class="rounded-lg border px-3 py-2" placeholder="Notes">
  </div>
  <div class="flex justify-end"><button class="px-4 py-2 rounded-lg bg-emerald-600 text-white hover:bg-emerald-700">Save</button></div>
</form>