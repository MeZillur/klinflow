<?php
/**
 * @var array       $rows         List of restrictions: id, room_type_id, start_date, end_date, cta, ctd, min_los, max_los
 * @var array|null  $ddl          Optional DDL helper from controller
 * @var string|null $module_base
 * @var array|null  $roomTypes    (optional) [ [id,name], ... ]
 */

$h = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');

$base = isset($module_base)
    ? rtrim((string)$module_base, '/')
    : '/hotel/apps/hotelflow';

$brand = '#228B22';

$ddlSql    = $ddl['hms_rate_restrictions'] ?? null;
$roomTypes = is_array($roomTypes ?? null) ? $roomTypes : [];

$today = date('Y-m-d');
?>
<div class="p-6 space-y-8">

  <!-- Header + micro navigation -->
  <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
    <div>
      <h1 class="text-2xl sm:text-3xl font-bold text-slate-900">
        Restrictions &amp; stay rules
      </h1>
      <p class="mt-1 text-sm text-slate-500 max-w-2xl">
        Control when guests can arrive or depart and how long they must stay
        (CTA / CTD / minimum and maximum length of stay).
      </p>
    </div>

    <div class="flex flex-wrap justify-start lg:justify-end gap-2 text-sm">
      <a href="<?= $h($base) ?>/rates"
         class="inline-flex items-center px-3 py-1.5 rounded-full border border-slate-200 bg-white hover:bg-slate-50">
        <i class="fa-solid fa-gauge-high mr-1"></i> Rates hub
      </a>
      <a href="<?= $h($base) ?>/rates/availability"
         class="inline-flex items-center px-3 py-1.5 rounded-full border border-slate-200 bg-white hover:bg-slate-50">
        <i class="fa-solid fa-calendar-days mr-1"></i> Availability
      </a>
      <a href="<?= $h($base) ?>/rates/rate-plans"
         class="inline-flex items-center px-3 py-1.5 rounded-full border border-slate-200 bg-white hover:bg-slate-50">
        <i class="fa-solid fa-layer-group mr-1"></i> Rate plans
      </a>
      <a href="<?= $h($base) ?>/rates/overrides"
         class="inline-flex items-center px-3 py-1.5 rounded-full border border-slate-200 bg-white hover:bg-slate-50">
        <i class="fa-solid fa-sun-plant-wilt mr-1"></i> Overrides
      </a>
      <a href="<?= $h($base) ?>/rates/allotments"
         class="inline-flex items-center px-3 py-1.5 rounded-full border border-slate-200 bg-white hover:bg-slate-50">
        <i class="fa-solid fa-people-arrows mr-1"></i> Allotments
      </a>
    </div>
  </div>

  <!-- Layout: left = new rule, right = list -->
  <div class="grid gap-6 xl:grid-cols-[minmax(0,360px)_minmax(0,1fr)] items-start">

    <!-- New restriction card -->
    <section class="rounded-2xl border border-slate-100 bg-white shadow-sm p-5 space-y-4">
      <div class="flex items-start justify-between gap-3">
        <div>
          <h2 class="text-sm font-semibold text-slate-900 flex items-center gap-2">
            <span class="inline-flex h-7 w-7 items-center justify-center rounded-xl"
                  style="background: rgba(34,139,34,0.08); color: <?= $h($brand) ?>;">
              <i class="fa-solid fa-road-barrier"></i>
            </span>
            New restriction rule
          </h2>
          <p class="mt-1 text-xs text-slate-500">
            Use CTA/CTD and LOS rules to shape your ideal booking pattern
            (e.g. no arrival on Friday, minimum 2 nights on weekends).
          </p>
        </div>
      </div>

      <form method="post"
            action="<?= $h($base) ?>/rates/restrictions/store"
            class="space-y-4">
        <?php if (function_exists('csrf_field')): ?>
          <?= csrf_field() ?>
        <?php endif; ?>

        <!-- Room type selector -->
        <div class="space-y-1">
          <label class="block text-xs font-medium text-slate-700">
            Room type
          </label>
          <?php if ($roomTypes): ?>
            <select name="room_type_id"
                    class="block w-full rounded-xl border border-slate-200 bg-slate-50/60 px-3 py-2 text-sm
                           focus:border-emerald-500 focus:bg-white focus:outline-none focus:ring-1 focus:ring-emerald-500">
              <option value="">All room types</option>
              <?php foreach ($roomTypes as $rt): ?>
                <option value="<?= (int)$rt['id'] ?>"><?= $h($rt['name'] ?? ('Room type #'.$rt['id'])) ?></option>
              <?php endforeach; ?>
            </select>
          <?php else: ?>
            <input type="number" name="room_type_id"
                   class="block w-full rounded-xl border border-slate-200 bg-slate-50/60 px-3 py-2 text-sm
                          focus:border-emerald-500 focus:bg-white focus:outline-none focus:ring-1 focus:ring-emerald-500"
                   placeholder="Room type ID (optional)">
            <p class="text-[11px] text-slate-400">
              Leave empty to apply this rule to all room types.
            </p>
          <?php endif; ?>
        </div>

        <!-- Date range -->
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
          <div class="space-y-1">
            <label class="block text-xs font-medium text-slate-700">
              Start date
            </label>
            <input type="date" name="start_date"
                   class="block w-full rounded-xl border border-slate-200 bg-slate-50/60 px-3 py-2 text-sm
                          focus:border-emerald-500 focus:bg-white focus:outline-none focus:ring-1 focus:ring-emerald-500"
                   required>
          </div>
          <div class="space-y-1">
            <label class="block text-xs font-medium text-slate-700">
              End date
            </label>
            <input type="date" name="end_date"
                   class="block w-full rounded-xl border border-slate-200 bg-slate-50/60 px-3 py-2 text-sm
                          focus:border-emerald-500 focus:bg-white focus:outline-none focus:ring-1 focus:ring-emerald-500"
                   required>
          </div>
        </div>

        <!-- CTA / CTD toggles -->
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
          <div class="space-y-1">
            <label class="block text-xs font-medium text-slate-700">
              Arrival (CTA)
            </label>
            <select name="cta"
                    class="block w-full rounded-xl border border-slate-200 bg-slate-50/60 px-3 py-2 text-sm
                           focus:border-emerald-500 focus:bg-white focus:outline-none focus:ring-1 focus:ring-emerald-500">
              <option value="0">Allow arrivals</option>
              <option value="1">Close to arrival</option>
            </select>
            <p class="text-[11px] text-slate-400">
              CTA = “Close To Arrival”: guests cannot start their stay on these dates.
            </p>
          </div>

          <div class="space-y-1">
            <label class="block text-xs font-medium text-slate-700">
              Departure (CTD)
            </label>
            <select name="ctd"
                    class="block w-full rounded-xl border border-slate-200 bg-slate-50/60 px-3 py-2 text-sm
                           focus:border-emerald-500 focus:bg-white focus:outline-none focus:ring-1 focus:ring-emerald-500">
              <option value="0">Allow departures</option>
              <option value="1">Close to departure</option>
            </select>
            <p class="text-[11px] text-slate-400">
              CTD = “Close To Departure”: guests cannot check out on these dates.
            </p>
          </div>
        </div>

        <!-- LOS fields -->
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
          <div class="space-y-1">
            <label class="block text-xs font-medium text-slate-700">
              Minimum length of stay (nights)
            </label>
            <input type="number" name="min_los" min="0"
                   class="block w-full rounded-xl border border-slate-200 bg-slate-50/60 px-3 py-2 text-sm
                          focus:border-emerald-500 focus:bg-white focus:outline-none focus:ring-1 focus:ring-emerald-500"
                   placeholder="e.g. 2">
            <p class="text-[11px] text-slate-400">
              Leave empty to keep existing minimum stay rules.
            </p>
          </div>

          <div class="space-y-1">
            <label class="block text-xs font-medium text-slate-700">
              Maximum length of stay (nights)
            </label>
            <input type="number" name="max_los" min="0"
                   class="block w-full rounded-xl border border-slate-200 bg-slate-50/60 px-3 py-2 text-sm
                          focus:border-emerald-500 focus:bg-white focus:outline-none focus:ring-1 focus:ring-emerald-500"
                   placeholder="e.g. 7">
            <p class="text-[11px] text-slate-400">
              Leave empty if you don’t want a maximum.
            </p>
          </div>
        </div>

        <!-- Footer -->
        <div class="flex items-center justify-between pt-2">
          <p class="text-[11px] text-slate-400">
            Rules apply on top of your base rate and overrides. Existing bookings are not changed automatically.
          </p>
          <button type="submit"
                  class="inline-flex items-center gap-2 rounded-xl px-4 py-2 text-xs font-semibold text-white shadow-sm"
                  style="background: <?= $h($brand) ?>;">
            <i class="fa-solid fa-plus text-[11px]"></i>
            Save rule
          </button>
        </div>
      </form>
    </section>

    <!-- Existing rules list -->
    <section class="rounded-2xl border border-slate-100 bg-white shadow-sm overflow-hidden">
      <div class="border-b border-slate-100 px-4 py-3 flex items-center justify-between">
        <div class="flex items-center gap-2 text-sm font-semibold text-slate-800">
          <i class="fa-solid fa-shield-halved" style="color: <?= $h($brand) ?>"></i>
          <span>Active &amp; scheduled restrictions</span>
        </div>
        <div class="text-xs text-slate-400">
          <?= count($rows) ?> rule<?= count($rows) === 1 ? '' : 's' ?>
        </div>
      </div>

      <?php if (!$rows): ?>
        <div class="px-4 py-6 text-center text-sm text-slate-400">
          No restriction rules yet. Start by adding minimum stay or CTA/CTD for busy dates.
        </div>
      <?php else: ?>
        <div class="overflow-x-auto">
          <table class="min-w-full text-xs">
            <thead class="bg-slate-50 text-slate-600">
              <tr>
                <th class="px-3 py-2 text-left font-semibold border-b border-slate-100">Room type</th>
                <th class="px-3 py-2 text-left font-semibold border-b border-slate-100">Dates</th>
                <th class="px-3 py-2 text-center font-semibold border-b border-slate-100">CTA</th>
                <th class="px-3 py-2 text-center font-semibold border-b border-slate-100">CTD</th>
                <th class="px-3 py-2 text-center font-semibold border-b border-slate-100">Min LOS</th>
                <th class="px-3 py-2 text-center font-semibold border-b border-slate-100">Max LOS</th>
                <th class="px-3 py-2 text-center font-semibold border-b border-slate-100 w-32">Status</th>
                <th class="px-3 py-2 text-right font-semibold border-b border-slate-100 w-32">Actions</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
            <?php
              // roomType map
              $rtMap = [];
              foreach ($roomTypes as $rt) {
                  $rtMap[(int)$rt['id']] = (string)($rt['name'] ?? ('#'.$rt['id']));
              }

              foreach ($rows as $r):
                  $id    = (int)($r['id'] ?? 0);
                  $rtId  = isset($r['room_type_id']) ? (int)$r['room_type_id'] : 0;
                  $start = (string)($r['start_date'] ?? '');
                  $end   = (string)($r['end_date'] ?? '');
                  $cta   = (int)($r['cta'] ?? 0);
                  $ctd   = (int)($r['ctd'] ?? 0);
                  $min   = $r['min_los'] !== null ? (int)$r['min_los'] : null;
                  $max   = $r['max_los'] !== null ? (int)$r['max_los'] : null;

                  $statusLabel = 'Upcoming';
                  $statusClass = 'bg-sky-50 text-sky-700 border-sky-200';
                  if ($end !== '' && $end < $today) {
                      $statusLabel = 'Past';
                      $statusClass = 'bg-slate-50 text-slate-500 border-slate-200';
                  } elseif ($start !== '' && $start <= $today && ($end === '' || $end >= $today)) {
                      $statusLabel = 'Active';
                      $statusClass = 'bg-emerald-50 text-emerald-700 border-emerald-200';
                  }
            ?>
              <tr class="hover:bg-slate-50/70">
                <td class="px-3 py-2 align-middle">
                  <?php if ($rtId && isset($rtMap[$rtId])): ?>
                    <span class="font-medium text-slate-900"><?= $h($rtMap[$rtId]) ?></span>
                    <span class="text-[11px] text-slate-400 block">#<?= $rtId ?></span>
                  <?php elseif ($rtId): ?>
                    <span class="font-medium text-slate-900">Room type #<?= $rtId ?></span>
                  <?php else: ?>
                    <span class="text-slate-400">All room types</span>
                  <?php endif; ?>
                </td>

                <td class="px-3 py-2 align-middle text-slate-800">
                  <?php if ($start || $end): ?>
                    <span><?= $h($start) ?> → <?= $h($end) ?></span>
                  <?php else: ?>
                    <span class="text-slate-400">—</span>
                  <?php endif; ?>
                </td>

                <td class="px-3 py-2 align-middle text-center">
                  <?php if ($cta): ?>
                    <span class="inline-flex items-center px-2 py-0.5 rounded-full border border-amber-200 bg-amber-50 text-[11px] font-medium text-amber-700">
                      Closed
                    </span>
                  <?php else: ?>
                    <span class="text-[11px] text-slate-500">Allowed</span>
                  <?php endif; ?>
                </td>

                <td class="px-3 py-2 align-middle text-center">
                  <?php if ($ctd): ?>
                    <span class="inline-flex items-center px-2 py-0.5 rounded-full border border-rose-200 bg-rose-50 text-[11px] font-medium text-rose-700">
                      Closed
                    </span>
                  <?php else: ?>
                    <span class="text-[11px] text-slate-500">Allowed</span>
                  <?php endif; ?>
                </td>

                <td class="px-3 py-2 align-middle text-center">
                  <?php if ($min !== null && $min > 0): ?>
                    <span class="font-medium text-slate-900"><?= $min ?></span>
                  <?php else: ?>
                    <span class="text-slate-400">—</span>
                  <?php endif; ?>
                </td>

                <td class="px-3 py-2 align-middle text-center">
                  <?php if ($max !== null && $max > 0): ?>
                    <span class="font-medium text-slate-900"><?= $max ?></span>
                  <?php else: ?>
                    <span class="text-slate-400">—</span>
                  <?php endif; ?>
                </td>

                <td class="px-3 py-2 align-middle text-center">
                  <span class="inline-flex items-center px-2 py-0.5 rounded-full border text-[11px] font-medium <?= $statusClass ?>">
                    <?= $h($statusLabel) ?>
                  </span>
                </td>

                <td class="px-3 py-2 align-middle text-right whitespace-nowrap">
                  <form method="post"
                        action="<?= $h($base) ?>/rates/restrictions/<?= $id ?>/delete"
                        class="inline-block"
                        onsubmit="return confirm('Delete this restriction rule?');">
                    <?php if (function_exists('csrf_field')): ?>
                      <?= csrf_field() ?>
                    <?php endif; ?>
                    <button type="submit"
                            class="inline-flex items-center gap-1 rounded-lg border border-rose-200 px-2 py-1 text-[11px] font-medium text-rose-700 hover:bg-rose-50">
                      <i class="fa-regular fa-trash-can text-[10px]"></i>
                      Delete
                    </button>
                  </form>
                </td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </section>
  </div>

  <!-- Optional DDL helper -->
  <?php if ($ddlSql): ?>
    <details class="rounded-2xl border border-dashed border-slate-200 bg-slate-50 px-4 py-3 text-xs text-slate-600">
      <summary class="cursor-pointer font-semibold text-slate-700 flex items-center gap-2">
        <i class="fa-solid fa-database text-slate-500"></i>
        Tech note: hms_rate_restrictions table (for admins)
      </summary>
      <p class="mt-2 mb-2 text-[11px] text-slate-500">
        If rules are not saving, ensure
        <code class="font-mono text-[11px]">hms_rate_restrictions</code> exists in the database.
      </p>
      <pre class="mt-1 overflow-x-auto rounded-lg bg-slate-900 text-slate-100 p-3 text-[10px] leading-snug"><?= $h($ddlSql) ?></pre>
    </details>
  <?php endif; ?>

  <!-- How to use this page -->
  <section class="mt-2 rounded-2xl border border-slate-100 bg-slate-50 px-4 py-4">
    <h2 class="text-sm font-semibold text-slate-800 flex items-center gap-2">
      <i class="fa-solid fa-circle-question text-slate-500"></i>
      How to use this page
    </h2>
    <ol class="mt-2 text-xs sm:text-sm text-slate-600 space-y-1.5 list-decimal list-inside">
      <li>
        <span class="font-medium">Decide the pattern you want:</span>
        weekend-only, no same-day departures, longer stays on holidays, etc.
      </li>
      <li>
        Pick a <span class="font-medium">room type</span> (or leave blank for all) and set the
        <span class="font-medium">date range</span> this rule should cover.
      </li>
      <li>
        Use <span class="font-medium">CTA</span> to block arrivals and
        <span class="font-medium">CTD</span> to block departures on specific days.
      </li>
      <li>
        Set <span class="font-medium">Min LOS</span> for events where you want 2+ night stays
        (e.g. New Year, Eid), and <span class="font-medium">Max LOS</span> to avoid very long stays.
      </li>
      <li>
        Watch the <span class="font-medium">status</span> chips:
        “Active” = impacting today, “Upcoming” = future, “Past” = only for history.
      </li>
      <li>
        Review your rules regularly so your ARI remains simple, clear, and “Klin”.
      </li>
    </ol>
  </section>

</div>