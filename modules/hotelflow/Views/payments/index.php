<?php
/** @var array  $ctx */
/** @var string $module_base */
/** @var array  $rows      List of payments (optional) */
/** @var array  $stats     Summary numbers (optional) */
/** @var string $today     Default date (optional) */

$h    = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
$base = rtrim((string)($module_base ?? '/apps/hotelflow'), '/');

$today = $today ?: date('Y-m-d');

$stats = $stats ?? [
    'today_total'   => 0,
    'month_total'   => 0,
    'refunds_today' => 0,
    'pending_total' => 0,
];

$rows = $rows ?? [];
?>
<div class="max-w-6xl mx-auto space-y-6">

  <!-- Header + tiny nav -->
  <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
    <div>
      <h1 class="text-2xl font-extrabold tracking-tight">Payments</h1>
      <p class="text-slate-500 text-sm">
        Monitor today’s collections, refunds, and pending amounts across all reservations.
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
      <a href="<?= $h($base) ?>/payments/receive"
         class="inline-flex items-center gap-2 px-3 py-1.5 rounded-lg text-xs font-semibold text-white shadow-sm hover:shadow-md"
         style="background:var(--brand,#228B22);">
        <i class="fa-solid fa-arrow-down-short-wide"></i>
        <span>Receive payment</span>
      </a>
    </div>
  </div>

  <!-- SUMMARY + QUICK FILTERS (HORIZONTAL LAYOUT) -->
  <section class="grid gap-4 lg:grid-cols-[2fr,1.6fr]">
    <!-- Summary cards -->
    <div class="grid gap-3 sm:grid-cols-2">
      <div class="rounded-2xl border border-emerald-100 bg-emerald-50/70 p-4 shadow-sm">
        <div class="flex items-center justify-between">
          <span class="text-xs font-semibold uppercase text-emerald-800 tracking-wide">
            Collected today
          </span>
          <span class="inline-flex items-center rounded-full bg-white/80 px-2 py-0.5 text-[10px] font-medium text-emerald-700 border border-emerald-100">
            <?= $h($today) ?>
          </span>
        </div>
        <div class="mt-3 text-2xl font-extrabold text-emerald-900">
          ৳<?= number_format((float)$stats['today_total'], 2) ?>
        </div>
        <p class="mt-1 text-[11px] text-emerald-900/70">
          Includes cash, card and digital payments posted for arrivals, in-house and departures.
        </p>
      </div>

      <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
        <div class="flex items-center justify-between">
          <span class="text-xs font-semibold uppercase text-slate-800 tracking-wide">
            This month
          </span>
          <i class="fa-solid fa-calendar-days text-slate-400 text-sm"></i>
        </div>
        <div class="mt-3 text-xl font-bold text-slate-900">
          ৳<?= number_format((float)$stats['month_total'], 2) ?>
        </div>
        <dl class="mt-3 grid grid-cols-2 gap-2 text-[11px] text-slate-600">
          <div>
            <dt class="uppercase tracking-wide text-[10px]">Refunds today</dt>
            <dd class="font-semibold text-amber-700">
              ৳<?= number_format((float)$stats['refunds_today'], 2) ?>
            </dd>
          </div>
          <div>
            <dt class="uppercase tracking-wide text-[10px]">Pending to post</dt>
            <dd class="font-semibold text-slate-900">
              ৳<?= number_format((float)$stats['pending_total'], 2) ?>
            </dd>
          </div>
        </dl>
      </div>
    </div>

    <!-- Filters + quick post CTA -->
    <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm space-y-3">
      <div class="flex items-center justify-between gap-2">
        <div>
          <h2 class="text-xs font-semibold text-slate-900 uppercase tracking-wide">
            Filter payments
          </h2>
          <p class="text-[11px] text-slate-500">
            See payments by date, method and status. Great for day closure.
          </p>
        </div>
        <span class="text-[11px] text-slate-400">
          Shortcut: <kbd class="px-1 rounded bg-slate-100 border border-slate-200 text-[10px]">P</kbd> to focus search
        </span>
      </div>

      <div class="grid gap-2 sm:grid-cols-2">
        <div class="grid grid-cols-2 gap-2">
          <div>
            <label class="block text-[11px] font-medium text-slate-700 mb-1">From</label>
            <input type="date" name="from"
                   class="w-full rounded-lg border border-slate-300 px-2.5 py-1.5 text-xs"
                   value="<?= $h($today) ?>">
          </div>
          <div>
            <label class="block text-[11px] font-medium text-slate-700 mb-1">To</label>
            <input type="date" name="to"
                   class="w-full rounded-lg border border-slate-300 px-2.5 py-1.5 text-xs"
                   value="<?= $h($today) ?>">
          </div>
        </div>
        <div class="grid grid-cols-2 gap-2">
          <div>
            <label class="block text-[11px] font-medium text-slate-700 mb-1">Method</label>
            <select name="method"
                    class="w-full rounded-lg border border-slate-300 px-2.5 py-1.5 text-xs">
              <option value="">All</option>
              <option value="cash">Cash</option>
              <option value="card">Card</option>
              <option value="mobile_banking">Mobile banking</option>
              <option value="bank_transfer">Bank transfer</option>
            </select>
          </div>
          <div>
            <label class="block text-[11px] font-medium text-slate-700 mb-1">Status</label>
            <select name="status"
                    class="w-full rounded-lg border border-slate-300 px-2.5 py-1.5 text-xs">
              <option value="">All</option>
              <option value="posted">Posted</option>
              <option value="pending">Pending</option>
              <option value="refunded">Refunded</option>
            </select>
          </div>
        </div>
      </div>

      <div class="flex items-center justify-between gap-2 pt-1">
        <div class="flex items-center gap-2 text-[11px] text-slate-500">
          <i class="fa-solid fa-magnifying-glass text-slate-400"></i>
          <input type="search"
                 name="q"
                 placeholder="Reservation ID, guest name, reference..."
                 class="w-40 sm:w-56 rounded-lg border border-slate-300 px-2.5 py-1.5 text-xs">
        </div>

        <div class="flex gap-2">
          <button type="button"
                  class="inline-flex items-center gap-1 rounded-lg px-3 py-1.5 text-xs font-semibold text-slate-700 border border-slate-300 hover:bg-slate-50">
            <i class="fa-solid fa-rotate-right"></i>
            <span>Reset</span>
          </button>
          <button type="button"
                  class="inline-flex items-center gap-1 rounded-lg px-3 py-1.5 text-xs font-semibold text-white shadow-sm hover:shadow-md"
                  style="background:var(--brand)">
            <i class="fa-solid fa-filter"></i>
            <span>Apply</span>
          </button>
        </div>
      </div>

      <div class="mt-2 border-t border-slate-100 pt-3 flex items-start justify-between gap-3">
        <div class="text-[11px] text-slate-500">
          <span class="font-semibold text-slate-700">Quick post</span>
          <span class="mx-1">·</span>
          <span>Use this from desk to add a one-off payment.</span>
        </div>
        <a href="<?= $h($base) ?>/folio"
           class="inline-flex items-center gap-1 rounded-lg px-2.5 py-1.5 text-[11px] font-semibold text-emerald-800 bg-emerald-50 hover:bg-emerald-100 border border-emerald-100">
          <i class="fa-solid fa-circle-plus"></i>
          <span>Open folio screen</span>
        </a>
      </div>
    </div>
  </section>

  <!-- MAIN TABLE: PAYMENTS LIST -->
  <section class="rounded-2xl border border-slate-200 bg-white shadow-sm overflow-hidden">
    <div class="flex items-center justify-between px-4 py-3 border-b border-slate-100">
      <div class="flex items-center gap-2">
        <span class="text-sm font-semibold text-slate-900">Payments list</span>
        <span class="text-xs text-slate-500">
          <?= count($rows) ?> record<?= count($rows) === 1 ? '' : 's' ?>
        </span>
      </div>
      <div class="flex items-center gap-3 text-[11px] text-slate-400">
        <span class="flex items-center gap-1">
          <span class="w-2 h-2 rounded-full bg-emerald-500"></span> Posted
        </span>
        <span class="flex items-center gap-1">
          <span class="w-2 h-2 rounded-full bg-amber-400"></span> Pending
        </span>
        <span class="flex items-center gap-1">
          <span class="w-2 h-2 rounded-full bg-rose-500"></span> Refunded
        </span>
      </div>
    </div>

    <div class="overflow-x-auto">
      <table class="min-w-full text-xs text-slate-700">
        <thead class="bg-slate-50 text-[11px] uppercase tracking-wide border-b border-slate-100">
          <tr>
            <th class="px-4 py-2 text-left">Date &amp; time</th>
            <th class="px-4 py-2 text-left">Reservation / Room</th>
            <th class="px-4 py-2 text-left">Guest</th>
            <th class="px-4 py-2 text-left">Method</th>
            <th class="px-4 py-2 text-right">Amount (BDT)</th>
            <th class="px-4 py-2 text-left">Reference</th>
            <th class="px-4 py-2 text-left">Status</th>
            <th class="px-4 py-2 text-right">Actions</th>
          </tr>
        </thead>
        <tbody>
        <?php if (!$rows): ?>
          <tr>
            <td colspan="8" class="px-4 py-6 text-center text-xs text-slate-500">
              No payments found for the selected filters. Once you post payments from
              <strong>Front desk</strong> or <strong>Folio &amp; billing</strong>,
              they will appear here.
            </td>
          </tr>
        <?php else: ?>
          <?php foreach ($rows as $row): ?>
            <?php
              $status = (string)($row['status'] ?? 'posted');
              $statusLabel = ucfirst(str_replace('_', ' ', $status));

              $badgeClass = match ($status) {
                  'posted'   => 'bg-emerald-50 text-emerald-800 border-emerald-100',
                  'pending'  => 'bg-amber-50 text-amber-800 border-amber-100',
                  'refunded' => 'bg-rose-50 text-rose-800 border-rose-100',
                  default    => 'bg-slate-50 text-slate-700 border-slate-200',
              };

              $amount = isset($row['amount']) ? (float)$row['amount'] : 0;
            ?>
            <tr class="border-b border-slate-100 last:border-0 hover:bg-slate-50/60">
              <td class="px-4 py-2 align-top">
                <div class="font-medium text-[11px] text-slate-900">
                  <?= $h($row['date'] ?? '') ?>
                </div>
                <div class="text-[11px] text-slate-500">
                  <?= $h($row['time'] ?? '') ?>
                </div>
              </td>
              <td class="px-4 py-2 align-top">
                <div class="text-[11px] font-semibold text-slate-900">
                  <?= $h($row['reservation_code'] ?? ('#'.$row['reservation_id'] ?? '')) ?>
                </div>
                <div class="text-[11px] text-slate-500">
                  Room <?= $h($row['room_no'] ?? '—') ?>
                </div>
              </td>
              <td class="px-4 py-2 align-top">
                <div class="text-[11px] font-medium text-slate-900">
                  <?= $h($row['guest_name'] ?? 'Walk-in guest') ?>
                </div>
                <div class="text-[11px] text-slate-500">
                  <?= $h($row['channel'] ?? '') ?>
                </div>
              </td>
              <td class="px-4 py-2 align-top text-[11px]">
                <div class="font-medium text-slate-900">
                  <?= $h($row['method'] ?? '') ?>
                </div>
                <div class="text-slate-500">
                  <?= $h($row['currency'] ?? 'BDT') ?>
                </div>
              </td>
              <td class="px-4 py-2 align-top text-right text-[11px] font-semibold text-slate-900">
                ৳<?= number_format($amount, 2) ?>
              </td>
              <td class="px-4 py-2 align-top text-[11px] text-slate-700">
                <?= $h($row['reference'] ?? '') ?>
              </td>
              <td class="px-4 py-2 align-top">
                <span class="inline-flex items-center px-2 py-0.5 rounded-full border text-[10px] font-medium <?= $badgeClass ?>">
                  <?= $h($statusLabel) ?>
                </span>
              </td>
              <td class="px-4 py-2 align-top text-right">
                <?php $resId = (int)($row['reservation_id'] ?? 0); ?>
                <?php if ($resId > 0): ?>
                  <a href="<?= $h($base) ?>/reservations/<?= $resId ?>"
                     class="inline-flex items-center gap-1 rounded-lg px-2.5 py-1 text-[11px] font-semibold text-emerald-800 bg-emerald-50 hover:bg-emerald-100 border border-emerald-100">
                    <i class="fa-solid fa-up-right-from-square"></i>
                    <span>Open folio</span>
                  </a>
                <?php else: ?>
                  <span class="text-[11px] text-slate-400">—</span>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
      </table>
    </div>
  </section>

  <!-- HOW TO USE THIS PAGE -->
  <section class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-4 text-xs text-slate-700 space-y-1.5">
    <div class="font-semibold text-slate-900 mb-1">
      How to use the Payments page
    </div>
    <ol class="list-decimal list-inside space-y-1">
      <li>
        Use the <strong>Filter payments</strong> card to set the date range, method and status.
        This is perfect for day closing, shift handover or audit checks.
      </li>
      <li>
        Scroll through the <strong>Payments list</strong> to review each transaction:
        check the reservation, guest, method and reference before you close the day.
      </li>
      <li>
        Click <strong>Open folio</strong> on any row to jump directly to the reservation folio
        for adjustments, charges or refunds.
      </li>
      <li>
        For new payments, post them from <strong>Front desk</strong> or
        <strong>Folio &amp; billing</strong>; they will automatically appear here
        with the correct reservation and guest.
      </li>
    </ol>
    <p class="mt-1">
      Future upgrade: this page can auto-sync with Accounting to push a daily payments
      journal into DMS/GL, keeping your books and HotelFlow perfectly aligned.
    </p>
  </section>
</div>