<?php
declare(strict_types=1);
/** @var array $rows @var string $asOf @var string $base */
$h    = fn($s)=>htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
$base = $base ?? '/apps/pos';
$asOf = $asOf ?? date('Y-m-d');
?>
<div class="max-w-6xl mx-auto px-6 py-6 space-y-6">

  <div class="flex items-center justify-between gap-3">
    <div>
      <h1 class="text-2xl md:text-3xl font-semibold text-gray-900 dark:text-gray-50">
        Trial Balance
      </h1>
      <p class="text-sm text-gray-500 dark:text-gray-400">
        As of <?= $h($asOf) ?>
      </p>
    </div>
    <a href="<?= $h($base) ?>/gl/journals"
       class="inline-flex items-center gap-2 px-3 py-2 rounded-lg border border-gray-200 dark:border-gray-700 text-sm text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-800">
      <i class="fa fa-book text-xs"></i>
      Journals
    </a>
  </div>

  <form method="get" class="flex items-center gap-3">
    <label class="text-xs text-gray-500 dark:text-gray-400">
      As of
      <input type="date" name="as_of" value="<?= $h($asOf) ?>"
             class="ml-2 px-3 py-2 rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 text-sm">
    </label>
    <button class="px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-700 bg-gray-50 dark:bg-gray-800 text-sm">
      Refresh
    </button>
  </form>

  <div class="border border-gray-200 dark:border-gray-800 rounded-2xl overflow-hidden bg-white dark:bg-gray-900">
    <table class="min-w-full text-sm">
      <thead class="bg-gray-50 dark:bg-gray-800 text-xs uppercase text-gray-500 dark:text-gray-400">
        <tr>
          <th class="px-3 py-2 text-left">Code</th>
          <th class="px-3 py-2 text-left">Account</th>
          <th class="px-3 py-2 text-left">Type</th>
          <th class="px-3 py-2 text-right">Debit</th>
          <th class="px-3 py-2 text-right">Credit</th>
        </tr>
      </thead>
      <tbody>
      <?php
      $sumDr = 0.0;
      $sumCr = 0.0;
      ?>
      <?php if (empty($rows)): ?>
        <tr>
          <td colspan="5" class="px-4 py-8 text-center text-gray-500 dark:text-gray-400">
            No GL data yet.
          </td>
        </tr>
      <?php else: foreach ($rows as $r):
          $dr = (float)($r['total_dr'] ?? 0);
          $cr = (float)($r['total_cr'] ?? 0);
          // Trial balance shows positive balances on correct side, but here we just show totals.
          $sumDr += $dr;
          $sumCr += $cr;
      ?>
        <tr class="border-t border-gray-100 dark:border-gray-800">
          <td class="px-3 py-2 whitespace-nowrap text-xs"><?= $h($r['code'] ?? '') ?></td>
          <td class="px-3 py-2"><?= $h($r['name'] ?? '') ?></td>
          <td class="px-3 py-2 text-xs text-gray-500 dark:text-gray-400"><?= $h($r['type'] ?? '') ?></td>
          <td class="px-3 py-2 text-right whitespace-nowrap"><?= number_format($dr, 2) ?></td>
          <td class="px-3 py-2 text-right whitespace-nowrap"><?= number_format($cr, 2) ?></td>
        </tr>
      <?php endforeach; endif; ?>
      </tbody>
      <tfoot class="bg-gray-50 dark:bg-gray-800 text-sm">
        <tr class="border-t border-gray-200 dark:border-gray-700 font-semibold">
          <td colspan="3" class="px-3 py-2 text-right">Total</td>
          <td class="px-3 py-2 text-right"><?= number_format($sumDr, 2) ?></td>
          <td class="px-3 py-2 text-right"><?= number_format($sumCr, 2) ?></td>
        </tr>
      </tfoot>
    </table>
  </div>

</div>