<?php
declare(strict_types=1);
/** @var array $sales */
/** @var string $module_base */
/** @var array $org */

$h     = fn($s)=>htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
$base  = $module_base ?? '/apps/medflow';
$brand = '#228B22';
?>
<div x-data="salesIndex()" class="p-4 space-y-6">

  <!-- Header -->
  <div class="flex items-center justify-between">
    <h1 class="text-2xl font-semibold flex items-center gap-2">
      <i class="fa-solid fa-receipt text-[<?= $brand ?>]"></i> Sales
    </h1>
    <a href="<?= $h($base.'/sales/new') ?>"
       class="inline-flex items-center gap-2 bg-[<?= $brand ?>] text-white px-4 py-2 rounded-lg shadow hover:opacity-90 transition">
      <i class="fa-solid fa-plus"></i> <span>New Sale</span>
    </a>
  </div>

  <!-- Tabs -->
  <div class="flex items-center gap-2 border-b border-gray-200 dark:border-gray-700 text-sm">
    <button @click="tab='all'"
            :class="tab==='all' ? 'border-b-2 border-[<?= $brand ?>] font-semibold text-[<?= $brand ?>]' : 'text-gray-500'"
            class="px-3 py-2">All</button>
    <button @click="tab='today'"
            :class="tab==='today' ? 'border-b-2 border-[<?= $brand ?>] font-semibold text-[<?= $brand ?>]' : 'text-gray-500'"
            class="px-3 py-2">Today</button>
    <button @click="tab='week'"
            :class="tab==='week' ? 'border-b-2 border-[<?= $brand ?>] font-semibold text-[<?= $brand ?>]' : 'text-gray-500'"
            class="px-3 py-2">This Week</button>
  </div>

  <!-- Filter Bar -->
  <div class="flex flex-wrap items-center justify-between gap-3 text-sm">
    <div class="flex items-center gap-2">
      <input x-model="search" type="text" placeholder="Search invoice or customer..."
             class="border border-gray-300 dark:border-gray-700 rounded-lg px-3 py-2 w-64 focus:outline-none focus:ring-1 focus:ring-[<?= $brand ?>]" />
      <input x-model="from" type="date" class="border rounded px-2 py-1" />
      <span>-</span>
      <input x-model="to" type="date" class="border rounded px-2 py-1" />
    </div>

    <div class="flex items-center gap-2">
      <button @click="window.print()"
              class="px-3 py-2 border rounded-lg hover:bg-gray-100 dark:hover:bg-gray-800 flex items-center gap-2">
        <i class="fa-solid fa-print text-[<?= $brand ?>]"></i> Print
      </button>
      <button @click="exportCSV()"
              class="px-3 py-2 border rounded-lg hover:bg-gray-100 dark:hover:bg-gray-800 flex items-center gap-2">
        <i class="fa-solid fa-file-csv text-[<?= $brand ?>]"></i> Export CSV
      </button>
    </div>
  </div>

  <!-- Sales Table -->
  <div class="overflow-x-auto border border-gray-200 dark:border-gray-700 rounded-lg shadow-sm">
    <table class="w-full text-sm">
      <thead class="bg-gray-50 dark:bg-gray-800 text-gray-600 dark:text-gray-300 uppercase text-xs">
        <tr>
          <th class="px-4 py-2 text-left">Invoice</th>
          <th class="px-4 py-2 text-left">Customer</th>
          <th class="px-4 py-2 text-right">Total</th>
          <th class="px-4 py-2 text-left">Date</th>
          <th class="px-4 py-2 text-center w-20">Action</th>
        </tr>
      </thead>
      <tbody>
        <?php if (!empty($sales)): ?>
          <?php foreach ($sales as $s): ?>
            <tr class="border-t border-gray-100 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-800"
                x-show="search==='' || '<?= strtolower($h($s['invoice_no'].' '.$s['customer_name'])) ?>'.includes(search.toLowerCase())">
              <td class="px-4 py-2 font-medium text-gray-800 dark:text-gray-100"><?= $h($s['invoice_no']) ?></td>
              <td class="px-4 py-2"><?= $h($s['customer_name'] ?: '-') ?></td>
              <td class="px-4 py-2 text-right font-semibold"><?= number_format((float)$s['grand_total'], 2) ?></td>
              <td class="px-4 py-2"><?= date('d M Y H:i', strtotime($s['sold_at'] ?? 'now')) ?></td>
              <td class="px-4 py-2 text-center">
                <button
                  @click="openInvoice(<?= (int)$s['id'] ?>)"
                  class="text-[<?= $brand ?>] hover:underline flex items-center justify-center gap-1">
                  <i class="fa-solid fa-eye"></i> View
                </button>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php else: ?>
          <tr><td colspan="5" class="py-6 text-center text-gray-500">No sales yet.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>

  <!-- Modal -->
  <div x-show="modalOpen" x-transition.opacity x-cloak
       class="fixed inset-0 z-50 flex items-center justify-center bg-black/50">
    <div class="bg-white dark:bg-gray-900 w-[min(900px,92vw)] max-h-[90vh] rounded-xl shadow-xl overflow-auto">
      <div class="sticky top-0 flex items-center justify-between px-4 py-3 border-b border-gray-200 dark:border-gray-700 bg-white/90 dark:bg-gray-900/90 backdrop-blur">
        <div class="font-semibold flex items-center gap-2">
          <i class="fa-solid fa-file-invoice text-[<?= $brand ?>]"></i> Invoice
        </div>
        <button @click="modalOpen=false" class="p-2 rounded hover:bg-gray-100 dark:hover:bg-gray-800">
          <i class="fa-solid fa-xmark"></i>
        </button>
      </div>
      <div class="p-4" x-html="modalHtml"></div>
    </div>
  </div>

  <script>
    // Alpine component
    document.addEventListener('alpine:init', () => {
      Alpine.data('salesIndex', () => ({
        tab: 'all', search: '', from: '', to: '',
        modalOpen: false,
        modalHtml: '<div class="p-8 text-center text-gray-500">Loading…</div>',
        async openInvoice(id) {
          this.modalOpen = true;
          this.modalHtml = '<div class="p-8 text-center text-gray-500">Loading…</div>';
          try {
            const res = await fetch(<?= json_encode($base) ?> + '/sales/' + id + '.html',
              { headers: { 'X-Requested-With':'fetch' } });
            this.modalHtml = await res.text();
          } catch(e) {
            this.modalHtml = '<div class="p-8 text-center text-red-600">Failed to load.</div>';
          }
        }
      }));
    });

    // CSV export (unchanged)
    function exportCSV() {
      const rows = [['Invoice', 'Customer', 'Total', 'Date']];
      document.querySelectorAll('tbody tr').forEach(tr => {
        const cols = Array.from(tr.querySelectorAll('td')).map(td => td.innerText.trim());
        if (cols.length >= 4) rows.push(cols.slice(0, 4));
      });
      const csv = rows.map(r => r.map(v => `"${v.replace(/"/g,'""')}"`).join(',')).join('\n');
      const blob = new Blob([csv], {type: 'text/csv'});
      const a = document.createElement('a');
      a.href = URL.createObjectURL(blob);
      a.download = 'sales_export_'+(new Date()).toISOString().slice(0,10)+'.csv';
      a.click();
    }
  </script>
</div>