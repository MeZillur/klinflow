<?php
/**
 * modules/DMS/Views/sales/print.php
 * Standalone printable Invoice view (hides app chrome like order print).
 * Expects: $sale, $items, $org (name,address,phone,email,logo_url), $module_base, $autoprint
 */
declare(strict_types=1);
if (!function_exists('h')) { function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); } }
$brand = '#228B22';

$sale  = is_array($sale ?? null)  ? $sale  : [];
$items = is_array($items ?? null) ? $items : [];
$org   = is_array($org   ?? null) ? $org   : ['name'=>'','address'=>'','phone'=>'','email'=>'','logo_url'=>''];
$base  = (string)($module_base ?? '/apps/dms');

$id     = (int)($sale['id'] ?? 0);
$no     = (string)($sale['sale_no'] ?? ('#'.$id));
$invDt  = substr((string)($sale['sale_date'] ?? ''),0,10);
$cust   = (string)($sale['customer_name'] ?? '');
$status = strtoupper((string)($sale['status'] ?? ($sale['invoice_status'] ?? 'ISSUED')));
$discT  = (string)($sale['discount_type'] ?? 'amount');
$discV  = (float)($sale['discount_value'] ?? 0);
$notes  = trim((string)($sale['notes'] ?? ''));

$fmt   = fn($n)=>number_format((float)$n, 2, '.', '');
$money = fn($n)=>'৳ '.$fmt($n);

$subtotal = 0.0;
foreach ($items as $ln) {
    $q=(float)($ln['qty'] ?? 0);
    $p=(float)($ln['unit_price'] ?? $ln['price'] ?? 0);
    $lt=(float)($ln['line_total'] ?? ($q*$p));
    $subtotal += $lt;
}
$discount = ($discT === 'percent') ? min($subtotal, round($subtotal*($discV/100),2))
                                   : min($subtotal, round($discV,2));
$grand = max(0, round($subtotal - $discount, 2));
$printedAt = date('Y-m-d H:i');
$auto = !empty($autoprint);
?>
<!doctype html>
<html>
<head>
<meta charset="utf-8">
<title><?= h("Invoice {$no}") ?></title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<style>
  :root { --brand: <?= $brand ?>; --ink:#0f172a; --muted:#64748b; }
  * { box-sizing:border-box; }
  body { margin:0; background:#fff; color:var(--ink); font:12px/1.45 system-ui,-apple-system,Segoe UI,Roboto,Arial,sans-serif; }

  /* Hide EVERYTHING outside .printable when printing to avoid shell */
  @media print {
    body * { visibility: hidden !important; }
    .printable, .printable * { visibility: visible !important; }
    .printable { position: static !important; }
    @page { size:A4; margin: 12mm; }
  }

  .toolbar { display:flex; gap:8px; padding:10px 16px; border-bottom:1px solid #e5e7eb; position:sticky; top:0; background:#fff; }
  .btn { display:inline-flex; align-items:center; gap:8px; padding:8px 12px; border-radius:10px; border:1px solid #e5e7eb; background:#fff; font-size:12px; text-decoration:none; color:#111827; }
  .btn.brand { background:var(--brand); color:#fff; border-color:var(--brand); }
  .btn:hover { filter:brightness(.98); }
  @media print { .toolbar { display:none !important; } }

  .sheet { margin: 16px auto; padding: 20px; max-width: 900px; }
  header { display:flex; gap:16px; align-items:center; border-bottom:2px solid var(--brand); padding-bottom:12px; }
  .logo { width:70px; height:70px; object-fit:contain; }
  .org h1 { margin:0; font-size:20px; }
  .org .meta { margin-top:2px; color:var(--muted); font-size:12px; }
  .titlebar { display:flex; justify-content:space-between; align-items:flex-end; margin-top:14px; }
  .titlebar h2 { margin:0; font-size:18px; letter-spacing:.2px; }
  .badge { display:inline-block; padding:2px 8px; border-radius:999px; font-size:11px; color:#fff; background:var(--brand); }

  .grid { display:grid; grid-template-columns:1fr 1fr; gap:14px; margin-top:14px; }
  .card { border:1px solid #e5e7eb; border-radius:12px; padding:10px; }
  .label { color:var(--muted); font-size:11px; margin-bottom:4px; }
  .val { font-size:13px; }

  table { width:100%; border-collapse:collapse; margin-top:14px; }
  thead th { text-align:left; font-weight:600; background:#f8fafc; border-bottom:1px solid #e5e7eb; padding:8px; }
  tbody td { border-bottom:1px solid #f1f5f9; padding:8px; vertical-align:top; }
  .tr { text-align:right; }

  .totals { width:360px; margin-left:auto; margin-top:10px; border:1px solid #e5e7eb; border-radius:12px; overflow:hidden; }
  .totals .row { display:flex; justify-content:space-between; padding:8px 12px; border-bottom:1px solid #eef2f7; }
  .totals .row:last-child { border-bottom:0; background:#f8fafc; font-weight:700; }

  .signers { display:grid; grid-template-columns: repeat(3, 1fr); gap:12px; margin-top:16px; }
  .sig { border:1px dashed #e5e7eb; border-radius:10px; padding:10px; height:90px; display:flex; flex-direction:column; justify-content:flex-end; }
  .sig .line { border-top:1px solid #cbd5e1; margin-top:auto; }
  .sig .who { font-size:11px; color:var(--muted); margin-top:6px; }

  .notes { margin-top:14px; border:1px dashed #e5e7eb; border-radius:12px; padding:10px; white-space:pre-wrap; }

  footer { margin-top:14px; display:flex; justify-content:space-between; align-items:center; color:var(--muted); font-size:11px; }
</style>
</head>
<body>

<!-- on-screen tiny toolbar (hidden on print) -->
<div class="toolbar">
  <a class="btn" href="<?= h($base.'/sales') ?>">← Back</a>
  <button class="btn brand" onclick="window.print()">Print</button>
  <a class="btn" href="<?= h($base.'/sales/'.$id) ?>">View</a>
</div>

<div class="printable">
  <div class="sheet">

    <header>
      <?php if (!empty($org['logo_url'])): ?>
        <img class="logo" src="<?= h($org['logo_url']) ?>" alt="Logo">
      <?php else: ?>
        <div class="logo" style="border:1px solid #e5e7eb; border-radius:12px; display:flex; align-items:center; justify-content:center; color:#94a3b8;">LOGO</div>
      <?php endif; ?>
      <div class="org">
        <h1><?= h($org['name'] ?: 'Organization') ?></h1>
        <div class="meta">
          <?php if (!empty($org['address'])): ?><?= h($org['address']) ?><?php endif; ?>
          <?php if (!empty($org['phone'])): ?><?= !empty($org['address']) ? ' · ' : '' ?><?= h($org['phone']) ?><?php endif; ?>
          <?php if (!empty($org['email'])): ?><?= (!empty($org['address'])||!empty($org['phone'])) ? ' · ' : '' ?><?= h($org['email']) ?><?php endif; ?>
        </div>
      </div>
    </header>

    <div class="titlebar">
      <h2>Invoice</h2>
      <div style="text-align:right">
        <div style="font-weight:600; font-size:14px;">Invoice No: <?= h($no) ?></div>
        <div class="badge" style="margin-top:4px;"><?= h($status) ?></div>
      </div>
    </div>

    <div class="grid">
      <div class="card">
        <div class="label">Customer</div>
        <div class="val"><?= h($cust ?: '—') ?></div>
      </div>
      <div class="card">
        <div class="label">Invoice Date</div>
        <div class="val"><?= h($invDt ?: '—') ?></div>
      </div>
    </div>

    <table aria-label="Invoice items">
      <thead>
        <tr>
          <th style="width:52%;">Product</th>
          <th class="tr" style="width:12%;">Qty</th>
          <th class="tr" style="width:18%;">Unit Price</th>
          <th class="tr" style="width:18%;">Line Total</th>
        </tr>
      </thead>
      <tbody>
        <?php if ($items): foreach ($items as $ln):
          $nm=(string)($ln['product_name'] ?? $ln['name'] ?? '');
          $q =(float)($ln['qty'] ?? 0);
          $p =(float)($ln['unit_price'] ?? $ln['price'] ?? 0);
          $lt=(float)($ln['line_total'] ?? ($q*$p));
        ?>
          <tr>
            <td><?= h($nm ?: '—') ?></td>
            <td class="tr"><?= h(rtrim(rtrim(number_format($q,2,'.',''), '0'), '.')) ?></td>
            <td class="tr"><?= $money($p) ?></td>
            <td class="tr"><?= $money($lt) ?></td>
          </tr>
        <?php endforeach; else: ?>
          <tr><td colspan="4" style="color:#64748b">No items.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>

    <div class="totals">
      <div class="row"><span>Subtotal</span><span><?= $money($subtotal) ?></span></div>
      <div class="row">
        <span>Discount<?= $discT==='percent' ? ' ('.$fmt($discV).'%)' : '' ?></span>
        <span>− <?= $money($discount) ?></span>
      </div>
      <div class="row"><span>Grand Total</span><span><?= $money($grand) ?></span></div>
    </div>

    <!-- signers -->
    <div class="signers">
      <div class="sig">
        <div class="line"></div>
        <div class="who">Prepared By</div>
      </div>
      <div class="sig">
        <div class="line"></div>
        <div class="who">Authorised By</div>
      </div>
      <div class="sig">
        <div class="line"></div>
        <div class="who">Approved By</div>
      </div>
    </div>

    <?php if ($notes !== ''): ?>
      <div class="notes">
        <div class="label" style="margin-bottom:6px;">Notes</div>
        <?= nl2br(h($notes)) ?>
      </div>
    <?php endif; ?>

    <footer>
      <div>
        Thank you for your business. This is a system-generated document; no signature is required.
      </div>
      <div>Printed: <?= h($printedAt) ?></div>
    </footer>

  </div>
</div>

<?php if ($auto): ?>
<script>window.addEventListener('load', ()=>{ setTimeout(()=>window.print(), 150); });</script>
<?php endif; ?>
</body>
</html>