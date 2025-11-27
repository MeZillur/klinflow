<?php
/**
 * BizFlow — Award details
 *
 * Expects:
 * - array  $org
 * - string $module_base
 * - array  $award
 * - array  $lines
 * - ?int   $purchase_id  (optional, from controller)
 */

$h = fn($s)=>htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');

$module_base = rtrim((string)($module_base ?? '/apps/bizflow'), '/');
$org         = $org ?? [];
$award       = $award ?? [];
$lines       = $lines ?? [];
$purchaseId  = isset($purchase_id) ? (int)$purchase_id : null;

$orgName  = trim((string)($org['name'] ?? ''));

$id          = (int)($award['id'] ?? 0);
$awardNo     = trim((string)($award['award_no'] ?? ''));
$status      = trim((string)($award['status'] ?? 'draft'));
$awardDate   = (string)($award['award_date'] ?? '');
$currency    = (string)($award['currency'] ?? 'BDT');

$subtotal    = (float)($award['subtotal'] ?? 0);
$discountTot = (float)($award['discount_total'] ?? 0);
$taxTot      = (float)($award['tax_total'] ?? 0);
$shipTot     = (float)($award['shipping_total'] ?? 0);
$grandTot    = (float)($award['grand_total'] ?? 0);

$customerName    = trim((string)($award['customer_name'] ?? ''));
$customerContact = trim((string)($award['customer_contact'] ?? ''));
$customerRef     = trim((string)($award['customer_ref'] ?? ''));

$quoteNo    = trim((string)($award['quote_no'] ?? ''));
$externalRef= trim((string)($award['external_ref'] ?? ''));

$backUrl    = $module_base . '/awards';
$brand      = '#228B22';

// Status pill
$statusLabel = ucfirst($status);
$statusClass = 'bg-gray-100 text-gray-700 border-gray-300';
if ($status === 'approved')   $statusClass = 'bg-emerald-50 text-emerald-700 border-emerald-200';
elseif ($status === 'draft')  $statusClass = 'bg-sky-50 text-sky-700 border-sky-200';
elseif ($status === 'cancelled') $statusClass = 'bg-rose-50 text-rose-700 border-rose-200';

?>
<div class="space-y-6">

  <!-- HEADER -->
  <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
    <div>
      <div class="inline-flex items-center gap-2 text-xs font-semibold tracking-wide text-emerald-700 bg-emerald-50 border border-emerald-100 px-3 py-1 uppercase">
        <i class="fa-regular fa-circle-check"></i>
        <span>Award details</span>
      </div>
      <h1 class="mt-3 text-2xl md:text-3xl font-semibold tracking-tight">
        <?= $h($awardNo ?: ('Award #'.$id)) ?><?= $orgName ? ' — '.$h($orgName) : '' ?>
      </h1>
      <p class="mt-2 text-sm text-gray-600 dark:text-gray-300 max-w-2xl">
        This page represents a confirmed award from your customer. From here you can
        generate a purchase order to your supplier and later continue to delivery and invoice.
      </p>
    </div>

    <div class="flex flex-wrap items-center gap-2 justify-end">
      <a href="<?= $h($backUrl) ?>"
         class="inline-flex items-center gap-1 px-3 py-2 text-xs border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-200 bg-white dark:bg-gray-900 hover:bg-gray-50 dark:hover:bg-gray-800">
        <i class="fa-solid fa-chevron-left text-[11px]"></i>
        <span>Back to awards</span>
      </a>

      <?php if ($purchaseId): ?>
        <!-- PO already exists -->
        <button type="button"
                class="inline-flex items-center gap-2 px-4 py-2 text-xs md:text-sm font-semibold text-white shadow-sm hover:shadow-md rounded-md"
                style="background:<?= $h($brand) ?>;"
                disabled>
          <i class="fa-solid fa-file-invoice-dollar text-[13px]"></i>
          <span>Purchase order created</span>
        </button>
      <?php else: ?>
        <!-- Create PO from award -->
       <form method="post"
      action="<?= $h($module_base) ?>/awards/<?= (int)$award['id'] ?>/purchase"
      class="inline">
  <button type="submit"
          class="inline-flex items-center gap-1 px-3 py-2 text-xs font-semibold rounded-md text-white"
          style="background:#228B22;">
    <i class="fa-solid fa-file-invoice text-[11px]"></i>
    <span>Create purchase order</span>
  </button>
</form>
        </form>
      <?php endif; ?>
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

        <dt class="text-gray-500 text-xs uppercase tracking-wide mt-1">Contact</dt>
        <dd class="mt-1">
          <?= $customerContact !== '' ? $h($customerContact) : '—' ?>
        </dd>

        <dt class="text-gray-500 text-xs uppercase tracking-wide mt-1">Customer ref</dt>
        <dd class="mt-1">
          <?= $customerRef !== '' ? $h($customerRef) : '—' ?>
        </dd>

        <dt class="text-gray-500 text-xs uppercase tracking-wide mt-1">Quote no</dt>
        <dd class="mt-1">
          <?= $quoteNo !== '' ? $h($quoteNo) : '—' ?>
        </dd>

        <dt class="text-gray-500 text-xs uppercase tracking-wide mt-1">External ref</dt>
        <dd class="mt-1">
          <?= $externalRef !== '' ? $h($externalRef) : '—' ?>
        </dd>
      </dl>
    </div>

    <!-- Status & dates -->
    <div class="border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 p-4 space-y-2">
      <h2 class="text-sm font-semibold mb-1">Status &amp; dates</h2>
      <div class="flex flex-wrap gap-2 text-[11px] mb-2">
        <span class="inline-flex items-center px-2 py-0.5 border rounded-full <?= $h($statusClass) ?>">
          <?= $h($statusLabel) ?>
        </span>
      </div>
      <dl class="grid grid-cols-[120px_minmax(0,1fr)] gap-y-1 text-sm">
        <dt class="text-gray-500 text-xs uppercase tracking-wide">Award date</dt>
        <dd><?= $awardDate ? $h($awardDate) : '—' ?></dd>

        <dt class="text-gray-500 text-xs uppercase tracking-wide mt-1">Currency</dt>
        <dd class="mt-1"><?= $h($currency ?: 'BDT') ?></dd>
      </dl>
    </div>
  </section>

  <!-- LINES -->
  <section class="border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900">
    <div class="px-4 py-3 border-b border-gray-100 dark:border-gray-800 flex items-center justify-between">
      <div class="flex items-center gap-2 text-sm font-semibold">
        <i class="fa-solid fa-list"></i>
        <span>Awarded lines</span>
      </div>
    </div>

    <?php if (!$lines): ?>
      <div class="px-4 py-4 text-sm text-gray-500">
        No line items have been stored for this award.
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
          </tr>
          </thead>
          <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
          <?php foreach ($lines as $i => $ln): ?>
            <?php
            $lineNo      = (int)($ln['line_no'] ?? ($i + 1));
            $rawName     = trim((string)($ln['name'] ?? ''));
            $productName = trim((string)($ln['product_name'] ?? ''));
            $productCode = trim((string)($ln['product_code'] ?? ''));
            $desc        = trim((string)($ln['description'] ?? ''));
            $qty         = (float)($ln['qty'] ?? 0);
            $unit        = trim((string)($ln['unit'] ?? 'pcs'));
            $price       = (float)($ln['unit_price'] ?? 0);
            $discPct     = (float)($ln['discount_pct'] ?? 0);
            $lineTotal   = (float)($ln['line_total'] ?? 0);

            $displayName = $productName !== ''
                ? $productName
                : ($rawName !== '' ? $rawName : ('Line '.$lineNo));
            ?>
            <tr>
              <td class="px-3 py-2"><?= $lineNo ?></td>
              <td class="px-3 py-2">
                <div class="text-xs font-medium"><?= $h($displayName) ?></div>
                <?php if ($productCode !== ''): ?>
                  <div class="text-[11px] text-gray-500">Code: <?= $h($productCode) ?></div>
                <?php endif; ?>
              </td>
              <td class="px-3 py-2">
                <div class="text-[11px] text-gray-700 dark:text-gray-200 whitespace-pre-line">
                  <?= $h($desc ?: '—') ?>
                </div>
              </td>
              <td class="px-3 py-2 text-right"><?= number_format($qty, 3) ?></td>
              <td class="px-3 py-2"><?= $h($unit) ?></td>
              <td class="px-3 py-2 text-right"><?= number_format($price, 2, '.', ',') ?></td>
              <td class="px-3 py-2 text-right"><?= number_format($discPct, 2) ?></td>
              <td class="px-3 py-2 text-right font-semibold"><?= number_format($lineTotal, 2, '.', ',') ?></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>

    <!-- Totals -->
    <div class="px-4 py-3 border-t border-gray-100 dark:border-gray-800 flex flex-col md:flex-row md:items-center md:justify-between gap-3">
      <div class="text-[11px] text-gray-500 max-w-md">
        These totals will be mirrored into the purchase order so your buy-side and sell-side flows stay consistent.
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

  <!-- HOW TO USE THIS PAGE -->
  <section class="border border-dashed border-gray-300 dark:border-gray-700 bg-gray-50/70 dark:bg-gray-900/40 px-4 py-3 text-xs md:text-sm text-gray-700 dark:text-gray-200 space-y-1.5">
    <h2 class="font-semibold text-sm mb-1">How to use this page</h2>
    <ul class="list-disc list-inside space-y-0.5">
      <li>Review the awarded customer, reference and totals to confirm what has been committed.</li>
      <li>Use the <strong>Create purchase order</strong> button to generate a buy-side PO for your suppliers.</li>
      <li>After the PO is created, you’ll continue the flow into delivery challans and finally sales invoices.</li>
      <li>This keeps your RFQ / tender side (customer) and purchase / import side (supplier) tightly linked.</li>
    </ul>
  </section>
</div>