<?php declare(strict_types=1); if(!function_exists('h')){function h($v){return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');}} ?>
<div class="flex items-center justify-between mb-4">
  <h1 class="text-xl font-semibold">Payment <?= h($p['pay_no'] ?? '') ?></h1>
  <div class="flex gap-2">
    <a href="<?= h($module_base) ?>/payments/<?= (int)$p['id'] ?>/edit" class="px-3 py-2 rounded-lg bg-slate-100 hover:bg-slate-200 text-sm">Edit</a>
    <a href="<?= h($module_base) ?>/payments" class="px-3 py-2 rounded-lg bg-slate-100 hover:bg-slate-200 text-sm">Back</a>
  </div>
</div>

<div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
  <div class="p-4 rounded-lg bg-slate-50">
    <div class="text-xs text-slate-500">Date</div>
    <div class="font-medium"><?= h($p['pay_date']) ?></div>
  </div>
  <div class="p-4 rounded-lg bg-slate-50">
    <div class="text-xs text-slate-500">Method</div>
    <div class="font-medium capitalize"><?= h($p['method']) ?></div>
  </div>
  <div class="p-4 rounded-lg bg-slate-50">
    <div class="text-xs text-slate-500">Amount</div>
    <div class="font-semibold">৳ <?= number_format((float)$p['amount'],2) ?></div>
  </div>
</div>

<div class="grid grid-cols-1 md:grid-cols-2 gap-4">
  <div class="p-4 rounded-lg bg-white md:border md:rounded-xl">
    <div class="text-sm font-semibold mb-2">Counterparty</div>
    <div class="text-sm">Type: <span class="capitalize"><?= h($p['counterparty']) ?></span></div>
    <?php if (($p['customer_id'] ?? null)): ?>
      <div class="text-sm">Customer ID: <?= (int)$p['customer_id'] ?></div>
    <?php endif; ?>
    <?php if (($p['dealer_id'] ?? null)): ?>
      <div class="text-sm">Dealer ID: <?= (int)$p['dealer_id'] ?></div>
    <?php endif; ?>
    <?php if (($p['bank_account_id'] ?? null)): ?>
      <div class="text-sm mt-2">Bank Account ID: <?= (int)$p['bank_account_id'] ?></div>
    <?php endif; ?>
    <?php if (!empty($p['notes'])): ?>
      <div class="text-sm mt-2 text-slate-600"><?= nl2br(h($p['notes'])) ?></div>
    <?php endif; ?>
  </div>

  <div class="p-4 rounded-lg bg-white md:border md:rounded-xl">
    <div class="text-sm font-semibold mb-2">Allocations (optional)</div>
    <?php if (!empty($alloc)): ?>
      <div class="overflow-x-auto">
        <table class="min-w-full text-sm border rounded-lg overflow-hidden">
          <thead class="bg-slate-50">
            <tr>
              <th class="px-3 py-2 text-left">Invoice</th>
              <th class="px-3 py-2 text-right">Applied</th>
            </tr>
          </thead>
          <tbody>
          <?php foreach ($alloc as $a): ?>
            <tr class="border-t">
              <td class="px-3 py-2"><?= h($a['invoice_no'] ?? ('#'.$a['invoice_id'])) ?></td>
              <td class="px-3 py-2 text-right">৳ <?= number_format((float)$a['amount'],2) ?></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php else: ?>
      <div class="text-sm text-slate-500">No allocations.</div>
    <?php endif; ?>
  </div>
</div>