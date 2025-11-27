<?php declare(strict_types=1);
$h = fn($v)=>htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
?>
<div class="max-w-6xl mx-auto p-4">
  <h1 class="text-2xl font-semibold mb-4" style="color:#228B22">DMS Health</h1>

  <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
    <a href="<?= $h(($module_base ?? '/apps/dms').'/reports/health/gl') ?>" class="block rounded-xl p-4 border hover:shadow">
      <div class="text-sm text-slate-500">Unbalanced Journals</div>
      <div class="text-3xl font-bold <?= ($unbalanced??0)? 'text-rose-600':'text-emerald-600' ?>">
        <?= (int)($unbalanced ?? 0) ?>
      </div>
    </a>
    <a href="<?= $h(($module_base ?? '/apps/dms').'/reports/health/stock') ?>" class="block rounded-xl p-4 border hover:shadow">
      <div class="text-sm text-slate-500">Negative Stock Items</div>
      <div class="text-3xl font-bold <?= ($negStock??0)? 'text-rose-600':'text-emerald-600' ?>">
        <?= (int)($negStock ?? 0) ?>
      </div>
    </a>
    <a href="<?= $h(($module_base ?? '/apps/dms').'/reports/health/map-keys') ?>" class="block rounded-xl p-4 border hover:shadow">
      <div class="text-sm text-slate-500">Missing Map Keys</div>
      <div class="text-3xl font-bold <?= !empty($missingMap)? 'text-amber-600':'text-emerald-600' ?>">
        <?= (int)count($missingMap ?? []) ?>
      </div>
    </a>
  </div>
</div>
