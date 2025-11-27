<?php
declare(strict_types=1);
if (!function_exists('h')) { function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); } }

$stakeholders = $stakeholders ?? [];
$kpis         = $kpis ?? ['visits'=>[], 'sales'=>[]];
$module_base  = $module_base ?? '';

// Aggregate totals safely
$total = [
  'visits_planned' => 0,
  'visits_done'    => 0,
  'orders_count'   => 0,
  'revenue'        => 0.0
];

foreach ($kpis['visits'] as $sid => $byRange) {
    foreach ($byRange as $days => $vals) {
        $total['visits_planned'] += (int)($vals['planned'] ?? 0);
        $total['visits_done']    += (int)($vals['done'] ?? 0);
    }
}
foreach ($kpis['sales'] as $sid => $byRange) {
    foreach ($byRange as $days => $amt) {
        $total['orders_count'] += 1; // approximate count
        $total['revenue']      += (float)$amt;
    }
}

// chart data placeholder
$labels = ['Mon','Tue','Wed','Thu','Fri','Sat','Sun'];
$series = [
  ['label'=>'Visits Done','data'=>[3,4,6,5,7,4,3]],
  ['label'=>'Orders','data'=>[2,2,3,4,2,3,2]],
];
?>
<div class="mb-4 flex items-center justify-between">
  <h1 class="text-xl font-semibold">Stakeholder Performance</h1>
  <a href="<?= h($module_base) ?>/stakeholders" class="px-3 py-2 rounded-lg border hover:bg-slate-100 dark:hover:bg-gray-700">Back to Stakeholders</a>
</div>

<!-- KPIs -->
<div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
  <div class="p-4 rounded-xl border bg-white dark:bg-gray-800 shadow-sm">
    <div class="text-xs uppercase text-slate-500">Visits Planned</div>
    <div class="text-2xl font-semibold mt-1"><?= (int)$total['visits_planned'] ?></div>
  </div>
  <div class="p-4 rounded-xl border bg-white dark:bg-gray-800 shadow-sm">
    <div class="text-xs uppercase text-slate-500">Visits Done</div>
    <div class="text-2xl font-semibold mt-1 text-green-600 dark:text-green-400"><?= (int)$total['visits_done'] ?></div>
  </div>
  <div class="p-4 rounded-xl border bg-white dark:bg-gray-800 shadow-sm">
    <div class="text-xs uppercase text-slate-500">Orders</div>
    <div class="text-2xl font-semibold mt-1"><?= (int)$total['orders_count'] ?></div>
  </div>
  <div class="p-4 rounded-xl border bg-white dark:bg-gray-800 shadow-sm">
    <div class="text-xs uppercase text-slate-500">Revenue</div>
    <div class="text-2xl font-semibold mt-1 text-emerald-600 dark:text-emerald-400">৳ <?= number_format((float)$total['revenue'], 2) ?></div>
  </div>
</div>

<!-- Stakeholder table -->
<div class="rounded-xl border bg-white dark:bg-gray-800 shadow-sm overflow-x-auto mb-6">
  <table class="min-w-full text-sm">
    <thead class="bg-slate-50 dark:bg-gray-700 text-slate-600 dark:text-gray-200 uppercase text-xs">
      <tr>
        <th class="px-4 py-2 text-left">Code</th>
        <th class="px-4 py-2 text-left">Name</th>
        <th class="px-4 py-2 text-center">Visits (30d)</th>
        <th class="px-4 py-2 text-center">Visits (90d)</th>
        <th class="px-4 py-2 text-center">Sales (৳)</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($stakeholders as $s): 
        $sid = (int)$s['id'];
        $v30 = $kpis['visits'][$sid]['30'] ?? [];
        $v90 = $kpis['visits'][$sid]['90'] ?? [];
        $s90 = $kpis['sales'][$sid]['90'] ?? 0.0;
      ?>
      <tr class="border-t border-slate-100 dark:border-gray-700 hover:bg-slate-50 dark:hover:bg-gray-700/30">
        <td class="px-4 py-2"><?= h($s['code'] ?? '') ?></td>
        <td class="px-4 py-2"><?= h($s['name'] ?? '') ?></td>
        <td class="px-4 py-2 text-center"><?= (int)($v30['done'] ?? 0) ?>/<?= (int)($v30['planned'] ?? 0) ?></td>
        <td class="px-4 py-2 text-center"><?= (int)($v90['done'] ?? 0) ?>/<?= (int)($v90['planned'] ?? 0) ?></td>
        <td class="px-4 py-2 text-right">৳ <?= number_format((float)$s90,2) ?></td>
      </tr>
      <?php endforeach ?>
      <?php if (!$stakeholders): ?>
      <tr><td colspan="5" class="px-4 py-3 text-center text-slate-400">No stakeholder data available.</td></tr>
      <?php endif ?>
    </tbody>
  </table>
</div>

<!-- Trend chart -->
<div class="rounded-xl border bg-white dark:bg-gray-800 p-4 shadow-sm">
  <div class="mb-2 font-semibold">Weekly Trend</div>
  <canvas id="perfChart" height="120"></canvas>
</div>

<script>
(function(){
  const ctx = document.getElementById('perfChart');
  if(!ctx || !window.Chart) return;
  const labels = <?= json_encode($labels) ?>;
  const series = <?= json_encode($series) ?>;
  const ds = series.map(s => ({
    label: s.label,
    data: s.data,
    borderWidth: 2,
    fill: false,
    tension: 0.35
  }));
  new Chart(ctx, {
    type: 'line',
    data: { labels, datasets: ds },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      plugins: { legend: { position: 'bottom' } },
      scales: { y: { beginAtZero: true } }
    }
  });
})();
</script>