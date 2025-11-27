<?php
declare(strict_types=1);

/** @var array  $branding */
/** @var string $base */
/** @var array  $org */

$h = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');

$brandName = trim((string)($branding['business_name'] ?? ''));
$address   = trim((string)($branding['address']       ?? ''));
$phone     = trim((string)($branding['phone']         ?? ''));
$email     = trim((string)($branding['email']         ?? ''));
$website   = trim((string)($branding['website']       ?? ''));
$logoPath  = trim((string)($branding['logo_path']     ?? ''));

$orgName   = trim((string)($org['name']    ?? ''));
$orgAddr   = trim((string)($org['address'] ?? ''));
$orgPhone  = trim((string)($org['phone']   ?? ''));
$orgEmail  = trim((string)($org['email']   ?? ''));

$baseUrl   = rtrim((string)$base, '/');

if (\PHP_SESSION_ACTIVE !== \session_status()) {
    @\session_start();
}
$flashOk  = $_SESSION['flash_ok']  ?? null;
$flashErr = $_SESSION['flash_err'] ?? null;
unset($_SESSION['flash_ok'], $_SESSION['flash_err']);
?>
<div class="max-w-5xl mx-auto space-y-6">
  <!-- Page title -->
  <header class="flex items-center justify-between gap-3">
    <div>
      <h1 class="text-2xl md:text-3xl font-bold tracking-tight text-slate-900">
        DMS Branding &amp; Identity
      </h1>
      <p class="mt-1 text-sm text-slate-600">
        Control how your business name, contact details, and logo appear on DMS invoices, challans, and dashboards.
      </p>
    </div>
    <span class="inline-flex items-center rounded-full bg-emerald-50 px-3 py-1 text-xs font-semibold text-emerald-700 border border-emerald-100">
      <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 mr-2"></span>
      Organisation&nbsp;ID:&nbsp;<?= $h((string)($org['id'] ?? '—')) ?>
    </span>
  </header>

  <?php if ($flashOk || $flashErr): ?>
    <div class="flex flex-col gap-2">
      <?php if ($flashOk): ?>
        <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2 text-xs text-emerald-800">
          <?= $h($flashOk) ?>
        </div>
      <?php endif; ?>
      <?php if ($flashErr): ?>
        <div class="rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-xs text-red-700">
          <?= $h($flashErr) ?>
        </div>
      <?php endif; ?>
    </div>
  <?php endif; ?>

  <!-- Two-column layout -->
  <div class="grid grid-cols-1 md:grid-cols-2 gap-6 items-start">
    <!-- LEFT: CP Organisation snapshot (read-only) -->
    <section class="bg-white border border-slate-200 rounded-2xl shadow-sm p-5 space-y-4">
      <div class="flex items-center gap-2">
        <div class="inline-flex items-center justify-center w-9 h-9 rounded-xl bg-emerald-100 text-emerald-700">
          <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none">
            <path d="M4 12a8 8 0 1116 0 8 8 0 01-16 0z" fill="#bbf7d0"/>
            <path d="M12 7v5l3 3" stroke="#16a34a" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
          </svg>
        </div>
        <div>
          <h2 class="text-sm font-semibold text-slate-900">Control-Panel Organisation</h2>
          <p class="text-xs text-slate-500">
            Source: <code class="font-mono text-[11px] bg-slate-50 px-1 rounded">cp_organizations</code>
          </p>
        </div>
      </div>

      <dl class="mt-2 space-y-2 text-sm">
        <div class="flex gap-2">
          <dt class="w-20 text-slate-500 font-medium">Name</dt>
          <dd class="flex-1 font-semibold text-slate-900"><?= $h($orgName ?: '—') ?></dd>
        </div>
        <div class="flex gap-2">
          <dt class="w-20 text-slate-500 font-medium">Address</dt>
          <dd class="flex-1 whitespace-pre-line">
            <?= $orgAddr !== '' ? nl2br($h($orgAddr)) : '—' ?>
          </dd>
        </div>
        <div class="flex gap-2">
          <dt class="w-20 text-slate-500 font-medium">Phone</dt>
          <dd class="flex-1"><?= $h($orgPhone ?: '—') ?></dd>
        </div>
        <div class="flex gap-2">
          <dt class="w-20 text-slate-500 font-medium">Email</dt>
          <dd class="flex-1"><?= $h($orgEmail ?: '—') ?></dd>
        </div>
      </dl>

      <p class="mt-3 text-xs text-slate-500 leading-snug">
        These values come from your main organisation profile and are used as sensible defaults when you first
        configure DMS branding. Editing them here does <strong>not</strong> change the control-panel master data.
      </p>
    </section>

    <!-- RIGHT: DMS branding form (POS-style) -->
    <section class="bg-white border border-slate-200 rounded-2xl shadow-sm p-5">
      <form action="<?= $h($baseUrl . '/settings') ?>" method="post" enctype="multipart/form-data" class="space-y-4">
        <?php if (class_exists('\\Shared\\Csrf')): ?>
          <?= \Shared\Csrf::field() ?>
        <?php endif; ?>

        <div class="flex items-center justify-between gap-3">
          <div>
            <h2 class="text-sm font-semibold text-slate-900">DMS Branding</h2>
            <p class="text-xs text-slate-500">
              Used for DMS invoices, challans, and the DMS landing screen.
            </p>
          </div>
          <?php if (isset($_GET['saved']) && $_GET['saved'] === '1'): ?>
            <span class="inline-flex items-center rounded-full bg-emerald-50 px-3 py-1 text-[11px] font-semibold text-emerald-700 border border-emerald-100">
              <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 mr-1.5"></span>
              Saved
            </span>
          <?php endif; ?>
        </div>

        <!-- Business name -->
        <div>
          <label class="block text-xs font-semibold text-slate-700 mb-1" for="business_name">
            Business / Trading name
          </label>
          <input
            type="text"
            id="business_name"
            name="business_name"
            value="<?= $h($brandName ?: ($orgName ?: '')) ?>"
            class="block w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:outline-none focus:ring-1 focus:ring-emerald-500 focus:border-emerald-500"
            placeholder="e.g. DEPENDCORE"
            required
          >
        </div>

        <!-- Address -->
        <div>
          <label class="block text-xs font-semibold text-slate-700 mb-1" for="address">
            Address (appears on documents)
          </label>
          <textarea
            id="address"
            name="address"
            rows="3"
            class="block w-full rounded-lg border border-slate-300 px-3 py-2 text-sm resize-y focus:outline-none focus:ring-1 focus:ring-emerald-500 focus:border-emerald-500"
            placeholder="House, road, area, city..."
          ><?= $h($address ?: $orgAddr) ?></textarea>
        </div>

        <!-- Contact row -->
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
          <div>
            <label class="block text-xs font-semibold text-slate-700 mb-1" for="phone">
              Phone
            </label>
            <input
              type="text"
              id="phone"
              name="phone"
              value="<?= $h($phone ?: $orgPhone) ?>"
              class="block w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:outline-none focus:ring-1 focus:ring-emerald-500 focus:border-emerald-500"
              placeholder="+8801..."
            >
          </div>
          <div>
            <label class="block text-xs font-semibold text-slate-700 mb-1" for="email">
              Email
            </label>
            <input
              type="email"
              id="email"
              name="email"
              value="<?= $h($email ?: $orgEmail) ?>"
              class="block w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:outline-none focus:ring-1 focus:ring-emerald-500 focus:border-emerald-500"
              placeholder="billing@example.com"
            >
          </div>
        </div>

        <!-- Website -->
        <div>
          <label class="block text-xs font-semibold text-slate-700 mb-1" for="website">
            Website (optional)
          </label>
          <input
            type="text"
            id="website"
            name="website"
            value="<?= $h($website) ?>"
            class="block w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:outline-none focus:ring-1 focus:ring-emerald-500 focus:border-emerald-500"
            placeholder="https://www.example.com"
          >
        </div>

        <!-- Logo upload + preview -->
        <div class="border-t border-slate-200 pt-4 mt-2">
          <label class="block text-xs font-semibold text-slate-700 mb-2">
            Logo (PNG/JPG/WEBP/SVG)
          </label>

          <div class="flex flex-col sm:flex-row gap-4 items-start sm:items-center">
            <div class="flex-1">
              <input
                type="file"
                name="logo"
                accept="image/*"
                class="block w-full text-xs text-slate-600 file:mr-3 file:py-1.5 file:px-3 file:rounded-full file:border-0
                       file:text-xs file:font-semibold file:bg-emerald-50 file:text-emerald-700 hover:file:bg-emerald-100"
              >
              <p class="mt-1 text-[11px] text-slate-500">
                Stored under
                <code class="bg-slate-50 px-1 rounded text-[11px]">
                  modules/DMS/storage/uploads/logo/org_<?= $h((string)($org['id'] ?? 'ID')) ?>/logo.*
                </code>
                and reused by the DMS landing page, invoices and challans.
              </p>
            </div>

            <div class="flex flex-col items-center gap-2">
              <div class="text-[11px] text-slate-500 uppercase tracking-wide">Current preview</div>
              <div class="border border-slate-200 rounded-xl bg-white px-3 py-2 min-w-[140px] min-h-[60px] flex items-center justify-center">
                <img
                  src="<?= $h($logoPath ?: '/public/assets/brand/logo.png') ?>"
                  alt="Current DMS logo"
                  class="max-h-12 w-auto object-contain"
                  onerror="this.src='/public/assets/brand/logo.png';"
                >
              </div>
            </div>
          </div>
        </div>

        <!-- Submit -->
        <div class="pt-2 flex items-center justify-between gap-3">
          <p class="text-[11px] text-slate-500">
            Changes apply immediately to new DMS invoices and challans. Existing stored PDFs keep their original logo.
          </p>
          <button
            type="submit"
            class="inline-flex items-center rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-emerald-700 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-1"
          >
            Save branding
          </button>
        </div>
      </form>
    </section>
  </div>

  <!-- How to use this page -->
  <section class="bg-slate-50 border border-slate-200 rounded-2xl px-4 py-3 text-xs text-slate-700 space-y-1.5">
    <h2 class="text-[11px] font-semibold tracking-wide uppercase text-slate-500">
      How to use this page
    </h2>
    <ul class="list-disc list-inside space-y-1">
      <li><strong>Confirm the organisation block</strong> on the left matches your CP organisation.</li>
      <li>Fill in <strong>Business / Trading name</strong>, address, phone, email and website exactly as you want them on DMS documents.</li>
      <li><strong>Upload your logo</strong> once; DMS stores it under
        <code class="bg-white px-1 rounded text-[11px]">modules/DMS/storage/uploads/logo/org_&lt;org_id&gt;/logo.*</code>
        and reuses it everywhere.</li>
      <li>After saving, open a DMS invoice and challan to confirm the <strong>name, contact &amp; logo</strong> look correct.</li>
    </ul>
  </section>
</div>