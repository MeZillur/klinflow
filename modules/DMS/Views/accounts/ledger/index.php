<?php
declare(strict_types=1);
/** @var array $accounts @var array|null $account @var float $opening @var array $rows */
$h = fn($v)=>htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
$fmt = fn($n)=>number_format((float)$n, 2);
?>
<div class="space-y-4">
  <div class="flex items-center justify-between gap-3 flex-wrap">
    <h1 class="text-xl font-semibold">General Ledger</h1>
    <div class="flex items-center gap-2">
      <a class="px-3 py-1.5 rounded-lg border hover:bg-gray-50 dark:hover:bg-gray-800"
         href="?account_id=<?= (int)($_GET['account_id'] ?? 0) ?>&from=<?= $h($_GET['from'] ?? '') ?>&to=<?= $h($_GET['to'] ?? '') ?>&print=1" target="_blank">
        Print
      </a>
      <button id="btn-csv" class="px-3 py-1.5 rounded-lg border hover:bg-gray-50 dark:hover:bg-gray-800">Export CSV</button>
    </div>
  </div>

  <form method="get" class="grid grid-cols-1 sm:grid-cols-4 gap-3 p-3 rounded-xl border bg-white dark:bg-gray-900">
    <label class="text-sm">
      <div class="text-gray-500 mb-1">Account</div>
      <select name="account_id" class="w-full border rounded-lg px-3 py-2 bg-white dark:bg-gray-900">
        <?php foreach ($accounts as $a): ?>
          <option value="<?= (int)$a['id'] ?>" <?= ((int)$a['id'] === (int)($account['id'] ?? 0)) ? 'selected':'' ?>>
            <?= $h($a['code'].' — '.$a['name']) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </label>
    <label class="text-sm">
      <div class="text-gray-500 mb-1">From</div>
      <input type="date" name="from" value="<?= $h($from ?? '') ?>" class="w-full border rounded-lg px-3 py-2 bg-white dark:bg-gray-900">
    </label>
    <label class="text-sm">
      <div class="text-gray-500 mb-1">To</div>
      <input type="date" name="to" value="<?= $h($to ?? '') ?>" class="w-full border rounded-lg px-3 py-2 bg-white dark:bg-gray-900">
    </label>
    <div class="flex items-end">
      <button class="px-3 py-2 rounded-lg bg-emerald-600 text-white hover:bg-emerald-700 w-full">Apply</button>
    </div>
  </form>

  <div class="overflow-x-auto rounded-lg border">
    <table class="min-w-full text-[13px]">
      <thead class="bg-gray-50 dark:bg-gray-800 text-gray-600 dark:text-gray-300">
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
      <tbody id="rows">
        <tr class="border-t bg-amber-50 dark:bg-amber-900/20">
          <td class="px-2 py-1.5"><?= $h(date('Y-m-d', strtotime(($from ?? date('Y-m-01')).' -1 day'))) ?></td>
          <td class="px-2 py-1.5">OPEN</td>
          <td class="px-2 py-1.5">Opening</td>
          <td class="px-2 py-1.5 text-gray-600">Opening balance</td>
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
              <div class="truncate max-w-[420px]">
                <?= $h($r['memo'] ?? '') ?>
                <?php if (!empty($r['ref_table']) && !empty($r['ref_id'])): ?>
                  <span class="text-gray-400">·</span>
                  <span class="text-xs text-gray-500"><?= $h($r['ref_table']) ?>#<?= (int)$r['ref_id'] ?></span>
                <?php endif; ?>
              </div>
            </td>
            <td class="px-2 py-1.5 text-right"><?= $fmt($r['dr']) ?></td>
            <td class="px-2 py-1.5 text-right"><?= $fmt($r['cr']) ?></td>
            <td class="px-2 py-1.5 text-right font-semibold"><?= $fmt($r['running']) ?></td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$rows): ?>
          <tr><td colspan="7" class="px-3 py-6 text-center text-sm text-gray-500">No entries in this period.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<script>
(function(){
  const money = n => (Math.round((+n||0)*100)/100).toFixed(2);

  document.getElementById('btn-csv')?.addEventListener('click', ()=>{
    const rows = Array.from(document.querySelectorAll('table tbody tr'));
    const head = ['Date','Journal','Type','Memo/Ref','Debit','Credit','Running'];
    const out  = [head.join(',')];
    rows.forEach(tr=>{
      const tds = tr.querySelectorAll('td');
      if (!tds.length) return;
      const cells = Array.from(tds).map(td => {
        const t = (td.innerText||'').trim().replace(/\s+/g,' ');
        return `"${t.replace(/"/g,'""')}"`;
      });
      out.push(cells.join(','));
    });
    const blob = new Blob([out.join('\n')], {type:'text/csv;charset=utf-8;'});
    const url  = URL.createObjectURL(blob);
    const a    = document.createElement('a');
    a.href = url;
    a.download = 'general-ledger.csv';
    document.body.appendChild(a); a.click(); a.remove();
    URL.revokeObjectURL(url);
  });
})();
</script>