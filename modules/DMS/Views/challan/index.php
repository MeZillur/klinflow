<?php declare(strict_types=1);
/**
 * modules/DMS/Views/challan/index.php — Dispatch Challans (Tailwind v7)
 *
 * - List of all delivery challans
 * - Bulk mark as dispatched (POST /challan/mark-dispatched)
 * - Bulk "Make master challan" (GET /challan/master-from-challan?ids=1,2,3)
 */

use Shared\Csrf;

$h    = fn($s)=>htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
$rows = $rows ?? [];
$org  = $org  ?? [];

/* Robust module base like Sales view */
$module_base = rtrim((string)($module_base ?? ($org['module_base'] ?? '')), '/');
if ($module_base === '') {
    $slug = (string)($org['slug'] ?? '');
    if ($slug === '' && isset($_SERVER['REQUEST_URI']) &&
        preg_match('#^/t/([^/]+)/apps/dms#', (string)$_SERVER['REQUEST_URI'], $m)) {
        $slug = $m[1];
    }
    $module_base = $slug !== '' ? '/t/'.rawurlencode($slug).'/apps/dms' : '/apps/dms';
}

/* CSRF token (if available) */
$csrf = class_exists(Csrf::class) ? Csrf::token('tenant') : ($csrf ?? '');

/* Summary stats (fallback) */
$stats = $stats ?? [];
if (empty($stats)) {
    $stats = ['total'=>0,'waiting'=>0,'dispatched'=>0,'cancelled'=>0];
    foreach ($rows as $r) {
        $stats['total']++;
        $st = strtolower((string)($r['status'] ?? ''));
        if ($st === 'dispatched')      $stats['dispatched']++;
        elseif ($st === 'cancelled')   $stats['cancelled']++;
        else                           $stats['waiting']++;
    }
}
$totalRows = count($rows);
?>
<div class="p-4 sm:p-6 space-y-5">
  <!-- Header -->
  <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
    <div>
      <h1 class="text-lg sm:text-xl font-semibold text-slate-900 dark:text-slate-50">
        Dispatch Challans
      </h1>
      <p class="text-xs sm:text-sm text-slate-500 dark:text-slate-400">
        Organization:
        <span class="font-medium text-slate-800 dark:text-slate-100">
          <?= $h($org['name'] ?? '—') ?>
        </span>
        · <?= (int)$totalRows ?> record(s)
      </p>
    </div>

    <!-- Quick shortcuts -->
    <div class="flex flex-wrap items-center gap-2 justify-start sm:justify-end">
      <a href="<?= $h($module_base) ?>/orders"
         class="inline-flex items-center gap-2 rounded-lg border border-slate-200/70 bg-white/80 px-3 py-1.5 text-xs font-medium text-slate-700 shadow-sm hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-900/70 dark:text-slate-100">
        <i class="fa-solid fa-list text-[13px] text-slate-400"></i>
        <span>Orders</span>
      </a>
      <a href="<?= $h($module_base) ?>/orders/create"
         class="inline-flex items-center gap-2 rounded-lg border border-emerald-200/70 bg-emerald-50 px-3 py-1.5 text-xs font-semibold text-emerald-700 shadow-sm hover:bg-emerald-100 dark:border-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-100">
        <i class="fa-solid fa-plus text-[12px]"></i>
        <span>New Order</span>
      </a>
      <a href="<?= $h($module_base) ?>/inventory"
         class="inline-flex items-center gap-2 rounded-lg border border-slate-200/70 bg-white/80 px-3 py-1.5 text-xs font-medium text-slate-700 shadow-sm hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-900/70 dark:text-slate-100">
        <i class="fa-solid fa-boxes-stacked text-[13px] text-slate-400"></i>
        <span>Inventory</span>
      </a>
      <a href="<?= $h($module_base) ?>/inventory/aging"
         class="inline-flex items-center gap-2 rounded-lg border border-slate-200/70 bg-white/80 px-3 py-1.5 text-xs font-medium text-slate-700 shadow-sm hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-900/70 dark:text-slate-100">
        <i class="fa-solid fa-hourglass-half text-[13px] text-slate-400"></i>
        <span>Aging</span>
      </a>
      <a href="<?= $h($module_base) ?>/inventory/adjust"
         class="inline-flex items-center gap-2 rounded-lg border border-slate-200/70 bg-white/80 px-3 py-1.5 text-xs font-medium text-slate-700 shadow-sm hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-900/70 dark:text-slate-100">
        <i class="fa-solid fa-sliders text-[13px] text-slate-400"></i>
        <span>Adjust</span>
      </a>
      <a href="<?= $h($module_base) ?>/inventory/damage"
         class="inline-flex items-center gap-2 rounded-lg border border-slate-200/70 bg-white/80 px-3 py-1.5 text-xs font-medium text-slate-700 shadow-sm hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-900/70 dark:text-slate-100">
        <i class="fa-solid fa-triangle-exclamation text-[13px] text-amber-500"></i>
        <span>Damage</span>
      </a>
    </div>
  </div>

  <!-- Stats -->
  <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-3">
    <div class="flex items-center justify-between rounded-xl border border-slate-200/70 bg-white/80 px-3 py-3 shadow-sm dark:border-slate-700 dark:bg-slate-900/70">
      <div>
        <p class="text-[11px] font-semibold tracking-wide text-slate-500 uppercase">
          Total Challans
        </p>
        <p class="mt-1 text-xl font-semibold text-emerald-700 dark:text-emerald-400">
          <?= (int)($stats['total'] ?? $totalRows) ?>
        </p>
        <p class="text-[11px] text-slate-400">All generated challans</p>
      </div>
      <i class="fa-solid fa-boxes-stacked text-xl text-slate-300"></i>
    </div>

    <div class="flex items-center justify-between rounded-xl border border-slate-200/70 bg-white/80 px-3 py-3 shadow-sm dark:border-slate-700 dark:bg-slate-900/70">
      <div>
        <p class="text-[11px] font-semibold tracking-wide text-slate-500 uppercase">
          Waiting for dispatch
        </p>
        <p class="mt-1 text-xl font-semibold text-amber-600 dark:text-amber-400">
          <?= (int)($stats['waiting'] ?? 0) ?>
        </p>
        <p class="text-[11px] text-slate-400">Generated but not dispatched</p>
      </div>
      <i class="fa-solid fa-clock text-xl text-slate-300"></i>
    </div>

    <div class="flex items-center justify-between rounded-xl border border-slate-200/70 bg-white/80 px-3 py-3 shadow-sm dark:border-slate-700 dark:bg-slate-900/70">
      <div>
        <p class="text-[11px] font-semibold tracking-wide text-slate-500 uppercase">
          Dispatched
        </p>
        <p class="mt-1 text-xl font-semibold text-emerald-700 dark:text-emerald-400">
          <?= (int)($stats['dispatched'] ?? 0) ?>
        </p>
        <p class="text-[11px] text-slate-400">Completed dispatch</p>
      </div>
      <i class="fa-solid fa-truck text-xl text-slate-300"></i>
    </div>

    <div class="flex items-center justify-between rounded-xl border border-slate-200/70 bg-white/80 px-3 py-3 shadow-sm dark:border-slate-700 dark:bg-slate-900/70">
      <div>
        <p class="text-[11px] font-semibold tracking-wide text-slate-500 uppercase">
          Cancelled
        </p>
        <p class="mt-1 text-xl font-semibold text-rose-600 dark:text-rose-400">
          <?= (int)($stats['cancelled'] ?? 0) ?>
        </p>
        <p class="text-[11px] text-slate-400">Voided challans</p>
      </div>
      <i class="fa-solid fa-ban text-xl text-slate-300"></i>
    </div>
  </div>

  <!-- Card: toolbar + table -->
  <div class="rounded-xl border border-slate-200/70 bg-white/90 p-3 shadow-sm dark:border-slate-700 dark:bg-slate-900/70">
    <!-- Toolbar: single row -->
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between mb-3">
      <p class="text-[11px] sm:text-xs text-slate-500">
        Search, filter and export your challans.
      </p>

      <div class="flex flex-wrap items-center gap-2 justify-start sm:justify-end">
        <div class="relative">
          <input
            id="q"
            type="search"
            placeholder="Search challan / invoice / customer…"
            class="search block w-56 rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs text-slate-800 shadow-sm placeholder:text-slate-400 focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100"
            aria-label="Search challans"
          >
        </div>

        <select
          id="filterStatus"
          class="select block w-32 rounded-lg border border-slate-200 bg-white px-2.5 py-1.5 text-xs text-slate-800 shadow-sm focus:border-emerald-500 focus:outline-none focus:ring-1 focus:ring-emerald-500 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100"
          aria-label="Filter by status"
        >
          <option value="">All status</option>
          <option value="waiting">Waiting</option>
          <option value="dispatched">Dispatched</option>
          <option value="cancelled">Cancelled</option>
        </select>

        <button
          id="btnClear"
          type="button"
          class="inline-flex items-center gap-1 rounded-lg border border-slate-200 bg-white px-2.5 py-1.5 text-[11px] font-medium text-slate-600 shadow-sm hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200"
          title="Clear filters"
        >
          Clear
        </button>

        <button
          id="btnExport"
          type="button"
          class="inline-flex items-center gap-1 rounded-lg border border-slate-200 bg-white px-2.5 py-1.5 text-[11px] font-medium text-slate-600 shadow-sm hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200"
          title="Export CSV"
        >
          <i class="fa-solid fa-file-export text-[11px]"></i>
          <span>Export CSV</span>
        </button>

        <button
          id="btnSelectAll"
          type="button"
          class="inline-flex items-center gap-1 rounded-lg border border-slate-200 bg-white px-2.5 py-1.5 text-[11px] font-medium text-slate-600 shadow-sm hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200"
          title="Select all visible"
        >
          <i class="fa-solid fa-square-check text-[11px]"></i>
          <span>Select all</span>
        </button>
      </div>
    </div>

    <!-- Table -->
    <div class="table-wrap overflow-x-auto rounded-lg border border-slate-200/70 dark:border-slate-700">
      <table id="tblDispatch" class="min-w-full text-xs text-left">
        <thead class="bg-slate-50 text-[11px] font-semibold text-slate-600 dark:bg-slate-800/80 dark:text-slate-300">
          <tr>
            <th class="w-8 px-3 py-2">
              <input id="chkAll" type="checkbox" aria-label="Select all rows"
                     class="h-3.5 w-3.5 rounded border-slate-300 text-emerald-600 focus:ring-emerald-500">
            </th>
            <th class="w-14 px-3 py-2">#</th>
            <th class="px-3 py-2">Challan No</th>
            <th class="w-32 px-3 py-2">Date</th>
            <th class="px-3 py-2">Invoice</th>
            <th class="px-3 py-2">Customer</th>
            <th class="w-16 px-3 py-2">Items</th>
            <th class="w-16 px-3 py-2">Qty</th>
            <th class="w-28 px-3 py-2">Status</th>
            <th class="w-32 px-3 py-2 text-right">Actions</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-100 bg-white text-xs dark:divide-slate-800 dark:bg-slate-900">
        <?php if (!empty($rows)): foreach ($rows as $r):
          $id    = (int)($r['id'] ?? 0);
          $st    = strtolower((string)($r['status'] ?? 'waiting'));
          $badgeClass = $st === 'dispatched'
            ? 'bg-emerald-50 text-emerald-700 ring-emerald-600/15 dark:bg-emerald-900/40 dark:text-emerald-100'
            : ($st === 'cancelled'
              ? 'bg-rose-50 text-rose-700 ring-rose-600/15 dark:bg-rose-900/40 dark:text-rose-100'
              : 'bg-amber-50 text-amber-700 ring-amber-600/15 dark:bg-amber-900/40 dark:text-amber-100');
        ?>
          <tr
            data-id="<?= $id ?>"
            data-challan="<?= $h($r['challan_no'] ?? '') ?>"
            data-invoice="<?= $h($r['invoice_no'] ?? '') ?>"
            data-customer="<?= $h($r['customer_name'] ?? '') ?>"
            data-status="<?= $h($st) ?>"
            class="hover:bg-slate-50/70 dark:hover:bg-slate-800/60"
          >
            <td class="px-3 py-2 align-middle">
              <input
                type="checkbox"
                class="rowchk h-3.5 w-3.5 rounded border-slate-300 text-emerald-600 focus:ring-emerald-500"
                value="<?= $id ?>"
                aria-label="Select challan <?= $id ?>"
              >
            </td>
            <td class="px-3 py-2 align-middle text-[11px] text-slate-500">
              <?= $id ?>
            </td>
            <td class="px-3 py-2 align-middle text-[13px] font-medium text-slate-800 dark:text-slate-100">
              <?= $h($r['challan_no'] ?? '') ?>
            </td>
            <td class="px-3 py-2 align-middle text-[11px] text-slate-500 dark:text-slate-400">
              <?= $h($r['challan_date'] ?? '') ?>
            </td>
            <td class="px-3 py-2 align-middle text-[11px] text-slate-600 dark:text-slate-300">
              <?= $h($r['invoice_no'] ?? '—') ?>
            </td>
            <td class="px-3 py-2 align-middle text-[12px] text-slate-700 dark:text-slate-100">
              <?= $h($r['customer_name'] ?? '—') ?>
            </td>
            <td class="px-3 py-2 align-middle text-[11px] text-slate-600 dark:text-slate-300">
              <?= (int)($r['total_items'] ?? 0) ?>
            </td>
            <td class="px-3 py-2 align-middle text-[11px] text-slate-600 dark:text-slate-300">
              <?= (float)($r['total_qty'] ?? 0) ?>
            </td>
            <td class="px-3 py-2 align-middle">
              <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-[11px] font-medium ring-1 ring-inset <?= $badgeClass ?>">
                <?= $h(ucfirst($st === 'waiting' ? 'Waiting' : $st)) ?>
              </span>
            </td>
            <td class="px-3 py-2 align-middle">
              <div class="flex items-center justify-end gap-1.5">
                <!-- Open -->
                <a href="<?= $h($module_base) ?>/challan/<?= $id ?>"
                   class="inline-flex h-7 w-7 items-center justify-center rounded-full border border-transparent text-emerald-600 hover:border-emerald-200 hover:bg-emerald-50 dark:hover:bg-emerald-900/30"
                   title="Open challan">
                  <svg viewBox="0 0 20 20" fill="currentColor" class="h-3.5 w-3.5" aria-hidden="true">
                    <path d="M10 3.5C5.6 3.5 2 7.2 2 10s3.6 6.5 8 6.5 8-3.7 8-6.5-3.6-6.5-8-6.5Zm0 10.1A3.6 3.6 0 1 1 10 6.4a3.6 3.6 0 0 1 0 7.2Z"/>
                    <circle cx="10" cy="10" r="2.3"></circle>
                  </svg>
                  <span class="sr-only">Open</span>
                </a>

                <!-- Print -->
                <a href="<?= $h($module_base) ?>/challan/<?= $id ?>/print"
                   target="_blank"
                   class="inline-flex h-7 w-7 items-center justify-center rounded-full border border-transparent text-slate-600 hover:border-slate-200 hover:bg-slate-50 dark:hover:bg-slate-800"
                   title="Print challan">
                  <svg viewBox="0 0 20 20" fill="currentColor" class="h-3.5 w-3.5" aria-hidden="true">
                    <path d="M5 3a2 2 0 0 1 2-2h6a2 2 0 0 1 2 2v3h-2V3H7v3H5V3Z"/>
                    <path d="M4 7h12a2 2 0 0 1 2 2v4h-3v4H5v-4H2V9a2 2 0 0 1 2-2Zm3 5v4h6v-4H7Z"/>
                  </svg>
                  <span class="sr-only">Print</span>
                </a>

                <!-- Edit (only if open) -->
                <?php if ($st !== 'dispatched' && $st !== 'cancelled'): ?>
                <a href="<?= $h($module_base) ?>/challan/<?= $id ?>/edit"
                   class="inline-flex h-7 w-7 items-center justify-center rounded-full border border-transparent text-slate-600 hover:border-slate-200 hover:bg-slate-50 dark:hover:bg-slate-800"
                   title="Edit challan">
                  <svg viewBox="0 0 20 20" fill="currentColor" class="h-3.5 w-3.5" aria-hidden="true">
                    <path d="M14.6 2.3a1.5 1.5 0 0 1 2.1 2.1l-1 1L12.9 3.3l1.7-1Z"/>
                    <path d="M3 13.4 11.4 5l2.8 2.8L5.8 16.2H3v-2.8Z"/>
                    <path d="M3 17.5h14v1.5H3v-1.5Z"/>
                  </svg>
                  <span class="sr-only">Edit</span>
                </a>
                <?php endif; ?>

                <!-- Cancel -->
                <button
                  type="button"
                  class="link-cancel inline-flex h-7 w-7 items-center justify-center rounded-full border border-transparent text-rose-600 hover:border-rose-200 hover:bg-rose-50 dark:hover:bg-rose-900/30"
                  data-id="<?= $id ?>"
                  title="Cancel challan"
                >
                  <svg viewBox="0 0 20 20" fill="currentColor" class="h-3.5 w-3.5" aria-hidden="true">
                    <path d="M5.3 5.3a1 1 0 0 1 1.4 0L10 8.6l3.3-3.3a1 1 0 1 1 1.4 1.4L11.4 10l3.3 3.3a1 1 0 0 1-1.4 1.4L10 11.4l-3.3 3.3a1 1 0 0 1-1.4-1.4L8.6 10 5.3 6.7a1 1 0 0 1 0-1.4Z"/>
                  </svg>
                  <span class="sr-only">Cancel</span>
                </button>
              </div>
            </td>
          </tr>
        <?php endforeach; else: ?>
          <tr>
            <td colspan="10" class="px-4 py-8 text-center text-sm text-slate-500">
              No challans yet.
            </td>
          </tr>
        <?php endif; ?>
        </tbody>
      </table>
    </div>

    <!-- Bulk actions -->
    <div class="mt-3 flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
      <p class="text-[11px] text-slate-500">
        Tip: Use the checkmarks to select multiple challans for bulk actions.
      </p>
      <div class="flex flex-wrap items-center gap-2 justify-start sm:justify-end">
        <button
          id="btnMarkDispatched"
          type="button"
          class="inline-flex items-center gap-2 rounded-lg bg-emerald-600 px-3.5 py-1.75 text-xs font-semibold text-white shadow-sm hover:bg-emerald-700 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-1"
          title="Mark selected as dispatched"
        >
          <i class="fa-solid fa-truck text-[13px]"></i>
          <span>Mark dispatched</span>
        </button>
        <button
          id="btnMakeMaster"
          type="button"
          class="inline-flex items-center gap-2 rounded-lg border border-emerald-600 bg-white px-3.5 py-1.75 text-xs font-semibold text-emerald-700 shadow-sm hover:bg-emerald-50 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-1 dark:border-emerald-500 dark:bg-slate-900 dark:text-emerald-200 dark:hover:bg-emerald-900/30"
          title="Create master challan from selected"
        >
          <i class="fa-solid fa-layer-group text-[13px]"></i>
          <span>Make master challan</span>
        </button>
      </div>
    </div>
  </div>

  <!-- How to use -->
  <div class="rounded-lg border border-dashed border-slate-300/80 bg-slate-50/70 px-4 py-3 text-[11px] text-slate-600 dark:border-slate-700 dark:bg-slate-900/60 dark:text-slate-300">
    <p class="font-semibold text-[11px] text-slate-700 dark:text-slate-100 mb-1.5">
      How to use this page
    </p>
    <ul class="list-disc pl-4 space-y-0.5">
      <li>Use the search box and status filter to quickly find specific challans by number, invoice or customer.</li>
      <li>Select one or more rows with the checkboxes to apply bulk actions from the bottom buttons.</li>
      <li><span class="font-semibold">Mark dispatched</span> updates the status for all selected challans.</li>
      <li><span class="font-semibold">Make master challan</span> opens a combined dispatch (master challan) using the selected challans.</li>
      <li>Use the icon buttons on each row to open, print, edit or cancel individual challans.</li>
    </ul>
  </div>
</div>

<!-- Hidden forms for POST actions -->
<form
  id="frmMark"
  method="post"
  action="<?= $h($module_base) ?>/challan/mark-dispatched"
  class="hidden"
>
  <?php if ($csrf): ?>
    <input type="hidden" name="_csrf" value="<?= $h($csrf) ?>">
  <?php endif; ?>
  <input type="hidden" name="ids" id="markIds" value="">
</form>

<form
  id="frmCancel"
  method="post"
  action="<?= $h($module_base) ?>/challan/cancel"
  class="hidden"
>
  <?php if ($csrf): ?>
    <input type="hidden" name="_csrf" value="<?= $h($csrf) ?>">
  <?php endif; ?>
  <input type="hidden" name="id" id="cancelId" value="">
</form>

<script>
(function(){
  const q            = document.getElementById('q');
  const filterStatus = document.getElementById('filterStatus');
  const btnClear     = document.getElementById('btnClear');
  const tbl          = document.getElementById('tblDispatch');
  const tbody        = tbl ? tbl.querySelector('tbody') : null;
  const chkAll       = document.getElementById('chkAll');
  const btnSelectAll = document.getElementById('btnSelectAll');
  const btnMark      = document.getElementById('btnMarkDispatched');
  const btnMakeMaster= document.getElementById('btnMakeMaster');
  const btnExport    = document.getElementById('btnExport');

  if (!tbody) return;

  const rows = Array.from(tbody.querySelectorAll('tr[data-id]'));
  const rowMap = rows.map(r => ({
    el: r,
    text: (r.dataset.challan + ' ' + r.dataset.invoice + ' ' +
           r.dataset.customer + ' ' + r.dataset.status).toLowerCase()
  }));

  function applyFilter() {
    const term = (q && q.value || '').trim().toLowerCase();
    const st   = (filterStatus && filterStatus.value || '').trim().toLowerCase();

    for (const r of rowMap) {
      const matchesTerm = !term || r.text.indexOf(term) !== -1;
      const rowStatus   = (r.el.dataset.status || '').toLowerCase();
      const matchesStatus = !st || (
        (st === 'waiting' &&
          rowStatus !== 'dispatched' &&
          rowStatus !== 'cancelled') ||
        rowStatus === st
      );

      if (matchesTerm && matchesStatus) {
        r.el.style.display = '';
      } else {
        r.el.style.display = 'none';
        const cb = r.el.querySelector('.rowchk');
        if (cb) cb.checked = false;
      }
    }
    if (chkAll) chkAll.checked = false;
  }

  let tmr = null;
  if (q) {
    q.addEventListener('input', ()=>{
      clearTimeout(tmr);
      tmr = setTimeout(applyFilter,150);
    });
  }
  if (filterStatus) {
    filterStatus.addEventListener('change', applyFilter);
  }
  if (btnClear) {
    btnClear.addEventListener('click', (e)=>{
      e.preventDefault();
      if (q) q.value='';
      if (filterStatus) filterStatus.value='';
      applyFilter();
    });
  }

  // Select visible rows
  function selectedIds() {
    return Array.from(tbody.querySelectorAll('.rowchk:checked')).map(cb => cb.value);
  }

  if (btnSelectAll) {
    btnSelectAll.addEventListener('click', (e)=>{
      e.preventDefault();
      Array.from(tbody.querySelectorAll('tr')).forEach(tr=>{
        if (tr.style.display === 'none') return;
        const cb = tr.querySelector('.rowchk');
        if (cb) cb.checked = true;
      });
      if (chkAll) chkAll.checked = true;
    });
  }

  if (chkAll) {
    chkAll.addEventListener('change', ()=> {
      const on = chkAll.checked;
      Array.from(tbody.querySelectorAll('.rowchk')).forEach(cb => {
        if (cb.closest('tr').style.display !== 'none') cb.checked = on;
      });
    });
  }

  // Mark dispatched (POST)
  if (btnMark) {
    btnMark.addEventListener('click', (e)=> {
      e.preventDefault();
      const ids = selectedIds();
      if (!ids.length) { alert('Please select at least one challan.'); return; }
      if (!confirm('Mark ' + ids.length + ' challan(s) as dispatched?')) return;
      document.getElementById('markIds').value = ids.join(',');
      document.getElementById('frmMark').submit();
    });
  }

  // Make master challan (GET /challan/master-from-challan?ids=1,2,3)
  if (btnMakeMaster) {
    btnMakeMaster.addEventListener('click', (e)=>{
      e.preventDefault();
      const ids = selectedIds();
      if (!ids.length) {
        alert('Please select at least one challan to create a master challan.');
        return;
      }
      if (!confirm('Create a master challan for ' + ids.length + ' challan(s)?')) return;

      const base   = '<?= $h($module_base) ?>';
      const params = new URLSearchParams();
      params.set('ids', ids.join(','));
      const url = base + '/challan/master-from-challan?' + params.toString();
      window.location.href = url;
    });
  }

  // Cancel single challan (POST)
  tbody.addEventListener('click', (e)=>{
    const t = e.target;
    if (!t) return;
    if (t.classList.contains('link-cancel') || t.closest('.link-cancel')) {
      const btn = t.classList.contains('link-cancel') ? t : t.closest('.link-cancel');
      e.preventDefault();
      const id = btn.dataset.id;
      if (!id) return;
      if (!confirm('Cancel challan #' + id + '?')) return;
      document.getElementById('cancelId').value = id;
      document.getElementById('frmCancel').submit();
    }
  });

  // Export CSV: /challan/export?q=...&status=...
  if (btnExport) {
    btnExport.addEventListener('click', (e)=>{
      e.preventDefault();
      const params = new URLSearchParams();
      if (q && q.value) params.set('q', q.value);
      if (filterStatus && filterStatus.value) params.set('status', filterStatus.value);
      const url = '<?= $h($module_base) ?>/challan/export' +
        (params.toString() ? ('?' + params.toString()) : '');
      window.location = url;
    });
  }

  applyFilter();
})();
</script>