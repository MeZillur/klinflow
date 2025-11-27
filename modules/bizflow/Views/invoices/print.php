<?php
declare(strict_types=1);

/**
 * BizFlow — Invoice print (A4)
 *
 * SEGMENT 0: Expectations
 * -----------------------
 * Expects:
 *   - array  $invoice   (header + totals)
 *   - array  $items     (line items)
 *   - array  $org       (current organisation context)
 *   - string $module_base (optional, not required here)
 */

$h = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');

/* ============================================================
 * SEGMENT 1: Org basics
 * ========================================================== */
$org   = $org ?? [];
$orgId = (int)($org['id'] ?? 0);

$orgName = trim((string)($org['name'] ?? 'DEPENDCORE'));
$orgAddr = trim((string)($org['address'] ?? 'House-20, Road-17, Nikunjo-2'));
$orgPhone = trim((string)($org['phone'] ?? '01712378526'));
$orgEmail = trim((string)($org['email'] ?? 'dependcore@gmail.com'));

/* ============================================================
 * SEGMENT 2: Identity + logo resolution (COPY of quote logic)
 *
 *  Identity:
 *    1) Optional $identity array from controller
 *    2) modules/bizflow/Assets/settings/org_{id}/identity.json
 *    3) Fallback to $org fields
 *
 *  Logo:
 *    1) Optional $logo['data_url'] or ['url']
 *    2) modules/bizflow/Assets/brand/logo/org_{id}/logo.{png|jpg|jpeg|webp|svg}
 *       → embedded as data: URL (works in browser + PDF)
 *    3) Final fallback: /assets/brand/logo.png (KlinFlow mark)
 * ========================================================== */

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

/* ----- Logo detection (exactly like quote print) ----- */
$logoArr = [];
if (isset($logo) && is_array($logo)) {
    $logoArr = $logo;
}

$logoUrl = '';

// 1) Prefer data_url passed from controller
if (!empty($logoArr['data_url'])) {
    $logoUrl = (string)$logoArr['data_url'];
} elseif (!empty($logoArr['url'])) {
    // Or a ready-to-use URL from controller
    $logoUrl = (string)$logoArr['url'];
}

// 2) If still empty and we know org id, embed file as data: URL
if ($logoUrl === '' && $orgId > 0) {
    $logoBaseFs = dirname(__DIR__, 2) . '/Assets/brand/logo';
    $orgKey     = 'org_' . $orgId;
    $candidates = ['png', 'jpg', 'jpeg', 'webp', 'svg'];

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

// 3) Final fallback → global KlinFlow mark (under /public/assets)
if ($logoUrl === '') {
    $logoUrl = '/assets/brand/logo.png';
}

/* ----- Identity fallbacks ----- */
$idName    = trim((string)($identityArr['name']    ?? ($org['name']    ?? '')));
$idAddress = trim((string)($identityArr['address'] ?? ($org['address'] ?? '')));
$idPhone   = trim((string)($identityArr['phone']   ?? ($org['phone']   ?? '')));
$idEmail   = trim((string)($identityArr['email']   ?? ($org['email']   ?? '')));

/* ============================================================
 * SEGMENT 3: Invoice core fields + totals
 * ========================================================== */

$invId   = (int)($invoice['id'] ?? 0);
$invNo   = (string)($invoice['invoice_no'] ?? ('INV-' . $invId));
$status  = ucfirst((string)($invoice['status'] ?? 'draft'));
$date    = (string)($invoice['date'] ?? date('Y-m-d'));
$dueDate = (string)($invoice['due_date'] ?? '');
$currency = (string)($invoice['currency'] ?? 'BDT');

$subtotal      = (float)($invoice['subtotal']       ?? 0);
$discountTotal = (float)($invoice['discount_total'] ?? 0);
$taxTotal      = (float)($invoice['tax_total']      ?? 0);
$shippingTotal = (float)($invoice['shipping_total'] ?? 0);
$grandTotal    = (float)($invoice['grand_total']    ?? 0);

/* ============================================================
 * SEGMENT 4: Meta (payment/delivery terms + customer info)
 * ========================================================== */

$metaRaw = $invoice['meta_json'] ?? null;
$meta    = [];
if ($metaRaw) {
    $tmp = json_decode((string)$metaRaw, true);
    if (is_array($tmp)) {
        $meta = $tmp;
    }
}

$paymentTerms  = trim((string)($meta['payment_terms']  ?? ($invoice['payment_terms']  ?? '')));
$deliveryTerms = trim((string)($meta['delivery_terms'] ?? ($invoice['delivery_terms'] ?? '')));

$customerName    = trim((string)($meta['customer_name']    ?? ($invoice['customer_name']    ?? '')));
$customerContact = trim((string)($meta['customer_contact'] ?? ($invoice['customer_contact'] ?? '')));
$customerRef     = trim((string)($meta['customer_ref']     ?? ($invoice['customer_ref']     ?? '')));

/* ============================================================
 * SEGMENT 5: Query-string flags (auto print / download)
 * ========================================================== */

$autoPrint = isset($_GET['auto'])     && $_GET['auto'] === '1';
$download  = isset($_GET['download']) && $_GET['download'] === '1';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Invoice <?= $h($invNo) ?></title>
    <style>
        /* SEGMENT 6: Page + typography */
        @page {
            size: A4;
            margin: 15mm 15mm 20mm 15mm;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            padding: 0;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            font-size: 12px;
            color: #222;
        }
        .doc { max-width: 780px; margin: 0 auto; }

        /* HEADER */
        .doc-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            border-bottom: 2px solid #222;
            padding-bottom: 10px;
            margin-bottom: 16px;
        }
        .doc-brand {
            display: flex;
            align-items: flex-start;
            gap: 10px;
        }
        .brand-logo {
            width: 40px;
            height: 40px;
        }
        .brand-logo img {
            display: block;
            width: 100%;
            height: 100%;
            object-fit: contain;
        }
        .brand-text { font-size: 13px; }
        .brand-name {
            font-size: 16px;
            font-weight: 700;
            letter-spacing: .03em;
        }
        .brand-sub {
            margin-top: 2px;
            line-height: 1.4;
        }

        .doc-type {
            text-align: right;
            font-size: 11px;
        }
        .doc-type-title {
            font-size: 18px;
            font-weight: 700;
            letter-spacing: .16em;
        }
        .doc-type table {
            margin-top: 6px;
            margin-left: auto;
            border-collapse: collapse;
        }
        .doc-type td {
            padding: 1px 0 1px 12px;
            font-size: 11px;
        }
        .doc-type td:first-child {
            padding-left: 0;
            font-weight: 600;
        }

        /* CUSTOMER + SUMMARY BOXES */
        .box-row {
            display: grid;
            grid-template-columns: 1.1fr 1.1fr;
            gap: 10px;
            margin-bottom: 14px;
        }
        .box {
            border: 1px solid #ddd;
        }
        .box-header {
            background: #f7f7f7;
            padding: 6px 10px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: .06em;
        }
        .box-body {
            padding: 8px 10px 10px;
            font-size: 11px;
        }
        .box-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 11px;
        }
        .box-table td:first-child {
            width: 32%;
            font-weight: 600;
            color: #555;
        }
        .box-table td {
            padding: 1px 0;
            vertical-align: top;
        }

        /* LINES TABLE */
        .lines-wrapper { margin-top: 16px; }
        .lines-title {
            font-size: 11px;
            font-weight: 600;
            margin-bottom: 4px;
            text-transform: uppercase;
        }
        table.lines {
            width: 100%;
            border-collapse: collapse;
            font-size: 11px;
        }
        table.lines thead th {
            border-top: 1px solid #ddd;
            border-bottom: 1px solid #ddd;
            padding: 5px 4px;
            text-align: left;
            font-weight: 600;
            text-transform: uppercase;
            font-size: 10px;
            background: #f9f9f9;
        }
        table.lines tbody td {
            border-bottom: 1px solid #eee;
            padding: 5px 4px;
            vertical-align: top;
        }
        table.lines th.num,
        table.lines td.num { text-align: right; width: 55px; }
        table.lines th.qty,
        table.lines td.qty { width: 60px; text-align: right; }
        table.lines th.unit,
        table.lines td.unit { width: 60px; text-align: center; }
        table.lines th.price,
        table.lines td.price,
        table.lines th.disc,
        table.lines td.disc,
        table.lines th.total,
        table.lines td.total {
            width: 80px;
            text-align: right;
        }
        .line-small {
            font-size: 10px;
            color: #666;
        }

        /* TOTALS + NOTE */
        .footer-row {
            display: grid;
            grid-template-columns: minmax(0, 1.3fr) minmax(0, .9fr);
            gap: 10px;
            margin-top: 10px;
        }
        .totals-note {
            font-size: 10px;
            color: #666;
            line-height: 1.5;
            padding-top: 4px;
        }
        .totals-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 11px;
        }
        .totals-table td { padding: 1px 0; }
        .totals-table td:first-child {
            text-align: right;
            padding-right: 8px;
        }
        .totals-table td:last-child {
            text-align: right;
            width: 110px;
        }
        .totals-table .grand-label,
        .totals-table .grand-value {
            font-weight: 700;
            padding-top: 3px;
        }
        .totals-table .discount { color: #b00020; }

        /* TERMS + SIGNATURE */
        .terms-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
            margin-top: 18px;
        }
        .terms-box {
            border: 1px solid #ddd;
        }
        .terms-box-header {
            background: #f7f7f7;
            padding: 6px 10px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: .06em;
        }
        .terms-box-body {
            padding: 8px 10px 10px;
            font-size: 11px;
            min-height: 45px;
        }

        .signature-row {
            display: grid;
            grid-template-columns: 1.2fr 1fr;
            gap: 10px;
            margin-top: 26px;
            font-size: 11px;
        }
        .sig-line {
            margin-top: 24px;
            border-top: 1px solid #222;
            width: 55%;
        }
        .sig-label { margin-top: 6px; }
        .decl {
            font-size: 10px;
            color: #555;
        }
        .decl-title {
            font-weight: 600;
            margin-bottom: 4px;
        }

        @media print {
            body { background: #fff; }
            .no-print { display: none !important; }
        }
    </style>
</head>
<body>
<div class="doc">

    <!-- SEGMENT 7: Header -->
    <div class="doc-header">
        <div class="doc-brand">
            <div class="brand-logo">
                <?php if ($logoUrl !== ''): ?>
                    <img src="<?= $h($logoUrl) ?>" alt="Logo">
                <?php endif; ?>
            </div>
            <div class="brand-text">
                <div class="brand-name">
                    <?= $h($idName !== '' ? $idName : $orgName) ?>
                </div>
                <div class="brand-sub">
                    <?= $h($idAddress !== '' ? $idAddress : $orgAddr) ?><br>
                    Phone: <?= $h($idPhone !== '' ? $idPhone : $orgPhone) ?>
                    &nbsp;&nbsp; Email: <?= $h($idEmail !== '' ? $idEmail : $orgEmail) ?>
                </div>
            </div>
        </div>
        <div class="doc-type">
            <div class="doc-type-title">INVOICE</div>
            <table>
                <tr>
                    <td>Invoice date:</td>
                    <td><?= $h($date) ?></td>
                </tr>
                <?php if ($dueDate !== ''): ?>
                <tr>
                    <td>Due date:</td>
                    <td><?= $h($dueDate) ?></td>
                </tr>
                <?php endif; ?>
            </table>
        </div>
    </div>

    <!-- SEGMENT 8: Customer + Summary -->
    <div class="box-row">
        <div class="box">
            <div class="box-header">Customer</div>
            <div class="box-body">
                <table class="box-table">
                    <tr>
                        <td>Name:</td>
                        <td><?= $h($customerName !== '' ? $customerName : '—') ?></td>
                    </tr>
                    <tr>
                        <td>Contact:</td>
                        <td><?= $h($customerContact !== '' ? $customerContact : '—') ?></td>
                    </tr>
                    <tr>
                        <td>Reference:</td>
                        <td><?= $h($customerRef !== '' ? $customerRef : '—') ?></td>
                    </tr>
                </table>
            </div>
        </div>
        <div class="box">
            <div class="box-header">Summary</div>
            <div class="box-body">
                <table class="box-table">
                    <tr>
                        <td>Invoice no:</td>
                        <td><?= $h($invNo) ?></td>
                    </tr>
                    <tr>
                        <td>Status:</td>
                        <td><?= $h($status) ?></td>
                    </tr>
                    <tr>
                        <td>Invoice date:</td>
                        <td><?= $h($date) ?></td>
                    </tr>
                    <?php if ($dueDate !== ''): ?>
                    <tr>
                        <td>Due date:</td>
                        <td><?= $h($dueDate) ?></td>
                    </tr>
                    <?php endif; ?>
                    <tr>
                        <td>Currency:</td>
                        <td><?= $h($currency) ?></td>
                    </tr>
                </table>
            </div>
        </div>
    </div>

    <!-- SEGMENT 9: Line items -->
    <div class="lines-wrapper">
        <div class="lines-title">Invoice items</div>
        <table class="lines">
            <thead>
            <tr>
                <th class="num">#</th>
                <th>Item</th>
                <th>Key features / specification</th>
                <th class="qty">Qty</th>
                <th class="unit">Unit</th>
                <th class="price">Unit price</th>
                <th class="disc">Disc %</th>
                <th class="total">Line total</th>
            </tr>
            </thead>
            <tbody>
            <?php if (!empty($items)): ?>
                <?php $idx = 0; foreach ($items as $ln): $idx++; ?>
                    <?php
                    $name = trim((string)($ln['item_name'] ?? $ln['product_name'] ?? ''));
                    if ($name === '') {
                        $name = (string)($ln['description'] ?? ('Line ' . $idx));
                    }
                    $code      = trim((string)($ln['item_code'] ?? $ln['product_code'] ?? ''));
                    $spec      = trim((string)($ln['description'] ?? '—'));
                    $qty       = (float)($ln['qty'] ?? 0);
                    $unit      = (string)($ln['unit'] ?? 'pcs');
                    $price     = (float)($ln['unit_price'] ?? 0);
                    $discPct   = (float)($ln['discount_pct'] ?? 0);
                    $lineTotal = (float)($ln['line_total'] ?? ($qty * $price));
                    ?>
                    <tr>
                        <td class="num"><?= $idx ?></td>
                        <td>
                            <div><?= $h($name) ?></div>
                            <?php if ($code !== ''): ?>
                                <div class="line-small">Code: <?= $h($code) ?></div>
                            <?php endif; ?>
                        </td>
                        <td><?= $h($spec) ?></td>
                        <td class="qty"><?= number_format($qty, 3) ?></td>
                        <td class="unit"><?= $h($unit) ?></td>
                        <td class="price"><?= number_format($price, 2) ?></td>
                        <td class="disc"><?= $discPct !== 0.0 ? number_format($discPct, 2) : '0.00' ?></td>
                        <td class="total"><?= number_format($lineTotal, 2) ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="8" style="text-align:center; padding:8px 4px;">
                        No items found for this invoice.
                    </td>
                </tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- SEGMENT 10: Totals + note -->
    <div class="footer-row">
        <div class="totals-note">
            Totals are expressed in <?= $h($currency) ?> (BDT).<br>
            For now, invoices are kept simple without multi-currency breakdown; this can be extended later if needed.
        </div>
        <div>
            <table class="totals-table">
                <tr>
                    <td>Subtotal</td>
                    <td><?= number_format($subtotal, 2) ?></td>
                </tr>
                <tr>
                    <td class="discount">Discounts</td>
                    <td class="discount">- <?= number_format($discountTotal, 2) ?></td>
                </tr>
                <tr>
                    <td>Tax total</td>
                    <td><?= number_format($taxTotal, 2) ?></td>
                </tr>
                <tr>
                    <td>Shipping</td>
                    <td><?= number_format($shippingTotal, 2) ?></td>
                </tr>
                <tr>
                    <td class="grand-label">Grand total (BDT)</td>
                    <td class="grand-value"><?= number_format($grandTotal, 2) ?></td>
                </tr>
            </table>
        </div>
    </div>

    <!-- SEGMENT 11: Terms -->
    <div class="terms-row">
        <div class="terms-box">
            <div class="terms-box-header">Payment terms</div>
            <div class="terms-box-body">
                <?= nl2br($h($paymentTerms !== '' ? $paymentTerms : '')) ?>
            </div>
        </div>
        <div class="terms-box">
            <div class="terms-box-header">Delivery terms</div>
            <div class="terms-box-body">
                <?= nl2br($h($deliveryTerms !== '' ? $deliveryTerms : '')) ?>
            </div>
        </div>
    </div>

    <!-- SEGMENT 12: Signature + declaration -->
    <div class="signature-row">
        <div>
            <div class="sig-line"></div>
            <div class="sig-label">
                For <?= $h($idName !== '' ? $idName : $orgName) ?>
            </div>
            <div style="margin-top: 4px; font-size:10px; color:#666;">
                Authorised signature
            </div>
        </div>
        <div class="decl">
            <div class="decl-title">Declaration</div>
            This invoice has been generated from KlinFlow for
            <?= $h($idName !== '' ? $idName : $orgName) ?>.
            It is a system-generated document and does not
            require a handwritten or stamped signature.
        </div>
    </div>

</div>

<?php if ($autoPrint && !$download): ?>
<script>
  // SEGMENT 13: Auto-print for ?auto=1 (print button)
  window.addEventListener('load', function () {
    window.print();
  });
</script>
<?php endif; ?>
</body>
</html>