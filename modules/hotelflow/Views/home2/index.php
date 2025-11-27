<?php
/** @var array $stats @var array $flags @var int $progress @var string $module_base */
$h = fn($v)=>htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
$base = rtrim((string)($module_base ?? '/apps/hotelflow'), '/');

function chip(bool $ok, string $txt): string {
  $cls = $ok ? 'bg-emerald-50 text-emerald-700 border-emerald-200'
             : 'bg-amber-50 text-amber-700 border-amber-200';
  $ico = $ok ? 'fa-check-circle' : 'fa-circle-exclamation';
  return '<span class="inline-flex items-center gap-2 px-2.5 py-1 rounded-full text-xs border '.$cls.'">'
       . '<i class="fa-solid '.$ico.'"></i>'.$txt.'</span>';
}
?>
<div class="max-w-[1100px] mx-auto space-y-8">

  <!-- Hero -->
  <section class="rounded-2xl border border-slate-200 p-6 lg:p-8 bg-white dark:bg-gray-900">
    <div class="flex flex-col lg:flex-row items-start lg:items-center gap-4 justify-between">
      <div>
        <h1 class="text-2xl font-extrabold tracking-tight">Welcome to HotelFlow</h1>
        <p class="text-slate-600 mt-1">Setup the core elements, then manage day-to-day operations from your dashboard.</p>
      </div>
      <div class="min-w-[260px]">
        <div class="text-sm mb-1 flex items-center justify-between">
          <span>Setup progress</span><span class="font-semibold"><?= $progress ?>%</span>
        </div>
        <div class="h-2 rounded-full bg-slate-200 overflow-hidden">
          <div class="h-full" style="width: <?= (int)$progress ?>%; background: var(--brand)"></div>
        </div>
      </div>
    </div>

    <div class="mt-4 flex flex-wrap gap-2">
      <?= chip($flags['properties'],   'Property profile') ?>
      <?= chip($flags['room_types'],   'Room types') ?>
      <?= chip($flags['rooms'],        'Rooms') ?>
      <?= chip($flags['rate_plans'],   'Rate plans') ?>
      <?= chip($flags['inventory'],    'Inventory') ?>
      <?= chip($flags['reservations'], 'Reservations') ?>
      <?= chip($flags['folios'],       'Folios') ?>
      <?= chip($flags['payments'],     'Payments') ?>
    </div>

    <div class="mt-6 flex gap-2">
      <a href="<?= $h($base) ?>/dashboard" class="px-4 py-2 rounded-lg text-white" style="background:var(--brand)">
        <i class="fa-solid fa-gauge-high mr-2"></i>Open Dashboard
      </a>
      <a href="<?= $h($base) ?>/reservations/create" class="px-4 py-2 rounded-lg border border-slate-300 hover:bg-slate-50">
        <i class="fa-solid fa-plus mr-2"></i>New Reservation
      </a>
    </div>
  </section>

  <!-- Quick setup tiles -->
  <section class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
    <a href="<?= $h($base) ?>/setup/property" class="group p-5 rounded-xl border border-slate-200 hover:bg-slate-50 flex items-start gap-3">
      <i class="fa-solid fa-hotel text-xl mt-1" style="color:var(--brand)"></i>
      <div class="min-w-0">
        <div class="font-semibold">Property profile</div>
        <div class="text-sm text-slate-500 truncate">Timezone, address, branding</div>
        <div class="text-xs text-slate-400 mt-1"><?= (int)$stats['properties'] ?> configured</div>
      </div>
      <i class="fa-solid fa-chevron-right ml-auto text-slate-300 group-hover:text-slate-500"></i>
    </a>

    <a href="<?= $h($base) ?>/rooms/types" class="group p-5 rounded-xl border border-slate-200 hover:bg-slate-50 flex items-start gap-3">
      <i class="fa-solid fa-vector-square text-xl mt-1" style="color:var(--brand)"></i>
      <div class="min-w-0">
        <div class="font-semibold">Room types</div>
        <div class="text-sm text-slate-500 truncate">Occupancy, bedding, base rates</div>
        <div class="text-xs text-slate-400 mt-1"><?= (int)$stats['room_types'] ?> types</div>
      </div>
      <i class="fa-solid fa-chevron-right ml-auto text-slate-300 group-hover:text-slate-500"></i>
    </a>

    <a href="<?= $h($base) ?>/rooms" class="group p-5 rounded-xl border border-slate-200 hover:bg-slate-50 flex items-start gap-3">
      <i class="fa-solid fa-door-closed text-xl mt-1" style="color:var(--brand)"></i>
      <div class="min-w-0">
        <div class="font-semibold">Rooms</div>
        <div class="text-sm text-slate-500 truncate">Floors, status (OOS/OOO), nightly rate</div>
        <div class="text-xs text-slate-400 mt-1"><?= (int)$stats['rooms'] ?> rooms</div>
      </div>
      <i class="fa-solid fa-chevron-right ml-auto text-slate-300 group-hover:text-slate-500"></i>
    </a>

    <a href="<?= $h($base) ?>/rates/plans" class="group p-5 rounded-xl border border-slate-200 hover:bg-slate-50 flex items-start gap-3">
      <i class="fa-solid fa-tags text-xl mt-1" style="color:var(--brand)"></i>
      <div class="min-w-0">
        <div class="font-semibold">Rate plans</div>
        <div class="text-sm text-slate-500 truncate">BAR, corporate, packages</div>
        <div class="text-xs text-slate-400 mt-1"><?= (int)$stats['rate_plans'] ?> plans</div>
      </div>
      <i class="fa-solid fa-chevron-right ml-auto text-slate-300 group-hover:text-slate-500"></i>
    </a>

    <a href="<?= $h($base) ?>/inventory/availability" class="group p-5 rounded-xl border border-slate-200 hover:bg-slate-50 flex items-start gap-3">
      <i class="fa-solid fa-calendar-days text-xl mt-1" style="color:var(--brand)"></i>
      <div class="min-w-0">
        <div class="font-semibold">Inventory</div>
        <div class="text-sm text-slate-500 truncate">Totals, OOO, availability by day</div>
        <div class="text-xs text-slate-400 mt-1"><?= (int)$stats['inventory'] ?> future days</div>
      </div>
      <i class="fa-solid fa-chevron-right ml-auto text-slate-300 group-hover:text-slate-500"></i>
    </a>

    <a href="<?= $h($base) ?>/billing/folios" class="group p-5 rounded-xl border border-slate-200 hover:bg-slate-50 flex items-start gap-3">
      <i class="fa-solid fa-file-invoice-dollar text-xl mt-1" style="color:var(--brand)"></i>
      <div class="min-w-0">
        <div class="font-semibold">Billing & Folios</div>
        <div class="text-sm text-slate-500 truncate">Charges, taxes, payments</div>
        <div class="text-xs text-slate-400 mt-1"><?= (int)$stats['folios'] ?> folios / <?= (int)$stats['payments'] ?> payments</div>
      </div>
      <i class="fa-solid fa-chevron-right ml-auto text-slate-300 group-hover:text-slate-500"></i>
    </a>
  </section>

  <!-- Operations shortcuts -->
  <section class="rounded-2xl border border-slate-200 p-5">
    <div class="flex items-center justify-between mb-3">
      <div class="font-semibold">Operations</div>
      <a href="<?= $h($base) ?>/dashboard" class="text-sm underline">Go to dashboard</a>
    </div>
    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3">
      <a href="<?= $h($base) ?>/frontdesk/arrivals"   class="px-3 py-2 rounded-lg border border-slate-200 hover:bg-slate-50"><i class="fa-solid fa-right-to-bracket mr-2"></i>Arrivals</a>
      <a href="<?= $h($base) ?>/frontdesk/inhouse"    class="px-3 py-2 rounded-lg border border-slate-200 hover:bg-slate-50"><i class="fa-solid fa-bed mr-2"></i>In-house</a>
      <a href="<?= $h($base) ?>/frontdesk/departures" class="px-3 py-2 rounded-lg border border-slate-200 hover:bg-slate-50"><i class="fa-solid fa-right-from-bracket mr-2"></i>Departures</a>
      <a href="<?= $h($base) ?>/reservations"         class="px-3 py-2 rounded-lg border border-slate-200 hover:bg-slate-50"><i class="fa-regular fa-calendar-plus mr-2"></i>Reservations</a>
      <a href="<?= $h($base) ?>/reports/revenue"      class="px-3 py-2 rounded-lg border border-slate-200 hover:bg-slate-50"><i class="fa-solid fa-chart-line mr-2"></i>Revenue</a>
      <a href="<?= $h($base) ?>/settings"             class="px-3 py-2 rounded-lg border border-slate-200 hover:bg-slate-50"><i class="fa-solid fa-sliders mr-2"></i>Settings</a>
    </div>
  </section>
</div>