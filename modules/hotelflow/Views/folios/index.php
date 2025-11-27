<?php
/** @var array  $ctx */
/** @var string $module_base ?? */
/** @var array  $folios ?? */
/** @var array  $summary ?? */

$h     = fn($v)=>htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
$base  = rtrim((string)($module_base ?? ($ctx['module_base'] ?? '/apps/hotelflow')), '/');
$folios  = $folios  ?? [];
$summary = $summary ?? ['open' => 0, 'closed' => 0, 'balance' => 0.0];

$totalOpen   = (int)($summary['open'] ?? 0);
$totalClosed = (int)($summary['closed'] ?? 0);
$totalBal    = (float)($summary['balance'] ?? 0.0);

$badge = function (string $status): string {
    $s = strtolower($status);
    return match ($s) {
        'open', 'in_house', 'inhouse'   => 'bg-emerald-50 text-emerald-700 border-emerald-200',
        'pending', 'proforma'           => 'bg-amber-50 text-amber-800 border-amber-200',
        'closed'                        => 'bg-slate-100 text-slate-700 border-slate-300',
        default                         => 'bg-slate-50 text-slate-700 border-slate-200',
    };
};
$label = function (string $status): string {
    $s = strtolower($status);
    return match ($s) {
        'open'         => 'Open',
        'in_house',
        'inhouse'      => 'In-house',
        'pending'      => 'Pending',
        'proforma'     => 'Pro-forma',
        'closed'       => 'Closed',
        default        => ucfirst($s),
    };
};
?>
<div class="max-w-6xl mx-auto space-y-6">

  <!-- Header + tiny nav -->
  <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
    <div>
      <h1 class="text-2xl font-extrabold tracking-tight">Folios &amp; guest bills</h1>
      <p class="text-slate-500 text-sm">
        All guest folios in one 2035-style view — see balances, status and quick links to payments &amp; reservations.
      </p>
    </div>

    <div class="flex flex-wrap gap-2 text-sm">
      <a href="<?= $h($base) ?>/frontdesk"
         class="inline-flex items-center gap-2 px-3 py-1.5 rounded-lg border border-slate-300 bg-white hover:bg-slate-50">
        <i class="fa-solid fa-bell-concierge"></i>
        <span>Frontdesk</span>
      </a>
      <a href="<?= $h($base) ?>/reservations"
         class="inline-flex items-center gap-2 px-3 py-1.5 rounded-lg border border-slate-200 bg-slate-50 hover:bg-slate-100">
        <i class="fa-regular fa-calendar-check"></i>
        <span>Reservations</span>
      </a>
      <a href="<?= $h($base) ?>/payments"
         class="inline-flex items-center gap-2 px-3 py-1.5 rounded-lg border border-slate-200 bg-slate-50 hover:bg-slate-100">
        <i class="fa-solid fa-money-bill-transfer"></i>
        <span>Payments</span>
      </a>
    </div>
  </div>

  <!-- KPI strip -->
  <div class="grid gap-3 sm:grid-cols-3">
    <div class="rounded-2xl border border-emerald-200 bg-emerald-50/80 p-4">
      <div class="flex items-center justify-between">
        <span class="text-xs font-medium text-emerald-900">Open folios</span>
        <i class="fa-regular fa-folder-open text-emerald-700"></i>
      </div>
      <div class="mt-2 text-2xl font-extrabold text-emerald-900">
        <?= $totalOpen ?>
      </div>
      <p class="mt-1 text-[11px] text-emerald-900/80">
        Live folios still accepting charges and payments.
      </p>
    </div>

    <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
      <div class="flex items-center justify-between">
        <span class="text-xs font-medium text-slate-900">Closed folios</span>
        <i class="fa-solid fa-lock text-slate-600"></i>
      </div>
      <div class="mt-2 text-2xl font-extrabold text-slate-900">
        <?= $totalClosed ?>
      </div>
      <p class="mt-1 text-[11px] text-slate-600/80">
        Already settled and typically linked to posted revenue.
      </p>
    </div>

    <div class="rounded-2xl border border-amber-200 bg-amber-50 p-4">
      <div class="flex items-center justify-between">
        <span class="text-xs font-medium text-amber-900">Total outstanding</span>
        <i class="fa-solid fa-bangladeshi-taka-sign text-amber-700"></i>
      </div>
      <div class="mt-2 text-2xl font-extrabold text-amber-900">
        ৳<?= number_format($totalBal, 2) ?>
      </div>
      <p class="mt-1 text-[11px] text-amber-900/80">
        Sum of balances for all open folios (approx).
      </p>
    </div>
  </div>

  <!-- Filters -->
  <form method="get"
        class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm space-y-3">
    <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
      <div>
        <h2 class="text-sm font-semibold text-slate-900">Filter folios</h2>
        <p class="text-xs text-slate-500">
          Use this as your daily billing cockpit — arrivals, in-house and departures in one place.
        </p>
      </div>
      <div class="flex flex-wrap gap-2 text-[11px] text-slate-500">
        <span>Keyboard: <span class="font-mono bg-slate-100 px-1.5 py-0.5 rounded">F</span> for quick search (future).</span>
      </div>
    </div>

    <div class="grid gap-3 md:grid-cols-5">
      <div class="md:col-span-2">
        <label class="block text-xs font-medium text-slate-700 mb-1">Search</label>
        <input type="text"
               name="q"
               value="<?= $h($_GET['q'] ?? '') ?>"
               class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
               placeholder="Guest, folio code, reservation…">
      </div>
      <div>
        <label class="block text-xs font-medium text-slate-700 mb-1">Status</label>
        <select name="status"
                class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
          <option value="">All</option>
          <option value="open">Open</option>
          <option value="in_house">In-house</option>
          <option value="pending">Pending</option>
          <option value="closed">Closed</option>
        </select>
      </div>
      <div>
        <label class="block text-xs font-medium text-slate-700 mb-1">From</label>
        <input type="date"
               name="from"
               class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
      </div>
      <div>
        <label class="block text-xs font-medium text-slate-700 mb-1">To</label>
        <input type="date"
               name="to"
               class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
      </div>
    </div>

    <div class="flex flex-wrap items-center gap-3 pt-1">
      <button type="submit"
              class="inline-flex items-center gap-2 rounded-xl px-4 py-2 text-xs font-semibold text-white shadow-sm hover:shadow-md"
              style="background:var(--brand,#228B22);">
        <i class="fa-solid fa-filter"></i>
        <span>Apply filters</span>
      </button>
      <a href="<?= $h($base) ?>/folios"
         class="text-[11px] text-slate-500 hover:text-slate-700">
        Clear
      </a>
    </div>
  </form>

  <!-- Folios table -->
  <div class="rounded-2xl border border-slate-200 bg-white shadow-sm overflow-hidden">
    <div class="flex items-center justify-between px-4 py-3 border-b border-slate-100">
      <div>
        <h2 class="text-sm font-semibold text-slate-900">Folio list</h2>
        <p class="text-xs text-slate-500">
          Each row is a guest bill. Click “Open” to drill into details and manage charges/payments.
        </p>
      </div>
      <div class="flex items-center gap-2 text-[11px] text-slate-500">
        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-emerald-50 text-emerald-700 border border-emerald-200">
          <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span> Open
        </span>
        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-slate-100 text-slate-700 border border-slate-300">
          <span class="w-1.5 h-1.5 rounded-full bg-slate-500"></span> Closed
        </span>
      </div>
    </div>

    <div class="overflow-x-auto">
      <table class="min-w-full text-sm">
        <thead class="bg-slate-50 text-xs text-slate-500 uppercase tracking-wide">
          <tr>
            <th class="px-3 py-2 text-left">Folio</th>
            <th class="px-3 py-2 text-left">Guest</th>
            <th class="px-3 py-2 text-left">Reservation</th>
            <th class="px-3 py-2 text-left">Opened</th>
            <th class="px-3 py-2 text-left">Closed</th>
            <th class="px-3 py-2 text-right">Balance</th>
            <th class="px-3 py-2 text-left">Status</th>
            <th class="px-3 py-2 text-right">Actions</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
        <?php if (!$folios): ?>
          <tr>
            <td colspan="8" class="px-3 py-6 text-center text-slate-400 text-sm">
              No folios yet. Once reservations start generating guest bills, they will show up here.
            </td>
          </tr>
        <?php else: ?>
          <?php foreach ($folios as $f): ?>
            <?php
              $status  = (string)($f['status'] ?? 'open');
              $code    = (string)($f['code'] ?? '');
              $id      = (int)($f['id'] ?? 0);
              $balance = (float)($f['balance_due'] ?? 0);
            ?>
            <tr class="hover:bg-slate-50/70">
              <td class="px-3 py-2 text-xs text-slate-900 whitespace-nowrap">
                <div class="font-semibold">
                  <?= $h($code !== '' ? $code : ('Folio #'.$id)) ?>
                </div>
                <div class="text-[11px] text-slate-400">
                  #<?= $id ?>
                </div>
              </td>
              <td class="px-3 py-2 text-xs text-slate-700 whitespace-nowrap">
                <?= $h($f['guest_name'] ?? '—') ?>
              </td>
              <td class="px-3 py-2 text-xs text-slate-700 whitespace-nowrap">
                <?= $h($f['reservation_code'] ?? '—') ?>
              </td>
              <td class="px-3 py-2 text-xs text-slate-600 whitespace-nowrap">
                <?= $h($f['opened_at'] ?? '—') ?>
              </td>
              <td class="px-3 py-2 text-xs text-slate-600 whitespace-nowrap">
                <?= $h($f['closed_at'] ?? '—') ?>
              </td>
              <td class="px-3 py-2 text-xs text-right whitespace-nowrap">
                <span class="font-semibold text-slate-900">
                  ৳<?= number_format($balance, 2) ?>
                </span>
                <span class="text-[11px] text-slate-400 ml-1">
                  <?= $h($f['currency'] ?? 'BDT') ?>
                </span>
              </td>
              <td class="px-3 py-2 text-xs whitespace-nowrap">
                <span class="inline-flex items-center px-2 py-0.5 rounded-full border text-[11px] font-medium <?= $badge($status) ?>">
                  <?= $h($label($status)) ?>
                </span>
              </td>
              <td class="px-3 py-2 text-right whitespace-nowrap">
                <div class="inline-flex items-center gap-1">
                  <a href="<?= $h($base) ?>/folios/<?= $id ?>"
                     class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg border border-slate-300 text-[11px] hover:bg-slate-100">
                    <i class="fa-regular fa-eye"></i>
                    <span>Open</span>
                  </a>
                  <a href="<?= $h($base) ?>/payments/receive?folio_id=<?= $id ?>"
                     class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg border border-emerald-300 text-[11px] text-emerald-700 hover:bg-emerald-50">
                    <i class="fa-solid fa-bangladeshi-taka-sign"></i>
                    <span>Receive</span>
                  </a>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- How to use this page -->
  <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-xs text-slate-700 space-y-1">
    <div class="font-semibold text-slate-900 mb-1">How to use this Folios page</div>
    <ol class="list-decimal list-inside space-y-1">
      <li>Use the <strong>KPI tiles</strong> to see how many folios are open vs closed and the total outstanding in BDT.</li>
      <li>Filter by <strong>status, date range and search</strong> when you’re chasing payments or doing night audit.</li>
      <li>Click <strong>Open</strong> on any row to see full folio details, line items and payment history.</li>
      <li>Use <strong>Receive</strong> to jump directly into the payment screen for that folio.</li>
      <li>During night audit, focus on <strong>open folios with high balance</strong> to reduce risk before day close.</li>
    </ol>
    <p class="mt-1">
      Tip: Later we can add a “Danger zone” filter (big balances close to check-out date) for even smarter 2035 workflows.
    </p>
  </div>
</div>