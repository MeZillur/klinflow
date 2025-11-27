<?php /** simple compact form; wire your styles/utilities as needed */ ?>
<h1 class="text-xl font-semibold mb-3">Create Sales Return</h1>
<?php if (!empty($error)): ?>
  <div class="mb-3 text-red-700 bg-red-50 border border-red-200 rounded p-2"><?= nl2br(htmlspecialchars($error)) ?></div>
<?php endif; ?>
<form method="post" action="./sales-returns">
  <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 mb-4">
    <div>
      <label class="block text-sm mb-1">Return No (optional)</label>
      <input name="return_no" class="input w-full">
    </div>
    <div>
      <label class="block text-sm mb-1">Return Date</label>
      <input name="return_date" type="date" required class="input w-full">
    </div>
    <div>
      <label class="block text-sm mb-1">Customer ID</label>
      <input name="customer_id" type="number" min="1" required class="input w-full">
    </div>
  </div>

  <div class="mb-3">
    <label class="block text-sm mb-1">Link to Sale (optional)</label>
    <input name="sale_id" type="number" class="input w-full" placeholder="Sale ID">
  </div>

  <div class="mb-3">
    <label class="block text-sm mb-1">Reason</label>
    <input name="reason" class="input w-full">
  </div>

  <div class="mb-3">
    <label class="block text-sm mb-1">Items</label>
    <div class="space-y-2">
      <!-- simple 3 rows starter -->
      <?php for ($i=0; $i<3; $i++): ?>
      <div class="grid grid-cols-12 gap-2">
        <input class="input col-span-3" name="items[<?= $i ?>][product_id]" placeholder="Product ID">
        <input class="input col-span-5" name="items[<?= $i ?>][product_name]" placeholder="Product name">
        <input class="input col-span-2" name="items[<?= $i ?>][qty]" placeholder="Qty">
        <input class="input col-span-2" name="items[<?= $i ?>][unit_price]" placeholder="Unit price">
      </div>
      <?php endfor; ?>
    </div>
  </div>

  <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 mb-4">
    <div>
      <label class="block text-sm mb-1">Discount Type</label>
      <select name="discount_type" class="input w-full">
        <option value="amount">Amount</option>
        <option value="percent">Percent</option>
      </select>
    </div>
    <div>
      <label class="block text-sm mb-1">Discount Value</label>
      <input name="discount_value" class="input w-full" value="0">
    </div>
    <div>
      <label class="block text-sm mb-1">Status</label>
      <select name="status" class="input w-full">
        <option value="confirmed">Confirmed (post to stock)</option>
        <option value="draft">Draft (do not post)</option>
        <option value="cancelled">Cancelled</option>
      </select>
    </div>
  </div>

  <button class="btn btn-brand">Save Return</button>
  <a href="./sales-returns" class="btn btn-ghost">Cancel</a>
</form>