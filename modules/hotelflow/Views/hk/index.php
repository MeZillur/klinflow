<?php
declare(strict_types=1);

/**
 * @var array $rooms
 * @var array $schema
 * @var array $filters
 * @var array $ctx   (through shell)
 */

$h = fn($s)=>htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
$status = $filters['status'] ?? '';
$floor  = $filters['floor']  ?? '';
?>
<div class="px-6 py-6 space-y-6">

  <!-- Header -->
  <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
    <div>
      <h1 class="text-2xl font-bold text-slate-900">
        Housekeeping board
      </h1>
      <p class="text-sm text-slate-600 mt-1">
        Live snapshot of rooms, cleaning status and quick task capture.
      </p>
    </div>
    <div class="flex flex-wrap gap-2">
      <a href="<?= $h(($ctx['module_base'] ?? '/apps/hotelflow').'/housekeeping/tasks') ?>"
         class="inline-flex items-center gap-2 px-3 py-2 rounded-xl text-sm border border-emerald-500 text-emerald-700 bg-emerald-50 hover:bg-emerald-100">
        <span class="fa fa-list-check"></span>
        <span>Task list</span>
      </a>
      <a href="<?= $h($base.'/housekeeping/lost-found') ?>"
         class="inline-flex items-center gap-2 px-3 py-2 rounded-xl text-sm border border-slate-300 text-slate-700 bg-white hover:bg-slate-50">
        <span class="fa fa-box-open"></span>
        <span>Lost &amp; found</span>
      </a>
    </div>
  </div>

  <!-- Filters + quick new task -->
  <div class="grid lg:grid-cols-[2fr,1.1fr] gap-6 items-start">

    <!-- Filters + board -->
    <section class="space-y-4">
      <form class="flex flex-wrap items-end gap-3 bg-white rounded-2xl shadow-sm px-4 py-3 border border-slate-100">
        <div>
          <label class="block text-[11px] font-semibold text-slate-500 uppercase tracking-wide">
            Status
          </label>
          <select name="status"
                  class="mt-1 rounded-xl border border-slate-300 text-sm px-3 py-2 bg-white">
            <option value="">All</option>
            <?php
            foreach ([
              'clean'          => 'Clean',
              'dirty'          => 'Dirty',
              'inspected'      => 'Inspected',
              'out_of_service' => 'Out of service',
            ] as $key => $label): ?>
              <option value="<?= $h($key) ?>" <?= $status===$key?'selected':'' ?>>
                <?= $h($label) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div>
          <label class="block text-[11px] font-semibold text-slate-500 uppercase tracking-wide">
            Floor
          </label>
          <input type="text"
                 name="floor"
                 value="<?= $h($floor) ?>"
                 placeholder="e.g. 3"
                 class="mt-1 rounded-xl border border-slate-300 text-sm px-3 py-2 bg-white w-24">
        </div>

        <button class="inline-flex items-center gap-2 mt-5 px-3 py-2 rounded-xl bg-emerald-600 text-white text-sm font-semibold hover:bg-emerald-700">
          <span class="fa fa-filter"></span>
          <span>Apply</span>
        </button>

        <?php if ($status || $floor): ?>
          <a href="? "
             class="mt-5 text-xs text-slate-500 hover:text-slate-700">
            Clear filters
          </a>
        <?php endif; ?>
      </form>

      <!-- Rooms board -->
      <div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
        <div class="flex items-center justify-between px-4 py-3 border-b border-slate-100">
          <div class="flex items-center gap-2 text-sm text-slate-700">
            <span class="fa fa-bed text-emerald-600"></span>
            <span>Rooms</span>
            <span class="text-xs text-slate-400">
              (<?= count($rooms) ?> visible)
            </span>
          </div>
        </div>

        <?php if (empty($rooms)): ?>
          <div class="px-4 py-10 text-center text-sm text-slate-500">
            No rooms found for this filter. Once you configure <code>hms_rooms</code>,
            they will appear here with cleaning status.
          </div>
        <?php else: ?>
          <div class="grid sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-3 p-4">
            <?php foreach ($rooms as $room):
              $status = strtolower((string)($room['hk_status'] ?? ''));
              $badgeClasses = match ($status) {
                'clean'          => 'bg-emerald-50 text-emerald-700 border-emerald-200',
                'inspected'      => 'bg-sky-50 text-sky-700 border-sky-200',
                'dirty'          => 'bg-rose-50 text-rose-700 border-rose-200',
                'out_of_service' => 'bg-amber-50 text-amber-800 border-amber-200',
                default          => 'bg-slate-50 text-slate-600 border-slate-200',
              };
              $label = $status ?: 'Unknown';
              ?>
              <article class="rounded-2xl border border-slate-100 bg-slate-50/40 px-3 py-3 flex flex-col gap-2">
                <div class="flex items-center justify-between gap-2">
                  <div class="flex flex-col">
                    <div class="text-sm font-semibold text-slate-900">
                      <?= $h($room['room_no'] ?? ('#'.$room['id'])) ?>
                    </div>
                    <div class="text-[11px] text-slate-500">
                      <?php if (!empty($room['room_type'])): ?>
                        <?= $h($room['room_type']) ?>
                      <?php endif; ?>
                      <?php if (!empty($room['floor'])): ?>
                        <span class="ml-1 text-slate-400">• Floor <?= $h($room['floor']) ?></span>
                      <?php endif; ?>
                    </div>
                  </div>
                  <div class="text-right">
                    <span class="inline-flex items-center px-2 py-1 rounded-full border text-[11px] font-semibold <?= $badgeClasses ?>">
                      <?= $h(ucwords(str_replace('_',' ',$label))) ?>
                    </span>
                  </div>
                </div>

                <?php if (!empty($room['hk_notes'])): ?>
                  <div class="text-[11px] text-slate-600 line-clamp-2">
                    <?= $h($room['hk_notes']) ?>
                  </div>
                <?php endif; ?>

                <!-- Quick status change -->
                <form method="post"
                      action="<?= $h(($ctx['module_base'] ?? '/apps/hotelflow').'/housekeeping/rooms/'.(int)$room['id'].'/hk-status') ?>"
                      class="mt-1 flex items-center gap-2">
                  <select name="hk_status"
                          class="flex-1 rounded-xl border border-slate-300 text-[11px] px-2 py-1 bg-white">
                    <?php foreach ([
                      ''               => '— Set status —',
                      'clean'          => 'Clean',
                      'dirty'          => 'Dirty',
                      'inspected'      => 'Inspected',
                      'out_of_service' => 'Out of service',
                    ] as $val => $lbl): ?>
                      <option value="<?= $h($val) ?>" <?= $status===$val?'selected':'' ?>>
                        <?= $h($lbl) ?>
                      </option>
                    <?php endforeach; ?>
                  </select>
                  <button class="inline-flex items-center justify-center px-2.5 py-1.5 rounded-xl bg-emerald-600 text-white text-[11px] font-semibold hover:bg-emerald-700">
                    Update
                  </button>
                </form>
              </article>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>
    </section>

    <!-- Quick new task card (uses lookups) -->
    <section class="bg-white rounded-2xl shadow-sm border border-slate-100 p-4 space-y-4">
      <div class="flex items-center justify-between gap-2">
        <div class="flex items-center gap-2">
          <span class="fa fa-broom text-emerald-600"></span>
          <div>
            <h2 class="text-sm font-semibold text-slate-900">Quick HK task</h2>
            <p class="text-[11px] text-slate-500">
              Capture a small job for a room and assign to a staff member.
            </p>
          </div>
        </div>
      </div>

      <form method="post"
            action="<?= $h(($ctx['module_base'] ?? '/apps/hotelflow').'/housekeeping/tasks') ?>"
            class="space-y-3"
            x-data>
        <div>
          <label class="block text-xs font-medium text-slate-700 mb-1">
            Title
          </label>
          <input type="text"
                 name="title"
                 required
                 class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm"
                 placeholder="e.g. Deep clean after checkout">
        </div>

        <div class="grid grid-cols-2 gap-3">
          <div>
            <label class="block text-xs font-medium text-slate-700 mb-1">
              Room
            </label>
            <input
              type="text"
              id="hk_task_room_search"
              class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm"
              placeholder="Type room no or type…"
              data-kf-lookup="rooms">
            <input type="hidden" name="room_no" id="hk_task_room_no">
          </div>

          <div>
            <label class="block text-xs font-medium text-slate-700 mb-1">
              Assignee
            </label>
            <input
              type="text"
              id="hk_task_staff_search"
              class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm"
              placeholder="Type staff name…"
              data-kf-lookup="staff">
            <input type="hidden" name="assignee" id="hk_task_staff_name">
          </div>
        </div>

        <div class="grid grid-cols-2 gap-3">
          <div>
            <label class="block text-xs font-medium text-slate-700 mb-1">
              Priority
            </label>
            <select name="priority"
                    class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm">
              <option value="low">Low</option>
              <option value="normal" selected>Normal</option>
              <option value="high">High</option>
            </select>
          </div>
          <div>
            <label class="block text-xs font-medium text-slate-700 mb-1">
              Due date
            </label>
            <input type="date"
                   name="due_date"
                   class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm">
          </div>
        </div>

        <div>
          <label class="block text-xs font-medium text-slate-700 mb-1">
            Notes (optional)
          </label>
          <textarea name="notes"
                    rows="2"
                    class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm"
                    placeholder="Any special instruction for this task…"></textarea>
        </div>

        <div class="flex justify-end">
          <button class="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-emerald-600 text-white text-sm font-semibold hover:bg-emerald-700">
            <span class="fa fa-paper-plane"></span>
            <span>Create task</span>
          </button>
        </div>
      </form>
    </section>
  </div>

  <!-- How to use this page -->
  <section class="mt-4 bg-slate-50 border border-slate-200 rounded-2xl px-4 py-4">
    <h3 class="text-sm font-semibold text-slate-800 mb-1">
      How to use this page
    </h3>
    <ul class="text-xs text-slate-600 space-y-1.5 list-disc list-inside">
      <li><strong>Filter by status or floor</strong> at the top to focus the board on a subset of rooms.</li>
      <li><strong>Update cleaning status</strong> of any room from the dropdown inside each room card and press <em>Update</em>.</li>
      <li><strong>Create housekeeping tasks</strong> from the quick form on the right – use the room and staff fields with live lookup.</li>
      <li><strong>Open the full task list</strong> or <strong>Lost &amp; found</strong> using the buttons in the header for deeper follow-up.</li>
    </ul>
  </section>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
  if (!window.KF || !KF.lookup) return;

  const roomInput  = document.getElementById('hk_task_room_search');
  const roomHidden = document.getElementById('hk_task_room_no');
  if (roomInput && roomHidden) {
    KF.lookup.bind({
      el: roomInput,
      entity: 'rooms',
      onPick(row) {
        const label = row.label || '';
        const code  = row.code  || label;
        roomInput.value  = label;
        roomHidden.value = code;
        roomHidden.dispatchEvent(new Event('change', { bubbles: true }));
      }
    });
  }

  const staffInput  = document.getElementById('hk_task_staff_search');
  const staffHidden = document.getElementById('hk_task_staff_name');
  if (staffInput && staffHidden) {
    KF.lookup.bind({
      el: staffInput,
      entity: 'staff',
      onPick(row) {
        const label = row.label || '';
        staffInput.value   = label;
        staffHidden.value  = label;
        staffHidden.dispatchEvent(new Event('change', { bubbles: true }));
      }
    });
  }
});
</script>