<?php
/**
 * @var array       $rows         List of overrides: id, room_type_id, rate_plan_id, start_date, end_date, price
 * @var array|null  $ddl          Optional DDL helper from controller
 * @var string|null $module_base
 * @var array|null  $roomTypes    (optional) [ [id,name], ... ]
 * @var array|null  $ratePlans    (optional) [ [id,name,code], ... ]
 */

$h = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');

$base = isset($module_base)
    ? rtrim((string)$module_base, '/')
    : '/hotel/apps/hotelflow';

$brand = '#228B22';

$ddlSql = $ddl['hms_rate_overrides'] ?? null;
$roomTypes = is_array($roomTypes ?? null) ? $roomTypes : [];
$ratePlans = is_array($ratePlans ?? null) ? $ratePlans : [];

$today = date('Y-m-d');
?>
<div class="p-6 space-y-8">

  <!-- Top bar / header -->
  <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
    <div>
      <h1 class="text-2xl sm:text-3xl font-bold text-slate-900">
        Seasonal overrides &amp; campaigns
      </h1>
      <p class="mt-1 text-sm text-slate-500 max-w-2xl">
        Configure special prices for specific dates, seasons, or campaigns
        (Eid offers, winter discounts, weekend promos) on top of your base rate plans.
      </p>
    </div>

    <!-- Tiny related menu -->
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
      <a href="<?= $h($base) ?>/rates/restrictions"
         class="inline-flex items-center px-3 py-1.5 rounded-full border border-slate-200 bg-white hover:bg-slate-50">
        <i class="fa-solid fa-ban mr-1"></i> Restrictions
      </a>
      <a href="<?= $h($base) ?>/rates/allotments"
         class="inline-flex items-center px-3 py-1.5 rounded-full border border-slate-200 bg-white hover:bg-slate-50">
        <i class="fa-solid fa-people-arrows mr-1"></i> Allotments
      </a>
    </div>
  </div>

  <!-- Main layout: new override (left) + list (right) -->
  <div class="grid gap-6 xl:grid-cols-[minmax(0,360px)_minmax(0,1fr)] items-start">

    <!-- Create override card -->
    <section class="rounded-2xl border border-slate-100 bg-white shadow-sm p-5 space-y-4">
      <div class="flex items-start justify-between gap-3">
        <div>
          <h2 class="text-sm font-semibold text-slate-900 flex items-center gap-2">
            <span class="inline-flex h-7 w-7 items-center justify-center rounded-xl"
                  style="background: rgba(34,139,34,0.08); color: <?= $h($brand) ?>;">
              <i class="fa-solid fa-sun-plant-wilt"></i>
            </span>
            New seasonal override
          </h2>
          <p class="mt-1 text-xs text-slate-500">
            Apply a fixed price for a date range. Ideal for Eid, New Year, weekend, or long-stay promos.
          </p>
        </div>
      </div>

      <form method="post"
            action="<?= $h($base) ?>/rates/overrides/store"
            class="space-y-4">

        <?php if (function_exists('csrf_field')): ?>
          <?= csrf_field() ?>
        <?php endif; ?>

        <!-- Room type + rate plan -->
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
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
                  <option value="<?= (int)$rt['id'] ?>"><?= $h($rt['name'] ?? ('#'.$rt['id'])) ?></option>
                <?php endforeach; ?>
              </select>
            <?php else: ?>
              <input type="number" name="room_type_id"
                     class="block w-full rounded-xl border border-slate-200 bg-slate-50/60 px-3 py-2 text-sm
                            focus:border-emerald-500 focus:bg-white focus:outline-none focus:ring-1 focus:ring-emerald-500"
                     placeholder="Room type ID (optional)">
              <p class="text-[11px] text-slate-400">
                Leave empty to affect all room types.
              </p>
            <?php endif; ?>
          </div>

          <div class="space-y-1">
            <label class="block text-xs font-medium text-slate-700">
              Rate plan
            </label>
            <?php if ($ratePlans): ?>
              <select name="rate_plan_id"
                      class="block w-full rounded-xl border border-slate-200 bg-slate-50/60 px-3 py-2 text-sm
                             focus:border-emerald-500 focus:bg-white focus:outline-none focus:ring-1 focus:ring-emerald-500">
                <option value="">All rate plans</option>
                <?php foreach ($ratePlans as $rp): ?>
                  <?php
                    $label = trim(($rp['name'] ?? '') . ' ' . ($rp['code'] ? '('.$rp['code'].')' : ''));
                  ?>
                  <option value="<?= (int)$rp['id'] ?>"><?= $h($label !== '' ? $label : '#'.$rp['id']) ?></option>
                <?php endforeach; ?>
              </select>
            <?php else: ?>
              <input type="number" name="rate_plan_id"
                     class="block w-full rounded-xl border border-slate-200 bg-slate-50/60 px-3 py-2 text-sm
                            focus:border-emerald-500 focus:bg-white focus:outline-none focus:ring-1 focus:ring-emerald-500"
                     placeholder="Rate plan ID (optional)">
              <p class="text-[11px] text-slate-400">
                Leave empty to affect all rate plans.
              </p>
            <?php endif; ?>
          </div>
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

        <!-- Price -->
        <div class="space-y-1">
          <label class="block text-xs font-medium text-slate-700">
            Override price (per night)
          </label>
          <div class="flex items-center gap-2">
            <span class="inline-flex items-center rounded-lg border border-slate-200 bg-slate-50 px-2 text-xs text-slate-500">
              BDT
            </span>
            <input type="number" step="0.01" min="0" name="price"
                   class="block w-full rounded-xl border border-slate-200 bg-slate-50/60 px-3 py-2 text-sm
                          focus:border-emerald-500 focus:bg-white focus:outline-none focus:ring-1 focus:ring-emerald-500"
                   placeholder="e.g. 7500.00" required>
          </div>
          <p class="text-[11px] text-slate-400">
            This price replaces the base rate for the selected dates (before taxes / service charges).
          </p>
        </div>

        <!-- Footer -->
        <div class="flex items-center justify-between pt-2">
          <p class="text-[11px] text-slate-400">
            Changes affect new bookings going forward. Existing reservations are not auto-updated.
          </p>
          <button type="submit"
                  class="inline-flex items-center gap-2 rounded-xl px-4 py-2 text-xs font-semibold text-white shadow-sm"
                  style="background: <?= $h($brand) ?>;">
            <i class="fa-solid fa-sparkles text-[11px]"></i>
            Create override
          </button>
        </div>
      </form>
    </section>

    <!-- Existing overrides list -->
    <section class="rounded-2xl border border-slate-100 bg-white shadow-sm overflow-hidden">
      <div class="border-b border-slate-100 px-4 py-3 flex items-center justify-between">
        <div class="flex items-center gap-2 text-sm font-semibold text-slate-800">
          <i class="fa-solid fa-calendar-star text-[<?= $h($brand) ?>]"></i>
          <span>Active &amp; scheduled overrides</span>
        </div>
        <div class="text-xs text-slate-400">
          <?= count($rows) ?> record<?= count($rows) === 1 ? '' : 's' ?>
        </div>
      </div>

      <?php if (!$rows): ?>
        <div class="px-4 py-6 text-center text-sm text-slate-400">
          No overrides yet. Create your first seasonal campaign on the left.
        </div>
      <?php else: ?>
        <div class="overflow-x-auto">
          <table class="min-w-full text-xs">
            <thead class="bg-slate-50 text-slate-600">
              <tr>
                <th class="px-3 py-2 text-left font-semibold border-b border-slate-100">Room type</th>
                <th class="px-3 py-2 text-left font-semibold border-b border-slate-100">Rate plan</th>
                <th class="px-3 py-2 text-left font-semibold border-b border-slate-100">Dates</th>
                <th class="px-3 py-2 text-right font-semibold border-b border-slate-100">Price (BDT)</th>
                <th class="px-3 py-2 text-center font-semibold border-b border-slate-100 w-32">Status</th>
                <th class="px-3 py-2 text-right font-semibold border-b border-slate-100 w-32">Actions</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
            <?php
              // small lookup maps if arrays present
              $rtMap = [];
              foreach ($roomTypes as $rt) {
                  $rtMap[(int)$rt['id']] = (string)($rt['name'] ?? ('#'.$rt['id']));
              }
              $rpMap = [];
              foreach ($ratePlans as $rp) {
                  $label = trim(($rp['name'] ?? '') . ' ' . (($rp['code'] ?? '') !== '' ? '(' . $rp['code'] . ')' : ''));
                  $rpMap[(int)$rp['id']] = $label !== '' ? $label : ('#'.$rp['id']);
              }

              foreach ($rows as $r):
                  $id   = (int)($r['id'] ?? 0);
                  $rtId = isset($r['room_type_id']) ? (int)$r['room_type_id'] : 0;
                  $rpId = isset($r['rate_plan_id']) ? (int)$r['rate_plan_id'] : 0;
                  $start = (string)($r['start_date'] ?? '');
                  $end   = (string)($r['end_date'] ?? '');
                  $price = (float)($r['price'] ?? 0);

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
                <td class="px-3 py-2 align-middle">
                  <?php if ($rpId && isset($rpMap[$rpId])): ?>
                    <span class="font-medium text-slate-900"><?= $h($rpMap[$rpId]) ?></span>
                    <span class="text-[11px] text-slate-400 block">#<?= $rpId ?></span>
                  <?php elseif ($rpId): ?>
                    <span class="font-medium text-slate-900">Plan #<?= $rpId ?></span>
                  <?php else: ?>
                    <span class="text-slate-400">All rate plans</span>
                  <?php endif; ?>
                </td>
                <td class="px-3 py-2 align-middle text-slate-800">
                  <?php if ($start || $end): ?>
                    <span><?= $h($start) ?> → <?= $h($end) ?></span>
                  <?php else: ?>
                    <span class="text-slate-400">—</span>
                  <?php endif; ?>
                </td>
                <td class="px-3 py-2 align-middle text-right">
                  <span class="font-semibold"><?= number_format($price, 2) ?></span>
                </td>
                <td class="px-3 py-2 align-middle text-center">
                  <span class="inline-flex items-center px-2 py-0.5 rounded-full border text-[11px] font-medium <?= $statusClass ?>">
                    <?= $h($statusLabel) ?>
                  </span>
                </td>
                <td class="px-3 py-2 align-middle text-right whitespace-nowrap">
                  <form method="post"
                        action="<?= $h($base) ?>/rates/overrides/<?= $id ?>/delete"
                        class="inline-block"
                        onsubmit="return confirm('Delete this override?');">
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
        Tech note: hms_rate_overrides table (for admins)
      </summary>
      <p class="mt-2 mb-2 text-[11px] text-slate-500">
        If overrides are not saving, ensure
        <code class="font-mono text-[11px]">hms_rate_overrides</code> exists in the database.
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
        <span class="font-medium">Start from a clear idea:</span>
        weekend offer, Eid package, winter promo, or long-stay discount.
      </li>
      <li>
        Choose a <span class="font-medium">room type</span> and/or <span class="font-medium">rate plan</span>,
        or leave them blank to affect everything.
      </li>
      <li>
        Set the <span class="font-medium">date range</span> when this price should apply.
        The system will use this price instead of the base rate for those nights.
      </li>
      <li>
        Keep prices in <span class="font-medium">BDT</span> and round neatly (e.g. 6,500 / 7,500)
        so front desk and guests find it easy to communicate.
      </li>
      <li>
        Watch the <span class="font-medium">status badges</span>:
        “Active” means today is inside the override; “Upcoming” is future; “Past” is history only.
      </li>
      <li>
        Clean up old tests and campaigns regularly so your ARI stays lean and “Klin”.
      </li>
    </ol>
  </section>

</div>