<?php
declare(strict_types=1);
/** @var array $tot */
/** @var array $customers */
/** @var array $invoices */
/** @var array $names */
/** @var int   $customer_id */
/** @var string $module_base */

$h = fn($v)=>htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
$fmt = fn($n)=>number_format((float)$n, 2);
$brand = '#228B22';
?>
<div class="space-y-6">
  <div class="flex items-center justify-between">
    <div>
      <h1 class="text-2xl font-semibold">Accounts Receivable</h1>
      <p class="text-slate-500 text-sm">Totals and invoice-wise breakdown. Filter by customer to drill down.</p>
    </div>
    <form class="flex items-center gap-2" method="get" action="">
      <input type="hidden" name="r" value="">
      <input type="number" name="customer_id" value="<?= (int)$customer_id ?>" placeholder="Customer ID"
             class="border rounded-lg px-3 py-2 w-40" />
      <button class="px-3 py-2 rounded-lg text-white" style="background:<?= $brand ?>;">Apply</button>
      <a href="<?= $h($module_base) ?>/ar" class="px-3 py-2 rounded-lg border">Reset</a>
    </form>
  </div>

  <!-- KPIs -->
  <div class="grid grid-cols-1 sm:grid-cols-4 gap-4">
    <div class="rounded-xl border p-4 bg-slate-50">
      <div class="text-xs uppercase text-slate-500">Invoiced</div>
      <div class="text-2xl font-semibold">৳ <?= $fmt($tot['invoiced'] ?? 0) ?></div>
    </div>
    <div class="rounded-xl border p-4 bg-slate-50">
      <div class="text-xs uppercase text-slate-500">Collected</div>
      <div class="text-2xl font-semibold">৳ <?= $fmt($tot['collected'] ?? 0) ?></div>
    </div>
    <div class="rounded-xl border p-4 bg-slate-50">
      <div class="text-xs uppercase text-slate-500">Total Due</div>
      <div class="text-2xl font-semibold text-rose-600">৳ <?= $fmt($tot['due'] ?? 0) ?></div>
    </div>
    <div class="rounded-xl border p-4 bg-slate-50">
      <div class="text-xs uppercase text-slate-500">Open Invoices</div>
      <div class="text-2xl font-semibold"><?= number_format((int)($tot['open_count'] ?? 0)) ?></div>
    </div>
  </div>

  <!-- Customer rollup -->
  <div class="rounded-xl border">
    <div class="px-4 py-3 font-medium">Customer Balances</div>
    <div class="overflow-x-auto">
      <table class="min-w-full text-sm">
        <thead class="bg-slate-50">
          <tr class="text-left text-slate-600">
            <th class="px-3 py-2">Customer</th>
            <th class="px-3 py-2 text-right">Invoiced</th>
            <th class="px-3 py-2 text-right">Collected</th>
            <th class="px-3 py-2 text-right">Due</th>
            <th class="px-3 py-2 text-right">Open Invoices</th>
          </tr>
        </thead>
        <tbody>
          <?php if (!$customers): ?>
            <tr><td colspan="5" class="px-3 py-6 text-center text-slate-500">No data yet.</td></tr>
          <?php else: foreach ($customers as $r): ?>
            <tr class="border-t">
              <td class="px-3 py-2">
                <?php
                  $cid = (int)$r['customer_id'];
                  $nm  = $names[$cid] ?? ('Customer #'.$cid);
                ?>
                <a class="text-emerald-700 hover:underline" href="?customer_id=<?= $cid ?>"><?= $h($nm) ?></a>
              </td>
              <td class="px-3 py-2 text-right">৳ <?= $fmt($r['invoiced']) ?></td>
              <td class="px-3 py-2 text-right">৳ <?= $fmt($r['collected']) ?></td>
              <td class="px-3 py-2 text-right font-semibold <?= ((float)$r['due_amount']>0?'text-rose-600':'') ?>">
                ৳ <?= $fmt($r['due_amount']) ?>
              </td>
              <td class="px-3 py-2 text-right"><?= (int)$r['open_invoices'] ?></td>
            </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- Invoice breakdown -->
  <div class="rounded-xl border">
    <div class="px-4 py-3 font-medium">Invoice Breakdown<?= $customer_id? ' · Customer #'.(int)$customer_id:'' ?></div>
    <div class="overflow-x-auto">
      <table class="min-w-full text-sm">
        <thead class="bg-slate-50">
          <tr class="text-left text-slate-600">
            <th class="px-3 py-2">Invoice #</th>
            <th class="px-3 py-2">Customer</th>
            <th class="px-3 py-2 text-right">Amount</th>
            <th class="px-3 py-2 text-right">Paid</th>
            <th class="px-3 py-2 text-right">Due</th>
            <th class="px-3 py-2"></th>
          </tr>
        </thead>
        <tbody>
          <?php if (!$invoices): ?>
            <tr><td colspan="6" class="px-3 py-6 text-center text-slate-500">No open invoices.</td></tr>
          <?php else: foreach ($invoices as $r): ?>
            <?php $cid = (int)$r['customer_id']; ?>
            <tr class="border-t">
              <td class="px-3 py-2 font-medium">INV-<?= (int)$r['invoice_id'] ?></td>
              <td class="px-3 py-2"><?= $h($names[$cid] ?? ('#'.$cid)) ?></td>
              <td class="px-3 py-2 text-right">৳ <?= $fmt($r['invoice_amount']) ?></td>
              <td class="px-3 py-2 text-right">৳ <?= $fmt($r['paid_amount']) ?></td>
              <td class="px-3 py-2 text-right font-semibold <?= ((float)$r['due_amount']>0?'text-rose-600':'') ?>">
                ৳ <?= $fmt($r['due_amount']) ?>
              </td>
              <td class="px-3 py-2 text-right">
                <a href="<?= $h($module_base) ?>/sales/invoices/<?= (int)$r['invoice_id'] ?>" class="text-emerald-700 hover:underline">View</a>
              </td>
            </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>