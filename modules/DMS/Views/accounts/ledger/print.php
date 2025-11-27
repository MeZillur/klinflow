<?php
declare(strict_types=1);
/** @var array $account @var float $opening @var array $rows @var string $from @var string $to */
$h = fn($v)=>htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
$fmt = fn($n)=>number_format((float)$n, 2);
?>
<div class="space-y-3 print:p-0">
  <div class="flex items-center justify-between">
    <div>
      <div class="text-xl font-semibold">General Ledger — Print</div>
      <div class="text-sm text-gray-500">
        <?= $h(($account['code'] ?? '').' — '.($account['name'] ?? '')) ?> · Period:
        <?= $h($from) ?> → <?= $h($to) ?>
      </div>
    </div>
    <button onclick="window.print()" class="px-3 py-1.5 rounded-lg border hidden print:hidden">Print</button>
  </div>

  <table class="min-w-full text-[13px] border rounded-lg">
    <thead class="bg-gray-50 text-gray-600">
      <tr>
        <th class="px-2 py-1.5 text-left w-28">Date</th>
        <th class="px-2 py-1.5 text-left w-28">Journal</th>
        <th class="px-2 py-1.5 text-left w-28">Type</th>
        <th class="px-2 py-1.5 text-left">Memo / Ref</th>
        <th class="px-2 py-1.5 text-right w-28">Debit</th>
        <th class="px-2 py-1.5 text-right w-28">Credit</th>
        <th class="px-2 py-1.5 text-right w-32">Running</th>
      </tr>
    </thead>
    <tbody>
      <tr class="border-t bg-amber-50">
        <td class="px-2 py-1.5"><?= $h(date('Y-m-d', strtotime(($from ?? date('Y-m-01')).' -1 day'))) ?></td>
        <td class="px-2 py-1.5">OPEN</td>
        <td class="px-2 py-1.5">Opening</td>
        <td class="px-2 py-1.5">Opening balance</td>
        <td class="px-2 py-1.5 text-right">0.00</td>
        <td class="px-2 py-1.5 text-right">0.00</td>
        <td class="px-2 py-1.5 text-right font-semibold"><?= $fmt($opening ?? 0) ?></td>
      </tr>
      <?php foreach ($rows as $r): ?>
        <tr class="border-t">
          <td class="px-2 py-1.5"><?= $h($r['jdate']) ?></td>
          <td class="px-2 py-1.5"><?= $h($r['jno']) ?></td>
          <td class="px-2 py-1.5"><?= $h($r['jtype']) ?></td>
          <td class="px-2 py-1.5">
            <?= $h($r['memo'] ?? '') ?>
            <?php if (!empty($r['ref_table']) && !empty($r['ref_id'])): ?>
              <span class="text-gray-400">·</span>
              <span class="text-xs text-gray-500"><?= $h($r['ref_table']) ?>#<?= (int)$r['ref_id'] ?></span>
            <?php endif; ?>
          </td>
          <td class="px-2 py-1.5 text-right"><?= $fmt($r['dr']) ?></td>
          <td class="px-2 py-1.5 text-right"><?= $fmt($r['cr']) ?></td>
          <td class="px-2 py-1.5 text-right font-semibold"><?= $fmt($r['running']) ?></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

<style>
@media print {
  header, nav, footer, .kf-sidenav, .kf-brandbar { display:none !important; }
  .kf-content { padding:0 !important; }
  table { page-break-inside: auto; }
  tr { page-break-inside: avoid; page-break-after: auto; }
}
</style>