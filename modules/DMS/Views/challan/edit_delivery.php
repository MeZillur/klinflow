<?php
declare(strict_types=1);

/**
 * DMS — Edit Delivery Challan (next-gen UI)
 *
 * Expects:
 *  - $challan (array, dms_challans row)
 *  - $items   (array, dms_challans_items[])
 *  - $org     (array, cp_organizations row)
 *  - $module_base (string, /t/{slug}/apps/dms) optional
 *  - $csrf    (string) optional
 */

$h = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');

$challan     = is_array($challan ?? null) ? $challan : [];
$items       = is_array($items ?? null) ? $items : [];
$org         = is_array($org ?? null) ? $org : [];
$module_base = rtrim((string)($module_base ?? ''), '/');

if ($module_base === '') {
    $slug        = (string)($org['slug'] ?? '');
    $module_base = $slug !== '' ? '/t/' . rawurlencode($slug) . '/apps/dms' : '/apps/dms';
}

$challanId   = (int)($challan['id'] ?? 0);
$challanNo   = (string)($challan['challan_no'] ?? ('ID '.$challanId));
$status      = (string)($challan['status'] ?? '');
$customer    = (string)($challan['customer_name'] ?? '');
$challanDate = (string)($challan['challan_date'] ?? '');
$vehicleNo   = (string)($challan['vehicle_no'] ?? '');
$driverName  = (string)($challan['driver_name'] ?? '');
$dispatchAt  = (string)($challan['dispatch_at'] ?? '');
$notes       = (string)($challan['notes'] ?? '');

$csrfToken   = (string)($csrf ?? '');

/* simple aggregates for sidebar */
$totalOrdered   = 0.0;
$totalDelivered = 0.0;
$totalReturned  = 0.0;

foreach ($items as $ln) {
    $totalOrdered   += (float)($ln['qty_ordered']  ?? $ln['qty'] ?? 0);
    $totalDelivered += (float)($ln['qty_delivered'] ?? 0);
    $totalReturned  += (float)($ln['qty_returned']  ?? 0);
}

$backUrl  = $module_base . '/challan/' . $challanId;
$postUrl  = $module_base . '/challan/' . $challanId . '/update-delivery';

?>
<div class="min-h-screen bg-slate-50/80 px-3 py-4 sm:px-6 sm:py-6">
  <div class="mx-auto max-w-6xl space-y-4">

    <!-- ===== Top header ===== -->
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
      <div>
        <p class="text-xs font-semibold tracking-wide text-emerald-600 uppercase">
          Delivery Challan
        </p>
        <h1 class="mt-1 flex items-center gap-2 text-2xl font-semibold text-slate-900">
          Update Delivery — <span class="font-mono text-base sm:text-lg">#<?= $h($challanNo) ?></span>
          <?php if ($status !== ''): ?>
            <span class="inline-flex items-center rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-medium text-slate-700">
              Status: <?= $h(ucfirst($status)) ?>
            </span>
          <?php endif; ?>
        </h1>
        <p class="mt-1 text-xs text-slate-500">
          Tweak delivered / returned quantities after the trip. Customer & pricing stay locked.
        </p>
      </div>

      <div class="flex items-center gap-2 sm:gap-3">
        <a href="<?= $h($backUrl) ?>"
           class="inline-flex items-center rounded-xl border border-slate-200 bg-white px-3 py-1.5 text-xs sm:text-sm font-medium text-slate-700 shadow-sm hover:bg-slate-50">
          ← Back to challan
        </a>
        <a href="<?= $h($module_base) ?>/challan"
           class="inline-flex items-center rounded-xl border border-slate-200 bg-white px-3 py-1.5 text-xs sm:text-sm font-medium text-slate-700 shadow-sm hover:bg-slate-50">
          Challan list
        </a>
      </div>
    </div>

    <!-- ===== Layout: main form + sidebar ===== -->
    <form method="post" action="<?= $h($postUrl) ?>" class="grid gap-4 lg:grid-cols-[minmax(0,3fr)_minmax(0,1.2fr)]">
      <input type="hidden" name="_csrf" value="<?= $h($csrfToken) ?>">

      <!-- ========== LEFT: lines + notes ========== -->
      <div class="space-y-4">

        <!-- Challan meta card -->
        <div class="rounded-2xl border border-slate-200 bg-white px-4 py-3 sm:px-5 sm:py-4 shadow-sm">
          <div class="grid gap-3 sm:grid-cols-3 text-xs sm:text-sm text-slate-700">
            <div>
              <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-400">Customer</p>
              <p class="mt-0.5 font-medium text-slate-900"><?= $customer !== '' ? $h($customer) : '—' ?></p>
            </div>
            <div>
              <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-400">Challan date</p>
              <p class="mt-0.5"><?= $challanDate !== '' ? $h($challanDate) : '—' ?></p>
            </div>
            <div>
              <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-400">Vehicle / Driver</p>
              <p class="mt-0.5">
                <?= $vehicleNo !== '' ? $h($vehicleNo) : 'No vehicle set' ?>
                <?php if ($driverName !== ''): ?>
                  · <?= $h($driverName) ?>
                <?php endif; ?>
              </p>
            </div>
          </div>
        </div>

        <!-- Lines table card -->
        <div class="rounded-2xl border border-slate-200 bg-white shadow-sm">
          <div class="flex items-center justify-between border-b border-slate-200 px-4 py-2.5 sm:px-5">
            <div>
              <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">
                Delivery lines
              </p>
              <p class="text-[11px] text-slate-400">
                Update <span class="font-medium text-slate-700">Delivered</span> and <span class="font-medium text-slate-700">Returned</span> for each product.
              </p>
            </div>
            <div class="hidden text-xs font-medium text-slate-500 sm:block">
              Ordered / Delivered / Returned
            </div>
          </div>

          <div class="overflow-x-auto">
            <table class="min-w-full text-xs sm:text-sm">
              <thead class="bg-slate-50">
                <tr class="text-left text-[11px] font-semibold uppercase tracking-wide text-slate-500">
                  <th class="px-3 py-2 sm:px-4">#</th>
                  <th class="px-3 py-2 sm:px-4">Item</th>
                  <th class="px-3 py-2 sm:px-4 text-right">Ordered</th>
                  <th class="px-3 py-2 sm:px-4 text-right">Delivered</th>
                  <th class="px-3 py-2 sm:px-4 text-right">Returned</th>
                  <th class="px-3 py-2 sm:px-4">Return reason</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-slate-100">
              <?php if ($items): ?>
                <?php $row = 1; foreach ($items as $i): $iid = (int)($i['id'] ?? 0); ?>
                  <?php
                    $sku   = (string)($i['product_code'] ?? $i['sku'] ?? '');
                    $name  = (string)($i['product_name'] ?? $i['name'] ?? '');
                    $qOrd  = (float)($i['qty_ordered'] ?? $i['qty'] ?? 0);
                    $qDel  = (float)($i['qty_delivered'] ?? 0);
                    $qRet  = (float)($i['qty_returned']  ?? 0);
                    $reas  = (string)($i['return_reason'] ?? '');
                  ?>
                  <tr class="hover:bg-slate-50/70">
                    <td class="px-3 py-2.5 sm:px-4 align-top text-slate-500">
                      <?= $row++ ?>
                    </td>
                    <td class="px-3 py-2.5 sm:px-4">
                      <div class="font-medium text-slate-900"><?= $h($name) ?></div>
                      <div class="mt-0.5 text-[11px] text-slate-500">
                        <?php if ($sku !== ''): ?>
                          Code: <span class="font-mono"><?= $h($sku) ?></span>
                        <?php else: ?>
                          —
                        <?php endif; ?>
                      </div>
                    </td>
                    <td class="px-3 py-2.5 sm:px-4 text-right align-middle text-slate-700">
                      <?= $h((string)$qOrd) ?>
                    </td>
                    <td class="px-3 py-2.5 sm:px-4 text-right align-middle">
                      <input
                        type="number"
                        step="0.01"
                        name="items[<?= $iid ?>][delivered]"
                        value="<?= $h((string)$qDel) ?>"
                        class="w-20 sm:w-24 rounded-lg border border-slate-200 bg-white px-2 py-1 text-right text-xs sm:text-sm text-slate-900 focus:border-[#228B22] focus:ring-1 focus:ring-[#228B22]"
                      >
                    </td>
                    <td class="px-3 py-2.5 sm:px-4 text-right align-middle">
                      <input
                        type="number"
                        step="0.01"
                        name="items[<?= $iid ?>][returned]"
                        value="<?= $h((string)$qRet) ?>"
                        class="w-20 sm:w-24 rounded-lg border border-slate-200 bg-white px-2 py-1 text-right text-xs sm:text-sm text-slate-900 focus:border-[#228B22] focus:ring-1 focus:ring-[#228B22]"
                      >
                    </td>
                    <td class="px-3 py-2.5 sm:px-4 align-middle">
                      <input
                        type="text"
                        name="items[<?= $iid ?>][reason]"
                        value="<?= $h($reas) ?>"
                        placeholder="Optional reason (damage, missing...)"
                        class="w-full rounded-lg border border-slate-200 bg-white px-2 py-1 text-xs sm:text-sm text-slate-900 placeholder:text-slate-400 focus:border-[#228B22] focus:ring-1 focus:ring-[#228B22]"
                      >
                    </td>
                  </tr>
                <?php endforeach; ?>
              <?php else: ?>
                <tr>
                  <td colspan="6" class="px-4 py-8 text-center text-sm text-slate-500">
                    No lines found for this challan.
                  </td>
                </tr>
              <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>

        <!-- Notes -->
        <div class="rounded-2xl border border-slate-200 bg-white px-4 py-3 sm:px-5 sm:py-4 shadow-sm">
          <label class="block text-xs font-semibold uppercase tracking-wide text-slate-500 mb-1.5">
            Internal notes / delivery remarks
          </label>
          <textarea
            name="notes"
            rows="3"
            class="block w-full rounded-2xl border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-900 placeholder:text-slate-400 focus:border-[#228B22] focus:bg-white focus:ring-1 focus:ring-[#228B22]"
            placeholder="e.g. 2 cartons returned due to short expiry."
          ><?= $h($notes) ?></textarea>
        </div>

        <!-- Bottom buttons -->
        <div class="flex flex-wrap gap-2">
          <button
            type="submit"
            class="inline-flex items-center rounded-xl border border-[#228B22] bg-[#228B22] px-5 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-emerald-700"
          >
            Save delivery update
          </button>
          <a
            href="<?= $h($backUrl) ?>"
            class="inline-flex items-center rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-sm font-medium text-slate-700 hover:bg-slate-50"
          >
            Cancel
          </a>
        </div>
      </div>

      <!-- ========== RIGHT: summary ========== -->
      <aside class="space-y-4">
        <div class="rounded-2xl border border-slate-200 bg-white px-4 py-3 sm:px-5 sm:py-4 shadow-sm">
          <p class="text-xs font-semibold uppercase tracking-wide text-slate-500 mb-2">
            Quantity summary
          </p>
          <dl class="space-y-1.5 text-sm text-slate-700">
            <div class="flex justify-between">
              <dt class="text-slate-500">Total ordered</dt>
              <dd class="font-medium"><?= $h(number_format($totalOrdered, 2)) ?></dd>
            </div>
            <div class="flex justify-between">
              <dt class="text-slate-500">Total delivered</dt>
              <dd class="font-medium"><?= $h(number_format($totalDelivered, 2)) ?></dd>
            </div>
            <div class="flex justify-between">
              <dt class="text-slate-500">Total returned</dt>
              <dd class="font-medium text-amber-700"><?= $h(number_format($totalReturned, 2)) ?></dd>
            </div>
          </dl>

          <?php if ($dispatchAt !== ''): ?>
            <div class="mt-3 border-t border-dashed border-slate-200 pt-3 text-xs text-slate-600">
              <p class="font-semibold text-slate-700 mb-0.5">Initial dispatch time</p>
              <p><?= $h($dispatchAt) ?></p>
            </div>
          <?php endif; ?>
        </div>

        <!-- How to use this page -->
        <div class="rounded-2xl border border-dashed border-slate-300 bg-slate-50 px-4 py-3 sm:px-5 sm:py-4 text-xs sm:text-sm text-slate-700">
          <p class="mb-1.5 text-sm font-semibold text-slate-900">
            How to use this page
          </p>
          <ul class="list-disc space-y-1 pl-4">
            <li>Check each line and update the <strong>Delivered</strong> and <strong>Returned</strong> quantities after the trip.</li>
            <li>Use the <strong>Return reason</strong> field to capture why items came back (damage, refused, stock issue, etc.).</li>
            <li>Use the <strong>Notes</strong> box for any extra remarks that should stay with this challan.</li>
            <li>Click <strong>Save delivery update</strong> to store the changes and return to the challan details page.</li>
          </ul>
        </div>
      </aside>
    </form>
  </div>
</div>