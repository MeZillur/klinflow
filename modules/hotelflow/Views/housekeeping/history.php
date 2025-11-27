<?php
/** @var array  $ctx */
/** @var string $module_base ?? */
/** @var array  $logs ?? */

$h    = fn($v)=>htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
$base = rtrim((string)($module_base ?? ($ctx['module_base'] ?? '/apps/hotelflow')), '/');
$logs = $logs ?? [];
?>
<div class="max-w-5xl mx-auto space-y-6">

  <!-- Header + tiny nav -->
  <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
    <div>
      <h1 class="text-2xl font-extrabold tracking-tight">Housekeeping history</h1>
      <p class="text-slate-500 text-sm">
        Audit trail of housekeeping actions — who cleaned which room, when, and how long it took.
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

  <!-- Filters -->
  <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm space-y-3">
    <div class="flex items-center justify-between mb-1">
      <div>
        <h2 class="text-sm font-semibold text-slate-900">Filter history</h2>
        <p class="text-xs text-slate-500">
          Date range, room and attendant filtering — ready for real data wiring.
        </p>
      </div>
    </div>
    <div class="grid gap-3 sm:grid-cols-4">
      <div>
        <label class="block text-xs font-medium text-slate-700 mb-1">From</label>
        <input type="date"
               class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
      </div>
      <div>
        <label class="block text-xs font-medium text-slate-700 mb-1">To</label>
        <input type="date"
               class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
      </div>
      <div>
        <label class="block text-xs font-medium text-slate-700 mb-1">Room</label>
        <input type="text"
               class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
               placeholder="Room no.">
      </div>
      <div>
        <label class="block text-xs font-medium text-slate-700 mb-1">Attendant</label>
        <input type="text"
               class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
               placeholder="Name">
      </div>
    </div>
  </div>

  <!-- History table -->
  <div class="rounded-2xl border border-slate-200 bg-white shadow-sm overflow-hidden">
    <div class="flex items-center justify-between px-4 py-3 border-b border-slate-100">
      <div>
        <h2 class="text-sm font-semibold text-slate-900">Cleaning log</h2>
        <p class="text-xs text-slate-500">
          Once wired, every “Start clean” / “Mark clean” action from the board will create a log row here.
        </p>
      </div>
    </div>

    <div class="overflow-x-auto">
      <table class="min-w-full text-sm">
        <thead class="bg-slate-50 text-xs text-slate-500 uppercase tracking-wide">
          <tr>
            <th class="px-3 py-2 text-left">Date &amp; time</th>
            <th class="px-3 py-2 text-left">Room</th>
            <th class="px-3 py-2 text-left">Action</th>
            <th class="px-3 py-2 text-left">Attendant</th>
            <th class="px-3 py-2 text-left">Duration</th>
            <th class="px-3 py-2 text-left">Notes</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
          <?php if (!$logs): ?>
            <tr>
              <td colspan="6" class="px-3 py-6 text-center text-slate-400 text-sm">
                No history yet. When housekeeping workflows go live, this will become the audit log for supervisors.
              </td>
            </tr>
          <?php else: ?>
            <!-- Future: loop $logs -->
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- How to use -->
  <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-xs text-slate-700 space-y-1">
    <div class="font-semibold text-slate-900 mb-1">How to use this Housekeeping history page</div>
    <ol class="list-decimal list-inside space-y-1">
      <li>Use this as an <strong>audit trail</strong> for cleaning actions and inspections.</li>
      <li>When we wire backend, every board update will insert a row into e.g. <code>hms_hk_history</code>.</li>
      <li>Supervisors can filter by date range, room, or attendant to investigate complaints.</li>
      <li>Later we can export this data as CSV for KPIs like average cleaning time per room type.</li>
    </ol>
    <p class="mt-1">
      Tip: Simple schema idea — <code>(org_id, room_id, action, attendant_id, started_at, finished_at, notes)</code>.
    </p>
  </div>
</div>