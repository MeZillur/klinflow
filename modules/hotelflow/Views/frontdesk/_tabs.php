<?php
/** @var string $module_base @var string $date @var string $tab */
$h    = fn($v)=>htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
$base = rtrim((string)($module_base ?? '/apps/hotelflow'), '/');
$qDate = isset($date) && preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$date) ? ('?date='.rawurlencode($date)) : '';
$T    = (string)($tab ?? '');
$cls  = function(string $k) use ($T){
  return $k===$T
    ? 'px-3 py-2 rounded-lg bg-emerald-50 text-emerald-800 border border-emerald-200'
    : 'px-3 py-2 rounded-lg border border-slate-300 hover:bg-slate-50';
};
?>
<div class="flex items-center gap-2">
  <a href="<?= $h($base) ?>/frontdesk/arrivals<?= $h($qDate) ?>"    class="<?= $cls('arrivals') ?>"><i class="fa-solid fa-right-to-bracket mr-2"></i>Arrivals</a>
  <a href="<?= $h($base) ?>/frontdesk/inhouse<?= $h($qDate) ?>"     class="<?= $cls('inhouse') ?>"><i class="fa-solid fa-bed mr-2"></i>In-house</a>
  <a href="<?= $h($base) ?>/frontdesk/departures<?= $h($qDate) ?>"  class="<?= $cls('departures') ?>"><i class="fa-solid fa-right-from-bracket mr-2"></i>Departures</a>
  <a href="<?= $h($base) ?>/frontdesk/room-status"                  class="<?= $cls('room-status') ?>"><i class="fa-solid fa-door-closed mr-2"></i>Room Status</a>
</div>