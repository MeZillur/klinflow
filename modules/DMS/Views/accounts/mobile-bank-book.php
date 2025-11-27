<?php
declare(strict_types=1);
/** @var array $rows @var string $from @var string $to
 *  @var float $opening @var float $closing
 *  @var array $wallets each: id, provider, wallet_no, is_master
 *  @var int|string $wallet_id
 *  @var string $module_base
 */
if(!function_exists('h')){function h($v){return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');}}
$rows   = is_array($rows??null)?$rows:[];
$from   = $from ?? date('Y-m-01');
$to     = $to   ?? date('Y-m-d');
$opening= (float)($opening ?? 0);
$closing= (float)($closing ?? 0);
$wallets = is_array($wallets??null)?$wallets:[];
$wallet_id = (string)($wallet_id ?? '');
$module_base = $module_base ?? '';
?>
<h1 class="text-xl font-semibold mb-4">Mobile Bank Book</h1>

<form method="GET" class="mb-4 flex flex-wrap items-center gap-2">
  <select name="wallet_id" class="rounded-lg border px-3 py-1.5">
    <option value="">— All Wallets —</option>
    <?php foreach ($wallets as $w): ?>
      <?php
        $label = (($w['is_master']??0)?'★ ':'').($w['provider']??'').' — '.($w['wallet_no']??'');
        $sel = ((string)$wallet_id === (string)$w['id']) ? 'selected' : '';
      ?>
      <option value="<?= (int)$w['id'] ?>" <?=$sel?>><?= h($label) ?></option>
    <?php endforeach; ?>
  </select>
  <input type="date" name="from" value="<?=h($from)?>" class="rounded-lg border px-3 py-1.5">
  <input type="date" name="to"   value="<?=h($to)?>"   class="rounded-lg border px-3 py-1.5">
  <button class="px-3 py-1.5 rounded-lg bg-emerald-600 text-white hover:bg-emerald-700">Filter</button>
</form>

<div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-3">
  <div class="rounded-lg border p-3 bg-white/50 dark:bg-gray-800/50">
    <div class="text-xs uppercase text-slate-500 mb-1">Opening Balance</div>
    <div class="text-xl font-semibold">৳ <?= number_format($opening,2) ?></div>
  </div>
  <div class="rounded-lg border p-3 bg-white/50 dark:bg-gray-800/50">
    <div class="text-xs uppercase text-slate-500 mb-1">Closing Balance</div>
    <div class="text-xl font-semibold">৳ <?= number_format($closing,2) ?></div>
  </div>
</div>

<div class="overflow-x-auto rounded-xl border">
  <table class="min-w-full text-sm">
    <thead class="bg-slate-50 dark:bg-gray-800/50">
      <tr>
        <th class="text-left px-3 py-2">Date</th>
        <th class="text-left px-3 py-2">Wallet</th>
        <th class="text-left px-3 py-2">Reference</th>
        <th class="text-left px-3 py-2">Notes</th>
        <th class="text-right px-3 py-2">Debit</th>
        <th class="text-right px-3 py-2">Credit</th>
        <th class="text-right px-3 py-2">Balance</th>
      </tr>
    </thead>
    <tbody>
      <?php if (!$rows): ?>
        <tr><td colspan="7" class="px-3 py-3 text-slate-500">No transactions.</td></tr>
      <?php else: foreach ($rows as $r): ?>
        <tr class="border-t border-slate-100 dark:border-gray-700">
          <td class="px-3 py-2"><?= h($r['date']??'') ?></td>
          <td class="px-3 py-2"><?= h(($r['provider']??'').' — '.($r['wallet_no']??'')) ?></td>
          <td class="px-3 py-2"><?= h($r['ref']??'') ?></td>
          <td class="px-3 py-2"><?= h($r['note']??'') ?></td>
          <td class="px-3 py-2 text-right"><?= number_format((float)($r['debit']??0),2) ?></td>
          <td class="px-3 py-2 text-right"><?= number_format((float)($r['credit']??0),2) ?></td>
          <td class="px-3 py-2 text-right"><?= number_format((float)($r['balance']??0),2) ?></td>
        </tr>
      <?php endforeach; endif; ?>
    </tbody>
  </table>
</div>