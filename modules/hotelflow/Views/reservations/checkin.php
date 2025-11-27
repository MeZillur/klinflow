<?php
/** @var array  $res */
/** @var string $module_base */

$h    = fn($v)=>htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
$base = rtrim((string)($module_base ?? '/apps/hotelflow'), '/');

$id      = (int)($res['id'] ?? 0);
$code    = (string)($res['code'] ?? ('RES-'.$id));
$guest   = (string)($res['guest_name'] ?? '—');
$mobile  = (string)($res['guest_mobile'] ?? '');
$email   = (string)($res['guest_email'] ?? '');
$ci      = (string)($res['check_in'] ?? '');
$co      = (string)($res['check_out'] ?? '');
$adults  = (int)($res['adults'] ?? 1);
$children= (int)($res['children'] ?? 0);
$status  = (string)($res['status'] ?? '');
?>

<div class="max-w-6xl mx-auto space-y-6">

  <!-- Header + tiny nav -->
  <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
    <div>
      <h1 class="text-2xl font-extrabold tracking-tight">Check-in — <?= $h($code) ?></h1>
      <p class="text-slate-500 text-sm">
        Verify guest, capture biometrics and move this reservation to <strong>In-house</strong>.
      </p>
    </div>

    <div class="flex flex-wrap gap-2 text-sm">
      <a href="<?= $h($base) ?>/reservations"
         class="inline-flex items-center gap-2 px-3 py-1.5 rounded-lg border border-slate-300 bg-white hover:bg-slate-50">
        <i class="fa-solid fa-list-ul"></i>
        <span>All reservations</span>
      </a>
      <a href="<?= $h($base) ?>/reservations/<?= $id ?>"
         class="inline-flex items-center gap-2 px-3 py-1.5 rounded-lg border border-slate-200 bg-slate-50 hover:bg-slate-100">
        <i class="fa-regular fa-file-lines"></i>
        <span>Reservation detail</span>
      </a>
    </div>
  </div>

  <!-- Main two-column layout -->
  <form method="post"
        action="<?= $h($base) ?>/reservations/<?= $id ?>/checkin"
        class="grid gap-6 lg:grid-cols-[1.6fr,1.4fr]">

    <!-- LEFT: Reservation + stay summary -->
    <div class="space-y-4">

      <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
        <div class="flex items-center justify-between mb-3">
          <div>
            <h2 class="text-sm font-semibold text-slate-900 uppercase tracking-wide">Reservation</h2>
            <p class="text-xs text-slate-500 mt-0.5">
              Quick snapshot before you check-in the guest.
            </p>
          </div>
          <span class="inline-flex items-center rounded-full border border-slate-200 bg-slate-50 px-2 py-0.5 text-[11px] font-medium text-slate-700">
            <i class="fa-solid fa-tag mr-1"></i>
            <?= $h(ucwords(str_replace('_',' ',$status))) ?>
          </span>
        </div>

        <dl class="grid gap-3 sm:grid-cols-2 text-sm">
          <div>
            <dt class="text-[11px] font-medium text-slate-500 uppercase tracking-wide">Primary guest</dt>
            <dd class="mt-0.5 text-slate-900"><?= $h($guest) ?></dd>
            <?php if ($mobile || $email): ?>
              <dd class="mt-1 text-[11px] text-slate-500 space-y-0.5">
                <?php if ($mobile): ?><div><i class="fa-solid fa-phone text-[10px] mr-1"></i><?= $h($mobile) ?></div><?php endif; ?>
                <?php if ($email): ?><div><i class="fa-regular fa-envelope text-[10px] mr-1"></i><?= $h($email) ?></div><?php endif; ?>
              </dd>
            <?php endif; ?>
          </div>

          <div>
            <dt class="text-[11px] font-medium text-slate-500 uppercase tracking-wide">Stay</dt>
            <dd class="mt-0.5 text-slate-900">
              <?= $h($ci) ?> → <?= $h($co) ?>
            </dd>
            <dd class="mt-1 text-[11px] text-slate-500">
              <?= $adults ?> adult<?= $adults === 1 ? '' : 's' ?>,
              <?= $children ?> child<?= $children === 1 ? '' : 'ren' ?>
            </dd>
          </div>
        </dl>

        <div class="mt-3 text-[11px] text-slate-500">
          Tip: Room assignment and folio/billing can be managed from the reservation screen after check-in.
        </div>
      </div>

      <!-- Optional quick notes at check-in -->
      <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
        <h2 class="text-sm font-semibold text-slate-900 uppercase tracking-wide mb-2">Check-in notes (optional)</h2>
        <textarea name="checkin_notes"
                  rows="3"
                  class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                  placeholder="Room preference, deposit taken, doc verified, vehicle info…"></textarea>
        <p class="mt-1 text-[11px] text-slate-500">
          This will be appended to the reservation notes or kept as a check-in comment (implementation later).
        </p>
      </div>

      <!-- Summary + primary action -->
      <div class="rounded-2xl border border-emerald-200 bg-emerald-50/70 p-5 shadow-sm">
        <h2 class="text-sm font-semibold text-emerald-900 uppercase tracking-wide">Check-in summary</h2>
        <p class="text-xs text-emerald-800 mt-0.5">
          When you confirm, this reservation will move to <strong>In-house</strong> and biometrics will be linked to the guest profile.
        </p>

        <ul class="mt-3 space-y-1 text-xs text-emerald-900/90">
          <li>Biometric images (face + ID) are stored against the primary guest.</li>
          <li>Reservation <strong><?= $h($code) ?></strong> status becomes <strong>In-house</strong> with a check-in timestamp.</li>
          <li>Later we can extend this to per-guest companions & multi-room check-ins.</li>
        </ul>

        <div class="mt-4 flex flex-wrap items-center justify-between gap-3">
          <button type="submit"
                  class="inline-flex items-center gap-2 rounded-xl px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:shadow-md transition"
                  style="background:var(--brand)">
            <i class="fa-solid fa-door-open"></i>
            <span>Confirm check-in</span>
          </button>

          <span class="text-[11px] text-emerald-900/70">
            After confirming, continue in the reservation screen for room assignment &amp; charges.
          </span>
        </div>
      </div>
    </div>

    <!-- RIGHT: Biometric & ID capture (camera) -->
    <div
      class="space-y-4"
      x-data="hfBiometricCapture()"
      x-init="init()"
    >
      <div class="bg-white border border-slate-200 rounded-2xl p-4 shadow-sm">
        <div class="flex items-center justify-between mb-3">
          <div>
            <h3 class="text-sm font-semibold text-slate-900">Biometric &amp; ID capture</h3>
            <p class="text-xs text-slate-500">
              Capture face and ID to verify the guest at check-in.
            </p>
          </div>
          <div class="inline-flex rounded-full bg-emerald-50 px-2 py-0.5 text-[11px] font-medium text-emerald-700">
            Live camera · Device only
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
            Video stays in the browser. Only captured still images are sent on submit.
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

        <!-- FACE -->
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

        <!-- Hidden base64 fields -->
        <input type="hidden" name="bio_face_data"     x-model="faceData">
        <input type="hidden" name="bio_id_front_data" x-model="idFrontData">
        <input type="hidden" name="bio_id_back_data"  x-model="idBackData">
      </div>

      <!-- How to use this page – biometrics block -->
      <div class="bg-emerald-50/60 border border-emerald-100 rounded-2xl p-3 text-[11px] text-emerald-900 space-y-1">
        <div class="font-semibold flex items-center gap-1">
          <i class="fa-solid fa-lightbulb text-[11px]"></i>
          <span>Biometric tips</span>
        </div>
        <ul class="list-disc pl-4 space-y-1">
          <li>Click <strong>Start camera</strong> and allow the browser permission.</li>
          <li>Ask the guest to face the camera and hit <strong>Capture</strong> under Face.</li>
          <li>Hold the ID steady and capture both <strong>front</strong> and <strong>back</strong>.</li>
          <li>Images are stored against the guest profile and can be used later for verification.</li>
          <li>If the camera does not work, you can skip this step and upload images later.</li>
        </ul>
      </div>
    </div>
  </form>

  <!-- Global tips for this page -->
  <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-xs text-slate-700 space-y-1">
    <div class="font-semibold text-slate-900 mb-1">How to use this Check-in page</div>
    <ol class="list-decimal list-inside space-y-1">
      <li>Open this screen from <strong>Reservations → Open → Check-in</strong> for the selected booking.</li>
      <li>Confirm the guest name, stay dates and party size on the left side.</li>
      <li>Use the camera panel on the right to capture face and ID images for the arriving guest(s).</li>
      <li>Add any check-in notes like deposit, vehicle number, or special instructions.</li>
      <li>Click <strong>Confirm check-in</strong>. The reservation moves to <strong>In-house</strong> and biometrics are stored.</li>
    </ol>
    <p class="mt-1">
      Next upgrade: per-guest companions &amp; auto room assignment from ARI so you can check-in a full family in one step.
    </p>
  </div>
</div>

<script>
function hfBiometricCapture() {
  return {
    isStreaming: false,
    stream: null,
    facePreview: null,
    idFrontPreview: null,
    idBackPreview: null,
    faceData: '',
    idFrontData: '',
    idBackData: '',
    faceLabel: '',
    idFrontLabel: '',
    idBackLabel: '',
    placeholder: 'data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iNjQiIGhlaWdodD0iNjQiIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyI+PHJlY3Qgd2lkdGg9IjY0IiBoZWlnaHQ9IjY0IiByeD0iMTIiIGZpbGw9IiNmMSYjM2Y2ZjkiIHN0cm9rZT0iI2RkZCIvPjxjaXJjbGUgY3g9IjMyIiBjeT0iMjUiIHI9IjEwIiBmaWxsPSIjZGRmMiIgc3Ryb2tlPSIjY2NjIi8+PHBhdGggZD0iTTIwIDQ0YzQtNSAxMC03IDEyLTdzOC0xIDEyIDciIGZpbGw9IiNkZGYyIiBzdHJva2U9IiNjY2MiLz48L3N2Zz4=',
    init() {
      // nothing yet
    },
    async startCamera() {
      try {
        this.stream = await navigator.mediaDevices.getUserMedia({ video: true });
        this.$refs.video.srcObject = this.stream;
        this.isStreaming = true;
      } catch (e) {
        alert('Unable to access camera: ' + e.message);
      }
    },
    stopCamera() {
      if (this.stream) {
        this.stream.getTracks().forEach(t => t.stop());
      }
      this.stream = null;
      this.isStreaming = false;
    },
    capture(kind) {
      if (!this.isStreaming || !this.$refs.video) return;

      const video  = this.$refs.video;
      const canvas = this.$refs.canvas;
      const w = video.videoWidth || 640;
      const h = video.videoHeight || 480;

      canvas.width  = w;
      canvas.height = h;
      const ctx = canvas.getContext('2d');
      ctx.drawImage(video, 0, 0, w, h);

      const dataUrl = canvas.toDataURL('image/jpeg', 0.9);

      if (kind === 'face') {
        this.facePreview = dataUrl;
        this.faceData    = dataUrl;
        this.faceLabel   = 'Face image captured';
      } else if (kind === 'id_front') {
        this.idFrontPreview = dataUrl;
        this.idFrontData    = dataUrl;
        this.idFrontLabel   = 'ID front captured';
      } else if (kind === 'id_back') {
        this.idBackPreview = dataUrl;
        this.idBackData    = dataUrl;
        this.idBackLabel   = 'ID back captured';
      }
    }
  };
}
</script>