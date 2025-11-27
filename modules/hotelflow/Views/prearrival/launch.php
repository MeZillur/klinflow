<?php
/** @var string $module_base @var array|null $last */
$h    = fn($v)=>htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
$base = rtrim((string)($module_base ?? '/apps/hotelflow'), '/');

$lastEmail  = $last['email']  ?? '';
$lastName   = $last['name']   ?? '';
$lastMailto = $last['mailto'] ?? '';
?>
<div class="max-w-xl mx-auto space-y-6">
  <div>
    <h1 class="text-2xl font-extrabold tracking-tight">Pre-arrival invite</h1>
    <p class="text-sm text-slate-500">
      Collect name, mobile and email over phone, then send a secure link so the guest
      can complete details before arrival.
    </p>
  </div>

  <form method="post"
        action="<?= $h($base) ?>/reservations/prearrival/send"
        class="space-y-4 rounded-2xl border border-slate-200 bg-white p-5">
    <div class="grid grid-cols-1 gap-4">
      <div>
        <label class="block text-sm font-medium text-slate-700">Guest name</label>
        <input type="text" name="name"
               class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
               required>
      </div>
      <div>
        <label class="block text-sm font-medium text-slate-700">Mobile</label>
        <input type="text" name="mobile"
               class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
               required>
      </div>
      <div>
        <label class="block text-sm font-medium text-slate-700">Email</label>
        <input type="email" name="email"
               class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
               required>
      </div>
    </div>

    <div class="flex items-center justify-between pt-2">
      <button type="submit"
              class="inline-flex items-center gap-2 rounded-lg px-4 py-2 text-sm font-semibold text-white shadow-sm hover:shadow-md"
              style="background:var(--brand)">
        <i class="fa-solid fa-paper-plane"></i>
        <span>Generate link &amp; prepare email</span>
      </button>

      <?php if ($lastMailto): ?>
        <a href="<?= $h($lastMailto) ?>"
           class="inline-flex items-center gap-1 text-xs text-emerald-700 hover:text-emerald-900">
          <i class="fa-regular fa-envelope-open"></i>
          <span>Open last invite (<?= $h($lastEmail) ?>)</span>
        </a>
      <?php endif; ?>
    </div>
  </form>
</div>