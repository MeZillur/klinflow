<?php
/** @var string $module_base */
$h    = fn($v)=>htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
$base = rtrim((string)($module_base ?? '/apps/hotelflow'), '/');
?>
<div class="max-w-[900px] mx-auto space-y-6"
     x-data="hfExistingReservation({ base: '<?= $h($base) ?>' })"
     x-init="boot()">

  <!-- Header -->
  <div class="flex items-center justify-between">
    <div>
      <h1 class="text-2xl font-extrabold tracking-tight">Reservation for Existing Guest</h1>
      <p class="text-slate-500 text-sm">
        Find a past guest by phone, email or name and create a new stay without re-typing their info.
      </p>
    </div>
    <a href="<?= $h($base) ?>/reservations"
       class="px-3 py-2 rounded-lg border border-slate-300 text-sm hover:bg-slate-50">
      <i class="fa-solid fa-arrow-left mr-2"></i>Back to list
    </a>
  </div>

  <!-- Step 1: lookup -->
  <section class="bg-white rounded-xl border border-slate-200 p-4 space-y-3">
    <h2 class="text-sm font-semibold text-slate-700 flex items-center gap-2">
      <span class="inline-flex h-6 w-6 rounded-full bg-emerald-100 text-emerald-700 items-center justify-center text-xs font-bold">1</span>
      Find guest
    </h2>

    <input type="hidden" name="guest_id" x-model="guest.id" id="guest_id">

    <div>
      <label class="text-xs font-medium text-slate-600">Search existing guest</label>
      <input
        type="text"
        placeholder="Type phone, email or name…"
        class="mt-1 w-full px-3 py-2 rounded-lg border border-slate-300 text-sm"
        data-kf-lookup="hf-guests"
        data-kf-endpoint="<?= $h($base) ?>/api/lookup/guests"
        data-kf-target-callback="hfExistingReservationSelect"
      >
      <p class="mt-1 text-[11px] text-slate-500">
        Start typing at least 3 characters. If no match is found, go back and use “New Reservation”.
      </p>
    </div>
  </section>

  <!-- Step 2: guest summary + reservation form -->
  <form method="post" action="<?= $h($base) ?>/reservations" class="space-y-6">
    <!-- if you have CSRF helper, inject hidden input here -->

    <!-- Guest details card -->
    <section class="bg-white rounded-xl border border-slate-200 p-4 space-y-3">
      <h2 class="text-sm font-semibold text-slate-700 flex items-center gap-2">
        <span class="inline-flex h-6 w-6 rounded-full bg-emerald-100 text-emerald-700 items-center justify-center text-xs font-bold">2</span>
        Guest details
      </h2>

      <template x-if="!guest.id">
        <p class="text-sm text-slate-500">
          No guest selected yet. Use the search box above to pick an existing guest.
        </p>
      </template>

      <template x-if="guest.id">
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
          <div class="col-span-1 sm:col-span-2">
            <div class="text-xs text-slate-500 mb-1">Guest</div>
            <div class="text-base font-semibold" x-text="guest.name || 'Guest'"></div>
            <div class="text-xs text-slate-500 mt-1">
              <span x-text="guest.phone || 'No phone'"></span>
              <span x-show="guest.email" class="ml-2">• <span x-text="guest.email"></span></span>
            </div>
          </div>
          <div class="text-xs text-slate-500">
            <div class="font-semibold mb-1">Notes</div>
            <p>
              Returning guest. Their profile will be reused and linked to this reservation.
            </p>
          </div>
        </div>
      </template>

      <!-- Hidden fields that store guest snapshot like normal create form -->
      <input type="hidden" name="guest_name"  x-model="guest.name">
      <input type="hidden" name="guest_phone" x-model="guest.phone">
      <input type="hidden" name="guest_email" x-model="guest.email">
    </section>

    <!-- Reservation fields -->
    <section class="bg-white rounded-xl border border-slate-200 p-4 space-y-4">
      <h2 class="text-sm font-semibold text-slate-700 flex items-center gap-2">
        <span class="inline-flex h-6 w-6 rounded-full bg-emerald-100 text-emerald-700 items-center justify-center text-xs font-bold">3</span>
        Stay details
      </h2>

      <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
        <label class="text-xs text-slate-600">
          Check-in
          <input type="date" name="check_in"
                 class="mt-1 w-full px-3 py-2 rounded-lg border border-slate-300 text-sm"
                 x-model="stay.check_in">
        </label>
        <label class="text-xs text-slate-600">
          Check-out
          <input type="date" name="check_out"
                 class="mt-1 w-full px-3 py-2 rounded-lg border border-slate-300 text-sm"
                 x-model="stay.check_out">
        </label>
      </div>

      <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
        <label class="text-xs text-slate-600">
          Room type (optional for now)
          <input type="text" name="room_type_name"
                 placeholder="Deluxe Double, Suite…"
                 class="mt-1 w-full px-3 py-2 rounded-lg border border-slate-300 text-sm">
        </label>
        <label class="text-xs text-slate-600">
          Reference / source
          <input type="text" name="source"
                 placeholder="Walk-in, Website, OTA…"
                 class="mt-1 w-full px-3 py-2 rounded-lg border border-slate-300 text-sm">
        </label>
      </div>

      <label class="text-xs text-slate-600">
        Internal notes
        <textarea name="internal_note" rows="2"
                  class="mt-1 w-full px-3 py-2 rounded-lg border border-slate-300 text-sm"
                  placeholder="Any special instructions for this stay…"></textarea>
      </label>
    </section>

    <!-- Submit -->
    <div class="flex items-center justify-between">
      <p class="text-xs text-slate-500">
        When you save, this reservation will be linked to the selected guest profile.
      </p>
      <button type="submit"
              class="px-4 py-2 rounded-lg text-white text-sm font-semibold disabled:opacity-60"
              :disabled="!guest.id"
              style="background:var(--brand)">
        <i class="fa-solid fa-circle-check mr-2"></i>Create reservation
      </button>
    </div>
  </form>
</div>

<script>
/**
 * Alpine state for Existing Guest reservation
 */
function hfExistingReservation(cfg){
  return {
    base: cfg.base || '/apps/hotelflow',
    guest: { id: null, name: '', phone: '', email: '' },
    stay: {
      check_in: new Date().toISOString().slice(0,10),
      check_out: new Date(Date.now() + 86400000).toISOString().slice(0,10),
    },
    boot(){
      // ensure KF lookup sees this page
      if (window.KF && KF.rescan) KF.rescan(document);
      window.hfExistingReservationSelect = (item) => {
        // item comes from /api/lookup/guests -> {id,name,phone,email,...}
        this.guest.id    = item.id || null;
        this.guest.name  = item.name || '';
        this.guest.phone = item.phone || '';
        this.guest.email = item.email || '';
      };
    }
  }
}
</script>