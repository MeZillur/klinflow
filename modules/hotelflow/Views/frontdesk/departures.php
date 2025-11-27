<?php
/** @var string $date @var array $rows @var int $total @var string $module_base @var string $tab */
$h    = fn($v)=>htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
$base = rtrim((string)($module_base ?? '/apps/hotelflow'), '/');
?>
<div class="max-w-[1100px] mx-auto space-y-6"
     x-data="hfDepartures(<?= json_encode(['base'=>$base,'seed'=>$rows,'date'=>$date]) ?>)"
     x-init="boot()">

  <!-- Header / Filters -->
  <div class="flex flex-col sm:flex-row sm:items-end gap-3 justify-between">
    <div>
      <h1 class="text-2xl font-extrabold tracking-tight">Front Desk</h1>
      <p class="text-slate-500 text-sm">Guests departing on <span x-text="date"></span>.</p>
    </div>
    <form method="get" class="flex items-center gap-2">
      <label class="text-sm text-slate-600">
        Date
        <input type="date" name="date" value="<?= $h($date) ?>" class="block w-44 mt-1 px-3 py-2 rounded-lg border border-slate-300">
      </label>
      <button class="px-3 py-2 rounded-lg text-white" style="background:var(--brand)">Go</button>
    </form>
  </div>

  <!-- Tabs -->
  <?php $tab='departures'; include __DIR__ . '/_tabs.php'; ?>

  <!-- Meta -->
  <div class="flex items-center justify-between mt-2">
    <div class="text-sm text-slate-500">
      <span class="font-medium"><?= (int)$total ?></span> total
      <span x-show="latency>0" class="ml-3 text-xs" :class="ok?'text-emerald-600':'text-red-600'">
        • <span x-text="ok ? ('live '+latency+'ms') : 'offline'"></span>
      </span>
    </div>
  </div>

  <!-- Table -->
  <div class="overflow-x-auto rounded-xl border border-slate-200">
    <table class="min-w-full text-sm">
      <thead class="bg-slate-50">
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
      <tbody>
        <?php if (!$rows): ?>
          <tr><td colspan="8" class="px-3 py-6 text-center text-slate-500">No departures for <?= $h($date) ?>.</td></tr>
        <?php else: foreach ($rows as $r): ?>
          <tr class="border-t border-slate-200">
            <td class="px-3 py-2 font-medium">#<?= $h((string)($r['code'] ?? $r['id'])) ?></td>
            <td class="px-3 py-2"><?= $h((string)($r['guest_name'] ?? 'Guest')) ?></td>
            <td class="px-3 py-2"><?= $h((string)($r['room_type_name'] ?? '')) ?></td>
            <td class="px-3 py-2"><?= $h((string)($r['room_no'] ?? '—')) ?></td>
            <td class="px-3 py-2"><?= $h((string)($r['check_in'] ?? '')) ?></td>
            <td class="px-3 py-2"><?= $h((string)($r['check_out'] ?? '')) ?></td>
            <td class="px-3 py-2">
              <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs bg-amber-50 text-amber-700 border border-amber-200">
                <i class="fa-solid fa-clock"></i> Departing
              </span>
            </td>
            <td class="px-3 py-2 text-right">
              <a href="<?= $h($base) ?>/reservations/<?= (int)($r['id'] ?? 0) ?>" class="text-emerald-700 hover:underline">Open</a>
            </td>
          </tr>
        <?php endforeach; endif; ?>

        <template x-for="row in list" :key="row.id">
          <tr class="border-t border-slate-200">
            <td class="px-3 py-2 font-medium">#<span x-text="row.code || row.id"></span></td>
            <td class="px-3 py-2" x-text="row.guest_name || 'Guest'"></td>
            <td class="px-3 py-2" x-text="row.room_type_name || ''"></td>
            <td class="px-3 py-2" x-text="row.room_no || '—'"></td>
            <td class="px-3 py-2" x-text="row.check_in || ''"></td>
            <td class="px-3 py-2" x-text="row.check_out || ''"></td>
            <td class="px-3 py-2">
              <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs bg-amber-50 text-amber-700 border border-amber-200">
                <i class="fa-solid fa-clock"></i> Departing
              </span>
            </td>
            <td class="px-3 py-2 text-right">
              <a :href="base+'/reservations/'+(row.id||'')" class="text-emerald-700 hover:underline">Open</a>
            </td>
          </tr>
        </template>
      </tbody>
    </table>
  </div>
</div>

<script>
function hfDepartures(cfg){
  return {
    base: cfg.base || '/apps/hotelflow',
    date: cfg.date || new Date().toISOString().slice(0,10),
    list: cfg.seed || [],
    latency: 0,
    ok: true,
    async pull(){
      const t0 = performance.now();
      try{
        const url = this.base + '/api/departures?date=' + encodeURIComponent(this.date);
        const res = await fetch(url, {headers:{'Accept':'application/json'}, cache:'no-store'});
        if(!res.ok) throw new Error('HTTP '+res.status);
        const j = await res.json();
        this.list = Array.isArray(j.data) ? j.data : [];
        this.ok = true;
      }catch(e){ this.ok = false; }
      finally{ this.latency = Math.round(performance.now()-t0); }
    },
    boot(){
      this.pull();
      document.addEventListener('visibilitychange', ()=>{ if(document.visibilityState==='visible') this.pull(); });
    }
  }
}
</script>