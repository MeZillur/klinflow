<?php
/**
 * Front Desk Dashboard
 *
 * Expected (safe defaults added):
 * - array  $rows        : arrivals / in-house / departures list for selected date
 * - array  $stats       : optional counts (arrivals, inhouse, departures, occupied, rooms_total)
 * - string $date        : selected date (Y-m-d)
 * - string $mode        : 'arrivals' | 'inhouse' | 'departures' | 'room_status'
 * - string $module_base : module base URL (/t/{slug}/apps/hotelflow)
 */

$h    = fn($v)=>htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
$base = rtrim((string)($module_base ?? '/apps/hotelflow'), '/');

$rows  = $rows  ?? [];
$stats = $stats ?? [];
$date  = $date  ?? date('Y-m-d');
$mode  = $mode  ?? 'arrivals';

$today = date('Y-m-d');

// Fallback stats if controller didn’t pass any
$statArrivals   = (int)($stats['arrivals']   ?? 0);
$statInhouse    = (int)($stats['inhouse']    ?? 0);
$statDepartures = (int)($stats['departures'] ?? 0);
$roomsTotal     = (int)($stats['rooms_total'] ?? 0);
$roomsOcc       = (int)($stats['occupied']    ?? 0);

// Simple occupancy %
$occRate = $roomsTotal > 0 ? round($roomsOcc * 100 / $roomsTotal) : 0;

// For “mini timeline”: sort by check-in time if present
$timeline = $rows;
usort($timeline, function ($a, $b) {
    $ta = (string)($a['check_in_time'] ?? '');
    $tb = (string)($b['check_in_time'] ?? '');
    return strcmp($ta, $tb);
});

// Date helpers for prev/next buttons
$dtSel   = DateTimeImmutable::createFromFormat('Y-m-d', $date) ?: new DateTimeImmutable('today');
$dtPrev  = $dtSel->modify('-1 day')->format('Y-m-d');
$dtNext  = $dtSel->modify('+1 day')->format('Y-m-d');
?>
<div class="max-w-[1200px] mx-auto space-y-6">
  <!-- HEADER + DATE / MODE CONTROLS -->
  <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
    <div>
      <h1 class="text-2xl font-extrabold tracking-tight">
        Front Desk
      </h1>
      <p class="text-slate-500 text-sm">
        Real-time view of today’s arrivals, in-house guests, departures and room status.
      </p>
    </div>

    <!-- Date selector + quick toggles -->
    <form method="get"
          class="flex flex-wrap items-center gap-2 bg-white border border-slate-200 rounded-2xl px-3 py-2 shadow-sm">
      <?php if (!empty($_GET['view'])): ?>
        <input type="hidden" name="view" value="<?= $h((string)$_GET['view']) ?>">
      <?php endif; ?>

      <button type="submit"
              name="date"
              value="<?= $h($today) ?>"
              class="hidden sm:inline-flex items-center gap-1 px-3 py-1.5 rounded-lg text-xs font-medium border
                     <?= $date === $today
                          ? 'bg-emerald-50 border-emerald-200 text-emerald-700'
                          : 'bg-white border-slate-200 text-slate-600 hover:bg-slate-50' ?>">
        <i class="fa-regular fa-sun"></i>
        Today
      </button>

      <div class="flex items-center gap-1">
        <button type="submit"
                name="date"
                value="<?= $h($dtPrev) ?>"
                class="inline-flex items-center justify-center h-8 w-8 rounded-lg border border-slate-200 text-xs text-slate-600 hover:bg-slate-50">
          <i class="fa-solid fa-chevron-left"></i>
        </button>

        <div class="relative">
          <input type="date"
                 name="date"
                 value="<?= $h($date) ?>"
                 class="h-8 pl-8 pr-2 rounded-lg border border-slate-200 text-sm">
          <span class="pointer-events-none absolute inset-y-0 left-2 flex items-center text-slate-400 text-xs">
            <i class="fa-regular fa-calendar"></i>
          </span>
        </div>

        <button type="submit"
                value="<?= $h($dtNext) ?>"
                name="date"
                class="inline-flex items-center justify-center h-8 w-8 rounded-lg border border-slate-200 text-xs text-slate-600 hover:bg-slate-50">
          <i class="fa-solid fa-chevron-right"></i>
        </button>
      </div>

      <button type="submit"
              class="ml-1 inline-flex items-center gap-1 px-3 py-1.5 rounded-lg text-xs font-semibold text-white shadow-sm"
              style="background:var(--brand)">
        <i class="fa-solid fa-play"></i>
        Go
      </button>
    </form>
  </div>

  <?php $tab = 'frontdesk_'.$mode; include __DIR__.'/../frontdesk/_tabs.php'; ?>

  <!-- TOP METRIC CARDS -->
  <div class="grid grid-cols-1 sm:grid-cols-4 gap-3 text-sm">
    <!-- Arrivals -->
    <div class="rounded-2xl border border-slate-200 bg-white px-4 py-3 flex items-center justify-between">
      <div class="flex items-center gap-3">
        <div class="h-9 w-9 rounded-full bg-emerald-50 flex items-center justify-center text-emerald-600">
          <i class="fa-solid fa-door-open"></i>
        </div>
        <div>
          <div class="text-[11px] text-slate-500 uppercase tracking-wide">Arrivals</div>
          <div class="text-lg font-semibold text-slate-900"><?= $statArrivals ?></div>
        </div>
      </div>
      <span class="text-[11px] px-2 py-0.5 rounded-full bg-emerald-50 text-emerald-700 border border-emerald-100">
        Today
      </span>
    </div>

    <!-- In-house -->
    <div class="rounded-2xl border border-slate-200 bg-white px-4 py-3 flex items-center justify-between">
      <div class="flex items-center gap-3">
        <div class="h-9 w-9 rounded-full bg-sky-50 flex items-center justify-center text-sky-600">
          <i class="fa-solid fa-bed"></i>
        </div>
        <div>
          <div class="text-[11px] text-slate-500 uppercase tracking-wide">In-house</div>
          <div class="text-lg font-semibold text-sky-800"><?= $statInhouse ?></div>
        </div>
      </div>
      <span class="text-[11px] px-2 py-0.5 rounded-full bg-sky-50 text-sky-700 border border-sky-100">
        Staying
      </span>
    </div>

    <!-- Departures -->
    <div class="rounded-2xl border border-slate-200 bg-white px-4 py-3 flex items-center justify-between">
      <div class="flex items-center gap-3">
        <div class="h-9 w-9 rounded-full bg-amber-50 flex items-center justify-center text-amber-600">
          <i class="fa-solid fa-door-closed"></i>
        </div>
        <div>
          <div class="text-[11px] text-slate-500 uppercase tracking-wide">Departures</div>
          <div class="text-lg font-semibold text-amber-700"><?= $statDepartures ?></div>
        </div>
      </div>
      <span class="text-[11px] px-2 py-0.5 rounded-full bg-amber-50 text-amber-700 border border-amber-100">
        Today
      </span>
    </div>

    <!-- Occupancy -->
    <div class="rounded-2xl border border-slate-200 bg-white px-4 py-3 flex flex-col justify-between">
      <div class="flex items-center justify-between gap-2">
        <div class="flex items-center gap-3">
          <div class="h-9 w-9 rounded-full bg-indigo-50 flex items-center justify-center text-indigo-600">
            <i class="fa-solid fa-chart-pie"></i>
          </div>
          <div>
            <div class="text-[11px] text-slate-500 uppercase tracking-wide">Occupancy</div>
            <div class="text-lg font-semibold text-slate-900"><?= $occRate ?>%</div>
          </div>
        </div>
        <div class="text-[11px] text-slate-500 text-right">
          <div><?= $roomsOcc ?> / <?= $roomsTotal ?> rooms</div>
        </div>
      </div>
      <div class="mt-2 h-1.5 w-full rounded-full bg-slate-100 overflow-hidden">
        <div class="h-full rounded-full"
             style="width:<?= max(0, min(100, $occRate)) ?>%;background:var(--brand);"></div>
      </div>
    </div>
  </div>

  <!-- MAIN LAYOUT: LEFT = TIMELINE + TASKS, RIGHT = GRID -->
  <div class="grid grid-cols-1 lg:grid-cols-3 gap-4 items-start">
    <!-- LEFT: timeline + tasks -->
    <div class="space-y-4 lg:col-span-1">
      <!-- Mini day timeline -->
      <div class="rounded-2xl border border-slate-200 bg-white p-4">
        <div class="flex items-center justify-between mb-2">
          <div>
            <div class="text-sm font-semibold text-slate-900">Day timeline</div>
            <div class="text-xs text-slate-500">
              Arrivals / departures for <?= $h($dtSel->format('d M Y')) ?>
            </div>
          </div>
          <span class="inline-flex items-center gap-1 text-[11px] text-emerald-700 bg-emerald-50 border border-emerald-100 px-2 py-0.5 rounded-full">
            <span class="h-1.5 w-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
            Live
          </span>
        </div>

        <ul class="divide-y divide-slate-200 text-sm max-h-64 overflow-y-auto">
          <?php if (!$timeline): ?>
            <li class="py-3 text-slate-500 text-sm">
              No reservations for this date yet.
            </li>
          <?php endif; ?>

          <?php foreach ($timeline as $row):
            $code   = (string)($row['code']        ?? '');
            $guest  = (string)($row['guest_name']  ?? 'Guest');
            $ciTime = (string)($row['check_in_time']  ?? '');
            $coTime = (string)($row['check_out_time'] ?? '');
            $room   = (string)($row['room_name']      ?? '');
            $st     = (string)($row['status']         ?? '');
          ?>
            <li class="py-2.5 flex items-center gap-3">
              <div class="flex flex-col items-center">
                <div class="text-[11px] text-slate-500">
                  <?= $ciTime !== '' ? $h($ciTime) : '&nbsp;' ?>
                </div>
                <div class="h-6 w-px bg-slate-200"></div>
                <div class="text-[11px] text-slate-400">
                  <?= $coTime !== '' ? $h($coTime) : '' ?>
                </div>
              </div>
              <div class="flex-1">
                <div class="text-xs font-medium text-slate-900">
                  <?= $h($guest) ?>
                  <?php if ($room !== ''): ?>
                    <span class="text-[11px] text-slate-500">• <?= $h($room) ?></span>
                  <?php endif; ?>
                </div>
                <div class="text-[11px] text-slate-500">
                  <?= $h($code) ?> · <?= $h($st !== '' ? ucwords(str_replace('_',' ',$st)) : '') ?>
                </div>
              </div>
              <a href="<?= $h($base) ?>/reservations/<?= (int)($row['reservation_id'] ?? $row['id'] ?? 0) ?>"
                 class="text-[11px] inline-flex items-center gap-1 px-2 py-1 rounded-lg border border-slate-200 hover:bg-slate-50">
                <i class="fa-regular fa-file-lines"></i> Open
              </a>
            </li>
          <?php endforeach; ?>
        </ul>
      </div>

      <!-- Pending tasks (housekeeping / payments / notes) -->
      <div class="rounded-2xl border border-slate-200 bg-white p-4">
        <div class="flex items-center justify-between mb-2">
          <div class="text-sm font-semibold text-slate-900">Pending tasks</div>
          <button type="button"
                  class="text-xs text-slate-500 hover:text-slate-700 inline-flex items-center gap-1">
            <i class="fa-solid fa-rotate-right text-[11px]"></i>
            Refresh
          </button>
        </div>
        <ul class="space-y-1 text-sm">
          <!-- For now we just show placeholders; controller can inject real tasks later -->
          <li class="flex items-start gap-2 text-slate-600">
            <span class="mt-1 h-2 w-2 rounded-full bg-amber-400"></span>
            <span>No pending tasks wired yet. You can feed housekeeping / payment alerts here.</span>
          </li>
        </ul>
      </div>
    </div>

    <!-- RIGHT: main data grid -->
    <div class="lg:col-span-2 space-y-3">
      <div class="flex items-center justify-between">
        <div class="text-sm font-semibold text-slate-900">
          <?= $mode === 'inhouse'
                ? 'In-house guests'
                : ($mode === 'departures' ? 'Departures' : 'Arrivals') ?>
        </div>
        <div class="text-xs text-slate-500">
          Total in list:
          <span class="font-medium"><?= count($rows) ?></span>
        </div>
      </div>

      <div class="rounded-2xl border border-slate-200 overflow-hidden bg-white">
        <table class="w-full text-sm">
          <thead class="bg-slate-50">
            <tr class="text-left text-slate-600">
              <th class="p-3 font-semibold">Res #</th>
              <th class="p-3 font-semibold">Guest</th>
              <th class="p-3 font-semibold">Room Type</th>
              <th class="p-3 font-semibold">Room</th>
              <th class="p-3 font-semibold">Check-in</th>
              <th class="p-3 font-semibold">Check-out</th>
              <th class="p-3 font-semibold">Status</th>
              <th class="p-3 font-semibold text-right">Actions</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-200">
            <?php if (!$rows): ?>
              <tr>
                <td colspan="8" class="p-6 text-center text-slate-500">
                  No reservations found for this date.
                </td>
              </tr>
            <?php endif; ?>

            <?php foreach ($rows as $r):
              $code    = (string)($r['code']        ?? '');
              $guest   = (string)($r['guest_name']  ?? '—');
              $rtName  = (string)($r['room_type']   ?? $r['room_type_name'] ?? '');
              $room    = (string)($r['room_name']   ?? '');
              $ci      = (string)($r['check_in']    ?? '');
              $co      = (string)($r['check_out']   ?? '');
              $status  = (string)($r['status']      ?? '');
              $resId   = (int)($r['reservation_id'] ?? $r['id'] ?? 0);

              $badgeClass = 'bg-slate-50 text-slate-700 border-slate-200';
              if (\in_array($status, ['confirmed','guaranteed'], true)) {
                  $badgeClass = 'bg-emerald-50 text-emerald-700 border-emerald-200';
              } elseif ($status === 'in_house') {
                  $badgeClass = 'bg-sky-50 text-sky-700 border-sky-200';
              } elseif ($status === 'cancelled') {
                  $badgeClass = 'bg-rose-50 text-rose-700 border-rose-200';
              } elseif ($status === 'no_show') {
                  $badgeClass = 'bg-amber-50 text-amber-700 border-amber-200';
              }
            ?>
              <tr class="hover:bg-slate-50/60">
                <td class="p-3 font-medium text-slate-900 whitespace-nowrap">
                  <?= $h($code) ?>
                </td>
                <td class="p-3">
                  <div class="text-slate-900"><?= $h($guest) ?></div>
                  <?php if (!empty($r['company_name'])): ?>
                    <div class="text-[11px] text-slate-500">
                      <?= $h((string)$r['company_name']) ?>
                    </div>
                  <?php endif; ?>
                </td>
                <td class="p-3 text-slate-700">
                  <?= $h($rtName) ?>
                </td>
                <td class="p-3 text-slate-700">
                  <?= $h($room) ?>
                </td>
                <td class="p-3 whitespace-nowrap text-slate-700">
                  <?= $h($ci) ?>
                </td>
                <td class="p-3 whitespace-nowrap text-slate-700">
                  <?= $h($co) ?>
                </td>
                <td class="p-3">
                  <span class="inline-flex items-center px-2 py-0.5 rounded-full border text-xs font-medium <?= $badgeClass ?>">
                    <?= $h($status !== '' ? ucwords(str_replace('_',' ', $status)) : '—') ?>
                  </span>
                </td>
                <td class="p-3 text-right whitespace-nowrap">
                  <a href="<?= $h($base) ?>/reservations/<?= $resId ?>"
                     class="inline-flex items-center px-3 py-1.5 rounded-lg border border-slate-300 text-xs hover:bg-slate-100 mr-1">
                    <i class="fa-regular fa-file-lines mr-1"></i> Open
                  </a>
                  <?php if ($status === 'booked'): ?>
                    <form method="post"
                          action="<?= $h($base) ?>/reservations/<?= $resId ?>/check-in"
                          class="inline-block">
                      <button class="inline-flex items-center px-3 py-1.5 rounded-lg text-xs text-white"
                              style="background:var(--brand)">
                        <i class="fa-solid fa-door-open mr-1"></i> Check-in
                      </button>
                    </form>
                  <?php elseif ($status === 'in_house'): ?>
                    <form method="post"
                          action="<?= $h($base) ?>/reservations/<?= $resId ?>/check-out"
                          class="inline-block">
                      <button class="inline-flex items-center px-3 py-1.5 rounded-lg text-xs border border-slate-300 hover:bg-slate-100">
                        <i class="fa-solid fa-door-closed mr-1"></i> Check-out
                      </button>
                    </form>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>