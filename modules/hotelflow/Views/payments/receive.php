<?php
/** @var array  $ctx */
/** @var string $module_base ?? */
/** @var string $today */
/** @var string $nowTime */

$h    = fn($v)=>htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
$base = rtrim((string)($module_base ?? ($ctx['module_base'] ?? '/apps/hotelflow')), '/');
?>
<div class="max-w-5xl mx-auto space-y-6">

  <!-- Header + tiny nav -->
  <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
    <div>
      <h1 class="text-2xl font-extrabold tracking-tight">Receive payment</h1>
      <p class="text-slate-500 text-sm">
        Log a payment against a reservation, with method, reference and date/time.
      </p>
    </div>

    <div class="flex flex-wrap gap-2 text-sm">
      <a href="<?= $h($base) ?>/payments"
         class="inline-flex items-center gap-2 px-3 py-1.5 rounded-lg border border-slate-300 bg-white hover:bg-slate-50">
        <i class="fa-solid fa-money-bill-wave"></i>
        <span>Payments dashboard</span>
      </a>
      <a href="<?= $h($base) ?>/reservations"
         class="inline-flex items-center gap-2 px-3 py-1.5 rounded-lg border border-slate-200 bg-slate-50 hover:bg-slate-100">
        <i class="fa-regular fa-calendar-check"></i>
        <span>Reservations</span>
      </a>
    </div>
  </div>

  <!-- Horizontal layout -->
  <form method="post"
        action="<?= $h($base) ?>/payments/receive"
        class="grid gap-6 lg:grid-cols-[2fr,1.2fr]">

    <!-- LEFT: core payment info -->
    <div class="space-y-5">

      <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
        <div class="flex items-center justify-between mb-4">
          <div>
            <h2 class="text-sm font-semibold text-slate-900 uppercase tracking-wide">Reservation &amp; amount</h2>
            <p class="text-xs text-slate-500 mt-0.5">
              Link the payment to a reservation using either ID or reservation code.
            </p>
          </div>
          <span class="inline-flex items-center rounded-full bg-emerald-50 px-2 py-0.5 text-[11px] font-medium text-emerald-700 border border-emerald-100">
            <i class="fa-solid fa-circle-check mr-1"></i> Frontdesk
          </span>
        </div>

        <div class="space-y-3">
          <div>
            <label class="block text-xs font-medium text-slate-700 mb-1">
              Reservation (ID or code) <span class="text-rose-500">*</span>
            </label>
            <input type="text"
                   name="reservation_lookup"
                   required
                   class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                   placeholder="E.g. 1024 or RES-20251118-1024">
            <p class="mt-1 text-[11px] text-slate-400">
              The system will find the reservation by matching the ID or reservation code.
            </p>
          </div>

          <div class="grid gap-3 sm:grid-cols-2">
            <div>
              <label class="block text-xs font-medium text-slate-700 mb-1">
                Amount (BDT) <span class="text-rose-500">*</span>
              </label>
              <input type="number" step="0.01" min="0.01"
                     name="amount"
                     required
                     class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
            </div>
            <div>
              <label class="block text-xs font-medium text-slate-700 mb-1">Method</label>
              <select name="method"
                      class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                <?php foreach (['Cash','Card','Mobile money','Bank transfer','Other'] as $m): ?>
                  <option value="<?= $h($m) ?>"><?= $h($m) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>

          <div class="grid gap-3 sm:grid-cols-2">
            <div>
              <label class="block text-xs font-medium text-slate-700 mb-1">Payment date</label>
              <input type="date"
                     name="pay_date"
                     value="<?= $h($today) ?>"
                     class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
            </div>
            <div>
              <label class="block text-xs font-medium text-slate-700 mb-1">Time</label>
              <input type="time"
                     name="pay_time"
                     value="<?= $h($nowTime) ?>"
                     class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
            </div>
          </div>

          <div class="grid gap-3 sm:grid-cols-2">
            <div>
              <label class="block text-xs font-medium text-slate-700 mb-1">Status</label>
              <select name="status"
                      class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                <option value="posted">Posted (received)</option>
                <option value="pending">Pending (to verify)</option>
                <option value="refunded">Refunded</option>
              </select>
            </div>
            <div>
              <label class="block text-xs font-medium text-slate-700 mb-1">Reference</label>
              <input type="text"
                     name="reference"
                     class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                     placeholder="Slip #, transaction ID, POS ref…">
            </div>
          </div>

          <div>
            <label class="block text-xs font-medium text-slate-700 mb-1">Internal note (optional)</label>
            <textarea name="note" rows="2"
                      class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                      placeholder="Partial payment, advance, no-show charge, etc."></textarea>
          </div>
        </div>
      </div>

    </div>

    <!-- RIGHT: summary + help -->
    <div class="space-y-4">

      <div class="rounded-2xl border border-emerald-200 bg-emerald-50/70 p-4 shadow-sm">
        <h2 class="text-sm font-semibold text-emerald-900 uppercase tracking-wide">Payment summary</h2>
        <p class="text-xs text-emerald-900/80 mt-1">
          This will create a payment entry against the selected reservation in BDT.
        </p>
        <ul class="mt-3 space-y-1 text-xs text-emerald-900/90">
          <li class="flex items-start gap-2">
            <i class="fa-solid fa-circle-small text-[6px] mt-1"></i>
            <span>Payment is stored in <strong>hms_payments</strong> with method, status and reference.</span>
          </li>
          <li class="flex items-start gap-2">
            <i class="fa-solid fa-circle-small text-[6px] mt-1"></i>
            <span>Reservation and guest stay history can later show all related payments.</span>
          </li>
          <li class="flex items-start gap-2">
            <i class="fa-solid fa-circle-small text-[6px] mt-1"></i>
            <span>You can adjust status if it is a pending transfer or refund.</span>
          </li>
        </ul>

        <div class="mt-4 flex flex-wrap items-center justify-between gap-3">
          <button type="submit"
                  class="inline-flex items-center gap-2 rounded-xl px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:shadow-md transition"
                  style="background:var(--brand,#228B22);">
            <i class="fa-solid fa-arrow-down-short-wide"></i>
            <span>Save payment</span>
          </button>
          <span class="text-[11px] text-emerald-900/70">
            After saving, you’ll be redirected to the reservation details.
          </span>
        </div>
      </div>

      <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-[11px] text-slate-700 space-y-1">
        <div class="font-semibold text-slate-900 mb-1">How to use this Receive payment page</div>
        <ol class="list-decimal list-inside space-y-1">
          <li>Type the <strong>reservation ID</strong> or <strong>code</strong> from the frontdesk screen.</li>
          <li>Enter the exact <strong>amount in BDT</strong> and choose the correct <strong>method</strong>.</li>
          <li>Set the <strong>date &amp; time</strong> to match your cash drawer or bank slip.</li>
          <li>Use <strong>reference</strong> and <strong>internal note</strong> for easy reconciliation later.</li>
          <li>Click <strong>Save payment</strong>. The system will link it to the reservation and update reports.</li>
        </ol>
        <p class="mt-1">
          Tip: Later we can auto-suggest the balance due and prevent over-payments in this form.
        </p>
      </div>

    </div>
  </form>
</div>