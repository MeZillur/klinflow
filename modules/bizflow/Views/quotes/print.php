<?php
/**
 * BizFlow — Quote print (A4)
 *
 * Expects:
 * - array  $org
 * - string $module_base
 * - array  $quote
 * - array  $lines
 * - array  $identity (optional: name, address, phone, email)
 * - array  $logo     (optional: url / data_url)
 */

$h = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');

$module_base = rtrim((string)($module_base ?? '/apps/bizflow'), '/');
$org         = $org   ?? [];
$quote       = $quote ?? [];
$lines       = $lines ?? [];

// ---------------------------------------------------------
// Resolve org id + load identity + logo from filesystem
// (so print works even if controller does not pass them)
// ---------------------------------------------------------
$orgId = (int)($org['id'] ?? 0);

/* ---------- Identity: try $identity, then identity.json ---------- */
$identityArr = [];
if (isset($identity) && is_array($identity)) {
    $identityArr = $identity;
} elseif ($orgId > 0) {
    // modules/bizflow/Assets/settings/org_{id}/identity.json
    $settingsBase = dirname(__DIR__, 2) . '/Assets/settings';
    $idDir        = $settingsBase . '/org_' . $orgId;
    $idFile       = $idDir . '/identity.json';
    if (is_file($idFile)) {
        $raw  = @file_get_contents($idFile);
        $data = json_decode((string)$raw, true);
        if (is_array($data)) {
            $identityArr = $data;
        }
    }
}

/* ---------- Logo resolution ----------
 * 1) If controller passed $logo['data_url'] or ['url'], use that.
 * 2) Otherwise, look for modules/bizflow/Assets/brand/logo/org_{id}/logo.{ext}
 *    and build a data: URL from the file (no /modules HTTP needed).
 * 3) Final fallback → global KlinFlow mark /assets/brand/logo.png
 * -------------------------------------------------------------- */
$logoArr = [];
if (isset($logo) && is_array($logo)) {
    $logoArr = $logo;
}

$logoUrl = '';

// 1) Prefer explicit data_url from controller
if (!empty($logoArr['data_url'])) {
    $logoUrl = (string)$logoArr['data_url'];
} elseif (!empty($logoArr['url'])) {
    // Controller might already have built a usable URL
    $logoUrl = (string)$logoArr['url'];
}

// 2) If still empty and we know org id, read file and embed as data: URL
if ($logoUrl === '' && $orgId > 0) {
    // modules/bizflow/Assets/brand/logo/org_{id}/logo.{ext}
    $logoBaseFs = dirname(__DIR__, 2) . '/Assets/brand/logo';
    $orgKey     = 'org_' . $orgId;
    $candidates = ['png','jpg','jpeg','webp','svg'];

    foreach ($candidates as $ext) {
        $fsPath = $logoBaseFs . '/' . $orgKey . '/logo.' . $ext;
        if (!is_file($fsPath)) {
            continue;
        }

        $raw = @file_get_contents($fsPath);
        if ($raw === false) {
            continue;
        }

        $mime = 'image/png';
        $e    = strtolower($ext);
        if ($e === 'jpg' || $e === 'jpeg') {
            $mime = 'image/jpeg';
        } elseif ($e === 'webp') {
            $mime = 'image/webp';
        } elseif ($e === 'svg') {
            $mime = 'image/svg+xml';
        }

        $logoUrl = 'data:' . $mime . ';base64,' . base64_encode($raw);
        break;
    }
}

// 3) Final fallback → global KlinFlow mark (lives under /public/assets)
if ($logoUrl === '') {
    $logoUrl = '/assets/brand/logo.png';
}

/* ---------- Identity fallbacks (identity.json → org → safe defaults) ---------- */
$orgName = trim((string)($org['name'] ?? ''));

$idName    = trim((string)($identityArr['name']    ?? ($org['name']    ?? '')));
$idAddress = trim((string)($identityArr['address'] ?? ($org['address'] ?? '')));
$idPhone   = trim((string)($identityArr['phone']   ?? ($org['phone']   ?? '')));
$idEmail   = trim((string)($identityArr['email']   ?? ($org['email']   ?? '')));

/* ---------- Quote core fields ---------- */
$id         = (int)($quote['id'] ?? 0);
$quoteNo    = trim((string)($quote['quote_no'] ?? ''));
$status     = trim((string)($quote['status'] ?? 'draft'));
$quoteType  = trim((string)($quote['quote_type'] ?? 'standard'));
$date       = (string)($quote['date'] ?? '');
$validUntil = (string)($quote['valid_until'] ?? '');
$currency   = (string)($quote['currency'] ?? 'BDT');

$subtotal    = (float)($quote['subtotal'] ?? 0);
$discountTot = (float)($quote['discount_total'] ?? 0);
$taxTot      = (float)($quote['tax_total'] ?? 0);
$vatTot      = (float)($quote['vat_total'] ?? 0);
$shipTot     = (float)($quote['shipping_total'] ?? 0);
$grandTot    = (float)($quote['grand_total'] ?? 0);

$customerName = trim((string)($quote['customer_name'] ?? ''));
$externalRef  = trim((string)($quote['external_ref'] ?? ''));

/* ---------- Meta & terms ---------- */
$meta = [];
if (!empty($quote['meta_json'])) {
    $decoded = json_decode((string)$quote['meta_json'], true);
    if (is_array($decoded)) {
        $meta = $decoded;
    }
}
$uiType      = (string)($meta['ui_type'] ?? 'mixed');
$vatPercent  = (float)($meta['vat_percent'] ?? 0.0);
$custContact = trim((string)($meta['customer_contact'] ?? ''));
$custRef     = trim((string)($meta['customer_reference'] ?? ''));
$paymentTs   = trim((string)($meta['payment_terms'] ?? ''));
$deliveryTs  = trim((string)($meta['delivery_terms'] ?? ''));

/* ---------- Line items: prefer DB lines, fallback to meta_json["rows"] ---------- */
$lineItems = is_array($lines) ? $lines : [];

if (!$lineItems && isset($meta['rows']) && is_array($meta['rows'])) {
    foreach ($meta['rows'] as $idx => $row) {
        if (!is_array($row)) {
            continue;
        }
        $lineItems[] = [
            'line_no'       => $row['line_no']      ?? ($idx + 1),
            'kind'          => $row['kind']         ?? 'item',
            // carry product_name / code if meta has them
            'product_name'  => $row['product_name'] ?? ($row['name'] ?? ''),
            'product_code'  => $row['product_code'] ?? ($row['code'] ?? ''),
            'name'          => $row['name']         ?? '',
            'description'   => $row['description']  ?? '',
            'qty'           => $row['qty']          ?? 0,
            'unit'          => $row['unit']         ?? 'pcs',
            'unit_price'    => $row['unit_price']   ?? 0,
            'discount_pct'  => $row['discount_pct'] ?? 0,
            // frontend may use "total" or "line_total"
            'line_total'    => $row['line_total']   ?? ($row['total'] ?? 0),
        ];
    }
}

function qn($v, int $dec = 2): string {
    return number_format((float)$v, $dec, '.', ',');
}
?>

<!-- PRINT-ONLY CSS: hide shell, keep only this card -->
<style>
@media print {
  html, body {
    margin: 0;
    padding: 0;
    background: #ffffff !important;
  }

  /* Hide everything */
  body * {
    visibility: hidden;
  }

  /* Show only our print root */
  #bizflow-print-root,
  #bizflow-print-root * {
    visibility: visible;
  }

  #bizflow-print-root {
    position: absolute;
    inset: 0;
    margin: 0 auto;
    padding: 0;
  }
}
</style>

<div class="bg-gray-100 py-6 print:bg-white print:py-0" id="bizflow-print-root">
  <div class="mx-auto bg-white shadow-md print:shadow-none max-w-3xl border border-gray-200">
    <div class="px-8 py-6">

      <!-- Header: identity + QUOTATION meta -->
      <header class="flex items-start justify-between gap-6 border-b border-gray-200 pb-4 mb-4">
        <div class="flex gap-3">
          <div class="flex items-center justify-center">
            <img src="<?= $h($logoUrl) ?>"
                 alt="Logo"
                 class="h-10 max-w-[120px] object-contain" />
          </div>
          <div class="text-xs text-gray-700 space-y-0.5">
            <div class="font-semibold text-base text-gray-900">
              <?= $h($idName !== '' ? $idName : ($orgName !== '' ? $orgName : 'Organisation')) ?>
            </div>
            <?php if ($idAddress !== ''): ?>
              <div class="whitespace-pre-line leading-snug"><?= $h($idAddress) ?></div>
            <?php endif; ?>
            <div class="flex flex-wrap gap-3 mt-1">
              <?php if ($idPhone !== ''): ?>
                <span><span class="font-semibold">Phone:</span> <?= $h($idPhone) ?></span>
              <?php endif; ?>
              
              <?php if ($idEmail !== ''): ?>
                <span><span class="font-semibold">Email:</span> <?= $h($idEmail) ?></span>
              <?php endif; ?>
            </div>
          </div>
        </div>

        <div class="text-right text-xs text-gray-800">
          <div class="text-lg font-semibold tracking-[0.12em] uppercase">
            Quotation
          </div>
          <!-- ONLY date + valid until here (rest is in the summary box) -->
          <div class="mt-3 space-y-0.5 text-[11px]">
            <div><span class="font-semibold">Quote date:</span> <?= $date ? $h($date) : '—' ?></div>
            <div><span class="font-semibold">Valid until:</span> <?= $validUntil ? $h($validUntil) : '—' ?></div>
          </div>
        </div>
      </header>

      <!-- Customer & summary two-column block -->
      <section class="grid grid-cols-1 md:grid-cols-2 gap-4 text-xs mb-4">
        <div class="border border-gray-200">
          <div class="bg-gray-50 px-3 py-2 border-b border-gray-200 text-[11px] font-semibold tracking-wide uppercase text-gray-600">
            Customer
          </div>
          <div class="px-3 py-2 space-y-0.5">
            <div><span class="font-semibold text-gray-700">Name:</span> <?= $h($customerName ?: '—') ?></div>
            <div><span class="font-semibold text-gray-700">Contact:</span> <?= $h($custContact ?: '—') ?></div>
            <div><span class="font-semibold text-gray-700">Reference:</span> <?= $h(($custRef ?: $externalRef) ?: '—') ?></div>
          </div>
        </div>

        <div class="border border-gray-200">
          <div class="bg-gray-50 px-3 py-2 border-b border-gray-200 text-[11px] font-semibold tracking-wide uppercase text-gray-600">
            Summary
          </div>
          <div class="px-3 py-2 space-y-0.5">
            <div><span class="font-semibold text-gray-700">Quote no:</span> <?= $h($quoteNo ?: '—') ?></div>
            <div><span class="font-semibold text-gray-700">Status:</span> <?= $h(ucfirst($status)) ?> (<?= $h(ucfirst($uiType)) ?>)</div>
            <div><span class="font-semibold text-gray-700">Quote date:</span> <?= $date ? $h($date) : '—' ?></div>
            <div><span class="font-semibold text-gray-700">Valid until:</span> <?= $h($validUntil ?: '—') ?></div>
            <div><span class="font-semibold text-gray-700">Currency:</span> <?= $h($currency ?: 'BDT') ?></div>
          </div>
        </div>
      </section>

      <!-- Quote lines -->
      <section class="border border-gray-200 mb-4">
        <div class="px-3 py-2 border-b border-gray-200 flex items-center justify-between">
          <div class="text-xs font-semibold text-gray-800">Quote lines</div>
        </div>

        <?php if (!$lineItems): ?>
          <div class="px-3 py-3 text-xs text-gray-500">
            No line items have been stored yet for this quote. Header totals below come from what was submitted on the create screen.
          </div>
        <?php else: ?>
          <div class="overflow-x-auto">
            <table class="min-w-full text-[11px]">
              <thead class="bg-gray-50 text-[10px] uppercase tracking-wide text-gray-500 border-b border-gray-200">
              <tr>
                <th class="px-2 py-2 text-left w-6">#</th>
                <th class="px-2 py-2 text-left min-w-[140px]">Item</th>
                <th class="px-2 py-2 text-left min-w-[220px]">Key features / specification</th>
                <th class="px-2 py-2 text-right w-16">Qty</th>
                <th class="px-2 py-2 text-left w-16">Unit</th>
                <th class="px-2 py-2 text-right w-20">Unit price</th>
                <th class="px-2 py-2 text-right w-16">Disc %</th>
                <th class="px-2 py-2 text-right w-24">Line total</th>
              </tr>
              </thead>
              <tbody class="divide-y divide-gray-100">
              <?php foreach ($lineItems as $i => $ln): ?>
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

                // SAME logic as show.php: prefer product_name → raw name → "Line {no}"
                $displayName = $productName !== ''
                    ? $productName
                    : ($rawName !== '' ? $rawName : ('Line ' . $lineNo));
                ?>
                <tr class="align-middle">
                  <td class="px-2 py-1 text-left">
                    <?= $lineNo ?>
                  </td>
                  <td class="px-2 py-1">
                    <div class="font-medium text-[11px] text-gray-900">
                      <?= $h($displayName) ?>
                    </div>
                    <?php if ($productCode !== ''): ?>
                      <div class="text-[10px] text-gray-500">
                        Code: <?= $h($productCode) ?>
                      </div>
                    <?php endif; ?>
                    <?php if ($lnKind === 'service'): ?>
                      <div class="mt-0.5 inline-flex px-1.5 py-0.5 rounded-full border border-sky-400 text-[9px] text-sky-700">
                        Service
                      </div>
                    <?php endif; ?>
                  </td>
                  <td class="px-2 py-1">
                    <div class="text-[10px] text-gray-700 whitespace-pre-line">
                      <?= $h($lnDesc ?: '—') ?>
                    </div>
                  </td>
                  <td class="px-2 py-1 text-right">
                    <?= qn($lnQty, 3) ?>
                  </td>
                  <td class="px-2 py-1">
                    <?= $h($lnUnit) ?>
                  </td>
                  <td class="px-2 py-1 text-right">
                    <?= qn($lnPrice) ?>
                  </td>
                  <td class="px-2 py-1 text-right">
                    <?= qn($lnDiscPct) ?>
                  </td>
                  <td class="px-2 py-1 text-right font-semibold">
                    <?= qn($lnTotal) ?>
                  </td>
                </tr>
              <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>
      </section>

      <!-- Totals -->
      <section class="flex flex-col md:flex-row md:items-start md:justify-between gap-4 mb-4">
        <div class="text-[10px] text-gray-500 max-w-sm">
          Totals are expressed in BDT. For now, quotes are kept simple without multi-currency breakdown; this can be
          extended later if needed.
        </div>
        <div class="text-xs w-full md:w-64 space-y-0.5">
          <div class="flex justify-between">
            <span class="text-gray-600">Subtotal</span>
            <span><?= qn($subtotal) ?></span>
          </div>
          <div class="flex justify-between text-[11px] text-gray-500">
            <span>Discounts</span>
            <span>- <?= qn($discountTot) ?></span>
          </div>

          <div class="flex justify-between text-[11px] text-gray-500">
            <span>VAT total</span>
            <span><?= qn($vatTot) ?></span>
          </div>
          <div class="flex justify-between text-[11px] text-gray-500">
            <span>Shipping</span>
            <span><?= qn($shipTot) ?></span>
          </div>
          <div class="flex justify-between border-t border-gray-200 pt-1 mt-1 text-sm font-semibold">
            <span>Grand total (BDT)</span>
            <span><?= qn($grandTot) ?></span>
          </div>
        </div>
      </section>

      <!-- Terms & conditions -->
      <section class="border border-gray-200 text-xs mb-6">
        <div class="bg-gray-50 px-3 py-2 border-b border-gray-200 text-[11px] font-semibold uppercase tracking-wide text-gray-600">
          Terms &amp; conditions
        </div>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-0">
          <div class="px-3 py-3 border-b md:border-b-0 md:border-r border-gray-200">
            <div class="font-semibold mb-1 text-gray-800">Payment terms</div>
            <?php if ($paymentTs !== ''): ?>
              <div class="whitespace-pre-line leading-snug text-gray-700"><?= $h($paymentTs) ?></div>
            <?php else: ?>
              <div class="text-gray-500">
                Custom payment terms can be filled on the quote create screen.
              </div>
            <?php endif; ?>
          </div>
          <div class="px-3 py-3">
            <div class="font-semibold mb-1 text-gray-800">Delivery terms</div>
            <?php if ($deliveryTs !== ''): ?>
              <div class="whitespace-pre-line leading-snug text-gray-700"><?= $h($deliveryTs) ?></div>
            <?php else: ?>
              <div class="text-gray-500">
                Add delivery terms or notes per quote to keep tender responses clear.
              </div>
            <?php endif; ?>
          </div>
        </div>
      </section>

      <!-- Footer: signature + declaration -->
      <section class="mt-4 grid grid-cols-1 md:grid-cols-2 gap-6 text-xs">
        <div>
          <div class="font-semibold text-gray-800 mb-6">
            Authorised signature
          </div>
          <div class="mt-8 border-t border-gray-300 w-48"></div>
          <div class="mt-1 text-[11px] text-gray-600">
            For <?= $h($idName !== '' ? $idName : ($orgName !== '' ? $orgName : '')) ?>
          </div>
        </div>
        <div class="text-[11px] text-gray-600 md:text-right">
          <div class="font-semibold text-gray-800 mb-1">
            Declaration
          </div>
          <p>
            This quotation has been generated from KlinFlow for <?= $h($idName !== '' ? $idName : ($orgName !== '' ? $orgName : 'your organisation')) ?>.
            It is a system-generated document and does not require a handwritten or stamped signature.
          </p>
        </div>
      </section>

    </div>
  </div>
</div>

