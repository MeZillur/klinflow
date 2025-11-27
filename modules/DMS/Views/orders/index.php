<?php
/**
 * modules/DMS/Views/orders/index.php
 * Content-only view. Shell wraps.
 * Brand: #228B22
 */

declare(strict_types=1);
if (!function_exists('h')) {
    function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
}

/* ---------- Inputs / context ---------- */
$base       = rtrim((string)($module_base ?? ''), '/');
$rows       = is_array($rows ?? null) ? $rows : [];
$_suppliers = is_array($suppliers ?? null) ? $suppliers : (is_array($dealers ?? null) ? $dealers : []); // compat
$srs        = is_array($srs ?? null) ? $srs : [];
$f          = is_array($filters ?? null) ? $filters : [];

/* Read filters (accept aliases for q) */
$from     = (string)($f['from']        ?? ($_GET['from']        ?? date('Y-m-01')));
$to       = (string)($f['to']          ?? ($_GET['to']          ?? date('Y-m-d')));
$q        = trim((string)($_GET['q'] ?? ($_GET['search'] ?? ($_GET['s'] ?? ''))));
$supplier = (string)($f['supplier_id'] ?? ($_GET['supplier_id'] ?? ''));
$sr       = (string)($f['sr_id']       ?? ($_GET['sr_id']       ?? ''));
$status   = (string)($f['status']      ?? ($_GET['status']      ?? ''));

$statsCount = (int)($stats['count'] ?? count($rows));
$statsSum   = (float)($stats['sum']  ?? array_sum(array_map(fn($r)=> (float)($r['grand_total'] ?? 0), $rows)));

$pathOnly = strtok($_SERVER['REQUEST_URI'] ?? ($base.'/orders'), '?');
?>
<style>
  :root { --brand:#228B22; }
  .kf-pill{display:inline-block;border-radius:9999px;padding:.125rem .5rem;font-size:.72rem;font-weight:600}
  .kf-toolbar{position:sticky;top:0;z-index:10;background:#fff;border-bottom:1px solid #e5e7eb}
  .kf-btn{border:1px solid #e2e8f0;border-radius:12px;padding:.5rem .75rem;display:inline-flex;gap:.5rem;align-items:center;font-size:.875rem}
  .kf-btn--brand{background:var(--brand);color:#fff;border-color:var(--brand)}
  .kf-btn:hover{background:#f8fafc}
  .kf-btn--brand:hover{filter:brightness(0.95)}
  .kf-chip{border-radius:10px;background:#f8fafc;border:1px solid #e2e8f0;padding:.25rem .5rem;font-size:.75rem}
  .kf-table th,.kf-table td{border-top:1px solid #e5e7eb}
  .kf-table th{font-weight:600;color:#334155}
  .kf-select{border:1px solid #e2e8f0;border-radius:12px;padding:.45rem .6rem;font-size:.8rem;background:#fff;min-width:130px}
  @media (max-width:640px){ .hide-sm{display:none} }
</style>

<!-- =========================== Toolbar =========================== -->
<div class="kf-toolbar mb-4">
  <div class="max-w-7xl mx-auto px-4 py-3 flex items-center gap-2">
    <div class="mr-auto">
      <div class="text-lg font-semibold">Orders</div>
      <div class="text-slate-500 text-xs">
        Filter by supplier, SR/DSR, dates and status. Export or print the current list.
      </div>
    </div>

    <a href="<?= h($base.'/orders/create') ?>" class="kf-btn kf-btn--brand">
      <span>＋</span><span>New Order</span>
    </a>

    <!-- Compact actions menu (CSV / Print) -->
    <select id="orderActions" class="kf-select">
      <option value="">Actions…</option>
      <option value="csv">Export CSV (current view)</option>
      <option value="print">Print this list</option>
    </select>
  </div>
</div>

<!-- =========================== Filters =========================== -->
<form class="max-w-7xl mx-auto px-4 mb-4 border rounded-2xl bg-white shadow-sm" method="GET" action="">
  <div class="grid grid-cols-1 lg:grid-cols-12 gap-2 p-3">
    <div class="lg:col-span-2">
      <label class="block text-xs text-slate-600">From</label>
      <input type="date" name="from" value="<?= h(substr($from,0,10)) ?>" class="w-full rounded-xl border px-3 py-2">
    </div>
    <div class="lg:col-span-2">
      <label class="block text-xs text-slate-600">To</label>
      <input type="date" name="to" value="<?= h(substr($to,0,10)) ?>" class="w-full rounded-xl border px-3 py-2">
    </div>
    <div class="lg:col-span-2">
      <label class="block text-xs text-slate-600">Supplier</label>
      <select name="supplier_id" class="w-full rounded-xl border px-3 py-2">
        <option value="">All</option>
        <?php foreach ($_suppliers as $s): $sid=(string)($s['id']??''); ?>
          <option value="<?= h($sid) ?>" <?= ($sid===$supplier?'selected':'') ?>>
            <?= h($s['name'] ?? ('#'.$sid)) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="lg:col-span-2">
      <label class="block text-xs text-slate-600">SR / DSR</label>
      <select name="sr_id" class="w-full rounded-xl border px-3 py-2">
        <option value="">All</option>
        <?php foreach ($srs as $u): $uid=(string)($u['id']??''); ?>
          <option value="<?= h($uid) ?>" <?= ($uid===$sr?'selected':'') ?>>
            <?= h($u['name'] ?? ('#'.$uid)) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="lg:col-span-2">
      <label class="block text-xs text-slate-600">Status</label>
      <select name="status" class="w-full rounded-xl border px-3 py-2">
        <option value="">Any</option>
        <?php foreach (['draft'=>'Draft','confirmed'=>'Confirmed','issued'=>'Issued','cancelled'=>'Cancelled'] as $k=>$label): ?>
          <option value="<?= h($k) ?>" <?= ($status===$k?'selected':'') ?>>
            <?= h($label) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="lg:col-span-2">
      <label class="block text-xs text-slate-600">Search</label>
      <input type="text" name="q" value="<?= h($q) ?>"
             placeholder="Order # / customer / supplier…" class="w-full rounded-xl border px-3 py-2" id="qBox">
    </div>

    <div class="lg:col-span-12 flex items-center gap-2 pt-1">
      <button type="submit" class="kf-btn kf-btn--brand">Apply</button>
      <a href="<?= h($pathOnly) ?>" class="kf-btn">Reset</a>

      <div class="ml-auto flex items-center gap-2 text-sm">
        <span class="kf-chip">Records: <b id="recCount"><?= (int)$statsCount ?></b></span>
        <span class="kf-chip">Grand Total: <b>৳ <span id="grandSum"><?= number_format($statsSum,2) ?></span></b></span>
      </div>
    </div>
  </div>
</form>

<!-- =========================== Table =========================== -->
<div class="max-w-7xl mx-auto px-4">
  <div class="overflow-x-auto rounded-2xl border bg-white">
    <table class="kf-table min-w-full text-sm" id="ordersTable">
      <thead class="bg-slate-50">
        <tr>
          <th class="px-3 py-2 text-left">Date</th>
          <th class="px-3 py-2 text-left">Order No</th>
          <th class="px-3 py-2 text-left hide-sm">Customer</th>
          <th class="px-3 py-2 text-left hide-sm">Supplier</th>
          <th class="px-3 py-2 text-left">Status</th>
          <th class="px-3 py-2 text-right">Total</th>
          <th class="px-3 py-2 text-right">Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php if (!$rows): ?>
          <tr>
            <td colspan="7" class="px-3 py-10 text-center text-slate-500">
              No orders found for this filter.
            </td>
          </tr>
        <?php else: foreach ($rows as $o):
          $id    = (int)($o['id'] ?? 0);
          $date  = substr((string)($o['order_date'] ?? ''), 0, 10);
          $no    = (string)($o['order_no'] ?? ('ORD-'.$id));
          $cust  = (string)($o['customer_name'] ?? '');
          $supp  = (string)($o['supplier_name'] ?? '');
          $st    = strtolower((string)($o['status'] ?? 'draft'));
          $total = (float)($o['grand_total'] ?? 0);
          $pill  = match ($st) {
            'confirmed' => 'kf-pill bg-emerald-100 text-emerald-700',
            'issued'    => 'kf-pill bg-blue-100 text-blue-700',
            'cancelled' => 'kf-pill bg-rose-100 text-rose-700',
            default     => 'kf-pill bg-slate-100 text-slate-700',
          };
        ?>
          <tr class="hover:bg-slate-50"
              data-id="<?= (int)$id ?>"
              data-no="<?= h(strtolower($no)) ?>"
              data-customer="<?= h(strtolower($cust)) ?>"
              data-supplier="<?= h(strtolower($supp)) ?>"
              data-status="<?= h(strtolower($st)) ?>"
              data-total="<?= h(number_format($total,2,'.','')) ?>">
            <td class="px-3 py-2"><?= h($date) ?></td>
            <td class="px-3 py-2">
              <a href="<?= h($base.'/orders/'.$id) ?>"
                 class="text-emerald-700 hover:underline font-medium"><?= h($no) ?></a>
            </td>
            <td class="px-3 py-2 hide-sm"><?= h($cust) ?></td>
            <td class="px-3 py-2 hide-sm"><?= h($supp) ?></td>
            <td class="px-3 py-2"><span class="<?= $pill ?>"><?= h(strtoupper($st)) ?></span></td>
            <td class="px-3 py-2 text-right">৳ <?= h(number_format($total,2)) ?></td>
            <td class="px-3 py-2 text-right">
              <a href="<?= h($base.'/orders/'.$id) ?>" class="kf-btn" style="padding:.25rem .5rem">View</a>
              <a href="<?= h($base.'/orders/'.$id.'/edit') ?>" class="kf-btn hide-sm" style="padding:.25rem .5rem">Edit</a>
              <a href="<?= h($base.'/orders/'.$id.'/print?autoprint=1') ?>" class="kf-btn hide-sm" style="padding:.25rem .5rem">Print</a>
            </td>
          </tr>
        <?php endforeach; endif; ?>
      </tbody>
      <?php if ($rows): ?>
      <tfoot class="bg-slate-50">
        <tr>
          <td class="px-3 py-3 font-semibold" colspan="5">Total</td>
          <td class="px-3 py-3 text-right font-semibold">
            ৳ <span id="tfootSum"><?= number_format($statsSum,2) ?></span>
          </td>
          <td></td>
        </tr>
      </tfoot>
      <?php endif; ?>
    </table>
  </div>
</div>

<!-- =========================== How to use this page =========================== -->
<div class="max-w-7xl mx-auto px-4 mt-6 border-t pt-4 text-xs text-slate-600 space-y-1">
  <div class="font-semibold text-slate-800 mb-1">How to use this page</div>
  <ol class="list-decimal ml-4 space-y-1">
    <li>Use the <b>date range</b>, <b>Supplier</b>, <b>SR/DSR</b> and <b>Status</b> filters to narrow down orders.</li>
    <li>Search by <b>Order No</b>, <b>Customer</b> or <b>Supplier</b> using the search box; filtering is live on the table.</li>
    <li>Click on an <b>Order No</b> to open the full order detail; use the row actions to <b>View</b>, <b>Edit</b> or <b>Print</b> a single order.</li>
    <li>Use the <b>New Order</b> button in the top-right to create a fresh order.</li>
    <li>From the <b>Actions…</b> menu, choose <b>Export CSV</b> to download the currently visible table rows, or <b>Print this list</b> for a quick printed summary.</li>
    <li>Use the totals chips above the filters and in the table footer to quickly see <b>record count</b> and <b>grand total</b> in BDT.</li>
  </ol>
</div>

<!-- =========================== Data & Export Helpers =========================== -->
<script>
/* Build dataset from server rows for client-side export and search */
const KF_ORDERS = <?= json_encode(array_map(function($r){
  return [
    'id'           => (int)($r['id'] ?? 0),
    'order_no'     => (string)($r['order_no'] ?? ''),
    'order_date'   => substr((string)($r['order_date'] ?? ''),0,10),
    'customer'     => (string)($r['customer_name'] ?? ''),
    'supplier'     => (string)($r['supplier_name'] ?? ''),
    'status'       => (string)($r['status'] ?? 'draft'),
    'grand_total'  => (float)($r['grand_total'] ?? 0),
  ];
}, $rows), JSON_UNESCAPED_UNICODE) ?>;

/* CSV export helpers */
function toCsv(rows){
  if(!rows.length) return '';
  const heads = Object.keys(rows[0]);
  const esc = v => {
    const s = (v ?? '').toString();
    return /[",\n]/.test(s) ? '"' + s.replace(/"/g,'""') + '"' : s;
  };
  const lines = [
    heads.map(esc).join(','),
    ...rows.map(r => heads.map(k => esc(r[k])).join(','))
  ];
  return lines.join('\n');
}

function download(name, mime, data){
  const blob = new Blob([data], {type: mime});
  const url = URL.createObjectURL(blob);
  const a = document.createElement('a');
  a.href = url;
  a.download = name;
  document.body.appendChild(a);
  a.click();
  a.remove();
  URL.revokeObjectURL(url);
}

function getVisibleRowsData(){
  const trs = document.querySelectorAll('#ordersTable tbody tr[data-id]');
  const out = [];
  trs.forEach(tr=>{
    if (tr.style.display === 'none') return;
    out.push({
      id: parseInt(tr.dataset.id || '0',10),
      order_no: tr.dataset.no || '',
      order_date: tr.children[0]?.textContent.trim() || '',
      customer: tr.dataset.customer || '',
      supplier: tr.dataset.supplier || '',
      status: tr.dataset.status || '',
      grand_total: parseFloat(tr.dataset.total || '0')
    });
  });
  return out;
}

/* Actions menu (CSV / Print) */
document.getElementById('orderActions')?.addEventListener('change', (e)=>{
  const val = e.target.value;
  if (!val) return;

  if (val === 'csv') {
    const data = getVisibleRowsData();
    const csv  = toCsv(data);
    download('orders-'+(new Date().toISOString().slice(0,10))+'.csv', 'text/csv;charset=utf-8', csv);
  } else if (val === 'print') {
    window.print();
  }

  // reset back to placeholder
  e.target.value = '';
});

/* Live client-side search (progressive enhancement) */
(function(){
  const qBox = document.getElementById('qBox');
  const tbody = document.querySelector('#ordersTable tbody');
  const recCount = document.getElementById('recCount');
  const grandSum = document.getElementById('grandSum');
  const tfootSum = document.getElementById('tfootSum');

  function digits(s){ return (s||'').replace(/\D+/g,''); }

  function applyFilter(q){
    const qnorm   = (q||'').trim().toLowerCase();
    const qdigits = digits(qnorm);
    let visible = 0;
    let sum = 0;

    tbody.querySelectorAll('tr[data-id]').forEach(tr=>{
      const no   = tr.dataset.no || '';
      const cust = tr.dataset.customer || '';
      const supp = tr.dataset.supplier || '';
      const stat = tr.dataset.status || '';
      const matchText = (no.includes(qnorm) || cust.includes(qnorm) || supp.includes(qnorm) || stat.includes(qnorm));
      const noDigits  = digits(no);
      const matchDigits = (qdigits && noDigits.includes(qdigits)) ? true : false;

      const show = qnorm === '' ? true : (matchText || matchDigits);
      tr.style.display = show ? '' : 'none';
      if (show){
        visible++;
        sum += parseFloat(tr.dataset.total || '0');
      }
    });

    recCount.textContent = visible.toString();
    const sumStr = new Intl.NumberFormat(undefined, {
      minimumFractionDigits:2,
      maximumFractionDigits:2
    }).format(sum);
    grandSum.textContent = sumStr;
    if (tfootSum) tfootSum.textContent = sumStr;
  }

  let t;
  qBox?.addEventListener('input', e=>{
    clearTimeout(t);
    t = setTimeout(()=>applyFilter(e.target.value), 150);
  });

  if (qBox && qBox.value.trim() !== '') {
    applyFilter(qBox.value);
  }
})();
</script>