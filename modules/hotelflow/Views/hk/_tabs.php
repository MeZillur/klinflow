<?php
/** @var string $module_base @var string $active */
$h=fn($v)=>htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');
$base=rtrim((string)($module_base??'/apps/hotelflow'),'/');
$tabs=[
  ['k'=>'board','href'=>"$base/hk",'label'=>'Board'],
  ['k'=>'tasks','href'=>"$base/hk/tasks",'label'=>'Tasks'],
  ['k'=>'lf','href'=>"$base/hk/lost-found",'label'=>'Lost & Found'],
];
?>
<div class="flex gap-2 mb-4 border-b border-slate-200">
  <?php foreach($tabs as $t):
    $is = ($active===$t['k']); ?>
    <a href="<?= $h($t['href']) ?>"
       class="px-3 py-2 -mb-px rounded-t-lg <?= $is?'bg-white border-x border-t border-slate-200 text-emerald-700 font-semibold':'text-slate-600 hover:bg-slate-50' ?>">
      <?= $h($t['label']) ?>
    </a>
  <?php endforeach; ?>
</div>