<?php
declare(strict_types=1);
$h=fn($v)=>htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');
$fmt=fn($n)=>number_format((float)$n,2);
?>
<div class="space-y-3">
  <div class="flex items-center justify-between">
    <div>
      <div class="text-xl font-semibold">Profit &amp; Loss — Print</div>
      <div class="text-sm text-gray-500">Period: <?= $h($from) ?> → <?= $h($to) ?></div>
    </div>
    <button onclick="window.print()" class="px-3 py-1.5 rounded-lg border hidden print:hidden">Print</button>
  </div>

  <table class="min-w-full text-[13px] border rounded-lg">
    <thead class="bg-gray-50 text-gray-600">
      <tr>
        <th class="px-2 py-1.5 text-left w-36">Account</th>
        <th class="px-2 py-1.5 text-left">Name</th>
        <th class="px-2 py-1.5 text-right w-28">Income</th>
        <th class="px-2 py-1.5 text-right w-28">Expense</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($rows as $r): ?>
        <tr class="border-t">
          <td class="px-2 py-1.5"><?= $h($r['code']) ?></td>
          <td class="px-2 py-1.5"><?= $h($r['name']) ?></td>
          <td class="px-2 py-1.5 text-right"><?= $fmt($r['income']) ?></td>
          <td class="px-2 py-1.5 text-right"><?= $fmt($r['expense']) ?></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
    <tfoot class="bg-gray-50">
      <tr class="font-semibold border-t">
        <td class="px-2 py-1.5" colspan="2">Totals</td>
        <td class="px-2 py-1.5 text-right"><?= $fmt($totalIncome) ?></td>
        <td class="px-2 py-1.5 text-right"><?= $fmt($totalExpense) ?></td>
      </tr>
      <tr class="font-bold">
        <td class="px-2 py-2" colspan="2">Net Profit</td>
        <td class="px-2 py-2 text-right" colspan="2"><?= $fmt($netProfit) ?></td>
      </tr>
    </tfoot>
  </table>
</div>

<style>
@media print {
  header, nav, footer, .kf-sidenav, .kf-brandbar { display:none!important; }
  .kf-content { padding:0!important; }
}
</style>