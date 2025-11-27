<?php
declare(strict_types=1);
/** @var array $org */
$h = fn($s)=>htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
?>
<div class="space-y-6">
  <h1 class="text-2xl font-semibold">School Dashboard</h1>

  <div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-4">
    <div class="border border-gray-200 dark:border-gray-700 rounded-xl p-4">
      <div class="text-sm text-gray-500">Students</div>
      <div class="mt-1 text-2xl font-bold">0</div>
    </div>
    <div class="border border-gray-200 dark:border-gray-700 rounded-xl p-4">
      <div class="text-sm text-gray-500">Teachers</div>
      <div class="mt-1 text-2xl font-bold">0</div>
    </div>
    <div class="border border-gray-200 dark:border-gray-700 rounded-xl p-4">
      <div class="text-sm text-gray-500">Classes</div>
      <div class="mt-1 text-2xl font-bold">0</div>
    </div>
    <div class="border border-gray-200 dark:border-gray-700 rounded-xl p-4">
      <div class="text-sm text-gray-500">Fees (This Month)</div>
      <div class="mt-1 text-2xl font-bold">0</div>
    </div>
  </div>
</div>