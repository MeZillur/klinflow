<?php
/** @var array  $payment */
/** @var array  $ctx */
/** @var string $module_base ?? */

$h    = fn($v)=>htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
$base = rtrim((string)($module_base ?? ($ctx['module_base'] ?? '/apps/hotelflow')), '/');

$id        = (int)($payment['id'] ?? 0);
$resId     = (int)($payment['reservation_id'] ?? 0);
$resCode   = (string)($payment['reservation_code'] ?? '');
$guestName = (string)($payment['guest_name'] ?? '');
$roomNo    = (string)($payment['room_no'] ?? '');
$method    = (string)($payment['method'] ?? '');
$status    = (string)($payment['status'] ?? 'posted');
$amount    = (float)($payment['amount'] ?? 0);
$currency  = (string)($payment['currency'] ?? 'BDT');
$ref       = (string)($payment['reference'] ?? '');
$created   = (string)($payment['created_at'] ?? '');
$paidAt    = (string)($payment['paid_at'] ?? '');
$note      = (string)($payment['note'] ?? '');
?>
<div class="max-w-4xl mx-auto space-y-6">

  <!-- Header + nav -->
  <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
    <div>
      <h1 class="text-2xl font-extrabold tracking-tight">Payment #<?= $id ?></h1>
      <p class="text-slate-500 text-sm">
        Details for a single payment record, linked to its reservation and guest.
      </p>
    </div>
    <div class="flex flex-wrap gap-2 text-sm">
      <a href="<?= $h($base) ?>/payments"
         class="inline-flex items-center gap-2 px-3 py-1.5 rounded-lg border border-slate-300 bg-white hover:bg-slate-50">
        <i class="fa-solid fa-money-bill-wave"></i>
        <span>Payments</span>
      </a>
      <?php if ($resId): ?>
        <a href="<?= $h($base) ?>/reservations/<?= $resId ?>"
           class="inline-flex items-center gap-2 px-3 py-1.5 rounded-lg border border-slate-200 bg-slate-50 hover:bg-slate-100">
          <i class="fa-regular fa-calendar-check"></i>
          <span>Reservation</span>
        </a>
      <?php endif; ?>
    </div>
  </div>

  <!-- Card -->
  <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm space-y-4">
    <div class="flex flex-col sm:flex-row sm:justify-between sm:items-start gap-3">
      <div>
        <div class="text-xs font-medium text-slate-500 uppercase tracking-wide">Amount</div>
        <div class="text-2xl font-extrabold text-slate-900">
          <?= number_format($amount,2) ?> <span class="text-base font-semibold"><?= $h($currency) ?></span>
        </div>
        <div class="mt-1 text-xs text-slate-500">
          Method: <span class="font-semibold text-slate-800"><?= $h($method ?: '—') ?></span>
        </div>
      </div>

      <div class="space-y-2">
        <div class="text-xs font-medium text-slate-500 uppercase tracking-wide">Status</div>
        <?php
          $badge = 'bg-slate-50 text-slate-700 border-slate-200';
          if ($status === 'posted')  $badge = 'bg-emerald-50 text-emerald-700 border-emerald-200';
          if ($status === 'pending') $badge = 'bg-amber-50 text-amber-800 border-amber-200';
          if ($status === 'refunded')$badge = 'bg-rose-50 text-rose-700 border-rose-200';
        ?>
        <span class="inline-flex items-center px-3 py-1 rounded-full border text-xs font-semibold <?= $badge ?>">
          <?= $h(ucfirst($status)) ?>
        </span>
        <?php if ($ref): ?>
          <div class="text-xs text-slate-500">
            Reference: <span class="font-medium text-slate-800"><?= $h($ref) ?></span>
          </div>
        <?php endif; ?>
      </div>
    </div>

    <div class="grid gap-4 sm:grid-cols-2 text-xs text-slate-700">
      <div class="space-y-1">
        <div class="font-semibold text-slate-900">Reservation</div>
        <?php if ($resId): ?>
          <div>
            <span class="text-slate-500">Code: </span>
            <a href="<?= $h($base) ?>/reservations/<?= $resId ?>"
               class="text-emerald-700 font-semibold hover:underline">
              <?= $h($resCode ?: '#'.$resId) ?>
            </a>
          </div>
        <?php else: ?>
          <div class="text-slate-400">Not linked to a reservation.</div>
        <?php endif; ?>
        <?php if ($roomNo): ?>
          <div>Room: <span class="font-medium text-slate-800"><?= $h($roomNo) ?></span></div>
        <?php endif; ?>
        <?php if ($guestName): ?>
          <div>Guest: <span class="font-medium text-slate-800"><?= $h($guestName) ?></span></div>
        <?php endif; ?>
      </div>

      <div class="space-y-1">
        <div class="font-semibold text-slate-900">Timing</div>
        <?php if ($paidAt): ?>
          <div>Paid at: <span class="font-medium text-slate-800"><?= $h($paidAt) ?></span></div>
        <?php endif; ?>
        <?php if ($created): ?>
          <div>Recorded: <span class="font-medium text-slate-800"><?= $h($created) ?></span></div>
        <?php endif; ?>
      </div>
    </div>

    <?php if ($note): ?>
      <div class="pt-3 border-t border-slate-100 text-xs text-slate-700">
        <div class="font-semibold text-slate-900 mb-1">Internal note</div>
        <p class="whitespace-pre-line"><?= $h($note) ?></p>
      </div>
    <?php endif; ?>
  </div>

  <!-- How to use this page -->
  <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-xs text-slate-700 space-y-1">
    <div class="font-semibold text-slate-900 mb-1">How to use this Payment details page</div>
    <ol class="list-decimal list-inside space-y-1">
      <li>Use this view when you need to <strong>verify a specific payment</strong> from audit or accounts team.</li>
      <li>Check the <strong>method, reference and timing</strong> to match POS slips or bank statements.</li>
      <li>Follow the <strong>Reservation</strong> link to see full stay history, folio and other charges.</li>
      <li>Use internal notes to capture any special context for this payment (e.g. partial, adjustment, refund).</li>
    </ol>
    <p class="mt-1">
      Tip: Later we can add role-based controls here to adjust or reverse payments with proper approval.
    </p>
  </div>
</div>