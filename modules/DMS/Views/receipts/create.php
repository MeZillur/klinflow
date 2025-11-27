<?php declare(strict_types=1); if(!function_exists('h')){function h($v){return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');}} ?>
<h2 class="text-xl font-semibold mb-3">Record Receipt</h2>
<?php if(!empty($error)): ?><div class="mb-3 rounded border border-rose-200 bg-rose-50 px-3 py-2 text-rose-700 text-sm"><?= nl2br(h($error)) ?></div><?php endif; ?>
<form method="POST" action="<?= h($module_base.'/receipts') ?>" class="space-y-4">
  <?= function_exists('csrf_field') ? csrf_field() : '' ?>

  <div class="grid sm:grid-cols-4 gap-3">
    <div>
      <label class="block text-sm font-medium mb-1">Receipt No</label>
      <input name="receipt_no" class="w-full rounded border px-3 py-2">
    </div>
    <div>
      <label class="block text-sm font-medium mb-1">Date</label>
      <input type="date" name="receipt_date" value="<?= h(date('Y-m-d')) ?>" class="w-full rounded border px-3 py-2">
    </div>
    <div class="sm:col-span-2">
      <label class="block text-sm font-medium mb-1">Customer</label>
      <input name="customer_name" class="w-full rounded border px-3 py-2" placeholder="Optional (for print)">
      <input type="hidden" name="customer_id" value="">
    </div>
  </div>

  <div>
    <label class="block text-sm font-medium mb-1">Apply to Invoices (optional)</label>
    <div id="applyBox" class="space-y-2">
      <div class="flex gap-2">
        <input name="apply[0][sale_id]" class="w-40 rounded border px-2 py-1" placeholder="Sale ID">
        <input name="apply[0][amount]" class="w-40 rounded border px-2 py-1" placeholder="Amount">
      </div>
    </div>
    <button type="button" id="addLine" class="mt-2 px-2 py-1 rounded bg-slate-100">+ add line</button>
  </div>

  <div class="grid sm:grid-cols-3 gap-3">
    <div>
      <label class="block text-sm font-medium mb-1">On-account Amount (if not applying to invoices)</label>
      <input name="on_account_amount" class="w-full rounded border px-3 py-2" placeholder="0.00">
    </div>
    <div>
      <label class="block text-sm font-medium mb-1">Payment Method</label>
      <input name="pay_method" value="cash" class="w-full rounded border px-3 py-2">
    </div>
    <div>
      <label class="block text-sm font-medium mb-1">Notes</label>
      <input name="notes" class="w-full rounded border px-3 py-2">
    </div>
  </div>

  <div class="flex justify-end">
    <button class="px-4 py-2 rounded bg-emerald-600 text-white">Save Receipt</button>
  </div>
</form>

<script>
document.getElementById('addLine').addEventListener('click', ()=>{
  const box = document.getElementById('applyBox');
  const idx = box.querySelectorAll('.flex').length;
  const row = document.createElement('div');
  row.className = 'flex gap-2';
  row.innerHTML = `
    <input name="apply[${idx}][sale_id]" class="w-40 rounded border px-2 py-1" placeholder="Sale ID">
    <input name="apply[${idx}][amount]" class="w-40 rounded border px-2 py-1" placeholder="Amount">
  `;
  box.appendChild(row);
});
</script>