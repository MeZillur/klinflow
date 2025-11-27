<?php
/** @var array  $ctx */
/** @var array  $report */
/** @var string $title */
/** @var string $today */

$h    = fn($v)=>htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
$base = rtrim((string)($ctx['module_base'] ?? '/apps/hotelflow'), '/');
?>
<div class="max-w-5xl mx-auto space-y-6">

  <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
    <div>
      <p class="text-[11px] text-slate-500 mb-1">
        HotelFlow · Report stub
      </p>
      <h1 class="text-2xl font-extrabold tracking-tight flex items-center gap-2">
        <?= $h($report['name'] ?? 'Report') ?>
        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-amber-50 text-amber-800 text-[11px] border border-amber-200">
          <i class="fa-regular fa-hourglass-half"></i>
          <span>Coming soon</span>
        </span>
      </h1>
      <p class="text-slate-500 text-sm mt-1">
        Group: <?= $h($report['group'] ?? 'Reports') ?> · <?= $h($today) ?>
      </p>
    </div>

    <a href="<?= $h($base) ?>/reports"
       class="inline-flex items-center gap-2 px-3 py-1.5 rounded-lg border border-slate-300 bg-white hover:bg-slate-50 text-sm">
      <i class="fa-solid fa-arrow-left-long"></i>
      <span>Back to all reports</span>
    </a>
  </div>

  <div class="rounded-2xl border border-dashed border-slate-300 bg-slate-50 p-6 text-sm text-slate-600 space-y-2">
    <p>
      This is a placeholder for the <strong><?= $h($report['name'] ?? 'Report') ?></strong> report.
      Next step: we’ll wire this to actual data (SQL) and filters.
    </p>
    <p>
      When implemented, this screen will show:
    </p>
    <ul class="list-disc list-inside text-[11px] space-y-1">
      <li>A date range selector and default period (today, MTD, custom…)</li>
      <li>A table / chart with the core metrics for this report</li>
      <li>Filters for channel, rate code, market, room type, etc. where relevant</li>
      <li>Export options (Excel / PDF) and print-friendly layout</li>
    </ul>
  </div>

  <section class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-[11px] text-slate-700 space-y-1">
    <div class="font-semibold text-slate-900 mb-1">How to use this <?= $h($report['name'] ?? 'report') ?> screen (future)</div>
    <ol class="list-decimal list-inside space-y-1">
      <li>Pick a <strong>date range</strong> from the top filter bar.</li>
      <li>Use filters (channel, market, rate, etc.) to zoom into the segment you care about.</li>
      <li>Review the key metrics and drill into outliers (high cancellations, low ADR, etc.).</li>
      <li>Export or print and share with management or owners as part of your daily pack.</li>
    </ol>
  </section>
</div>