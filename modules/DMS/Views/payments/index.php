<?php declare(strict_types=1); if(!function_exists('h')){function h($v){return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');}} ?>
<div class="flex items-center justify-between mb-4">
  <h1 class="text-xl font-semibold">Payments</h1>
  <a href="<?= h($module_base) ?>/payments/create" class="px-3 py-2 rounded-lg bg-emerald-600 text-white hover:bg-emerald-700 text-sm">
    + New Payment
  </a>
</div>

<form method="get" class="mb-4">
  <div class="flex gap-2">
    <input type="text" name="q" value="<?= h($q ?? '') ?>" placeholder="Search by Payment No…"
           class="w-64 rounded-lg border px-3 py-2">
    <button class="px-3 py-2 rounded-lg bg-slate-100 hover:bg-slate-200">Search</button>
  </div>
</form>

<div class="overflow-x-auto">
  <table class="min-w-full text-sm border rounded-lg overflow-hidden">
    <thead class="bg-slate-50">
      <tr>
        <th class="px-3 py-2 text-left">Payment No</th>
        <th class="px-3 py-2 text-left">Date</th>
        <th class="px-3 py-2 text-left">Method</th>
        <th class="px-3 py-2 text-left">Counterparty</th>
        <th class="px-3 py-2 text-right">Amount</th>
        <th class="px-3 py-2"></th>
      </tr>
    </thead>
    <tbody>
      <?php foreach (($rows??[]) as $r): ?>
      <tr class="border-t">
        <td class="px-3 py-2"><a class="text-emerald-700 hover:underline" href="<?= h($module_base) ?>/payments/<?= (int)$r['id'] ?>"><?= h($r['pay_no']) ?></a></td>
        <td class="px-3 py-2"><?= h($r['pay_date']) ?></td>
        <td class="px-3 py-2 capitalize"><?= h($r['method']) ?></td>
        <td class="px-3 py-2 capitalize"><?= h($r['counterparty']) ?></td>
        <td class="px-3 py-2 text-right">৳ <?= number_format((float)$r['amount'],2) ?></td>
        <td class="px-3 py-2 text-right">
          <a href="<?= h($module_base) ?>/payments/<?= (int)$r['id'] ?>/edit" class="text-sm text-slate-600 hover:underline">Edit</a>
        </td>
      </tr>
      <?php endforeach; if (empty($rows)): ?>
      <tr><td colspan="6" class="px-3 py-6 text-center text-slate-500">No payments yet.</td></tr>
      <?php endif; ?>
    </tbody>
  </table>
</div>