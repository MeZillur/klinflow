<?php declare(strict_types=1);
/**
 * Stored Master Dispatch Challan — read-only view
 *
 * Inputs:
 *  - $org         array
 *  - $module_base string
 *  - $master      array row from dms_master_challans
 *  - $challans    array rows from dms_challans
 */

$h = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');

$org         = $org         ?? [];
$module_base = rtrim((string)($module_base ?? ''), '/');
$master      = $master      ?? [];
$challans    = is_array($challans ?? null) ? $challans : [];

$orgName    = trim((string)($org['name']    ?? ''));
$orgAddress = trim((string)($org['address'] ?? ''));

$masterNo     = $master['master_no']        ?? ('#'.$master['id'] ?? '');
$status       = $master['status']           ?? 'draft';
$deliveryPers = $master['delivery_person']  ?? '';
$vehicleNo    = $master['vehicle_no']       ?? '';
$routeText    = $master['route_text']       ?? '';
$dispatchAt   = $master['dispatch_at']      ?? null;
$notes        = $master['notes']            ?? '';
$subtotal     = (float)($master['subtotal']       ?? 0);
$returnAmt    = (float)($master['return_amount']  ?? 0);
$netTotal     = (float)($master['net_total']      ?? 0);
$totalRecv    = (float)($master['total_received'] ?? 0);

?>
<div class="min-h-screen bg-white px-3 py-4 sm:px-5 lg:px-8">
  <div class="mx-auto max-w-7xl space-y-4">

    <!-- HEADER -->
    <div class="bg-white border border-slate-200 shadow-sm rounded-2xl px-4 py-3 sm:px-6 sm:py-4 flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
      <div class="space-y-1">
        <h1 class="text-2xl font-semibold text-slate-900">
          Master Dispatch Challan
          <span class="text-sm font-normal text-slate-500">· <?= $h($masterNo) ?></span>
        </h1>
        <?php if ($orgName !== ''): ?>
          <p class="text-xs text-slate-500">
            <?= $h($orgName) ?>
            <?php if ($orgAddress !== ''): ?> · <?= $h($orgAddress) ?><?php endif; ?>
          </p>
        <?php endif; ?>
      </div>
      <div class="flex flex-col items-start gap-2 sm:flex-row sm:items-center sm:gap-3 lg:justify-end">
        <a
          href="<?= $h($module_base) ?>/challan"
          class="inline-flex items-center rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 text-xs sm:text-sm font-medium text-slate-700 hover:bg-slate-100"
        >
          ← Back to challans
        </a>
        <button
          type="button"
          onclick="window.print()"
          class="inline-flex items-center rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 text-xs sm:text-sm font-medium text-slate-700 hover:bg-slate-100"
        >
          Download / Print
        </button>
        <span class="inline-flex items-center rounded-full px-3 py-1 text-xs
                     <?= $status === 'final' ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-700' ?>">
          <span class="mr-1.5 h-1.5 w-1.5 rounded-full
                       <?= $status === 'final' ? 'bg-emerald-500' : 'bg-slate-400' ?>"></span>
          Status: <span class="ml-1 font-medium"><?= $h(ucfirst($status)) ?></span>
        </span>
      </div>
    </div>

    <!-- MAIN GRID -->
    <div class="grid gap-4 lg:grid-cols-[minmax(0,3.2fr)_minmax(0,1fr)]">
      <!-- LEFT: challan list + notes -->
      <div class="bg-white border border-slate-200 shadow-sm rounded-2xl px-4 py-4 sm:px-6 sm:py-5">
        <div class="flex flex-wrap justify-between gap-4 mb-4">
          <div class="space-y-0.5">
            <p class="text-xs font-medium text-slate-400 uppercase tracking-wide">Delivery details</p>
            <p class="text-sm text-slate-800">
              Person: <span class="font-medium"><?= $deliveryPers !== '' ? $h($deliveryPers) : '—' ?></span>
            </p>
            <p class="text-xs text-slate-500">
              Vehicle: <?= $vehicleNo !== '' ? $h($vehicleNo) : '—' ?> · Route: <?= $routeText !== '' ? $h($routeText) : '—' ?>
            </p>
            <p class="text-xs text-slate-500">
              Dispatch at: <?= $dispatchAt ? $h($dispatchAt) : '—' ?>
            </p>
          </div>
          <div class="min-w-[220px] max-w-md text-right">
            <p class="text-xs font-medium text-slate-400 uppercase tracking-wide">Selection</p>
            <p class="text-sm font-medium text-slate-900">
              <?= count($challans) ?> challan(s)
            </p>
          </div>
        </div>

        <div class="rounded-2xl border border-slate-200 overflow-hidden">
          <div class="bg-slate-50 px-3 py-2 sm:px-4">
            <div class="grid grid-cols-[32px_minmax(0,1.7fr)_120px_120px] gap-2 text-[11px] sm:text-xs font-semibold text-slate-500">
              <div>#</div>
              <div>Challan</div>
              <div class="text-right">Challan date</div>
              <div class="text-right">Grand total</div>
            </div>
          </div>

          <div class="divide-y divide-slate-100 bg-white">
            <?php if ($challans): ?>
              <?php $i = 1; foreach ($challans as $c): ?>
                <div class="px-3 py-3 sm:px-4 sm:py-3.5 text-xs sm:text-sm">
                  <div class="grid grid-cols-[32px_minmax(0,1.7fr)_120px_120px] gap-2 items-center">
                    <div class="text-slate-500"><?= $i++ ?></div>
                    <div>
                      <div class="font-medium text-slate-900">
                        Challan <?= $h($c['challan_no'] ?? ('#'.$c['id'])) ?>
                      </div>
                      <div class="text-[11px] text-slate-500">
                        Customer: <?= $h($c['customer_name'] ?? '—') ?>
                      </div>
                    </div>
                    <div class="text-right text-slate-700">
                      <?= $h($c['challan_date'] ?? '') ?>
                    </div>
                    <div class="text-right text-slate-900 font-medium">
                      ৳<?= number_format((float)($c['grand_total'] ?? 0), 2) ?>
                    </div>
                  </div>
                </div>
              <?php endforeach; ?>
            <?php else: ?>
              <div class="px-4 py-6 text-center text-sm text-slate-500">
                No source challans recorded for this master challan.
              </div>
            <?php endif; ?>
          </div>
        </div>

        <div class="mt-4">
          <label class="block text-xs font-medium text-slate-500 uppercase tracking-wide mb-1">
            Notes / driver instructions
          </label>
          <div class="rounded-2xl border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-800 min-h-[64px]">
            <?= $notes !== '' ? nl2br($h($notes)) : '—' ?>
          </div>
        </div>
      </div>

      <!-- RIGHT: summary -->
      <aside class="bg-white border border-slate-200 shadow-sm rounded-2xl px-4 py-4 sm:px-5 sm:py-5 flex flex-col gap-4 lg:max-w-xs">
        <div>
          <p class="text-xs font-semibold text-slate-400 uppercase tracking-wide">
            Dispatch summary
          </p>
          <div class="mt-2 space-y-2 text-sm">
            <div class="flex justify-between">
              <span class="text-slate-500">Subtotal</span>
              <span class="font-medium text-slate-900">৳<?= number_format($subtotal, 2) ?></span>
            </div>
            <div class="flex justify-between">
              <span class="text-slate-500">Return amount</span>
              <span class="font-medium text-slate-900">৳<?= number_format($returnAmt, 2) ?></span>
            </div>
            <div class="flex justify-between">
              <span class="text-slate-500">Net total</span>
              <span class="font-semibold text-slate-900">৳<?= number_format($netTotal, 2) ?></span>
            </div>
          </div>

          <div class="mt-3">
            <p class="text-xs font-medium text-slate-500 mb-1">
              Total received
            </p>
            <p class="text-sm font-semibold text-slate-900">
              ৳<?= number_format($totalRecv, 2) ?>
            </p>
          </div>
        </div>

        <p class="text-[11px] text-slate-400 mt-2">
          Printed at: <?= $h(date('Y-m-d H:i')) ?> — klinflow.com
        </p>
      </aside>
    </div>

    <!-- HOW TO USE -->
    <div class="mt-4 bg-white border border-dashed border-slate-300 rounded-2xl px-4 py-3 sm:px-6 sm:py-4 text-xs sm:text-sm text-slate-700">
      <h2 class="text-sm font-semibold text-slate-900 mb-1">
        How to use this page
      </h2>
      <ul class="list-disc pl-5 space-y-1">
        <li>This page shows a stored <strong>Master Dispatch Challan</strong> created from multiple delivery challans.</li>
        <li>Use the header section to confirm delivery person, vehicle, route, and dispatch time.</li>
        <li>The table lists all original challans grouped into this master run, with their totals.</li>
        <li>The right-hand summary shows financial totals (subtotal, returns, and net total) and how much the driver received in cash.</li>
        <li>Use your normal challan list to create new masters or to re-open drafts if needed.</li>
      </ul>
    </div>
  </div>
</div>