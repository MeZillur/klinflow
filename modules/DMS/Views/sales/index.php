<?php
declare(strict_types=1);

use Shared\Csrf;

$h     = fn($s)=>htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
$title = $title ?? 'Sales (Invoices)';

/* ---------- Canonical module base ---------- */
$base = rtrim((string)($module_base ?? ($org['module_base'] ?? '')), '/');
if ($base === '') {
    $slug = (string)($_SESSION['tenant_org']['slug'] ?? '');
    if ($slug === '' && isset($_SERVER['REQUEST_URI']) &&
        preg_match('#^/t/([^/]+)/apps/dms#', (string)$_SERVER['REQUEST_URI'], $m)) {
        $slug = $m[1];
    }
    $base = $slug ? '/t/'.rawurlencode($slug).'/apps/dms' : '/apps/dms';
}

/* ---------- CSRF ---------- */
$csrf = class_exists(Csrf::class) ? Csrf::token() : '';

/* ---------- Normalize rows from controller (tolerant) ---------- */
$rows = array_map(function(array $r){
    $id   = (int)($r['id'] ?? 0);
    $no   = (string)($r['sale_no'] ?? $r['invoice_no'] ?? $r['code'] ?? $r['no'] ?? '');
    if ($no === '' && $id) $no = '#'.$id;

    $date = (string)($r['sale_date'] ?? $r['invoice_date'] ?? $r['date'] ?? '');
    $cust = trim((string)($r['customer_name'] ?? $r['customer'] ?? '—')) ?: '—';

    $qty     = (float)($r['qty'] ?? 0);
    $disc    = (float)($r['discount'] ?? $r['discount_total'] ?? $r['discount_value'] ?? 0);
    $total   = (float)($r['total'] ?? $r['grand_total'] ?? 0);
    $paid    = (float)($r['paid'] ?? $r['paid_total'] ?? 0);
    $returns = (float)($r['returns'] ?? 0);

    // robust due: total - paid - returns
    $due     = max(0.0, $total - $paid - $returns);

    $st      = strtolower((string)($r['invoice_status'] ?? $r['status'] ?? 'issued'));
    if ($st === 'posted') $st = 'issued';
    if ($total > 0 && $due <= 0.00001) $st = 'paid'; // force UI paid

    $challanIssued = !empty($r['challan_count']); // any challan linked

    return [
        'id'=>$id,'no'=>$no,'date'=>$date,'cust'=>$cust,
        'qty'=>$qty,'disc'=>$disc,'total'=>$total,'paid'=>$paid,
        'due'=>$due,'returns'=>$returns,'st'=>$st,'challanIssued'=>$challanIssued
    ];
}, $rows ?? []);

/* ---------- Tabs ---------- */
$tabs = [
    ['key'=>'all','label'=>'All'],
    ['key'=>'paid','label'=>'Paid'],
    ['key'=>'due','label'=>'Due'],
    ['key'=>'issued','label'=>'Issued'],
    ['key'=>'delivered','label'=>'Delivered'],
    ['key'=>'returned','label'=>'Returned'],
    ['key'=>'challan','label'=>'Challan Issued'],
];
$active = strtolower((string)($_GET['filter'] ?? 'all'));
$fmt2   = fn(float $n)=>number_format($n,2,'.',',');

/* ---------- Status badge palette (Issued = green) ---------- */
$badge = function(string $st){
    return match($st){
        'paid' => 'bg-emerald-100 text-emerald-700 border border-emerald-200',
        'issued' => 'bg-emerald-50 text-emerald-700 border border-emerald-200',
        'delivered','partially_delivered' => 'bg-blue-50 text-blue-700 border border-blue-200',
        'returned' => 'bg-amber-50 text-amber-700 border border-amber-200',
        'void','cancelled' => 'bg-rose-50 text-rose-700 border border-rose-200',
        default => 'bg-gray-50 text-gray-700 border border-gray-200',
    };
};

/* ---------- Section mini-menu (Sales & Dispatch group) ---------- */
$currentPath = strtok((string)($_SERVER['REQUEST_URI'] ?? ''), '?');

$sectionMenus = [
    ['label' => 'Sales / Invoices',  'href' => $base.'/sales'],
    ['label' => 'Create Invoice',    'href' => $base.'/sales/create'],
    ['label' => 'All Orders',        'href' => $base.'/orders'],
    ['label' => 'Create Order',      'href' => $base.'/orders/create'],
    ['label' => 'Dispatch Challans', 'href' => $base.'/challan'],
    ['label' => 'Payments',          'href' => $base.'/payments'],
    ['label' => 'Returns',           'href' => $base.'/returns'],
];

$menuIsActive = function(string $href) use ($currentPath): bool {
    if ($href === '') return false;
    return $currentPath !== '' && str_starts_with($currentPath, $href);
};
?>
<div class="space-y-4">
  <!-- Header + Section Menu -->
  <div class="flex items-start justify-between gap-3 flex-wrap mb-2">
    <div class="space-y-1">
      <h1 class="text-[18px] font-semibold">
        <?= $h($title) ?>
      </h1>
      <p class="text-[12px] text-gray-500">
        Review invoices, track dues, and issue delivery challans from a single place.
      </p>
    </div>

    <div class="flex flex-col items-end gap-2">
      <!-- Primary actions -->
      <div class="flex items-center gap-2">
        <a href="<?= $h($base) ?>/sales/create"
           class="px-3 py-1.5 rounded-lg bg-[#228B22] text-white text-[13px] hover:opacity-90">
          + New Invoice
        </a>

        <button id="btn-export"
                class="px-3 py-1.5 rounded-lg border text-[13px] hover:bg-gray-50 dark:hover:bg-gray-800">
          Export CSV
        </button>

        <a href="<?= $h($base) ?>/challan"
           class="px-3 py-1.5 rounded-lg border text-[13px] hover:bg-gray-50 dark:hover:bg-gray-800">
          Dispatch Board
        </a>
      </div>

      <!-- Section page menu -->
      <div class="flex flex-wrap justify-end gap-1.5 text-[11px]">
        <?php foreach ($sectionMenus as $m):
          $isOn = $menuIsActive($m['href']);
        ?>
          <a href="<?= $h($m['href']) ?>"
             class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full border transition
                    <?= $isOn
                      ? 'bg-[#228B22] border-[#228B22] text-white shadow-sm'
                      : 'bg-white dark:bg-gray-900 border-gray-200 text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-800' ?>">
            <?= $h($m['label']) ?>
          </a>
        <?php endforeach; ?>
      </div>
    </div>
  </div>

  <!-- Tabs + Search -->
  <div class="flex items-center justify-between gap-3 flex-wrap">
    <div class="flex items-center flex-wrap gap-1.5" id="tabs">
      <?php foreach ($tabs as $t): $on = ($active === $t['key']); ?>
        <button type="button"
                class="tab px-2.5 py-1.5 rounded-full text-[12px] border
                       <?= $on ? 'bg-[#228B22] text-white border-[#228B22]' : 'bg-white dark:bg-gray-800 hover:bg-gray-50' ?>"
                data-key="<?= $h($t['key']) ?>">
          <?= $h($t['label']) ?>
        </button>
      <?php endforeach; ?>
    </div>
    <div class="relative w-full sm:w-80">
      <input id="q" type="text" placeholder="Search no, customer…"
             class="w-full text-[13px] rounded-lg border px-3 py-2 bg-white dark:bg-gray-900"
             autocomplete="off">
      <span class="absolute right-2 top-2.5 text-gray-400 text-xs">⌘/Ctrl + K</span>
    </div>
  </div>

  <!-- Table -->
  <div class="overflow-x-auto rounded-lg border">
    <table class="min-w-full text-[13px]">
      <thead class="bg-gray-50 dark:bg-gray-800 text-gray-600 dark:text-gray-300">
        <tr>
          <th class="px-2 py-1.5 text-left">No</th>
          <th class="px-2 py-1.5 text-left">Date</th>
          <th class="px-2 py-1.5 text-left">Customer</th>
          <th class="px-2 py-1.5 text-right">Qty</th>
          <th class="px-2 py-1.5 text-right">Discount</th>
          <th class="px-2 py-1.5 text-right">Total</th>
          <th class="px-2 py-1.5 text-right">Paid</th>
          <th class="px-2 py-1.5 text-right">Due</th>
          <th class="px-2 py-1.5 text-right">Returns</th>
          <th class="px-2 py-1.5 text-center">Status</th>
          <th class="px-2 py-1.5 text-right">Actions</th>
        </tr>
      </thead>
      <tbody id="rows">
      <?php foreach ($rows as $r): ?>
        <tr class="border-t" data-row
            data-no="<?= $h(strtolower($r['no'])) ?>"
            data-date="<?= $h(strtolower($r['date'])) ?>"
            data-cust="<?= $h(strtolower($r['cust'])) ?>"
            data-status="<?= $h($r['st']) ?>"
            data-due="<?= $h(number_format((float)$r['due'], 6, '.', '')) ?>"
            data-challan="<?= $r['challanIssued'] ? '1' : '0' ?>">
          <td class="px-2 py-1.5">
            <div class="font-medium"><?= $h($r['no']) ?></div>
          </td>
          <td class="px-2 py-1.5"><?= $h($r['date'] ?: '—') ?></td>
          <td class="px-2 py-1.5">
            <span class="truncate inline-block max-w-[220px]"><?= $h($r['cust']) ?></span>
          </td>
          <td class="px-2 py-1.5 text-right">
            <?= ($r['qty'] == (int)$r['qty']) ? (int)$r['qty'] : $fmt2((float)$r['qty']) ?>
          </td>
          <td class="px-2 py-1.5 text-right"><?= $fmt2((float)$r['disc']) ?></td>
          <td class="px-2 py-1.5 text-right"><?= $fmt2((float)$r['total']) ?></td>
          <td class="px-2 py-1.5 text-right"><?= $fmt2((float)$r['paid']) ?></td>
          <td class="px-2 py-1.5 text-right font-semibold <?= $r['due'] > 0 ? 'text-rose-600' : 'text-emerald-600' ?>">
            <?= $fmt2((float)$r['due']) ?>
          </td>
          <td class="px-2 py-1.5 text-right"><?= $fmt2((float)$r['returns']) ?></td>
          <td class="px-2 py-1.5 text-center">
            <span class="px-2 py-0.5 rounded-full text-[11px] <?= $badge($r['st']) ?>">
              <?= $h($r['st']) ?>
            </span>
          </td>
          <td class="px-2 py-1.5 text-right whitespace-nowrap">
            <div class="inline-flex gap-1 items-center">
              <a href="<?= $h($base) ?>/sales/<?= (int)$r['id'] ?>"
                 class="px-2.5 py-1 rounded border text-[12px] hover:bg-gray-50 dark:hover:bg-gray-800">
                View
              </a>
              <a href="<?= $h($base) ?>/sales/<?= (int)$r['id'] ?>/edit"
                 class="px-2.5 py-1 rounded border text-[12px] hover:bg-gray-50 dark:hover:bg-gray-800">
                Edit
              </a>
              <a href="<?= $h($base) ?>/sales/<?= (int)$r['id'] ?>/print"
                 class="px-2.5 py-1 rounded border text-[12px] hover:bg-gray-50 dark:hover:bg-gray-800">
                Print
              </a>

              <!-- Issue Challan (dispatch from invoice) -->
              <a href="<?= $h($base) ?>/challan/prepare?invoice_id=<?= (int)$r['id'] ?>"
                 class="px-2.5 py-1 rounded border text-[12px] hover:bg-gray-50 dark:hover:bg-gray-800">
                Issue Challan
              </a>

              <!-- Mark Paid (only if still due) -->
              <?php if ($r['st'] !== 'paid' && $r['due'] > 0.00001): ?>
                <form method="post"
                      action="<?= $h($base) ?>/sales/<?= (int)$r['id'] ?>/pay"
                      onsubmit="return confirm('Mark as paid?');"
                      class="inline">
                  <input type="hidden" name="_csrf" value="<?= $h($csrf) ?>">
                  <button class="px-2.5 py-1 rounded bg-emerald-600 text-white text-[12px] hover:bg-emerald-700">
                    Mark Paid
                  </button>
                </form>
              <?php else: ?>
                <span class="px-2.5 py-1 rounded bg-emerald-50 text-emerald-700 text-[12px]">
                  Paid
                </span>
              <?php endif; ?>
            </div>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if (empty($rows)): ?>
        <tr>
          <td colspan="11" class="px-3 py-6 text-center text-sm text-gray-500">
            No invoices found.
          </td>
        </tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<script>
(function(){
  const $  = s => document.querySelector(s);
  const $$ = s => Array.from(document.querySelectorAll(s));
  const qs = new URLSearchParams(location.search);
  let active = (qs.get('filter') || 'all').toLowerCase();

  const inp  = $('#q');
  const rows = $$('#rows tr[data-row]');

  // Quick focus shortcut
  document.addEventListener('keydown', e=>{
    if ((e.metaKey||e.ctrlKey) && e.key.toLowerCase()==='k') {
      e.preventDefault();
      inp?.focus();
    }
  });

  function matchFilter(row){
    const stat = (row.dataset.status || '').toLowerCase();
    const due  = parseFloat(row.dataset.due || '0') || 0;
    const chal = (row.dataset.challan === '1');

    switch(active){
      case 'paid':      return stat === 'paid' || (due <= 0.00001);
      case 'due':       return (due > 0.00001) && stat !== 'paid';
      case 'issued':    return stat === 'issued' || stat === 'posted';
      case 'delivered': return stat === 'delivered' || stat === 'partially_delivered';
      case 'returned':  return stat === 'returned';
      case 'challan':   return chal;
      default:          return true;
    }
  }

  function matchQuery(row, q){
    if (!q) return true;
    return (row.dataset.no||'').includes(q)
        || (row.dataset.cust||'').includes(q)
        || (row.dataset.date||'').includes(q);
  }

  function repaint(){
    const q = (inp?.value || '').trim().toLowerCase();
    rows.forEach(tr => {
      tr.style.display = (matchFilter(tr) && matchQuery(tr, q)) ? '' : 'none';
    });
  }

  // Tabs
  const tabs = $$('#tabs .tab');
  tabs.forEach(btn=>{
    btn.addEventListener('click', ()=>{
      active = (btn.dataset.key || 'all').toLowerCase();
      tabs.forEach(b=>b.classList.remove('bg-[#228B22]','text-white','border-[#228B22]'));
      btn.classList.add('bg-[#228B22]','text-white','border-[#228B22]');
      const qsp = new URLSearchParams(location.search);
      qsp.set('filter', active);
      history.replaceState(null, '', location.pathname + '?' + qsp.toString());
      repaint();
    });
  });

  // Search
  let t=null;
  inp?.addEventListener('input', ()=>{
    clearTimeout(t);
    t = setTimeout(repaint,120);
  });

  repaint();

  // Export CSV (visible rows only)
  $('#btn-export')?.addEventListener('click', ()=>{
    const head = ['No','Date','Customer','Qty','Discount','Total','Paid','Due','Returns','Status'];
    const out  = [head.join(',')];
    rows.forEach(tr=>{
      if (tr.style.display === 'none') return;
      const tds = tr.querySelectorAll('td');
      const txt = i => (tds[i]?.innerText||'').trim();
      const esc = s => `"${String(s).replace(/"/g,'""')}"`;
      const num = s => (String(s).replace(/[, ]/g,''));
      out.push([
        esc(txt(0)), // No
        esc(txt(1)), // Date
        esc(txt(2)), // Customer
        num(txt(3)), // Qty
        num(txt(4)), // Discount
        num(txt(5)), // Total
        num(txt(6)), // Paid
        num(txt(7)), // Due
        num(txt(8)), // Returns
        esc(txt(9)), // Status
      ].join(','));
    });
    const blob = new Blob([out.join('\n')], {type:'text/csv;charset=utf-8;'});
    const url  = URL.createObjectURL(blob);
    const a    = document.createElement('a');
    const ts   = new Date().toISOString().slice(0,19).replace(/[:T]/g,'-');
    a.href = url;
    a.download = `invoices-${active}-${ts}.csv`;
    document.body.appendChild(a);
    a.click();
    a.remove();
    URL.revokeObjectURL(url);
  });
})();
</script>