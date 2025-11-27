<?php
/** @var array $journal */
/** @var array $lines */
/** @var string $module_base */
$base  = $module_base ?? '/apps/dms';
$brand = '#0a936b';
$sumD = 0.0; $sumC = 0.0;
foreach ($lines as $ln) { $sumD += (float)$ln['debit']; $sumC += (float)$ln['credit']; }
?>
<div class="container mx-auto max-w-6xl px-4 py-6">
  <div class="flex items-center justify-between mb-4">
    <div>
      <h1 class="text-2xl font-semibold">Journal <?= htmlspecialchars($journal['jno']) ?></h1>
      <div class="text-sm text-gray-600 dark:text-gray-300">
        <span class="mr-4"><strong>Date:</strong> <?= htmlspecialchars($journal['jdate']) ?></span>
        <span class="mr-4"><strong>Type:</strong> <?= htmlspecialchars($journal['jtype']) ?></span>
        <?php if (!empty($journal['ref_table'])): ?>
          <span class="mr-4"><strong>Ref:</strong> <?= htmlspecialchars($journal['ref_table'].' #'.$journal['ref_id']) ?></span>
        <?php endif; ?>
      </div>
      <?php if (!empty($journal['memo'])): ?>
        <p class="mt-2 text-sm"><?= nl2br(htmlspecialchars($journal['memo'])) ?></p>
      <?php endif; ?>
    </div>
    <div class="flex gap-2">
      <a href="<?= htmlspecialchars($base.'/accounts/journals') ?>" class="px-3 py-1 rounded border"><i class="fa fa-arrow-left me-1"></i>Back</a>
      <button onclick="window.print()" class="px-3 py-1 rounded text-white" style="background:<?= $brand ?>;"><i class="fa fa-print me-1"></i>Print</button>
    </div>
  </div>

  <div class="overflow-auto rounded border">
    <table class="min-w-full text-sm">
      <thead class="bg-gray-50 dark:bg-gray-800">
        <tr>
          <th class="px-3 py-2 text-left">Account</th>
          <th class="px-3 py-2 text-left">Memo</th>
          <th class="px-3 py-2 text-right">Debit</th>
          <th class="px-3 py-2 text-right">Credit</th>
        </tr>
      </thead>
      <tbody>
      <?php if (empty($lines)): ?>
        <tr><td colspan="4" class="px-3 py-6 text-center text-gray-500">No lines.</td></tr>
      <?php else: foreach ($lines as $ln): ?>
        <tr class="border-t">
          <td class="px-3 py-2">
            <div class="font-medium"><?= htmlspecialchars($ln['account_code'].' — '.$ln['account_name']) ?></div>
          </td>
          <td class="px-3 py-2"><?= htmlspecialchars($ln['line_memo'] ?? '') ?></td>
          <td class="px-3 py-2 text-right"><?= number_format((float)$ln['debit'], 2) ?></td>
          <td class="px-3 py-2 text-right"><?= number_format((float)$ln['credit'], 2) ?></td>
        </tr>
      <?php endforeach; endif; ?>
      </tbody>
      <tfoot class="bg-gray-50 dark:bg-gray-900">
        <tr class="border-t">
          <th class="px-3 py-2 text-left" colspan="2">Total</th>
          <th class="px-3 py-2 text-right"><?= number_format($sumD, 2) ?></th>
          <th class="px-3 py-2 text-right"><?= number_format($sumC, 2) ?></th>
        </tr>
      </tfoot>
    </table>
  </div>
</div>