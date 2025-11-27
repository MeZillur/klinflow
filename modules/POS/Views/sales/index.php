<?php
declare(strict_types=1);
/**
 * modules/POS/Views/sales/index.php
 *
 * Sales Dashboard / Index
 *
 * Expects:
 * - $rows : each row has
 *     id, no, customer, total, status, dt
 *   (as selected in SalesController::index)
 * - $q    : search term
 * - $base : module base (e.g. /t/{slug}/apps/pos)
 */

$h    = fn($s)=>htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
$bdt  = fn($n)=>'৳'.number_format((float)$n, 2);

// Quick metrics from the loaded rows (last 200 as per controller)
$totalSales = count($rows);
$revenue    = array_sum(array_map(fn($r)=>(float)($r['total'] ?? 0), $rows));

$pendingStatuses = ['draft','issued','unpaid','pending'];
$refundStatuses  = ['refunded','returned','void','voided','cancelled','credit'];

$pending = 0;
$refunds = 0;
foreach ($rows as $r) {
    $st = strtolower((string)($r['status'] ?? 'posted'));
    if (in_array($st, $pendingStatuses, true)) $pending++;
    if (in_array($st, $refundStatuses, true))  $refunds++;
}
?>
<div class="px-6 py-6 space-y-8 max-w-7xl mx-auto">

  <!-- Header -->
  <div class="flex flex-wrap items-center justify-between gap-4">
    <div>
      <h1 class="text-3xl font-semibold text-gray-900 dark:text-gray-100">Sales Dashboard</h1>
      <p class="text-sm text-gray-500 dark:text-gray-400">
        Monitor and manage your point-of-sale transactions.
      </p>
    </div>

    <!-- Right-aligned menu -->
    <nav class="flex flex-wrap gap-2 justify-end text-sm">
      <?php
      $menu = [
        'All Sales'   => $base.'/sales',
        'Held'        => $base.'/sales/hold',
        'Resume'      => $base.'/sales/resume',
        'Refunds'     => $base.'/sales/refunds',
        'Reports'     => $base.'/reports',
        'Register'    => $base.'/sales/register',
      ];
      $uri = $_SERVER['REQUEST_URI'] ?? '';
      foreach ($menu as $label => $url):
        $active = str_contains($uri, basename($url));
      ?>
        <a href="<?= $h($url) ?>"
           class="px-4 py-2 rounded-md font-medium transition-colors duration-150 <?= $active
              ? 'text-white shadow'
              : 'hover:bg-green-50 dark:hover:bg-green-900/30 text-gray-700 dark:text-gray-300' ?>"
           style="<?= $active ? 'background:#228B22' : 'border:1px solid #228B22' ?>">
          <?= $h($label) ?>
        </a>
      <?php endforeach; ?>
    </nav>
  </div>

  <!-- Metrics grid -->
  <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
    <div class="rounded-xl p-4 text-white shadow" style="background:#228B22">
      <div class="text-lg font-semibold">Total Sales</div>
      <div class="text-3xl font-bold mt-2"><?= number_format($totalSales) ?></div>
    </div>
    <div class="rounded-xl p-4 bg-green-700 text-white shadow">
      <div class="text-lg font-semibold">Revenue</div>
      <div class="text-3xl font-bold mt-2"><?= $bdt($revenue) ?></div>
    </div>
    <div class="rounded-xl p-4 bg-yellow-600 text-white shadow">
      <div class="text-lg font-semibold">Pending</div>
      <div class="text-3xl font-bold mt-2"><?= number_format($pending) ?></div>
    </div>
    <div class="rounded-xl p-4 bg-red-600 text-white shadow">
      <div class="text-lg font-semibold">Refunds</div>
      <div class="text-3xl font-bold mt-2"><?= number_format($refunds) ?></div>
    </div>
  </div>

  <!-- Lookup (invoice / customer) -->
  <form method="get"
        class="flex flex-wrap items-end gap-3 bg-white dark:bg-gray-900 p-4 rounded-xl shadow"
        style="border:1px solid #228B22">
    <div class="flex-1 min-w-[260px]">
      <label class="text-sm text-gray-600 dark:text-gray-400">
        Lookup by invoice or customer
      </label>
      <input
        type="text"
        name="q"
        value="<?= $h($q ?? '') ?>"
        class="w-full rounded-lg dark:bg-gray-800 dark:text-gray-100"
        style="border:1px solid #c6e6c6;"
        placeholder="Type invoice number or customer name…"
        onfocus="this.style.boxShadow='0 0 0 3px rgba(34,139,34,.2)'; this.style.borderColor='#228B22'"
        onblur="this.style.boxShadow='none'; this.style.borderColor='#c6e6c6'">
      <p class="mt-1 text-[11px] text-gray-500">
        Press Enter to search. Matching by invoice number or customer name.
      </p>
    </div>

    <button type="submit"
            class="inline-flex items-center gap-2 px-4 py-2 text-sm font-semibold text-white rounded-lg shadow"
            style="background:#228B22">
      <i class="fa fa-search"></i> Lookup
    </button>
  </form>

  <!-- Sales table -->
  <div class="overflow-x-auto rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 shadow">
    <table class="min-w-full text-sm text-left">
      <thead class="bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-300">
        <tr>
          <th class="px-4 py-3 font-semibold">Invoice</th>
          <th class="px-4 py-3 font-semibold">Customer</th>
          <th class="px-4 py-3 font-semibold text-right">Total</th>
          <th class="px-4 py-3 font-semibold text-center">Status</th>
          <th class="px-4 py-3 font-semibold">Date</th>
          <th class="px-4 py-3 font-semibold text-right">Actions</th>
        </tr>
      </thead>
      <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
      <?php if ($rows): ?>
        <?php foreach ($rows as $r):
          $id     = (int)($r['id'] ?? 0);
          $no     = (string)($r['no'] ?? '—');
          $cust   = (string)($r['customer'] ?? '—');
          $total  = (float)($r['total'] ?? 0);
          $status = strtolower((string)($r['status'] ?? 'posted'));
          $dtRaw  = (string)($r['dt'] ?? '');
          $dtShow = $dtRaw ? date('d M Y', strtotime($dtRaw)) : '—';

          $pillClass = match (true) {
            in_array($status, $refundStatuses, true)
              => 'bg-red-100 text-red-700 dark:bg-red-900/50 dark:text-red-300',
            in_array($status, $pendingStatuses, true)
              => 'bg-yellow-100 text-yellow-700 dark:bg-yellow-900/50 dark:text-yellow-300',
            default
              => 'bg-green-100 text-green-700 dark:bg-green-900/50 dark:text-green-300',
          };
        ?>
        <tr class="hover:bg-gray-50 dark:hover:bg-gray-800 transition-colors">
          <td class="px-4 py-2 font-mono"><?= $h($no) ?></td>
          <td class="px-4 py-2"><?= $h($cust) ?></td>
          <td class="px-4 py-2 text-right"><?= $bdt($total) ?></td>
          <td class="px-4 py-2 text-center">
            <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full <?= $pillClass ?>">
              <?= strtoupper($status) ?>
            </span>
          </td>
          <td class="px-4 py-2 whitespace-nowrap"><?= $h($dtShow) ?></td>
          <td class="px-4 py-2 text-right">
            <?php if ($id > 0): ?>
              <a href="<?= $h($base) ?>/sales/<?= $id ?>"
                 class="text-green-700 dark:text-green-300 hover:underline">
                View
              </a>
            <?php else: ?>
              <span class="text-gray-400">—</span>
            <?php endif; ?>
          </td>
        </tr>
        <?php endforeach; ?>
      <?php else: ?>
        <tr>
          <td colspan="6" class="px-4 py-8 text-center text-gray-500">
            No sales found.
            <a href="<?= $h($base) ?>/sales/register"
               style="color:#228B22" class="underline">
              Open register
            </a>
            to create a new sale.
          </td>
        </tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>