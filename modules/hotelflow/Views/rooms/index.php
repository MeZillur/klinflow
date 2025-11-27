<?php
/** @var array $rows */
/** @var array $filters */
/** @var array $roomTypes */
/** @var array $floors */
/** @var string $module_base */

$h    = fn($v)=>htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
$base = rtrim((string)($module_base ?? '/apps/hotelflow'), '/');

$qFilter      = $filters['q']            ?? '';
$rtFilter     = (int)($filters['room_type_id'] ?? 0);
$floorFilter  = (string)($filters['floor'] ?? '');
$hkFilter     = (string)($filters['hk'] ?? '');
$statusFilter = (string)($filters['status'] ?? '');
?>
<div class="max-w-[1200px] mx-auto space-y-6">

  <!-- Header + CTA -->
  <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
    <div>
      <h1 class="text-2xl font-extrabold tracking-tight">Rooms</h1>
      <p class="text-slate-500 text-sm">Manage physical rooms, room types and floors.</p>
    </div>

    <div class="flex flex-wrap gap-2">
      <a href="<?= $h($base) ?>/rooms/types"
         class="inline-flex items-center gap-2 px-3 py-2 rounded-lg border border-slate-300 bg-white text-xs font-medium hover:bg-slate-50">
        <i class="fa-solid fa-layer-group text-slate-600"></i>
        <span>Room Types</span>
      </a>
      <a href="<?= $h($base) ?>/rooms/floors"
         class="inline-flex items-center gap-2 px-3 py-2 rounded-lg border border-slate-300 bg-white text-xs font-medium hover:bg-slate-50">
        <i class="fa-solid fa-building text-slate-600"></i>
        <span>Floors</span>
      </a>
      <a href="<?= $h($base) ?>/rooms/create"
         class="inline-flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-semibold text-white shadow-sm hover:shadow-md transition"
         style="background:var(--brand,#228B22);">
        <i class="fa-solid fa-plus"></i>
        <span>Add Room</span>
      </a>
    </div>
  </div>

  <!-- Filters -->
  <form method="get" class="rounded-xl border border-slate-200 bg-white px-4 py-3 flex flex-wrap gap-3 items-end">
    <div class="w-full sm:w-auto sm:flex-1">
      <label class="block text-xs font-medium text-slate-600 mb-1">Search</label>
      <input type="text" name="q"
             value="<?= $h($qFilter) ?>"
             placeholder="Room no / name…"
             class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm">
    </div>

    <div class="w-36">
      <label class="block text-xs font-medium text-slate-600 mb-1">Room Type</label>
      <select name="room_type_id"
              class="w-full border border-slate-300 rounded-lg px-2 py-2 text-sm">
        <option value="">All</option>
        <?php foreach ($roomTypes as $rt): ?>
          <option value="<?= (int)$rt['id'] ?>"
            <?= $rtFilter === (int)$rt['id'] ? 'selected' : '' ?>>
            <?= $h((string)$rt['name']) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="w-28">
      <label class="block text-xs font-medium text-slate-600 mb-1">Floor</label>
      <select name="floor"
              class="w-full border border-slate-300 rounded-lg px-2 py-2 text-sm">
        <option value="">All</option>
        <?php foreach ($floors as $f): ?>
          <option value="<?= $h($f) ?>" <?= $floorFilter === $f ? 'selected' : '' ?>>
            <?= $h($f) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="w-32">
      <label class="block text-xs font-medium text-slate-600 mb-1">HK Status</label>
      <select name="hk"
              class="w-full border border-slate-300 rounded-lg px-2 py-2 text-sm">
        <option value="">All</option>
        <?php foreach (['clean','dirty','inspected','pickup'] as $hk): ?>
          <option value="<?= $hk ?>" <?= $hkFilter === $hk ? 'selected' : '' ?>>
            <?= ucfirst($hk) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="w-40">
      <label class="block text-xs font-medium text-slate-600 mb-1">Room Status</label>
      <select name="status"
              class="w-full border border-slate-300 rounded-lg px-2 py-2 text-sm">
        <option value="">All</option>
        <?php foreach (['vacant','occupied','out_of_order','out_of_service'] as $st): ?>
          <option value="<?= $st ?>" <?= $statusFilter === $st ? 'selected' : '' ?>>
            <?= ucwords(str_replace('_',' ',$st)) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="flex gap-2">
      <button type="submit"
              class="inline-flex items-center gap-2 px-3 py-2 rounded-lg text-xs font-semibold text-white shadow-sm hover:shadow-md transition"
              style="background:var(--brand,#228B22);">
        <i class="fa-solid fa-magnifying-glass"></i>
        <span>Apply</span>
      </button>
      <a href="<?= $h($base) ?>/rooms"
         class="inline-flex items-center px-3 py-2 rounded-lg text-xs border border-slate-300 text-slate-600 hover:bg-slate-50">
        Reset
      </a>
    </div>
  </form>

  <!-- Table -->
  <div class="overflow-x-auto rounded-xl border border-slate-200 bg-white">
    <table class="min-w-full text-sm">
      <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
        <tr>
          <th class="p-3 text-left w-24">Room No</th>
          <th class="p-3 text-left w-40">Name</th>
          <th class="p-3 text-left w-40">Room Type</th>
          <th class="p-3 text-left w-24">Floor</th>
          <th class="p-3 text-left w-28">HK</th>
          <th class="p-3 text-left w-32">Status</th>
          <th class="p-3 text-left">Notes</th>
          <th class="p-3 text-right w-40">Actions</th>
        </tr>
      </thead>
      <tbody class="divide-y divide-slate-100">
      <?php if (!$rows): ?>
        <tr>
          <td colspan="8" class="p-6 text-center text-slate-400 text-sm">
            No rooms found. Click “Add Room” to create your first one.
          </td>
        </tr>
      <?php endif; ?>

      <?php foreach ($rows as $r):
        $id       = (int)$r['id'];
        $roomNo   = (string)$r['room_no'];
        $name     = (string)$r['name'];
        $rtName   = (string)$r['room_type_name'];
        $floor    = (string)$r['floor'];
        $hk       = (string)$r['hk_status'];
        $status   = (string)$r['room_status'];
        $notes    = (string)$r['notes'];

        // HK badge
        $hkClass = 'bg-slate-50 text-slate-700 border-slate-200';
        if ($hk === 'clean')     $hkClass = 'bg-emerald-50 text-emerald-700 border-emerald-200';
        elseif ($hk === 'dirty') $hkClass = 'bg-amber-50 text-amber-700 border-amber-200';
        elseif ($hk === 'inspected') $hkClass = 'bg-sky-50 text-sky-700 border-sky-200';

        // Status badge
        $stClass = 'bg-slate-50 text-slate-700 border-slate-200';
        if ($status === 'vacant')           $stClass = 'bg-emerald-50 text-emerald-700 border-emerald-200';
        elseif ($status === 'occupied')     $stClass = 'bg-sky-50 text-sky-700 border-sky-200';
        elseif ($status === 'out_of_order') $stClass = 'bg-rose-50 text-rose-700 border-rose-200';
        elseif ($status === 'out_of_service') $stClass = 'bg-rose-50 text-rose-700 border-rose-200';
      ?>
        <tr class="hover:bg-slate-50/60">
          <!-- Room No -->
          <td class="p-3 font-medium text-slate-900 whitespace-nowrap">
            <?= $h($roomNo !== '' ? $roomNo : '#'.$id) ?>
          </td>

          <!-- Name -->
          <td class="p-3">
            <div class="text-slate-900"><?= $h($name !== '' ? $name : '—') ?></div>
          </td>

          <!-- Room Type -->
          <td class="p-3">
            <div class="text-slate-900 text-sm"><?= $rtName !== '' ? $h($rtName) : '—' ?></div>
          </td>

          <!-- Floor -->
          <td class="p-3">
            <span class="inline-flex items-center rounded-md bg-slate-50 px-2 py-0.5 text-xs text-slate-700 border border-slate-200">
              <?= $h($floor !== '' ? $floor : '—') ?>
            </span>
          </td>

          <!-- HK -->
          <td class="p-3">
            <?php if ($hk !== ''): ?>
              <span class="inline-flex items-center px-2 py-0.5 rounded-full border text-xs font-medium <?= $hkClass ?>">
                <?= $h(ucwords($hk)) ?>
              </span>
            <?php else: ?>
              <span class="text-xs text-slate-400">—</span>
            <?php endif; ?>
          </td>

          <!-- Room Status -->
          <td class="p-3">
            <?php if ($status !== ''): ?>
              <span class="inline-flex items-center px-2 py-0.5 rounded-full border text-xs font-medium <?= $stClass ?>">
                <?= $h(ucwords(str_replace('_',' ',$status))) ?>
              </span>
            <?php else: ?>
              <span class="text-xs text-slate-400">—</span>
            <?php endif; ?>
          </td>

          <!-- Notes -->
          <td class="p-3 text-xs text-slate-600 max-w-xs">
            <div class="line-clamp-2">
              <?= $h($notes !== '' ? $notes : '—') ?>
            </div>
          </td>

          <!-- Actions -->
          <td class="p-3 text-right whitespace-nowrap space-x-1">
            <!-- HK quick dropdown -->
            <form method="post"
                  action="<?= $h($base) ?>/rooms/<?= $id ?>/hk-status"
                  class="inline-flex items-center gap-1">
              <select name="hk"
                      class="border border-slate-300 rounded-lg px-2 py-1 text-[11px]"
                      onchange="this.form.submit()">
                <option value="">HK</option>
                <?php foreach (['clean','dirty','inspected','pickup'] as $opt): ?>
                  <option value="<?= $opt ?>" <?= $hk === $opt ? 'selected' : '' ?>>
                    <?= ucfirst($opt) ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </form>

            <!-- OOO -->
            <form method="post"
                  action="<?= $h($base) ?>/rooms/<?= $id ?>/toggle-ooo"
                  class="inline-block"
                  onsubmit="return confirm('Toggle Out of Order for this room?');">
              <button type="submit"
                      class="inline-flex items-center px-2 py-1 rounded-lg text-[11px] border border-slate-300 text-slate-600 hover:bg-slate-100">
                OOO
              </button>
            </form>

            <!-- OOS -->
            <form method="post"
                  action="<?= $h($base) ?>/rooms/<?= $id ?>/toggle-oos"
                  class="inline-block"
                  onsubmit="return confirm('Toggle Out of Service for this room?');">
              <button type="submit"
                      class="inline-flex items-center px-2 py-1 rounded-lg text-[11px] border border-slate-300 text-slate-600 hover:bg-slate-100">
                OOS
              </button>
            </form>

            <!-- Edit -->
            <a href="<?= $h($base) ?>/rooms/<?= $id ?>/edit"
               class="inline-flex items-center px-3 py-1.5 rounded-lg border border-slate-300 text-xs hover:bg-slate-100">
              <i class="fa-regular fa-pen-to-square mr-1"></i>Edit
            </a>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- ===== Quick lookup redirect wiring (uses global KF.lookup) ===== -->
<script>
document.addEventListener('DOMContentLoaded', function () {
  var input = document.getElementById('hf-room-quick');
  var hidden = document.getElementById('hf-room-quick-id');
  if (!input || !hidden) return;

  input.addEventListener('change', function () {
    var id = hidden.value ? parseInt(hidden.value, 10) : 0;
    if (!id || isNaN(id)) return;
    var base = '<?= $h($base) ?>'.replace(/\/$/, '');
    window.location.href = base + '/rooms/' + id;
  });
});
</script>