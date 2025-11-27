<?php
/** @var array  $ctx */
/** @var string $module_base ?? */
/** @var array  $tasks ?? */

$h    = fn($v)=>htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
$base = rtrim((string)($module_base ?? ($ctx['module_base'] ?? '/apps/hotelflow')), '/');
$tasks = $tasks ?? [];
?>
<div class="max-w-5xl mx-auto space-y-6">

  <!-- Header + tiny nav -->
  <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
    <div>
      <h1 class="text-2xl font-extrabold tracking-tight">Housekeeping tasks</h1>
      <p class="text-slate-500 text-sm">
        Personalised cleaning tasks by room, attendant and priority — think of this as your 2035 HK to-do list.
      </p>
    </div>

    <div class="flex flex-wrap gap-2 text-sm">
      <a href="<?= $h($base) ?>/housekeeping"
         class="inline-flex items-center gap-2 px-3 py-1.5 rounded-lg border border-slate-300 bg-white hover:bg-slate-50">
        <i class="fa-solid fa-table-columns"></i>
        <span>Back to board</span>
      </a>
    </div>
  </div>

  <!-- Filters + new task -->
  <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm space-y-3">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
      <div>
        <h2 class="text-sm font-semibold text-slate-900">Today’s cleaning plan</h2>
        <p class="text-xs text-slate-500">
          Filter by status or attendant. Later this can sync with mobile HK app.
        </p>
      </div>
      <button type="button"
              class="inline-flex items-center gap-2 rounded-xl px-4 py-2 text-xs font-semibold text-white shadow-sm hover:shadow-md"
              style="background:var(--brand,#228B22);">
        <i class="fa-solid fa-plus"></i>
        <span>Quick add task</span>
      </button>
    </div>

    <div class="grid gap-3 sm:grid-cols-4">
      <div>
        <label class="block text-xs font-medium text-slate-700 mb-1">Status</label>
        <select class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
          <option>All</option>
          <option>Pending</option>
          <option>In progress</option>
          <option>Done</option>
        </select>
      </div>
      <div>
        <label class="block text-xs font-medium text-slate-700 mb-1">Priority</label>
        <select class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
          <option>Any</option>
          <option>High</option>
          <option>Medium</option>
          <option>Low</option>
        </select>
      </div>
      <div>
        <label class="block text-xs font-medium text-slate-700 mb-1">Attendant</label>
        <select class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
          <option>Anyone</option>
          <option>Team A</option>
          <option>Team B</option>
          <option>Night</option>
        </select>
      </div>
      <div>
        <label class="block text-xs font-medium text-slate-700 mb-1">Search</label>
        <input type="text"
               class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
               placeholder="Room, task, notes…">
      </div>
    </div>
  </div>

  <!-- Tasks list -->
  <div class="rounded-2xl border border-slate-200 bg-white shadow-sm overflow-hidden">
    <div class="flex items-center justify-between px-4 py-3 border-b border-slate-100">
      <div>
        <h2 class="text-sm font-semibold text-slate-900">Task queue</h2>
        <p class="text-xs text-slate-500">Drag &amp; drop / tap to update will come later — UI is ready.</p>
      </div>
    </div>

    <div class="divide-y divide-slate-100 text-sm">
      <?php if (!$tasks): ?>
        <div class="px-4 py-6 text-center text-slate-400 text-sm">
          No tasks yet. Once we start assigning cleaning tasks per room, they will show here.
        </div>
      <?php else: ?>
        <!-- Future: loop tasks -->
      <?php endif; ?>
    </div>
  </div>

  <!-- How to use -->
  <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-xs text-slate-700 space-y-1">
    <div class="font-semibold text-slate-900 mb-1">How to use this Housekeeping tasks page</div>
    <ol class="list-decimal list-inside space-y-1">
      <li>Use this as the <strong>daily to-do list</strong> for HK teams, separate from the board view.</li>
      <li>Filter by <strong>status / priority / attendant</strong> when the list gets long.</li>
      <li>In a later phase, we’ll connect this to a mobile app so attendants see exactly their tasks only.</li>
      <li>Every task will be linked to a room, and status updates will sync back to the main HK board.</li>
    </ol>
    <p class="mt-1">
      Tip: Amar suggestion — ekta simple schema hms_hk_tasks diye suru kori (room_id, title, status, priority, assigned_to).
    </p>
  </div>
</div>