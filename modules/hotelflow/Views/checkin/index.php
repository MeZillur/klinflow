<?php
/** @var array  $ctx */
/** @var string $module_base */
/** @var string $today */
/** @var string $query */
/** @var array  $rows */
/** @var string|null $error */

$h      = fn($v)=>htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
$base   = rtrim((string)($module_base ?? '/apps/hotelflow'), '/');
$today  = $today ?? date('Y-m-d');
$query  = $query ?? '';
$rows   = is_array($rows ?? null) ? $rows : [];
$error  = $error ?? null;
?>
<div class="max-w-6xl mx-auto space-y-6">

  <!-- HEADER + TINY NAV -->
  <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
    <div>
      <h1 class="text-2xl font-extrabold tracking-tight">Check-in desk</h1>
      <p class="text-slate-500 text-sm">
        Start every guest stay from one place. Search reservations by ID, code or token and launch the
        biometric check-in flow.
      </p>
    </div>

    <div class="flex flex-wrap gap-2 text-sm">
      <a href="<?= $h($base) ?>"
         class="inline-flex items-center gap-2 px-3 py-1.5 rounded-lg border border-slate-300 bg-white hover:bg-slate-50">
        <i class="fa-solid fa-grid-2"></i>
        <span>Back to dashboard</span>
      </a>
      <a href="<?= $h($base) ?>/reservations"
         class="inline-flex items-center gap-2 px-3 py-1.5 rounded-lg border border-slate-200 bg-slate-50 hover:bg-slate-100">
        <i class="fa-solid fa-list"></i>
        <span>Reservations</span>
      </a>
      <a href="<?= $h($base) ?>/frontdesk"
         class="inline-flex items-center gap-2 px-3 py-1.5 rounded-lg border border-slate-200 bg-slate-50 hover:bg-slate-100">
        <i class="fa-solid fa-table-cells-large"></i>
        <span>Front desk board</span>
      </a>
    </div>
  </div>

  <!-- MAIN LAYOUT -->
  <div class="grid gap-6 lg:grid-cols-[minmax(0,2.1fr)_minmax(0,1.2fr)]">

    <!-- LEFT: LOOKUP + TODAY ARRIVALS -->
    <div class="space-y-5">

      <!-- Reservation lookup -->
      <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
        <div class="flex items-center justify-between gap-3 mb-4">
          <div>
            <h2 class="text-sm font-semibold text-slate-900 uppercase tracking-wide">
              Reservation lookup
            </h2>
            <p class="text-xs text-slate-500 mt-0.5">
              Type a reservation ID, confirmation code or pre-arrival token. The system will route you to the
              dedicated check-in screen for that booking.
            </p>
          </div>
          <span class="inline-flex items-center rounded-full bg-emerald-50 px-2 py-0.5 text-[11px] font-medium text-emerald-700 border border-emerald-100">
            Shortcut: <span class="ml-1 inline-flex items-center justify-center h-5 w-5 rounded-md bg-emerald-600 text-white text-[10px] font-bold">C</span>
          </span>
        </div>

        <?php if ($error): ?>
          <div class="mb-3 rounded-lg border border-rose-200 bg-rose-50 px-3 py-2 text-xs text-rose-800 flex items-start gap-2">
            <i class="fa-solid fa-circle-exclamation mt-0.5"></i>
            <span><?= $h($error) ?></span>
          </div>
        <?php endif; ?>

        <form method="post" action="<?= $h($base) ?>/checkin/lookup" class="space-y-3" id="checkin-lookup-form">
          <div class="flex flex-col gap-2 sm:flex-row sm:items-center">
            <div class="flex-1">
              <label class="block text-xs font-medium text-slate-700 mb-1">Reservation ID / code / token</label>
              <div class="relative">
                <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-slate-400 text-xs">
                  <i class="fa-solid fa-magnifying-glass"></i>
                </span>
                <input
                  type="text"
                  name="q"
                  value="<?= $h($query) ?>"
                  class="w-full rounded-xl border border-slate-300 pl-8 pr-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500/80 focus:border-emerald-500"
                  placeholder="E.g. 1024, HFL-2025-0009, token-XYZ..."
                  autofocus
                >
              </div>
            </div>
            <button
              type="submit"
              class="mt-1 sm:mt-6 inline-flex items-center gap-2 rounded-xl px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:shadow-md transition"
              style="background:var(--brand, #228B22);"
            >
              <i class="fa-solid fa-right-to-bracket"></i>
              <span>Open check-in</span>
            </button>
          </div>

          <p class="text-[11px] text-slate-500">
            The next screen will guide you through guest selection, biometric capture and room assignment for this reservation.
          </p>
        </form>
      </div>

      <!-- Today’s arrivals (stub) -->
      <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
        <div class="flex items-center justify-between mb-3">
          <div>
            <h2 class="text-sm font-semibold text-slate-900 uppercase tracking-wide">
              Today’s arrivals
            </h2>
            <p class="text-xs text-slate-500 mt-0.5">
              Quick view of bookings scheduled to arrive on <strong><?= $h($today) ?></strong>.
            </p>
          </div>
          <a href="<?= $h($base) ?>/frontdesk?tab=arrivals"
             class="inline-flex items-center gap-1 rounded-lg border border-slate-200 bg-slate-50 px-3 py-1.5 text-xs text-slate-700 hover:bg-slate-100">
            <i class="fa-solid fa-table-cells"></i>
            <span>Open front desk</span>
          </a>
        </div>

        <?php if (!$rows): ?>
          <div class="rounded-xl border border-dashed border-slate-200 bg-slate-50 px-4 py-3 text-xs text-slate-500">
            No arrivals are loaded into this widget yet.
            <span class="block mt-1">
              Later we can reuse the Frontdesk arrivals query here to show live status.
            </span>
          </div>
        <?php else: ?>
          <div class="overflow-x-auto -mx-3 sm:mx-0">
            <table class="min-w-full text-xs text-left border-collapse">
              <thead>
              <tr class="border-b border-slate-200 text-slate-500">
                <th class="px-3 py-2 font-medium">Res ID</th>
                <th class="px-3 py-2 font-medium">Guest</th>
                <th class="px-3 py-2 font-medium">Room type</th>
                <th class="px-3 py-2 font-medium">Arrival</th>
                <th class="px-3 py-2 font-medium text-right">Action</th>
              </tr>
              </thead>
              <tbody class="divide-y divide-slate-100">
              <?php foreach ($rows as $r): ?>
                <tr>
                  <td class="px-3 py-2 text-slate-800">
                    #<?= $h($r['id'] ?? '') ?>
                  </td>
                  <td class="px-3 py-2 text-slate-800">
                    <?= $h($r['guest_name'] ?? '—') ?>
                  </td>
                  <td class="px-3 py-2 text-slate-600">
                    <?= $h($r['room_type'] ?? '—') ?>
                  </td>
                  <td class="px-3 py-2 text-slate-600">
                    <?= $h($r['arrival'] ?? $today) ?>
                  </td>
                  <td class="px-3 py-2 text-right">
                    <?php if (!empty($r['id'])): ?>
                      <a href="<?= $h($base) ?>/checkin/<?= $h($r['id']) ?>"
                         class="inline-flex items-center gap-1 rounded-lg border border-emerald-200 bg-emerald-50 px-2.5 py-1 text-[11px] font-medium text-emerald-800 hover:bg-emerald-100">
                        <i class="fa-solid fa-right-to-bracket"></i>
                        <span>Check-in</span>
                      </a>
                    <?php endif; ?>
                  </td>
                </tr>
              <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>
      </div>
    </div>

    <!-- RIGHT: CONTEXT + TIPS -->
    <div class="space-y-4">

      <!-- Today summary -->
      <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
        <div class="flex items-start justify-between gap-3">
          <div>
            <p class="text-xs font-semibold text-emerald-700 uppercase tracking-wide">
              Today
            </p>
            <p class="text-lg font-semibold text-slate-900">
              <?= $h(date('l, d M Y', strtotime($today))) ?>
            </p>
            <p class="text-xs text-slate-500 mt-1">
              Use this screen only to start check-ins. Room moves, upsells and billing continue from the
              Frontdesk board and Folio.
            </p>
          </div>
          <div class="inline-flex flex-col items-end text-right gap-1">
            <span class="text-xs text-slate-500">Local time</span>
            <span id="checkin-clock" class="text-base font-mono font-semibold text-slate-900">
              --
            </span>
          </div>
        </div>
      </div>

      <!-- Flow cards -->
      <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4 shadow-sm space-y-3 text-xs text-slate-700">
        <div class="flex items-center gap-2">
          <div class="h-7 w-7 rounded-xl bg-emerald-600 text-white flex items-center justify-center text-[11px]">
            1
          </div>
          <div>
            <p class="font-semibold text-slate-900">Search booking by ID or token</p>
            <p class="text-[11px] text-slate-500">
              Type anything you have at the counter: printed confirmation, WhatsApp code or pre-arrival link token.
            </p>
          </div>
        </div>

        <div class="flex items-center gap-2">
          <div class="h-7 w-7 rounded-xl bg-emerald-500 text-white flex items-center justify-center text-[11px]">
            2
          </div>
          <div>
            <p class="font-semibold text-slate-900">Confirm guests &amp; stay details</p>
            <p class="text-[11px] text-slate-500">
              The dedicated check-in page shows all guests on the reservation, balance due and assigned room (if any).
            </p>
          </div>
        </div>

        <div class="flex items-center gap-2">
          <div class="h-7 w-7 rounded-xl bg-emerald-400 text-white flex items-center justify-center text-[11px]">
            3
          </div>
          <div>
            <p class="font-semibold text-slate-900">Capture biometrics &amp; ID</p>
            <p class="text-[11px] text-slate-500">
              On the next screen, use the camera block to capture face and ID for each guest who needs verification.
            </p>
          </div>
        </div>

        <div class="flex items-center gap-2">
          <div class="h-7 w-7 rounded-xl bg-emerald-300 text-slate-900 flex items-center justify-center text-[11px]">
            4
          </div>
          <div>
            <p class="font-semibold text-slate-900">Set room to <span class="font-bold">in-house</span></p>
            <p class="text-[11px] text-slate-500">
              When finished, the system marks the reservation as in-house and exposes charges to Frontdesk / Folio.
            </p>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- HOW TO USE THIS PAGE -->
  <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-xs text-slate-700 space-y-1">
    <div class="font-semibold text-slate-900 mb-1">How to use this Check-in desk</div>
    <ol class="list-decimal list-inside space-y-1">
      <li>From the main HotelFlow dashboard, press <strong>C</strong> or click the <strong>Check-in</strong> tile to open this screen.</li>
      <li>Ask the guest for their reservation ID, confirmation code or pre-arrival link. Type it into the lookup box and click <strong>Open check-in</strong>.</li>
      <li>If a match is found, you’ll be redirected to the dedicated check-in page where you confirm guests, capture biometrics and assign rooms.</li>
      <li>Use the <strong>Front desk board</strong> link on the right to see all arrivals, in-house and departures in one grid.</li>
    </ol>
    <p class="mt-1 text-[11px] text-slate-500">
      Later we can plug live arrival data into this page and add one-click check-in buttons directly in the “Today’s arrivals” list.
    </p>
  </div>
</div>

<script>
  // Tiny clock for local UX polish
  (function () {
    var el = document.getElementById('checkin-clock');
    if (!el) return;
    function tick() {
      var d = new Date();
      el.textContent = d.toLocaleTimeString([], {hour: '2-digit', minute: '2-digit', second: '2-digit'});
    }
    tick();
    setInterval(tick, 1000);
  })();
</script>