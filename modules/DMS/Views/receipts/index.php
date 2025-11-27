<?php declare(strict_types=1); if(!function_exists('h')){function h($v){return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');}} ?>
<div class="flex items-center justify-between mb-4">
  <h2 class="text-xl font-semibold">Receipts</h2>
  <a class="px-3 py-2 rounded-lg bg-emerald-600 text-white" href="<?= h($module_base.'/receipts/create') ?>">+ New Receipt</a>
</div>
<div class="overflow-x-auto">
  <table class="min-w-full text-sm border rounded-xl overflow-hidden">
    <thead class="bg-slate-50">
      <tr>
        <th class="px-3 py-2 text-left w-[16%]">Date</th>
        <th class="px-3 py-2 text-left w-[18%]">No</th>
        <th class="px-3 py-2 text-left">Customer</th>
        <th class="px-3 py-2 text-right w-[18%]">Amount</th>
        <th class="px-3 py-2 text-right w-[16%]">Actions</th>
      </tr>
    </thead>
    <tbody>
      <?php if(empty($rows)): ?>
        <tr><td colspan="5" class="px-3 py-8 text-center text-slate-500">No receipts yet.</td></tr>
      <?php else: foreach($rows as $r): ?>
        <tr class="border-t hover:bg-slate-50">
          <td class="px-3 py-2"><?= h(substr($r['receipt_date']??'',0,10)) ?></td>
          <td class="px-3 py-2"><?= h($r['receipt_no']??'') ?></td>
          <td class="px-3 py-2"><?= h($r['customer_name']??'') ?></td>
          <td class="px-3 py-2 text-right">৳ <?= number_format((float)($r['total_amount']??0),2) ?></td>
          <td class="px-3 py-2 text-right">
            <a class="px-2 py-1 rounded border hover:bg-slate-50" href="<?= h($module_base.'/receipts/'.(int)$r['id'].'/print?autoprint=1') ?>">Print</a>
          </td>
        </tr>
      <?php endforeach; endif; ?>
    </tbody>
  </table>
</div>