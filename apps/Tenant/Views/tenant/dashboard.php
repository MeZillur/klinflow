<?php
$h = fn($s)=>htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
$org = $org ?? ['name'=>'Organization','slug'=>''];
?>
<div class="max-w-6xl mx-auto space-y-6">
  <h1 class="text-2xl font-semibold">Welcome to <?= $h($org['name']) ?></h1>
  <p class="text-gray-500">Slug: <code class="px-2 py-1 bg-gray-100 rounded"><?= $h($org['slug']) ?></code></p>

  <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-4">
    <div class="p-4 border rounded-xl bg-white dark:bg-gray-800">
      <div class="text-sm text-gray-500 mb-1">Status</div>
      <div class="text-lg font-semibold">Active</div>
    </div>
    <div class="p-4 border rounded-xl bg-white dark:bg-gray-800">
      <div class="text-sm text-gray-500 mb-1">Modules</div>
      <div class="text-lg font-semibold">Coming soon</div>
    </div>
    <div class="p-4 border rounded-xl bg-white dark:bg-gray-800">
      <div class="text-sm text-gray-500 mb-1">Users</div>
      <div class="text-lg font-semibold">—</div>
    </div>
  </div>
</div>