<?php
/** @var string      $ym        YYYY-MM */
/** @var int         $days      number of days in month */
/** @var string      $first     first day YYYY-MM-01 */
/** @var array       $roomTypes [id,name] */
/** @var array       $data      rows from hms_rate_availability */
/** @var array|null  $ddl       optional DDL hints */
/** @var string|null $module_base */

$h = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');

$base = isset($module_base)
    ? rtrim((string)$module_base, '/')
    : '/hotel/apps/hotelflow';

$brand = '#228B22';

$curTs   = strtotime($first);
$prevYm  = date('Y-m', strtotime('-1 month', $curTs));
$nextYm  = date('Y-m', strtotime('+1 month', $curTs));
$monthUi = date('F Y', $curTs);

// Index availability by [room_type_id][date]
$grid = [];
foreach ($data as $row) {
    $rtId = (int)($row['room_type_id'] ?? 0);
    $d    = (string)($row['date'] ?? '');
    if ($rtId && $d !== '') {
        $grid[$rtId][$d] = $row;
    }
}

$ddlSql = $ddl['hms_rate_availability'] ?? null;
?>
<div class="p-6 space-y-8">

  <!-- Top bar: title + micro-nav -->
  <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
    <div>
      <h1 class="text-2xl sm:text-3xl font-bold text-slate-900">
        Availability calendar
      </h1>
      <p class="mt-1 text-sm text-slate-500 max-w-2xl">
        See your room-type allotment for the month, track sold rooms, and close dates in one compact grid.
      </p>
    </div>

    <!-- Tiny related menu -->
    <div class="flex flex-wrap justify-start lg:justify-end gap-2 text-sm">
      <a href="<?= $h($base) ?>/rates"
         class="inline-flex items-center px-3 py-1.5 rounded-full border border-slate-200 bg-white hover:bg-slate-50">
        <i class="fa-solid fa-gauge-high mr-1"></i> Rates hub
      </a>
      <a href="<?= $h($base) ?>/rates/rate-plans"
         class="inline-flex items-center px-3 py-1.5 rounded-full border border-slate-200 bg-white hover:bg-slate-50">
        <i class="fa-solid fa-tags mr-1"></i> Rate plans
      </a>
      <a href="<?= $h($base) ?>/rooms"
         class="inline-flex items-center px-3 py-1.5 rounded-full border border-slate-200 bg-white hover:bg-slate-50">
        <i class="fa-regular fa-building mr-1"></i> Rooms
      </a>
    </div>
  </div>

  <!-- Month switcher + legend -->
  <div class="flex flex-col xl:flex-row xl:items-center xl:justify-between gap-4">

    <!-- Month controls -->
    <div class="inline-flex items-center gap-3">
      <a href="<?= $h($base) ?>/rates/availability?ym=<?= $h($prevYm) ?>"
         class="inline-flex items-center justify-center h-9 w-9 rounded-full border border-slate-200 bg-white hover:bg-slate-50"
         title="Previous month">
        <i class="fa-solid fa-chevron-left text-slate-600"></i>
      </a>

      <div class="px-3 py-1 rounded-full bg-slate-900 text-white text-sm font-medium flex items-center gap-2">
        <i class="fa-solid fa-calendar-days text-xs"></i>
        <?= $h($monthUi) ?>
      </div>

      <a href="<?= $h($base) ?>/rates/availability?ym=<?= $h($nextYm) ?>"
         class="inline-flex items-center justify-center h-9 w-9 rounded-full border border-slate-200 bg-white hover:bg-slate-50"
         title="Next month">
        <i class="fa-solid fa-chevron-right text-slate-600"></i>
      </a>
    </div>

    <!-- Legend -->
    <div class="flex flex-wrap gap-3 text-xs text-slate-500">
      <div class="inline-flex items-center gap-1.5">
        <span class="h-3 w-3 rounded-full bg-emerald-50 border border-emerald-100"></span>
        <span>Low pickup</span>
      </div>
      <div class="inline-flex items-center gap-1.5">
        <span class="h-3 w-3 rounded-full bg-amber-50 border border-amber-100"></span>
        <span>Medium pickup</span>
      </div>
      <div class="inline-flex items-center gap-1.5">
        <span class="h-3 w-3 rounded-full bg-rose-50 border border-rose-100"></span>
        <span>High pickup</span>
      </div>
      <div class="inline-flex items-center gap-1.5">
        <span class="h-3 w-3 rounded-full bg-rose-600"></span>
        <span>Closed</span>
      </div>
    </div>
  </div>

  <!-- Calendar grid -->
  <div class="rounded-2xl border border-slate-100 bg-white overflow-hidden shadow-sm">
    <div class="border-b border-slate-100 px-4 py-3 flex items-center justify-between">
      <div class="text-sm font-semibold text-slate-800 flex items-center gap-2">
        <i class="fa-solid fa-table-cells-large text-[<?= $brand ?>]"></i>
        Month view per room type
      </div>
      <div class="text-xs text-slate-400">
        Scroll horizontally to see all days →
      </div>
    </div>

    <div class="overflow-x-auto">
      <table class="min-w-full text-xs border-t border-slate-100">
        <thead class="bg-slate-50 text-slate-600">
          <tr>
            <!-- Left sticky column -->
            <th class="sticky left-0 z-20 bg-slate-50 border-b border-r border-slate-100 px-3 py-2 text-left text-[11px] font-semibold uppercase tracking-wide w-40">
              Room type
            </th>
            <?php
            for ($i = 0; $i < $days; $i++):
                $dTs   = strtotime($first . " +{$i} day");
                $dNum  = date('d', $dTs);
                $dDow  = date('D', $dTs);
                $isWknd = in_array($dDow, ['Fri','Sat'], true);
            ?>
              <th class="border-b border-slate-100 px-2 py-2 text-center text-[10px] font-medium <?= $isWknd ? 'bg-slate-100' : '' ?>">
                <div><?= $h($dNum) ?></div>
                <div class="text-[10px] text-slate-400"><?= $h($dDow) ?></div>
              </th>
            <?php endfor; ?>
          </tr>
        </thead>

        <tbody class="divide-y divide-slate-100">
        <?php if (!$roomTypes): ?>
          <tr>
            <td colspan="<?= $days + 1 ?>" class="px-4 py-6 text-center text-slate-400 text-xs">
              No room types found yet. Configure your room types first, then return here to control availability.
            </td>
          </tr>
        <?php else: ?>
          <?php foreach ($roomTypes as $rt): 
              $rtId   = (int)($rt['id'] ?? 0);
              $rtName = (string)($rt['name'] ?? 'Room type');
          ?>
            <tr class="hover:bg-slate-50/70">
              <!-- Sticky type column -->
              <th scope="row"
                  class="sticky left-0 z-10 bg-white border-r border-slate-100 px-3 py-2 text-left align-top">
                <div class="text-[13px] font-semibold text-slate-900">
                  <?= $h($rtName) ?>
                </div>
                <div class="mt-0.5 text-[11px] text-slate-400">
                  <?= $h("ID #{$rtId}") ?>
                </div>
              </th>

              <?php
              for ($i = 0; $i < $days; $i++):
                  $dTs   = strtotime($first . " +{$i} day");
                  $dStr  = date('Y-m-d', $dTs);
                  $cell  = $grid[$rtId][$dStr] ?? null;

                  $allot = (int)($cell['allotment'] ?? 0);
                  $sold  = (int)($cell['sold'] ?? 0);
                  $closed = !empty($cell['closed']);

                  $rem   = max(0, $allot - $sold);
                  $heatClass = 'bg-slate-50';

                  if ($closed) {
                      $heatClass = 'bg-rose-600 text-white';
                  } elseif ($allot > 0) {
                      $occ = $sold / $allot;
                      if ($occ < 0.35) {
                          $heatClass = 'bg-emerald-50';
                      } elseif ($occ < 0.75) {
                          $heatClass = 'bg-amber-50';
                      } else {
                          $heatClass = 'bg-rose-50';
                      }
                  }
              ?>
                <td class="align-top border-t border-slate-50 px-1.5 py-1.5">
                  <div class="rounded-xl <?= $heatClass ?> px-2 py-1.5 min-w-[72px] text-[11px] flex flex-col gap-0.5">
                    <?php if ($closed): ?>
                      <div class="flex items-center justify-between">
                        <span class="font-semibold">
                          Closed
                        </span>
                        <i class="fa-solid fa-lock text-[10px]"></i>
                      </div>
                    <?php elseif ($allot === 0): ?>
                      <div class="text-slate-400 text-[11px]">
                        —
                      </div>
                    <?php else: ?>
                      <div class="flex items-center justify-between">
                        <span class="font-semibold text-slate-900">
                          <?= $h($rem) ?> left
                        </span>
                        <span class="text-[10px] text-slate-500">
                          of <?= $h($allot) ?>
                        </span>
                      </div>
                      <div class="flex items-center justify-between text-[10px] text-slate-500">
                        <span>
                          Sold: <?= $h($sold) ?>
                        </span>
                        <span>
                          Occ: <?= $h(number_format($allot > 0 ? ($sold / $allot * 100) : 0, 0)) ?>%
                        </span>
                      </div>
                    <?php endif; ?>
                  </div>
                </td>
              <?php endfor; ?>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- Optional DDL help -->
  <?php if ($ddlSql): ?>
    <details class="rounded-2xl border border-dashed border-slate-200 bg-slate-50 px-4 py-3 text-xs text-slate-600">
      <summary class="cursor-pointer font-semibold text-slate-700 flex items-center gap-2">
        <i class="fa-solid fa-database text-slate-500"></i>
        Tech note: availability table (for admins)
      </summary>
      <p class="mt-2 mb-2 text-[11px] text-slate-500">
        If this calendar never shows data, your database may be missing the
        <code class="font-mono text-[11px]">hms_rate_availability</code> table.
        A DBA can provision it using the SQL below.
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
        <span class="font-medium">Choose the month</span> using the arrows at the top.
        The grid will reload for that month.
      </li>
      <li>
        Each <span class="font-medium">row</span> is a room type. Each <span class="font-medium">column</span> is
        a calendar day for the selected month.
      </li>
      <li>
        Cells show <span class="font-medium">remaining rooms</span>, total allotment, and
        <span class="font-medium">occupancy %</span>. Colours help you scan pickup quickly.
      </li>
      <li>
        A <span class="font-medium">red “Closed”</span> cell means this room type is not bookable
        on that date (for any or specific channels, depending on your channel mapping).
      </li>
      <li>
        To change availability rules (allotment, closed dates, overrides),
        use the other sections from the <span class="font-medium">Rates hub</span>:
        Rate plans, Seasons/overrides, Restrictions and Allotments.
      </li>
      <li>
        Revisit this page daily during high demand periods (Eid, peak season,
        events) to keep pickup healthy and avoid overselling.
      </li>
    </ol>
  </section>
</div>