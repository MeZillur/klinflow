<?php
/** @var array  $ctx */
/** @var string $module_base ?? */

$h    = fn($v)=>htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
$base = rtrim((string)($module_base ?? ($ctx['module_base'] ?? '/apps/hotelflow')), '/');
?>
<div class="max-w-5xl mx-auto space-y-6">

  <!-- Header + tiny nav -->
  <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
    <div>
      <p class="text-[11px] text-slate-500 mb-1">
        HotelFlow · F&amp;B module
      </p>
      <h1 class="text-2xl font-extrabold tracking-tight flex items-center gap-2">
        Restaurant &amp; F&amp;B
        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-slate-900 text-white text-[11px]">
          <i class="fa-regular fa-hourglass-half"></i>
          <span>Coming soon</span>
        </span>
      </h1>
      <p class="text-slate-500 text-sm mt-1">
        A unified workspace for restaurant orders, in-room dining, POS integration and posting to guest folios —
        designed for the same 2035 standard as the rest of HotelFlow.
      </p>
    </div>

    <div class="flex flex-wrap gap-2 text-sm">
      <a href="<?= $h($base) ?>"
         class="inline-flex items-center gap-2 px-3 py-1.5 rounded-lg border border-slate-300 bg-white hover:bg-slate-50">
        <i class="fa-solid fa-grid-2"></i>
        <span>Back to apps</span>
      </a>
      <a href="<?= $h($base) ?>/frontdesk"
         class="inline-flex items-center gap-2 px-3 py-1.5 rounded-lg border border-slate-200 bg-slate-50 hover:bg-slate-100">
        <i class="fa-solid fa-bell-concierge"></i>
        <span>Frontdesk</span>
      </a>
      <a href="<?= $h($base) ?>/folios"
         class="inline-flex items-center gap-2 px-3 py-1.5 rounded-lg border border-slate-200 bg-slate-50 hover:bg-slate-100">
        <i class="fa-regular fa-file-invoice"></i>
        <span>Folios</span>
      </a>
    </div>
  </div>

  <!-- Main card: Coming soon explanation -->
  <div class="rounded-3xl border border-emerald-200 bg-gradient-to-br from-emerald-50 via-white to-slate-50 p-6 shadow-sm">
    <div class="grid gap-6 md:grid-cols-[1.4fr,1.1fr] md:items-center">

      <!-- Left: text + timeline -->
      <div class="space-y-4">
        <div>
          <h2 class="text-sm font-semibold text-emerald-900 uppercase tracking-wide">
            What this module will do
          </h2>
          <p class="text-xs text-slate-600 mt-1">
            The Restaurant &amp; F&amp;B engine will connect tables, room service and events directly
            with folios and night audit — no more separate systems.
          </p>
        </div>

        <div class="space-y-3 text-xs">
          <div class="flex gap-3">
            <div class="mt-0.5 w-5 flex justify-center">
              <span class="w-2 h-2 rounded-full bg-emerald-600 mt-1"></span>
            </div>
            <div>
              <div class="font-semibold text-slate-900">Phase 1 · Core orders</div>
              <p class="text-slate-600">
                Table &amp; counter orders, item routing to kitchen, simple order history, and posting to guest folios.
              </p>
            </div>
          </div>

          <div class="flex gap-3">
            <div class="mt-0.5 w-5 flex justify-center">
              <span class="w-2 h-2 rounded-full bg-emerald-500 mt-1"></span>
            </div>
            <div>
              <div class="font-semibold text-slate-900">Phase 2 · Room service &amp; packages</div>
              <p class="text-slate-600">
                In-room dining, meal plans (BB, HB, FB), cover charges, and automatic mapping to folio charge codes.
              </p>
            </div>
          </div>

          <div class="flex gap-3">
            <div class="mt-0.5 w-5 flex justify-center">
              <span class="w-2 h-2 rounded-full bg-emerald-400 mt-1"></span>
            </div>
            <div>
              <div class="font-semibold text-slate-900">Phase 3 · Advanced analytics</div>
              <p class="text-slate-600">
                Menu performance, cost vs. revenue tracking, and smart suggestions for menu engineering.
              </p>
            </div>
          </div>
        </div>

        <div class="inline-flex items-center gap-2 rounded-xl border border-dashed border-emerald-300 bg-emerald-50/70 px-3 py-2 text-[11px] text-emerald-900">
          <i class="fa-regular fa-circle-dot"></i>
          <span>
            This is a visual placeholder only. No restaurant data is stored or processed yet.
          </span>
        </div>
      </div>

      <!-- Right: visual placeholder / mock wireframe -->
      <div class="rounded-2xl border border-slate-200 bg-slate-950/95 text-slate-100 p-4 text-[11px]">
        <div class="flex items-center justify-between mb-3">
          <div class="flex items-center gap-2">
            <span class="w-2 h-2 rounded-full bg-emerald-400"></span>
            <span class="font-semibold tracking-wide uppercase text-[10px] text-slate-300">Preview mock</span>
          </div>
          <span class="text-slate-500">Restaurant screen · 2035</span>
        </div>

        <div class="grid gap-3 md:grid-cols-[1.3fr,1fr]">
          <div class="space-y-2">
            <div class="h-7 rounded-lg bg-slate-800 flex items-center justify-between px-2 text-[10px]">
              <span class="text-slate-300">Floor A · Tables</span>
              <span class="text-slate-500">T1 · T2 · T3 · T4</span>
            </div>
            <div class="grid grid-cols-3 gap-2 text-[10px]">
              <div class="h-16 rounded-lg bg-slate-900/80 border border-slate-700 flex flex-col justify-center items-center">
                <span class="font-semibold">T1</span>
                <span class="text-slate-400">2 pax · Open</span>
              </div>
              <div class="h-16 rounded-lg bg-emerald-900/40 border border-emerald-600 flex flex-col justify-center items-center">
                <span class="font-semibold">T2</span>
                <span class="text-emerald-200">3 pax · In service</span>
              </div>
              <div class="h-16 rounded-lg bg-slate-900/80 border border-slate-700 flex flex-col justify-center items-center">
                <span class="font-semibold">T3</span>
                <span class="text-slate-400">Free</span>
              </div>
            </div>
          </div>

          <div class="space-y-2">
            <div class="h-7 rounded-lg bg-slate-800 flex items-center justify-between px-2 text-[10px]">
              <span class="text-slate-300">Current order</span>
              <span class="text-slate-500">#TEMP-001</span>
            </div>
            <div class="rounded-lg bg-slate-900/80 border border-slate-700 p-2 space-y-1">
              <div class="flex items-center justify-between">
                <span>Chicken biryani</span>
                <span class="font-semibold">৳420</span>
              </div>
              <div class="flex items-center justify-between">
                <span>Mojito</span>
                <span class="font-semibold">৳190</span>
              </div>
              <div class="flex items-center justify-between border-t border-slate-700 pt-1 mt-1">
                <span class="text-slate-400">To folio</span>
                <span class="font-semibold text-emerald-300">Room 307</span>
              </div>
            </div>
            <div class="flex gap-2">
              <div class="flex-1 h-7 rounded-lg bg-emerald-700/90 flex items-center justify-center">
                <span>Post to folio</span>
              </div>
              <div class="flex-1 h-7 rounded-lg bg-slate-700 flex items-center justify-center">
                <span>Print bill</span>
              </div>
            </div>
          </div>
        </div>

        <p class="mt-3 text-slate-500">
          This is just a static mock. When the module is ready, this space will become the live restaurant console.
        </p>
      </div>
    </div>
  </div>

  <!-- How to use this page (for now) -->
  <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-[11px] text-slate-700 space-y-1">
    <div class="font-semibold text-slate-900 mb-1">How to use this Restaurant page (Coming soon)</div>
    <ol class="list-decimal list-inside space-y-1">
      <li>Treat this screen as a <strong>visual placeholder</strong> only — no restaurant data is saved yet.</li>
      <li>Use the tiny menu at the top-right to move between <strong>Apps, Frontdesk and Folios</strong> as usual.</li>
      <li>Share this mock with your team or management to confirm how restaurant &amp; room service should work.</li>
      <li>Once the module is developed, this same URL will become the <strong>live Restaurant &amp; F&amp;B console</strong>.</li>
      <li>Any feedback on layout or flow can be collected now, before we connect it to the database.</li>
    </ol>
    <p class="mt-1">
      Tip: Later we can add separate tabs for <strong>Restaurant, Bar, Room service and Events catering</strong> —
      all linked directly with HotelFlow folios and night audit.
    </p>
  </div>
</div>