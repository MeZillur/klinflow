<?php
/** @var array|null $row */
/** @var string     $mode */
/** @var array      $roomTypes */
/** @var array      $floors */
/** @var string     $module_base */

$h    = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
$base = isset($module_base) ? rtrim((string)$module_base, '/') : '/t/hotel/apps/hotelflow';

$mode    = $mode ?? 'create';
$isEdit  = $mode === 'edit';
$id      = (int)($row['id'] ?? 0);

$roomNo      = (string)($row['room_no']      ?? '');
$name        = (string)($row['name']         ?? '');
$roomTypeId  = (int)   ($row['room_type_id'] ?? 0);
$floorVal    = (string)($row['floor']        ?? '');
$hkStatusVal = (string)($row['hk_status']    ?? '');
$roomStatus  = (string)($row['room_status']  ?? '');
$amenities   = (string)($row['amenities']    ?? '');
$notes       = (string)($row['notes']        ?? '');

$hkOptions = [
    ''           => 'Not set',
    'clean'      => 'Clean',
    'dirty'      => 'Dirty',
    'inspected'  => 'Inspected',
    'out_of_service' => 'Out of service',
];

$roomStatusOptions = [
    ''              => 'Not set',
    'available'     => 'Available',
    'out_of_order'  => 'Out of order',
    'out_of_service'=> 'Out of service',
];
$action = $isEdit
    ? $base . '/rooms/' . $id
    : $base . '/rooms';
?>
<div class="p-6 space-y-6">

  <!-- Header + tiny menu -->
  <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
    <div>
      <h1 class="text-2xl font-semibold text-slate-900">
        <?= $isEdit ? 'Edit Room' : 'Add Room' ?>
      </h1>
      <p class="mt-1 text-sm text-slate-600">
        Define room basics, housekeeping status and internal notes. Changes apply only to this property &amp; organisation.
      </p>
    </div>

    <!-- Tiny related-menu -->
    <div class="inline-flex flex-wrap gap-2 text-xs">
      <a href="<?= $h($base) ?>/rooms"
         class="inline-flex items-center gap-1 rounded-full border border-slate-300 px-3 py-1.5 text-slate-700 bg-white hover:bg-slate-50">
        <i class="fa-solid fa-arrow-left text-[11px]"></i>
        Back to rooms
      </a>
      <a href="<?= $h($base) ?>/rooms/types"
         class="inline-flex items-center gap-1 rounded-full border border-slate-200 px-3 py-1.5 text-slate-700 bg-slate-50 hover:bg-slate-100">
        <i class="fa-solid fa-layer-group text-[11px]"></i>
        Room types
      </a>
      <a href="<?= $h($base) ?>/rooms/floors"
         class="inline-flex items-center gap-1 rounded-full border border-slate-200 px-3 py-1.5 text-slate-700 bg-slate-50 hover:bg-slate-100">
        <i class="fa-solid fa-building text-[11px]"></i>
        Floors
      </a>
    </div>
  </div>

  <!-- Main horizontal layout card -->
  <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-5 space-y-5">

    <!-- Top row: room id / name -->
    <form method="post" action="<?= $h($action) ?>" class="space-y-5">
      <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div>
          <label class="block text-xs font-medium text-slate-700 mb-1">
            Room No
          </label>
          <input
            type="text"
            name="room_no"
            value="<?= $h($roomNo) ?>"
            placeholder="101, 1A, 305..."
            class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm
                   focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500">
          <p class="mt-1 text-[11px] text-slate-500">
            Exact room number printed on door / keycard.
          </p>
        </div>

        <div>
          <label class="block text-xs font-medium text-slate-700 mb-1">
            Room Name (internal)
          </label>
          <input
            type="text"
            name="name"
            value="<?= $h($name) ?>"
            placeholder="Deluxe King City View"
            class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm
                   focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500">
          <p class="mt-1 text-[11px] text-slate-500">
            Optional alias for staff (used in reports &amp; dashboards).
          </p>
        </div>
      </div>

      <!-- Middle row: type / floor / HK -->
      <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <!-- Room type -->
        <div>
          <label class="block text-xs font-medium text-slate-700 mb-1">
            Room Type
          </label>
          <select
            name="room_type_id"
            class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm bg-white
                   focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500">
            <option value="0">Select type...</option>
            <?php foreach ($roomTypes as $rt): ?>
              <option value="<?= (int)$rt['id'] ?>"
                <?= $roomTypeId === (int)$rt['id'] ? 'selected' : '' ?>>
                <?= $h($rt['name'] ?? '') ?>
              </option>
            <?php endforeach; ?>
          </select>
          <p class="mt-1 text-[11px] text-slate-500">
            Used for pricing (ARI), availability and analytics.
          </p>
        </div>

        <!-- Floor -->
        <div>
          <label class="block text-xs font-medium text-slate-700 mb-1">
            Floor
          </label>
          <select
            name="floor"
            class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm bg-white
                   focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500">
            <option value="">Select floor...</option>
            <?php foreach ($floors as $f): ?>
              <option value="<?= $h($f) ?>"
                <?= $floorVal === (string)$f ? 'selected' : '' ?>>
                <?= $h($f) ?>
              </option>
            <?php endforeach; ?>
          </select>
          <p class="mt-1 text-[11px] text-slate-500">
            Comes from the Floors page; helps with housekeeping routing.
          </p>
        </div>

        <!-- HK status -->
        <div>
          <label class="block text-xs font-medium text-slate-700 mb-1">
            HK Status
          </label>
          <select
            name="hk_status"
            class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm bg-white
                   focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500">
            <?php foreach ($hkOptions as $val => $label): ?>
              <option value="<?= $h($val) ?>" <?= $hkStatusVal === $val ? 'selected' : '' ?>>
                <?= $h($label) ?>
              </option>
            <?php endforeach; ?>
          </select>
          <p class="mt-1 text-[11px] text-slate-500">
            Current housekeeping state (used on Front Desk &amp; HK board).
          </p>
        </div>
      </div>

      <!-- Third row: room status / amenities -->
      <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <!-- Room status -->
        <div>
          <label class="block text-xs font-medium text-slate-700 mb-1">
            Room Status
          </label>
          <select
            name="room_status"
            class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm bg-white
                   focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500">
            <?php foreach ($roomStatusOptions as $val => $label): ?>
              <option value="<?= $h($val) ?>" <?= $roomStatus === $val ? 'selected' : '' ?>>
                <?= $h($label) ?>
              </option>
            <?php endforeach; ?>
          </select>
          <p class="mt-1 text-[11px] text-slate-500">
            Use <strong>Out of order</strong> or <strong>Out of service</strong> to block selling.
          </p>
        </div>

        <!-- Amenities -->
        <div class="md:col-span-2">
          <label class="block text-xs font-medium text-slate-700 mb-1">
            Amenities (comma separated)
          </label>
          <input
            type="text"
            name="amenities"
            value="<?= $h($amenities) ?>"
            placeholder="AC, WiFi, TV, Mini-bar"
            class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm
                   focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500">
          <p class="mt-1 text-[11px] text-slate-500">
            Simple list for staff reference; later can be mapped to OTA attributes.
          </p>
        </div>
      </div>

      <!-- Notes -->
      <div>
        <label class="block text-xs font-medium text-slate-700 mb-1">
          Notes (internal)
        </label>
        <textarea
          name="notes"
          rows="3"
          placeholder="Any room-specific remarks (maintenance, view, noisy side, VIP usage...)."
          class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm
                 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500"><?= $h($notes) ?></textarea>
      </div>

      <!-- Footer actions -->
      <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between pt-3 border-t border-slate-100">
        <p class="text-[11px] text-slate-500">
          Changes apply only for this property &amp; organisation. Updating room type or status will
          reflect on the front desk board and availability screens.
        </p>
        <div class="flex gap-2 justify-end">
          <a href="<?= $h($base) ?>/rooms"
             class="inline-flex items-center gap-1 rounded-full border border-slate-300 px-4 py-2 text-xs font-medium text-slate-700 bg-white hover:bg-slate-50">
            Cancel
          </a>
          <button
            type="submit"
            class="inline-flex items-center gap-2 rounded-full bg-[#228B22] px-5 py-2 text-xs font-semibold text-white shadow-sm hover:bg-emerald-700 focus:outline-none focus:ring-2 focus:ring-offset-1 focus:ring-emerald-500">
            <i class="fa-regular fa-floppy-disk text-[11px]"></i>
            <?= $isEdit ? 'Save changes' : 'Create Room' ?>
          </button>
        </div>
      </div>
    </form>
  </div>

  <!-- How to use this page -->
  <div class="bg-slate-50 border border-slate-200 rounded-2xl px-4 py-4 text-sm text-slate-700 space-y-2 mt-2">
    <div class="flex items-center gap-2">
      <i class="fa-regular fa-lightbulb text-amber-500 text-base"></i>
      <h2 class="text-sm font-semibold text-slate-900">
        How to use the Add / Edit Room page
      </h2>
    </div>
    <ul class="list-disc list-inside text-[13px] leading-relaxed text-slate-700">
      <li>Start by defining <strong>Room Types</strong> and <strong>Floors</strong>, then create individual rooms here.</li>
      <li>Each room should map to exactly one <strong>Room Type</strong> so ARI and pricing stay consistent.</li>
      <li>Use <strong>HK Status</strong> for daily housekeeping workflow and <strong>Room Status</strong> to block / unblock sale.</li>
      <li>Keep <strong>Amenities</strong> short but meaningful; later we can sync them to OTA / website descriptions.</li>
      <li>Update notes whenever a room has recurring issues (noise, maintenance, special equipment) so the team stays aligned.</li>
    </ul>
  </div>
</div>