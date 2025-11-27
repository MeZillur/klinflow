<?php
declare(strict_types=1);

/** @var array $rows @var array $tot @var string $from @var string $to @var string $module_base */

$h = static fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
$nf = static fn($n) => number_format((float)$n, 2);

$base = rtrim((string)($module_base ?? '/apps/dms'), '/');
$applyUrl = $base . '/accounts/trial-balance';
$printUrl = $applyUrl . '?from=' . $h($from) . '&to=' . $h($to) . '&print=1';
$csvUrl   = $applyUrl . '?from=' . $h($from) . '&to=' . $h($to) . '&csv=1';
?>
<div class="space-y-5">
  <div class="flex items-center justify-between flex-wrap gap-3">
    <h1 class="text-xl font-semibold">Trial Balance</h1>

    <form class="flex flex-wrap gap-2 items-center" method="get" action="<?= $h($applyUrl) ?>">
      <label class="text-sm text-gray-600">From</label>
      <input type="date" name="from" value="<?= $h($from) ?>" class="px-3 py-2 border rounded-md">
      <label class="text-sm text-gray-600">To</label>
      <input type="date" name="to" value="<?= $h($to) ?>" class="px-3 py-2 border rounded-md">
      <button type="submit" class="px-4 py-2 rounded-md bg-emerald-600 text-white hover:bg-emerald-700">
        Apply
      </button>
      <a href="<?= $h($printUrl) ?>" class="px-3 py-2 rounded-md border hover:bg-gray-50">Print</a>
      <a href="<?= $h($csvUrl) ?>"   class="px-3 py-2 rounded-md border hover:bg-gray-50">Export CSV</a>
    </form>
  </div>

  <div class="overflow-x-auto">
    <table class="min-w-full border divide-y">
      <thead class="bg-gray-50">
        <tr class="text-left text-sm">
          <th class="px-3 py-2 w-32">Account</th>
          <th class="px-3 py-2">Name</th>
          <th class="px-3 py-2 text-right">Opening Dr</th>
          <th class="px-3 py-2 text-right">Opening Cr</th>
          <th class="px-3 py-2 text-right">Period Dr</th>
          <th class="px-3 py-2 text-right">Period Cr</th>
          <th class="px-3 py-2 text-right">Closing Dr</th>
          <th class="px-3 py-2 text-right">Closing Cr</th>
        </tr>
      </thead>
      <tbody class="divide-y">
        <?php if ($rows): ?>
          <?php foreach ($rows as $r): 
            $pos = ($r['closing_dr'] ?? 0) > 0;
            $neg = ($r['closing_cr'] ?? 0) > 0;
            $rowClass = $pos ? 'text-emerald-700' : ($neg ? 'text-rose-700' : 'text-gray-800');
          ?>
          <tr class="text-sm <?= $h($rowClass) ?>">
            <td class="px-3 py-2 font-mono"><?= $h($r['code']) ?></td>
            <td class="px-3 py-2"><?= $h($r['name']) ?></td>
            <td class="px-3 py-2 text-right"><?= $nf($r['opening_dr'] ?? 0) ?></td>
            <td class="px-3 py-2 text-right"><?= $nf($r['opening_cr'] ?? 0) ?></td>
            <td class="px-3 py-2 text-right"><?= $nf($r['period_dr'] ?? 0) ?></td>
            <td class="px-3 py-2 text-right"><?= $nf($r['period_cr'] ?? 0) ?></td>
            <td class="px-3 py-2 text-right font-medium"><?= $nf($r['closing_dr'] ?? 0) ?></td>
            <td class="px-3 py-2 text-right font-medium"><?= $nf($r['closing_cr'] ?? 0) ?></td>
          </tr>
          <?php endforeach; ?>
        <?php else: ?>
          <tr>
            <td colspan="8" class="px-3 py-6 text-center text-sm text-gray-500">
              No data found for the selected period.
              <div class="mt-1">
                If you recently posted journals, ensure <span class="font-semibold">org_id</span> and
                <span class="font-semibold">account mapping</span> are correct.
              </div>
            </td>
          </tr>
        <?php endif; ?>
      </tbody>
      <tfoot class="bg-gray-50">
        <tr class="text-sm font-semibold">
          <td class="px-3 py-2" colspan="2">Totals</td>
          <td class="px-3 py-2 text-right"><?= $nf($tot['opening_dr'] ?? 0) ?></td>
          <td class="px-3 py-2 text-right"><?= $nf($tot['opening_cr'] ?? 0) ?></td>
          <td class="px-3 py-2 text-right"><?= $nf($tot['period_dr']  ?? 0) ?></td>
          <td class="px-3 py-2 text-right"><?= $nf($tot['period_cr']  ?? 0) ?></td>
          <td class="px-3 py-2 text-right"><?= $nf($tot['closing_dr'] ?? 0) ?></td>
          <td class="px-3 py-2 text-right"><?= $nf($tot['closing_cr'] ?? 0) ?></td>
        </tr>
      </tfoot>
    </table>
  </div>
</div>

<script>
document.getElementById('btn-csv')?.addEventListener('click', ()=>{
  const table = document.getElementById('tb');
  const rows  = Array.from(table.querySelectorAll('tr'));
  const csv   = rows.map(tr => Array.from(tr.children).map(td=>{
    const t=(td.innerText||'').trim().replace(/\s+/g,' ');
    return `"${t.replace(/"/g,'""')}"`;
  }).join(',')).join('\n');
  const blob=new Blob([csv],{type:'text/csv;charset=utf-8;'});
  const url=URL.createObjectURL(blob); const a=document.createElement('a');
  a.href=url; a.download='trial-balance.csv'; document.body.appendChild(a); a.click(); a.remove(); URL.revokeObjectURL(url);
});
</script>