<?php declare(strict_types=1);
/** @var array $products @var array $rows */ ?>
<h1 class="text-xl font-semibold mb-4">Issue Free Product</h1>
<p class="text-sm text-slate-600 mb-4">Decrease stock for promotional/bonus units (free issue).</p>

<form method="post" action="<?= h($module_base) ?>/free-products" class="grid md:grid-cols-4 gap-3 mb-6">
  <?= csrf_field() ?>
  <div>
    <label class="block text-sm mb-1">Product</label>
    <select name="product_id" class="w-full rounded-lg border px-3 py-2">
      <?php foreach ($products as $p): ?>
        <option value="<?= (int)$p['id'] ?>"><?= h($p['name']) ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <div>
    <label class="block text-sm mb-1">Quantity</label>
    <input type="number" step="0.01" min="0" name="qty" class="w-full rounded-lg border px-3 py-2">
  </div>
  <div class="md:col-span-2">
    <label class="block text-sm mb-1">Note</label>
    <input type="text" name="note" class="w-full rounded-lg border px-3 py-2" placeholder="Campaign / reason">
  </div>
  <div class="md:col-span-4 flex justify-end">
    <button class="px-4 py-2 rounded-lg bg-emerald-600 text-white">Issue Free</button>
  </div>
</form>

<div class="overflow-x-auto">
  <table class="min-w-full text-sm border rounded-lg overflow-hidden">
    <thead class="bg-slate-50">
      <tr>
        <th class="px-3 py-2 text-left">Product</th>
        <th class="px-3 py-2 text-right">Qty</th>
        <th class="px-3 py-2 text-left">Note</th>
        <th class="px-3 py-2 text-left">When</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($rows as $r): ?>
        <tr class="border-t">
          <td class="px-3 py-2"><?= h($r['product_name'] ?? '') ?></td>
          <td class="px-3 py-2 text-right"><?= number_format((float)($r['qty'] ?? 0),2) ?></td>
          <td class="px-3 py-2"><?= h($r['note'] ?? '') ?></td>
          <td class="px-3 py-2"><?= h($r['created_at'] ?? '') ?></td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$rows): ?>
        <tr><td colspan="4" class="px-3 py-6 text-center text-slate-500">No free issues yet.</td></tr>
      <?php endif; ?>
    </tbody>
  </table>
</div>