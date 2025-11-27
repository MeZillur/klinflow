<?php
/** @var array|null $supplier */
/** @var array|null $dealer */
/** @var array       $stats */
/** @var array       $recentPurchases */
/** @var array       $recentProducts */
/** @var array       $recent_purch */
/** @var array       $recent_bills */
/** @var array       $products */
/** @var string      $module_base */

$h = fn($v)=>htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');

/* Back-compat mappings */
if (!isset($supplier) && isset($dealer))           $supplier = $dealer;
if (!isset($recentPurchases) && isset($recent_purch)) $recentPurchases = $recent_purch;
if (!isset($recentPurchases) && isset($recent_bills)) $recentPurchases = $recent_bills;
if (!isset($recentProducts)  && isset($products))     $recentProducts  = $products;

$s = $supplier ?? null;
if (!$s): ?>
  <div class="p-6">
    <h1 class="text-xl font-semibold mb-3 text-slate-900 dark:text-slate-100">Supplier not found</h1>
    <a href="<?= $h($module_base) ?>/suppliers"
       class="inline-flex items-center gap-2 px-3 py-2 rounded-lg bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-800 dark:text-slate-200">
      <i class="fa-solid fa-arrow-left-long"></i> Back to Suppliers
    </a>
  </div>
<?php return; endif;

/* Derivations */
$id      = (int)($s['id'] ?? 0);
$name    = $s['name'] ?? '';
$code    = $s['code'] ?? '';
$status  = strtolower((string)($s['status'] ?? 'active')) === 'inactive' ? 'inactive' : 'active';
$phone   = $s['phone'] ?? '';
$email   = $s['email'] ?? '';
$addr    = $s['address'] ?? '';
$opening = (float)($s['opening_balance'] ?? 0);

/* Normalize arrays */
$recentPurchases = is_array($recentPurchases ?? null) ? $recentPurchases : [];
$recentProducts  = is_array($recentProducts  ?? null) ? $recentProducts  : [];

/* Stats with safe fallbacks/derivations */
$st = [
  'purchases_count' => (int)($stats['purchases_count'] ?? count($recentPurchases)),
  'purchases_total' => (float)($stats['purchases_total'] ?? array_reduce($recentPurchases, fn($s,$p)=>$s+(float)($p['amount']??0), 0)),
  'last_purchase_at'=> $stats['last_purchase_at'] ?? (function() use ($recentPurchases){
                        $d = $recentPurchases[0]['bill_date'] ?? null;
                        return $d ? substr((string)$d,0,19) : null;
                      })(),
  'products_count'  => (int)($stats['products_count'] ?? count($recentProducts)),
];
?>
<div class="p-6 space-y-6 text-slate-900 dark:text-slate-100" x-data="{ tab: 'purchases' }">
  <!-- Header -->
  <div class="flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
    <div>
      <div class="flex items-center gap-2 flex-wrap">
        <h1 class="text-2xl font-semibold"><?= $h($name) ?></h1>
        <?php if ($code): ?>
          <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-lg text-[12px]
                       bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300">
            <i class="fa-regular fa-id-badge"></i> <?= $h($code) ?>
          </span>
        <?php endif; ?>
        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[12px] font-semibold
                     <?= $status==='active'
                          ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300'
                          : 'bg-slate-200 text-slate-700 dark:bg-slate-700 dark:text-slate-200' ?>">
          <i class="fa-solid fa-circle text-[8px]"></i> <?= $h($status) ?>
        </span>
      </div>

      <div class="mt-2 text-sm text-slate-600 dark:text-slate-300 space-x-3">
        <?php if ($phone): ?>
          <a class="hover:underline" href="tel:<?= $h($phone) ?>"><i class="fa-solid fa-phone"></i> <?= $h($phone) ?></a>
        <?php endif; ?>
        <?php if ($email): ?>
          <a class="hover:underline" href="mailto:<?= $h($email) ?>"><i class="fa-regular fa-envelope"></i> <?= $h($email) ?></a>
        <?php endif; ?>
      </div>
      <?php if ($addr): ?>
        <div class="mt-1 text-sm text-slate-500 dark:text-slate-400 max-w-2xl"><?= nl2br($h($addr)) ?></div>
      <?php endif; ?>
    </div>

    <div class="flex items-center gap-2">
      <a href="<?= $h($module_base) ?>/suppliers"
         class="inline-flex items-center gap-2 px-3 py-2 rounded-lg border border-slate-200 dark:border-slate-700 hover:bg-slate-50 dark:hover:bg-slate-800">
        <i class="fa-solid fa-arrow-left-long"></i> Back
      </a>
      <a href="<?= $h($module_base) ?>/suppliers/<?= $id ?>/edit"
         class="inline-flex items-center gap-2 px-3 py-2 rounded-lg bg-slate-900 text-white hover:bg-slate-800 dark:bg-slate-700 dark:hover:bg-slate-600">
        <i class="fa-regular fa-pen-to-square"></i> Edit
      </a>
      <a href="<?= $h($module_base) ?>/purchases/create?supplier_id=<?= $id ?>"
         class="inline-flex items-center gap-2 px-3 py-2 rounded-lg bg-emerald-600 text-white hover:bg-emerald-700">
        <i class="fa-solid fa-file-circle-plus"></i> New Purchase
      </a>
    </div>
  </div>

  <!-- KPI Cards -->
  <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
    <div class="rounded-2xl border border-slate-200 dark:border-slate-700 p-4 bg-white dark:bg-slate-900">
      <div class="text-xs text-slate-500 dark:text-slate-400">Total Purchases</div>
      <div class="mt-1 text-2xl font-semibold"><?= (int)$st['purchases_count'] ?></div>
    </div>
    <div class="rounded-2xl border border-slate-200 dark:border-slate-700 p-4 bg-white dark:bg-slate-900">
  <div class="text-xs text-slate-500 dark:text-slate-400">Total Purchased Amount</div>
  <?php
    // Combine actual purchases + opening balance for clarity (if you wish)
    $grandTotal = (float)($st['purchases_total'] ?? 0);
    // Optional: include opening balance
    // $grandTotal += (float)($opening ?? 0);
  ?>
  <div class="mt-1 text-2xl font-semibold text-slate-900 dark:text-slate-100">
    ৳ <?= number_format($grandTotal, 2) ?>
  </div>
  <?php if ($grandTotal > 0): ?>
    <div class="text-[13px] text-slate-500 dark:text-slate-400 mt-0.5">
      (excluding Opening Balance)
    </div>
  <?php endif; ?>
</div>
    <div class="rounded-2xl border border-slate-200 dark:border-slate-700 p-4 bg-white dark:bg-slate-900">
      <div class="text-xs text-slate-500 dark:text-slate-400">Products Supplied</div>
      <div class="mt-1 text-2xl font-semibold"><?= (int)$st['products_count'] ?></div>
    </div>
    <div class="rounded-2xl border border-slate-200 dark:border-slate-700 p-4 bg-white dark:bg-slate-900">
      <div class="text-xs text-slate-500 dark:text-slate-400">Opening Balance</div>
      <div class="mt-1 text-2xl font-semibold">৳ <?= number_format($opening,2) ?></div>
    </div>
  </div>

  <!-- Tabs -->
  <div class="rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900">
    <div class="flex items-center gap-2 p-2 border-b border-slate-200 dark:border-slate-700">
      <button @click="tab='purchases'"
              :class="tab==='purchases' ? 'bg-emerald-600 text-white' : 'bg-transparent hover:bg-slate-100 dark:hover:bg-slate-800'"
              class="px-3 py-1.5 rounded-lg text-sm font-semibold">
        <i class="fa-solid fa-file-invoice-dollar mr-1"></i> Purchases
      </button>
      <button @click="tab='products'"
              :class="tab==='products' ? 'bg-emerald-600 text-white' : 'bg-transparent hover:bg-slate-100 dark:hover:bg-slate-800'"
              class="px-3 py-1.5 rounded-lg text-sm font-semibold">
        <i class="fa-solid fa-box-open mr-1"></i> Products
      </button>
      <button @click="tab='about'"
              :class="tab==='about' ? 'bg-emerald-600 text-white' : 'bg-transparent hover:bg-slate-100 dark:hover:bg-slate-800'"
              class="px-3 py-1.5 rounded-lg text-sm font-semibold">
        <i class="fa-regular fa-id-card mr-1"></i> About
      </button>
    </div>

    <!-- Purchases -->
    <div x-show="tab==='purchases'" class="p-4" x-cloak>
      <div class="flex items-center justify-between mb-2">
        <div class="text-sm text-slate-600 dark:text-slate-300">Recent Purchases</div>
        <a class="text-emerald-700 dark:text-emerald-400 hover:underline text-sm"
           href="<?= $h($module_base) ?>/purchases?supplier_id=<?= $id ?>">View all</a>
      </div>
      <div class="overflow-x-auto rounded-xl border border-slate-200 dark:border-slate-700">
        <table class="min-w-full text-sm">
          <thead class="bg-slate-50 dark:bg-slate-800/60 text-slate-600 dark:text-slate-300">
            <tr>
              <th class="px-3 py-2 text-left">Bill #</th>
              <th class="px-3 py-2 text-left">Date</th>
              <th class="px-3 py-2 text-left">Status</th>
              <th class="px-3 py-2 text-right">Amount</th>
              <th class="px-3 py-2 text-right"></th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-200 dark:divide-slate-700">
            <?php if (!$recentPurchases): ?>
              <tr><td colspan="5" class="px-3 py-6 text-center text-slate-500 dark:text-slate-400">No purchases yet.</td></tr>
            <?php else: foreach ($recentPurchases as $p): ?>
              <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/40">
                <td class="px-3 py-2 font-mono"><?= $h($p['bill_no'] ?? ('#'.$p['id'])) ?></td>
                <td class="px-3 py-2"><?= $h(substr((string)($p['bill_date'] ?? ''),0,10) ?: '—') ?></td>
                <td class="px-3 py-2">
                  <?php $pst = strtolower((string)($p['status'] ?? '')); ?>
                  <?php if ($pst): ?>
                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold
                                 <?= in_array($pst,['confirmed','posted','paid'],true)
                                        ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300'
                                        : 'bg-slate-200 text-slate-700 dark:bg-slate-700 dark:text-slate-200' ?>">
                      <?= $h(ucfirst($pst)) ?>
                    </span>
                  <?php else: ?>—<?php endif; ?>
                </td>
                <td class="px-3 py-2 text-right font-mono">৳ <?= number_format((float)($p['amount'] ?? 0), 2) ?></td>
                <td class="px-3 py-2 text-right">
                  <a class="text-emerald-700 dark:text-emerald-400 hover:underline"
                     href="<?= $h($module_base) ?>/purchases/<?= (int)$p['id'] ?>">View</a>
                </td>
              </tr>
            <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Products (aggregated from purchases) -->
    <div x-show="tab==='products'" class="p-4" x-cloak>
      <div class="text-sm text-slate-600 dark:text-slate-300 mb-2">Products from this supplier</div>
      <div class="overflow-x-auto rounded-xl border border-slate-200 dark:border-slate-700">
        <table class="min-w-full text-sm">
          <thead class="bg-slate-50 dark:bg-slate-800/60 text-slate-600 dark:text-slate-300">
            <tr>
              <th class="px-3 py-2 text-left">Product</th>
              <th class="px-3 py-2 text-right">Total Qty</th>
              <th class="px-3 py-2 text-right">Total Amount</th>
              <th class="px-3 py-2 text-right"></th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-200 dark:divide-slate-700">
            <?php if (!$recentProducts): ?>
              <tr><td colspan="4" class="px-3 py-6 text-center text-slate-500 dark:text-slate-400">No products yet.</td></tr>
            <?php else: foreach ($recentProducts as $pr): ?>
              <?php
                // Controller may send: product_id, product_name, total_qty, total_amount
                $pid   = (int)($pr['product_id'] ?? ($pr['id'] ?? 0));
                $pname = $pr['product_name'] ?? $pr['name'] ?? '';
                $tqty  = (float)($pr['total_qty'] ?? $pr['qty'] ?? 0);
                $tamt  = (float)($pr['total_amount'] ?? $pr['amount'] ?? 0);
              ?>
              <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/40">
                <td class="px-3 py-2"><?= $h($pname) ?></td>
                <td class="px-3 py-2 text-right font-mono"><?= number_format($tqty, 2) ?></td>
                <td class="px-3 py-2 text-right font-mono">৳ <?= number_format($tamt, 2) ?></td>
                <td class="px-3 py-2 text-right">
                  <?php if ($pid): ?>
                  <a class="text-emerald-700 dark:text-emerald-400 hover:underline"
                     href="<?= $h($module_base) ?>/products/<?= $pid ?>">View</a>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>
    </div>

    <!-- About -->
    <div x-show="tab==='about'" class="p-4" x-cloak>
      <div class="grid md:grid-cols-2 gap-4">
        <div class="rounded-xl border border-slate-200 dark:border-slate-700 p-4">
          <div class="text-sm text-slate-500 dark:text-slate-400 mb-2">Contact</div>
          <div class="space-y-2">
            <div class="flex items-center justify-between">
              <span>Phone</span>
              <div class="flex items-center gap-2">
                <a href="tel:<?= $h($phone) ?>" class="text-sky-600 dark:text-sky-400 hover:underline"><?= $h($phone ?: '—') ?></a>
                <?php if ($phone): ?>
                <button type="button" class="px-2 py-1 rounded border border-slate-200 dark:border-slate-700 text-xs"
                        onclick="navigator.clipboard.writeText('<?= $h($phone) ?>')">Copy</button>
                <?php endif; ?>
              </div>
            </div>
            <div class="flex items-center justify-between">
              <span>Email</span>
              <div class="flex items-center gap-2">
                <a href="mailto:<?= $h($email) ?>" class="text-sky-600 dark:text-sky-400 hover:underline"><?= $h($email ?: '—') ?></a>
                <?php if ($email): ?>
                <button type="button" class="px-2 py-1 rounded border border-slate-200 dark:border-slate-700 text-xs"
                        onclick="navigator.clipboard.writeText('<?= $h($email) ?>')">Copy</button>
                <?php endif; ?>
              </div>
            </div>
            <div>
              <div class="text-sm text-slate-500 dark:text-slate-400">Address</div>
              <div class="mt-1"><?= $addr ? nl2br($h($addr)) : '<span class="text-slate-400">—</span>' ?></div>
            </div>
          </div>
        </div>
        <div class="rounded-xl border border-slate-200 dark:border-slate-700 p-4">
          <div class="text-sm text-slate-500 dark:text-slate-400 mb-2">Meta</div>
          <div class="space-y-2">
            <div class="flex items-center justify-between">
              <span>Supplier Code</span>
              <span class="font-mono"><?= $h($code ?: '—') ?></span>
            </div>
            <div class="flex items-center justify-between">
              <span>Last Purchase</span>
              <span><?= $h($st['last_purchase_at'] ?: '—') ?></span>
            </div>
          </div>
        </div>
      </div>
    </div>

  </div>
</div>