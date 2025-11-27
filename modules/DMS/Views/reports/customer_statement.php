<?php
/** @var array|null $customer */
/** @var string $date_from */
/** @var string $date_to */
/** @var float $opening_balance */
/** @var float $closing_balance */
/** @var array $rows */

$h = fn($v)=>htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
$fmt = fn($n)=>number_format((float)$n,2);
$isPrint = ($_GET['print'] ?? '') === '1';
?>

<div class="<?= $isPrint ? '' : 'max-w-5xl mx-auto' ?>">
  <div class="flex items-center justify-between mb-4">
    <h1 class="text-xl font-semibold">Customer Statement</h1>
    <?php if(!$isPrint): ?>
      <div class="flex gap-2">
        <a class="btn btn-ghost rounded px-3 py-2 border"
           href="?customer_id=<?= $h($_GET['customer_id']??'') ?>&from=<?= $h($date_from) ?>&to=<?= $h($date_to) ?>&print=1"
           target="_blank">Print</a>
        <button class="btn btn-ghost rounded px-3 py-2 border"
                onclick="navigator.clipboard.writeText(location.href);">Share Link</button>
      </div>
    <?php endif; ?>
  </div>

  <form method="get" class="grid md:grid-cols-4 gap-2 mb-4">
    <input type="hidden" name="customer_id" value="<?= $h($_GET['customer_id'] ?? '') ?>">
    <label class="text-sm">From
      <input type="date" name="from" value="<?= $h($date_from) ?>" class="kf-input w-full rounded border px-2 py-1">
    </label>
    <label class="text-sm">To
      <input type="date" name="to" value="<?= $h($date_to) ?>" class="kf-input w-full rounded border px-2 py-1">
    </label>
    <div class="md:col-span-2 flex items-end">
      <button class="btn btn-brand rounded px-3 py-2">Run</button>
    </div>
  </form>

  <div class="rounded border p-3 mb-3">
    <div class="text-sm"><span class="font-medium">Customer:</span>
      <?= $customer ? $h($customer['name']) : 'All/Unspecified' ?>
    </div>
    <div class="text-sm"><span class="font-medium">Period:</span> <?= $h($date_from) ?> → <?= $h($date_to) ?></div>
    <div class="text-sm mt-1"><span class="font-medium">Opening Balance:</span> <?= $fmt($opening_balance) ?></div>
  </div>

  <div class="overflow-x-auto">
    <table class="w-full text-sm border">
      <thead>
        <tr class="bg-gray-50">
          <th class="text-left p-2 border">Date</th>
          <th class="text-left p-2 border">Type</th>
          <th class="text-left p-2 border">Ref No</th>
          <th class="text-right p-2 border">Debit (Invoice)</th>
          <th class="text-right p-2 border">Credit (Receipt)</th>
          <th class="text-right p-2 border">Balance</th>
        </tr>
      </thead>
      <tbody>
        <tr>
          <td class="p-2 border"><?= $h($date_from) ?></td>
          <td class="p-2 border">Opening</td>
          <td class="p-2 border">—</td>
          <td class="p-2 border text-right"><?= $fmt(max(0,$opening_balance)) ?></td>
          <td class="p-2 border text-right"><?= $fmt(max(0,-$opening_balance)) ?></td>
          <td class="p-2 border text-right"><?= $fmt($opening_balance) ?></td>
        </tr>
        <?php foreach ($rows as $r): ?>
          <tr>
            <td class="p-2 border"><?= $h($r['txn_date']) ?></td>
            <td class="p-2 border"><?= $h(str_replace('_',' ', $r['kind'])) ?></td>
            <td class="p-2 border"><?= $h($r['ref_no'] ?? ('#'.$r['ref_id'])) ?></td>
            <td class="p-2 border text-right"><?= $fmt($r['debit']) ?></td>
            <td class="p-2 border text-right"><?= $fmt($r['credit']) ?></td>
            <td class="p-2 border text-right"><?= $fmt($r['balance']) ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
      <tfoot>
        <tr class="bg-gray-50 font-medium">
          <td colspan="5" class="p-2 border text-right">Closing Balance</td>
          <td class="p-2 border text-right"><?= $fmt($closing_balance) ?></td>
        </tr>
      </tfoot>
    </table>
  </div>
</div>