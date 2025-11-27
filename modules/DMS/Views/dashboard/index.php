<?php
/**
 * modules/DMS/Views/dashboard/index.php — v2.0 (dark-friendly borders)
 *
 * Expects: $counts, $totals, $financials, $topCustomers, $lowStock, $recent,
 *          $module_base, $org, $slug
 *
 * Notes:
 * - Same data capture logic as before.
 * - Improved dark-mode friendliness and subtle colored borders per grid cell.
 */
$h = fn($v)=>htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
$currency = $org['currency'] ?? '৳';

$fmtMoney = function($n) use ($currency) {
  $n = (float)$n;
  return ($n < 0 ? '-' : '').$currency.number_format(abs($n), 2);
};
$fmtNum = fn($n)=>number_format((int)$n);
$brand  = $org['name'] ?? ucfirst((string)($slug ?? ''));

// safe accessors so missing keys don't break the view
$c = fn(string $k, $d=0)=>$counts[$k]  ?? $d;
$t = fn(string $k, $d=0)=>$totals[$k]  ?? $d;
$f = fn(string $k, $d=0)=>$financials[$k] ?? $d;

// Derived metrics (non-destructive)
$cash = (float)$f('cash');
$bank = (float)$f('bank');
$ar   = (float)$f('ar');
$ap   = (float)$f('ap');
$current_assets = (float)$f('current_assets');
$current_liabilities = (float)$f('current_liabilities');
$sales_mtd = (float)$t('sales_value');
$avg_daily_sales = $sales_mtd > 0 ? ($sales_mtd / 30.0) : 0.0;
$ar_days = ($avg_daily_sales > 0 && $ar > 0) ? round($ar / $avg_daily_sales, 1) : null;
$net_working = ($cash + $bank + $ar) - $ap;
$current_ratio = ($current_liabilities > 0) ? round($current_assets / $current_liabilities, 2) : null;
?>
<style>
  :root{
    --brand: #16a34a;
    --muted: #64748b;
    --bg-card: rgba(255,255,255,0.88);
    --card-bdr: rgba(2,6,23,0.06);
    --glass: rgba(255,255,255,0.85);
    --ui-border: rgba(2,6,23,0.06);
    --panel-bg: #ffffff;
  }
  @media (prefers-color-scheme: dark){
    :root{
      --brand: #34d399;
      --muted: #94a3b8;
      --bg-card: rgba(2,6,23,0.52);
      --card-bdr: rgba(255,255,255,0.06);
      --glass: rgba(2,6,23,0.6);
      --ui-border: rgba(255,255,255,0.06);
      --panel-bg: rgba(7,12,20,0.6);
    }
  }

  /* Card base */
  .card {
    border-radius: 12px;
    border: 1px solid var(--card-bdr);
    background: var(--bg-card);
    padding: 14px;
    box-shadow: 0 1px 2px rgba(2,6,23,.04);
  }

  /* Per-grid fine ui borders: subtle colored borders */
  .border-emerald { border-color: rgba(16,185,129,0.10) !important; }
  .border-emerald-strong { border-color: rgba(16,185,129,0.16) !important; }
  .border-rose   { border-color: rgba(244,63,94,0.08) !important; }
  .border-amber  { border-color: rgba(245,158,11,0.10) !important; }
  .border-slate  { border-color: rgba(100,116,139,0.06) !important; }

  /* Layout helpers */
  .page-header { display:flex; justify-content:space-between; align-items:flex-start; gap:12px; flex-wrap:wrap; margin-bottom:10px; }
  .header-left h1 { margin:0; font-size:1.55rem; }
  .muted { color: var(--muted); font-size: .95rem; }

  /* KPI grid */
  .kpi-grid { display:grid; gap:12px; grid-template-columns: repeat(2,1fr); }
  @media(min-width:720px){ .kpi-grid { grid-template-columns: repeat(3,1fr); } }
  @media(min-width:1200px){ .kpi-grid { grid-template-columns: repeat(6,1fr); } }

  .kpi-card { padding:12px; display:flex; justify-content:space-between; align-items:center; gap:12px; border:1px solid var(--ui-border); border-radius:10px; background:var(--panel-bg); }
  .kpi-label { font-size:.72rem; color:var(--muted); text-transform:uppercase; letter-spacing:.06em; }
  .kpi-value { font-size:1.45rem; font-weight:700; color:var(--brand); }

  /* Liquidity grid: responsive 2 cols -> 4 cols */
  .balances-grid {
    display:grid;
    gap:10px;
    grid-template-columns: repeat(2,1fr);
  }
  @media(min-width:900px){
    .balances-grid { grid-template-columns: repeat(4,1fr); }
  }
  .balance-item {
    padding:12px;
    border-radius:10px;
    border:1px solid var(--card-bdr);
    background: linear-gradient(180deg, rgba(255,255,255,0.6), rgba(255,255,255,0.45));
    backdrop-filter: blur(2px);
  }
  @media (prefers-color-scheme: dark){
    .balance-item { background: linear-gradient(180deg, rgba(2,6,23,0.5), rgba(2,6,23,0.44)); }
  }
  .balance-label { font-size:.72rem; color:var(--muted); margin-bottom:6px; }
  .balance-value { font-size:1.15rem; font-weight:700; }

  /* sub-panels */
  .panel-row { display:flex; gap:12px; align-items:center; }
  .chip { display:inline-block; padding:6px 8px; border-radius:999px; font-size:.75rem; }
  .chip.good { background: rgba(16,185,129,0.07); color: #065f46; }
  .chip.warn { background: rgba(245,158,11,0.06); color: #92400e; }
  .chip.bad  { background: rgba(244,63,94,0.06); color: #831843; }

  /* Recent table */
  .recent-table { width:100%; border-collapse:collapse; font-size:.95rem; }
  .recent-table th, .recent-table td { padding:8px 10px; border-bottom:1px solid rgba(0,0,0,.04); text-align:left; }
  .recent-table th { color:var(--muted); font-weight:600; font-size:.82rem; }
  @media (prefers-color-scheme: dark){
    .recent-table th, .recent-table td { border-bottom-color: rgba(255,255,255,0.04); }
  }

  /* Two column area */
  .two-col-grid { display:grid; grid-template-columns:1fr; gap:12px; }
  @media(min-width:900px){ .two-col-grid { grid-template-columns: 1fr 1fr; } }

  /* Quick links */
  .links-grid { display:grid; grid-template-columns: repeat(2,1fr); gap:10px; }
  @media(min-width:900px){ .links-grid { grid-template-columns: repeat(4,1fr); } }

  .muted-sm { color:var(--muted); font-size:.9rem; }
  .right { text-align:right; }
  .action-btn { display:inline-flex; gap:8px; align-items:center; padding:8px 12px; border-radius:10px; border:1px solid var(--card-bdr); background:var(--panel-bg); color:inherit; }
  .brand { color: var(--brand); font-weight:700; }

  .spark { height:36px; border-radius:6px; background: linear-gradient(90deg, rgba(16,185,129,0.06), rgba(16,185,129,0.02)); }
</style>

<div class="space-y-6">
  <div class="page-header">
    <div class="header-left">
      <h1><?= $h($brand) ?> — DMS Dashboard</h1>
      <div class="muted">Overview for <strong><?= $h($org['slug'] ?? $slug) ?></strong></div>
    </div>

    <div class="header-right" style="display:flex;gap:8px;align-items:center;">
      <a class="action-btn brand-bg text-white" href="<?= $h($module_base) ?>/sales/create">+ New Sale</a>
      <a class="action-btn" href="<?= $h($module_base) ?>/purchases/create">+ New Purchase</a>
      <a class="action-btn" href="<?= $h($module_base) ?>/reports">Reports</a>
    </div>
  </div>

  <!-- KPI Cards -->
  <div class="kpi-grid">
    <?php
      $kpis = [
        ['label'=>'Products',       'value'=>$fmtNum($c('products')),         'href'=>"$module_base/products"],
        ['label'=>'Customers',      'value'=>$fmtNum($c('customers')),        'href'=>"$module_base/customers"],
        ['label'=>'Suppliers',      'value'=>$fmtNum($c('suppliers')),        'href'=>"$module_base/suppliers"],
        ['label'=>'Sales (MTD)',    'value'=>$fmtMoney($t('sales_value')),    'href'=>"$module_base/sales"],
        ['label'=>'Purch (MTD)',    'value'=>$fmtMoney($t('purchase_value')), 'href'=>"$module_base/purchases"],
        ['label'=>'Expenses (MTD)', 'value'=>$fmtMoney($t('expenses')),       'href'=>"$module_base/expenses"],
      ];
    ?>
    <?php foreach ($kpis as $k): ?>
      <a href="<?= $h($k['href']) ?>" class="kpi-card">
        <div>
          <div class="kpi-label"><?= $h($k['label']) ?></div>
          <div class="kpi-value"><?= $h($k['value']) ?></div>
        </div>
        <div class="muted-sm"><i class="fa-solid fa-chevron-right"></i></div>
      </a>
    <?php endforeach; ?>
  </div>

  <!-- Financials (Liquidity & Balances) -->
  <div class="grid grid-cols-1 gap-4" style="display:grid;grid-template-columns:1fr; gap:12px;">
    <div class="card border-slate">
      <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:10px;">
        <div>
          <h2 style="margin:0;font-size:1.05rem;font-weight:700;">Liquidity & Balances</h2>
          <div class="muted-sm">Snapshot of cash, bank, receivables and payables</div>
        </div>
        <div style="display:flex;gap:8px;align-items:center;">
          <div class="muted-sm">Currency: <strong><?= $h($currency) ?></strong></div>
          <a class="action-btn" href="<?= $h($module_base) ?>/accounts">Accounts</a>
        </div>
      </div>

      <div class="balances-grid">
        <!-- Cash -->
        <div class="balance-item border-emerald-strong">
          <div class="balance-label">Cash</div>
          <div class="balance-value <?= $cash>=0 ? 'text-emerald-600' : 'text-rose-600' ?>"><?= $h($fmtMoney($cash)) ?></div>
          <div style="margin-top:8px;" class="muted-sm"><span class="chip <?= $cash>=0 ? 'good' : 'bad' ?>"><?= $cash>=0 ? 'Available' : 'Overdrawn' ?></span></div>
        </div>

        <!-- Bank -->
        <div class="balance-item border-emerald">
          <div class="balance-label">Bank</div>
          <div class="balance-value <?= $bank>=0 ? 'text-emerald-600' : 'text-rose-600' ?>"><?= $h($fmtMoney($bank)) ?></div>
          <div style="margin-top:8px;" class="muted-sm">Primary accounts balance</div>
        </div>

        <!-- Accounts Receivable -->
        <div class="balance-item border-amber">
          <div class="balance-label">A/R (Receivables)</div>
          <div class="balance-value <?= $ar>=0 ? 'text-emerald-600' : 'text-rose-600' ?>"><?= $h($fmtMoney($ar)) ?></div>
          <div style="margin-top:8px;" class="muted-sm"><?= $ar_days !== null ? ("~".$h((string)$ar_days)." days") : 'AR days N/A' ?></div>
        </div>

        <!-- Accounts Payable -->
        <div class="balance-item border-rose">
          <div class="balance-label">A/P (Payables)</div>
          <div class="balance-value <?= $ap>=0 ? 'text-rose-600' : 'text-emerald-600' ?>"><?= $h($fmtMoney($ap)) ?></div>
          <div style="margin-top:8px;" class="muted-sm">Upcoming supplier liabilities</div>
        </div>

        <!-- Net Working Capital -->
        <div class="balance-item border-slate">
          <div class="balance-label">Net Working Capital</div>
          <div class="balance-value <?= $net_working>=0 ? 'text-emerald-600' : 'text-rose-600' ?>"><?= $h($fmtMoney($net_working)) ?></div>
          <div style="margin-top:8px;" class="muted-sm">Cash + Bank + AR − AP</div>
        </div>

        <!-- Current Ratio -->
        <div class="balance-item border-slate">
          <div class="balance-label">Current Ratio</div>
          <div class="balance-value"><?= $current_ratio !== null ? $h((string)$current_ratio) : 'N/A' ?></div>
          <div style="margin-top:8px;" class="muted-sm">Current assets / Current liabilities</div>
        </div>

        <!-- Quick Ratio (approx) -->
        <div class="balance-item border-amber">
          <div class="balance-label">Quick Ratio (approx)</div>
          <?php
            $quick = null;
            if ($current_assets > 0 && $current_liabilities > 0) {
              $quick = round((($cash + $bank + $ar) / max(1.0, $current_liabilities)), 2);
            }
          ?>
          <div class="balance-value"><?= $quick !== null ? $h((string)$quick) : 'N/A' ?></div>
          <div style="margin-top:8px;" class="muted-sm">Higher is healthier</div>
        </div>

        <!-- Cash trend (spark) -->
        <div class="balance-item border-emerald">
          <div>
            <div class="balance-label">Cash trend (30d)</div>
            <div class="balance-value"><?= $h($fmtMoney($cash)) ?></div>
            <div style="margin-top:8px;" class="muted-sm">Approx. 30-day snapshot</div>
          </div>
          <div class="spark" id="cashSpark" style="margin-top:8px;"></div>
        </div>
      </div>
    </div>

    <!-- Insights + Recent -->
    <div class="card border-slate">
      <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:10px;">
        <div>
          <h3 style="margin:0;font-size:1rem;font-weight:700;">Financial Insights</h3>
          <div class="muted-sm">Handy derived metrics</div>
        </div>
        <div class="muted-sm">
          <a class="hover:underline" href="<?= $h($module_base) ?>/reports/aging">Aging report</a> ·
          <a class="hover:underline" href="<?= $h($module_base) ?>/accounts">Ledger</a>
        </div>
      </div>

      <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:12px;">
        <div class="card border-amber" style="padding:10px;">
          <div class="muted-sm">AR Days</div>
          <div style="font-weight:700;font-size:1.15rem;"><?= $ar_days !== null ? $h((string)$ar_days)." days" : 'N/A' ?></div>
          <div class="muted-sm">Lower is better</div>
        </div>
        <div class="card border-slate" style="padding:10px;">
          <div class="muted-sm">Net Working Capital</div>
          <div style="font-weight:700;font-size:1.15rem;"><?= $h($fmtMoney($net_working)) ?></div>
          <div class="muted-sm">Liquidity after liabilities</div>
        </div>
        <div class="card border-slate" style="padding:10px;">
          <div class="muted-sm">Current Ratio</div>
          <div style="font-weight:700;font-size:1.15rem;"><?= $current_ratio !== null ? $h((string)$current_ratio) : 'N/A' ?></div>
          <div class="muted-sm">Coverage of short-term liabilities</div>
        </div>
      </div>

      <div style="margin-top:12px;">
        <h4 style="margin:0 0 8px 0;font-size:1rem;font-weight:700;">Recent Activity</h4>
        <div style="overflow:auto;">
          <table class="recent-table" role="table" aria-label="Recent activity">
            <thead>
              <tr><th>Type</th><th>Reference</th><th class="right">Amount</th><th>Date</th></tr>
            </thead>
            <tbody>
              <?php if (!empty($recent)): foreach ($recent as $r): ?>
                <tr>
                  <td><?= $h($r['type'] ?? '—') ?></td>
                  <td><?= $h($r['ref'] ?? '—') ?></td>
                  <td class="right"><?= $h($fmtMoney((float)($r['amount'] ?? 0))) ?></td>
                  <td><?= $h($r['date'] ?? '') ?></td>
                </tr>
              <?php endforeach; else: ?>
                <tr><td colspan="4" class="muted-sm">No recent activity.</td></tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>

  <!-- Top customers & Low stock -->
  <div class="two-col-grid">
    <div class="card border-slate">
      <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;">
        <h3 style="margin:0;font-weight:700;">Top Customers (MTD)</h3>
        <a class="muted-sm" href="<?= $h($module_base) ?>/customers">View all</a>
      </div>
      <div style="overflow:auto;">
        <table class="recent-table" role="table" aria-label="Top customers">
          <thead><tr><th>Customer</th><th class="right">Total</th></tr></thead>
          <tbody>
            <?php if (!empty($topCustomers)): foreach ($topCustomers as $row): ?>
              <tr>
                <td><?= $h($row['name'] ?? '—') ?></td>
                <td class="right"><?= $h($fmtMoney((float)($row['total'] ?? 0))) ?></td>
              </tr>
            <?php endforeach; else: ?>
              <tr><td colspan="2" class="muted-sm">No sales in the current month.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

    <div class="card border-slate">
      <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;">
        <h3 style="margin:0;font-weight:700;">Low Stock</h3>
        <a class="muted-sm" href="<?= $h($module_base) ?>/products">View products</a>
      </div>
      <div style="overflow:auto;">
        <table class="recent-table" role="table" aria-label="Low stock">
          <thead><tr><th>SKU</th><th>Product</th><th class="right">Qty</th><th class="right">Reorder</th></tr></thead>
          <tbody>
            <?php if (!empty($lowStock)): foreach ($lowStock as $p): ?>
              <tr>
                <td><?= $h($p['sku'] ?? '—') ?></td>
                <td><?= $h($p['name'] ?? '—') ?></td>
                <td class="right"><?= $h((string)($p['quantity'] ?? 0)) ?></td>
                <td class="right"><?= $h((string)($p['reorder_level'] ?? 0)) ?></td>
              </tr>
            <?php endforeach; else: ?>
              <tr><td colspan="4" class="muted-sm">No low-stock items right now.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <!-- Quick links -->
  <div class="links-grid">
    <?php
      $links = [
        ['icon'=>'fa-file-invoice-dollar','label'=>'Invoices','href'=>"$module_base/invoices"],
        ['icon'=>'fa-sack-dollar','label'=>'Payments','href'=>"$module_base/payments"],
        ['icon'=>'fa-book','label'=>'Cash Book','href'=>"$module_base/accounts/cash-book"],
        ['icon'=>'fa-scale-balanced','label'=>'Trial Balance','href'=>"$module_base/accounts/trial-balance"],
      ];
    ?>
    <?php foreach ($links as $lnk): ?>
      <a href="<?= $h($lnk['href']) ?>" class="card border-slate" style="display:flex;gap:12px;align-items:center;">
        <div style="width:44px;height:44px;border-radius:8px;display:grid;place-items:center;background:rgba(16,185,129,0.06);color:#047857;">
          <i class="fa-solid <?= $h($lnk['icon']) ?>"></i>
        </div>
        <div style="font-weight:600;"><?= $h($lnk['label']) ?></div>
      </a>
    <?php endforeach; ?>
  </div>
</div>

<script>
/* Small client enhancements */
(function(){
  // Render simple faux sparkline using recent cash points (if available in window.KF.financialsPoints)
  try {
    var points = (window.KF && window.KF.financialsPoints && window.KF.financialsPoints.cash) || null;
    var el = document.getElementById('cashSpark');
    if (el && points && points.length) {
      var w = el.clientWidth || 260, h = el.clientHeight || 36;
      var max = Math.max.apply(null, points), min = Math.min.apply(null, points);
      var range = Math.max(1, max - min);
      var step = w / (points.length - 1 || 1);
      var path = [];
      for (var i=0;i<points.length;i++){
        var x = Math.round(i*step);
        var y = Math.round(h - ((points[i]-min)/range)*h);
        path.push(x+','+y);
      }
      var svg = '<svg width="'+w+'" height="'+h+'" viewBox="0 0 '+w+' '+h+'" xmlns="http://www.w3.org/2000/svg"><polyline fill="none" stroke="#10b981" stroke-width="2" points="'+path.join(' ')+'"/></svg>';
      el.innerHTML = svg;
    }
  } catch(e){}
})();
</script>