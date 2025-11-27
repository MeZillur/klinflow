<?php
/** @var array $rooms @var array $roomTypes @var array $floors @var array $filters @var string $module_base */
$h    = fn($v)=>htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
$base = rtrim((string)($module_base ?? '/apps/hotelflow'), '/');
$f    = $filters ?? ['status'=>'','room_type_id'=>0,'floor'=>null,'q'=>''];

$config = [
    'base'    => $base,
    'filters' => $f,
    // seed is not used yet for per-day calendar, but passed for future if needed
    'seed'    => [],
];
?>
<div class="max-w-[1200px] mx-auto space-y-6"
     x-data="hfRoomStatusCalendar(<?= json_encode($config, JSON_UNESCAPED_SLASHES|JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT) ?>)"
     x-init="boot()">

  <!-- ===================== Header ===================== -->
  <div class="flex flex-col sm:flex-row sm:items-end gap-3 justify-between">
    <div>
      <h1 class="text-2xl font-extrabold tracking-tight">Front Desk</h1>
      <p class="text-slate-500 text-sm">
        Monthly room status calendar (vacant / occupied / OOO / OOS).
      </p>
    </div>

    <!-- Month nav (auto month change) -->
    <div class="flex items-center gap-2">
      <button type="button"
              class="inline-flex items-center justify-center w-9 h-9 rounded-lg border border-slate-300 hover:bg-slate-50"
              @click="prevMonth">
        <i class="fa-solid fa-chevron-left text-xs"></i>
      </button>
      <div class="text-sm font-semibold" x-text="monthLabel()"></div>
      <button type="button"
              class="inline-flex items-center justify-center w-9 h-9 rounded-lg border border-slate-300 hover:bg-slate-50"
              @click="nextMonth">
        <i class="fa-solid fa-chevron-right text-xs"></i>
      </button>
    </div>
  </div>

  <!-- ===================== Tabs ===================== -->
  <?php $tab='room-status'; include __DIR__ . '/_tabs.php'; ?>

  <!-- ===================== Filters ===================== -->
  <form method="get" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-6 gap-3 items-end">
    <div>
      <label class="text-sm text-slate-600">Status</label>
      <select name="status" class="mt-1 w-full px-3 py-2 rounded-lg border border-slate-300">
        <?php $opts=[''=>'Any','vacant'=>'Vacant','occupied'=>'Occupied','ooo'=>'Out of Order','oos'=>'Out of Service']; ?>
        <?php foreach ($opts as $k=>$v): ?>
          <option value="<?= $h($k) ?>" <?= ($f['status']===$k?'selected':'') ?>><?= $h($v) ?></option>
        <?php endforeach; ?>
      </select>
    </div>

    <div>
      <label class="text-sm text-slate-600">Room Type</label>
      <select name="room_type_id" class="mt-1 w-full px-3 py-2 rounded-lg border border-slate-300">
        <option value="0">All types</option>
        <?php foreach ($roomTypes as $rt): ?>
          <option value="<?= (int)$rt['id'] ?>" <?= ((int)$f['room_type_id']===(int)$rt['id']?'selected':'') ?>>
            <?= $h((string)$rt['name']) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>

    <div>
      <label class="text-sm text-slate-600">Floor</label>
      <select name="floor" class="mt-1 w-full px-3 py-2 rounded-lg border border-slate-300">
        <option value="">All floors</option>
        <?php foreach ($floors as $fl): ?>
          <option value="<?= (int)$fl ?>" <?= ((string)$f['floor']===(string)$fl?'selected':'') ?>>
            Floor <?= (int)$fl ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="lg:col-span-2">
      <label class="text-sm text-slate-600">Search</label>
      <input type="text" name="q" value="<?= $h((string)$f['q']) ?>" placeholder="Room number…"
             class="mt-1 w-full px-3 py-2 rounded-lg border border-slate-300">
    </div>

    <div class="flex gap-2">
      <button class="px-4 py-2 rounded-lg text-white w-full sm:w-auto text-sm font-semibold"
              style="background:var(--brand)">
        Apply
      </button>
      <a href="<?= $h($base) ?>/frontdesk/room-status"
         class="px-4 py-2 rounded-lg border border-slate-300 w-full sm:w-auto text-sm">
        Reset
      </a>
    </div>
  </form>

  <!-- ===================== Meta / Legend ===================== -->
  <div class="flex flex-col md:flex-row md:items-center justify-between gap-3">
    <div class="text-sm text-slate-500 flex items-center gap-3">
      <span>
        Days in view:
        <span class="font-medium" x-text="daysInMonth"></span>
      </span>
      <span class="hidden md:inline">•</span>
      <span>
        Loaded from API:
        <span class="font-medium" x-text="Object.keys(map).length"></span> day(s)
      </span>
      <span x-show="latency>0"
            class="text-xs flex items-center gap-1"
            :class="ok ? 'text-emerald-600' : 'text-red-600'">
        • <span x-text="ok ? ('live '+latency+'ms') : 'offline'"></span>
      </span>
    </div>

    <div class="text-xs sm:text-sm text-slate-500 flex flex-wrap gap-3">
      <span class="inline-flex items-center gap-1">
        <span class="w-3 h-3 rounded-sm bg-emerald-100 border border-emerald-300"></span> Mostly vacant
      </span>
      <span class="inline-flex items-center gap-1">
        <span class="w-3 h-3 rounded-sm bg-amber-100 border border-amber-300"></span> Mixed / medium
      </span>
      <span class="inline-flex items-center gap-1">
        <span class="w-3 h-3 rounded-sm bg-rose-100 border border-rose-300"></span> High occupancy
      </span>
      <span class="inline-flex items-center gap-1">
        <span class="w-3 h-3 rounded-sm bg-slate-200 border border-slate-400"></span> Mostly OOO/OOS
      </span>
    </div>
  </div>

  <!-- ===================== Calendar ===================== -->
  <div class="mt-3 rounded-2xl border border-slate-200 bg-white p-4">
    <!-- Weekday header -->
    <div class="grid grid-cols-7 gap-2 text-[11px] font-semibold text-slate-500 mb-2">
      <div class="text-center">Sun</div>
      <div class="text-center">Mon</div>
      <div class="text-center">Tue</div>
      <div class="text-center">Wed</div>
      <div class="text-center">Thu</div>
      <div class="text-center">Fri</div>
      <div class="text-center">Sat</div>
    </div>

    <!-- Calendar grid -->
    <div class="grid grid-cols-7 gap-2 text-xs">
      <template x-for="(cell, idx) in cells" :key="idx">
        <div class="h-24 sm:h-28 rounded-xl border px-2 py-1.5 flex flex-col"
             :class="dayClass(cell)">
          <!-- Empty cell (padding before month starts) -->
          <template x-if="!cell.date">
            <span>&nbsp;</span>
          </template>

          <!-- Real day -->
          <template x-if="cell.date">
            <div class="flex flex-col h-full">
              <div class="flex items-center justify-between">
                <span class="text-xs font-semibold" x-text="cell.day"></span>
                <span class="text-[10px] text-slate-400" x-text="shortDate(cell.date)"></span>
              </div>

              <div class="mt-1.5 space-y-0.5 text-[10px] leading-tight">
                <div>
                  <span class="font-medium">Occ:</span>
                  <span x-text="cell.occupied"></span>
                  /
                  <span x-text="cell.total"></span>
                </div>
                <div>
                  <span class="font-medium">Vac:</span>
                  <span x-text="cell.vacant"></span>
                </div>
                <div>
                  <span>OOO/OOS:</span>
                  <span x-text="cell.ooo + '/' + cell.oos"></span>
                </div>
              </div>

              <div class="mt-auto pt-1">
                <div class="h-1.5 w-full rounded-full overflow-hidden bg-white/40 border border-white/60">
                  <div class="h-full rounded-full bg-emerald-500"
                       :style="'width:'+occupancyPercent(cell)+'%'"></div>
                </div>
              </div>
            </div>
          </template>
        </div>
      </template>
    </div>

    <div class="mt-3 text-[11px] text-slate-500">
      * Counts are per selected filters (status / room type / floor / search). API endpoint expected:
      <code><?= $h($base) ?>/api/room-status-calendar?month=YYYY-MM</code> returning
      <code>{ date, total, occupied, vacant, ooo, oos }</code> per day.
    </div>
  </div>

  <noscript>
    <div class="mt-3 text-xs text-red-600">
      JavaScript is required to view the live calendar.
    </div>
  </noscript>
</div>

<script>
function hfRoomStatusCalendar(cfg){
  return {
    base: cfg.base || '/apps/hotelflow',
    filters: cfg.filters || {},
    month: (cfg.month || new Date().toISOString().slice(0,7)), // YYYY-MM
    map: {},          // { 'YYYY-MM-DD': {total,occupied,vacant,ooo,oos} }
    cells: [],        // calendar cells including blanks
    daysInMonth: 0,
    latency: 0,
    ok: true,

    monthLabel(){
      const [yy,mm] = this.month.split('-').map(v=>parseInt(v,10));
      if (!yy || !mm) return this.month;
      const d = new Date(yy, mm-1, 1);
      return d.toLocaleString('en-GB', {month:'long', year:'numeric'});
    },

    prevMonth(){
      const [yy,mm] = this.month.split('-').map(v=>parseInt(v,10));
      const d = new Date(yy, mm-2, 1); // previous month
      this.month = d.toISOString().slice(0,7);
      this.pull();
    },

    nextMonth(){
      const [yy,mm] = this.month.split('-').map(v=>parseInt(v,10));
      const d = new Date(yy, mm, 1); // next month
      this.month = d.toISOString().slice(0,7);
      this.pull();
    },

    shortDate(dateStr){
      // returns like "17 Nov"
      const d = new Date(dateStr);
      if (isNaN(d.getTime())) return '';
      return d.toLocaleDateString('en-GB', {day:'2-digit', month:'short'});
    },

    occupancyPercent(cell){
      if (!cell.total || !cell.occupied) return 0;
      const pct = (cell.occupied / cell.total) * 100;
      return Math.max(0, Math.min(100, Math.round(pct)));
    },

    dayClass(cell){
      if (!cell.date) return 'border-transparent bg-transparent';
      // decide color by occupancy + OOO/OOS mix
      const t = cell.total || 0;
      const occ = t ? (cell.occupied / t) : 0;
      const oooTotal = (cell.ooo || 0) + (cell.oos || 0);

      if (oooTotal && (!t || oooTotal >= t)) {
        return 'bg-slate-200/70 border-slate-400';
      }
      if (occ === 0 && t > 0) {
        return 'bg-emerald-50 border-emerald-200';
      }
      if (occ >= 0.9) {
        return 'bg-rose-50 border-rose-200';
      }
      if (occ >= 0.4) {
        return 'bg-amber-50 border-amber-200';
      }
      return 'bg-slate-50 border-slate-200';
    },

    buildCells(){
      // Build calendar cells from this.month + this.map
      const [yy,mm] = this.month.split('-').map(v=>parseInt(v,10));
      if (!yy || !mm) { this.cells=[]; this.daysInMonth=0; return; }

      const first = new Date(yy, mm-1, 1);
      const firstDow = first.getDay(); // 0=Sun
      const daysInMonth = new Date(yy, mm, 0).getDate();
      this.daysInMonth = daysInMonth;

      const cells = [];
      // leading blanks
      for (let i=0; i<firstDow; i++) {
        cells.push({ date:null });
      }
      // real days
      for (let d=1; d<=daysInMonth; d++) {
        const dateStr = this.month + '-' + String(d).padStart(2,'0');
        const meta = this.map[dateStr] || {};
        cells.push({
          date: dateStr,
          day: d,
          total: meta.total || 0,
          occupied: meta.occupied || 0,
          vacant: meta.vacant || 0,
          ooo: meta.ooo || 0,
          oos: meta.oos || 0,
        });
      }
      this.cells = cells;
    },

    async pull(){
      const t0 = performance.now();
      try{
        const u = new URL(this.base + '/api/room-status-calendar', window.location.origin);
        u.searchParams.set('month', this.month);
        // pass filters through to API
        if (this.filters) {
          for (const [k,v] of Object.entries(this.filters)) {
            if (v !== null && v !== '' && typeof v !== 'undefined') {
              u.searchParams.set(k, v);
            }
          }
        }
        const res = await fetch(u.toString(), {
          headers: {'Accept':'application/json'},
          cache: 'no-store'
        });
        if (!res.ok) throw new Error('HTTP '+res.status);
        const j = await res.json();
        const rows = Array.isArray(j.data) ? j.data : [];

        const map = {};
        rows.forEach(r => {
          let d = (r.date || r.d || r.day || '').toString();
          if (!d) return;
          if (d.length === 1 || d.length === 2) {
            d = this.month + '-' + d.padStart(2,'0');
          }
          map[d] = {
            total:     Number(r.total     ?? r.rooms      ?? 0),
            occupied:  Number(r.occupied  ?? 0),
            vacant:    Number(r.vacant    ?? 0),
            ooo:       Number(r.ooo       ?? r.out_of_order     ?? 0),
            oos:       Number(r.oos       ?? r.out_of_service   ?? 0),
          };
        });
        this.map = map;
        this.ok  = true;
      }catch(e){
        console.error('room-status-calendar error', e);
        this.ok  = false;
        this.map = {};
      }finally{
        this.latency = Math.round(performance.now() - t0);
        this.buildCells();
      }
    },

    boot(){
      this.buildCells();   // initial empty calendar for current month
      this.pull();         // then hit API for real data
    }
  }
}
</script>