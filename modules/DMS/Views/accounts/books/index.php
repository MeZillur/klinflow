<?php
$base = $module_base ?? '/apps/dms';
$f = $filters ?? ['from'=>date('Y-m-01'),'to'=>date('Y-m-d'),'account_id'=>0,'cleared'=>'all'];
$title = $title ?? 'Cash Book';
$printUrl = $base.'/accounts/'.($_GET['page'] ?? 'cash-book').'?print=1'
          . '&account_id='.(int)$f['account_id'].'&from='.urlencode($f['from']).'&to='.urlencode($f['to'])
          . '&cleared='.urlencode($f['cleared']);
?>
<div class="mx-auto max-w-6xl px-4 py-6">
  <div class="flex items-center justify-between mb-4">
    <h1 class="text-2xl font-semibold"><?= htmlspecialchars($title) ?></h1>
    <a class="px-3 py-2 rounded-md bg-slate-900 text-white dark:bg-slate-100 dark:text-slate-900"
       target="_blank" rel="noopener" href="<?= htmlspecialchars($printUrl) ?>">Print</a>
  </div>

  <form method="get" class="grid md:grid-cols-5 gap-3 mb-4">
    <input type="hidden" name="page" value="<?= htmlspecialchars($_GET['page'] ?? '') ?>">
    <label class="block">
      <span class="text-sm text-slate-500">Account</span>
      <select name="account_id" class="mt-1 w-full rounded-md border-slate-300 bg-white dark:bg-slate-800 dark:border-slate-700">
        <option value="0">— select —</option>
        <?php foreach (($accounts ?? []) as $a): ?>
          <option value="<?= (int)$a['id'] ?>" <?= (int)$f['account_id']===(int)$a['id']?'selected':'' ?>>
            <?= htmlspecialchars($a['code'].' — '.$a['name']) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </label>
    <label class="block">
      <span class="text-sm text-slate-500">From</span>
      <input type="date" name="from" value="<?= htmlspecialchars($f['from']) ?>" class="mt-1 w-full rounded-md border-slate-300 bg-white dark:bg-slate-800 dark:border-slate-700">
    </label>
    <label class="block">
      <span class="text-sm text-slate-500">To</span>
      <input type="date" name="to" value="<?= htmlspecialchars($f['to']) ?>" class="mt-1 w-full rounded-md border-slate-300 bg-white dark:bg-slate-800 dark:border-slate-700">
    </label>
    <label class="block">
      <span class="text-sm text-slate-500">Cleared</span>
      <select name="cleared" class="mt-1 w-full rounded-md border-slate-300 bg-white dark:bg-slate-800 dark:border-slate-700">
        <option value="all"    <?= ($f['cleared']??'all')==='all'?'selected':'' ?>>All</option>
        <option value="yes"    <?= ($f['cleared']??'all')==='yes'?'selected':'' ?>>Cleared</option>
        <option value="no"     <?= ($f['cleared']??'all')==='no'?'selected':''  ?>>Uncleared</option>
      </select>
    </label>
    <div class="flex items-end">
      <button class="w-full px-3 py-2 rounded-md bg-emerald-600 text-white">Apply</button>
    </div>
  </form>

  <div class="overflow-x-auto rounded-lg border border-slate-200 dark:border-slate-700">
    <table class="min-w-full text-sm">
      <thead class="bg-slate-50 dark:bg-slate-800/60">
        <tr class="text-left">
          <th class="px-3 py-2">Date</th>
          <th class="px-3 py-2">Ref</th>
          <th class="px-3 py-2">Memo</th>
          <th class="px-3 py-2 text-right">Debit</th>
          <th class="px-3 py-2 text-right">Credit</th>
          <th class="px-3 py-2 text-right">Δ</th>
          <th class="px-3 py-2 text-right">Running</th>
          <th class="px-3 py-2">Cleared</th>
        </tr>
      </thead>
      <tbody>
        <?php $run = (float)($opening ?? 0); ?>
        <?php if (!empty($rows)): foreach ($rows as $r): ?>
          <?php $delta = (float)($r['dr'] ?? 0) - (float)($r['cr'] ?? 0); $run += $delta; ?>
          <tr class="border-t border-slate-100 dark:border-slate-800">
            <td class="px-3 py-2"><?= htmlspecialchars($r['jdate'] ?? '') ?></td>
            <td class="px-3 py-2"><?= htmlspecialchars($r['jno'] ?? '') ?></td>
            <td class="px-3 py-2"><?= htmlspecialchars($r['memo'] ?? '') ?></td>
            <td class="px-3 py-2 text-right"><?= number_format((float)($r['dr'] ?? 0),2) ?></td>
            <td class="px-3 py-2 text-right"><?= number_format((float)($r['cr'] ?? 0),2) ?></td>
            <td class="px-3 py-2 text-right"><?= number_format($delta,2) ?></td>
            <td class="px-3 py-2 text-right font-medium"><?= number_format($run,2) ?></td>
            <td class="px-3 py-2">
              <?= !empty($r['is_cleared']) ? 'Yes' : 'No' ?>
            </td>
          </tr>
        <?php endforeach; else: ?>
          <tr><td colspan="8" class="px-3 py-6 text-center text-slate-500">Apply filters to view book.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>