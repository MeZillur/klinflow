<?php
/** @var array $invoice @var array $lines @var array $branding */
$size = $branding['print_size'] ?? 'A4'; // 'A4' | 'A5' | 'POS'
$h=fn($v)=>htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');
?>
<!doctype html><meta charset="utf-8">
<title>Print Invoice</title>
<style>
  :root{--brand:var(--brand,#228B22);font-family:system-ui,-apple-system,Segoe UI,Roboto}
  body{margin:0;padding:0}
  .sheet{margin:0 auto;padding:16px}
  .A4{width:210mm}.A5{width:148mm}.POS{width:80mm}
  @media print{
    body{background:#fff}
    .sheet{padding:0}
  }
  .hdr{display:flex;gap:12px;align-items:center;border-bottom:1px solid #ddd;padding-bottom:8px;margin-bottom:12px}
  .logo{height:36px}
  table{width:100%;border-collapse:collapse}
  th,td{padding:6px 8px;border-top:1px solid #eee;font-size:12px}
  tfoot td{font-weight:700}
  .tot{font-size:14px}
  .muted{color:#666;font-size:11px}
</style>
<div class="sheet <?= $size ?>">
  <div class="hdr">
    <img class="logo" src="<?= $h((string)$branding['logo_path']) ?>" alt="logo">
    <div>
      <div style="font-weight:800"><?= $h((string)$branding['org_name']) ?></div>
      <div class="muted"><?= $h((string)$branding['org_address']) ?></div>
      <div class="muted"><?= $h((string)$branding['org_phone']) ?> • <?= $h((string)$branding['org_web']) ?> • <?= $h((string)$branding['org_email']) ?></div>
    </div>
  </div>

  <table>
    <thead><tr><th style="text-align:left">Description</th><th style="text-align:right">Qty</th><th style="text-align:right">Unit</th><th style="text-align:right">Tax</th><th style="text-align:right">Line</th></tr></thead>
    <tbody>
    <?php $sub=0; $tax=0; foreach ($lines as $l):
      $qty=(float)($l['qty'] ?? 1); $unit=(float)($l['unit_price'] ?? 0); $tx=(float)($l['tax_amount'] ?? 0);
      $sub += $qty*$unit; $tax += $tx; ?>
      <tr>
        <td><?= $h((string)($l['description'] ?? '')) ?></td>
        <td style="text-align:right"><?= number_format($qty,2) ?></td>
        <td style="text-align:right"><?= number_format($unit,2) ?></td>
        <td style="text-align:right"><?= number_format($tx,2) ?></td>
        <td style="text-align:right"><?= number_format($qty*$unit+$tx,2) ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
    <tfoot>
      <tr><td colspan="4" style="text-align:right">Subtotal</td><td style="text-align:right"><?= number_format($sub,2) ?></td></tr>
      <tr><td colspan="4" style="text-align:right">Tax</td><td style="text-align:right"><?= number_format($tax,2) ?></td></tr>
      <tr><td colspan="4" class="tot" style="text-align:right">Total</td><td class="tot" style="text-align:right"><?= number_format($sub+$tax,2) ?></td></tr>
    </tfoot>
  </table>

  <?php if (!empty($branding['invoice_footer'])): ?>
    <div class="muted" style="margin-top:10px"><?= nl2br($h((string)$branding['invoice_footer'])) ?></div>
  <?php endif; ?>
</div>
<script>window.print&&setTimeout(()=>window.print(),200);</script>