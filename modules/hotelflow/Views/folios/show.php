<?php
/** @var array  $ctx */
/** @var string $module_base ?? */
/** @var array  $folio */
/** @var array  $lines ?? */
/** @var array  $payments ?? */

$h     = fn($v)=>htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
$base  = rtrim((string)($module_base ?? ($ctx['module_base'] ?? '/apps/hotelflow')), '/');
$lines = $lines    ?? [];
$payments = $payments ?? [];

$id        = (int)($folio['id'] ?? 0);
$code      = (string)($folio['code'] ?? ($folio['folio_code'] ?? ('FOLIO-'.$id)));
$status    = strtolower((string)($folio['status'] ?? 'open'));
$currency  = (string)($folio['currency'] ?? 'BDT');
$guestName = (string)($folio['guest_name'] ?? '');
$guestMobile = (string)($folio['guest_mobile'] ?? '');
$resCode   = (string)($folio['reservation_code'] ?? '');
$checkIn   = (string)($folio['check_in'] ?? '');
$checkOut  = (string)($folio['check_out'] ?? '');
$openedAt  = (string)($folio['opened_at'] ?? ($folio['created_at'] ?? ''));
$closedAt  = (string)($folio['closed_at'] ?? '');
$balance   = (float)($folio['balance_due'] ?? 0.0);

/* Simple totals based on lines/payments if available */
$totalCharges  = 0.0;
$totalPayments = 0.0;

foreach ($lines as $ln) {
    $type   = strtolower((string)($ln['line_type'] ?? $ln['type'] ?? 'charge'));
    $amount = (float)($ln['amount'] ?? 0);
    if (in_array($type, ['charge', 'debit'], true)) {
        $totalCharges += $amount;
    } elseif (in_array($type, ['credit', 'adjustment'], true)) {
        $totalCharges += $amount; // or adjust differently later
    }
}
foreach ($payments as $p) {
    $totalPayments += (float)($p['amount'] ?? 0);
}

$badgeClass = match ($status) {
    'open', 'in_house', 'inhouse'   => 'bg-emerald-50 text-emerald-700 border-emerald-200',
    'pending', 'proforma'           => 'bg-amber-50 text-amber-800 border-amber-200',
    'closed'                        => 'bg-slate-100 text-slate-700 border-slate-300',
    default                         => 'bg-slate-50 text-slate-700 border-slate-200',
};
$label = function (string $status): string {
    $s = strtolower($status);
    return match ($s) {
        'open'         => 'Open',
        'in_house',
        'inhouse'      => 'In-house',
        'pending'      => 'Pending',
        'proforma'     => 'Pro-forma',
        'closed'       => 'Closed',
        default        => ucfirst($s),
    };
};
?>
<div class="max-w-6xl mx-auto space-y-6">

  <!-- Header + tiny nav -->
  <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
    <div>
      <div class="flex items-center gap-2 text-xs text-slate-500 mb-1">
        <a href="<?= $h($base) ?>/folios" class="hover:text-slate-700 inline-flex items-center gap-1">
          <i class="fa-solid fa-angle-left text-[10px]"></i>
          <span>Back to folios</span>
        </a>
        <span class="w-1 h-px bg-slate-300"></span>
        <span>Folio ID #<?= $id ?></span>
      </div>
      <h1 class="text-2xl font-extrabold tracking-tight flex items-center gap-2">
        <?= $h($code) ?>
        <span class="inline-flex items-center px-2 py-0.5 rounded-full border text-[11px] font-medium <?= $badgeClass ?>">
          <?= $h($label($status)) ?>
        </span>
      </h1>
      <p class="text-slate-500 text-sm mt-1">
        Guest folio with all room charges, extras and payments organised for audit-friendly 2035 workflows.
      </p>
    </div>

    <div class="flex flex-wrap gap-2 text-sm">
      <?php if ($resCode !== ''): ?>
        <a href="<?= $h($base) ?>/reservations"
           class="inline-flex items-center gap-2 px-3 py-1.5 rounded-lg border border-slate-300 bg-white hover:bg-slate-50">
          <i class="fa-regular fa-calendar-check"></i>
          <span>Reservation <?= $h($resCode) ?></span>
        </a>
      <?php endif; ?>
      <a href="<?= $h($base) ?>/payments/receive?folio_id=<?= $id ?>"
         class="inline-flex items-center gap-2 px-3 py-1.5 rounded-lg border border-emerald-300 bg-emerald-50 hover:bg-emerald-100 text-emerald-800">
        <i class="fa-solid fa-bangladeshi-taka-sign"></i>
        <span>Receive payment</span>
      </a>
    </div>
  </div>

  <!-- 2-column layout -->
  <div class="grid gap-6 lg:grid-cols-[2.1fr,1.4fr]">

    <!-- LEFT: Guest + line items -->
    <div class="space-y-4">

      <!-- Guest & stay info -->
      <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
          <div>
            <h2 class="text-sm font-semibold text-slate-900">Guest &amp; stay</h2>
            <p class="text-xs text-slate-500 mt-0.5">
              Quick snapshot of who this folio belongs to and which stay it’s linked with.
            </p>
          </div>
          <div class="text-right text-xs text-slate-500">
            <div>Opened: <span class="font-medium text-slate-700"><?= $h($openedAt ?: '—') ?></span></div>
            <?php if ($closedAt): ?>
              <div>Closed: <span class="font-medium text-slate-700"><?= $h($closedAt) ?></span></div>
            <?php endif; ?>
          </div>
        </div>

        <div class="mt-3 grid gap-3 sm:grid-cols-3 text-xs">
          <div class="space-y-1">
            <div class="text-[11px] text-slate-500">Guest</div>
            <div class="font-semibold text-slate-900">
              <?= $guestName !== '' ? $h($guestName) : '—' ?>
            </div>
            <?php if ($guestMobile): ?>
              <div class="text-[11px] text-slate-500"><?= $h($guestMobile) ?></div>
            <?php endif; ?>
          </div>
          <div class="space-y-1">
            <div class="text-[11px] text-slate-500">Reservation</div>
            <div class="font-semibold text-slate-900">
              <?= $resCode !== '' ? $h($resCode) : '—' ?>
            </div>
            <div class="text-[11px] text-slate-500">
              <?= $checkIn !== '' ? $h($checkIn) : '—' ?> → <?= $checkOut !== '' ? $h($checkOut) : '—' ?>
            </div>
          </div>
          <div class="space-y-1">
            <div class="text-[11px] text-slate-500">Currency</div>
            <div class="font-semibold text-slate-900"><?= $h($currency) ?></div>
            <div class="text-[11px] text-slate-500">
              Property default; multi-currency support can come later.
            </div>
          </div>
        </div>
      </div>

      <!-- Line items -->
      <div class="rounded-2xl border border-slate-200 bg-white shadow-sm overflow-hidden">
        <div class="flex items-center justify-between px-4 py-3 border-b border-slate-100">
          <div>
            <h2 class="text-sm font-semibold text-slate-900">Charges &amp; line items</h2>
            <p class="text-xs text-slate-500">Room rate, extras, discounts — everything that builds this folio.</p>
          </div>
          <button type="button"
                  class="inline-flex items-center gap-1 px-3 py-1.5 rounded-lg border border-slate-300 text-[11px] hover:bg-slate-50">
            <i class="fa-solid fa-plus"></i>
            <span>Post charge</span>
          </button>
        </div>

        <div class="overflow-x-auto">
          <table class="min-w-full text-sm">
            <thead class="bg-slate-50 text-xs text-slate-500 uppercase tracking-wide">
              <tr>
                <th class="px-3 py-2 text-left">Date</th>
                <th class="px-3 py-2 text-left">Description</th>
                <th class="px-3 py-2 text-left">Type</th>
                <th class="px-3 py-2 text-right">Amount (<?= $h($currency) ?>)</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
              <?php if (!$lines): ?>
                <tr>
                  <td colspan="4" class="px-3 py-6 text-center text-slate-400 text-sm">
                    No line items yet. Once we wire room charges and extras, they will appear here automatically.
                  </td>
                </tr>
              <?php else: ?>
                <?php foreach ($lines as $ln): ?>
                  <?php
                    $lineDate = (string)($ln['line_date'] ?? $ln['date'] ?? '');
                    $desc     = (string)($ln['description'] ?? '');
                    $type     = strtolower((string)($ln['line_type'] ?? $ln['type'] ?? 'charge'));
                    $amount   = (float)($ln['amount'] ?? 0);
                    $typeLabel = match ($type) {
                        'charge', 'debit'      => 'Charge',
                        'payment', 'credit'    => 'Credit',
                        'adjustment'           => 'Adjustment',
                        default                => ucfirst($type),
                    };
                  ?>
                  <tr class="hover:bg-slate-50/70">
                    <td class="px-3 py-2 text-xs text-slate-600 whitespace-nowrap">
                      <?= $h($lineDate ?: '—') ?>
                    </td>
                    <td class="px-3 py-2 text-xs text-slate-800">
                      <?= $h($desc ?: '—') ?>
                    </td>
                    <td class="px-3 py-2 text-xs text-slate-600 whitespace-nowrap">
                      <?= $h($typeLabel) ?>
                    </td>
                    <td class="px-3 py-2 text-xs text-right whitespace-nowrap">
                      ৳<?= number_format($amount, 2) ?>
                    </td>
                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- RIGHT: Summary + payments + timeline -->
    <div class="space-y-4">

      <!-- Summary card -->
      <div class="rounded-2xl border border-emerald-200 bg-emerald-50/70 p-4 shadow-sm">
        <h2 class="text-sm font-semibold text-emerald-900">Folio summary</h2>
        <p class="text-xs text-emerald-900/80 mt-0.5">
          3-line snapshot for frontdesk: charges, payments and current balance.
        </p>

        <dl class="mt-3 space-y-1 text-xs text-emerald-900/90">
          <div class="flex items-center justify-between">
            <dt>Total charges</dt>
            <dd class="font-semibold">৳<?= number_format($totalCharges, 2) ?></dd>
          </div>
          <div class="flex items-center justify-between">
            <dt>Payments</dt>
            <dd class="font-semibold">৳<?= number_format($totalPayments, 2) ?></dd>
          </div>
          <div class="flex items-center justify-between border-t border-emerald-200 mt-1 pt-2">
            <dt>Balance due</dt>
            <dd class="font-extrabold text-emerald-950">
              ৳<?= number_format($balance, 2) ?>
            </dd>
          </div>
        </dl>

        <div class="mt-3 flex flex-wrap gap-2 text-[11px]">
          <button type="button"
                  class="inline-flex items-center gap-1 px-3 py-1.5 rounded-lg bg-emerald-600 text-white font-semibold hover:bg-emerald-700">
            <i class="fa-solid fa-bangladeshi-taka-sign"></i>
            <span>Receive payment</span>
          </button>
          <button type="button"
                  class="inline-flex items-center gap-1 px-3 py-1.5 rounded-lg border border-emerald-300 bg-emerald-50 text-emerald-800 hover:bg-emerald-100">
            <i class="fa-solid fa-file-invoice"></i>
            <span>Print / export</span>
          </button>
        </div>
      </div>

      <!-- Payments history -->
      <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
        <div class="flex items-center justify-between mb-2">
          <div>
            <h2 class="text-sm font-semibold text-slate-900">Payments</h2>
            <p class="text-xs text-slate-500">
              All payments recorded against this folio (cash, card, transfer etc.).
            </p>
          </div>
          <a href="<?= $h($base) ?>/payments?folio_id=<?= $id ?>"
             class="inline-flex items-center gap-1 px-3 py-1.5 rounded-lg border border-slate-300 text-[11px] hover:bg-slate-50">
            <i class="fa-solid fa-up-right-from-square"></i>
            <span>Open payments page</span>
          </a>
        </div>

        <div class="space-y-2 text-xs">
          <?php if (!$payments): ?>
            <div class="text-slate-400 text-sm py-2">
              No payments recorded yet for this folio.
            </div>
          <?php else: ?>
            <?php foreach ($payments as $p): ?>
              <?php
                $dt   = (string)($p['paid_at'] ?? $p['created_at'] ?? '');
                $amt  = (float)($p['amount'] ?? 0);
                $meth = (string)($p['method'] ?? '');
                $ref  = (string)($p['reference'] ?? '');
              ?>
              <div class="flex items-center justify-between rounded-xl border border-slate-200 bg-slate-50 px-3 py-1.5">
                <div>
                  <div class="font-semibold text-slate-900">
                    ৳<?= number_format($amt, 2) ?>
                  </div>
                  <div class="text-[11px] text-slate-500">
                    <?= $h($meth ?: 'Payment') ?>
                    <?= $ref ? ' • '.$h($ref) : '' ?>
                  </div>
                </div>
                <div class="text-[11px] text-slate-500 text-right">
                  <?= $h($dt ?: '—') ?>
                </div>
              </div>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>
      </div>

      <!-- Timeline / notes -->
      <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4 shadow-sm">
        <h2 class="text-sm font-semibold text-slate-900">Timeline &amp; notes</h2>
        <p class="text-xs text-slate-500 mb-2">
          Later we can log each important folio event (creation, adjustments, payments) for full audit trail.
        </p>
        <ul class="space-y-1 text-[11px] text-slate-700">
          <li>• Folio created when reservation was booked or check-in completed.</li>
          <li>• Room charges auto-posted from rate &amp; nights.</li>
          <li>• Manual extras and discounts appear as separate line items.</li>
          <li>• Payments reduce balance and push data to the accounting layer.</li>
        </ul>
      </div>

      <!-- How to use this folio page -->
      <div class="rounded-2xl border border-slate-200 bg-white px-4 py-3 text-[11px] text-slate-700 space-y-1">
        <div class="font-semibold text-slate-900 mb-1">How to use this Folio details page</div>
        <ol class="list-decimal list-inside space-y-1">
          <li>Verify the <strong>guest &amp; stay info</strong> at the top before taking any payment.</li>
          <li>Use the <strong>Charges &amp; line items</strong> table to review what has been billed so far.</li>
          <li>From the <strong>Folio summary</strong> card, always check the balance before closing the folio.</li>
          <li>Use the <strong>Receive payment</strong> buttons to open the payments flow with this folio pre-selected.</li>
          <li>Keep this page open during check-out while the other monitor shows Frontdesk or room status.</li>
        </ol>
        <p class="mt-1">
          Tip: Future step — we can add “Split folio” and “Move charges to another folio” so corporate and personal charges stay separate.
        </p>
      </div>
    </div>
  </div>
</div>