<?php
declare(strict_types=1);
/**
 * DMS — Delivery Challan Show
 *
 * Vars from controller:
 *   @var array $challan  dms_challan row
 *   @var array $items    dms_challan_items[] rows
 *   @var array $ctx      context (org, module_base, etc.)
 */

$h = fn($s)=>htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');

// Always prefer slug-based module base: /t/{slug}/apps/dms
$slug = (string)($ctx['slug'] ?? ($_SESSION['tenant_org']['slug'] ?? ''));
if ($slug !== '' && $slug !== '_') {
    $base = '/t/' . rawurlencode($slug) . '/apps/dms';
} else {
    $base = rtrim((string)($ctx['module_base'] ?? '/apps/dms'), '/');
}

/* --------- Header fields --------- */
$id        = (int)($challan['id'] ?? 0);
$no        = (string)($challan['challan_no'] ?? ('CH-'.$id));
$date      = (string)($challan['challan_date'] ?? '');
$status    = strtolower((string)($challan['status'] ?? 'ready'));
$saleId    = (int)($challan['sale_id'] ?? 0);
$invoiceId = (int)($challan['invoice_id'] ?? 0);

$customerName = trim((string)($challan['customer_name'] ?? ''));
$vehicleNo    = trim((string)($challan['vehicle_no'] ?? ''));
$driverName   = trim((string)($challan['driver_name'] ?? ''));
$remarks      = trim((string)($challan['notes'] ?? $challan['remarks'] ?? ''));

$createdAt   = (string)($challan['created_at'] ?? '');
$dispatchAt  = (string)($challan['dispatch_at'] ?? '');
$deliveredAt = (string)($challan['delivered_at'] ?? ''); // safe even if column not present

$paymentReceived = (float)($challan['payment_received'] ?? 0);

/* --------- Totals from items --------- */
$totalQty   = 0.0;
$grandTotal = 0.0;
foreach ($items as $ln) {
    $qty = (float)($ln['qty'] ?? 0);
    $up  = (float)($ln['unit_price'] ?? 0);
    $totalQty   += $qty;
    $grandTotal += $qty * $up;
}

/* --------- Status badge styles --------- */
$statusLabel = ucfirst($status);
$badgeClass  = 'bg-slate-100 text-slate-800 border border-slate-200';

switch ($status) {
    case 'ready':
        $statusLabel = 'Ready for dispatch';
        $badgeClass  = 'bg-emerald-50 text-emerald-800 border border-emerald-200';
        break;
    case 'dispatched':
        $statusLabel = 'Dispatched';
        $badgeClass  = 'bg-sky-50 text-sky-800 border border-sky-200';
        break;
    case 'delivered':
        $statusLabel = 'Delivered';
        $badgeClass  = 'bg-emerald-100 text-emerald-900 border border-emerald-300';
        break;
    case 'cancelled':
        $statusLabel = 'Cancelled';
        $badgeClass  = 'bg-rose-50 text-rose-800 border border-rose-200';
        break;
}

/* --------- Simple timeline “step index” --------- */
$stepIndex = 1; // ready
if ($status === 'dispatched') $stepIndex = 2;
if ($status === 'delivered')  $stepIndex = 3;
if ($status === 'cancelled')  $stepIndex = 0; // special
?>
<div class="px-6 py-6 space-y-6">
  <!-- Top bar: title + actions -->
  <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
    <div class="space-y-1">
      <div class="flex flex-wrap items-center gap-3">
        <h1 class="text-xl font-semibold text-slate-900">
          Delivery Challan <span class="text-slate-500">#<?= $h($no) ?></span>
        </h1>
        <span class="inline-flex items-center rounded-full px-3 py-1 text-xs font-medium <?= $h($badgeClass) ?>">
          <?= $h($statusLabel) ?>
        </span>
      </div>
      <p class="text-xs text-slate-500">
        Date: <span class="font-medium text-slate-700"><?= $h($date ?: '—') ?></span>
        <?php if ($createdAt): ?>
          · Created: <span><?= $h($createdAt) ?></span>
        <?php endif; ?>
      </p>

      <?php if ($saleId || $invoiceId): ?>
        <p class="text-xs text-slate-500 space-x-2">
          <?php if ($saleId): ?>
            <span>
              Invoice:
              <a href="<?= $h($base) ?>/sales/<?= $saleId ?>"
                 class="text-emerald-700 hover:text-emerald-800 hover:underline">
                #<?= $saleId ?>
              </a>
            </span>
          <?php endif; ?>
          <?php if ($invoiceId && $invoiceId !== $saleId): ?>
            <span>
              · Invoice ID:
              <span class="font-mono text-slate-700">#<?= $invoiceId ?></span>
            </span>
          <?php endif; ?>
        </p>
      <?php endif; ?>
    </div>

    <div class="flex flex-wrap gap-2 justify-end">
      <a href="<?= $h($base) ?>/challan"
   class="inline-flex items-center rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50">
  ← Back to challans
</a>
      <?php if ($saleId): ?>
        <a href="<?= $h($base) ?>/sales/<?= $saleId ?>"
           class="inline-flex items-center rounded-lg border border-emerald-600 bg-white px-3 py-1.5 text-xs font-medium text-emerald-700 hover:bg-emerald-50">
          View invoice
        </a>
      <?php endif; ?>
      <!-- Placeholder for future print route -->
      <!--
      <a href="<?= $h($base) ?>/challan/<?= $id ?>/print"
         class="inline-flex items-center rounded-lg bg-emerald-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-emerald-700">
        Print challan
      </a>
      -->
    </div>
  </div>

  <!-- Main layout: meta + amounts -->
  <div class="grid grid-cols-1 gap-5 lg:grid-cols-3">
    <!-- Dispatch & customer block -->
    <section class="lg:col-span-2 rounded-xl border border-slate-200 bg-white/80 shadow-sm">
      <div class="border-b border-slate-100 px-4 py-3 flex items-center justify-between gap-3">
        <h2 class="text-sm font-semibold text-slate-900">Dispatch &amp; customer</h2>
        <?php if ($customerName !== ''): ?>
          <span class="inline-flex items-center rounded-full bg-emerald-50 px-2.5 py-1 text-[11px] font-medium text-emerald-800">
            <?= $h($customerName) ?>
          </span>
        <?php endif; ?>
      </div>

      <div class="px-4 py-4 grid grid-cols-1 gap-4 md:grid-cols-2 text-xs text-slate-600">
        <div class="space-y-2">
          <div>
            <div class="text-[11px] uppercase tracking-wide text-slate-400">Challan no.</div>
            <div class="font-medium text-slate-800"><?= $h($no) ?></div>
          </div>
          <?php if ($customerName !== ''): ?>
          <div>
            <div class="text-[11px] uppercase tracking-wide text-slate-400">Customer</div>
            <div class="font-medium text-slate-800"><?= $h($customerName) ?></div>
          </div>
          <?php endif; ?>
          <?php if ($remarks !== ''): ?>
          <div>
            <div class="text-[11px] uppercase tracking-wide text-slate-400">Remarks</div>
            <div class="whitespace-pre-line text-slate-700"><?= $h($remarks) ?></div>
          </div>
          <?php endif; ?>
        </div>

        <div class="space-y-2">
          <div class="grid grid-cols-2 gap-3">
            <div>
              <div class="text-[11px] uppercase tracking-wide text-slate-400">Vehicle no.</div>
              <div class="font-medium text-slate-800"><?= $h($vehicleNo ?: '—') ?></div>
            </div>
            <div>
              <div class="text-[11px] uppercase tracking-wide text-slate-400">Driver</div>
              <div class="font-medium text-slate-800"><?= $h($driverName ?: '—') ?></div>
            </div>
          </div>

          <div class="grid grid-cols-3 gap-3">
            <div>
              <div class="text-[11px] uppercase tracking-wide text-slate-400">Created</div>
              <div class="text-slate-700"><?= $h($createdAt ?: '—') ?></div>
            </div>
            <div>
              <div class="text-[11px] uppercase tracking-wide text-slate-400">Dispatched</div>
              <div class="text-slate-700"><?= $h($dispatchAt ?: '—') ?></div>
            </div>
            <div>
              <div class="text-[11px] uppercase tracking-wide text-slate-400">Delivered</div>
              <div class="text-slate-700"><?= $h($deliveredAt ?: '—') ?></div>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- Amount / stats / timeline -->
    <section class="rounded-xl border border-slate-200 bg-white/80 shadow-sm flex flex-col justify-between">
      <div class="border-b border-slate-100 px-4 py-3">
        <h2 class="text-sm font-semibold text-slate-900">Challan summary</h2>
      </div>

      <div class="px-4 py-3 space-y-3 text-xs text-slate-600">
        <div class="grid grid-cols-2 gap-3">
          <div>
            <div class="text-[11px] uppercase tracking-wide text-slate-400">Items</div>
            <div class="text-sm font-semibold text-slate-900"><?= count($items) ?></div>
          </div>
          <div>
            <div class="text-[11px] uppercase tracking-wide text-slate-400">Total quantity</div>
            <div class="text-sm font-semibold text-slate-900">
              <?= number_format($totalQty, 2) ?>
            </div>
          </div>
        </div>

        <div class="grid grid-cols-2 gap-3">
          <div>
            <div class="text-[11px] uppercase tracking-wide text-slate-400">Challan value</div>
            <div class="text-sm font-semibold text-slate-900">
              <?= number_format($grandTotal, 2) ?> <span class="text-[11px] font-normal text-slate-500">BDT</span>
            </div>
          </div>
          <div>
            <div class="text-[11px] uppercase tracking-wide text-slate-400">Payment on this challan</div>
            <div class="text-sm font-semibold text-slate-900">
              <?= number_format($paymentReceived, 2) ?> <span class="text-[11px] font-normal text-slate-500">BDT</span>
            </div>
          </div>
        </div>

        <!-- Status timeline -->
        <div class="mt-2">
          <div class="mb-1 text-[11px] uppercase tracking-wide text-slate-400">Dispatch progress</div>
          <div class="flex items-center justify-between text-[10px] text-slate-500">
            <?php
              $steps = [
                1 => ['key'=>'ready',      'label'=>'Ready'],
                2 => ['key'=>'dispatched', 'label'=>'Dispatched'],
                3 => ['key'=>'delivered',  'label'=>'Delivered'],
              ];
            ?>
            <?php foreach ($steps as $i => $s): ?>
              <?php
                $done   = $stepIndex >= $i;
                $active = $stepIndex === $i;
              ?>
              <div class="flex-1 flex items-center last:flex-none">
                <div class="flex flex-col items-center">
                  <div class="relative flex items-center justify-center">
                    <div class="h-2 w-10 -z-10 translate-y-1/2 hidden sm:block">
                      <?php if ($i < 3): ?>
                        <div class="h-px w-full <?= $done ? 'bg-emerald-500' : 'bg-slate-200' ?>"></div>
                      <?php endif; ?>
                    </div>
                    <div class="h-5 w-5 rounded-full border
                      <?= $done ? 'border-emerald-500 bg-emerald-500' : 'border-slate-300 bg-white' ?>">
                      <?php if ($done): ?>
                        <div class="h-5 w-5 flex items-center justify-center text-[9px] text-white">✓</div>
                      <?php endif; ?>
                    </div>
                  </div>
                  <span class="mt-1 <?= $active ? 'text-emerald-700 font-semibold' : '' ?>">
                    <?= $h($s['label']) ?>
                  </span>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
          <?php if ($status === 'cancelled'): ?>
            <p class="mt-1 text-[11px] text-rose-700">
              This challan is cancelled. Keep it for audit trail, but do not dispatch against it.
            </p>
          <?php endif; ?>
        </div>
      </div>
    </section>
  </div>

  <!-- Items table -->
  <section class="rounded-xl border border-slate-200 bg-white/80 shadow-sm">
    <div class="border-b border-slate-100 px-4 py-3 flex items-center justify-between">
      <h2 class="text-sm font-semibold text-slate-900">Items in this challan</h2>
      <p class="text-[11px] text-slate-500">
        Showing <?= count($items) ?> line(s), total qty
        <span class="font-semibold text-slate-700"><?= number_format($totalQty, 2) ?></span>
      </p>
    </div>

    <div class="max-h-[420px] overflow-auto">
      <table class="min-w-full text-xs text-slate-700">
        <thead class="bg-slate-50 text-[11px] uppercase tracking-wide text-slate-500">
          <tr>
            <th class="px-3 py-2 text-left">SL</th>
            <th class="px-3 py-2 text-left">Item</th>
            <th class="px-3 py-2 text-left hidden md:table-cell">Code</th>
            <th class="px-3 py-2 text-right">Qty</th>
            <th class="px-3 py-2 text-right hidden sm:table-cell">Unit price</th>
            <th class="px-3 py-2 text-right hidden sm:table-cell">Line total</th>
          </tr>
        </thead>
        <tbody>
        <?php if (!$items): ?>
          <tr>
            <td colspan="6" class="px-3 py-6 text-center text-xs text-slate-400">
              No items recorded on this challan.
            </td>
          </tr>
        <?php else: ?>
          <?php
            $i = 1;
            foreach ($items as $ln):
              $name = (string)($ln['product_name'] ?? $ln['name'] ?? '');
              $code = (string)($ln['product_code'] ?? $ln['sku'] ?? $ln['item_code'] ?? '');
              $qty  = (float)($ln['qty'] ?? 0);
              $up   = (float)($ln['unit_price'] ?? 0);
              $line = $qty * $up;
          ?>
          <tr class="border-t border-slate-100 hover:bg-emerald-50/40">
            <td class="px-3 py-2 align-middle text-slate-500"><?= $i++ ?></td>
            <td class="px-3 py-2 align-middle">
              <div class="font-medium text-slate-900"><?= $h($name ?: '—') ?></div>
              <?php if ($code): ?>
              <div class="text-[11px] text-slate-500 md:hidden"><?= $h($code) ?></div>
              <?php endif; ?>
            </td>
            <td class="px-3 py-2 align-middle text-slate-600 hidden md:table-cell">
              <?= $h($code ?: '—') ?>
            </td>
            <td class="px-3 py-2 align-middle text-right font-medium">
              <?= number_format($qty, 2) ?>
            </td>
            <td class="px-3 py-2 align-middle text-right hidden sm:table-cell">
              <?= number_format($up, 2) ?>
            </td>
            <td class="px-3 py-2 align-middle text-right hidden sm:table-cell">
              <?= number_format($line, 2) ?>
            </td>
          </tr>
          <?php endforeach; ?>
        <?php endif; ?>
        </tbody>

        <?php if ($items): ?>
        <tfoot class="border-t border-slate-100 bg-slate-50 text-xs">
          <tr>
            <th colspan="3" class="px-3 py-2 text-right font-semibold text-slate-800">
              Challan total
            </th>
            <th class="px-3 py-2 text-right font-semibold text-slate-900">
              <?= number_format($totalQty, 2) ?>
            </th>
            <th class="px-3 py-2 text-right hidden sm:table-cell font-semibold text-slate-900">
              —
            </th>
            <th class="px-3 py-2 text-right hidden sm:table-cell font-semibold text-slate-900">
              <?= number_format($grandTotal, 2) ?>
            </th>
          </tr>
        </tfoot>
        <?php endif; ?>
      </table>
    </div>
  </section>

  <!-- How to use this page -->
  <section class="rounded-xl border border-dashed border-emerald-300 bg-emerald-50/70 px-4 py-3 text-xs text-emerald-900 space-y-1">
    <h3 class="text-[11px] font-semibold uppercase tracking-wide text-emerald-800">
      How to use this page
    </h3>
    <ul class="list-disc pl-4 space-y-1">
      <li>
        Use this challan as your official <strong>dispatch document</strong> for the invoice:
        verify quantities and items against the invoice before loading goods.
      </li>
      <li>
        The <strong>Dispatch progress</strong> bar shows whether the challan is only ready,
        already dispatched, or fully delivered. This helps your team and accounts stay aligned.
      </li>
      <li>
        The <strong>Challan summary</strong> on the right shows total quantity, challan value
        in BDT, and any payment recorded at the time of dispatch.
      </li>
      <li>
        After delivery, you can update the challan status from your dispatch workflow
        (future enhancement) to mark it as <strong>Delivered</strong> and lock the quantities.
      </li>
      <li>
        For audits, keep the challan, invoice, and delivery acknowledgements together
        so you can trace <strong>order → invoice → challan → payment</strong> in one chain.
      </li>
    </ul>
  </section>
</div>