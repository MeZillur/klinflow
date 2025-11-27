<?php
/** @var array $rows @var string $module_base */
$h=fn($v)=>htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');
$base=rtrim((string)($module_base ?? '/apps/hotelflow'),'/');
$active='payments'; include __DIR__.'/../_tabs.php';
?>
<h1 class="text-2xl font-extrabold mb-3">Payments</h1>
<div class="overflow-auto rounded-xl border border-slate-200">
  <table class="min-w-[900px] w-full text-sm">
    <thead class="bg-slate-50">
      <tr>
        <th class="px-3 py-2 text-left">Date</th>
        <th class="px-3 py-2 text-right">Amount</th>
        <th class="px-3 py-2">Currency</th>
        <th class="px-3 py-2">Method</th>
        <th class="px-3 py-2 text-left">Note</th>
      </tr>
    </thead>
    <tbody>
      <?php if (!$rows): ?>
        <tr><td colspan="5" class="px-3 py-6 text-center text-slate-500">No payments found.</td></tr>
      <?php endif; foreach ($rows as $r): ?>
        <tr class="border-t">
          <td class="px-3 py-2"><?= $h((string)($r['created_at'] ?? '')) ?></td>
          <td class="px-3 py-2 text-right"><?= number_format((float)($r['amount'] ?? 0),2) ?></td>
          <td class="px-3 py-2 text-center"><?= $h((string)($r['currency'] ?? '')) ?></td>
          <td class="px-3 py-2 text-center"><?= $h((string)($r['method_name'] ?? '')) ?></td>
          <td class="px-3 py-2"><?= $h((string)($r['note'] ?? '')) ?></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>