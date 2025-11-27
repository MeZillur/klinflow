<?php
/** @var array  $ctx */
/** @var string $title */
/** @var array  $metrics */
/** @var array  $recent */
/** @var string $today */
/** @var string $monthStart */

$h    = fn($v)=>htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
$base = rtrim((string)($ctx['module_base'] ?? '/apps/hotelflow'), '/');

$m = array_merge([
    'today_revenue'   => 0,
    'month_revenue'   => 0,
    'open_folios'     => 0,
    'overdue_balance' => 0,
    'payments_today'  => 0,
    'refunds_today'   => 0,
], $metrics ?? []);

$recentPayments = $recent['payments'] ?? [];
$recentFolios   = $recent['folios'] ?? [];
?>
<div class="max-w-6xl mx-auto space-y-6">

  <!-- Header + tiny nav -->
  <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
    <div>
      <p class="text-[11px] text-slate-500 mb-1">
        HotelFlow · Financial workspace
      </p>
      <h1 class="text-2xl font-extrabold tracking-tight flex items-center gap-2">
        Accounting dashboard
        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-emerald-50 text-emerald-800 text-[11px] border border-emerald-100">
          <i class="fa-regular fa-gauge-high"></i>
          <span>Live snapshot</span>
        </span>
      </h1>
      <p class="text-slate-500 text-sm mt-1">
        One place for revenue, folios and payments — designed for frontdesk, finance and owners to share the same truth.
      </p>
    </div>

    <div class="flex flex-wrap gap-2 text-sm">
      <a href="<?= $h($base) ?>/frontdesk"
         class="inline-flex items-center gap-2 px-3 py-1.5 rounded-lg border border-slate-300 bg-white hover:bg-slate-50">
        <i class="fa-solid fa-bell-concierge"></i>
        <span>Frontdesk</span>
      </a>
      <a href="<?= $h($base) ?>/folios"
         class="inline-flex items-center gap-2 px-3 py-1.5 rounded-lg border border-slate-200 bg-slate-50 hover:bg-slate-100">
        <i class="fa-regular fa-file-invoice"></i>
        <span>Folios</span>
      </a>
      <a href="<?= $h($base) ?>/payments"
         class="inline-flex items-center gap-2 px-3 py-1.5 rounded-lg border border-slate-200 bg-slate-50 hover:bg-slate-100">
        <i class="fa-solid fa-money-bill-transfer"></i>
        <span>Payments</span>
      </a>
    </div>
  </div>

  <!-- KPI row -->
  <section class="grid gap-4 md:grid-cols-4">
    <!-- Today revenue -->
    <div class="rounded-2xl border border-emerald-200 bg-emerald-50/60 p-4 flex flex-col justify-between">
      <div class="flex items-center justify-between">
        <div class="text-xs font-semibold text-emerald-900 uppercase tracking-wide">
          Revenue today
        </div>
        <i class="fa-solid fa-bangladeshi-taka-sign text-emerald-700"></i>
      </div>
      <div class="mt-2">
        <div class="text-xl font-extrabold text-emerald-900">
          <?= number_format((float)$m['today_revenue'], 2) ?> <span class="text-sm font-semibold">BDT</span>
        </div>
        <p class="text-[11px] text-emerald-900/80 mt-1">
          Updated from closed folios / charges on <?= $h($today) ?>.
        </p>
      </div>
    </div>

    <!-- Month revenue -->
    <div class="rounded-2xl border border-slate-200 bg-white p-4 flex flex-col justify-between">
      <div class="flex items-center justify-between">
        <div class="text-xs font-semibold text-slate-900 uppercase tracking-wide">
          Month to date
        </div>
        <i class="fa-regular fa-calendar-check text-slate-500"></i>
      </div>
      <div class="mt-2">
        <div class="text-xl font-extrabold text-slate-900">
          <?= number_format((float)$m['month_revenue'], 2) ?> <span class="text-sm font-semibold">BDT</span>
        </div>
        <p class="text-[11px] text-slate-500 mt-1">
          From <?= $h($monthStart) ?> to <?= $h($today) ?> (system time).
        </p>
      </div>
    </div>

    <!-- Open folios -->
    <div class="rounded-2xl border border-slate-200 bg-white p-4 flex flex-col justify-between">
      <div class="flex items-center justify-between">
        <div class="text-xs font-semibold text-slate-900 uppercase tracking-wide">
          Open folios
        </div>
        <i class="fa-regular fa-folder-open text-slate-500"></i>
      </div>
      <div class="mt-2">
        <div class="text-xl font-extrabold text-slate-900">
          <?= (int)$m['open_folios'] ?>
        </div>
        <p class="text-[11px] text-slate-500 mt-1">
          In-house / pending folios not yet closed.
        </p>
      </div>
    </div>

    <!-- Outstanding -->
    <div class="rounded-2xl border border-rose-200 bg-rose-50/70 p-4 flex flex-col justify-between">
      <div class="flex items-center justify-between">
        <div class="text-xs font-semibold text-rose-900 uppercase tracking-wide">
          Outstanding balance
        </div>
        <i class="fa-regular fa-triangle-exclamation text-rose-600"></i>
      </div>
      <div class="mt-2">
        <div class="text-xl font-extrabold text-rose-900">
          <?= number_format((float)$m['overdue_balance'], 2) ?> <span class="text-sm font-semibold">BDT</span>
        </div>
        <p class="text-[11px] text-rose-900/80 mt-1">
          Total balance across open folios (subject to posting).
        </p>
      </div>
    </div>
  </section>

  <!-- Flow cards: collections vs refunds -->
  <section class="grid gap-4 md:grid-cols-2">
    <div class="rounded-2xl border border-emerald-200 bg-emerald-50/70 p-4">
      <div class="flex items-center justify-between">
        <div class="text-xs font-semibold text-emerald-900 uppercase tracking-wide">
          Collections today
        </div>
        <i class="fa-solid fa-circle-down text-emerald-700"></i>
      </div>
      <div class="mt-2 flex items-baseline gap-2">
        <div class="text-2xl font-extrabold text-emerald-900">
          <?= number_format((float)$m['payments_today'], 2) ?> <span class="text-sm font-semibold">BDT</span>
        </div>
        <span class="text-[11px] text-emerald-900/80">
          All payment methods
        </span>
      </div>
      <p class="text-[11px] text-emerald-900/80 mt-1">
        Use the <strong>Payments</strong> screen to drill down by method (Cash, Card, MFS, Bank).
      </p>
    </div>

    <div class="rounded-2xl border border-slate-200 bg-white p-4">
      <div class="flex items-center justify-between">
        <div class="text-xs font-semibold text-slate-900 uppercase tracking-wide">
          Refunds today
        </div>
        <i class="fa-regular fa-rotate-left text-slate-500"></i>
      </div>
      <div class="mt-2 flex items-baseline gap-2">
        <div class="text-2xl font-extrabold text-slate-900">
          <?= number_format((float)$m['refunds_today'], 2) ?> <span class="text-sm font-semibold">BDT</span>
        </div>
        <span class="text-[11px] text-slate-500">
          Negative payments posted today
        </span>
      </div>
      <p class="text-[11px] text-slate-600 mt-1">
        Keep this as low as possible — spikes may indicate rate / package issues or guest disputes.
      </p>
    </div>
  </section>

  <!-- Two-column: recent payments + folios -->
  <section class="grid gap-4 lg:grid-cols-[1.2fr,1fr]">

    <!-- Recent payments -->
    <div class="rounded-2xl border border-slate-200 bg-white p-4">
      <div class="flex items-center justify-between mb-3">
        <div>
          <h2 class="text-sm font-semibold text-slate-900">Recent payments</h2>
          <p class="text-[11px] text-slate-500">
            Last few payments posted to guest folios.
          </p>
        </div>
        <a href="<?= $h($base) ?>/payments"
           class="inline-flex items-center gap-1 text-[11px] text-emerald-700 hover:text-emerald-800">
          <span>View all</span>
          <i class="fa-solid fa-chevron-right text-[9px]"></i>
        </a>
      </div>

      <?php if ($recentPayments): ?>
        <div class="overflow-x-auto -mx-2">
          <table class="min-w-full text-xs">
            <thead>
              <tr class="text-left text-slate-500 border-b border-slate-100">
                <th class="px-2 py-1.5 font-medium">When</th>
                <th class="px-2 py-1.5 font-medium">Folio</th>
                <th class="px-2 py-1.5 font-medium">Method</th>
                <th class="px-2 py-1.5 font-medium text-right">Amount (BDT)</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
              <?php foreach ($recentPayments as $p): ?>
                <?php
                  $amt = (float)($p['amount'] ?? 0);
                  $isRefund = $amt < 0;
                ?>
                <tr class="hover:bg-slate-50/60">
                  <td class="px-2 py-1.5 whitespace-nowrap text-slate-600">
                    <?= $h(substr((string)($p['paid_at'] ?? ''), 0, 16)) ?>
                  </td>
                  <td class="px-2 py-1.5 whitespace-nowrap text-slate-700">
                    <?php if (!empty($p['folio_id'])): ?>
                      <a href="<?= $h($base) ?>/folios/<?= (int)$p['folio_id'] ?>"
                         class="text-emerald-700 hover:underline">
                        Folio #<?= (int)$p['folio_id'] ?>
                      </a>
                    <?php else: ?>
                      <span class="text-slate-400">—</span>
                    <?php endif; ?>
                  </td>
                  <td class="px-2 py-1.5 whitespace-nowrap text-slate-600">
                    <?= $h($p['method'] ?? '—') ?>
                  </td>
                  <td class="px-2 py-1.5 whitespace-nowrap text-right font-semibold <?= $isRefund ? 'text-rose-600' : 'text-emerald-700' ?>">
                    <?= number_format(abs($amt), 2) ?>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php else: ?>
        <p class="text-[11px] text-slate-500">
          No payment data available yet. Once you start posting payments, they will appear here.
        </p>
      <?php endif; ?>
    </div>

    <!-- Recent folios -->
    <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
      <div class="flex items-center justify-between mb-3">
        <div>
          <h2 class="text-sm font-semibold text-slate-900">Recent folios</h2>
          <p class="text-[11px] text-slate-500">
            Quick view of open / recently updated folios.
          </p>
        </div>
        <a href="<?= $h($base) ?>/folios"
           class="inline-flex items-center gap-1 text-[11px] text-slate-700 hover:text-slate-900">
          <span>Folios</span>
          <i class="fa-solid fa-chevron-right text-[9px]"></i>
        </a>
      </div>

      <?php if ($recentFolios): ?>
        <ul class="space-y-2 text-[11px]">
          <?php foreach ($recentFolios as $f): ?>
            <li class="rounded-xl bg-white border border-slate-200 px-3 py-2 flex items-center justify-between">
              <div class="space-y-0.5">
                <a href="<?= $h($base) ?>/folios/<?= (int)$f['id'] ?>"
                   class="font-semibold text-slate-900 hover:text-emerald-700">
                  <?= $h($f['folio_no'] ?? ('Folio #'.$f['id'])) ?>
                </a>
                <p class="text-slate-500">
                  <?= $h($f['guest_name'] ?? 'Guest') ?>
                  <?php if (!empty($f['room_no'])): ?>
                    · Room <?= $h($f['room_no']) ?>
                  <?php endif; ?>
                </p>
                <p class="text-slate-400">
                  Updated <?= $h(substr((string)($f['updated_at'] ?? ''), 0, 16)) ?>
                </p>
              </div>
              <div class="text-right">
                <div class="text-xs font-semibold <?= ((float)($f['balance_due'] ?? 0) > 0) ? 'text-rose-600' : 'text-emerald-700' ?>">
                  <?= number_format((float)($f['balance_due'] ?? 0), 2) ?> BDT
                </div>
                <div class="mt-0.5">
                  <?php $status = (string)($f['status'] ?? ''); ?>
                  <span class="inline-flex items-center px-2 py-0.5 rounded-full border text-[10px] font-medium
                    <?php
                      echo match ($status) {
                        'open', 'in_house'   => 'bg-amber-50 text-amber-800 border-amber-200',
                        'closed', 'settled'  => 'bg-emerald-50 text-emerald-800 border-emerald-200',
                        'pending'            => 'bg-slate-50 text-slate-700 border-slate-200',
                        default              => 'bg-slate-50 text-slate-500 border-slate-200',
                      };
                    ?>
                  ">
                    <?= $h(ucwords(str_replace('_', ' ', $status ?: 'open'))) ?>
                  </span>
                </div>
              </div>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php else: ?>
        <p class="text-[11px] text-slate-500">
          No folio information yet. Once reservations are checked-in and charges posted, folios will show here.
        </p>
      <?php endif; ?>
    </div>
  </section>

  <!-- How to use this page -->
  <section class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-[11px] text-slate-700 space-y-1">
    <div class="font-semibold text-slate-900 mb-1">How to use this Accounting dashboard</div>
    <ol class="list-decimal list-inside space-y-1">
      <li>
        Use the <strong>KPI cards</strong> at the top to quickly see today’s revenue, month-to-date totals,
        open folios and outstanding balance.
      </li>
      <li>
        Watch the <strong>Collections today</strong> vs <strong>Refunds today</strong> cards to catch unusual
        spikes in refunds or missing payments.
      </li>
      <li>
        Scroll through <strong>Recent payments</strong> to verify that large payments have been posted correctly
        and are linked to the right folios.
      </li>
      <li>
        Check <strong>Recent folios</strong> for any big balances that are still open before night audit completes.
      </li>
      <li>
        Use the tiny menu buttons (Frontdesk, Folios, Payments) to jump directly into detailed screens for deeper investigation.
      </li>
    </ol>
    <p class="mt-1">
      Tip: Later we can add separate tabs for <strong>GL export, tax breakdown and owner reports</strong>
      so this same URL becomes your full 2035 hotel accounting hub.
    </p>
  </section>
</div>