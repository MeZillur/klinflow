<?php
/** @var string $date @var array $rows @var int $total @var string $module_base */
$h      = fn($v)=>htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
$base   = rtrim((string)($module_base ?? '/apps/hotelflow'), '/');
$seed   = $rows ?? [];
$config = [
    'base' => $base,
    'seed' => $seed,
    'date' => $date ?? date('Y-m-d'),
];
?>
<div class="max-w-[1100px] mx-auto space-y-6"
     x-data="hfArrivals(<?= json_encode($config, JSON_UNESCAPED_SLASHES|JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT) ?>)"
     x-init="boot()">

  <!-- ============================================================
       Header / Filters
  ============================================================ -->
  <div class="flex flex-col sm:flex-row sm:items-end gap-3 justify-between">
    <div>
      <h1 class="text-2xl font-extrabold tracking-tight">Front Desk</h1>
      <p class="text-slate-500 text-sm">
        Guests scheduled to arrive on
        <span class="font-medium" x-text="date"></span>.
      </p>
    </div>

    <form method="get" class="flex items-center gap-2">
      <label class="text-sm text-slate-600">
        Date
        <input type="date"
               name="date"
               value="<?= $h($date) ?>"
               class="block w-44 mt-1 px-3 py-2 rounded-lg border border-slate-300">
      </label>
      <button class="px-3 py-2 rounded-lg text-white text-sm font-semibold"
              style="background:var(--brand)">
        Go
      </button>
    </form>
  </div>

  <!-- ============================================================
       Tabs
  ============================================================ -->
  <?php $tab = 'arrivals'; include __DIR__ . '/_tabs.php'; ?>

  <!-- ============================================================
       Meta / Quick actions
  ============================================================ -->
  <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 mt-2">
    <div class="text-sm text-slate-500 flex items-center gap-3">
      <span>
        Total (server): <span class="font-medium"><?= (int)($total ?? 0) ?></span>
      </span>
      <span class="hidden sm:inline">•</span>
      <span>
        Loaded (live): <span class="font-medium" x-text="list.length"></span>
      </span>
      <span x-show="latency>0"
            class="text-xs flex items-center gap-1"
            :class="ok ? 'text-emerald-600' : 'text-red-600'">
        •
        <span x-text="ok ? ('live '+latency+'ms') : 'offline'"></span>
      </span>
    </div>
  </div>

  <!-- ============================================================
       Table
  ============================================================ -->
  <div class="overflow-x-auto rounded-xl border border-slate-200 bg-white mt-2">
    <table class="min-w-full text-sm">
      <thead class="bg-slate-50 border-b border-slate-200">
        <tr class="text-left">
          <th class="px-3 py-2">Res #</th>
          <th class="px-3 py-2">Guest</th>
          <th class="px-3 py-2">Room Type</th>
          <th class="px-3 py-2">Room</th>
          <th class="px-3 py-2">Check-in</th>
          <th class="px-3 py-2">Check-out</th>
          <th class="px-3 py-2">Status</th>
          <th class="px-3 py-2 text-right">Actions</th>
        </tr>
      </thead>

      <!-- Live rows -->
      <tbody class="divide-y divide-slate-200" x-show="list.length" x-cloak>
        <template x-for="row in list" :key="row.id">
          <tr>
            <td class="px-3 py-2 font-medium whitespace-nowrap">
              #<span x-text="row.code || row.id"></span>
            </td>
            <td class="px-3 py-2 whitespace-nowrap" x-text="row.guest_name || 'Guest'"></td>
            <td class="px-3 py-2" x-text="row.room_type_name || ''"></td>
            <td class="px-3 py-2 whitespace-nowrap" x-text="row.room_no || '—'"></td>
            <td class="px-3 py-2 whitespace-nowrap" x-text="row.check_in || ''"></td>
            <td class="px-3 py-2 whitespace-nowrap" x-text="row.check_out || ''"></td>
            <td class="px-3 py-2">
              <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs border"
                    :class="statusClasses(row.status)">
                <span class="w-1.5 h-1.5 rounded-full"
                      :class="dotClasses(row.status)"></span>
                <span x-text="prettyStatus(row.status)"></span>
              </span>
            </td>
            <td class="px-3 py-2 text-right whitespace-nowrap">
              <a :href="base + '/reservations/' + (row.id || '')"
                 class="inline-flex items-center gap-1 px-2 py-1 rounded-lg border border-slate-300 text-xs hover:bg-slate-50">
                <i class="fa-solid fa-up-right-from-square"></i>
                Open
              </a>
            </td>
          </tr>
        </template>
      </tbody>

      <!-- Empty state -->
      <tbody x-show="!list.length" x-cloak>
        <tr>
          <td colspan="8" class="px-3 py-6 text-center text-slate-500 text-sm">
            No arrivals found for
            <span class="font-medium" x-text="date"></span>.
            <span x-show="!ok" class="block text-xs text-red-500 mt-1">
              Live feed offline. Showing server data (if any).
            </span>
          </td>
        </tr>
      </tbody>
    </table>
  </div>

  <noscript>
    <div class="mt-3 text-xs text-red-600">
      JavaScript is required to view live arrivals.
    </div>
  </noscript>
</div>

<script>
function hfArrivals(cfg){
  return {
    base: cfg.base || '/apps/hotelflow',
    date: cfg.date || new Date().toISOString().slice(0,10),
    list: Array.isArray(cfg.seed) ? cfg.seed : [],
    latency: 0,
    ok: true,

    prettyStatus(s){
      s = (s || '').toString().toLowerCase();
      if (!s) return 'Expected';
      switch (s) {
        case 'confirmed':   return 'Confirmed';
        case 'guaranteed':  return 'Guaranteed';
        case 'tentative':   return 'Tentative';
        case 'in_house':
        case 'checked_in':  return 'Checked-in';
        case 'cancelled':   return 'Cancelled';
        case 'no_show':     return 'No-show';
        default:            return s.replace(/_/g,' ');
      }
    },

    statusClasses(s){
      s = (s || '').toString().toLowerCase();
      switch (s) {
        case 'confirmed':
        case 'guaranteed':
          return 'bg-emerald-50 text-emerald-700 border-emerald-200';
        case 'tentative':
          return 'bg-amber-50 text-amber-700 border-amber-200';
        case 'checked_in':
        case 'in_house':
          return 'bg-sky-50 text-sky-700 border-sky-200';
        case 'cancelled':
        case 'no_show':
          return 'bg-rose-50 text-rose-700 border-rose-200';
        default:
          return 'bg-slate-50 text-slate-700 border-slate-200';
      }
    },

    dotClasses(s){
      s = (s || '').toString().toLowerCase();
      switch (s) {
        case 'confirmed':
        case 'guaranteed':
          return 'bg-emerald-500';
        case 'tentative':
          return 'bg-amber-500';
        case 'checked_in':
        case 'in_house':
          return 'bg-sky-500';
        case 'cancelled':
        case 'no_show':
          return 'bg-rose-500';
        default:
          return 'bg-slate-400';
      }
    },

    async pull(){
      const t0 = performance.now();
      try{
        const url = this.base + '/api/arrivals?date=' + encodeURIComponent(this.date);
        const res = await fetch(url, {
          headers: {'Accept':'application/json'},
          cache: 'no-store'
        });
        if (!res.ok) throw new Error('HTTP '+res.status);
        const j = await res.json();
        this.list = Array.isArray(j.data) ? j.data : [];
        this.ok = true;
      }catch(e){
        this.ok = false;
      }finally{
        this.latency = Math.round(performance.now() - t0);
      }
    },

    boot(){
      // first load uses server seed; then refresh from API
      this.pull();
      document.addEventListener('visibilitychange', () => {
        if (document.visibilityState === 'visible') this.pull();
      });
    }
  }
}
</script>