<?php
/** @var array  $ctx */
/** @var string $module_base ?? */
/** @var array  $staff ?? */

$h    = fn($v)=>htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
$base = rtrim((string)($module_base ?? ($ctx['module_base'] ?? '/apps/hotelflow')), '/');
$staff = $staff ?? [];
?>
<div class="max-w-5xl mx-auto space-y-6">

  <!-- Header + tiny nav -->
  <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
    <div>
      <h1 class="text-2xl font-extrabold tracking-tight">Housekeeping staff</h1>
      <p class="text-slate-500 text-sm">
        Keep a clean, structured list of attendants, floors, and shifts — ready for mobile app sync in future.
      </p>
    </div>

    <div class="flex flex-wrap gap-2 text-sm">
      <a href="<?= $h($base) ?>/housekeeping"
         class="inline-flex items-center gap-2 px-3 py-1.5 rounded-lg border border-slate-300 bg-white hover:bg-slate-50">
        <i class="fa-solid fa-table-columns"></i>
        <span>Back to board</span>
      </a>
    </div>
  </div>

  <!-- Staff cards -->
  <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
    <div class="flex items-center justify-between mb-3">
      <div>
        <h2 class="text-sm font-semibold text-slate-900">Teams &amp; attendants</h2>
        <p class="text-xs text-slate-500">
          Later this will pull live from an hms_hk_staff table. For now, just a clean 2035 layout.
        </p>
      </div>
      <button type="button"
              class="inline-flex items-center gap-2 rounded-xl px-4 py-2 text-xs font-semibold text-white shadow-sm hover:shadow-md"
              style="background:var(--brand,#228B22);">
        <i class="fa-solid fa-user-plus"></i>
        <span>Add attendant</span>
      </button>
    </div>

    <?php if (!$staff): ?>
      <div class="grid gap-3 sm:grid-cols-3 text-xs text-slate-700">
        <div class="rounded-2xl border border-emerald-100 bg-emerald-50/70 p-4">
          <div class="flex items-center justify-between mb-2">
            <span class="font-semibold text-emerald-900">Team A</span>
            <span class="text-[11px] text-emerald-700">Day shift</span>
          </div>
          <ul class="space-y-1">
            <li>Example: Rahim — Floors 1–2</li>
            <li>Example: Ayesha — Floors 3–4</li>
          </ul>
        </div>
        <div class="rounded-2xl border border-sky-100 bg-sky-50 p-4">
          <div class="flex items-center justify-between mb-2">
            <span class="font-semibold text-sky-900">Team B</span>
            <span class="text-[11px] text-sky-700">Support</span>
          </div>
          <ul class="space-y-1">
            <li>Example: Karim — Public areas</li>
            <li>Example: Fatima — Back-of-house</li>
          </ul>
        </div>
        <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
          <div class="flex items-center justify-between mb-2">
            <span class="font-semibold text-slate-900">Night</span>
            <span class="text-[11px] text-slate-600">Night shift</span>
          </div>
          <ul class="space-y-1">
            <li>Example: Night runner</li>
            <li>Example: Linen support</li>
          </ul>
        </div>
      </div>
      <p class="mt-3 text-[11px] text-slate-500">
        These are just placeholders so layout feels real even before we wire the database.
      </p>
    <?php else: ?>
      <!-- Future: loop $staff and group by team -->
    <?php endif; ?>
  </div>

  <!-- How to use -->
  <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-xs text-slate-700 space-y-1">
    <div class="font-semibold text-slate-900 mb-1">How to use this Housekeeping staff page</div>
    <ol class="list-decimal list-inside space-y-1">
      <li>Use this page as the <strong>source of truth</strong> for HK attendants and teams.</li>
      <li>In database, we can maintain an <code>hms_hk_staff</code> table with role, floors, and shift.</li>
      <li>Frontdesk + HK managers can quickly see who is on duty and where.</li>
      <li>Later this list can sync to access control / mobile app for assignment push.</li>
    </ol>
    <p class="mt-1">
      Tip: Ekta simple on/off flag (<code>is_active</code>) dile roster maintain kora easy hobe.
    </p>
  </div>
</div>