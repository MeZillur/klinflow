<?php declare(strict_types=1);
/**
 * Master Dispatch Challan (preview from selected dms_challans)
 *
 * Inputs:
 *  - $org         array (cp_organizations row)
 *  - $module_base string (/t/{slug}/apps/dms)
 *  - $challans    array:
 *        EITHER plain rows from dms_challans
 *        OR   each element = ['header' => dms_challans row, 'items' => dms_challans_items[]]
 *  - $ids         array of challan ids
 */

$h = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');

$org         = $org         ?? [];
$module_base = rtrim((string)($module_base ?? ''), '/');
$challans    = is_array($challans ?? null) ? $challans : [];
$ids         = is_array($ids ?? null) ? $ids : [];

$orgName    = trim((string)($org['name']    ?? ''));
$orgAddress = trim((string)($org['address'] ?? ''));

/* ---------- simple aggregates (supports header+items structure) ---------- */
$totalChallans = count($challans);
$totalLines    = 0;
$totalQty      = 0.0;
$subtotal      = 0.0;

foreach ($challans as $cRaw) {
    $header = $cRaw['header'] ?? $cRaw;
    $items  = is_array($cRaw['items'] ?? null) ? $cRaw['items'] : [];

    if ($items) {
        foreach ($items as $it) {
            $q  = (float)($it['qty_ordered'] ?? $it['qty'] ?? 0);
            $up = (float)($it['unit_price'] ?? $it['price'] ?? 0);
            $lt = (float)($it['line_total'] ?? $it['total'] ?? ($q * $up));

            $totalLines++;
            $totalQty += $q;
            $subtotal += $lt;
        }
    } else {
        $totalLines += (int)($header['total_items'] ?? 0);
        $totalQty   += (float)($header['total_qty'] ?? 0);
        $subtotal   += (float)($header['grand_total'] ?? 0);
    }
}

/* ---------- first header for customer block ---------- */
$firstHeader = [];
if ($challans) {
    $firstHeader = $challans[0]['header'] ?? $challans[0];
}
?>
<style>
  @media print {
    .kf-no-print { display:none !important; }
    body { background:#ffffff !important; }
  }
</style>

<form id="masterForm"
      method="post"
      action="<?= $h($module_base) ?>/challan/master-from-challan/store">
  <?php if (class_exists('\Shared\Csrf')) { echo \Shared\Csrf::field(); } ?>

  <input type="hidden" name="ids" value="<?= $h(implode(',', $ids)) ?>">

  <!-- mode + totals (kept in sync from Alpine) -->
  <input type="hidden" name="mode" x-model="mode">
  <input type="hidden" name="subtotal" x-bind:value="subtotal.toFixed(2)">
  <input type="hidden" name="return_amount" x-bind:value="totalReturn.toFixed(2)">
  <input type="hidden" name="net_total" x-bind:value="netTotal.toFixed(2)">

  <div
    x-data="{
      // header / meta
      deliveryPerson: '',
      vehicleNo: '',
      routeText: '',
      dispatchAt: '<?= $h(date('Y-m-d H:i')) ?>',
      notes: 'Deliver after 9 AM. Contact receiver before arrival.',
      totalReceived: 0,

      // totals
      mode: 'final', // 'final' or 'draft'
      subtotal: <?= json_encode((float)$subtotal) ?>,
      totalReturn: 0,
      get netTotal() { return this.subtotal - this.totalReturn; },

      finalize() {
        this.mode = 'final';
        const f = document.getElementById('masterForm');
        if (f) f.submit();
      },
      saveDraft() {
        this.mode = 'draft';
        const f = document.getElementById('masterForm');
        if (f) f.submit();
      },
      previewPrint() { window.print(); },
      markDispatched() { alert('Mark selected as dispatched: backend wiring pending.'); },
      backToList() { window.location = '<?= $h($module_base) ?>/challan'; },
      printPage() { window.print(); }
    }"
    @row-return-updated.window="
      totalReturn = (totalReturn - (parseFloat($event.detail.oldAmount) || 0)) + (parseFloat($event.detail.newAmount) || 0)
    "
    class="min-h-screen bg-white px-3 py-4 sm:px-5 lg:px-8"
  >
    <div class="mx-auto max-w-7xl space-y-4">

      <!-- ===== TOP HEADER BAR ===== -->
      <div class="bg-white border border-slate-200 shadow-sm rounded-2xl px-4 py-3 sm:px-6 sm:py-4 flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
        <div class="space-y-1">
          <h1 class="text-2xl font-semibold text-slate-900 flex items-center gap-2">
            Master Dispatch Challan
            <?php if ($totalChallans): ?>
              <span class="inline-flex items-center rounded-full border border-emerald-500/40 bg-emerald-50 px-2.5 py-0.5 text-xs font-medium text-emerald-700">
                <?= (int)$totalChallans ?> challan(s)
              </span>
            <?php endif; ?>
          </h1>
          <p class="text-sm text-slate-600">
            Combined from challan IDs:
            <span class="font-mono"><?= $h(implode(',', $ids)) ?></span>
          </p>
          <?php if ($orgName !== ''): ?>
            <p class="text-xs text-slate-500">
              <?= $h($orgName) ?>
              <?php if ($orgAddress !== ''): ?>
                · <?= $h($orgAddress) ?>
              <?php endif; ?>
            </p>
          <?php endif; ?>
        </div>

        <div class="flex flex-col items-start gap-2 sm:flex-row sm:items-center sm:gap-3 lg:justify-end kf-no-print">
          <!-- actions right side -->
          <div class="flex flex-wrap gap-2">
            <button
              type="button"
              @click="backToList()"
              class="inline-flex items-center rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 text-xs sm:text-sm font-medium text-slate-700 hover:bg-slate-100"
            >
              ← Back to challans
            </button>
            <button
              type="button"
              @click="printPage()"
              class="inline-flex items-center rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 text-xs sm:text-sm font-medium text-slate-700 hover:bg-slate-100"
            >
              Download / Print
            </button>
            <button
              type="button"
              @click="markDispatched()"
              class="inline-flex items-center rounded-xl border border-[#228B22] bg-[#228B22] px-3 py-2 text-xs sm:text-sm font-semibold text-white shadow-sm hover:bg-emerald-700"
            >
              Mark selected dispatched
            </button>
          </div>

          <!-- status chip -->
          <div class="inline-flex items-center rounded-full bg-emerald-50 px-3 py-1 text-xs">
            <span class="mr-1.5 h-1.5 w-1.5 rounded-full bg-amber-400"></span>
            <span class="text-slate-600">
              Status:
              <span class="font-medium text-slate-900">Waiting for dispatch</span>
            </span>
          </div>
        </div>
      </div>

      <!-- ===== DELIVERY DETAIL PILLS ===== -->
      <div class="bg-white border border-slate-200 shadow-sm rounded-2xl px-4 py-3 sm:px-6 sm:py-4 kf-no-print">
        <div class="grid gap-3 md:grid-cols-4">
          <!-- Delivery person -->
          <div class="border border-slate-200 rounded-xl px-3 py-2.5">
            <p class="text-xs font-medium text-slate-400 uppercase tracking-wide">Delivery person</p>
            <input
              x-model="deliveryPerson"
              name="deliveryPerson"
              type="text"
              class="mt-1 block w-full border-0 bg-transparent px-0 py-0 text-sm text-slate-900 placeholder:text-slate-400 focus:ring-0"
              placeholder="Md. Saiful Islam"
            >
          </div>
          <!-- Vehicle no -->
          <div class="border border-slate-200 rounded-xl px-3 py-2.5">
            <p class="text-xs font-medium text-slate-400 uppercase tracking-wide">Vehicle no.</p>
            <input
              x-model="vehicleNo"
              name="vehicleNo"
              type="text"
              class="mt-1 block w-full border-0 bg-transparent px-0 py-0 text-sm text-slate-900 placeholder:text-slate-400 focus:ring-0"
              placeholder="Dhaka-12-3456"
            >
          </div>
          <!-- Route -->
          <div class="border border-slate-200 rounded-xl px-3 py-2.5">
            <p class="text-xs font-medium text-slate-400 uppercase tracking-wide">Route</p>
            <input
              x-model="routeText"
              name="routeText"
              type="text"
              class="mt-1 block w-full border-0 bg-transparent px-0 py-0 text-sm text-slate-900 placeholder:text-slate-400 focus:ring-0"
              placeholder="Dhaka → Narayanganj"
            >
          </div>
          <!-- Dispatch datetime -->
          <div class="border border-slate-200 rounded-xl px-3 py-2.5">
            <p class="text-xs font-medium text-slate-400 uppercase tracking-wide">Dispatch date</p>
            <input
              x-model="dispatchAt"
              name="dispatchAt"
              type="text"
              class="mt-1 block w-full border-0 bg-transparent px-0 py-0 text-sm text-slate-900 placeholder:text-slate-400 focus:ring-0"
              placeholder="2025-11-26 09:30"
            >
          </div>
        </div>
      </div>

      <!-- ===== MAIN GRID: LEFT (items) + RIGHT (summary) ===== -->
      <div class="grid gap-4 lg:grid-cols-[minmax(0,3.2fr)_minmax(0,1fr)]">
        <!-- LEFT BIG CARD -->
        <div class="bg-white border border-slate-200 shadow-sm rounded-2xl px-4 py-4 sm:px-6 sm:py-5">
          <!-- prepared by / customer block -->
          <div class="flex flex-wrap justify-between gap-4 mb-4">
            <div class="space-y-0.5">
              <p class="text-xs font-medium text-slate-400 uppercase tracking-wide">Prepared by</p>
              <p class="text-sm font-medium text-slate-900">
                <?= $h($_SESSION['tenant_user']['name'] ?? 'User') ?> · Accounts
              </p>
              <p class="text-xs text-slate-500">
                Generated at <?= $h(date('Y-m-d H:i')) ?>
              </p>
            </div>
            <div class="min-w-[220px] max-w-md">
              <p class="text-xs font-medium text-slate-400 uppercase tracking-wide">Customer</p>
              <?php
              $cust = '';
              if ($firstHeader) {
                  $cust = (string)($firstHeader['customer_name'] ?? '');
              }
              ?>
              <p class="text-sm font-medium text-slate-900">
                <?= $cust !== '' ? $h($cust) : 'Multiple customers' ?>
              </p>
              <?php if (!empty($firstHeader['ship_to_addr'] ?? '')): ?>
                <p class="text-xs text-slate-500">
                  <?= $h($firstHeader['ship_to_addr']) ?>
                </p>
              <?php endif; ?>
            </div>
          </div>

          <!-- TABLE HEADER -->
          <div class="rounded-2xl border border-slate-200 overflow-hidden">
            <div class="bg-slate-50 px-3 py-2 sm:px-4">
              <div class="grid grid-cols-[32px_minmax(0,1.7fr)_90px_110px_110px_120px_120px] gap-2 text-[11px] sm:text-xs font-semibold text-slate-500">
                <div>#</div>
                <div>Item</div>
                <div class="text-right">Qty ordered</div>
                <div class="text-right">Qty dispatch</div>
                <div class="text-right">Qty return</div>
                <div class="text-right">Return amount</div>
                <div class="text-right">Line total</div>
              </div>
            </div>

            <!-- TABLE BODY -->
            <div class="divide-y divide-slate-100 bg-white">
              <?php if ($challans): ?>
                <?php $i = 1; ?>
                <?php foreach ($challans as $cRaw): ?>
                  <?php
                    $header    = $cRaw['header'] ?? $cRaw;
                    $items     = is_array($cRaw['items'] ?? null) ? $cRaw['items'] : [];
                    $challanNo = $header['challan_no'] ?? ('#'.($header['id'] ?? ''));
                    $invoiceNo = $header['invoice_no'] ?? '—';
                    $custName  = $header['customer_name'] ?? '—';

                    if (!$items) {
                        $items = [[
                            'product_name' => 'Challan ' . $challanNo,
                            'meta_only'    => true,
                            'qty_ordered'  => (float)($header['total_qty'] ?? 0),
                            'unit_price'   => 0,
                            'line_total'   => (float)($header['grand_total'] ?? 0),
                        ]];
                    }
                  ?>

                  <?php foreach ($items as $item): ?>
                    <?php
                      $name        = $item['product_name'] ?? $item['name'] ?? ('Item '.$i);
                      $code        = $item['product_code'] ?? $item['code'] ?? null;

                      $qtyOrdered  = (float)($item['qty_ordered'] ?? $item['qty'] ?? 0);
                      $unitPrice   = (float)($item['unit_price'] ?? $item['price'] ?? 0);
                      $lineTotal   = (float)($item['line_total'] ?? $item['total'] ?? ($qtyOrdered * $unitPrice));
                      $returnAmt   = (float)($item['return_amount'] ?? 0);
                    ?>
                    <div
                      class="px-3 py-3 sm:px-4 sm:py-3.5"
                      x-data="{
                        qtyOrdered: <?= json_encode($qtyOrdered) ?>,
                        qtyDispatch: <?= json_encode($qtyOrdered) ?>,
                        qtyReturn: 0,
                        unitPrice: <?= json_encode($unitPrice) ?>,
                        lineBase: <?= json_encode($lineTotal) ?>,
                        lineTotal: <?= json_encode($lineTotal) ?>,
                        returnAmount: <?= json_encode($returnAmt) ?>,
                        onDispatchChange(e) {
                          const v = parseFloat(e.target.value) || 0;
                          this.qtyDispatch = v;
                          if (this.unitPrice > 0) {
                            this.lineBase = this.qtyDispatch * this.unitPrice;
                          }
                          this.lineTotal = this.lineBase - this.returnAmount;
                        },
                        onReturnChange(e) {
                          const oldAmount = this.returnAmount || 0;
                          const v = parseFloat(e.target.value) || 0;
                          this.qtyReturn = v;
                          this.returnAmount = this.unitPrice * this.qtyReturn;
                          this.lineTotal = this.lineBase - this.returnAmount;
                          $dispatch('row-return-updated', {
                            oldAmount: oldAmount,
                            newAmount: this.returnAmount
                          });
                        }
                      }"
                    >
                      <div class="grid grid-cols-[32px_minmax(0,1.7fr)_90px_110px_110px_120px_120px] gap-2 items-center text-xs sm:text-sm">
                        <!-- # -->
                        <div class="text-slate-500"><?= $i++ ?></div>

                        <!-- Item -->
                        <div>
                          <div class="font-medium text-slate-900">
                            <?= $h($name) ?>
                          </div>
                          <div class="text-[11px] text-slate-500">
                            <?php if ($code): ?>
                              Code: <?= $h($code) ?> ·
                            <?php endif; ?>
                            Challan <?= $h($challanNo) ?> · Invoice: <?= $h($invoiceNo) ?> · Customer: <?= $h($custName) ?>
                          </div>
                        </div>

                        <!-- Qty ordered -->
                        <div class="text-right text-slate-800" x-text="qtyOrdered"></div>

                        <!-- Qty dispatch input -->
                        <div class="text-right">
                          <div class="inline-flex justify-end">
                            <input
                              type="number"
                              x-model.number="qtyDispatch"
                              @input="onDispatchChange($event)"
                              class="w-24 rounded-lg border border-slate-200 bg-white px-2 py-1 text-xs sm:text-sm text-right text-slate-900 focus:border-[#228B22] focus:ring-1 focus:ring-[#228B22]"
                            >
                          </div>
                        </div>

                        <!-- Qty return input -->
                        <div class="text-right">
                          <div class="inline-flex justify-end">
                            <input
                              type="number"
                              x-model.number="qtyReturn"
                              @input="onReturnChange($event)"
                              class="w-24 rounded-lg border border-slate-200 bg-white px-2 py-1 text-xs sm:text-sm text-right text-slate-900 focus:border-[#228B22] focus:ring-1 focus:ring-[#228B22]"
                            >
                          </div>
                        </div>

                        <!-- Return amount -->
                        <div class="text-right text-slate-800">
                          <span x-text="`৳${(returnAmount || 0).toFixed(2)}`"></span>
                        </div>

                        <!-- Line total -->
                        <div class="text-right text-slate-900 font-medium">
                          <span x-text="`৳${(lineTotal || 0).toFixed(2)}`"></span>
                        </div>
                      </div>
                    </div>
                  <?php endforeach; ?>
                <?php endforeach; ?>
              <?php else: ?>
                <div class="px-4 py-8 text-center text-sm text-slate-500">
                  No challans loaded for this master challan.
                </div>
              <?php endif; ?>

              <!-- continuation area -->
              <div class="px-4 py-3 border-t border-dashed border-slate-200 text-[11px] text-slate-400">
                … item-wise lines are generated from selected challans and their related <code>dms_challans_items</code> …
              </div>
            </div>
          </div>

          <!-- NOTES -->
          <div class="mt-4">
            <label class="block text-xs font-medium text-slate-500 uppercase tracking-wide mb-1">
              Notes / driver instructions
            </label>
            <textarea
              x-model="notes"
              name="notes"
              rows="2"
              class="block w-full rounded-2xl border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-900 placeholder:text-slate-400 focus:border-[#228B22] focus:ring-1 focus:ring-[#228B22]"
            ></textarea>
          </div>

          <!-- BOTTOM BUTTONS -->
<div class="kf-no-print mt-5 flex flex-wrap gap-2">
  <button
    type="submit"
    name="mode"
    value="draft"
    @click="updateTotals()"
    class="inline-flex items-center rounded-xl border border-slate-200 bg-slate-50 px-4 py-2 text-xs sm:text-sm font-medium text-slate-700 hover:bg-slate-100"
  >
    Save draft
  </button>
  <button
    type="submit"
    name="mode"
    value="final"
    @click="updateTotals()"
    class="inline-flex items-center rounded-xl border border-[#228B22] bg-[#228B22] px-4 py-2 text-xs sm:text-sm font-semibold text-white shadow-sm hover:bg-emerald-700"
  >
    Finalize &amp; lock
  </button>
  <button
    type="button"
    @click="previewPrint()"
    class="inline-flex items-center rounded-xl border border-slate-200 bg-slate-50 px-4 py-2 text-xs sm:text-sm font-medium text-slate-700 hover:bg-slate-100"
  >
    Preview print
  </button>
</div>
        </div>

        <!-- RIGHT SUMMARY CARD -->
        <aside class="bg-white border border-slate-200 shadow-sm rounded-2xl px-4 py-4 sm:px-5 sm:py-5 flex flex-col gap-4 lg:max-w-xs">
          <!-- Dispatch summary -->
          <div>
            <p class="text-xs font-semibold text-slate-400 uppercase tracking-wide">
              Dispatch summary
            </p>
            <div class="mt-2 space-y-2 text-sm">
              <div class="flex justify-between">
                <span class="text-slate-500">Subtotal</span>
                <span class="font-medium text-slate-900" x-text="`৳${subtotal.toFixed(2)}`"></span>
              </div>
              <div class="flex justify-between">
                <span class="text-slate-500">Return amount</span>
                <span class="font-medium text-slate-900" x-text="`৳${totalReturn.toFixed(2)}`"></span>
              </div>
              <div class="flex justify-between">
                <span class="text-slate-500">Net total</span>
                <span class="font-semibold text-slate-900" x-text="`৳${netTotal.toFixed(2)}`"></span>
              </div>
            </div>

            <div class="mt-3">
              <label class="block text-xs font-medium text-slate-500 mb-1">
                Total received
              </label>
              <input
                x-model.number="totalReceived"
                name="totalReceived"
                type="number"
                class="block w-full rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-900 focus:border-[#228B22] focus:ring-1 focus:ring-[#228B22]"
                placeholder="0.00"
              >
            </div>
          </div>

          <!-- Delivery details -->
          <div class="border-top border-slate-200 pt-4 border-t">
            <p class="text-xs font-semibold text-slate-400 uppercase tracking-wide mb-2">
              Delivery details
            </p>
            <dl class="space-y-1 text-xs sm:text-sm text-slate-700">
              <div class="flex justify-between gap-2">
                <dt class="text-slate-500">Delivery person</dt>
                <dd class="font-medium text-right truncate" x-text="deliveryPerson || '—'"></dd>
              </div>
              <div class="flex justify-between gap-2">
                <dt class="text-slate-500">Contact</dt>
                <dd class="font-medium text-right truncate">+8801-2345-6789</dd>
              </div>
              <div class="flex justify-between gap-2">
                <dt class="text-slate-500">Vehicle no.</dt>
                <dd class="font-medium text-right truncate" x-text="vehicleNo || '—'"></dd>
              </div>
              <div class="flex justify-between gap-2">
                <dt class="text-slate-500">Route</dt>
                <dd class="font-medium text-right truncate" x-text="routeText || '—'"></dd>
              </div>
              <div class="flex justify-between gap-2">
                <dt class="text-slate-500">Dispatch date</dt>
                <dd class="font-medium text-right truncate" x-text="dispatchAt"></dd>
              </div>
            </dl>
          </div>

          <!-- Quick menus -->
          <div class="kf-no-print border-t border-slate-200 pt-4 space-y-2">
            <p class="text-xs font-semibold text-slate-400 uppercase tracking-wide">
              Quick menus
            </p>
            <div class="flex flex-col gap-2">
              <a
                href="<?= $h($module_base) ?>/orders"
                class="inline-flex items-center justify-between rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 text-xs sm:text-sm text-slate-700 hover:bg-slate-100"
              >
                <span>View orders</span>
                <span class="text-slate-400">↗</span>
              </a>
              <a
                href="<?= $h($module_base) ?>/inventory/aging"
                class="inline-flex items-center justify-between rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 text-xs sm:text-sm text-slate-700 hover:bg-slate-100"
              >
                <span>Inventory aging</span>
                <span class="text-slate-400">↗</span>
              </a>
            </div>
          </div>

          <!-- big mark dispatched button -->
          <div class="kf-no-print pt-2">
            <button
              type="button"
              @click="markDispatched()"
              class="inline-flex w-full items-center justify-center rounded-xl border border-[#228B22] bg-[#228B22] px-4 py-2.5 text-xs sm:text-sm font-semibold text-white shadow-sm hover:bg-emerald-700"
            >
              Mark selected as dispatched
            </button>
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
          <li>From <strong>Dispatch Challans</strong>, select multiple challans using the checkboxes.</li>
          <li>Click <strong>“Make master challan”</strong> — this master view opens with all selected challans.</li>
          <li>Item-wise lines are pulled from the selected challans; adjust dispatch / return quantities if needed.</li>
          <li>Return amount and net total update automatically when you enter return quantities.</li>
          <li>Use <strong>Finalize &amp; lock</strong> to save this master challan, or <strong>Save draft</strong> to keep it editable.</li>
        </ul>
      </div>
    </div>
  </div>
</form>