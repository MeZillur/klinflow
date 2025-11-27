<?php declare(strict_types=1);
if(!function_exists('h')){function h($v){return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');}}
$p    = $p ?? [];
$base = $module_base ?? '';
$stock = (float)($stock ?? 0);
?>
<div class="flex items-center justify-between mb-4">
  <h1 class="text-xl font-semibold"><?= h($p['name'] ?? 'Free Product') ?></h1>
  <div class="flex gap-2">
    <a href="<?= h($base.'/free-products/receive') ?>" class="px-3 py-2 rounded-lg bg-slate-100 hover:bg-slate-200">Receive</a>
    <a href="<?= h($base.'/free-products/issue') ?>" class="px-3 py-2 rounded-lg bg-slate-100 hover:bg-slate-200">Issue</a>
    <a href="<?= h($base.'/free-products/'.$p['id'].'/movements') ?>" class="px-3 py-2 rounded-lg bg-emerald-600 text-white hover:bg-emerald-700">Movements</a>
  </div>
</div>

<div class="grid grid-cols-1 md:grid-cols-3 gap-4">
  <div class="p-4 rounded-xl border bg-slate-50">
    <div class="text-xs text-slate-500">Code</div>
    <div class="text-lg font-semibold"><?= h($p['code'] ?? '') ?></div>
  </div>
  <div class="p-4 rounded-xl border bg-slate-50">
    <div class="text-xs text-slate-500">Unit</div>
    <div class="text-lg font-semibold"><?= h($p['unit'] ?? '') ?></div>
  </div>
  <div class="p-4 rounded-xl border bg-slate-50">
    <div class="text-xs text-slate-500">Current Stock</div>
    <div class="text-lg font-semibold"><?= number_format($stock,2) ?></div>
  </div>
</div>