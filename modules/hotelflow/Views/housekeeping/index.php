<?php
/** @var array  $ctx */
/** @var string $module_base ?? */
/** @var array  $rooms ?? */
/** @var array  $summary ?? */

$h    = fn($v)=>htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
$base = rtrim((string)($module_base ?? ($ctx['module_base'] ?? '/apps/hotelflow')), '/');

$rooms   = $rooms   ?? [];
$summary = $summary ?? [
    'dirty'          => 0,
    'in_progress'    => 0,
    'clean'          => 0,
    'inspected'      => 0,
    'out_of_service' => 0,
];
$totalRooms = array_sum($summary);

/* Badge + label helpers */
$badge = function (string $status): string {
    switch ($status) {
        case 'dirty':
            return 'bg-rose-50 text-rose-700 border-rose-200';
        case 'in_progress':
            return 'bg-amber-50 text-amber-800 border-amber-200';
        case 'clean':
            return 'bg-emerald-50 text-emerald-700 border-emerald-200';
        case 'inspected':
            return 'bg-sky-50 text-sky-700 border-sky-200';
        case 'out_of_service':
            return 'bg-slate-100 text-slate-700 border-slate-300';
        default:
            return 'bg-slate-50 text-slate-700 border-slate-200';
    }
};
$label = function (string $status): string {
    return match ($status) {
        'dirty'          => 'Dirty',
        'in_progress'    => 'In progress',
        'clean'          => 'Clean',
        'inspected'      => 'Inspected',
        'out_of_service' => 'Out of service',
        default          => ucfirst($status),
    };
};
?>
<div class="max-w-6xl mx-auto space-y-6">

  <!-- Header + tiny nav -->
  <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
    <div>
      <h1 class="text-2xl font-extrabold tracking-tight">Housekeeping board</h1>
      <p class="text-slate-500 text-sm">
        Live view of room cleaning status, priorities and assignments — designed for 2035 frontdesk + HK teams.
      </p>
    </div>

    <div class="flex flex-wrap gap-2 text-sm">
      <a href="<?= $h($base) ?>/frontdesk"
         class="inline-flex items-center gap-2 px-3 py-1.5 rounded-lg border border-slate-300 bg-white hover:bg-slate-50">
        <i class="fa-solid fa-bell-concierge"></i>
        <span>Frontdesk</span>
      </a>
      <a href="<?= $h($base) ?>/rooms"
         class="inline-flex items-center gap-2 px-3 py-1.5 rounded-lg border border-slate-200 bg-slate-50 hover:bg-slate-100">
        <i class="fa-solid fa-bed"></i>
        <span>Rooms grid</span>
      </a>
      <a href="<?= $h($base) ?>/frontdesk/room-status"
         class="inline-flex items-center gap-2 px-3 py-1.5 rounded-lg border border-slate-200 bg-slate-50 hover:bg-slate-100">
        <i class="fa-regular fa-rectangle-list"></i>
        <span>Room status</span>
      </a>
    </div>
  </div>

  <!-- KPI + filters -->
  <div class="grid gap-4 lg:grid-cols-[2.2fr,1.8fr]">

    <!-- KPI cards -->
    <div class="grid gap-3 sm:grid-cols-3">
      <div class="rounded-2xl border border-rose-200 bg-rose-50 p-4">
        <div class="flex items-center justify-between">
          <span class="text-xs font-medium text-rose-900">Dirty rooms</span>
          <i class="fa-solid fa-broom text-rose-700"></i>
        </div>
        <div class="mt-2 text-2xl font-extrabold text-rose-900">
          <?= (int)($summary['dirty'] ?? 0) ?>
        </div>
        <p class="mt-1 text-[11px] text-rose-900/80">
          Priority for today’s first cleaning wave.
        </p>
      </div>

      <div class="rounded-2xl border border-amber-200 bg-amber-50 p-4">
        <div class="flex items-center justify-between">
          <span class="text-xs font-medium text-amber-900">In progress</span>
          <i class="fa-solid fa-person-digging text-amber-700"></i>
        </div>
        <div class="mt-2 text-2xl font-extrabold text-amber-900">
          <?= (int)($summary['in_progress'] ?? 0) ?>
        </div>
        <p class="mt-1 text-[11px] text-amber-900/80">
          Rooms currently being cleaned or turned around.
        </p>
      </div>

      <div class="rounded-2xl border border-emerald-200 bg-emerald-50/80 p-4">
        <div class="flex items-center justify-between">
          <span class="text-xs font-medium text-emerald-900">Ready / Inspected</span>
          <i class="fa-solid fa-circle-check text-emerald-700"></i>
        </div>
        <div class="mt-2 text-2xl font-extrabold text-emerald-900">
          <?= (int)($summary['clean'] ?? 0) + (int)($summary['inspected'] ?? 0) ?>
        </div>
        <p class="mt-1 text-[11px] text-emerald-900/80">
          Good to sell rooms — frontdesk can assign instantly.
        </p>
      </div>
    </div>

    <!-- Filters -->
    <form method="get"
          class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm space-y-3">
      <div class="flex items-center justify-between mb-1">
        <h2 class="text-xs font-semibold text-slate-900 uppercase tracking-wide">Filters</h2>
        <span class="text-[11px] text-slate-400">
          Total rooms: <?= (int)$totalRooms ?>
        </span>
      </div>

      <div class="grid gap-3 sm:grid-cols-3">
        <div>
          <label class="block text-xs font-medium text-slate-700 mb-1">Floor</label>
          <select name="floor"
                  class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
            <option value="">All</option>
            <option value="1">1st floor</option>
            <option value="2">2nd floor</option>
            <option value="3">3rd floor</option>
            <option value="4+">4+ floors</option>
          </select>
        </div>
        <div>
          <label class="block text-xs font-medium text-slate-700 mb-1">Status</label>
          <select name="status"
                  class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
            <option value="">All</option>
            <option value="dirty">Dirty</option>
            <option value="in_progress">In progress</option>
            <option value="clean">Clean</option>
            <option value="inspected">Inspected</option>
            <option value="out_of_service">Out of service</option>
          </select>
        </div>
        <div>
          <label class="block text-xs font-medium text-slate-700 mb-1">Assigned to</label>
          <select name="attendant"
                  class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
            <option value="">Anyone</option>
            <option value="A">Team A</option>
            <option value="B">Team B</option>
            <option value="Night">Night shift</option>
          </select>
        </div>
      </div>

      <div class="grid gap-3 sm:grid-cols-[1.7fr,1.3fr]">
        <div>
          <label class="block text-xs font-medium text-slate-700 mb-1">Search</label>
          <input type="text"
                 name="q"
                 value="<?= $h($_GET['q'] ?? '') ?>"
                 class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                 placeholder="Room, guest name, note…">
        </div>
        <div class="flex items-end gap-2">
          <button type="submit"
                  class="inline-flex items-center gap-2 rounded-xl px-4 py-2 text-xs font-semibold text-white shadow-sm hover:shadow-md"
                  style="background:var(--brand,#228B22);">
            <i class="fa-solid fa-filter"></i>
            <span>Apply</span>
          </button>
          <a href="<?= $h($base) ?>/housekeeping"
             class="text-[11px] text-slate-500 hover:text-slate-700">
            Clear
          </a>
        </div>
      </div>
    </form>
  </div>

  <!-- Board table -->
  <div class="rounded-2xl border border-slate-200 bg-white shadow-sm overflow-hidden">
    <div class="flex items-center justify-between px-4 py-3 border-b border-slate-100">
      <div>
        <h2 class="text-sm font-semibold text-slate-900">Today’s rooms</h2>
        <p class="text-xs text-slate-500">
          From dirty departures to inspected arrivals — all in one 2035-style board.
        </p>
      </div>
      <div class="flex flex-wrap gap-1 text-[11px] text-slate-500">
        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-rose-50 text-rose-700 border border-rose-200">
          <span class="w-1.5 h-1.5 rounded-full bg-rose-500"></span> Dirty
        </span>
        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-amber-50 text-amber-800 border border-amber-200">
          <span class="w-1.5 h-1.5 rounded-full bg-amber-500"></span> In progress
        </span>
        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-emerald-50 text-emerald-700 border border-emerald-200">
          <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span> Clean
        </span>
        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-sky-50 text-sky-700 border border-sky-200">
          <span class="w-1.5 h-1.5 rounded-full bg-sky-500"></span> Inspected
        </span>
      </div>
    </div>

    <div class="overflow-x-auto">
      <table class="min-w-full text-sm">
        <thead class="bg-slate-50 text-xs text-slate-500 uppercase tracking-wide">
          <tr>
            <th class="px-3 py-2 text-left">Room</th>
            <th class="px-3 py-2 text-left">Type</th>
            <th class="px-3 py-2 text-left">Status</th>
            <th class="px-3 py-2 text-left">Assigned</th>
            <th class="px-3 py-2 text-left">Priority</th>
            <th class="px-3 py-2 text-left">Notes</th>
            <th class="px-3 py-2 text-right">Quick actions</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
        <?php if (!$rooms): ?>
          <tr>
            <td colspan="7" class="px-3 py-6 text-center text-slate-400 text-sm">
              No housekeeping data yet. Once we wire the backend, today’s rooms and tasks will appear here in real time.
            </td>
          </tr>
        <?php else: ?>
          <?php foreach ($rooms as $room): ?>
            <?php
              $status   = (string)($room['status'] ?? 'dirty');
              $priority = (string)($room['priority'] ?? 'normal');
            ?>
            <tr class="hover:bg-slate-50/60">
              <td class="px-3 py-2 whitespace-nowrap text-slate-900 text-xs">
                <div class="font-semibold">
                  <?= $h($room['room_no'] ?? '') ?>
                </div>
                <?php if (!empty($room['floor'])): ?>
                  <div class="text-[11px] text-slate-400">
                    Floor <?= $h($room['floor']) ?>
                  </div>
                <?php endif; ?>
              </td>
              <td class="px-3 py-2 whitespace-nowrap text-xs text-slate-700">
                <?= $h($room['room_type'] ?? '—') ?>
              </td>
              <td class="px-3 py-2 whitespace-nowrap">
                <span class="inline-flex items-center px-2 py-0.5 rounded-full border text-[11px] font-medium <?= $badge($status) ?>">
                  <?= $h($label($status)) ?>
                </span>
              </td>
              <td class="px-3 py-2 whitespace-nowrap text-xs text-slate-700">
                <?= $h($room['attendant'] ?? 'Unassigned') ?>
                <?php if (!empty($room['shift'])): ?>
                  <div class="text-[11px] text-slate-400"><?= $h($room['shift']) ?></div>
                <?php endif; ?>
              </td>
              <td class="px-3 py-2 whitespace-nowrap text-xs">
                <?php
                  $chipClass = 'bg-slate-50 text-slate-700 border-slate-200';
                  if ($priority === 'high')   $chipClass = 'bg-rose-50 text-rose-700 border-rose-200';
                  if ($priority === 'medium') $chipClass = 'bg-amber-50 text-amber-800 border-amber-200';
                  if ($priority === 'low')    $chipClass = 'bg-emerald-50 text-emerald-700 border-emerald-200';
                ?>
                <span class="inline-flex items-center px-2 py-0.5 rounded-full border text-[11px] font-medium <?= $chipClass ?>">
                  <?= $h(ucfirst($priority)) ?>
                </span>
              </td>
              <td class="px-3 py-2 text-xs text-slate-600 max-w-xs">
                <span class="line-clamp-2">
                  <?= $h($room['note'] ?? '') ?>
                </span>
              </td>
              <td class="px-3 py-2 text-right whitespace-nowrap">
                <div class="inline-flex items-center gap-1">
                  <button type="button"
                          class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg border border-slate-300 text-[11px] hover:bg-slate-100">
                    <i class="fa-solid fa-play"></i>
                    <span>Start clean</span>
                  </button>
                  <button type="button"
                          class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg border border-emerald-300 text-[11px] text-emerald-700 hover:bg-emerald-50">
                    <i class="fa-solid fa-circle-check"></i>
                    <span>Mark clean</span>
                  </button>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- Concept cards -->
  <div class="grid gap-4 lg:grid-cols-3">
    <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
      <div class="flex items-center justify-between mb-2">
        <h2 class="text-xs font-semibold text-slate-900 uppercase tracking-wide">Attendant workload</h2>
        <span class="text-[11px] text-slate-400">Concept card</span>
      </div>
      <p class="text-xs text-slate-500 mb-2">
        Later we can auto-balance rooms across attendants — this card is just visual placeholder for that 2035 logic.
      </p>
      <ul class="space-y-1 text-xs text-slate-700">
        <li class="flex items-center justify-between">
          <span>Team A (Day shift)</span>
          <span class="font-semibold">12 rooms</span>
        </li>
        <li class="flex items-center justify-between">
          <span>Team B (Day shift)</span>
          <span class="font-semibold">9 rooms</span>
        </li>
        <li class="flex items-center justify-between">
          <span>Night shift</span>
          <span class="font-semibold">4 rooms</span>
        </li>
      </ul>
    </div>

    <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
      <div class="flex items-center justify-between mb-2">
        <h2 class="text-xs font-semibold text-slate-900 uppercase tracking-wide">Smart suggestions</h2>
        <span class="text-[11px] text-slate-400">Future AI</span>
      </div>
      <p class="text-xs text-slate-500">
        In a later phase, KlinFlow can auto-suggest:
      </p>
      <ul class="mt-2 space-y-1 text-xs text-slate-700 list-disc list-inside">
        <li>Which rooms to clean first based on arrivals / VIPs.</li>
        <li>When to send push alert to housekeeping mobile app.</li>
        <li>Predicted time to finish each floor, so frontdesk knows stock.</li>
      </ul>
    </div>

    <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4 shadow-sm">
      <div class="flex items-center justify-between mb-2">
        <h2 class="text-xs font-semibold text-slate-900 uppercase tracking-wide">Quick links</h2>
        <span class="text-[11px] text-slate-400">Navigation</span>
      </div>
      <div class="space-y-2 text-xs">
        <a href="<?= $h($base) ?>/frontdesk/arrivals"
           class="flex items-center justify-between px-3 py-2 rounded-xl bg-white border border-slate-200 hover:bg-slate-100">
          <span class="flex items-center gap-2">
            <i class="fa-regular fa-calendar-check text-slate-500"></i>
            <span>Arrivals needing clean room</span>
          </span>
          <i class="fa-solid fa-chevron-right text-slate-400 text-[10px]"></i>
        </a>
        <a href="<?= $h($base) ?>/frontdesk/departures"
           class="flex items-center justify-between px-3 py-2 rounded-xl bg-white border border-slate-200 hover:bg-slate-100">
          <span class="flex items-center gap-2">
            <i class="fa-regular fa-calendar-minus text-slate-500"></i>
            <span>Departures to turn around</span>
          </span>
          <i class="fa-solid fa-chevron-right text-slate-400 text-[10px]"></i>
        </a>
        <a href="<?= $h($base) ?>/rooms"
           class="flex items-center justify-between px-3 py-2 rounded-xl bg-white border border-slate-200 hover:bg-slate-100">
          <span class="flex items-center gap-2">
            <i class="fa-solid fa-bed text-slate-500"></i>
            <span>Full room inventory</span>
          </span>
          <i class="fa-solid fa-chevron-right text-slate-400 text-[10px]"></i>
        </a>
      </div>
    </div>
  </div>

  <!-- How to use -->
  <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-xs text-slate-700 space-y-1">
    <div class="font-semibold text-slate-900 mb-1">How to use this Housekeeping board</div>
    <ol class="list-decimal list-inside space-y-1">
      <li>Use the <strong>Dirty / In progress / Ready</strong> tiles to get an instant health check of the hotel.</li>
      <li>Filter by <strong>floor, status or attendant</strong> to focus on one area or one team.</li>
      <li>Work primarily from the <strong>rooms table</strong>: each row represents a room with current status and priority.</li>
      <li>Use <strong>Start clean / Mark clean</strong> actions as we later wire them to real endpoints for live status updates.</li>
      <li>Frontdesk can keep this board open on a side screen while assigning rooms to arriving guests.</li>
    </ol>
    <p class="mt-1">
      Tip: Next step, we can connect this board to a mobile HK app so attendants update from phones and this page becomes fully live.
    </p>
  </div>
</div>