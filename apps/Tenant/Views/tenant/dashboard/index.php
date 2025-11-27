<?php $h=fn($s)=>htmlspecialchars((string)$s,ENT_QUOTES,'UTF-8');
$org=$_SESSION['tenant_org'] ?? ['name'=>'']; $user=$_SESSION['tenant_user'] ?? ['name'=>'']; ?>
<div class="max-w-6xl mx-auto space-y-6">
  <div>
    <h1 class="text-2xl font-semibold">Welcome, <?= $h($user['name'] ?? 'User') ?></h1>
    <p class="text-gray-500">Organization: <?= $h($org['name'] ?? '') ?></p>
  </div>
  <div class="grid sm:grid-cols-3 gap-4">
    <div class="rounded-xl border dark:border-gray-700 p-4">
      <div class="text-sm text-gray-500 mb-1">Enabled Modules</div>
      <div class="text-2xl font-semibold"><?= (int)($appsCount ?? 0) ?></div>
    </div>
    <div class="rounded-xl border dark:border-gray-700 p-4">
      <div class="text-sm text-gray-500 mb-1">Status</div>
      <div class="text-2xl font-semibold">Active</div>
    </div>
    <div class="rounded-xl border dark:border-gray-700 p-4">
      <div class="text-sm text-gray-500 mb-1">Quick Links</div>
      <a class="text-brand hover:underline" href="/apps">Open Apps</a>
    </div>
  </div>
</div>