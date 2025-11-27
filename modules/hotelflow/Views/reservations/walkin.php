<?php
/** @var array  $ctx */
/** @var string $module_base */

$h    = fn($v)=>htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
$base = rtrim((string)($module_base ?? '/apps/hotelflow'), '/');
?>
<div class="max-w-6xl mx-auto space-y-6">

  <!-- SEGMENT 1: Header + tiny nav -->
  <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
    <div>
      <h1 class="text-2xl font-extrabold tracking-tight">Walk-in guest</h1>
      <p class="text-slate-500 text-sm">
        Create a reservation for a guest who arrives without a prior booking. Capture guest details and biometrics in one flow.
      </p>
    </div>

    <div class="flex flex-wrap gap-2 text-sm">
      <a href="<?= $h($base) ?>/reservations"
         class="inline-flex items-center gap-2 px-3 py-1.5 rounded-lg border border-slate-300 bg-white hover:bg-slate-50">
        <i class="fa-solid fa-list"></i>
        <span>Back to reservations</span>
      </a>
      <a href="<?= $h($base) ?>/rooms"
         class="inline-flex items-center gap-2 px-3 py-1.5 rounded-lg border border-slate-200 bg-slate-50 hover:bg-slate-100">
        <i class="fa-solid fa-bed"></i>
        <span>Rooms grid</span>
      </a>
    </div>
  </div>

  <!-- SEGMENT 2: Form layout (horizontal 2-column) -->
  <form method="post"
        action="<?= $h($base) ?>/reservations"
        class="grid gap-6 lg:grid-cols-[2fr,1.3fr]">

    <!-- LEFT COLUMN: Stay + Guest + summary -->
    <div class="space-y-6">

      <!-- Stay details -->
      <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
        <div class="flex items-center justify-between mb-4">
          <div>
            <h2 class="text-sm font-semibold text-slate-900 uppercase tracking-wide">Stay details</h2>
            <p class="text-xs text-slate-500 mt-0.5">
              Set dates and party size. You can refine room and rate from arrivals later.
            </p>
          </div>
          <span class="inline-flex items-center rounded-full bg-emerald-50 px-2 py-0.5 text-[11px] font-medium text-emerald-700 border border-emerald-100">
            <i class="fa-solid fa-person-walking mr-1"></i> Walk-in
          </span>
        </div>

        <div class="grid gap-3 sm:grid-cols-2">
          <div>
            <label class="block text-xs font-medium text-slate-700 mb-1">Check-in date</label>
            <input type="date" name="check_in"
                   class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                   required>
          </div>
          <div>
            <label class="block text-xs font-medium text-slate-700 mb-1">Check-out date</label>
            <input type="date" name="check_out"
                   class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                   required>
          </div>
        </div>

        <div class="grid gap-3 sm:grid-cols-2 mt-3">
          <div>
            <label class="block text-xs font-medium text-slate-700 mb-1">Adults</label>
            <input type="number" name="adults" min="1" value="1"
                   class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
          </div>
          <div>
            <label class="block text-xs font-medium text-slate-700 mb-1">Children</label>
            <input type="number" name="children" min="0" value="0"
                   class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
          </div>
        </div>

        <div class="mt-3">
          <label class="block text-xs font-medium text-slate-700 mb-1">Notes (optional)</label>
          <textarea name="notes" rows="2"
                    class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                    placeholder="Late check-in, VIP guest, rate override, special requests…"></textarea>
        </div>

        <!-- Channel is fixed as Walk-in -->
        <input type="hidden" name="channel" value="Walk-in">
      </div>

      <!-- Guest details (existing vs new) -->
      <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm"
           id="walkin-guest-card">
        <div class="flex items-center justify-between mb-4">
          <div>
            <h2 class="text-sm font-semibold text-slate-900 uppercase tracking-wide">Guest details</h2>
            <p class="text-xs text-slate-500 mt-0.5">
              Use an existing profile or create a new one with contact and ID info.
            </p>
          </div>

          <!-- Mode pills -->
          <div class="inline-flex rounded-full border border-slate-200 bg-slate-50 p-0.5 text-[11px]">
            <button type="button"
                    id="walkin-mode-existing"
                    class="px-2.5 py-1 rounded-full font-medium text-slate-600">
              Existing guest
            </button>
            <button type="button"
                    id="walkin-mode-new"
                    class="px-2.5 py-1 rounded-full font-medium text-slate-900 bg-white shadow-sm">
              New guest
            </button>
          </div>
        </div>

        <!-- hidden guest_mode used by ReservationsController::store() -->
        <input type="hidden" name="guest_mode" id="walkin-guest-mode-input" value="new">

        <!-- Existing guest block -->
        <div id="walkin-panel-existing" class="hidden space-y-3">
          <p class="text-xs text-slate-500">
            Search guest in your internal list, then link this walk-in stay to their profile.
          </p>

          <div class="grid gap-3 sm:grid-cols-[1fr,0.7fr]">
            <div>
              <label class="block text-xs font-medium text-slate-700 mb-1">
                Guest (ID or name search)
              </label>
              <input type="text" name="guest_name"
                     class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                     placeholder="Start typing name / company…">
            </div>
            <div>
              <label class="block text-xs font-medium text-slate-700 mb-1">
                Guest ID
              </label>
              <input type="number" name="guest_id"
                     class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                     placeholder="Internal ID">
            </div>
          </div>

          <p class="mt-1 text-[11px] text-slate-400">
            Tip: Later this can be wired to a lookup modal. For now, match the ID from your Guests list.
          </p>
        </div>

        <!-- New guest block -->
        <div id="walkin-panel-new" class="space-y-3">
          <div class="grid gap-3 sm:grid-cols-[1.3fr,0.7fr]">
            <div>
              <label class="block text-xs font-medium text-slate-700 mb-1">Full name</label>
              <input type="text" name="ng_name"
                     class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                     required>
            </div>
            <div>
              <label class="block text-xs font-medium text-slate-700 mb-1">Mobile</label>
              <input type="text" name="ng_mobile"
                     class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                     required>
            </div>
          </div>

          <div class="grid gap-3 sm:grid-cols-2">
            <div>
              <label class="block text-xs font-medium text-slate-700 mb-1">Email (optional)</label>
              <input type="email" name="ng_email"
                     class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
            </div>
            <div>
              <label class="block text-xs font-medium text-slate-700 mb-1">Nationality</label>
              <input type="text" name="ng_nationality"
                     class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                     value="Bangladesh">
            </div>
          </div>

          <div class="grid gap-3 sm:grid-cols-3">
            <div>
              <label class="block text-xs font-medium text-slate-700 mb-1">Gender</label>
              <select name="ng_gender"
                      class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                <option value="">Not specified</option>
                <option value="Male">Male</option>
                <option value="Female">Female</option>
                <option value="Other">Other</option>
              </select>
            </div>
            <div>
              <label class="block text-xs font-medium text-slate-700 mb-1">Age (optional)</label>
              <input type="number" name="ng_age" min="0"
                     class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
            </div>
            <div>
              <label class="block text-xs font-medium text-slate-700 mb-1">Country (optional)</label>
              <input type="text" name="ng_country"
                     class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
            </div>
          </div>

          <div>
            <label class="block text-xs font-medium text-slate-700 mb-1">Address (optional)</label>
            <textarea name="ng_address" rows="2"
                      class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                      placeholder="Street, city, company…"></textarea>
          </div>

          <div class="grid gap-3 sm:grid-cols-2">
            <div>
              <label class="block text-xs font-medium text-slate-700 mb-1">ID type</label>
              <input type="text" name="ng_id_type"
                     class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                     placeholder="NID, Passport, Driving license…">
            </div>
            <div>
              <label class="block text-xs font-medium text-slate-700 mb-1">ID number</label>
              <input type="text" name="ng_id_number"
                     class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
            </div>
          </div>
        </div>
      </div>

      <!-- Walk-in summary + primary action -->
      <div class="rounded-2xl border border-emerald-200 bg-emerald-50/70 p-5 shadow-sm">
        <h2 class="text-sm font-semibold text-emerald-900 uppercase tracking-wide">Walk-in summary</h2>
        <p class="text-xs text-emerald-800 mt-0.5">
          This will create a reservation with channel <strong>Walk-in</strong>. You can assign room and manage billing from the reservation page.
        </p>

        <ul class="mt-3 space-y-1 text-xs text-emerald-900/90">
          <li class="flex items-start gap-2">
            <i class="fa-solid fa-circle text-[6px] mt-1"></i>
            <span>Guest record is created or linked first.</span>
          </li>
          <li class="flex items-start gap-2">
            <i class="fa-solid fa-circle text-[6px] mt-1"></i>
            <span>Reservation is saved as a normal record (same table as all bookings).</span>
          </li>
          <li class="flex items-start gap-2">
            <i class="fa-solid fa-circle text-[6px] mt-1"></i>
            <span>You can later change status to <strong>in house</strong>, assign room and post charges.</span>
          </li>
        </ul>

        <div class="mt-4 flex flex-wrap items-center justify-between gap-3">
          <button type="submit"
                  class="inline-flex items-center gap-2 rounded-xl px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:shadow-md transition"
                  style="background:var(--brand)">
            <i class="fa-solid fa-right-to-bracket"></i>
            <span>Create walk-in reservation</span>
          </button>

          <span class="text-[11px] text-emerald-900/70">
            After saving, use the reservation screen to confirm status, assign room &amp; check-in.
          </span>
        </div>
      </div>
    </div>

    <!-- RIGHT COLUMN: BIOMETRIC & ID CAPTURE -->
    <div
      class="space-y-4"
      x-data="walkinBiometricCapture('<?= $h($base) ?>')"
      x-init="init()"
    >
      <div class="bg-white border border-slate-200 rounded-2xl p-4 shadow-sm">
        <div class="flex items-center justify-between mb-3">
          <div>
            <h3 class="text-sm font-semibold text-slate-900">Biometric &amp; ID capture</h3>
            <p class="text-xs text-slate-500">
              For new guests, capture face and ID images using the device camera.
            </p>
          </div>
          <div class="inline-flex rounded-full bg-emerald-50 px-2 py-0.5 text-[11px] font-medium text-emerald-700">
            Beta · Local device only
          </div>
        </div>

        <!-- CAMERA PREVIEW -->
        <div class="rounded-xl overflow-hidden bg-slate-950/90 relative">
          <video
            x-ref="video"
            class="w-full h-52 sm:h-56 object-cover"
            autoplay
            playsinline
            x-show="isStreaming"
          ></video>

          <canvas x-ref="canvas" class="hidden"></canvas>

          <div
            class="w-full h-52 sm:h-56 flex flex-col items-center justify-center text-slate-300 text-xs gap-2"
            x-show="!isStreaming"
          >
            <i class="fa-regular fa-id-card text-2xl mb-1"></i>
            <span>Camera is off</span>
            <span class="text-[11px] text-slate-400">
              Click “Start camera” and allow browser permission.
            </span>
          </div>
        </div>

        <div class="flex items-center justify-between mt-3">
          <div class="text-[11px] text-slate-500">
            Video stays in browser. Images are sent only when you save the form.
          </div>
          <div class="flex gap-2">
            <button
              type="button"
              @click="startCamera()"
              class="inline-flex items-center gap-1 px-3 py-1.5 rounded-lg text-xs font-semibold bg-emerald-600 text-white hover:bg-emerald-700"
            >
              <i class="fa-solid fa-camera"></i>
              <span>Start camera</span>
            </button>
            <button
              type="button"
              x-show="isStreaming"
              @click="stopCamera()"
              class="inline-flex items-center gap-1 px-3 py-1.5 rounded-lg text-xs font-semibold border border-slate-300 text-slate-700 hover:bg-slate-50"
            >
              <i class="fa-solid fa-stop"></i>
              <span>Stop</span>
            </button>
          </div>
        </div>

        <!-- FACE IMAGE -->
        <div class="mt-4 border-t border-slate-100 pt-3">
          <div class="flex items-center justify-between mb-2">
            <span class="text-xs font-semibold text-slate-800">Face image</span>
            <button
              type="button"
              @click="capture('face')"
              :disabled="!isStreaming"
              class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg text-[11px] font-semibold border border-slate-300 text-slate-700 hover:bg-slate-50 disabled:opacity-40 disabled:cursor-not-allowed"
            >
              <i class="fa-solid fa-user-circle"></i>
              <span>Capture</span>
            </button>
          </div>
          <div class="flex items-center gap-3">
            <img
              :src="facePreview || placeholder"
              class="w-16 h-16 rounded-lg object-cover border border-slate-200 bg-slate-100"
              alt="Face preview"
            />
            <input
              type="text"
              name="bio_face_path_display"
              x-model="faceLabel"
              readonly
              class="flex-1 rounded-lg border border-slate-200 bg-slate-50 px-3 py-1.5 text-xs text-slate-500"
              placeholder="Captured face image (saved on submit)"
            />
          </div>
        </div>

        <!-- ID FRONT -->
        <div class="mt-4 border-t border-slate-100 pt-3">
          <div class="flex items-center justify-between mb-2">
            <span class="text-xs font-semibold text-slate-800">ID front</span>
            <button
              type="button"
              @click="capture('id_front')"
              :disabled="!isStreaming"
              class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg text-[11px] font-semibold border border-slate-300 text-slate-700 hover:bg-slate-50 disabled:opacity-40 disabled:cursor-not-allowed"
            >
              <i class="fa-regular fa-id-card"></i>
              <span>Capture</span>
            </button>
          </div>
          <div class="flex items-center gap-3">
            <img
              :src="idFrontPreview || placeholder"
              class="w-16 h-16 rounded-lg object-cover border border-slate-200 bg-slate-100"
              alt="ID front preview"
            />
            <input
              type="text"
              name="bio_id_front_path_display"
              x-model="idFrontLabel"
              readonly
              class="flex-1 rounded-lg border border-slate-200 bg-slate-50 px-3 py-1.5 text-xs text-slate-500"
              placeholder="Captured ID front (saved on submit)"
            />
          </div>
        </div>

        <!-- ID BACK -->
        <div class="mt-4 border-t border-slate-100 pt-3">
          <div class="flex items-center justify-between mb-2">
            <span class="text-xs font-semibold text-slate-800">ID back</span>
            <button
              type="button"
              @click="capture('id_back')"
              :disabled="!isStreaming"
              class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg text-[11px] font-semibold border border-slate-300 text-slate-700 hover:bg-slate-50 disabled:opacity-40 disabled:cursor-not-allowed"
            >
              <i class="fa-regular fa-id-badge"></i>
              <span>Capture</span>
            </button>
          </div>
          <div class="flex items-center gap-3">
            <img
              :src="idBackPreview || placeholder"
              class="w-16 h-16 rounded-lg object-cover border border-slate-200 bg-slate-100"
              alt="ID back preview"
            />
            <input
              type="text"
              name="bio_id_back_path_display"
              x-model="idBackLabel"
              readonly
              class="flex-1 rounded-lg border border-slate-200 bg-slate-50 px-3 py-1.5 text-xs text-slate-500"
              placeholder="Captured ID back (saved on submit)"
            />
          </div>
        </div>

        <!-- HIDDEN FIELDS THAT GO WITH FORM SUBMIT -->
        <input type="hidden" name="bio_face_data"     x-model="faceData">
        <input type="hidden" name="bio_id_front_data" x-model="idFrontData">
        <input type="hidden" name="bio_id_back_data"  x-model="idBackData">
      </div>

      <!-- How to use – biometrics block -->
      <div class="bg-emerald-50/60 border border-emerald-100 rounded-2xl p-3 text-[11px] text-emerald-900 space-y-1">
        <div class="font-semibold flex items-center gap-1">
          <i class="fa-solid fa-lightbulb text-[11px]"></i>
          <span>Biometric tips</span>
        </div>
        <ul class="list-disc pl-4 space-y-1">
          <li>Click <strong>Start camera</strong> and allow browser permission.</li>
          <li>Ask the guest to face the camera, then hit <strong>Capture</strong> under “Face image”.</li>
          <li>Hold ID card close and capture <strong>front</strong> and <strong>back</strong> the same way.</li>
          <li>When you save the walk-in form, images are sent to the server and linked to the guest profile.</li>
          <li>If the camera fails, you can skip this step and upload images later from the guest profile.</li>
        </ul>
      </div>
    </div>
  </form>

  <!-- SEGMENT 3: How to use this page -->
  <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-xs text-slate-700 space-y-1">
    <div class="font-semibold text-slate-900 mb-1">How to use this Walk-in guest page</div>
    <ol class="list-decimal list-inside space-y-1">
      <li>Set check-in / check-out dates and party size in the <strong>Stay details</strong> card.</li>
      <li>Choose <strong>Existing guest</strong> if the guest already has a profile, otherwise keep <strong>New guest</strong> and fill their contact and ID details.</li>
      <li>Use the <strong>Biometric &amp; ID capture</strong> panel to take face and ID images with the camera (or skip if not available).</li>
      <li>Click <strong>Create walk-in reservation</strong>. You’ll be redirected to the reservation screen where you can assign a room, update status to in-house, and manage charges.</li>
    </ol>
    <p class="mt-1">
      Tip: Later we can upgrade this flow to auto-suggest rooms and rates based on ARI, turning this into a full 2035-style one-click check-in.
    </p>
  </div>
</div>

<!-- SEGMENT 4: Tiny JS to toggle existing/new guest blocks -->
<script>
(function () {
  var modeInput     = document.getElementById('walkin-guest-mode-input');
  var btnExisting   = document.getElementById('walkin-mode-existing');
  var btnNew        = document.getElementById('walkin-mode-new');
  var panelExisting = document.getElementById('walkin-panel-existing');
  var panelNew      = document.getElementById('walkin-panel-new');

  if (!modeInput || !btnExisting || !btnNew || !panelExisting || !panelNew) return;

  function setMode(mode) {
    modeInput.value = mode;

    if (mode === 'existing') {
      btnExisting.classList.add('bg-white', 'shadow-sm', 'text-slate-900');
      btnExisting.classList.remove('text-slate-600');

      btnNew.classList.remove('bg-white', 'shadow-sm', 'text-slate-900');
      btnNew.classList.add('text-slate-600');

      panelExisting.classList.remove('hidden');
      panelNew.classList.add('hidden');
    } else {
      btnNew.classList.add('bg-white', 'shadow-sm', 'text-slate-900');
      btnNew.classList.remove('text-slate-600');

      btnExisting.classList.remove('bg-white', 'shadow-sm', 'text-slate-900');
      btnExisting.classList.add('text-slate-600');

      panelExisting.classList.add('hidden');
      panelNew.classList.remove('hidden');
    }
  }

  btnExisting.addEventListener('click', function (e) {
    e.preventDefault();
    setMode('existing');
  });

  btnNew.addEventListener('click', function (e) {
    e.preventDefault();
    setMode('new');
  });

  // default = new guest
  setMode('new');
})();
</script>

<!-- SEGMENT 5: Alpine component for biometric capture -->
<script>
document.addEventListener('alpine:init', () => {
  Alpine.data('walkinBiometricCapture', (moduleBase) => ({
    moduleBase,
    stream: null,
    isStreaming: false,

    // UI state
    placeholder: '/public/assets/brand/biometric-placeholder.png', // change if needed
    facePreview: '',
    idFrontPreview: '',
    idBackPreview: '',

    faceLabel: '',
    idFrontLabel: '',
    idBackLabel: '',

    // base64 data posted with form
    faceData: '',
    idFrontData: '',
    idBackData: '',

    init() {
      const self = this;
      window.addEventListener('beforeunload', () => self.stopCamera());
    },

    async startCamera() {
      try {
        const stream = await navigator.mediaDevices.getUserMedia({
          video: { facingMode: 'user' },
          audio: false
        });
        this.stream = stream;
        this.$refs.video.srcObject = stream;
        this.isStreaming = true;
      } catch (e) {
        console.error(e);
        alert('Camera access denied. Please allow camera permission in your browser.');
      }
    },

    stopCamera() {
      if (this.stream) {
        this.stream.getTracks().forEach(t => t.stop());
        this.stream = null;
      }
      this.isStreaming = false;
    },

    capture(kind) {
      if (!this.isStreaming) return;

      const video  = this.$refs.video;
      const canvas = this.$refs.canvas;
      const w = video.videoWidth || 640;
      const h = video.videoHeight || 480;

      canvas.width  = w;
      canvas.height = h;

      const ctx = canvas.getContext('2d');
      ctx.drawImage(video, 0, 0, w, h);

      const dataUrl = canvas.toDataURL('image/jpeg', 0.9);
      const label   = 'Captured ' + (new Date()).toISOString().replace('T',' ').substring(0,19);

      if (kind === 'face') {
        this.faceData    = dataUrl;
        this.facePreview = dataUrl;
        this.faceLabel   = label;
      } else if (kind === 'id_front') {
        this.idFrontData    = dataUrl;
        this.idFrontPreview = dataUrl;
        this.idFrontLabel   = label;
      } else if (kind === 'id_back') {
        this.idBackData    = dataUrl;
        this.idBackPreview = dataUrl;
        this.idBackLabel   = label;
      }
    }
  }));
});
</script>