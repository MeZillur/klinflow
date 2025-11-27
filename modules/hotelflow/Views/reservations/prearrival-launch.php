<?php
/** @var string $module_base */

if (\PHP_SESSION_ACTIVE !== \session_status()) {
    @\session_start();
}

$h    = fn($v)=>htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
$base = rtrim((string)($module_base ?? '/apps/hotelflow'), '/');

// preserve ?_debug=1 when present
$debugSuffix = (isset($_GET['_debug']) && $_GET['_debug'] === '1') ? '?_debug=1' : '';
$action      = $base . '/reservations/prearrival/send' . $debugSuffix;

/* --- pull flash + last generated pre-arrival invitation from session --- */
$flashes = $_SESSION['hf_flash'] ?? [];
unset($_SESSION['hf_flash']);

$last = $_SESSION['hf_prearrival_last'] ?? null;
unset($_SESSION['hf_prearrival_last']);
?>

<div class="max-w-[800px] mx-auto space-y-6">

  <!-- SEGMENT 1: Header -->
  <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
    <div>
      <h1 class="text-2xl font-extrabold tracking-tight">Pre-arrival invite</h1>
      <p class="text-slate-500 text-sm">
        Collect name, mobile and email over phone, then send a secure link so the guest can complete details before arrival.
      </p>
    </div>
  </div>

  <!-- SEGMENT 2: Flash messages -->
  <?php if ($flashes): ?>
    <div class="space-y-2">
      <?php foreach ($flashes as $f):
        $type = $f['type'] ?? 'info';
        $msg  = $h($f['msg'] ?? '');
        $cls  = 'bg-slate-50 border-slate-200 text-slate-800';
        if ($type === 'success') $cls = 'bg-emerald-50 border-emerald-200 text-emerald-800';
        if ($type === 'error')   $cls = 'bg-rose-50 border-rose-200 text-rose-800';
      ?>
        <div class="rounded-xl border px-4 py-2 text-sm <?= $cls ?>">
          <?= $msg ?>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

  <!-- SEGMENT 3: Last generated link + mailto button -->
  <?php if ($last && !empty($last['link']) && !empty($last['mailto_href'])): ?>
    <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm space-y-3">

      <div class="flex flex-wrap items-center justify-between gap-2">
        <div>
          <div class="font-semibold text-emerald-900">
            Link ready for <?= $h($last['name'] ?? '') ?>
          </div>
          <div class="text-emerald-800/80">
            <?= $h($last['email'] ?? '') ?> · <?= $h($last['mobile'] ?? '') ?>
          </div>
        </div>

        <a href="<?= $h($last['mailto_href']) ?>"
           class="inline-flex items-center gap-2 px-3 py-1.5 rounded-lg text-xs font-semibold text-white"
           style="background:var(--brand)">
          <i class="fa-solid fa-envelope"></i>
          <span>Open email app</span>
        </a>
      </div>

      <!-- Copy field -->
      <div>
        <label class="block text-[11px] uppercase tracking-wide text-emerald-900/70 mb-1">
          Guest link (copy / paste if needed)
        </label>

        <div class="flex gap-2">
          <input id="prearrival-link"
                 type="text"
                 readonly
                 class="flex-1 px-2 py-1.5 rounded-md border border-emerald-200 bg-white text-xs font-mono"
                 value="<?= $h($last['link']) ?>">

          <button type="button"
                  onclick="hfCopyPrearrivalLink()"
                  class="px-3 py-1.5 rounded-md text-xs font-semibold bg-emerald-600 text-white hover:bg-emerald-700">
            Copy
          </button>
        </div>
      </div>

    </div>
  <?php endif; ?>

  <!-- SEGMENT 4: Form -->
  <div class="rounded-xl border border-slate-200 bg-white p-5">
    <form method="post" action="<?= $h($action) ?>" class="space-y-4">
      <div>
        <label class="block text-sm text-slate-700 mb-1">Guest name</label>
        <input name="name" type="text"
               class="w-full border border-slate-300 rounded-lg px-3 py-2"
               required>
      </div>
      <div>
        <label class="block text-sm text-slate-700 mb-1">Mobile</label>
        <input name="mobile" type="text"
               class="w-full border border-slate-300 rounded-lg px-3 py-2"
               required>
      </div>
      <div>
        <label class="block text-sm text-slate-700 mb-1">Email</label>
        <input name="email" type="email"
               class="w-full border border-slate-300 rounded-lg px-3 py-2"
               required>
      </div>

      <button type="submit"
              class="inline-flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-semibold text-white shadow-sm hover:shadow-md transition"
              style="background:var(--brand)">
        <i class="fa-solid fa-paper-plane"></i>
        <span>Generate link &amp; prepare email</span>
      </button>
    </form>
  </div>
</div>

<script>
  function hfCopyPrearrivalLink() {
    var input = document.getElementById('prearrival-link');
    if (!input) return;
    var value = input.value || '';

    if (!value) return;

    // Modern clipboard API
    if (navigator.clipboard && navigator.clipboard.writeText) {
      navigator.clipboard.writeText(value).then(function () {
        // optional: small native alert; later you can swap with toast
        alert('Link copied to clipboard');
      }).catch(function () {
        hfCopyPrearrivalFallback(input);
      });
    } else {
      hfCopyPrearrivalFallback(input);
    }
  }

  function hfCopyPrearrivalFallback(input) {
    try {
      input.focus();
      input.select();
      document.execCommand('copy');
      alert('Link copied to clipboard');
    } catch (e) {
      alert('Please select and copy the link manually.');
    }
  }
</script>