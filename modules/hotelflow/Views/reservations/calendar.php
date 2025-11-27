<?php
/** @var string $module_base @var string $ym @var string $monthName
    @var string $prevYm @var string $nextYm @var array $events */
$h    = fn($v)=>htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
$base = rtrim((string)($module_base ?? '/apps/hotelflow'), '/');

$firstDayTs = strtotime($ym.'-01');
$startWeek  = (int)date('N', $firstDayTs); // 1..7 (Mon..Sun)
$daysInMon  = (int)date('t', $firstDayTs);
// Grid starts on Monday (ISO)
$leadingBlanks = ($startWeek - 1 + 7) % 7;
$totalCells    = $leadingBlanks + $daysInMon;
$rows          = (int)ceil($totalCells / 7);
?>
<div class="max-w-[1100px] mx-auto"
     x-data="hfResCal(<?= json_encode([
        'base'=>$base,
        'ym'=>$ym,
        'events'=>$events,
      ], JSON_UNESCAPED_SLASHES) ?>)">

  <div class="flex items-center justify-between mb-3">
    <h1 class="text-2xl font-extrabold">Reservations Calendar</h1>
    <div class="flex items-center gap-2">
      <a href="<?= $h($base) ?>/reservations/calendar?ym=<?= $h($prevYm) ?>"
         class="px-3 py-2 rounded-lg border hover:bg-slate-50">
        <i class="fa-solid fa-chevron-left"></i>
        <span class="hidden sm:inline"><?= $h(date('M Y', strtotime($ym.'-01 -1 month'))) ?></span>
      </a>
      <div class="font-semibold min-w-[10ch] text-center"><?= $h($monthName) ?></div>
      <a href="<?= $h($base) ?>/reservations/calendar?ym=<?= $h($nextYm) ?>"
         class="px-3 py-2 rounded-lg border hover:bg-slate-50">
        <span class="hidden sm:inline"><?= $h(date('M Y', strtotime($ym.'-01 +1 month'))) ?></span>
        <i class="fa-solid fa-chevron-right"></i>
      </a>
    </div>
  </div>

  <?php $active='calendar'; include __DIR__.'/_tabs.php'; ?>

  <!-- Toolbar -->
  <div class="flex flex-wrap items-center gap-2 mb-4">
    <a href="<?= $h($base) ?>/reservations/calendar?ym=<?= $h(date('Y-m')) ?>"
       class="px-3 py-1.5 rounded-lg border hover:bg-slate-50">Today</a>

    <form method="get" action="<?= $h($base) ?>/reservations/calendar" class="flex items-center gap-2">
      <input type="month" name="ym" value="<?= $h($ym) ?>"
             class="px-3 py-1.5 rounded-lg border border-slate-300">
      <button class="px-3 py-1.5 rounded-lg text-white" style="background:var(--brand)">Go</button>
    </form>

    <div class="ml-auto flex items-center gap-2 text-sm">
      <label class="text-slate-600">Status</label>
      <select x-model="status"
              class="px-2 py-1.5 rounded-lg border border-slate-300">
        <option value="">All</option>
        <option value="confirmed">Confirmed</option>
        <option value="in_house">In House</option>
        <option value="tentative">Tentative</option>
        <option value="cancelled">Cancelled</option>
        <option value="no_show">No-show</option>
      </select>
    </div>
  </div>

  <!-- Legend -->
  <div class="flex flex-wrap gap-3 text-sm text-slate-600 mb-3">
    <span class="inline-flex items-center gap-2"><i class="fa-solid fa-square" style="color:#10b981"></i> Confirmed</span>
    <span class="inline-flex items-center gap-2"><i class="fa-solid fa-square" style="color:#f59e0b"></i> In House</span>
    <span class="inline-flex items-center gap-2"><i class="fa-solid fa-square" style="color:#6b7280"></i> Tentative</span>
    <span class="inline-flex items-center gap-2"><i class="fa-solid fa-square" style="color:#ef4444"></i> Cancelled/No-show</span>
  </div>

  <!-- Week headers -->
  <div class="grid grid-cols-7 text-xs font-semibold text-slate-500 border-y border-slate-200">
    <?php foreach (['Mon','Tue','Wed','Thu','Fri','Sat','Sun'] as $wd): ?>
      <div class="px-2 py-2"><?= $h($wd) ?></div>
    <?php endforeach; ?>
  </div>

  <!-- Month grid -->
  <div class="grid grid-cols-7 border-b border-slate-200">
    <?php
    $cell = 0;
    for ($r = 0; $r < $rows; $r++):
      for ($c = 0; $c < 7; $c++, $cell++):
        $dayNum = $cell - $leadingBlanks + 1;
        $inMonth = $dayNum >= 1 && $dayNum <= $daysInMon;
        $date = $inMonth ? sprintf('%s-%02d', $ym, $dayNum) : '';
    ?>
      <div class="min-h-[110px] border-r border-slate-200 <?= ($r === $rows-1 ? '' : 'border-b') ?>">
        <div class="flex items-center justify-between px-2 py-1 text-xs">
          <div class="font-semibold <?= $inMonth?'':'text-slate-300' ?>"><?= $inMonth ? (int)$dayNum : '' ?></div>
          <?php if ($date === date('Y-m-d')): ?>
            <span class="text-[10px] px-1.5 py-0.5 rounded bg-emerald-50 text-emerald-700 border border-emerald-200">Today</span>
          <?php endif; ?>
        </div>

        <div class="px-1 space-y-1">
          <!-- Events that touch this day -->
          <?php if ($inMonth): ?>
            <template x-for="e in day('<?= $h($date) ?>')" :key="e.id + '-<?= $h($date) ?>'">
              <a :href="e.href"
                 class="block text-[11px] leading-tight rounded-md px-1.5 py-1 border whitespace-nowrap overflow-hidden text-ellipsis"
                 :class="edge(e, '<?= $h($date) ?>')"
                 :style="styleFor(e)"
                 :title="(e.room_no||e.room_type||'') + ' • ' + (e.guest||('#'+e.id))">
                <div class="truncate" x-text="label(e,'<?= $h($date) ?>')"></div>
              </a>
            </template>
          <?php endif; ?>
        </div>
      </div>
    <?php endfor; endfor; ?>
  </div>
</div>

<script>
function hfResCal(props){
  const color = (status)=>{
    status = (status||'').toLowerCase();
    if (status==='in_house' || status==='checked_in')
      return {bg:'#FEF3C7', bd:'#F59E0B', tx:'#92400E'}; // amber
    if (status==='cancelled' || status==='no_show')
      return {bg:'#FEE2E2', bd:'#EF4444', tx:'#991B1B'}; // red
    if (status==='tentative')
      return {bg:'#F3F4F6', bd:'#9CA3AF', tx:'#374151'}; // gray
    return {bg:'#ECFDF5', bd:'#10B981', tx:'#065F46'};   // confirmed -> emerald
  };
  const inside = (d, ci, co)=> (d>=ci && d<co);

  return {
    base: props.base,
    ym: props.ym,
    evs: props.events || [],
    status: '',

    /** events that include the given day (and match optional filter) */
    day(d){
      return this.evs.filter(e =>
        inside(d, e.check_in, e.check_out) &&
        (!this.status || (e.status||'').toLowerCase()===this.status)
      );
    },

    /** Rounded edges for first/last day of the span */
    edge(e, d){
      if (d===e.check_in && d===e.check_out) return 'rounded-md';   // same-day
      if (d===e.check_in)  return 'rounded-l-md';
      if (d===e.check_out) return 'rounded-r-md';
      return 'rounded-none';
    },

    /** Inline style per status */
    styleFor(e){
      const c = color(e.status);
      return `background:${c.bg};border-color:${c.bd};color:${c.tx}`;
    },

    /** Label: show guest on start day; mid days show code-lite */
    label(e, d){
      const rm = e.room_no || e.room_type || '';
      const start = (d===e.check_in);
      if (start) return (rm ? '['+rm+'] ' : '') + (e.guest || ('#'+e.id));
      return (rm ? '['+rm+'] ' : '') + (e.guest ? '…' : ('#'+e.id));
    },
  }
}
</script>