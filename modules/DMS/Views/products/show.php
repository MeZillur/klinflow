<?php
declare(strict_types=1);
/** DMS · Products → Show
 *
 * Expects (controller can supply any/all; view degrades gracefully):
 *   - $product       array row from dms_products (merged with joins if available)
 *   - $moves         optional array of stock movements (newest first)
 *   - $purchases     optional array of recent purchases (newest first)
 *   - $recent_tiers  optional array of tiers (newest first)
 *   - $stock_qty     optional numeric stock from balance
 *   - $module_base   string
 *   - $org           array
 */

$org         = $org ?? [];
$module_base = $module_base ?? ('/t/'.rawurlencode($org['slug'] ?? '').'/apps/dms');
$h  = fn($v)=>htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
$n  = fn($v,$d=2)=>number_format((float)($v ?? 0), $d);
$dt = function(?string $s) use($h){ if(!$s) return '—'; $t=strtotime($s); return $t?date('Y-m-d H:i', $t):$h($s); };

$p   = is_array($product ?? null)?$product:[];
$mov = is_array($moves ?? null)?$moves:[];
$pur = is_array($purchases ?? null)?$purchases:[];
$tiers = is_array($recent_tiers ?? null)?$recent_tiers:[];

/* ---------- Robust field extraction ---------- */
$id    = (int)($p['id'] ?? 0);
$name  = (string)($p['name'] ?? $p['name_canonical'] ?? 'Product');
$sku   = (string)($p['sku'] ?? $p['code'] ?? $p['product_code'] ?? '');
$code  = (string)($p['code'] ?? $p['product_code'] ?? '');
$brand = (string)($p['brand'] ?? '');
$model = (string)($p['model'] ?? '');
$barcode = (string)($p['barcode'] ?? $p['ean'] ?? $p['upc'] ?? '');
$cat   = (string)($p['category_name'] ?? $p['category'] ?? '');
$uom   = (string)($p['uom_name'] ?? $p['uom'] ?? $p['unit'] ?? '');
$status= strtolower((string)($p['status'] ?? (($p['active']??1)?'active':'inactive')));
$status= ($status==='inactive')?'inactive':'active';

$supplierName = (string)($p['supplier_name'] ?? '');
$supplierCode = (string)($p['supplier_code'] ?? '');
$priceNow     = $p['price'] ?? $p['unit_price'] ?? null;

/* Upcoming tier (if controller provides) */
$nextTier = $p['next_tier'] ?? null;

/* Lifecycle */
$arrival = $p['arrival_date'] ?? null;
$expiry  = $p['expiry_date']  ?? null;
$mfg     = $p['mfg_date']     ?? null;

$ageDays = null;
if ($arrival && ($ta=strtotime($arrival))) $ageDays = max(0, (int)floor((time()-$ta)/86400));
$daysLeft=null;
if ($expiry && ($te=strtotime($expiry))) $daysLeft=(int)ceil(($te-time())/86400);

/* Stock qty: prefer param then common fields */
$qty = null;
foreach ([
  $stock_qty ?? null,
  $p['stock_qty'] ?? null,
  $p['qty_on_hand'] ?? null,
  $p['on_hand'] ?? null,
  $p['current_stock'] ?? null
] as $cand) {
  if ($cand === 0 || $cand === '0' || ($cand !== null && $cand !== '')) { $qty=(float)$cand; break; }
}
$qty = $qty ?? 0.0;

/* Spec JSON (pretty) */
$specRaw = (string)($p['spec_json'] ?? $p['attributes'] ?? '');
$specPretty = $specRaw;
if ($specRaw !== '') {
  $dec = json_decode($specRaw, true);
  if (json_last_error() === JSON_ERROR_NONE) $specPretty = json_encode($dec, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE);
}
?>
<!-- brand edge -->
<div class="h-1 w-full bg-emerald-600 dark:bg-emerald-500 -mt-4 mb-4"></div>

<div class="max-w-7xl mx-auto px-4 py-6 space-y-4">
  <!-- Header -->
  <div class="flex items-start justify-between gap-3 flex-wrap">
    <div>
      <h1 class="text-2xl font-semibold text-emerald-700 dark:text-emerald-300"><?= $h($name) ?></h1>
      <div class="mt-1 text-sm text-gray-500 dark:text-gray-400 flex items-center gap-3">
        <span>SKU: <?= $sku? $h($sku) : '—' ?></span>
        <span class="text-gray-300">•</span>
        <span>Code: <?= $code? $h($code) : '—' ?></span>
        <span class="text-gray-300">•</span>
        <span>Status:
          <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs
            <?= $status==='active'
                  ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-900/20 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800'
                  : 'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-300 border border-gray-200 dark:border-gray-700' ?>">
            <?= $h($status) ?>
          </span>
        </span>
      </div>
    </div>
    <div class="flex items-center gap-2">
      <a href="<?= $h($module_base.'/products/'.$id.'/edit') ?>"
         class="px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-900 hover:bg-gray-50 dark:hover:bg-gray-800">Edit</a>
      <a href="<?= $h($module_base.'/products/'.$id.'/tiers') ?>"
         class="px-3 py-2 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white">Manage Tiers</a>
    </div>
  </div>

  <!-- Overview cards -->
  <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
    <!-- Details -->
    <section class="rounded-xl bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 p-5">
      <div class="flex items-center justify-between">
        <h3 class="font-semibold text-emerald-700 dark:text-emerald-300">Details</h3>
      </div>
      <dl class="mt-3 text-sm space-y-2">
        <div class="flex justify-between"><dt class="text-gray-500">Category</dt><dd><?= $cat? $h($cat):'—' ?></dd></div>
        <div class="flex justify-between"><dt class="text-gray-500">UOM</dt><dd><?= $uom? $h($uom):'—' ?></dd></div>
        <div class="flex justify-between"><dt class="text-gray-500">Brand / Model</dt><dd><?= ($brand||$model)? $h(trim($brand.' '.$model)):'—' ?></dd></div>
        <div class="flex justify-between">
          <dt class="text-gray-500">Barcode</dt>
          <dd class="flex items-center gap-2">
            <span><?= $barcode? $h($barcode) : '—' ?></span>
            <?php if ($barcode): ?><button type="button" class="px-2 py-0.5 text-xs rounded border border-gray-300 dark:border-gray-700" data-copy="<?= $h($barcode) ?>">Copy</button><?php endif; ?>
          </dd>
        </div>
        <div class="flex justify-between"><dt class="text-gray-500">Supplier</dt><dd><?= $supplierName? $h($supplierName):'—' ?></dd></div>
        <?php if ($supplierCode): ?>
        <div class="flex justify-between"><dt class="text-gray-500">Supplier Code</dt><dd><?= $h($supplierCode) ?></dd></div>
        <?php endif; ?>
      </dl>
    </section>

    <!-- Stock + Quick actions -->
    <section class="rounded-xl bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 p-5">
      <h3 class="font-semibold text-emerald-700 dark:text-emerald-300">Stock</h3>
      <div class="mt-2 text-4xl font-semibold"><?= $n($qty) ?></div>
      <div class="mt-4 text-sm text-gray-900 dark:text-gray-100 font-medium">Quick Actions</div>
      <div class="mt-2 flex flex-wrap gap-2">
        <a href="<?= $h($module_base.'/purchases?product_id='.$id) ?>" class="px-3 py-1.5 rounded-lg border border-gray-300 dark:border-gray-700">View Purchases</a>
        <a href="<?= $h($module_base.'/inventory/adjust?product_id='.$id) ?>" class="px-3 py-1.5 rounded-lg border border-gray-300 dark:border-gray-700">Adjust Stock</a>
        <a href="<?= $h($module_base.'/inventory/damage?product_id='.$id) ?>" class="px-3 py-1.5 rounded-lg border border-gray-300 dark:border-gray-700">Damage Entry</a>
      </div>
      <div class="mt-4 grid grid-cols-3 gap-3 text-xs text-gray-500 dark:text-gray-400">
        <div>
          <div class="text-gray-400">Arrival</div>
          <div class="text-gray-900 dark:text-gray-100"><?= $dt($arrival) ?></div>
        </div>
        <div>
          <div class="text-gray-400">Expiry</div>
          <div class="text-gray-900 dark:text-gray-100"><?= $dt($expiry) ?></div>
        </div>
        <div>
          <div class="text-gray-400">Age</div>
          <div class="text-gray-900 dark:text-gray-100"><?= $ageDays !== null ? $ageDays.'d' : '—' ?></div>
        </div>
      </div>
    </section>

    <!-- Pricing -->
    <section class="rounded-xl bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 p-5">
      <h3 class="font-semibold text-emerald-700 dark:text-emerald-300">Pricing</h3>
      <dl class="mt-2 text-sm space-y-2">
        <div class="flex justify-between">
          <dt class="text-gray-500">Current price (BDT)</dt>
          <dd class="font-semibold"><?= $priceNow!==null ? $n($priceNow,2) : '—' ?></dd>
        </div>
        <?php if (is_array($nextTier ?? null)): ?>
          <div class="flex justify-between">
            <dt class="text-gray-500">Upcoming (from <?= $h((string)$nextTier['effective_from']) ?>)</dt>
            <dd><?= $n($nextTier['final_price'] ?? $nextTier['base_price'] ?? 0, 2) ?></dd>
          </div>
        <?php endif; ?>
      </dl>
      <p class="text-xs text-gray-500 mt-2">Prices are tiered (BDT / default / default). Use “Manage Tiers” to schedule changes.</p>
    </section>
  </div>

  <!-- Tabs -->
  <div class="rounded-xl border border-gray-200 dark:border-gray-700 overflow-hidden">
    <div class="sticky top-0 bg-white dark:bg-gray-900 border-b border-gray-200 dark:border-gray-700">
      <nav class="flex gap-1 p-2">
        <button class="tab px-3 py-2 rounded-lg text-sm bg-emerald-600 text-white" data-tab="overview">Overview</button>
        <button class="tab px-3 py-2 rounded-lg text-sm" data-tab="purchases">Purchases</button>
        <button class="tab px-3 py-2 rounded-lg text-sm" data-tab="movements">Movements</button>
        <button class="tab px-3 py-2 rounded-lg text-sm" data-tab="pricing">Pricing</button>
        <button class="tab px-3 py-2 rounded-lg text-sm" data-tab="spec">Specification</button>
      </nav>
    </div>

    <!-- OVERVIEW: recent movements (top 5) -->
    <section id="tab-overview" class="p-4">
      <h4 class="font-medium mb-2">Recent Stock Movements</h4>
      <?php
        $recent = array_slice($mov, 0, 5);
      ?>
      <?php if (!$recent): ?>
        <div class="text-sm text-gray-500 py-6 text-center">No movement records yet.</div>
      <?php else: ?>
        <div class="overflow-auto">
          <table class="min-w-full text-sm">
            <thead class="bg-gray-50 dark:bg-gray-800">
              <tr class="text-left text-gray-500 dark:text-gray-400">
                <th class="px-3 py-2">Date</th>
                <th class="px-3 py-2">Type</th>
                <th class="px-3 py-2 text-right">In</th>
                <th class="px-3 py-2 text-right">Out</th>
                <th class="px-3 py-2">Note</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-200 dark:divide-gray-800">
              <?php foreach ($recent as $m): ?>
                <tr>
                  <td class="px-3 py-2"><?= $dt($m['created_at'] ?? $m['date'] ?? '') ?></td>
                  <td class="px-3 py-2"><?= $h(ucfirst((string)($m['move_type'] ?? ''))) ?></td>
                  <td class="px-3 py-2 text-right"><?= ($m['in_qty'] ?? 0) ? $n($m['in_qty']) : '—' ?></td>
                  <td class="px-3 py-2 text-right"><?= ($m['out_qty'] ?? 0) ? $n($m['out_qty']) : '—' ?></td>
                  <td class="px-3 py-2"><?= $h($m['note'] ?? '') ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </section>

    <!-- PURCHASES -->
    <section id="tab-purchases" class="hidden p-4">
      <div class="flex items-center justify-between mb-2">
        <h4 class="font-medium">Purchases</h4>
        <?php if ($pur): ?>
          <button class="px-2 py-1 text-xs rounded border border-gray-300 dark:border-gray-700" data-export="purchases">Export CSV</button>
        <?php endif; ?>
      </div>
      <?php if (!$pur): ?>
        <div class="text-sm text-gray-500 py-6 text-center">No purchases yet.</div>
      <?php else: ?>
        <div class="overflow-auto">
          <table class="min-w-full text-sm">
            <thead class="bg-gray-50 dark:bg-gray-800">
              <tr class="text-left text-gray-500 dark:text-gray-400">
                <th class="px-3 py-2">Date</th>
                <th class="px-3 py-2">Supplier</th>
                <th class="px-3 py-2 text-right">Qty</th>
                <th class="px-3 py-2 text-right">Unit Cost</th>
                <th class="px-3 py-2 text-right">Total</th>
                <th class="px-3 py-2">Ref</th>
                <th class="px-3 py-2">Note</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-200 dark:divide-gray-800">
              <?php foreach ($pur as $r): ?>
                <tr>
                  <td class="px-3 py-2"><?= $dt($r['created_at'] ?? $r['date'] ?? '') ?></td>
                  <td class="px-3 py-2"><?= $h($r['supplier_name'] ?? '') ?></td>
                  <td class="px-3 py-2 text-right"><?= $n($r['qty'] ?? $r['in_qty'] ?? 0) ?></td>
                  <td class="px-3 py-2 text-right"><?= $n($r['unit_cost'] ?? $r['cost'] ?? 0, 2) ?></td>
                  <td class="px-3 py-2 text-right"><?= $n(($r['unit_cost'] ?? 0) * ($r['qty'] ?? 0), 2) ?></td>
                  <td class="px-3 py-2"><?= $h($r['ref_no'] ?? $r['invoice_no'] ?? '') ?></td>
                  <td class="px-3 py-2"><?= $h($r['note'] ?? '') ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </section>

    <!-- MOVEMENTS -->
    <section id="tab-movements" class="hidden p-4">
      <div class="flex items-center justify-between mb-2">
        <h4 class="font-medium">All Movements</h4>
        <?php if ($mov): ?>
          <button class="px-2 py-1 text-xs rounded border border-gray-300 dark:border-gray-700" data-export="movements">Export CSV</button>
        <?php endif; ?>
      </div>
      <?php if (!$mov): ?>
        <div class="text-sm text-gray-500 py-6 text-center">No movement records yet.</div>
      <?php else: ?>
        <div class="overflow-auto">
          <table class="min-w-full text-sm">
            <thead class="bg-gray-50 dark:bg-gray-800">
              <tr class="text-left text-gray-500 dark:text-gray-400">
                <th class="px-3 py-2">Date</th>
                <th class="px-3 py-2">Type</th>
                <th class="px-3 py-2 text-right">In</th>
                <th class="px-3 py-2 text-right">Out</th>
                <th class="px-3 py-2">Ref</th>
                <th class="px-3 py-2">Note</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-200 dark:divide-gray-800">
              <?php foreach ($mov as $m): ?>
                <tr>
                  <td class="px-3 py-2"><?= $dt($m['created_at'] ?? $m['date'] ?? '') ?></td>
                  <td class="px-3 py-2"><?= $h(ucfirst((string)($m['move_type'] ?? ''))) ?></td>
                  <td class="px-3 py-2 text-right"><?= ($m['in_qty'] ?? 0) ? $n($m['in_qty']) : '—' ?></td>
                  <td class="px-3 py-2 text-right"><?= ($m['out_qty'] ?? 0) ? $n($m['out_qty']) : '—' ?></td>
                  <td class="px-3 py-2"><?= $h($m['ref_no'] ?? $m['ref'] ?? '') ?></td>
                  <td class="px-3 py-2"><?= $h($m['note'] ?? '') ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </section>

    <!-- PRICING (tiers) -->
    <section id="tab-pricing" class="hidden p-4">
      <div class="flex items-center justify-between mb-2">
        <h4 class="font-medium">Price Tiers</h4>
        <a href="<?= $h($module_base.'/products/'.$id.'/tiers') ?>" class="px-2 py-1 text-xs rounded border border-gray-300 dark:border-gray-700">Open Tier Manager</a>
      </div>
      <?php if (!$tiers): ?>
        <div class="text-sm text-gray-500 py-6 text-center">No tiers yet.</div>
      <?php else: ?>
        <div class="overflow-auto">
          <table class="min-w-full text-sm">
            <thead class="bg-gray-50 dark:bg-gray-800">
              <tr class="text-left text-gray-500 dark:text-gray-400">
                <th class="px-3 py-2">State</th>
                <th class="px-3 py-2">Effective From</th>
                <th class="px-3 py-2">Min Qty</th>
                <th class="px-3 py-2 text-right">Base (BDT)</th>
                <th class="px-3 py-2 text-right">Discount %</th>
                <th class="px-3 py-2 text-right">Commission %</th>
                <th class="px-3 py-2">Tax Included</th>
                <th class="px-3 py-2">Priority</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-200 dark:divide-gray-800">
              <?php foreach ($tiers as $t): ?>
                <tr>
                  <td class="px-3 py-2"><?= $h($t['state'] ?? '') ?></td>
                  <td class="px-3 py-2"><?= $dt($t['effective_from'] ?? null) ?></td>
                  <td class="px-3 py-2"><?= (int)($t['min_qty'] ?? 1) ?></td>
                  <td class="px-3 py-2 text-right"><?= $n($t['base_price'] ?? 0, 2) ?></td>
                  <td class="px-3 py-2 text-right"><?= $n($t['discount_pct'] ?? 0, 2) ?></td>
                  <td class="px-3 py-2 text-right"><?= $n($t['commission_pct'] ?? 0, 2) ?></td>
                  <td class="px-3 py-2"><?= !empty($t['tax_included']) ? 'Yes' : 'No' ?></td>
                  <td class="px-3 py-2"><?= (int)($t['priority'] ?? 0) ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </section>

    <!-- SPEC -->
    <section id="tab-spec" class="hidden p-4">
      <?php if (!$specRaw): ?>
        <div class="text-sm text-gray-500 py-6 text-center">No specification provided.</div>
      <?php else: ?>
        <h4 class="font-medium mb-2">Specification (JSON)</h4>
        <pre class="text-xs bg-gray-50 dark:bg-gray-900 rounded-lg p-3 overflow-x-auto"><?= $h($specPretty) ?></pre>
      <?php endif; ?>
    </section>
  </div>
</div>

<script>
  // Copy helpers
  document.querySelectorAll('[data-copy]').forEach(btn=>{
    btn.addEventListener('click', async ()=>{
      try{
        await navigator.clipboard.writeText(btn.getAttribute('data-copy')||'');
        const old=btn.textContent; btn.textContent='Copied'; setTimeout(()=>btn.textContent=old,1200);
      }catch{}
    });
  });

  // Tabs
  const tabs = document.querySelectorAll('.tab');
  const sections = {
    overview: document.getElementById('tab-overview'),
    purchases: document.getElementById('tab-purchases'),
    movements: document.getElementById('tab-movements'),
    pricing: document.getElementById('tab-pricing'),
    spec: document.getElementById('tab-spec'),
  };
  function setTab(name){
    tabs.forEach(b=>{
      const on = b.getAttribute('data-tab')===name;
      b.classList.toggle('bg-emerald-600', on);
      b.classList.toggle('text-white', on);
    });
    Object.entries(sections).forEach(([k,el])=>{
      el.classList.toggle('hidden', k!==name);
    });
  }
  tabs.forEach(b=>b.addEventListener('click',()=>setTab(b.getAttribute('data-tab'))));
  setTab('overview');

  // CSV export for purchases / movements
  function toCsv(rows, header, map){
    const head = header.join(',');
    const body = rows.map(r => map(r).map(cs).join(',')).join('\n');
    return head + '\n' + body;
    function cs(s){
      s = (s??'').toString().replaceAll('"','""');
      return /[",\n]/.test(s) ? `"${s}"` : s;
    }
  }
  document.querySelector('[data-export="purchases"]')?.addEventListener('click', ()=>{
    const rows = <?= json_encode($pur, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) ?>;
    const csv = toCsv(rows,
      ['date','supplier','qty','unit_cost','total','ref','note'],
      r => [
        (r.created_at??r.date??''),
        (r.supplier_name??''),
        (r.qty??r.in_qty??0),
        (r.unit_cost??r.cost??0),
        ((r.unit_cost??0)*(r.qty??0)),
        (r.ref_no??r.invoice_no??''),
        (r.note??'')
      ]);
    const blob = new Blob([csv],{type:'text/csv;charset=utf-8;'});
    const a=document.createElement('a'); a.href=URL.createObjectURL(blob); a.download='purchases.csv';
    document.body.appendChild(a); a.click(); a.remove(); setTimeout(()=>URL.revokeObjectURL(a.href),1500);
  });

  document.querySelector('[data-export="movements"]')?.addEventListener('click', ()=>{
    const rows = <?= json_encode($mov, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) ?>;
    const csv = toCsv(rows,
      ['date','type','in_qty','out_qty','ref','note'],
      r => [
        (r.created_at??r.date??''),
        (r.move_type??''),
        (r.in_qty??0),
        (r.out_qty??0),
        (r.ref_no??r.ref??''),
        (r.note??'')
      ]);
    const blob = new Blob([csv],{type:'text/csv;charset=utf-8;'});
    const a=document.createElement('a'); a.href=URL.createObjectURL(blob); a.download='movements.csv';
    document.body.appendChild(a); a.click(); a.remove(); setTimeout(()=>URL.revokeObjectURL(a.href),1500);
  });
</script>