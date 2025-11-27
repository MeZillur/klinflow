<?php
declare(strict_types=1);
$h = fn($s)=>htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');

$org         = $org ?? ($_SESSION['tenant_org'] ?? []);
$orgName     = (string)($org['name'] ?? 'Organization');
$module_base = $module_base ?? '/apps/medflow';
?>
<div class="space-y-6">
  <header>
    <h1 class="text-2xl font-semibold"><?= $h($orgName) ?> — MedFlow</h1>
    <p class="text-gray-500">Lightweight pharmacy dashboard</p>
  </header>

  <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
    <div class="rounded-xl border border-gray-200 dark:border-gray-700 p-4">
      <div class="text-sm text-gray-500">Today’s Sales</div>
      <div class="mt-1 text-2xl font-semibold">0</div>
    </div>
    <div class="rounded-xl border border-gray-200 dark:border-gray-700 p-4">
      <div class="text-sm text-gray-500">Open Prescriptions</div>
      <div class="mt-1 text-2xl font-semibold">0</div>
    </div>
    <div class="rounded-xl border border-gray-200 dark:border-gray-700 p-4">
      <div class="text-sm text-gray-500">Low Stock Items</div>
      <div class="mt-1 text-2xl font-semibold">0</div>
    </div>
    <div class="rounded-xl border border-gray-200 dark:border-gray-700 p-4">
      <div class="text-sm text-gray-500">Suppliers</div>
      <div class="mt-1 text-2xl font-semibold">0</div>
    </div>
  </div>

  <div class="rounded-xl border border-gray-200 dark:border-gray-700 p-4">
    <div class="mb-2 text-sm font-medium">Quick Links</div>
    <div class="flex flex-wrap gap-2">
      <a class="inline-flex items-center gap-2 rounded-lg border border-gray-200 dark:border-gray-700 px-3 py-2 hover:bg-gray-50 dark:hover:bg-gray-800"
         href="<?= $h($module_base . '/sales') ?>"><i class="fa-solid fa-receipt"></i> Open Sales</a>
      <a class="inline-flex items-center gap-2 rounded-lg border border-gray-200 dark:border-gray-700 px-3 py-2 hover:bg-gray-50 dark:hover:bg-gray-800"
         href="<?= $h($module_base . '/inventory') ?>"><i class="fa-solid fa-boxes-stacked"></i> Manage Inventory</a>
      <a class="inline-flex items-center gap-2 rounded-lg border border-gray-200 dark:border-gray-700 px-3 py-2 hover:bg-gray-50 dark:hover:bg-gray-800"
         href="<?= $h($module_base . '/reports') ?>"><i class="fa-solid fa-chart-line"></i> Reports</a>
    </div>
  </div>
</div>