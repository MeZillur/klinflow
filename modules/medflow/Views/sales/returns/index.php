<?php
declare(strict_types=1);
/** @var string $module_base */
/** @var array $returns */

$h = fn($s)=>htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
$base  = $module_base ?? '/apps/medflow';
$brand = '#228B22';
?>
<div x-data="{ tab: 'all', q: '', from: '', to: '' }" class="p-4 space-y-6">
  <div class="flex items-center justify-between">
    <h1 class="text-2xl font-semibold flex items-center gap-2">
      <i class="fa-solid fa-rotate-left text-[<?= $brand ?>]"></i> Sales Returns
    </h1>
    <a href="<?= $h($base.'/sales/returns/new') ?>"
       class="inline-flex items-center gap-2 bg-[<?= $brand ?>] text-white px-4 py-2 rounded-lg shadow hover:opacity-90">
      <i class="fa-solid fa-plus"></i> New Return
    </a>
  </div>

  <div class="flex items-center gap-3 border-b border-gray-200 dark:border-gray-700 text-sm">
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

  <div class="flex flex-wrap items-center justify-between gap-3 text-sm">
    <div class="flex items-center gap-2">
      <input x-model="q" type="text" placeholder="Search return no. or customer..."
             class="border border-gray-300 dark:border-gray-700 rounded-lg px-3 py-2 w-72 focus:outline-none focus:ring-1 focus:ring-[<?= $brand ?>]" />
      <input x-model="from" type="date" class="border rounded px-2 py-1" />
      <span>-</span>
      <input x-model="to" type="date" class="border rounded px-2 py-1" />
    </div>
    <div class="flex items-center gap-2">
      <button onclick="window.print()"
              class="px-3 py-2 border rounded-lg hover:bg-gray-100 dark:hover:bg-gray-800 flex items-center gap-2">
        <i class="fa-solid fa-print text-[<?= $brand ?>]"></i> Print
      </button>
      <button onclick="exportCSV('#returnsTable')"
              class="px-3 py-2 border rounded-lg hover:bg-gray-100 dark:hover:bg-gray-800 flex items-center gap-2">
        <i class="fa-solid fa-file-csv text-[<?= $brand ?>]"></i> Export CSV
      </button>
    </div>
  </div>

  <div class="overflow-x-auto border border-gray-200 dark:border-gray-700 rounded-lg shadow-sm">
    <table id="returnsTable" class="w-full text-sm">
      <thead class="bg-gray-50 dark:bg-gray-800 text-gray-600 dark:text-gray-300 uppercase text-xs">
        <tr>
          <th class="px-4 py-2 text-left">Return No</th>
          <th class="px-4 py-2 text-left">Customer</th>
          <th class="px-4 py-2 text-right">Refund</th>
          <th class="px-4 py-2 text-left">Date</th>
          <th class="px-4 py-2 text-center w-20">Action</th>
        </tr>
      </thead>
      <tbody>
        <?php if (!empty($returns)): ?>
          <?php foreach ($returns as $r): ?>
            <?php
              $needle = strtolower($r['return_no'].' '.$r['customer_name']);
            ?>
            <tr class="border-t border-gray-100 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-800"
                x-show="q==='' || '<?= $h($needle) ?>'.includes(q.toLowerCase())">
              <td class="px-4 py-2 font-medium"><?= $h($r['return_no']) ?></td>
              <td class="px-4 py-2"><?= $h($r['customer_name'] ?: '-') ?></td>
              <td class="px-4 py-2 text-right font-semibold"><?= number_format((float)($r['refund_total'] ?? 0), 2) ?></td>
              <td class="px-4 py-2"><?= $h($r['returned_at'] ?? '') ?></td>
              <td class="px-4 py-2 text-center">
                <a href="<?= $h($base.'/sales/returns/'.(int)$r['id']) ?>"
                   class="text-[<?= $brand ?>] hover:underline"><i class="fa-regular fa-eye"></i> View</a>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php else: ?>
          <tr><td colspan="5" class="py-6 text-center text-gray-500">No returns yet.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<script>
function exportCSV(selector){
  const table = document.querySelector(selector);
  if(!table) return;
  const rows = [];
  rows.push(["Return No","Customer","Refund","Date"]);
  table.querySelectorAll("tbody tr").forEach(tr=>{
    const tds = Array.from(tr.querySelectorAll("td")).map(td=>td.innerText.trim());
    if (tds.length>=4) rows.push(tds.slice(0,4));
  });
  const csv = rows.map(r=>r.map(v=>`"${v.replace(/"/g,'""')}"`).join(',')).join('\n');
  const blob = new Blob([csv],{type:'text/csv'});
  const a = document.createElement('a');
  a.href = URL.createObjectURL(blob);
  a.download = 'sales_returns_'+(new Date()).toISOString().slice(0,10)+'.csv';
  a.click();
}
</script>