<?php
/**
 * Balance Sheet — View (dark-mode ready)
 * Vars expected (with safe defaults):
 * $module_base (string)
 * $asOf (Y-m-d), $level (int), $showZero (bool/int)
 * $assets (array of ['code','name','amount'])
 * $liabs  (array of ['code','name','amount'])
 * $equity (array of ['code','name','amount'])
 * $totalAssets, $totalLiabs, $totalEquity, $finalBalance (decimals)
 */

$module_base  = $module_base  ?? '/apps/dms';
$asOf         = $asOf         ?? date('Y-m-d');
$level        = (int)($level  ?? 3);
$showZero     = (int)($showZero ?? 0);

$assets       = $assets ?? [];
$liabs        = $liabs  ?? [];
$equity       = $equity ?? [];

$fmt = fn($n) => number_format((float)$n, 2);

$totalAssets  = $totalAssets  ?? array_sum(array_map(fn($r)=> (float)($r['amount']??0), $assets));
$totalLiabs   = $totalLiabs   ?? array_sum(array_map(fn($r)=> (float)($r['amount']??0), $liabs));
$totalEquity  = $totalEquity  ?? array_sum(array_map(fn($r)=> (float)($r['amount']??0), $equity));
$finalBalance = $finalBalance ?? round($totalAssets - ($totalLiabs + $totalEquity), 2);

$isPrint      = isset($_GET['print']) && ($_GET['print'] === '1' || $_GET['print'] === 'true');
?>
<div class="px-4 sm:px-6 lg:px-8 py-6">
  <!-- Header / Filters -->
  <div class="mb-4 flex items-center justify-between gap-3 print:hidden">
    <h1 class="text-2xl font-semibold text-gray-900 dark:text-gray-100">Balance Sheet</h1>

    <form method="get" action="<?= htmlspecialchars($module_base) ?>/accounts/balance-sheet"
          class="flex items-center gap-2">
      <label class="text-sm text-gray-600 dark:text-gray-300">As of</label>
      <input type="date" name="asof" value="<?= htmlspecialchars($asOf) ?>"
             class="rounded-md border-gray-300 bg-white text-gray-900 dark:bg-gray-800 dark:text-gray-100 dark:border-gray-700
                    focus:ring-emerald-500 focus:border-emerald-500 text-sm px-2 py-1"/>

      <label class="text-sm text-gray-600 dark:text-gray-300">Level</label>
      <select name="level"
              class="rounded-md border-gray-300 bg-white text-gray-900 dark:bg-gray-800 dark:text-gray-100 dark:border-gray-700
                     focus:ring-emerald-500 focus:border-emerald-500 text-sm px-2 py-1">
        <?php for ($i=1; $i<=6; $i++): ?>
          <option value="<?= $i ?>" <?= $i===$level?'selected':'' ?>><?= $i ?></option>
        <?php endfor; ?>
      </select>

      <label class="inline-flex items-center gap-2 px-2 py-1 rounded-md text-sm text-gray-700 dark:text-gray-200">
        <input type="checkbox" name="zero" value="1" <?= $showZero ? 'checked':'' ?>
               class="rounded border-gray-300 text-emerald-600 focus:ring-emerald-500 dark:bg-gray-800 dark:border-gray-700">
        Show zero
      </label>

      <button type="submit"
              class="px-3 py-1.5 rounded-md bg-gray-900 text-white dark:bg-emerald-500 dark:hover:bg-emerald-600
                     hover:bg-gray-800 text-sm">
        Apply
      </button>

      <button type="button" id="btnPrint"
              class="px-3 py-1.5 rounded-md bg-emerald-600 text-white hover:bg-emerald-700 text-sm">
        Print
      </button>
    </form>
  </div>

  <!-- Card -->
  <div class="rounded-xl border border-gray-200 bg-white shadow-sm dark:bg-gray-900 dark:border-gray-800 print:border-0">
    <div class="p-4 sm:p-6">
      <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Assets -->
        <section>
          <h2 class="text-sm font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-3">Assets</h2>
          <div class="overflow-hidden rounded-lg border border-gray-200 dark:border-gray-800">
            <table class="min-w-full bg-white dark:bg-gray-900">
              <thead class="bg-gray-50 dark:bg-gray-800/60">
              <tr>
                <th class="px-4 py-2 text-left text-xs font-semibold text-gray-600 dark:text-gray-300">Account</th>
                <th class="px-4 py-2 text-right text-xs font-semibold text-gray-600 dark:text-gray-300">Amount</th>
              </tr>
              </thead>
              <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
              <?php if (empty($assets)): ?>
                <tr>
                  <td class="px-4 py-3 text-sm text-gray-500 dark:text-gray-400" colspan="2">No rows.</td>
                </tr>
              <?php else: ?>
                <?php foreach ($assets as $r):
                  $amt = (float)($r['amount'] ?? 0);
                  if (!$showZero && abs($amt) < 0.005) continue;
                  ?>
                  <tr>
                    <td class="px-4 py-2 text-sm text-gray-800 dark:text-gray-200">
                      <?= htmlspecialchars(($r['code'] ?? '').' '.($r['name'] ?? '')) ?>
                    </td>
                    <td class="px-4 py-2 text-sm text-right tabular-nums text-gray-900 dark:text-gray-100">
                      <?= $fmt($amt) ?>
                    </td>
                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
              </tbody>
              <tfoot class="bg-gray-50 dark:bg-gray-800/60">
              <tr>
                <th class="px-4 py-2 text-left text-xs font-semibold text-gray-700 dark:text-gray-200">Total Assets</th>
                <th class="px-4 py-2 text-right text-xs font-semibold text-gray-900 dark:text-gray-100">
                  <?= $fmt($totalAssets) ?>
                </th>
              </tr>
              </tfoot>
            </table>
          </div>
        </section>

        <!-- Liabilities & Equity -->
        <section>
          <h2 class="text-sm font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-3">
            Liabilities &amp; Equity
          </h2>
          <div class="overflow-hidden rounded-lg border border-gray-200 dark:border-gray-800">
            <table class="min-w-full bg-white dark:bg-gray-900">
              <thead class="bg-gray-50 dark:bg-gray-800/60">
              <tr>
                <th class="px-4 py-2 text-left text-xs font-semibold text-gray-600 dark:text-gray-300">Account</th>
                <th class="px-4 py-2 text-right text-xs font-semibold text-gray-600 dark:text-gray-300">Amount</th>
              </tr>
              </thead>
              <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
              <?php
              $rightSide = array_merge($liabs, $equity);
              if (empty($rightSide)): ?>
                <tr>
                  <td class="px-4 py-3 text-sm text-gray-500 dark:text-gray-400" colspan="2">No rows.</td>
                </tr>
              <?php else: ?>
                <?php foreach ($rightSide as $r):
                  $amt = (float)($r['amount'] ?? 0);
                  if (!$showZero && abs($amt) < 0.005) continue;
                  ?>
                  <tr>
                    <td class="px-4 py-2 text-sm text-gray-800 dark:text-gray-200">
                      <?= htmlspecialchars(($r['code'] ?? '').' '.($r['name'] ?? '')) ?>
                    </td>
                    <td class="px-4 py-2 text-sm text-right tabular-nums text-gray-900 dark:text-gray-100">
                      <?= $fmt($amt) ?>
                    </td>
                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
              </tbody>
              <tfoot class="bg-gray-50 dark:bg-gray-800/60">
              <tr>
                <th class="px-4 py-2 text-left text-xs font-semibold text-gray-700 dark:text-gray-200">
                  Total Liabilities &amp; Equity
                </th>
                <th class="px-4 py-2 text-right text-xs font-semibold text-gray-900 dark:text-gray-100">
                  <?= $fmt($totalLiabs + $totalEquity) ?>
                </th>
              </tr>
              </tfoot>
            </table>
          </div>
        </section>
      </div>

      <!-- Footer line -->
      <div class="mt-4 text-sm text-gray-700 dark:text-gray-300 flex items-center gap-4">
        <span>As of: <span class="font-medium text-gray-900 dark:text-gray-100"><?= htmlspecialchars($asOf) ?></span></span>
        <span>&bull;</span>
        <span>Balance: <span class="font-semibold tabular-nums <?= abs($finalBalance) < 0.005 ? 'text-emerald-600' : 'text-red-600' ?>">
          <?= $fmt($finalBalance) ?>
        </span></span>
      </div>
    </div>
  </div>
</div>

<!-- Print handling -->
<script>
  (function () {
    const btn = document.getElementById('btnPrint');
    if (btn) {
      btn.addEventListener('click', function () {
        const url = new URL(window.location.href);
        url.searchParams.set('print', '1');
        window.open(url.toString(), '_blank', 'noopener,noreferrer');
      });
    }
    <?php if ($isPrint): ?>
    // Auto fire print in the print view
    window.addEventListener('load', () => {
      setTimeout(() => window.print(), 100);
    });
    <?php endif; ?>
  })();
</script>

<style>
  /* Little helpers for dark print too */
  @media print {
    .print\:hidden { display: none !important; }
    html, body { background: #fff !important; color: #000 !important; }
    table { page-break-inside: auto; }
    tr    { page-break-inside: avoid; page-break-after: auto; }
  }
</style>