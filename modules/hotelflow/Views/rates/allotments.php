<?php
/** @var string      $module_base */
/** @var array<int,array> $rows */
/** @var array<string,string> $ddl */

$h    = fn($v)=>htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
$base = rtrim((string)($module_base ?? '/apps/hotelflow'), '/');
$today = date('Y-m-d');

$filterPartner = trim((string)($_GET['partner'] ?? ''));
?>
<div class="max-w-6xl mx-auto space-y-6">

  <!-- SEGMENT 1: Header + mini menu -->
  <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
    <div>
      <h1 class="text-2xl font-extrabold tracking-tight">Allotments</h1>
      <p class="text-sm text-slate-500">
        Fixed room blocks you give to partners (TA/OTA/corporate) for specific date ranges.
      </p>
    </div>

    <!-- Tiny rates navigation -->
    <nav class="flex flex-wrap gap-1 text-xs">
      <a href="<?= $h($base) ?>/rates"
         class="px-3 py-1.5 rounded-full border border-slate-200 text-slate-600 hover:bg-slate-50">
        Summary
      </a>
      <a href="<?= $h($base) ?>/rates/availability"
         class="px-3 py-1.5 rounded-full border border-slate-200 text-slate-600 hover:bg-slate-50">
        Availability
      </a>
      <a href="<?= $h($base) ?>/rates/rate-plans"
         class="px-3 py-1.5 rounded-full border border-slate-200 text-slate-600 hover:bg-slate-50">
        Rate Plans
      </a>
      <a href="<?= $h($base) ?>/rates/overrides"
         class="px-3 py-1.5 rounded-full border border-slate-200 text-slate-600 hover:bg-slate-50">
        Overrides
      </a>
      <a href="<?= $h($base) ?>/rates/restrictions"
         class="px-3 py-1.5 rounded-full border border-slate-200 text-slate-600 hover:bg-slate-50">
        Restrictions
      </a>
      <a href="<?= $h($base) ?>/rates/allotments"
         class="px-3 py-1.5 rounded-full border border-emerald-500 text-emerald-700 bg-emerald-50">
        Allotments
      </a>
      <a href="<?= $h($base) ?>/rates/yield-rules"
         class="px-3 py-1.5 rounded-full border border-slate-200 text-slate-600 hover:bg-slate-50">
        Yield Rules
      </a>
    </nav>
  </div>

  <!-- SEGMENT 2: Top bar – filter + quick add (UI only for now) -->
  <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
    <form method="get" class="flex flex-wrap gap-2 items-center">
      <input type="hidden" name="first" value="rates">
      <input type="hidden" name="second" value="allotments">

      <label class="text-xs text-slate-500">
        Partner / Agency
      </label>
      <input name="partner"
             value="<?= $h($filterPartner) ?>"
             placeholder="e.g. Booking.com, ABC Travel"
             class="w-52 md:w-64 border border-slate-300 rounded-lg px-3 py-1.5 text-sm focus:outline-none focus:ring-1 focus:ring-emerald-500 focus:border-emerald-500">

      <button type="submit"
              class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg border border-slate-300 text-xs font-medium text-slate-700 hover:bg-slate-50">
        <i class="fa-solid fa-magnifying-glass text-slate-500"></i>
        <span>Filter</span>
      </button>
    </form>

    <!-- “Add allotment” placeholder (wiring later) -->
    <button type="button"
            class="inline-flex items-center gap-2 px-4 py-2 rounded-lg text-xs font-semibold text-white shadow-sm hover:shadow-md transition"
            style="background:var(--brand)">
      <i class="fa-solid fa-plus"></i>
      <span>New allotment (coming soon)</span>
    </button>
  </div>

  <!-- SEGMENT 3: Table -->
  <div class="overflow-x-auto rounded-2xl border border-slate-200 bg-white">
    <table class="min-w-full text-sm">
      <thead class="bg-slate-50/70 border-b border-slate-200">
        <tr>
          <th class="px-3 py-2 text-left text-xs font-semibold text-slate-500">Partner</th>
          <th class="px-3 py-2 text-left text-xs font-semibold text-slate-500">Room Type</th>
          <th class="px-3 py-2 text-left text-xs font-semibold text-slate-500">From</th>
          <th class="px-3 py-2 text-left text-xs font-semibold text-slate-500">To</th>
          <th class="px-3 py-2 text-right text-xs font-semibold text-slate-500">Allotment</th>
          <th class="px-3 py-2 text-left text-xs font-semibold text-slate-500">Status</th>
          <th class="px-3 py-2 text-right text-xs font-semibold text-slate-500">Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($rows)): ?>
          <tr>
            <td colspan="7" class="px-3 py-8 text-center text-sm text-slate-500">
              No allotments yet. Start by defining a block for a partner or corporate account.
            </td>
          </tr>
        <?php else: ?>
          <?php foreach ($rows as $row):
            $partner  = (string)($row['partner'] ?? '');
            $rtId     = (int)($row['room_type_id'] ?? 0);
            $from     = (string)($row['from_date'] ?? '');
            $to       = (string)($row['to_date'] ?? '');
            $allot    = (int)($row['allotment'] ?? 0);

            $isPast   = ($to !== '' && $to < $today);
            $status   = $isPast ? 'Expired' : 'Active';
            $badgeCls = $isPast
              ? 'bg-slate-50 text-slate-600 border-slate-200'
              : 'bg-emerald-50 text-emerald-700 border-emerald-200';
          ?>
            <tr class="hover:bg-slate-50/60">
              <td class="px-3 py-2 align-top">
                <div class="font-medium text-slate-900">
                  <?= $h($partner !== '' ? $partner : '—') ?>
                </div>
                <div class="text-[11px] text-slate-500">
                  Partner-based allotment
                </div>
              </td>
              <td class="px-3 py-2 align-top">
                <?php if ($rtId > 0): ?>
                  <span class="inline-flex items-center rounded-full bg-slate-100 px-2 py-0.5 text-[11px] text-slate-700">
                    RT #<?= (int)$rtId ?>
                  </span>
                <?php else: ?>
                  <span class="text-[11px] text-slate-400">All room types</span>
                <?php endif; ?>
              </td>
              <td class="px-3 py-2 align-top">
                <div class="text-slate-900"><?= $h($from) ?></div>
              </td>
              <td class="px-3 py-2 align-top">
                <div class="text-slate-900"><?= $h($to) ?></div>
              </td>
              <td class="px-3 py-2 text-right align-top whitespace-nowrap">
                <span class="font-semibold text-slate-900"><?= (int)$allot ?></span>
                <span class="text-[11px] text-slate-500">rooms</span>
              </td>
              <td class="px-3 py-2 align-top">
                <span class="inline-flex items-center px-2 py-0.5 rounded-full border text-[11px] font-medium <?= $badgeCls ?>">
                  <?= $h($status) ?>
                </span>
              </td>
              <td class="px-3 py-2 text-right align-top whitespace-nowrap space-x-2">
                <button type="button"
                        class="inline-flex items-center px-2.5 py-1.5 rounded-lg border border-slate-300 text-[11px] text-slate-700 hover:bg-slate-100">
                  <i class="fa-regular fa-pen-to-square mr-1"></i>
                  Edit
                </button>
                <button type="button"
                        class="inline-flex items-center px-2.5 py-1.5 rounded-lg border border-rose-200 text-[11px] text-rose-700 hover:bg-rose-50">
                  <i class="fa-regular fa-trash-can mr-1"></i>
                  Remove
                </button>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>

  <!-- SEGMENT 4: How to use this page -->
  <div class="mt-4 rounded-2xl border border-slate-200 bg-slate-50/60 p-4 text-xs text-slate-600 space-y-2">
    <div class="font-semibold text-slate-800 flex items-center gap-2">
      <i class="fa-solid fa-circle-info text-emerald-600"></i>
      <span>How to use the Allotments page</span>
    </div>
    <ul class="list-disc list-inside space-y-1">
      <li><strong>Define contracts:</strong> For each partner, create an allotment with date range and number of rooms.</li>
      <li><strong>Combine with ARI:</strong> Allotments work together with availability and overrides when calculating final inventory.</li>
      <li><strong>Monitor status:</strong> “Active” means current/future; “Expired” blocks are kept for history and reporting.</li>
      <li><strong>Housekeeping link:</strong> Use allotments to forecast expected occupancies and optimise room assignments.</li>
    </ul>
    <p class="pt-1 text-[11px] text-slate-500">
      Later we can wire “New allotment” and “Edit” buttons to full forms and ARI engine so that room inventory auto-syncs with partners.
    </p>
  </div>
</div>