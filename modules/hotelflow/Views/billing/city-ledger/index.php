<?php
/** @var array $rows @var string $module_base */
$h=fn($v)=>htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');
$base=rtrim((string)($module_base ?? '/apps/hotelflow'),'/');
$active='city-ledger'; include __DIR__.'/../_tabs.php';
?>
<h1 class="text-2xl font-extrabold mb-3">City Ledger</h1>
<div class="overflow-auto rounded-xl border border-slate-200">
  <table class="min-w-[800px] w-full text-sm">
    <thead class="bg-slate-50">
      <tr>
        <th class="px-3 py-2 text-left">Company</th>
        <th class="px-3 py-2 text-right">Balance</th>
        <th class="px-3 py-2">Currency</th>
      </tr>
    </thead>
    <tbody>
      <?php if (!$rows): ?>
        <tr><td colspan="3" class="px-3 py-6 text-center text-slate-500">No city ledger accounts.</td></tr>
      <?php endif; foreach ($rows as $r): ?>
        <tr class="border-t">
          <td class="px-3 py-2 font-medium"><?= $h((string)($r['company_name'] ?? '')) ?></td>
          <td class="px-3 py-2 text-right"><?= number_format((float)($r['balance'] ?? 0),2) ?></td>
          <td class="px-3 py-2 text-center"><?= $h((string)($r['currency'] ?? '')) ?></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>