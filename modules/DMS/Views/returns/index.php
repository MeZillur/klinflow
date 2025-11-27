<?php
/**
 * modules/DMS/Views/returns/index.php
 * Content-only view. Shell wraps.
 * Brand: #228B22
 *
 * Expects:
 *   - $module_base
 *   - $rows (from SalesReturnsController::index)
 */

declare(strict_types=1);

$h = fn($v)=>htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');

/* ---------- Canonical module base ---------- */
$base = rtrim((string)($module_base ?? ''), '/');
if ($base === '') {
    $slug = (string)($_SESSION['tenant_org']['slug'] ?? '');
    if ($slug === '' && isset($_SERVER['REQUEST_URI'])
        && preg_match('#^/t/([^/]+)/apps/dms#', (string)$_SERVER['REQUEST_URI'], $m)) {
        $slug = $m[1];
    }
    $base = $slug ? '/t/'.rawurlencode($slug).'/apps/dms' : '/apps/dms';
}

/* ---------- Normalize rows (tolerant) ---------- */
$rows = is_array($rows ?? null) ? $rows : [];

$norm = [];
$totalAmount = 0.0;
$draftCount  = 0;
$postedCount = 0;

foreach ($rows as $r) {
    $id   = (int)($r['id'] ?? 0);
    $no   = (string)($r['return_no'] ?? '');
    if ($no === '' && $id) $no = 'RET-'.str_pad((string)$id, 5, '0', STR_PAD_LEFT);

    $date = substr((string)($r['return_date'] ?? ''), 0, 10);
    $custName = trim((string)($r['customer_name'] ?? $r['customer'] ?? ''));
    $custId   = (int)($r['customer_id'] ?? 0);
    if ($custName === '' && $custId > 0) $custName = 'Customer #'.$custId;
    if ($custName === '') $custName = '—';

    $gt   = (float)($r['grand_total'] ?? 0);
    $st   = strtolower((string)($r['status'] ?? 'draft'));

    $totalAmount += $gt;
    if (in_array($st, ['posted','confirmed'], true)) $postedCount++;
    else $draftCount++;

    $norm[] = [
        'id'    => $id,
        'no'    => $no,
        'date'  => $date,
        'cust'  => $custName,
        'total' => $gt,
        'st'    => $st,
    ];
}

/* ---------- Status badge palette ---------- */
$badge = function(string $st): string {
    return match ($st) {
        'posted', 'confirmed' => 'bg-emerald-50 text-emerald-700 border border-emerald-200',
        'cancelled', 'void'   => 'bg-rose-50 text-rose-700 border border-rose-200',
        default                => 'bg-slate-50 text-slate-700 border border-slate-200',
    };
};

$fmt2 = fn(float $n)=>number_format($n, 2, '.', ',');
?>
<div class="space-y-5">
  <!-- Header -->
  <div class="flex items-start justify-between gap-3 flex-wrap">
    <div class="space-y-1">
      <h1 class="text-[18px] md:text-[20px] font-semibold">
        Sales Returns
      </h1>
      <p class="text-[12px] text-gray-500">
        Review all delivery returns, check status, and export a clean history up to 2035.
      </p>
    </div>

    <div class="flex flex-col items-end gap-1">
      <!-- Primary actions -->
      <div class="flex items-center gap-2">
        <a href="<?= $h($base) ?>/returns/create"
           class="px-3 py-1.5 rounded-lg bg-[#228B22] text-white text-[13px] hover:opacity-90">
          + New Return
        </a>

        <button id="btn-export"
                class="px-3 py-1.5 rounded-lg border text-[13px] hover:bg-gray-50 dark:hover:bg-gray-800">
          Export CSV
        </button>
      </div>

      <!-- Section page menu (Sales & Dispatch cluster) -->
      <div class="flex items-center flex-wrap gap-1 text-[11px] text-gray-600 mt-1">
        <a href="<?= $h($base) ?>/orders"
           class="px-2 py-1 rounded-full hover:bg-gray-100 dark:hover:bg-gray-800">
          Orders
        </a>
        <a href="<?= $h($base) ?>/sales"
           class="px-2 py-1 rounded-full hover:bg-gray-100 dark:hover:bg-gray-800">
          Invoices
        </a>
        <a href="<?= $h($base) ?>/returns"
           class="px-2 py-1 rounded-full bg-gray-900 text-white dark:bg-gray-100 dark:text-gray-900">
          Returns
        </a>
        <a href="<?= $h($base) ?>/challan"
           class="px-2 py-1 rounded-full hover:bg-gray-100 dark:hover:bg-gray-800">
          Dispatch Challans
        </a>
        <a href="<?= $h($base) ?>/payments"
           class="px-2 py-1 rounded-full hover:bg-gray-100 dark:hover:bg-gray-800">
          Payments
        </a>
        <a href="<?= $h($base) ?>/reports/damage"
           class="px-2 py-1 rounded-full hover:bg-gray-100 dark:hover:bg-gray-800">
          Damage Reports
        </a>
      </div>
    </div>
  </div>

  <!-- Summary cards -->
  <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
    <div class="rounded-2xl border bg-white dark:bg-gray-900 px-3 py-3 flex items-center justify-between">
      <div>
        <div class="text-[11px] text-gray-500">Total Returns</div>
        <div class="text-[18px] font-semibold mt-0.5"><?= count($norm) ?></div>
      </div>
      <div class="text-[11px] px-2 py-1 rounded-full bg-gray-50 text-gray-600 border border-gray-100">
        All time
      </div>
    </div>

    <div class="rounded-2xl border bg-white dark:bg-gray-900 px-3 py-3 flex items-center justify-between">
      <div>
        <div class="text-[11px] text-gray-500">Total Amount</div>
        <div class="text-[18px] font-semibold mt-0.5">
          ৳ <?= $fmt2($totalAmount) ?>
        </div>
      </div>
      <div class="text-[11px] px-2 py-1 rounded-full bg-emerald-50 text-emerald-700 border border-emerald-100">
        BDT
      </div>
    </div>

    <div class="rounded-2xl border bg-white dark:bg-gray-900 px-3 py-3 flex items-center justify-between">
      <div>
        <div class="text-[11px] text-gray-500">Status Snapshot</div>
        <div class="mt-0.5 flex items-center gap-3 text-[12px]">
          <span class="inline-flex items-center gap-1">
            <span class="w-2 h-2 rounded-full bg-emerald-500"></span><?= (int)$postedCount ?> posted
          </span>
          <span class="inline-flex items-center gap-1">
            <span class="w-2 h-2 rounded-full bg-slate-400"></span><?= (int)$draftCount ?> draft
          </span>
        </div>
      </div>
      <div class="text-[11px] px-2 py-1 rounded-full bg-gray-50 text-gray-600 border border-gray-100">
        Live
      </div>
    </div>
  </div>

  <!-- Filters -->
  <div class="flex items-center justify-between gap-3 flex-wrap">
    <!-- Tabs -->
    <div class="flex items-center flex-wrap gap-1.5" id="ret-tabs">
      <?php
      $tabDefs = [
          ['key'=>'all',      'label'=>'All'],
          ['key'=>'draft',    'label'=>'Draft'],
          ['key'=>'posted',   'label'=>'Posted'],
          ['key'=>'cancelled','label'=>'Cancelled'],
      ];
      ?>
      <?php foreach ($tabDefs as $i => $t): ?>
        <button type="button"
                data-key="<?= $h($t['key']) ?>"
                class="ret-tab px-2.5 py-1.5 rounded-full text-[12px] border
                       <?= $i === 0 ? 'bg-[#228B22] text-white border-[#228B22]' : 'bg-white dark:bg-gray-900 hover:bg-gray-50' ?>">
          <?= $h($t['label']) ?>
        </button>
      <?php endforeach; ?>
    </div>

    <!-- Search -->
    <div class="relative w-full sm:w-80">
      <input id="ret-search"
             type="text"
             placeholder="Search return no or customer…"
             class="w-full text-[13px] rounded-lg border px-3 py-2 bg-white dark:bg-gray-900"
             autocomplete="off">
      <span class="absolute right-2 top-2.5 text-gray-400 text-xs">⌘/Ctrl + K</span>
    </div>
  </div>

  <!-- Table -->
  <div class="overflow-x-auto rounded-2xl border bg-white dark:bg-gray-900">
    <table class="min-w-full text-[13px]" id="ret-table">
      <thead class="bg-gray-50 dark:bg-gray-800 text-gray-600 dark:text-gray-300">
        <tr>
          <th class="px-3 py-2 text-left">Return No</th>
          <th class="px-3 py-2 text-left">Customer</th>
          <th class="px-3 py-2 text-left">Date</th>
          <th class="px-3 py-2 text-right">Amount (BDT)</th>
          <th class="px-3 py-2 text-center">Status</th>
          <th class="px-3 py-2 text-right">Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php if (!$norm): ?>
          <tr>
            <td colspan="6" class="px-3 py-8 text-center text-sm text-gray-500">
              No returns recorded yet.
            </td>
          </tr>
        <?php else: ?>
          <?php foreach ($norm as $r): ?>
            <tr class="border-t hover:bg-gray-50/60 dark:hover:bg-gray-800"
                data-row
                data-no="<?= $h(strtolower($r['no'])) ?>"
                data-cust="<?= $h(strtolower($r['cust'])) ?>"
                data-status="<?= $h(strtolower($r['st'])) ?>"
                data-total="<?= $h(number_format((float)$r['total'], 6, '.', '')) ?>">
              <td class="px-3 py-2">
                <a href="<?= $h($base) ?>/returns/<?= (int)$r['id'] ?>"
                   class="font-medium text-emerald-700 hover:underline">
                  <?= $h($r['no']) ?>
                </a>
              </td>
              <td class="px-3 py-2">
                <span class="inline-block max-w-[220px] truncate">
                  <?= $h($r['cust']) ?>
                </span>
              </td>
              <td class="px-3 py-2"><?= $h($r['date'] ?: '—') ?></td>
              <td class="px-3 py-2 text-right">৳ <?= $fmt2((float)$r['total']) ?></td>
              <td class="px-3 py-2 text-center">
                <span class="px-2 py-0.5 rounded-full text-[11px] <?= $badge($r['st']) ?>">
                  <?= $h($r['st'] ?: 'draft') ?>
                </span>
              </td>
              <td class="px-3 py-2 text-right whitespace-nowrap">
                <div class="inline-flex gap-1 items-center">
                  <a href="<?= $h($base) ?>/returns/<?= (int)$r['id'] ?>"
                     class="px-2.5 py-1 rounded border text-[12px] hover:bg-gray-50 dark:hover:bg-gray-800">
                    View
                  </a>
                  <a href="<?= $h($base) ?>/returns/<?= (int)$r['id'] ?>/print?autoprint=1"
                     class="px-2.5 py-1 rounded border text-[12px] hover:bg-gray-50 dark:hover:bg-gray-800">
                    Print
                  </a>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
      <?php if ($norm): ?>
      <tfoot class="bg-gray-50 dark:bg-gray-800">
        <tr>
          <td colspan="3" class="px-3 py-3 text-[12px] text-gray-600">
            Showing <span id="ret-count"><?= count($norm) ?></span> returns
          </td>
          <td class="px-3 py-3 text-right font-semibold">
            ৳ <span id="ret-sum"><?= $fmt2($totalAmount) ?></span>
          </td>
          <td colspan="2"></td>
        </tr>
      </tfoot>
      <?php endif; ?>
    </table>
  </div>

  <!-- How to use this page -->
  <div class="mt-4 rounded-2xl border bg-gray-50 dark:bg-gray-900/60 px-4 py-3 text-[12px] text-gray-700 dark:text-gray-200">
    <div class="font-semibold text-[13px] mb-1">How to use this page</div>
    <ul class="list-disc ml-4 space-y-1">
      <li><strong>Create new return:</strong> Click <span class="px-1 rounded bg-[#228B22] text-white">+ New Return</span> to start a fresh delivery return.</li>
      <li><strong>Navigate between sections:</strong> Use the mini menu on the top-right to jump to Orders, Invoices, Returns, Dispatch Challans, Payments, or Damage Reports.</li>
      <li><strong>Filter quickly:</strong> Use the status tabs (All / Draft / Posted / Cancelled) plus the search box to narrow down by return no or customer.</li>
      <li><strong>Review and print:</strong> Use <em>View</em> to open a return detail, and <em>Print</em> for a ready-to-share document.</li>
      <li><strong>Export history:</strong> Click <em>Export CSV</em> to download only the currently visible (filtered) rows for Excel or backup.</li>
    </ul>
  </div>
</div>

<script>
(function(){
  const $  = s => document.querySelector(s);
  const $$ = s => Array.from(document.querySelectorAll(s));

  const searchInput = $('#ret-search');
  const rows        = $$('#ret-table tbody tr[data-row]');
  const tabs        = $$('#ret-tabs .ret-tab');
  const countEl     = $('#ret-count');
  const sumEl       = $('#ret-sum');
  let activeFilter  = 'all';

  function matchFilter(row){
    const st = (row.dataset.status || '').toLowerCase();
    switch (activeFilter) {
      case 'draft':     return !['posted','confirmed'].includes(st);
      case 'posted':    return ['posted','confirmed'].includes(st);
      case 'cancelled': return ['cancelled','void'].includes(st);
      default:          return true;
    }
  }
  function matchQuery(row, q){
    if (!q) return true;
    const no   = (row.dataset.no || '');
    const cust = (row.dataset.cust || '');
    return no.includes(q) || cust.includes(q);
  }

  function repaint(){
    const q = (searchInput?.value || '').trim().toLowerCase();
    let visible = 0;
    let sum = 0;

    rows.forEach(tr => {
      const show = matchFilter(tr) && matchQuery(tr, q);
      tr.style.display = show ? '' : 'none';
      if (show) {
        visible++;
        sum += parseFloat(tr.dataset.total || '0') || 0;
      }
    });

    if (countEl) countEl.textContent = String(visible);
    if (sumEl) {
      try {
        sumEl.textContent = new Intl.NumberFormat(undefined, {
          minimumFractionDigits: 2,
          maximumFractionDigits: 2
        }).format(sum);
      } catch (e) {
        sumEl.textContent = sum.toFixed(2);
      }
    }
  }

  // Tabs behaviour
  tabs.forEach(btn => {
    btn.addEventListener('click', () => {
      activeFilter = (btn.dataset.key || 'all').toLowerCase();
      tabs.forEach(b => b.classList.remove('bg-[#228B22]','text-white','border-[#228B22]'));
      btn.classList.add('bg-[#228B22]','text-white','border-[#228B22]');
      repaint();
    });
  });

  // Search debounce
  let t = null;
  searchInput?.addEventListener('input', () => {
    clearTimeout(t);
    t = setTimeout(repaint, 140);
  });

  // Keyboard shortcut: focus search
  document.addEventListener('keydown', (e) => {
    if ((e.metaKey || e.ctrlKey) && e.key.toLowerCase() === 'k') {
      e.preventDefault();
      searchInput?.focus();
    }
  });

  repaint();

  // Export CSV for visible rows
  $('#btn-export')?.addEventListener('click', () => {
    const head = ['Return No','Customer','Date','Amount (BDT)','Status'];
    const out  = [head.join(',')];

    rows.forEach(tr => {
      if (tr.style.display === 'none') return;
      const tds = tr.querySelectorAll('td');
      const txt = i => (tds[i]?.innerText || '').trim();
      const esc = s => '"' + String(s).replace(/"/g,'""') + '"';
      const num = s => String(s).replace(/[, ]/g,'');

      out.push([
        esc(txt(0)),      // Return No
        esc(txt(1)),      // Customer
        esc(txt(2)),      // Date
        num(txt(3)),      // Amount
        esc(txt(4)),      // Status
      ].join(','));
    });

    const blob = new Blob([out.join('\n')], {type:'text/csv;charset=utf-8;'});
    const url  = URL.createObjectURL(blob);
    const a    = document.createElement('a');
    const ts   = new Date().toISOString().slice(0,19).replace(/[:T]/g,'-');
    a.href = url;
    a.download = `sales-returns-${activeFilter}-${ts}.csv`;
    document.body.appendChild(a);
    a.click();
    a.remove();
    URL.revokeObjectURL(url);
  });
})();
</script>