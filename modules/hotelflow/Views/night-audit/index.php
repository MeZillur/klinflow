<?php
/** @var array  $ctx */
/** @var string $module_base ?? */
/** @var string $today */
/** @var array  $metrics */

$h     = fn($v)=>htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
$base  = rtrim((string)($module_base ?? ($ctx['module_base'] ?? '/apps/hotelflow')), '/');
$m     = $metrics ?? [];

$arrivals     = (int)($m['arrivals']      ?? 0);
$departures   = (int)($m['departures']    ?? 0);
$inhouse      = (int)($m['inhouse']       ?? 0);
$openFolios   = (int)($m['openFolios']    ?? 0);
$folioBalance = (float)($m['folioBalance'] ?? 0.0);
$dirtyRooms   = (int)($m['dirtyRooms']    ?? 0);
$warnings     = (array)($m['warnings']    ?? []);
?>
<div class="max-w-6xl mx-auto space-y-6">

  <!-- Header + tiny nav -->
  <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
    <div>
      <p class="text-[11px] text-slate-500 mb-1">
        Night audit · <?= $h($today) ?>
      </p>
      <h1 class="text-2xl font-extrabold tracking-tight flex items-center gap-2">
        Night audit desk
        
      </h1>
      <p class="text-slate-500 text-sm mt-1">
        One screen to review arrivals, departures, folios and housekeeping before rolling the hotel to the next business day.
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
      <a href="<?= $h($base) ?>/housekeeping"
         class="inline-flex items-center gap-2 px-3 py-1.5 rounded-lg border border-slate-200 bg-slate-50 hover:bg-slate-100">
        <i class="fa-solid fa-broom"></i>
        <span>Housekeeping</span>
      </a>
      <a href="<?= $h($base) ?>/night-audit/history"
         class="inline-flex items-center gap-2 px-3 py-1.5 rounded-lg border border-slate-200 bg-slate-50 hover:bg-slate-100">
        <i class="fa-regular fa-clock"></i>
        <span>History</span>
      </a>
    </div>
  </div>

  <!-- Main 2-column layout -->
  <div class="grid gap-6 lg:grid-cols-[2.1fr,1.4fr]">

    <!-- LEFT: workflow + checklists -->
    <div class="space-y-4">

      <!-- Tonight’s workflow -->
      <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
        <div class="flex items-center justify-between mb-3">
          <div>
            <h2 class="text-sm font-semibold text-slate-900">Tonight’s workflow</h2>
            <p class="text-xs text-slate-500">
              Run through these steps from left to right before you mark night audit as complete.
            </p>
          </div>
          <div class="flex items-center gap-2 text-[11px] text-slate-500">
            <span>Shortcut:</span>
            <span class="font-mono bg-slate-100 px-1.5 py-0.5 rounded border border-slate-200">N</span>
            <span>to open this desk (future).</span>
          </div>
        </div>

        <div class="grid gap-3 md:grid-cols-3">
          <!-- 1) Arrivals & no-shows -->
          <div class="rounded-xl border border-slate-200 bg-slate-50 p-3 flex flex-col gap-2">
            <div class="flex items-center justify-between">
              <span class="text-xs font-semibold text-slate-900">Arrivals &amp; no-shows</span>
              <i class="fa-regular fa-calendar-check text-slate-500"></i>
            </div>
            <div class="text-2xl font-extrabold text-slate-900">
              <?= $arrivals ?>
            </div>
            <p class="text-[11px] text-slate-600">
              Confirm today’s arrivals are either <strong>checked-in</strong> or marked as <strong>no-show</strong>.
            </p>
            <a href="<?= $h($base) ?>/frontdesk?tab=arrivals"
               class="mt-auto inline-flex items-center gap-1 text-[11px] text-emerald-700 hover:text-emerald-900">
              <span>Open arrivals board</span>
              <i class="fa-solid fa-arrow-right-long text-[10px]"></i>
            </a>
          </div>

          <!-- 2) Folios & balances -->
          <div class="rounded-xl border border-emerald-200 bg-emerald-50/80 p-3 flex flex-col gap-2">
            <div class="flex items-center justify-between">
              <span class="text-xs font-semibold text-emerald-900">Folios &amp; balances</span>
              <i class="fa-regular fa-file-invoice-dollar text-emerald-700"></i>
            </div>
            <div class="text-xs text-emerald-900/80">
              <div class="flex items-center justify-between">
                <span>Open folios</span>
                <span class="font-semibold"><?= $openFolios ?></span>
              </div>
              <div class="flex items-center justify-between mt-1">
                <span>Outstanding</span>
                <span class="font-extrabold">৳<?= number_format($folioBalance, 2) ?></span>
              </div>
            </div>
            <p class="text-[11px] text-emerald-900/80">
              Settle or validate balances for all departures and long-stay in-house guests.
            </p>
            <a href="<?= $h($base) ?>/folios"
               class="mt-auto inline-flex items-center gap-1 text-[11px] text-emerald-800 hover:text-emerald-950">
              <span>Review folios</span>
              <i class="fa-solid fa-arrow-right-long text-[10px]"></i>
            </a>
          </div>

          <!-- 3) Rooms & housekeeping -->
          <div class="rounded-xl border border-slate-200 bg-slate-50 p-3 flex flex-col gap-2">
            <div class="flex items-center justify-between">
              <span class="text-xs font-semibold text-slate-900">Rooms &amp; housekeeping</span>
              <i class="fa-solid fa-bed text-slate-500"></i>
            </div>
            <div class="text-xs text-slate-700">
              <div class="flex items-center justify-between">
                <span>In-house rooms</span>
                <span class="font-semibold"><?= $inhouse ?></span>
              </div>
              <div class="flex items-center justify-between mt-1">
                <span>Dirty rooms</span>
                <span class="font-semibold<?= $dirtyRooms > 0 ? ' text-amber-700' : '' ?>">
                  <?= $dirtyRooms ?>
                </span>
              </div>
            </div>
            <p class="text-[11px] text-slate-600">
              Align with housekeeping so tomorrow’s arrivals have clean rooms and statuses match reality.
            </p>
            <a href="<?= $h($base) ?>/housekeeping"
               class="mt-auto inline-flex items-center gap-1 text-[11px] text-slate-700 hover:text-slate-900">
              <span>Open housekeeping board</span>
              <i class="fa-solid fa-arrow-right-long text-[10px]"></i>
            </a>
          </div>
        </div>
      </div>

      <!-- Checklist -->
      <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
        <div class="flex items-center justify-between mb-3">
          <div>
            <h2 class="text-sm font-semibold text-slate-900">Night audit checklist</h2>
            <p class="text-xs text-slate-500">
              A quick digital checklist so every shift runs the same clean process — no matter who is on duty.
            </p>
          </div>
          <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full bg-slate-900 text-white text-[11px]">
            <i class="fa-regular fa-moon"></i>
            <span>Standard operating flow</span>
          </span>
        </div>

        <div class="grid gap-3 sm:grid-cols-2 text-xs">
          <div class="space-y-2">
            <label class="flex items-start gap-2">
              <input type="checkbox" class="mt-0.5 rounded border-slate-300 text-emerald-600">
              <span>
                All <strong>arrivals</strong> have either valid check-in time or are marked as no-show / cancelled.
              </span>
            </label>

            <label class="flex items-start gap-2">
              <input type="checkbox" class="mt-0.5 rounded border-slate-300 text-emerald-600">
              <span>
                All <strong>departures</strong> have closed folios and keycards are deactivated.
              </span>
            </label>

            <label class="flex items-start gap-2">
              <input type="checkbox" class="mt-0.5 rounded border-slate-300 text-emerald-600">
              <span>
                In-house rooms match the <strong>Frontdesk</strong> board (no ghost rooms).
              </span>
            </label>
          </div>

          <div class="space-y-2">
            <label class="flex items-start gap-2">
              <input type="checkbox" class="mt-0.5 rounded border-slate-300 text-emerald-600">
              <span>
                High-balance folios reviewed; all unusual amounts explained in notes.
              </span>
            </label>

            <label class="flex items-start gap-2">
              <input type="checkbox" class="mt-0.5 rounded border-slate-300 text-emerald-600">
              <span>
                Cash / card summaries match POS terminals and physical cash drawers.
              </span>
            </label>

            <label class="flex items-start gap-2">
              <input type="checkbox" class="mt-0.5 rounded border-slate-300 text-emerald-600">
              <span>
                Housekeeping &amp; maintenance notes logged for any out-of-order rooms.
              </span>
            </label>
          </div>
        </div>
      </div>
    </div>

    <!-- RIGHT: KPIs, warnings, action -->
    <div class="space-y-4">

      <!-- KPI card -->
      <div class="rounded-2xl border border-emerald-200 bg-emerald-50/70 p-4 shadow-sm">
        <h2 class="text-sm font-semibold text-emerald-900">Tonight’s KPIs</h2>
        <p class="text-xs text-emerald-900/80 mt-0.5">
          High-level snapshot before you decide whether it is safe to roll the business date.
        </p>

        <dl class="mt-3 space-y-1 text-xs text-emerald-900/90">
          <div class="flex items-center justify-between">
            <dt>Arrivals today</dt>
            <dd class="font-semibold"><?= $arrivals ?></dd>
          </div>
          <div class="flex items-center justify-between">
            <dt>Departures today</dt>
            <dd class="font-semibold"><?= $departures ?></dd>
          </div>
          <div class="flex items-center justify-between">
            <dt>In-house at close</dt>
            <dd class="font-semibold"><?= $inhouse ?></dd>
          </div>
          <div class="flex items-center justify-between">
            <dt>Open folios</dt>
            <dd class="font-semibold"><?= $openFolios ?></dd>
          </div>
          <div class="flex items-center justify-between">
            <dt>Outstanding balance (approx)</dt>
            <dd class="font-extrabold text-emerald-950">
              ৳<?= number_format($folioBalance, 2) ?>
            </dd>
          </div>
          <div class="flex items-center justify-between">
            <dt>Dirty rooms</dt>
            <dd class="font-semibold<?= $dirtyRooms > 0 ? ' text-amber-800' : '' ?>">
              <?= $dirtyRooms ?>
            </dd>
          </div>
        </dl>

        <div class="mt-3 flex flex-wrap gap-2 text-[11px]">
          <button type="button"
                  class="inline-flex items-center gap-1 px-3 py-1.5 rounded-lg bg-emerald-600 text-white font-semibold hover:bg-emerald-700">
            <i class="fa-regular fa-eye"></i>
            <span>Preview audit (stub)</span>
          </button>
          <button type="button"
                  class="inline-flex items-center gap-1 px-3 py-1.5 rounded-lg border border-emerald-300 bg-emerald-50 text-emerald-800 hover:bg-emerald-100">
            <i class="fa-solid fa-play"></i>
            <span>Close day (future)</span>
          </button>
        </div>

        <p class="mt-2 text-[11px] text-emerald-900/75">
          For now these buttons are visual only — later we can wire them to generate GL batches and roll the business date.
        </p>
      </div>

      <!-- Warnings / messages -->
      <div class="rounded-2xl border border-amber-200 bg-amber-50 p-4 shadow-sm">
        <div class="flex items-center gap-2 mb-2">
          <i class="fa-solid fa-triangle-exclamation text-amber-700 text-sm"></i>
          <h2 class="text-sm font-semibold text-amber-900">Things to double-check</h2>
        </div>
        <ul class="space-y-1 text-[11px] text-amber-900/90">
          <?php foreach ($warnings as $w): ?>
            <li>• <?= $h($w) ?></li>
          <?php endforeach; ?>
        </ul>
      </div>

      <!-- How to use this page -->
      <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-[11px] text-slate-700 space-y-1">
        <div class="font-semibold text-slate-900 mb-1">How to use this Night audit page</div>
        <ol class="list-decimal list-inside space-y-1">
          <li>Start your shift on this screen to see <strong>arrivals, departures, in-house and folio balance</strong> at a glance.</li>
          <li>Use the <strong>workflow cards</strong> to jump into Frontdesk, Folios and Housekeeping for deeper checks.</li>
          <li>Tick off the <strong>Night audit checklist</strong> as you complete each standard step.</li>
          <li>Review the <strong>KPI card</strong> and warnings — if something looks off, fix it before closing the day.</li>
          <li>Once everything is clean, use the <strong>Preview / Close day</strong> buttons (future) to generate reports and roll the business date.</li>
        </ol>
        <p class="mt-1">
          Tip: In future we can attach a downloadable PDF &amp; auto-email of night audit reports to management straight from here.
        </p>
      </div>
    </div>
  </div>
</div>