<?php
/** @var array $roomTypes @var array $ratePlans @var string $module_base */
$h    = fn($v)=>htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
$base = rtrim((string)($module_base ?? '/apps/hotelflow'), '/');
?>
<div class="max-w-[1100px] mx-auto space-y-6"
     x-data="hfCreateRes('<?= $h($base) ?>')"
     x-init="init()">

  <div class="flex items-center justify-between mb-2">
    <div>
      <h1 class="text-2xl font-extrabold">New Reservation</h1>
    </div>
    <a href="<?= $h($base) ?>/reservations"
       class="px-3 py-2 rounded-lg border border-slate-300 text-sm hover:bg-slate-50">
      Back
    </a>
  </div>

  <?php $tab='reservations'; include __DIR__.'/../frontdesk/_tabs.php'; ?>

  <!-- IMPORTANT: form wraps everything, including modal, so all fields POST -->
  <form method="post"
        action="<?= $h($base) ?>/reservations"
        enctype="multipart/form-data"
        class="space-y-4 lg:space-y-6"
        data-hf-res="1">

    <!-- Always treat as NEW guest via popup -->
    <input type="hidden" name="guest_mode" value="new">

    <!-- =================================================================== -->
    <!-- 1. STAY (top, single main card)                                     -->
    <!-- =================================================================== -->
    <section class="rounded-xl border border-slate-200 bg-white p-4 space-y-3">
      <div class="font-semibold mb-1">Stay</div>
      <div class="grid grid-cols-2 gap-3">
        <div>
          <label class="text-sm text-slate-600">Check-in</label>
          <input type="date" name="check_in"
                 x-model="ci" @change="recalc"
                 required
                 class="mt-1 w-full px-3 py-2 rounded-lg border border-slate-300">
        </div>
        <div>
          <label class="text-sm text-slate-600">Check-out</label>
          <input type="date" name="check_out"
                 x-model="co" @change="recalc"
                 required
                 class="mt-1 w-full px-3 py-2 rounded-lg border border-slate-300">
        </div>
        <div>
          <label class="text-sm text-slate-600">Adults</label>
          <input type="number" name="adults"
                 min="1" value="1"
                 x-model.number="adults" @input="recalcGuests"
                 class="mt-1 w-full px-3 py-2 rounded-lg border border-slate-300">
        </div>
        <div>
          <label class="text-sm text-slate-600">Children</label>
          <input type="number" name="children"
                 min="0" value="0"
                 x-model.number="children" @input="recalcGuests"
                 class="mt-1 w-full px-3 py-2 rounded-lg border border-slate-300">
        </div>
        <div class="col-span-2 flex justify-between text-sm text-slate-500">
          <span>
            Nights:
            <span class="font-medium" x-text="nights"></span>
          </span>
          <span>
            Total Guests (Adults + Children):
            <span class="font-medium" x-text="expectedGuests"></span>
          </span>
        </div>
      </div>
    </section>

    <!-- =================================================================== -->
    <!-- 2. GUEST MANAGER SUMMARY (button opens modal)                       -->
    <!-- =================================================================== -->
    <section class="rounded-xl border border-slate-200 bg-white p-4 space-y-3">
      <div class="flex items-center justify-between mb-1">
        <div class="font-semibold">Guests &amp; Identity</div>
        <button type="button"
                @click="openGuestModal"
                class="inline-flex items-center gap-2 px-3 py-1.5 rounded-lg text-sm border border-emerald-600 text-emerald-700 hover:bg-emerald-50">
          <i class="fa fa-users"></i>
          <span>
            Manage Guests (
              <span x-text="guests.length"></span>
              /
              <span x-text="expectedGuests"></span>
            )
          </span>
        </button>
      </div>

      <p class="text-sm text-slate-500">
        System must collect all guests’ details and ID / face capture before a room is finally confirmed.
        Use <b>Manage Guests</b> to enter each person one-by-one.
      </p>

      <!-- Simple list / preview -->
      <template x-if="guests.length">
        <div class="mt-3 border border-slate-200 rounded-lg divide-y divide-slate-200">
          <template x-for="(g, idx) in guests" :key="idx">
            <div class="px-3 py-2 flex items-center justify-between text-sm">
              <div>
                <div class="font-medium" x-text="g.name"></div>
                <div class="text-xs text-slate-500">
                  <span x-text="g.relation || 'Guest'"></span>
                  •
                  <span x-text="g.id_type || 'ID not set'"></span>
                  <template x-if="g.main">
                    <span class="ml-1 inline-flex items-center px-1.5 py-0.5 rounded-full bg-emerald-50 text-emerald-700 text-[11px]">
                      Main guest
                    </span>
                  </template>
                </div>
              </div>
              <button type="button"
                      class="text-xs text-red-600 hover:underline"
                      @click="removeGuest(idx)">
                Remove
              </button>
            </div>
          </template>
        </div>
      </template>

      <!-- JSON payload for backend (all guests meta) -->
      <input type="hidden" name="extra_guests_json"
             :value="JSON.stringify(guests)">

      <!-- Hidden flag to help backend know we used the multi-guest popup -->
      <input type="hidden" name="guest_capture_mode" value="popup">
    </section>

    <!-- =================================================================== -->
    <!-- 3. ROOM & RATE                                                      -->
    <!-- =================================================================== -->
    <section class="rounded-xl border border-slate-200 bg-white p-4 space-y-3">
      <div class="font-semibold mb-1">Room &amp; Rate</div>
      <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
        <div>
          <label class="text-sm text-slate-600">Room Type</label>
          <select name="room_type_id"
                  class="mt-1 w-full px-3 py-2 rounded-lg border border-slate-300">
            <option value="0">—</option>
            <?php foreach ($roomTypes as $rt): ?>
              <option value="<?= (int)$rt['id'] ?>"><?= $h((string)$rt['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label class="text-sm text-slate-600">Rate Plan</label>
          <select name="rate_plan_id"
                  class="mt-1 w-full px-3 py-2 rounded-lg border border-slate-300">
            <option value="0">—</option>
            <?php foreach ($ratePlans as $rp): ?>
              <option value="<?= (int)$rp['id'] ?>"><?= $h((string)$rp['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>
    </section>

    <!-- =================================================================== -->
    <!-- 4. NOTES + FINAL SAVE                                               -->
    <!-- =================================================================== -->
    <section class="rounded-xl border border-slate-200 bg-white p-4 space-y-3">
      <div class="font-semibold mb-1">Notes</div>
      <textarea name="notes" rows="3"
                class="w-full px-3 py-2 rounded-lg border border-slate-300"></textarea>

      <p class="text-xs text-slate-500">
        For security, system expects
        <span class="font-semibold" x-text="expectedGuests"></span>
        guest record(s) with ID &amp; face capture.
        If counts don’t match, the system will still allow saving but staff should review.
      </p>

      <div class="mt-3 flex justify-end gap-2">
        <a href="<?= $h($base) ?>/reservations"
           class="px-4 py-2 rounded-lg border border-slate-300 text-sm hover:bg-slate-50">
          Cancel
        </a>
        <button type="submit"
                class="px-4 py-2 rounded-lg text-sm font-semibold text-white disabled:opacity-60 disabled:cursor-not-allowed"
                style="background:var(--brand)"
                id="hf-res-save">
          Save
        </button>
      </div>

      <p class="text-[11px] text-slate-500 text-right">
        Tip: Press <kbd>Enter</kbd> anywhere on this page (outside the guest popup / notes) to save quickly.
      </p>
    </section>

    <!-- =================================================================== -->
    <!-- 5. GUEST DETAILS + BIOMETRIC MODAL (auto-adjust height)             -->
    <!-- =================================================================== -->
    <div x-show="guestModalOpen"
         x-cloak
         class="fixed inset-0 z-40 flex items-start justify-center bg-black/40 overflow-y-auto">
      <div class="bg-white rounded-2xl shadow-xl max-w-3xl w-full mx-4 my-6 relative
                  max-h-[90vh] flex flex-col">

        <!-- Header (fixed) -->
        <div class="flex items-center justify-between px-5 py-4 border-b border-slate-200 flex-none">
          <div>
            <h2 class="text-lg font-semibold">Guest details &amp; identity</h2>
            <p class="text-xs text-slate-500">
              One by one. System expects
              <span x-text="expectedGuests"></span> guest(s). Added:
              <span x-text="guests.length"></span>/<span x-text="expectedGuests"></span>.
            </p>
          </div>
          <button type="button"
                  class="text-slate-400 hover:text-slate-700"
                  @click="closeGuestModal">
            <i class="fa fa-times"></i>
          </button>
        </div>

        <!-- Scrollable body -->
        <div class="px-5 py-4 space-y-4 text-sm overflow-y-auto flex-1">
          <!-- Main guest basic info (this guest will be inserted into hms_guests) -->
          <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
            <div class="md:col-span-2">
              <label class="text-sm text-slate-600">
                Guest Name <span class="text-red-600">*</span>
              </label>
              <input type="text"
                     x-model="popup.name"
                     name="ng_name"
                     aria-required="true"
                     class="mt-1 w-full px-3 py-2 rounded-lg border border-slate-300">
            </div>

            <div>
              <label class="text-sm text-slate-600">Relation / Tag</label>
              <input type="text"
                     x-model="popup.relation"
                     placeholder="Main, Spouse, Child, Friend…"
                     class="mt-1 w-full px-3 py-2 rounded-lg border border-slate-300">
            </div>
            <div>
              <label class="text-sm text-slate-600">ID Type</label>
              <select x-model="popup.id_type"
                      name="ng_id_type"
                      class="mt-1 w-full px-3 py-2 rounded-lg border border-slate-300">
                <option value="">—</option>
                <option value="NID">NID / National ID</option>
                <option value="Passport">Passport</option>
                <option value="DL">Driving License</option>
              </select>
            </div>

            <div class="md:col-span-2">
              <label class="text-sm text-slate-600">ID Number</label>
              <input type="text"
                     x-model="popup.id_number"
                     name="ng_id_number"
                     class="mt-1 w-full px-3 py-2 rounded-lg border border-slate-300">
            </div>

            <div>
              <label class="text-sm text-slate-600">Country</label>
              <input type="text" name="ng_country"
                     x-model="popup.country"
                     placeholder="Bangladesh"
                     class="mt-1 w-full px-3 py-2 rounded-lg border border-slate-300">
            </div>
            <div>
              <label class="text-sm text-slate-600">
                Mobile <span class="text-red-600">*</span>
              </label>
              <input type="tel"
                     name="ng_mobile"
                     x-model="popup.mobile"
                     aria-required="true"
                     class="mt-1 w-full px-3 py-2 rounded-lg border border-slate-300">
            </div>
            <div>
              <label class="text-sm text-slate-600">Email</label>
              <input type="email"
                     name="ng_email"
                     x-model="popup.email"
                     class="mt-1 w-full px-3 py-2 rounded-lg border border-slate-300">
            </div>
            <div>
              <label class="text-sm text-slate-600">Channel</label>
              <select name="channel"
                      x-model="popup.channel"
                      class="mt-1 w-full px-3 py-2 rounded-lg border border-slate-300">
                <option value="Direct">Direct</option>
                <option value="Booking.com">Booking.com</option>
                <option value="Expedia">Expedia</option>
                <option value="Phone">Phone</option>
              </select>
            </div>

            <div class="md:col-span-2">
              <label class="text-sm text-slate-600">Address</label>
              <input type="text"
                     name="ng_address"
                     x-model="popup.address"
                     placeholder="Street, city, ZIP"
                     class="mt-1 w-full px-3 py-2 rounded-lg border border-slate-300">
            </div>
          </div>

          <!-- BIOMETRIC / CAMERA CAPTURE BLOCKS -->
          <div class="mt-4 grid grid-cols-1 md:grid-cols-3 gap-4">
            <!-- ID front -->
            <div id="hf-bio-id-front"
                 class="border border-slate-200 rounded-xl p-3 space-y-2 text-sm"
                 data-kind="id_front">
              <div class="flex items-center justify-between">
                <div class="font-semibold text-slate-800">
                  ID Capture (Front)
                </div>
                <button type="button"
                        data-action="start"
                        class="inline-flex items-center gap-1 px-2 py-1 rounded-lg border border-slate-300 text-xs hover:bg-slate-50">
                  <i class="fa fa-camera"></i>
                  <span>Start camera</span>
                </button>
              </div>
              <video class="hf-video mt-2 w-full rounded-lg border border-slate-300 hidden"
                     autoplay playsinline></video>
              <canvas class="hf-canvas mt-2 w-full rounded-lg border border-slate-300 hidden"></canvas>

              <div class="flex flex-wrap gap-2 mt-2">
                <button type="button"
                        data-action="capture"
                        class="hidden px-3 py-1.5 rounded-lg bg-emerald-600 text-white text-xs hover:bg-emerald-700">
                  Capture
                </button>
                <button type="button"
                        data-action="save"
                        class="hidden px-3 py-1.5 rounded-lg bg-slate-900 text-white text-xs hover:bg-slate-800">
                  Save &amp; store
                </button>
                <button type="button"
                        data-action="retake"
                        class="hidden px-3 py-1.5 rounded-lg border border-slate-300 text-xs hover:bg-slate-50">
                  Retake
                </button>
              </div>

              <p class="text-[11px] text-slate-500 mt-1"
                 data-role="status">
                Camera is off. Click “Start camera” to capture ID front.
              </p>

              <input type="hidden" name="bio_id_front_path" value="">
            </div>

            <!-- ID back -->
            <div id="hf-bio-id-back"
                 class="border border-slate-200 rounded-xl p-3 space-y-2 text-sm"
                 data-kind="id_back">
              <div class="flex items-center justify-between">
                <div class="font-semibold text-slate-800">
                  ID Capture (Back)
                </div>
                <button type="button"
                        data-action="start"
                        class="inline-flex items-center gap-1 px-2 py-1 rounded-lg border border-slate-300 text-xs hover:bg-slate-50">
                  <i class="fa fa-camera"></i>
                  <span>Start camera</span>
                </button>
              </div>
              <video class="hf-video mt-2 w-full rounded-lg border border-slate-300 hidden"
                     autoplay playsinline></video>
              <canvas class="hf-canvas mt-2 w-full rounded-lg border border-slate-300 hidden"></canvas>

              <div class="flex flex-wrap gap-2 mt-2">
                <button type="button"
                        data-action="capture"
                        class="hidden px-3 py-1.5 rounded-lg bg-emerald-600 text-white text-xs hover:bg-emerald-700">
                  Capture
                </button>
                <button type="button"
                        data-action="save"
                        class="hidden px-3 py-1.5 rounded-lg bg-slate-900 text-white text-xs hover:bg-slate-800">
                  Save &amp; store
                </button>
                <button type="button"
                        data-action="retake"
                        class="hidden px-3 py-1.5 rounded-lg border border-slate-300 text-xs hover:bg-slate-50">
                  Retake
                </button>
              </div>

              <p class="text-[11px] text-slate-500 mt-1"
                 data-role="status">
                Camera is off. Click “Start camera” to capture ID back.
              </p>

              <input type="hidden" name="bio_id_back_path" value="">
            </div>

            <!-- Face / biometric -->
            <div id="hf-bio-face"
                 class="border border-slate-200 rounded-xl p-3 space-y-2 text-sm"
                 data-kind="face">
              <div class="flex items-center justify-between">
                <div>
                  <div class="font-semibold text-slate-800">
                    Guest Face Capture
                  </div>
                  <div class="text-[11px] text-slate-500">
                    Keep face centered. We’ll use this for biometric verification.
                  </div>
                </div>
                <button type="button"
                        data-action="start"
                        class="inline-flex items-center gap-1 px-2 py-1 rounded-lg border border-slate-300 text-xs hover:bg-slate-50">
                  <i class="fa fa-camera"></i>
                  <span>Start camera</span>
                </button>
              </div>
              <video class="hf-video mt-2 w-full rounded-lg border border-slate-300 hidden"
                     autoplay playsinline></video>
              <canvas class="hf-canvas mt-2 w-full rounded-lg border border-slate-300 hidden"></canvas>

              <div class="flex flex-wrap gap-2 mt-2">
                <button type="button"
                        data-action="capture"
                        class="hidden px-3 py-1.5 rounded-lg bg-emerald-600 text-white text-xs hover:bg-emerald-700">
                  Capture
                </button>
                <button type="button"
                        data-action="save"
                        class="hidden px-3 py-1.5 rounded-lg bg-slate-900 text-white text-xs hover:bg-slate-800">
                  Save &amp; store
                </button>
                <button type="button"
                        data-action="retake"
                        class="hidden px-3 py-1.5 rounded-lg border border-slate-300 text-xs hover:bg-slate-50">
                  Retake
                </button>
              </div>

              <p class="text-[11px] text-slate-500 mt-1"
                 data-role="status">
                Camera is off. Click “Start camera” to capture guest face.
              </p>

              <input type="hidden" name="bio_face_path" value="">
            </div>
          </div>

          <p class="mt-2 text-[11px] text-slate-500">
            Note: This popup is used to store and count all persons in the room. Current guest will be treated
            as the <b>main guest</b> for this reservation; backend can parse
            <code>extra_guests_json</code> for additional persons.
          </p>
        </div>

        <!-- Footer (fixed) -->
        <div class="px-5 py-4 border-t border-slate-200 flex justify-between flex-none">
          <button type="button"
                  class="px-3 py-2 text-sm rounded-lg border border-slate-300 hover:bg-slate-50"
                  @click="closeGuestModal">
            Close
          </button>
          <button type="button"
                  class="px-3 py-2 text-sm rounded-lg bg-emerald-600 text-white hover:bg-emerald-700"
                  @click="saveGuestFromPopup">
            Save &amp; Next
          </button>
        </div>
      </div>
    </div>
  </form>
</div>

<!-- ========================== JS: Alpine state ========================== -->
<script>
function hfCreateRes(base){
  window.HF_RES_BASE = window.HF_RES_BASE || base;
  return {
    base,
    // stay
    ci: '',
    co: '',
    nights: 0,
    adults: 1,
    children: 0,
    expectedGuests: 1,

    // guests popup + list
    guestModalOpen: false,
    guests: [],
    popup: {
      name: '',
      relation: '',
      id_type: '',
      id_number: '',
      country: 'Bangladesh',
      mobile: '',
      email: '',
      channel: 'Direct',
      address: ''
    },

    init(){
      // ensure expectedGuests is synced with adults/children on load
      this.recalcGuests();

      // Global Enter shortcut: submit form, except when popup open or typing in textarea
      document.addEventListener('keydown', (e) => {
        const key = (e.key || '').toLowerCase();
        if (key !== 'enter') return;

        const tag = (e.target && e.target.tagName || '').toLowerCase();
        if (tag === 'textarea' || tag === 'button') return;
        if (e.ctrlKey || e.altKey || e.metaKey || e.shiftKey) return;
        if (this.guestModalOpen) return; // don’t submit from inside popup

        const form = document.querySelector('form[data-hf-res]');
        const btn  = form ? form.querySelector('#hf-res-save') : null;
        if (form && btn && !btn.disabled) {
          e.preventDefault();
          btn.click();
        }
      });
    },

    recalc(){
      if(!this.ci || !this.co){ this.nights = 0; return; }
      const a = new Date(this.ci), b = new Date(this.co);
      this.nights = Math.max(0, Math.round((b-a)/(1000*60*60*24)));
    },
    recalcGuests(){
      const ad = Number(this.adults) || 0;
      const ch = Number(this.children) || 0;
      this.expectedGuests = Math.max(1, ad + ch);
    },

    openGuestModal(){
      if (this.expectedGuests < 1) this.recalcGuests();
      this.guestModalOpen = true;
    },
    closeGuestModal(){
      this.guestModalOpen = false;
    },

    saveGuestFromPopup(){
      if (!this.popup.name || !this.popup.mobile) {
        alert('Guest name and mobile are required.');
        return;
      }

      // push meta for list + JSON payload
      this.guests.push({
        name: this.popup.name,
        relation: this.popup.relation,
        id_type: this.popup.id_type,
        id_number: this.popup.id_number,
        main: (this.guests.length === 0) // first one = main guest
      });

      // After pushing, if we already reached expected guest count, close modal
      if (this.guests.length >= this.expectedGuests) {
        this.guestModalOpen = false;
      }

      // IMPORTANT: do NOT clear popup fields so backend still sees ng_* values
      // (controller will read ng_name, ng_mobile, etc. when saving guest)
    },

    removeGuest(idx){
      this.guests.splice(idx, 1);
    }
  }
}
</script>

<!-- ========================== JS: Webcam / biometric (unchanged) ======= -->
<script>
(function(){
  let hfStream = null;
  const saved = { id_front:false, id_back:false, face:false };

  function stopCamera(){
    if (hfStream) {
      hfStream.getTracks().forEach(t => t.stop());
      hfStream = null;
    }
    document.querySelectorAll('.hf-video').forEach(v => { v.srcObject = null; });
  }

  async function startCamera(section, video, statusEl){
    try{
      if (hfStream) stopCamera();
      const stream = await navigator.mediaDevices.getUserMedia({video:true, audio:false});
      hfStream = stream;
      video.srcObject = stream;
      video.classList.remove('hidden');
      await video.play();
      if (statusEl) statusEl.textContent = 'Camera is active. Position correctly, then press Capture.';
    }catch(e){
      if (statusEl) statusEl.textContent = 'Unable to access camera: ' + (e.message || e);
    }
  }

  function captureFrame(video, canvas, statusEl){
    if (!hfStream || !video.srcObject) {
      if (statusEl) statusEl.textContent = 'Camera is not active.';
      return false;
    }
    const w = video.videoWidth || 640;
    const h = video.videoHeight || 480;
    canvas.width = w;
    canvas.height = h;
    const ctx = canvas.getContext('2d');
    ctx.drawImage(video, 0, 0, w, h);
    canvas.classList.remove('hidden');
    if (statusEl) statusEl.textContent = 'Preview captured. Click “Save & store” to upload.';
    return true;
  }

  function initSection(root){
    if (!root) return;
    const kind       = root.dataset.kind || '';
    const video      = root.querySelector('.hf-video');
    const canvas     = root.querySelector('.hf-canvas');
    const btnStart   = root.querySelector('[data-action="start"]');
    const btnCapture = root.querySelector('[data-action="capture"]');
    const btnSave    = root.querySelector('[data-action="save"]');
    const btnRetake  = root.querySelector('[data-action="retake"]');
    const statusEl   = root.querySelector('[data-role="status"]');
    const hidden     = root.querySelector('input[type="hidden"]');

    if (!video || !canvas || !btnStart || !btnCapture || !btnSave || !btnRetake || !hidden) return;

    btnStart.addEventListener('click', function(){
      btnStart.classList.add('hidden');
      btnCapture.classList.remove('hidden');
      btnSave.classList.add('hidden');
      btnRetake.classList.add('hidden');
      canvas.classList.add('hidden');
      startCamera(root, video, statusEl);
    });

    btnCapture.addEventListener('click', function(){
      if (!captureFrame(video, canvas, statusEl)) return;
      btnCapture.classList.add('hidden');
      btnSave.classList.remove('hidden');
      btnRetake.classList.remove('hidden');
    });

    btnRetake.addEventListener('click', function(){
      if (hidden.value){
        hidden.value = '';
        if (statusEl) statusEl.textContent = 'Previous capture cleared. Start camera to capture again.';
        saved[kind] = false;
      }
      canvas.classList.add('hidden');
      btnSave.classList.add('hidden');
      btnCapture.classList.remove('hidden');
      btnRetake.classList.add('hidden');
      startCamera(root, video, statusEl);
    });

    btnSave.addEventListener('click', function(){
      canvas.toBlob(async function(blob){
        if (!blob){
          if (statusEl) statusEl.textContent = 'Could not read captured image.';
          return;
        }
        const uploadUrl = (window.HF_RES_BASE || '').replace(/\/$/,'') + '/biometric/upload';
        const fd = new FormData();
        fd.append('kind', kind);
        fd.append('image', blob, kind + '.jpg');

        btnSave.disabled = true;
        if (statusEl) statusEl.textContent = 'Uploading…';

        try{
          const resp = await fetch(uploadUrl, {
            method: 'POST',
            body: fd,
            headers: {'X-Requested-With': 'XMLHttpRequest'}
          });
          const js = await resp.json();
          if (!resp.ok || !js || js.ok === false){
            throw new Error(js && js.error ? js.error : 'Upload failed');
          }

          hidden.value = js.path || '';
          saved[kind] = true;
          if (statusEl) statusEl.textContent = 'Saved successfully. Camera closed for this section.';

          stopCamera();
          video.classList.add('hidden');
          btnSave.classList.add('hidden');
          btnCapture.classList.add('hidden');
          btnRetake.classList.remove('hidden');
          btnRetake.textContent = 'Change photo';

          if (saved.id_front && saved.id_back && saved.face) {
            stopCamera();
          }

        }catch(e){
          if (statusEl) statusEl.textContent = 'Upload error: ' + (e.message || e);
        }finally{
          btnSave.disabled = false;
        }
      }, 'image/jpeg', 0.9);
    });
  }

  document.addEventListener('DOMContentLoaded', function(){
    initSection(document.getElementById('hf-bio-id-front'));
    initSection(document.getElementById('hf-bio-id-back'));
    initSection(document.getElementById('hf-bio-face'));
  });
})();
</script>