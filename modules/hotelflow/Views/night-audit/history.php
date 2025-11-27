<?php
/** @var array  $ctx */
/** @var string $module_base ?? */
/** @var array  $runs */

$h    = fn($v)=>htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
$base = rtrim((string)($module_base ?? ($ctx['module_base'] ?? '/apps/hotelflow')), '/');
$runs = $runs ?? [];
?>
<div class="max-w-5xl mx-auto space-y-6">

  <!-- Header + tiny nav -->
  <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
    <div>
      <div class="flex items-center gap-2 text-[11px] text-slate-500 mb-1">
        <a href="<?= $h($base) ?>/night-audit"
           class="inline-flex items-center gap-1 hover:text-slate-700">
          <i class="fa-solid fa-angle-left text-[10px]"></i>
          <span>Back to tonight</span>
        </a>
        <span class="w-1 h-px bg-slate-300"></span>
        <span>Night audit history</span>
      </div>
      <h1 class="text-2xl font-extrabold tracking-tight">Night audit history</h1>
      <p class="text-slate-500 text-sm mt-1">
        Past night audit runs — helpful for comparing occupancy, revenue and process issues across days.
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
    </div>
  </div>

  <!-- History table -->
  <div class="rounded-2xl border border-slate-200 bg-white shadow-sm overflow-hidden">
    <div class="px-4 py-3 border-b border-slate-100">
      <h2 class="text-sm font-semibold text-slate-900">Recent runs</h2>
      <p class="text-xs text-slate-500">
        Once we add <code>hms_night_audit_runs</code>, each run will appear here with key metrics and comments.
      </p>
    </div>

    <div class="overflow-x-auto">
      <table class="min-w-full text-sm">
        <thead class="bg-slate-50 text-xs text-slate-500 uppercase tracking-wide">
          <tr>
            <th class="px-3 py-2 text-left">Date</th>
            <th class="px-3 py-2 text-left">Status</th>
            <th class="px-3 py-2 text-right">Open folios</th>
            <th class="px-3 py-2 text-right">Outstanding (BDT)</th>
            <th class="px-3 py-2 text-left">Note</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
          <?php if (!$runs): ?>
            <tr>
              <td colspan="5" class="px-3 py-6 text-center text-slate-400 text-sm">
                No night audit runs recorded yet. Once we wire the close-day process,
                this table will show a neat history of each night.
              </td>
            </tr>
          <?php else: ?>
            <?php foreach ($runs as $r): ?>
              <?php
                $dt     = (string)($r['run_date'] ?? $r['business_date'] ?? '');
                $status = (string)($r['status'] ?? 'completed');
                $open   = (int)($r['open_folios'] ?? 0);
                $bal    = (float)($r['outstanding_balance'] ?? 0.0);
                $note   = (string)($r['note'] ?? '');
              ?>
              <tr class="hover:bg-slate-50/70">
                <td class="px-3 py-2 text-xs text-slate-700 whitespace-nowrap">
                  <?= $h($dt ?: '—') ?>
                </td>
                <td class="px-3 py-2 text-xs text-slate-700 whitespace-nowrap">
                  <?= $h(ucfirst($status)) ?>
                </td>
                <td class="px-3 py-2 text-xs text-right">
                  <?= $open ?>
                </td>
                <td class="px-3 py-2 text-xs text-right">
                  ৳<?= number_format($bal, 2) ?>
                </td>
                <td class="px-3 py-2 text-xs text-slate-600">
                  <?= $h($note ?: '—') ?>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- How to use this page -->
  <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-[11px] text-slate-700 space-y-1">
    <div class="font-semibold text-slate-900 mb-1">How to use this Night audit history page</div>
    <ol class="list-decimal list-inside space-y-1">
      <li>Use this page as a <strong>logbook</strong> once night audit runs are stored in the database.</li>
      <li>Compare <strong>open folios</strong> and <strong>outstanding balances</strong> across days to spot trends.</li>
      <li>Review notes for days with unusual adjustments or incidents.</li>
      <li>Share this history with management during weekly or monthly review meetings.</li>
    </ol>
    <p class="mt-1">
      Tip: Later we can add CSV export and filters by date range, user and shift for full 2035-style analytics.
    </p>
  </div>
</div>