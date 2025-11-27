<?php
declare(strict_types=1);

/**
 * View: modules/DMS/Views/orders/show.php
 * Expects:
 *  - $order, $items, $module_base
 *  - $sale_id int|null   (optional)
 *  - $csrf_token string|null (optional)
 *
 * Controller routes used:
 *  - POST {base}/orders/{id}/issue-invoice
 *  - GET  {base}/orders/{id}/print
 *  - GET  {base}/orders/{id}/share/whatsapp[?phone=]
 *  - POST {base}/orders/{id}/share/email
 */

if (!function_exists('h')) { function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); } }

$KF_BRAND = '#228B22';
$WA_BRAND = '#25D366'; // WhatsApp green

$base   = rtrim((string)($module_base ?? ''), '/');
$o      = is_array($order ?? null) ? $order : [];
$lines  = is_array($items ?? null) ? $items : [];
$saleId = (int)($sale_id ?? 0);

$id    = (int)($o['id'] ?? 0);
$no    = (string)($o['order_no'] ?? ('ORD-'.str_pad((string)$id, 6, '0', STR_PAD_LEFT)));
$date  = substr((string)($o['order_date'] ?? ''), 0, 10);
$cust  = (string)($o['customer_name'] ?? '');
$supp  = (string)($o['supplier_name'] ?? '');
$st    = strtolower((string)($o['status'] ?? 'draft'));

$discType = (string)($o['discount_type'] ?? 'amount');
$discVal  = (float)($o['discount_value'] ?? 0);

$subtotal = 0.0;
foreach ($lines as $ln) {
  $qty   = (float)($ln['qty'] ?? $ln['quantity'] ?? 0);
  $price = (float)($ln['unit_price'] ?? $ln['price'] ?? $ln['rate'] ?? 0);
  $subtotal += $qty * $price;
}
$discount = $discType === 'percent' ? min($subtotal, $subtotal * ($discVal/100)) : min($subtotal, $discVal);
$grand    = max(0, $subtotal - $discount);

$pill  = [
  'draft'            => 'bg-slate-100 text-slate-700',
  'confirmed'        => 'bg-blue-100 text-blue-700',
  'pending_invoice'  => 'bg-amber-100 text-amber-700',
  'issued'           => 'bg-emerald-100 text-emerald-700',
  'paid'             => 'bg-emerald-100 text-emerald-700',
  'cancelled'        => 'bg-rose-100 text-rose-700',
][$st] ?? 'bg-slate-100 text-slate-700';

$hasLines   = count($lines) > 0;
$canIssue   = !in_array($st, ['issued','paid','cancelled'], true) && $hasLines;
$canRebuild = ($st === 'issued' || $st === 'paid') && $saleId === 0 && $hasLines;

$csrfToken = $csrf_token ?? ($_SESSION['_csrf'] ?? '');
?>
<style>
  :root { --kf: #228B22; --wa: #25D366; }
  /* Namespaced to avoid collisions with global .btn rules */
  .kfbtn{display:inline-flex;align-items:center;gap:.5rem;border-radius:12px;padding:.5rem .8rem;
         font-weight:600;border:1px solid transparent;line-height:1.2;white-space:nowrap}
  .kfbtn-brand{background:var(--kf);color:#fff;border-color:var(--kf)}
  .kfbtn-brand:hover{filter:brightness(.95)}
  .kfbtn-wa{background:var(--wa);color:#fff;border-color:var(--wa)}
  .kfbtn-wa:hover{filter:brightness(.95)}
  .kfbtn-ghost{border-color:#e2e8f0;background:#fff;color:#0f172a}
  .kfbtn-ghost:hover{background:#f8fafc}
  .kfbtn-disabled{background:#e5e7eb;color:#94a3b8;border-color:#e5e7eb;cursor:not-allowed}
</style>

<!-- Header -->
<div class="flex items-start justify-between mb-4">
  <div>
    <h2 class="text-xl font-semibold">Order <?= h($no) ?></h2>
    <div class="text-slate-500 text-sm">
      Date: <?= h($date) ?>
      <?= $cust !== '' ? ' · Customer: '.h($cust) : '' ?>
      <?= $supp !== '' ? ' · Supplier: '.h($supp) : '' ?>
    </div>
  </div>
  <div class="flex items-center gap-2">
    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium <?= $pill ?>">
      <?= h(strtoupper($st)) ?>
    </span>
    <a href="<?= h($base.'/orders/'.$id.'/edit') ?>" class="btn btn-ghost">
      <i class="fa-regular fa-pen-to-square"></i><span class="hide-sm">Edit</span>
    </a>
  </div>
</div>

<!-- Meta -->
<div class="mb-4 rounded-2xl border bg-white p-4">
  <div class="grid md:grid-cols-2 gap-4">
    <div class="kv">
      <div class="text-slate-500 text-xs">Order No</div><div class="font-medium"><?= h($no) ?></div>
      <div class="text-slate-500 text-xs">Date</div><div><?= h($date) ?></div>
      <div class="text-slate-500 text-xs">Customer</div><div><?= h($cust ?: '—') ?></div>
      <div class="text-slate-500 text-xs">Supplier</div><div><?= h($supp ?: '—') ?></div>
    </div>
    <div class="kv">
      <div class="text-slate-500 text-xs">Discount</div>
      <div><?= h($discType==='percent' ? (number_format($discVal,2).'%') : ('৳ '.number_format($discVal,2))) ?></div>
      <div class="text-slate-500 text-xs">Status</div><div><?= h(ucfirst($st)) ?></div>
      <?php if ($saleId > 0): ?>
      <div class="text-slate-500 text-xs">Invoice</div>
      <div><a class="text-emerald-700 hover:underline" href="<?= h($base.'/sales/'.$saleId) ?>">View Invoice</a></div>
      <?php endif; ?>
    </div>
  </div>
</div>

<!-- Items -->
<div class="rounded-2xl border overflow-hidden bg-white">
  <table class="min-w-full text-sm">
    <thead class="bg-slate-50">
      <tr>
        <th class="px-3 py-2 text-left">Product</th>
        <th class="px-3 py-2 text-right w-[12%]">Qty</th>
        <th class="px-3 py-2 text-right w-[16%]">Price</th>
        <th class="px-3 py-2 text-right w-[16%]">Line Total</th>
      </tr>
    </thead>
    <tbody>
      <?php if (!$lines): ?>
        <tr><td colspan="4" class="px-3 py-8 text-center text-slate-500">No items.</td></tr>
      <?php else: foreach ($lines as $ln):
        $pname = (string)($ln['product_name'] ?? '');
        $qty   = (float)($ln['qty'] ?? $ln['quantity'] ?? 0);
        $price = (float)($ln['unit_price'] ?? $ln['price'] ?? $ln['rate'] ?? 0);
        $lt    = $qty * $price;
      ?>
        <tr class="border-t">
          <td class="px-3 py-2"><?= h($pname) ?></td>
          <td class="px-3 py-2 text-right"><?= number_format($qty, 2) ?></td>
          <td class="px-3 py-2 text-right">৳ <?= number_format($price, 2) ?></td>
          <td class="px-3 py-2 text-right">৳ <?= number_format($lt, 2) ?></td>
        </tr>
      <?php endforeach; endif; ?>
    </tbody>
    <tfoot class="bg-slate-50">
      <tr>
        <td colspan="3" class="px-3 py-2 text-right font-medium">Subtotal</td>
        <td class="px-3 py-2 text-right font-semibold">৳ <?= number_format($subtotal, 2) ?></td>
      </tr>
      <tr>
        <td colspan="3" class="px-3 py-2 text-right">
          Discount <?= $discType==='percent' ? '('.number_format($discVal,2).'%)' : '' ?>
        </td>
        <td class="px-3 py-2 text-right">− ৳ <?= number_format($discount, 2) ?></td>
      </tr>
      <tr>
        <td colspan="3" class="px-3 py-3 text-right text-lg font-semibold">Grand Total</td>
        <td class="px-3 py-3 text-right text-lg font-bold">৳ <?= number_format($grand, 2) ?></td>
      </tr>
    </tfoot>
  </table>
</div>

<?php if (!empty($o['notes'])): ?>
  <div class="mt-4 rounded-2xl border p-4 bg-white">
    <div class="text-xs text-slate-500 mb-1">Notes</div>
    <div class="text-sm"><?= nl2br(h((string)$o['notes'])) ?></div>
  </div>
<?php endif; ?>

<!-- Actions -->
<div class="mt-4 flex flex-wrap items-center gap-2">
  <?php if ($canIssue || $canRebuild): ?>
    <form method="post" action="<?= h($base.'/orders/'.$id.'/issue-invoice') ?>"
          onsubmit="return confirm('<?= $canRebuild ? 'Rebuild invoice from this order?' : 'Issue invoice from this order?' ?>');">
      <?php if (!empty($csrf_token ?? ($_SESSION['_csrf'] ?? ''))): ?>
        <input type="hidden" name="_csrf" value="<?= h($csrf_token ?? $_SESSION['_csrf']) ?>">
      <?php endif; ?>
      <button type="submit" class="kfbtn kfbtn-brand">
        <i class="fa-solid fa-file-invoice"></i><?= $canRebuild ? 'Rebuild Invoice' : 'Issue Invoice' ?>
      </button>
    </form>
  <?php else: ?>
    <button type="button" class="kfbtn kfbtn-disabled" title="Invoice already issued/paid/cancelled or no items">
      <i class="fa-solid fa-file-invoice"></i> Issue Invoice
    </button>
  <?php endif; ?>

  <a href="<?= h($base.'/orders/'.$id.'/print?autoprint=1') ?>" target="_blank" rel="noopener"
     class="kfbtn kfbtn-brand">
    <i class="fa-solid fa-print"></i> Print
  </a>

  <!-- WhatsApp share -->
  <form method="get" action="<?= h($base.'/orders/'.$id.'/share/whatsapp') ?>" class="inline-flex items-center gap-2">
    <input type="tel" name="phone" placeholder="Phone (optional)"
           class="rounded-xl border px-3 py-2 text-sm" style="max-width:180px">
    <button type="submit" class="kfbtn kfbtn-wa">
      <i class="fa-brands fa-whatsapp"></i> WhatsApp
    </button>
  </form>

  <!-- Email share -->
  <button type="button" class="kfbtn kfbtn-brand" onclick="KF_emailModal.open()">
    <i class="fa-regular fa-envelope"></i> Email
  </button>
</div>

<!-- Email modal (unchanged API; Font Awesome only) -->
<div id="emailModal" class="fixed inset-0 z-50 hidden">
  <div class="absolute inset-0 bg-black/30" onclick="KF_emailModal.close()"></div>
  <div class="relative mx-auto mt-24 w-[92%] max-w-md rounded-2xl border bg-white p-4 shadow-xl">
    <div class="flex items-center justify-between mb-2">
      <div class="font-semibold">Share Order by Email</div>
      <button class="text-slate-500" onclick="KF_emailModal.close()"><i class="fa-solid fa-xmark"></i></button>
    </div>
    <form method="post" action="<?= h($base.'/orders/'.$id.'/share/email') ?>" id="emailForm">
      <?php if (!empty($csrf_token ?? ($_SESSION['_csrf'] ?? ''))): ?>
        <input type="hidden" name="_csrf" value="<?= h($csrf_token ?? $_SESSION['_csrf']) ?>">
      <?php endif; ?>
      <div class="mb-2">
        <label class="block text-xs text-slate-600 mb-1">To (email)</label>
        <input type="email" name="to" required class="w-full rounded-xl border px-3 py-2"
               placeholder="customer@example.com" value="<?= h((string)($o['customer_email'] ?? '')) ?>">
      </div>
      <div class="mb-3">
        <label class="block text-xs text-slate-600 mb-1">Cc (optional)</label>
        <input type="text" name="cc" class="w-full rounded-xl border px-3 py-2" placeholder="comma-separated">
      </div>
      <div class="flex items-center justify-end gap-2">
        <button type="button" class="kfbtn kfbtn-ghost" onclick="KF_emailModal.close()">Cancel</button>
        <button type="submit" class="kfbtn kfbtn-brand"><i class="fa-regular fa-paper-plane"></i> Send</button>
      </div>
    </form>
  </div>
</div>

<script>
const KF_emailModal = {
  el: document.getElementById('emailModal'),
  open(){ this.el.classList.remove('hidden'); setTimeout(()=>this.el.querySelector('input[name="to"]')?.focus(), 50); },
  close(){ this.el.classList.add('hidden'); }
};
</script>