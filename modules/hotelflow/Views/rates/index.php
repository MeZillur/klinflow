<?php
/** @var array  $flags */
/** @var string $today */
/** @var string $monthRef */
/** @var string|null $module_base */

$h = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');

$base = isset($module_base)
    ? rtrim((string)$module_base, '/')
    : '/hotel/apps/hotelflow';

$availabilityUrl = $base . '/rates/availability';
$plansUrl        = $base . '/rates/rate-plans';
$overridesUrl    = $base . '/rates/overrides';
$restrUrl        = $base . '/rates/restrictions';
$allotUrl        = $base . '/rates/allotments';
$yieldUrl        = $base . '/rates/yield-rules';

$brand = '#228B22';
?>
<div class="p-6 space-y-8">

  <!-- Top bar: title + micro-nav -->
  <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
    <div>
      <h1 class="text-2xl sm:text-3xl font-bold text-slate-900">
        Rates &amp; Availability
      </h1>
      <p class="mt-1 text-sm text-slate-500 max-w-2xl">
        Single hub for your room prices, availability, restrictions and yield rules.
        Designed so front desk, revenue and reservations can speak the same language.
      </p>
    </div>

    <!-- Tiny related menu -->
    <div class="flex flex-wrap justify-start lg:justify-end gap-2 text-sm">
      <a href="<?= $h($base) ?>/rooms"
         class="inline-flex items-center px-3 py-1.5 rounded-full border border-slate-200 bg-white hover:bg-slate-50">
        <i class="fa-regular fa-building mr-1"></i> Rooms
      </a>
      <a href="<?= $h($base) ?>/rooms/types"
         class="inline-flex items-center px-3 py-1.5 rounded-full border border-slate-200 bg-white hover:bg-slate-50">
        <i class="fa-solid fa-layer-group mr-1"></i> Room types
      </a>
      <a href="<?= $h($base) ?>/rooms/floors"
         class="inline-flex items-center px-3 py-1.5 rounded-full border border-slate-200 bg-white hover:bg-slate-50">
        <i class="fa-solid fa-border-all mr-1"></i> Floors
      </a>
    </div>
  </div>

  <!-- Context strip -->
  <div class="grid gap-4 md:grid-cols-3">
    <div class="rounded-2xl border border-emerald-100 bg-emerald-50/60 px-4 py-3 flex items-center gap-3">
      <div class="shrink-0 h-10 w-10 rounded-xl flex items-center justify-center bg-white shadow-sm">
        <i class="fa-solid fa-calendar-days text-[<?= $brand ?>]"></i>
      </div>
      <div>
        <div class="text-xs uppercase tracking-wide text-emerald-700">Month in focus</div>
        <div class="text-sm font-medium text-emerald-900">
          <?= $h(date('F Y', strtotime($monthRef))) ?>
        </div>
      </div>
    </div>
    <div class="rounded-2xl border border-slate-100 bg-slate-50 px-4 py-3 flex items-center gap-3">
      <div class="shrink-0 h-10 w-10 rounded-xl flex items-center justify-center bg-white shadow-sm">
        <i class="fa-solid fa-bolt text-slate-600"></i>
      </div>
      <div>
        <div class="text-xs uppercase tracking-wide text-slate-500">Today</div>
        <div class="text-sm font-medium text-slate-900">
          <?= $h(date('D, d M Y', strtotime($today))) ?>
        </div>
      </div>
    </div>
    <div class="rounded-2xl border border-slate-100 bg-white px-4 py-3 flex items-center justify-between gap-3">
      <div>
        <div class="text-xs uppercase tracking-wide text-slate-500">Quick actions</div>
        <div class="mt-1 flex flex-wrap gap-2 text-xs">
          <a href="<?= $h($availabilityUrl) ?>"
             class="inline-flex items-center px-2.5 py-1 rounded-full bg-emerald-600 text-white font-medium hover:bg-emerald-700">
            <i class="fa-solid fa-table-cells mr-1"></i> Open availability
          </a>
          <a href="<?= $h($plansUrl) ?>"
             class="inline-flex items-center px-2.5 py-1 rounded-full bg-slate-900 text-white hover:bg-black/80">
            <i class="fa-solid fa-tags mr-1"></i> Rate plans
          </a>
        </div>
      </div>
    </div>
  </div>

  <!-- Main ARI modules grid -->
  <section aria-label="Rates modules" class="space-y-4">
    <div class="flex items-center justify-between gap-2">
      <h2 class="text-sm font-semibold text-slate-700 uppercase tracking-wide">
        Control Center
      </h2>
      <p class="text-xs text-slate-400">
        Click a tile to manage that part of your ARI.
      </p>
    </div>

    <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
      <!-- Availability -->
      <a href="<?= $h($availabilityUrl) ?>"
         class="group rounded-2xl border border-slate-100 bg-white px-4 py-4 flex flex-col justify-between hover:border-emerald-200 hover:shadow-sm transition">
        <div class="flex items-start justify-between gap-3">
          <div class="flex items-center gap-3">
            <div class="h-10 w-10 rounded-xl flex items-center justify-center bg-emerald-50">
              <i class="fa-solid fa-table-cells-large text-[<?= $brand ?>]"></i>
            </div>
            <div>
              <h3 class="text-sm font-semibold text-slate-900">
                Availability calendar
              </h3>
              <p class="mt-1 text-xs text-slate-500 max-w-[16rem]">
                Drag-and-drop style grid to adjust daily allotment, close dates,
                and see sold rooms per type.
              </p>
            </div>
          </div>
          <?php if (!empty($flags['availability'])): ?>
            <span class="inline-flex items-center rounded-full bg-emerald-50 px-2 py-0.5 text-[11px] font-medium text-emerald-700">
              <i class="fa-solid fa-circle-check mr-1"></i> Live
            </span>
          <?php else: ?>
            <span class="inline-flex items-center rounded-full bg-amber-50 px-2 py-0.5 text-[11px] font-medium text-amber-700">
              <i class="fa-solid fa-circle-exclamation mr-1"></i> Needs setup
            </span>
          <?php endif; ?>
        </div>
        <div class="mt-4 flex items-center justify-between text-xs text-slate-500">
          <span>Per room type · per day</span>
          <span class="group-hover:text-emerald-700 font-medium inline-flex items-center">
            Open calendar
            <i class="fa-solid fa-arrow-right ml-1 text-[11px]"></i>
          </span>
        </div>
      </a>

      <!-- Rate plans -->
      <a href="<?= $h($plansUrl) ?>"
         class="group rounded-2xl border border-slate-100 bg-white px-4 py-4 flex flex-col justify-between hover:border-emerald-200 hover:shadow-sm transition">
        <div class="flex items-start justify-between gap-3">
          <div class="flex items-center gap-3">
            <div class="h-10 w-10 rounded-xl flex items-center justify-center bg-slate-900/90">
              <i class="fa-solid fa-tags text-white"></i>
            </div>
            <div>
              <h3 class="text-sm font-semibold text-slate-900">
                Rate plans
              </h3>
              <p class="mt-1 text-xs text-slate-500 max-w-[16rem]">
                Define BAR, corporate, OTA and promo plans with currencies and
                default pricing rules.
              </p>
            </div>
          </div>
          <?php if (!empty($flags['rate_plans'])): ?>
            <span class="inline-flex items-center rounded-full bg-emerald-50 px-2 py-0.5 text-[11px] font-medium text-emerald-700">
              <i class="fa-solid fa-circle-check mr-1"></i> Configured
            </span>
          <?php endif; ?>
        </div>
        <div class="mt-4 flex items-center justify-between text-xs text-slate-500">
          <span>Base products for your prices</span>
          <span class="group-hover:text-emerald-700 font-medium inline-flex items-center">
            Manage plans
            <i class="fa-solid fa-arrow-right ml-1 text-[11px]"></i>
          </span>
        </div>
      </a>

      <!-- Overrides / Seasons -->
      <a href="<?= $h($overridesUrl) ?>"
         class="group rounded-2xl border border-slate-100 bg-white px-4 py-4 flex flex-col justify-between hover:border-emerald-200 hover:shadow-sm transition">
        <div class="flex items-start justify-between gap-3">
          <div class="flex items-center gap-3">
            <div class="h-10 w-10 rounded-xl flex items-center justify-center bg-amber-50">
              <i class="fa-solid fa-sun-plant-wilt text-amber-700"></i>
            </div>
            <div>
              <h3 class="text-sm font-semibold text-slate-900">
                Seasons &amp; overrides
              </h3>
              <p class="mt-1 text-xs text-slate-500 max-w-[16rem]">
                School holidays, Eid, New Year – set special prices for
                specific periods or channels.
              </p>
            </div>
          </div>
          <?php if (!empty($flags['overrides'])): ?>
            <span class="inline-flex items-center rounded-full bg-emerald-50 px-2 py-0.5 text-[11px] font-medium text-emerald-700">
              Ready
            </span>
          <?php endif; ?>
        </div>
        <div class="mt-4 flex items-center justify-between text-xs text-slate-500">
          <span>Date-based price rules</span>
          <span class="group-hover:text-emerald-700 font-medium inline-flex items-center">
            Manage seasons
            <i class="fa-solid fa-arrow-right ml-1 text-[11px]"></i>
          </span>
        </div>
      </a>

      <!-- Restrictions -->
      <a href="<?= $h($restrUrl) ?>"
         class="group rounded-2xl border border-slate-100 bg-white px-4 py-4 flex flex-col justify-between hover:border-emerald-200 hover:shadow-sm transition">
        <div class="flex items-start justify-between gap-3">
          <div class="flex items-center gap-3">
            <div class="h-10 w-10 rounded-xl flex items-center justify-center bg-indigo-50">
              <i class="fa-solid fa-sliders text-indigo-600"></i>
            </div>
            <div>
              <h3 class="text-sm font-semibold text-slate-900">
                Restrictions (CTA / CTD / LOS)
              </h3>
              <p class="mt-1 text-xs text-slate-500 max-w-[16rem]">
                Control minimum stay, close-to-arrival and close-to-departure
                without touching prices.
              </p>
            </div>
          </div>
          <?php if (!empty($flags['restrictions'])): ?>
            <span class="inline-flex items-center rounded-full bg-emerald-50 px-2 py-0.5 text-[11px] font-medium text-emerald-700">
              Rules active
            </span>
          <?php endif; ?>
        </div>
        <div class="mt-4 flex items-center justify-between text-xs text-slate-500">
          <span>Protect key dates and patterns</span>
          <span class="group-hover:text-emerald-700 font-medium inline-flex items-center">
            Adjust rules
            <i class="fa-solid fa-arrow-right ml-1 text-[11px]"></i>
          </span>
        </div>
      </a>

      <!-- Allotments -->
      <a href="<?= $h($allotUrl) ?>"
         class="group rounded-2xl border border-slate-100 bg-white px-4 py-4 flex flex-col justify-between hover:border-emerald-200 hover:shadow-sm transition">
        <div class="flex items-start justify-between gap-3">
          <div class="flex items-center gap-3">
            <div class="h-10 w-10 rounded-xl flex items-center justify-center bg-sky-50">
              <i class="fa-solid fa-handshake-simple text-sky-600"></i>
            </div>
            <div>
              <h3 class="text-sm font-semibold text-slate-900">
                Allotments &amp; contracts
              </h3>
              <p class="mt-1 text-xs text-slate-500 max-w-[16rem]">
                Block stock for tour operators, corporates or OTAs, and see how
                much is left to sell.
              </p>
            </div>
          </div>
          <?php if (!empty($flags['allotments'])): ?>
            <span class="inline-flex items-center rounded-full bg-emerald-50 px-2 py-0.5 text-[11px] font-medium text-emerald-700">
              Contracts in place
            </span>
          <?php endif; ?>
        </div>
        <div class="mt-4 flex items-center justify-between text-xs text-slate-500">
          <span>Partner-specific inventory</span>
          <span class="group-hover:text-emerald-700 font-medium inline-flex items-center">
            Manage allotments
            <i class="fa-solid fa-arrow-right ml-1 text-[11px]"></i>
          </span>
        </div>
      </a>

      <!-- Yield rules -->
      <a href="<?= $h($yieldUrl) ?>"
         class="group rounded-2xl border border-slate-100 bg-white px-4 py-4 flex flex-col justify-between hover:border-emerald-200 hover:shadow-sm transition">
        <div class="flex items-start justify-between gap-3">
          <div class="flex items-center gap-3">
            <div class="h-10 w-10 rounded-xl flex items-center justify-center bg-emerald-100">
              <i class="fa-solid fa-robot text-emerald-700"></i>
            </div>
            <div>
              <h3 class="text-sm font-semibold text-slate-900">
                Yield rules (automation)
              </h3>
              <p class="mt-1 text-xs text-slate-500 max-w-[16rem]">
                Future-ready engine: define smart rules so prices react to
                occupancy, pickup and events automatically.
              </p>
            </div>
          </div>
          <?php if (!empty($flags['yield_rules'])): ?>
            <span class="inline-flex items-center rounded-full bg-emerald-50 px-2 py-0.5 text-[11px] font-medium text-emerald-700">
              Beta
            </span>
          <?php else: ?>
            <span class="inline-flex items-center rounded-full bg-slate-50 px-2 py-0.5 text-[11px] font-medium text-slate-500">
              Optional
            </span>
          <?php endif; ?>
        </div>
        <div class="mt-4 flex items-center justify-between text-xs text-slate-500">
          <span>Automation layer (optional)</span>
          <span class="group-hover:text-emerald-700 font-medium inline-flex items-center">
            Configure rules
            <i class="fa-solid fa-arrow-right ml-1 text-[11px]"></i>
          </span>
        </div>
      </a>
    </div>
  </section>

  <!-- How to use this page -->
  <section class="mt-6 rounded-2xl border border-slate-100 bg-slate-50 px-4 py-4">
    <h2 class="text-sm font-semibold text-slate-800 flex items-center gap-2">
      <i class="fa-solid fa-circle-question text-slate-500"></i>
      How to use this page
    </h2>
    <ol class="mt-2 text-xs sm:text-sm text-slate-600 space-y-1.5 list-decimal list-inside">
      <li>
        <span class="font-medium">Start with rate plans:</span>
        define BAR, corporate and OTA plans so every price has a “home”.
      </li>
      <li>
        <span class="font-medium">Set base availability:</span>
        use <strong>Availability calendar</strong> to load your normal daily
        allotment by room type.
      </li>
      <li>
        <span class="font-medium">Layer seasons &amp; promotions:</span>
        add <strong>Seasons &amp; overrides</strong> for peak periods, events
        or promotions without touching your base.
      </li>
      <li>
        <span class="font-medium">Protect stays with restrictions:</span>
        configure <strong>CTA/CTD/LOS</strong> so weekends, holidays and events
        follow your minimum-stay strategy.
      </li>
      <li>
        <span class="font-medium">Reserve stock for partners:</span>
        set up <strong>Allotments</strong> for key contracts (NGOs, corporates,
        OTAs) and monitor how much is left for direct channels.
      </li>
      <li>
        <span class="font-medium">Grow into automation:</span>
        when you’re ready, add <strong>Yield rules</strong> so prices can
        respond automatically to occupancy and pickup.
      </li>
    </ol>
  </section>
</div>