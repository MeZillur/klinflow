<?php
/** @var string $module_base */
/** @var string|null $active */
$h    = fn($v)=>htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
$base = rtrim((string)($module_base ?? '/apps/hotelflow'), '/');
$active = (string)($active ?? '');
$btn = function(string $href, string $label, string $key) use ($active, $h){
  $is = ($active === $key);
  return '<a href="'.$h($href).'" class="px-3 py-2 rounded-lg text-sm '.($is
    ? 'bg-emerald-50 text-emerald-800 border border-emerald-200'
    : 'border border-transparent hover:bg-slate-50').'">'.$h($label).'</a>';
};
?>
<div class="flex items-center gap-2 mb-4">
  <?= $btn($base.'/reservations',            'List',      'list') ?>
  <?= $btn($base.'/reservations/create',     'New',       'create') ?>
  <?= $btn($base.'/reservations/calendar',   'Calendar',  'calendar') ?>
  <?= $btn($base.'/reservations/groups',     'Groups',    'groups') ?>
</div>