<?php
/**
 * BizFlow — Quote details
 *
 * Expects:
 * - array  $org
 * - string $module_base
 * - array  $quote
 * - array  $lines
 */

$h = fn($s)=>htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');

$module_base = rtrim((string)($module_base ?? '/apps/bizflow'), '/');
$org         = $org ?? [];
$quote       = $quote ?? [];
$lines       = $lines ?? [];

$orgName     = trim((string)($org['name'] ?? ''));
$brand       = '#228B22';

$id          = (int)($quote['id'] ?? 0);
$quoteNo     = trim((string)($quote['quote_no'] ?? ''));
$status      = trim((string)($quote['status'] ?? 'draft'));
$quoteType   = trim((string)($quote['quote_type'] ?? 'standard'));
$date        = (string)($quote['date'] ?? '');
$validUntil  = (string)($quote['valid_until'] ?? '');
$currency    = (string)($quote['currency'] ?? 'BDT');

$subtotal    = (float)($quote['subtotal'] ?? 0);
$discountTot = (float)($quote['discount_total'] ?? 0);
$taxTot      = (float)($quote['tax_total'] ?? 0);
$vatTot      = (float)($quote['vat_total'] ?? 0);
$shipTot     = (float)($quote['shipping_total'] ?? 0);
$grandTot    = (float)($quote['grand_total'] ?? 0);

$customerName = trim((string)($quote['customer_name'] ?? ''));
$externalRef  = trim((string)($quote['external_ref'] ?? ''));

$meta = [];
if (!empty($quote['meta_json'])) {
    $decoded = json_decode((string)$quote['meta_json'], true);
    if (is_array($decoded)) {
        $meta = $decoded;
    }
}
$uiType        = (string)($meta['ui_quote_type'] ?? ($meta['ui_type'] ?? 'mixed'));
$vatPercent    = (float)($meta['vat_percent'] ?? 0.0);
$custContact   = trim((string)($meta['customer_contact'] ?? ''));
$custRef       = trim((string)($meta['customer_reference'] ?? ''));
$paymentTerms  = trim((string)($meta['payment_terms'] ?? ''));
$deliveryTerms = trim((string)($meta['delivery_terms'] ?? ''));

$backUrl   = $module_base . '/quotes';
$printUrl  = $module_base . '/quotes/' . $id . '/print';
$emailUrl  = $module_base . '/quotes/' . $id . '/email';
$pdfUrl    = $module_base . '/quotes/' . $id . '/pdf';

// status pill
$statusLabel = ucfirst($status);
$statusClass = 'bg-gray-100 text-gray-700 border-gray-300';
if ($status === 'sent')           $statusClass = 'bg-sky-50 text-sky-700 border-sky-200';
elseif ($status === 'accepted')   $statusClass = 'bg-emerald-50 text-emerald-700 border-emerald-200';
elseif ($status === 'rejected')   $statusClass = 'bg-rose-50 text-rose-700 border-rose-200';
elseif ($status === 'expired')    $statusClass = 'bg-amber-50 text-amber-700 border-amber-200';

// ui type pill
$typeLabel = ucfirst($uiType);
$typeClass = 'bg-gray-50 text-gray-600 border-gray-200';
if ($uiType === 'stock')   { $typeLabel = 'Stock only';   $typeClass = 'bg-emerald-50 text-emerald-700 border-emerald-200'; }
if ($uiType === 'service') { $typeLabel = 'Service only'; $typeClass = 'bg-sky-50 text-sky-700 border-sky-200'; }

// Allow award only when not already final
$canCreateAward = !in_array(strtolower($status), ['approved','accepted','rejected'], true);
?>
<div class="space-y-6">

  <!-- HEADER -->
  <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
    <div>
      <div class="inline-flex items-center gap-2 text-xs font-semibold tracking-wide text-emerald-700 bg-emerald-50 border border-emerald-100 px-3 py-1 uppercase">
        <i class="fa-regular fa-file-lines"></i>
        <span>Quote details</span>
      </div>
      <h1 class="mt-3 text-2xl md:text-3xl font-semibold tracking-tight">
        <?= $h($quoteNo ?: ('Quote #'.$id)) ?><?= $orgName ? ' — '.$h($orgName) : '' ?>
      </h1>
      <p class="mt-2 text-sm text-gray-600 dark:text-gray-300 max-w-2xl">
        This page shows a single quotation. From here you can generate awards, share a branded PDF, and follow up with your customer.
      </p>
    </div>

    <div class="flex flex-wrap items-center gap-2 justify-end">
      <!-- Back (neutral) -->
      <a href="<?= $h($backUrl) ?>"
         class="inline-flex items-center gap-1 px-3 py-2 text-xs border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-200 bg-white dark:bg-gray-900 hover:bg-gray-50 dark:hover:bg-gray-800">
        <i class="fa-solid fa-chevron-left text-[11px]"></i>
        <span>Back to quotes</span>
      </a>

      <!-- Create award (brand) -->
      <?php if ($canCreateAward): ?>
        <form method="post"
              action="<?= $h($module_base . '/quotes/' . $id . '/award') ?>"
              class="inline-flex">
          <button type="submit"
                  class="inline-flex items-center gap-1 px-3 py-2 text-xs font-semibold text-white shadow-sm hover:shadow-md"
                  style="background:<?= $h($brand) ?>; border:1px solid <?= $h($brand) ?>;">
            <i class="fa-regular fa-circle-check text-[13px]"></i>
            <span>Create award</span>
          </button>
        </form>
      <?php endif; ?>

      <!-- Download PDF (brand) -->
      <a href="<?= $h($pdfUrl) ?>" download
         class="inline-flex items-center gap-2 px-3 py-2 text-xs font-semibold text-white shadow-sm hover:shadow-md"
         style="background:<?= $h($brand) ?>; border:1px solid <?= $h($brand) ?>;">
        <i class="fa-regular fa-file-pdf text-[13px]"></i>
        <span>Download PDF</span>
      </a>

      <!-- Print / PDF (brand) -->
      <a href="<?= $h($printUrl) ?>"
         class="inline-flex items-center gap-2 px-3 py-2 text-xs font-semibold text-white shadow-sm hover:shadow-md"
         style="background:<?= $h($brand) ?>; border:1px solid <?= $h($brand) ?>;">
        <i class="fa-regular fa-file-pdf text-[13px]"></i>
        <span>Print / PDF</span>
      </a>

      <!-- Email (preview, brand) -->
      <button type="button"
              data-quote-email="<?= $h($emailUrl) ?>"
              class="inline-flex items-center gap-2 px-3 py-2 text-xs font-semibold text-white shadow-sm hover:shadow-md"
              style="background:<?= $h($brand) ?>; border:1px solid <?= $h($brand) ?>;">
        <i class="fa-regular fa-envelope text-[13px]"></i>
        <span>Email (preview)</span>
      </button>
    </div>
  </div>

  <!-- TOP SUMMARY -->
  <section class="grid grid-cols-1 lg:grid-cols-[minmax(0,2fr)_minmax(0,1.4fr)] gap-4">
    <!-- Customer & reference -->
    <div class="border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 p-4 space-y-2">
      <h2 class="text-sm font-semibold mb-1">Customer &amp; reference</h2>
      <dl class="grid grid-cols-[120px_minmax(0,1fr)] gap-y-1 text-sm">
        <dt class="text-gray-500 text-xs uppercase tracking-wide">Customer</dt>
        <dd class="font-medium"><?= $h($customerName ?: '—') ?></dd>

        <dt class="text-gray-500 text-xs uppercase tracking-wide mt-1">External ref</dt>
        <dd class="mt-1"><?= $externalRef !== '' ? $h($externalRef) : '—' ?></dd>

        <dt class="text-gray-500 text-xs uppercase tracking-wide mt-1">Quote no</dt>
        <dd class="mt-1"><?= $h($quoteNo) ?></dd>

        <?php if ($custContact !== '' || $custRef !== ''): ?>
          <dt class="text-gray-500 text-xs uppercase tracking-wide mt-1">Contact</dt>
          <dd class="mt-1 text-sm">
            <?php if ($custContact !== ''): ?>
              <div><?= $h($custContact) ?></div>
            <?php endif; ?>
            <?php if ($custRef !== ''): ?>
              <div class="text-[12px] text-gray-500">Ref: <?= $h($custRef) ?></div>
            <?php endif; ?>
          </dd>
        <?php endif; ?>
      </dl>
    </div>

    <!-- Status & dates -->
    <div class="border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 p-4 space-y-2">
      <h2 class="text-sm font-semibold mb-1">Status &amp; dates</h2>
      <div class="flex flex-wrap gap-2 text-[11px] mb-2">
        <span class="inline-flex items-center px-2 py-0.5 border rounded-full <?= $h($statusClass) ?>">
          <?= $h($statusLabel) ?>
        </span>
        <span class="inline-flex items-center px-2 py-0.5 border rounded-full <?= $h($typeClass) ?>">
          <?= $h($typeLabel) ?>
        </span>
      </div>
      <dl class="grid grid-cols-[120px_minmax(0,1fr)] gap-y-1 text-sm">
        <dt class="text-gray-500 text-xs uppercase tracking-wide">Quote date</dt>
        <dd><?= $date ? $h($date) : '—' ?></dd>

        <dt class="text-gray-500 text-xs uppercase tracking-wide mt-1">Valid until</dt>
        <dd class="mt-1"><?= $validUntil ? $h($validUntil) : '—' ?></dd>

        <dt class="text-gray-500 text-xs uppercase tracking-wide mt-1">Currency</dt>
        <dd class="mt-1"><?= $h($currency ?: 'BDT') ?></dd>

        <?php if ($vatPercent > 0): ?>
          <dt class="text-gray-500 text-xs uppercase tracking-wide mt-1">VAT %</dt>
          <dd class="mt-1"><?= number_format($vatPercent, 2) ?>%</dd>
        <?php endif; ?>
      </dl>
    </div>
  </section>

  <!-- QUOTE LINES -->
  <section class="border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900">
    <div class="px-4 py-3 border-b border-gray-100 dark:border-gray-800 flex items-center justify-between">
      <div class="flex items-center gap-2 text-sm font-semibold">
        <i class="fa-solid fa-list"></i>
        <span>Quote lines</span>
      </div>
    </div>

    <?php if (!$lines): ?>
      <div class="px-4 py-4 text-sm text-gray-500">
        No line items have been stored yet for this quote.
        Header totals above come from what was submitted on the create screen.
      </div>
    <?php else: ?>
      <div class="overflow-x-auto">
        <table class="min-w-full text-xs">
          <thead class="bg-gray-50 dark:bg-gray-800/70 text-[11px] uppercase tracking-wide text-gray-500">
          <tr>
            <th class="px-3 py-2 text-left w-10">#</th>
            <th class="px-3 py-2 text-left min-w-[200px]">Item</th>
            <th class="px-3 py-2 text-left min-w-[220px]">Key features / specification</th>
            <th class="px-3 py-2 text-right w-16">Qty</th>
            <th class="px-3 py-2 text-left w-20">Unit</th>
            <th class="px-3 py-2 text-right w-24">Unit price</th>
            <th class="px-3 py-2 text-right w-20">Disc %</th>
            <th class="px-3 py-2 text-right w-28">Line total</th>
            <th class="px-3 py-2 text-left w-20">Type</th>
          </tr>
          </thead>
          <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
          <?php foreach ($lines as $i => $ln): ?>
            <?php
            $lnKind       = (string)($ln['kind'] ?? 'item');
            $rawName      = trim((string)($ln['name'] ?? ''));
            $productName  = trim((string)($ln['product_name'] ?? ''));
            $productCode  = trim((string)($ln['product_code'] ?? ''));
            $lnDesc       = trim((string)($ln['description'] ?? ''));
            $lnQty        = (float)($ln['qty'] ?? 0);
            $lnUnit       = trim((string)($ln['unit'] ?? 'pcs'));
            $lnPrice      = (float)($ln['unit_price'] ?? 0);
            $lnDiscPct    = (float)($ln['discount_pct'] ?? 0);
            $lnTotal      = (float)($ln['line_total'] ?? 0);
            $lineNo       = (int)($ln['line_no'] ?? ($i + 1));

            // Prefer product_name → raw name → "Line {no}"
            $displayName = $productName !== ''
                ? $productName
                : ($rawName !== '' ? $rawName : ('Line ' . $lineNo));
            ?>
            <tr class="align-middle">
              <td class="px-3 py-2 text-left">
                <?= $lineNo ?>
              </td>
              <td class="px-3 py-2">
                <div class="text-xs font-medium">
                  <?= $h($displayName) ?>
                </div>
                <?php if ($productCode !== ''): ?>
                  <div class="text-[11px] text-gray-500">
                    Code: <?= $h($productCode) ?>
                  </div>
                <?php endif; ?>
              </td>
              <td class="px-3 py-2">
                <div class="text-[11px] text-gray-700 dark:text-gray-200 whitespace-pre-line">
                  <?= $h($lnDesc ?: '—') ?>
                </div>
              </td>
              <td class="px-3 py-2 text-right">
                <?= number_format($lnQty, 3) ?>
              </td>
              <td class="px-3 py-2">
                <?= $h($lnUnit) ?>
              </td>
              <td class="px-3 py-2 text-right">
                <?= number_format($lnPrice, 2, '.', ',') ?>
              </td>
              <td class="px-3 py-2 text-right">
                <?= number_format($lnDiscPct, 2) ?>
              </td>
              <td class="px-3 py-2 text-right font-semibold">
                <?= number_format($lnTotal, 2, '.', ',') ?>
              </td>
              <td class="px-3 py-2">
                <?php if ($lnKind === 'service'): ?>
                  <span class="inline-flex px-2 py-0.5 rounded-full border text-[11px] border-sky-500 bg-sky-50 text-sky-700">
                    Service
                  </span>
                <?php else: ?>
                  <span class="inline-flex px-2 py-0.5 rounded-full border text-[11px] border-emerald-500 bg-emerald-50 text-emerald-700">
                    Item
                  </span>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>

    <!-- Totals -->
    <div class="px-4 py-3 border-t border-gray-100 dark:border-gray-800 flex flex-col md:flex-row md:items-center md:justify-between gap-3">
      <div class="text-[11px] text-gray-500 max-w-md">
        Totals are expressed in BDT. For now, quotes are kept simple without multi-currency
        breakdown; this can be extended later if needed.
      </div>
      <div class="space-y-1 text-sm w-full max-w-xs">
        <div class="flex justify-between gap-4">
          <span class="text-gray-600 dark:text-gray-300">Subtotal</span>
          <span><?= number_format($subtotal, 2, '.', ',') ?></span>
        </div>
        <div class="flex justify-between gap-4 text-xs text-gray-500">
          <span>Discounts</span>
          <span>- <?= number_format($discountTot, 2, '.', ',') ?></span>
        </div>
        <div class="flex justify-between gap-4 text-xs text-gray-500">
          <span>Tax total</span>
          <span><?= number_format($taxTot, 2, '.', ',') ?></span>
        </div>
        <div class="flex justify-between gap-4 text-xs text-gray-500">
          <span>VAT total</span>
          <span><?= number_format($vatTot, 2, '.', ',') ?></span>
        </div>
        <div class="flex justify-between gap-4 text-xs text-gray-500">
          <span>Shipping</span>
          <span><?= number_format($shipTot, 2, '.', ',') ?></span>
        </div>
        <div class="flex justify-between gap-4 text-sm font-semibold border-t border-gray-200 dark:border-gray-700 pt-1 mt-1">
          <span>Grand total (BDT)</span>
          <span><?= number_format($grandTot, 2, '.', ',') ?></span>
        </div>
      </div>
    </div>
  </section>

  <!-- TERMS & CONDITIONS -->
  <?php if ($paymentTerms !== '' || $deliveryTerms !== ''): ?>
    <section class="border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 p-4 space-y-3">
      <h2 class="text-sm font-semibold">Terms &amp; conditions</h2>
      <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-xs md:text-sm">
        <div>
          <h3 class="font-semibold mb-1">Payment terms</h3>
          <div class="whitespace-pre-line text-gray-700 dark:text-gray-200">
            <?= $h($paymentTerms) ?>
          </div>
        </div>
        <div>
          <h3 class="font-semibold mb-1">Delivery terms</h3>
          <div class="whitespace-pre-line text-gray-700 dark:text-gray-200">
            <?= $h($deliveryTerms) ?>
          </div>
        </div>
      </div>
    </section>
  <?php endif; ?>
</div>

<script>
(function () {
  const btn = document.querySelector('[data-quote-email]');
  if (!btn) return;

  btn.addEventListener('click', async function () {
    const url = this.getAttribute('data-quote-email');
    if (!url) return;

    try {
      this.disabled = true;
      const res = await fetch(url, {
        method: 'POST',
        credentials: 'include',
        headers: { 'Accept': 'application/json' }
      });

      const js = await res.json().catch(() => ({}));

      if (js && js.redirect) {
        window.location.href = js.redirect;
      } else if (res.ok) {
        alert('Email send requested (preview only).');
      } else {
        alert('Failed to send email (HTTP ' + res.status + ').');
      }
    } catch (e) {
      console.error(e);
      alert('Unexpected error while sending email.');
    } finally {
      this.disabled = false;
    }
  });
})();
</script>