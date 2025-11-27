<?php
declare(strict_types=1);
/** @var array $byMonth, $byCat, $banks, $cats, $filter */
if (!function_exists('h')) { function h($v){ return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8'); } }
$base = $module_base ?? '';
$labelsMonth = array_map(fn($r)=>$r['ym'], $byMonth);
$dataMonth   = array_map(fn($r)=>(float)$r['total'], $byMonth);
$labelsCat   = array_map(fn($r)=>$r['name'], $byCat);
$dataCat     = array_map(fn($r)=>(float)$r['total'], $byCat);
?>
<div class="mb-5">
  <h2 class="text-xl font-semibold">Expense Reports</h2>
  <p class="text-sm text-slate-500">Overview by month and by category. Filter by date/bank/category.</p>
</div>

<form method="get" class="mb-6 grid grid-cols-1 md:grid-cols-5 gap-2">
  <input type="date" name="from" value="<?= h($filter['from'] ?? '') ?>" class="rounded-lg border px-3 py-2" placeholder="From">
  <input type="date" name="to"   value="<?= h($filter['to']   ?? '') ?>" class="rounded-lg border px-3 py-2" placeholder="To">

  <select name="bank_account_id" class="rounded-lg border px-3 py-2">
    <option value="">All Banks</option>
    <?php foreach ($banks as $b): ?>
      <option value="<?= (int)$b['id'] ?>" <?= (int)($filter['bank_account_id']??0)===(int)$b['id']?'selected':'' ?>>
        <?= h($b['name']) ?>
      </option>
    <?php endforeach; ?>
  </select>

  <select name="category_id" class="rounded-lg border px-3 py-2">
    <option value="">All Categories</option>
    <?php foreach ($cats as $c): ?>
      <option value="<?= (int)$c['id'] ?>" <?= (int)($filter['category_id']??0)===(int)$c['id']?'selected':'' ?>>
        <?= h($c['name']) ?>
      </option>
    <?php endforeach; ?>
  </select>

  <div class="flex gap-2">
    <button class="px-3 py-2 rounded-lg bg-slate-100 hover:bg-slate-200">Apply</button>
    <a href="<?= h($base) ?>/reports" class="px-3 py-2 rounded-lg bg-slate-50 hover:bg-slate-100">Reset</a>
  </div>
</form>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
  <div class="p-4 rounded-xl border bg-white dark:bg-gray-800">
    <div class="mb-2 font-medium">By Month</div>
    <canvas id="expMonth"></canvas>
  </div>

  <div class="p-4 rounded-xl border bg-white dark:bg-gray-800">
    <div class="mb-2 font-medium">By Category</div>
    <canvas id="expCat"></canvas>
  </div>
</div>

<script>
(function(){
  const labelsM = <?= json_encode($labelsMonth) ?>;
  const dataM   = <?= json_encode($dataMonth) ?>;
  const labelsC = <?= json_encode($labelsCat) ?>;
  const dataC   = <?= json_encode($dataCat) ?>;

  // Month (line)
  const ctx1 = document.getElementById('expMonth');
  if (ctx1 && typeof Chart!=='undefined') {
    new Chart(ctx1, {
      type: 'line',
      data: {
        labels: labelsM,
        datasets: [{ label: 'Total', data: dataM, tension: 0.3 }]
      },
      options: {
        responsive: true,
        plugins: { legend: { display: false } },
        scales: { y: { beginAtZero: true } }
      }
    });
  }

  // Category (bar)
  const ctx2 = document.getElementById('expCat');
  if (ctx2 && typeof Chart!=='undefined') {
    new Chart(ctx2, {
      type: 'bar',
      data: {
        labels: labelsC,
        datasets: [{ label: 'Total', data: dataC }]
      },
      options: {
        indexAxis: 'y',
        responsive: true,
        plugins: { legend: { display: false } },
        scales: { x: { beginAtZero: true } }
      }
    });
  }
})();
</script>