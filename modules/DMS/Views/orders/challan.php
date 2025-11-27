<?php
/**
 * modules/DMS/Views/orders/challan.php
 * Compact Delivery Challan (no prices). A4-ready, brand-consistent, content-only.
 *
 * Expects (from controller):
 * - array $order : { id, order_no, order_date, delivery_date, customer_name, address?, status,
 *                    vehicle_no?, driver_name?, delivery_note?, ref_no? }
 * - array $items : [{ product_name, qty, unit? }, ...]  // unit optional
 * - array $org   : { name, address, phone, logo_url }   // optional, used for header
 * - string $module_base (optional) e.g. /t/{slug}/apps/dms
 */
declare(strict_types=1);

if (!function_exists('h')) { function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); } }

$brand  = '#228B22';
$order  = is_array($order ?? null) ? $order : [];
$items  = is_array($items ?? null) ? $items : [];
$org    = is_array($org   ?? null) ? $org   : ['name'=>'','address'=>'','phone'=>'','logo_url'=>''];
$base   = (string)($module_base ?? '/apps/dms');

$no     = (string)($order['order_no'] ?? ('#'.($order['id'] ?? '')));
$ref    = (string)($order['ref_no'] ?? '');
$odate  = substr((string)($order['order_date'] ?? ''),0,10);
$ddate  = substr((string)($order['delivery_date'] ?? $odate),0,10);
$cust   = (string)($order['customer_name'] ?? '');
$caddr  = (string)($order['customer_address'] ?? $order['address'] ?? '');
$status = strtoupper((string)($order['status'] ?? 'DRAFT'));

$veh    = (string)($order['vehicle_no'] ?? '');
$driver = (string)($order['driver_name'] ?? '');
$note   = trim((string)($order['delivery_note'] ?? $order['notes'] ?? ''));

$totalQty = 0.0;
foreach ($items as $ln) $totalQty += (float)($ln['qty'] ?? 0);

$printedAt = date('Y-m-d H:i');
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <title><?= h("Delivery Challan {$no}") ?></title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <style>
    :root { --brand: <?= $brand ?>; --ink:#0f172a; --muted:#64748b; }
    * { box-sizing: border-box; }
    body { margin:0; background:#fff; color:var(--ink); font:12px/1.45 system-ui, -apple-system, Segoe UI, Roboto, Arial, sans-serif; }
    .sheet { margin:24px auto; padding:24px; max-width:900px; border:1px solid #e5e7eb; border-radius:16px; }
    header { display:flex; gap:16px; align-items:center; border-bottom:2px solid var(--brand); padding-bottom:12px; }
    .logo { width:72px; height:72px; object-fit:contain; }
    .org h1 { margin:0; font-size:20px; line-height:1.1; }
    .org .meta { margin-top:4px; color:var(--muted); font-size:12px; }
    .titlebar { display:flex; justify-content:space-between; align-items:end; margin-top:14px; }
    .titlebar h2 { margin:0; font-size:18px; letter-spacing:.3px; }
    .badge { display:inline-block; padding:2px 8px; border-radius:999px; font-size:11px; color:#fff; background:var(--brand); }
    .grid { display:grid; grid-template-columns:1fr 1fr; gap:16px; margin-top:16px; }
    .card { border:1px solid #e5e7eb; border-radius:12px; padding:12px; }
    .label { color:var(--muted); font-size:11px; margin-bottom:4px; }
    .val { font-size:13px; white-space:pre-wrap; }
    .row { display:flex; gap:16px; }
    .row .flex-1 { flex:1; }
    table { width:100%; border-collapse:collapse; margin-top:16px; }
    thead th { text-align:left; font-weight:600; background:#f8fafc; border-bottom:1px solid #e5e7eb; padding:8px; }
    tbody td { border-bottom:1px solid #f1f5f9; padding:8px; vertical-align:top; }
    .tright { text-align:right; }
    .muted { color:var(--muted); }
    .kv { display:grid; grid-template-columns: 140px 1fr; gap:6px 12px; }
    .footgrid { display:grid; grid-template-columns: repeat(3, 1fr); gap:16px; margin-top:18px; }
    .sig { border:1px solid #e5e7eb; border-radius:12px; padding:12px; height:96px; display:flex; justify-content:space-between; flex-direction:column; }
    .line { border-top:1px solid #cbd5e1; margin-top:auto; padding-top:6px; }
    .actions { margin-bottom:12px; display:flex; gap:8px; }
    .btn { display:inline-block; padding:8px 12px; border-radius:8px; border:1px solid #e5e7eb; background:#fff; text-decoration:none; color:#111827; font-size:12px; }
    .btn.brand { background:var(--brand); color:#fff; border-color:var(--brand); }
    @media print {
      .sheet { border:none; margin:0; border-radius:0; padding:0; max-width:none; }
      .actions { display:none !important; }
      a[href]:after { content:""; }
      @page { size: A4; margin: 14mm; }
    }
  </style>
</head>
<body>
  <div class="sheet">
    <!-- On-screen actions -->
    <div class="actions">
      <a class="btn" href="<?= h($base.'/orders') ?>">&larr; Back</a>
      <button class="btn brand" onclick="window.print()">Print</button>
      <a class="btn" href="<?= h($base.'/orders/'.(int)($order['id']??0).'/edit') ?>">Edit</a>
      <a class="btn" href="<?= h($base.'/orders/'.(int)($order['id']??0).'/print') ?>">View Invoice-style</a>
    </div>

    <header>
      <?php if (!empty($org['logo_url'])): ?>
        <img class="logo" src="<?= h($org['logo_url']) ?>" alt="Logo">
      <?php else: ?>
        <div class="logo" style="border:1px solid #e5e7eb; border-radius:12px; display:flex; align-items:center; justify-content:center; color:#94a3b8;">LOGO</div>
      <?php endif; ?>
      <div class="org">
        <h1><?= h($org['name'] ?: 'Organization') ?></h1>
        <div class="meta">
          <?php if (!empty($org['address'])): ?><span><?= h($org['address']) ?></span><?php endif; ?>
          <?php if (!empty($org['phone'])): ?><span><?= $org['address'] ? ' · ' : '' ?>Phone: <?= h($org['phone']) ?></span><?php endif; ?>
        </div>
      </div>
    </header>

    <div class="titlebar">
      <h2>Delivery Challan</h2>
      <div style="text-align:right">
        <div style="font-weight:600; font-size:14px;">Order No: <?= h($no) ?></div>
        <?php if ($ref !== ''): ?><div class="muted">Ref: <?= h($ref) ?></div><?php endif; ?>
        <div class="muted">Status: <span class="badge"><?= h($status) ?></span></div>
      </div>
    </div>

    <!-- Parties / Dates -->
    <div class="grid">
      <div class="card">
        <div class="label">Deliver To</div>
        <div class="val" style="font-weight:600;"><?= h($cust ?: '—') ?></div>
        <?php if ($caddr !== ''): ?>
          <div class="val muted" style="margin-top:2px;"><?= nl2br(h($caddr)) ?></div>
        <?php endif; ?>
      </div>
      <div class="card">
        <div class="kv">
          <div class="label">Order Date</div><div class="val"><?= h($odate ?: '—') ?></div>
          <div class="label">Delivery Date</div><div class="val"><?= h($ddate ?: '—') ?></div>
          <?php if ($veh !== ''): ?>
            <div class="label">Vehicle No</div><div class="val"><?= h($veh) ?></div>
          <?php endif; ?>
          <?php if ($driver !== ''): ?>
            <div class="label">Driver</div><div class="val"><?= h($driver) ?></div>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <!-- Item list (NO PRICES) -->
    <table aria-label="Challan items">
      <thead>
        <tr>
          <th style="width:70%;">Product</th>
          <th class="tright" style="width:15%;">Qty</th>
          <th style="width:15%;">Unit</th>
        </tr>
      </thead>
      <tbody>
        <?php if ($items): ?>
          <?php foreach ($items as $ln):
            $nm = (string)($ln['product_name'] ?? '');
            $q  = (float)($ln['qty'] ?? 0);
            $u  = (string)($ln['unit'] ?? $ln['uom'] ?? $ln['uom_name'] ?? '');
          ?>
            <tr>
              <td><?= h($nm ?: '—') ?></td>
              <td class="tright"><?= h(rtrim(rtrim(number_format($q,2,'.',''), '0'), '.')) ?></td>
              <td><?= h($u) ?></td>
            </tr>
          <?php endforeach; ?>
        <?php else: ?>
          <tr><td colspan="3" class="muted">No items.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>

    <!-- Totals (qty only) + Notes -->
    <div class="row" style="margin-top:10px;">
      <div class="flex-1">
        <?php if ($note !== ''): ?>
          <div class="card" style="border-style:dashed;">
            <div class="label" style="margin-bottom:6px;">Delivery Note</div>
            <div class="val"><?= nl2br(h($note)) ?></div>
          </div>
        <?php endif; ?>
      </div>
      <div style="width:260px;">
        <div class="card">
          <div class="row" style="justify-content:space-between;">
            <div class="label" style="margin:0;">Total Quantity</div>
            <div style="font-weight:700;"><?= h(rtrim(rtrim(number_format($totalQty,2,'.',''), '0'), '.')) ?></div>
          </div>
        </div>
      </div>
    </div>

    <!-- Signatures -->
    <div class="footgrid">
      <div class="sig">
        <div class="label">Prepared By</div>
        <div class="line muted">Signature & Date</div>
      </div>
      <div class="sig">
        <div class="label">Checked/Approved By</div>
        <div class="line muted">Signature & Date</div>
      </div>
      <div class="sig">
        <div class="label">Received By</div>
        <div class="line muted">Signature, Name & Date</div>
      </div>
    </div>

    <footer style="margin-top:18px; display:flex; justify-content:space-between; align-items:center; color:var(--muted); font-size:11px;">
      <div>Goods delivered in good condition unless otherwise noted.</div>
      <div>Printed: <?= h($printedAt) ?></div>
    </footer>
  </div>
</body>
</html>