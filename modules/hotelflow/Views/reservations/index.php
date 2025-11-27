<?php
/** @var array  $rows
 *  @var int    $total
 *  @var int    $page
 *  @var int    $limit
 *  @var array  $filters
 *  @var string $module_base
 */

$h    = fn($v)=>htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
$base = rtrim((string)($module_base ?? '/apps/hotelflow'), '/');

$rows    = $rows    ?? [];
$total   = isset($total) ? (int)$total : count($rows);
$page    = isset($page)  ? max(1, (int)$page) : 1;
$limit   = isset($limit) ? (int)$limit : 25;
$filters = $filters ?? [];

$f = [
    'status' => (string)($filters['status'] ?? ''),
    'from'   => (string)($filters['from']   ?? ''),
    'to'     => (string)($filters['to']     ?? ''),
    'q'      => (string)($filters['q']      ?? ''),
];

$pages = max(1, (int)ceil($total / max(1, $limit)));

// Small on-page stats (current result set only)
$pageCount     = count($rows);
$inHouseCount  = 0;
$todayCiCount  = 0;
$today         = date('Y-m-d');

foreach ($rows as $r) {
    $st = (string)($r['status'] ?? '');
    if ($st === 'in_house') {
        $inHouseCount++;
    }
    if (!empty($r['check_in']) && (string)$r['check_in'] === $today) {
        $todayCiCount++;
    }
}
?>

<div class="max-w-[1200px] mx-auto space-y-6">
  <!-- Header + CTAs -->
  <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
    <div>
      <h1 class="text-2xl font-extrabold tracking-tight">Reservations</h1>
      <p class="text-slate-500 text-sm">
        Create, search and manage bookings – phone, walk-in or online.
      </p>
    </div>

    <div class="flex flex-wrap gap-2 sm:justify-end">
      <!-- Pre-arrival invite (token link) -->
      <a href="<?= $h($base) ?>/reservations/prearrival-launch"
         class="inline-flex items-center gap-2 px-4 py-2 rounded-xl border border-emerald-200 bg-emerald-50 text-sm font-medium text-emerald-800 hover:bg-emerald-100 hover:border-emerald-300 shadow-sm">
        <span class="inline-flex h-6 w-6 items-center justify-center rounded-full bg-white/80">
          <i class="fa-solid fa-envelope-circle-check"></i>
        </span>
        <span>Send Pre-arrival Link</span>
      </a>
      
      
       <a href="<?= $h($base) ?>/reservations/walkin"
         class="inline-flex items-center gap-2 px-4 py-2 rounded-xl border border-slate-300 bg-white text-sm font-medium text-slate-800 hover:bg-slate-50">
        <span class="inline-flex h-6 w-6 items-center justify-center rounded-full bg-slate-100 text-slate-600">
          <i class="fa-regular fa-user"></i>
        </span>
        <span>+ Walk-in guest</span>
      </a>
      
      <!-- Existing guest -->
      <a href="<?= $h($base) ?>/reservations/create-existing"
         class="inline-flex items-center gap-2 px-4 py-2 rounded-xl border border-slate-300 bg-white text-sm font-medium text-slate-800 hover:bg-slate-50">
        <span class="inline-flex h-6 w-6 items-center justify-center rounded-full bg-slate-100 text-slate-600">
          <i class="fa-regular fa-user"></i>
        </span>
        <span>Existing Guest</span>
      </a>

      <!-- New reservation (primary) -->
      <a href="<?= $h($base) ?>/reservations/create"
         class="inline-flex items-center gap-2 px-4 py-2 rounded-xl text-sm font-semibold text-white shadow-sm hover:shadow-md transition"
         style="background:var(--brand)">
        <span class="inline-flex h-6 w-6 items-center justify-center rounded-full bg-white/15">
          <i class="fa-solid fa-plus"></i>
        </span>
        <span>New Reservation</span>
      </a>
    </div>
  </div>


  <?php $tab='reservations'; include __DIR__.'/../frontdesk/_tabs.php'; ?>

  <!-- Quick stats row (current list only) -->
  <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 text-sm">
    <!-- Total (this list) -->
    <div class="relative overflow-hidden rounded-2xl border border-slate-200 bg-white px-4 py-3 flex items-center justify-between">
      <div class="flex items-center gap-3">
        <div class="h-9 w-9 rounded-full bg-emerald-50 flex items-center justify-center text-emerald-600">
          <i class="fa-solid fa-layer-group"></i>
        </div>
        <div>
          <div class="text-xs text-slate-500 uppercase tracking-wide">Total (this list)</div>
          <div class="text-lg font-semibold text-slate-900"><?= (int)$pageCount ?></div>
        </div>
      </div>
      <span class="text-[11px] px-2 py-0.5 rounded-full bg-emerald-50 text-emerald-700 border border-emerald-100">
        All statuses
      </span>
    </div>

    <!-- In House -->
    <div class="relative overflow-hidden rounded-2xl border border-slate-200 bg-white px-4 py-3 flex items-center justify-between">
      <div class="flex items-center gap-3">
        <div class="h-9 w-9 rounded-full bg-sky-50 flex items-center justify-center text-sky-600">
          <i class="fa-solid fa-bed"></i>
        </div>
        <div>
          <div class="text-xs text-slate-500 uppercase tracking-wide">In House</div>
          <div class="text-lg font-semibold text-sky-800"><?= (int)$inHouseCount ?></div>
        </div>
      </div>
      <span class="text-[11px] px-2 py-0.5 rounded-full bg-sky-50 text-sky-700 border border-sky-100">
        Checked-in
      </span>
    </div>

    <!-- Today Check-in -->
    <div class="relative overflow-hidden rounded-2xl border border-slate-200 bg-white px-4 py-3 flex items-center justify-between">
      <div class="flex items-center gap-3">
        <div class="h-9 w-9 rounded-full bg-amber-50 flex items-center justify-center text-amber-600">
          <i class="fa-solid fa-door-open"></i>
        </div>
        <div>
          <div class="text-xs text-slate-500 uppercase tracking-wide">Today Check-in</div>
          <div class="text-lg font-semibold text-amber-700"><?= (int)$todayCiCount ?></div>
        </div>
      </div>
      <span class="text-[11px] px-2 py-0.5 rounded-full bg-amber-50 text-amber-700 border border-amber-100">
        <?= $h(date('d M')) ?>
      </span>
    </div>
  </div>

  <!-- Filters -->
  <form method="get"
        class="grid grid-cols-1 sm:grid-cols-5 gap-3 items-end rounded-2xl border border-slate-200 bg-white p-4">
    <div>
      <label class="text-sm text-slate-600">Status</label>
      <select name="status"
              class="mt-1 w-full px-3 py-2 rounded-lg border border-slate-300">
        <?php
        $statuses = [
            ''           => 'Any',
            'tentative'  => 'Tentative',
            'confirmed'  => 'Confirmed',
            'guaranteed' => 'Guaranteed',
            'in_house'   => 'In House',
            'cancelled'  => 'Cancelled',
            'no_show'    => 'No-show',
        ];
        foreach ($statuses as $k => $v): ?>
          <option value="<?= $h($k) ?>" <?= ($f['status'] === $k ? 'selected' : '') ?>>
            <?= $h($v) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>
    <div>
      <label class="text-sm text-slate-600">From</label>
      <input type="date"
             name="from"
             value="<?= $h($f['from']) ?>"
             class="mt-1 w-full px-3 py-2 rounded-lg border border-slate-300">
    </div>
    <div>
      <label class="text-sm text-slate-600">To</label>
      <input type="date"
             name="to"
             value="<?= $h($f['to']) ?>"
             class="mt-1 w-full px-3 py-2 rounded-lg border border-slate-300">
    </div>
    <div class="sm:col-span-2">
      <label class="text-sm text-slate-600">Search</label>
      <input type="text"
             name="q"
             value="<?= $h($f['q']) ?>"
             placeholder="Code / Guest..."
             class="mt-1 w-full px-3 py-2 rounded-lg border border-slate-300">
    </div>
    <div class="sm:col-span-5 flex flex-wrap gap-2 pt-1">
      <button class="px-4 py-2 rounded-lg text-white text-sm shadow-sm hover:shadow-md transition"
              style="background:var(--brand)">
        Apply
      </button>
      <a href="<?= $h($base) ?>/reservations"
         class="px-4 py-2 rounded-lg border border-slate-300 text-sm hover:bg-slate-50">
        Reset
      </a>
      <div class="ml-auto text-sm text-slate-500 flex items-center">
        Total in database:
        <span class="font-medium ml-1"><?= (int)$total ?></span>
      </div>
    </div>
  </form>

  <!-- Table -->
  <div class="rounded-2xl border border-slate-200 overflow-hidden bg-white">
    <table class="w-full text-sm">
      <thead class="bg-slate-50">
        <tr class="text-left text-slate-600">
          <th class="p-3 font-semibold">Code</th>
          <th class="p-3 font-semibold">Guest</th>
          <th class="p-3 font-semibold">Dates</th>
          <th class="p-3 font-semibold">Status</th>
          <th class="p-3 font-semibold text-right">Balance</th>
          <th class="p-3 font-semibold text-right">Actions</th>
        </tr>
      </thead>
      <tbody class="divide-y divide-slate-200">
        <?php if (!$rows): ?>
          <tr>
            <td colspan="6" class="p-6 text-center text-slate-500">
              No reservations found.
            </td>
          </tr>
        <?php endif; ?>
        
 <!-----Index List------>

<?php foreach ($rows as $r):
    $status = (string)($r['status'] ?? '');
    $id     = (int)$r['id'];

    // ----- Status badge colors -----
    $badgeClass = 'bg-slate-50 text-slate-700 border-slate-200';

    if ($status === 'booked' || $status === 'confirmed' || $status === 'guaranteed') {
        $badgeClass = 'bg-emerald-50 text-emerald-700 border-emerald-200';
    } elseif ($status === 'in_house') {
        $badgeClass = 'bg-sky-50 text-sky-700 border-sky-200';
    } elseif ($status === 'pending_confirmation') {
        $badgeClass = 'bg-rose-50 text-rose-700 border-rose-200';
    } elseif ($status === 'cancelled') {
        $badgeClass = 'bg-rose-50 text-rose-700 border-rose-200';
    } elseif ($status === 'no_show') {
        $badgeClass = 'bg-amber-50 text-amber-700 border-amber-200';
    }

    $code  = (string)($r['code'] ?? '');
    $guest = (string)($r['guest_name'] ?? '—');
    $ci    = (string)($r['check_in'] ?? '');
    $co    = (string)($r['check_out'] ?? '');

    // ----- Room info -----
    $roomType = (string)($r['room_type_name'] ?? '');
    $roomQty  = isset($r['room_qty']) ? (int)$r['room_qty'] : null;

    // room balance priority: room_balance -> balance_due
    if (isset($r['room_balance'])) {
        $bal = (float)$r['room_balance'];
    } elseif (isset($r['balance_due'])) {
        $bal = (float)$r['balance_due'];
    } else {
        $bal = null;
    }
?>
<tr class="hover:bg-slate-50/60">

    <!-- Code -->
    <td class="p-3 font-medium text-slate-900 whitespace-nowrap">
        <?= $h($code) ?>
    </td>

    <!-- Guest + Room summary -->
    <td class="p-3">
        <div class="text-slate-900"><?= $h($guest) ?></div>

        <?php if ($roomType !== '' || $roomQty !== null): ?>
            <div class="text-[11px] text-slate-500">
                <?php if ($roomType !== ''): ?>
                    <?= $h($roomType) ?>
                <?php endif; ?>
                <?php if ($roomQty !== null): ?>
                    <?php if ($roomType !== ''): ?> · <?php endif; ?>
                    Rooms: <?= (int)$roomQty ?>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </td>

    <!-- Dates -->
    <td class="p-3">
        <div><?= $h($ci) ?> → <?= $h($co) ?></div>
    </td>

    <!-- Status -->
    <td class="p-3">
        <span class="inline-flex items-center px-2 py-0.5 rounded-full border text-xs font-medium <?= $badgeClass ?>">
            <?= $h(ucwords(str_replace('_',' ',$status))) ?>
        </span>
    </td>

    <!-- Room Balance -->
    <td class="p-3 text-right whitespace-nowrap">
        <?php if ($bal !== null): ?>
            <span class="font-medium"><?= number_format($bal, 2) ?></span>
            <span class="text-xs text-slate-500">BDT</span>
        <?php else: ?>
            <span class="text-slate-400">—</span>
        <?php endif; ?>
    </td>

    <!-- Actions -->
    <td class="p-3 text-right space-x-2 whitespace-nowrap">

        <?php if ($status === 'pending_confirmation'): ?>
            <!-- CONFIRM BUTTON (POST) -->
            <form method="post"
                  action="<?= $h($base) ?>/reservations/<?= $id ?>/confirm-prearrival"
                  class="inline-block"
                  onsubmit="return confirm('Confirm this reservation?');">

                <button type="submit"
                        class="inline-flex items-center px-3 py-1.5 rounded-lg text-xs font-semibold text-white"
                        style="background:#228B22;">
                    <i class="fa-solid fa-check mr-1"></i>
                    Confirm
                </button>
            </form>
        <?php endif; ?>

        <!-- OPEN BUTTON -->
        <a href="<?= $h($base) ?>/reservations/<?= $id ?>"
           class="inline-flex items-center px-3 py-1.5 rounded-lg border border-slate-300 text-xs hover:bg-slate-100">
            <i class="fa-regular fa-file-lines mr-1"></i>Open
        </a>
    </td>
</tr>
<?php endforeach; ?>

  <!-- Pagination -->
  <?php if ($pages > 1): ?>
    <div class="flex items-center justify-center gap-2">
      <?php for ($p = 1; $p <= $pages; $p++):
        $is = ($p === $page);
        $qArr = $f;
        $qArr['page'] = $p;
      ?>
        <a href="?<?= $h(http_build_query($qArr)) ?>"
           class="px-3 py-1.5 rounded-lg border text-sm
                  <?= $is
                       ? 'bg-emerald-50 border-emerald-300 text-emerald-800'
                       : 'border-slate-300 text-slate-700 hover:bg-slate-50' ?>">
          <?= $p ?>
        </a>
      <?php endfor; ?>
    </div>
  <?php endif; ?>
</div>