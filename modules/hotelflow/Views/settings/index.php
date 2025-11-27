<?php
declare(strict_types=1);

/**
 * HotelFlow — Settings / Branding
 *
 * Inputs from controller:
 * - array  $branding    (business_name, address, phone, email, website, logo_path)
 * - string $module_base ("/t/{slug}/apps/hotelflow" or "/apps/hotelflow")
 */

$h = fn($s)=>htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');

$brandColor  = '#228B22';
$module_base = rtrim((string)($module_base ?? '/apps/hotelflow'), '/');
$branding    = $branding ?? [];

/* -----------------------------------------------------------------
 * Normalised branding fields
 * ----------------------------------------------------------------- */
$business = trim((string)($branding['business_name'] ?? 'Your Hotel Name'));
$address  = trim((string)($branding['address']       ?? ''));
$phone    = trim((string)($branding['phone']         ?? ''));
$email    = trim((string)($branding['email']         ?? ''));
$website  = trim((string)($branding['website']       ?? ''));
$logoRaw  = trim((string)($branding['logo_path']     ?? ''));

/* -----------------------------------------------------------------
 * Resolve logo URL (SINGLE SOURCE OF TRUTH)
 *  - if absolute http/https → keep
 *  - if absolute local path → normalise /public prefix
 *  - if relative           → prefix with module_base
 *  - fallback              → /assets/brand/logo.png
 * ----------------------------------------------------------------- */
$docRoot = rtrim((string)($_SERVER['DOCUMENT_ROOT'] ?? ''), '/');
$asset   = fn(string $rel) => '/assets/' . ltrim($rel, '/');

$logoUrl = '';

if ($logoRaw !== '') {
    if (preg_match('#^https?://#i', $logoRaw)) {
        // Full external URL already
        $logoUrl = $logoRaw;
    } elseif ($logoRaw[0] === '/') {
        // Absolute from docroot; strip any /public prefix
        $logoUrl = preg_replace('#^/public/#', '/', $logoRaw);
    } else {
        // Relative; treat as under module_base
        $logoUrl = $module_base . '/' . ltrim($logoRaw, '/');
    }
}

// Hard fallback to main app logo if still empty
if ($logoUrl === '') {
    $logoUrl = $asset('brand/logo.png');
}

// For UX text
$hasCustomLogo = ($logoRaw !== '');

/* -----------------------------------------------------------------
 * Saved flash
 * ----------------------------------------------------------------- */
$saved = isset($_GET['saved']) && $_GET['saved'] === '1';

/* -----------------------------------------------------------------
 * Optional CSRF token (if controller provided)
 * ----------------------------------------------------------------- */
$csrfToken = $csrf ?? ($ctx['csrf'] ?? null);
?>

<div class="max-w-6xl mx-auto px-4 md:px-6 py-4 md:py-6 space-y-4 md:space-y-6">

  <?php if ($saved): ?>
    <div class="rounded-md border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900 flex items-start gap-2">
      <span class="mt-0.5 inline-flex h-5 w-5 items-center justify-center rounded-full bg-emerald-600 text-white text-xs">
        ✓
      </span>
      <div>
        <div class="font-semibold">Branding updated.</div>
        <div>Your hotel name, contacts and logo are now saved for all HotelFlow pages.</div>
      </div>
    </div>
  <?php endif; ?>

  <!-- Heading -->
  <div class="flex items-center justify-between gap-3">
    <div>
      <h1 class="text-xl md:text-2xl font-semibold text-slate-900">Hotel Branding</h1>
      <p class="text-sm text-slate-600">
        Set the property identity that appears on dashboards, folios and guest documents.
      </p>
    </div>
    <span class="inline-flex items-center gap-2 rounded-full border border-slate-200 px-3 py-1 text-xs text-slate-600">
      <span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>
      Saved per organisation
    </span>
  </div>

  <!-- Main grid: preview + form -->
  <div class="grid grid-cols-1 lg:grid-cols-[minmax(0,280px)_minmax(0,1fr)] gap-4 md:gap-6 items-start">

    <!-- Preview card -->
    <section class="bg-white border border-slate-200 rounded-xl shadow-sm p-4 md:p-5 space-y-4">
      <h2 class="text-sm font-semibold text-slate-700 mb-1">Preview</h2>
      <p class="text-xs text-slate-500 mb-3">
        This preview shows how your header block will appear on HotelFlow reports, folios and dashboards.
      </p>

      <div class="border border-slate-200 rounded-xl px-4 py-3 flex gap-3 items-start">
        <div class="flex-shrink-0">
          <div class="h-16 w-16 rounded-lg border border-slate-200 bg-slate-50 overflow-hidden flex items-center justify-center">
            <img
              src="<?= $h($logoUrl) ?>"
              alt="<?= $h($business !== '' ? $business : 'Hotel logo') ?>"
              class="h-full w-full object-contain"
              loading="lazy"
              onerror="this.src='<?= $h($asset('brand/logo.png')) ?>';"
            >
          </div>
        </div>

        <div class="flex-1 min-w-0">
          <div class="font-semibold text-slate-900 leading-tight">
            <?= $h($business !== '' ? $business : 'Your Hotel Name') ?>
          </div>
          <div class="text-xs text-slate-500 mb-2">HotelFlow • Property identity</div>

          <dl class="space-y-1 text-xs text-slate-600">
            <div class="flex gap-2">
              <dt class="w-14 text-slate-500">Address</dt>
              <dd class="flex-1">
                <?= $address !== '' ? nl2br($h($address)) : '<span class="text-slate-400">Not set</span>' ?>
              </dd>
            </div>
            <div class="flex gap-2">
              <dt class="w-14 text-slate-500">Phone</dt>
              <dd class="flex-1">
                <?= $phone !== '' ? $h($phone) : '<span class="text-slate-400">Not set</span>' ?>
              </dd>
            </div>
            <div class="flex gap-2">
              <dt class="w-14 text-slate-500">Email</dt>
              <dd class="flex-1">
                <?= $email !== '' ? $h($email) : '<span class="text-slate-400">Not set</span>' ?>
              </dd>
            </div>
            <div class="flex gap-2">
              <dt class="w-14 text-slate-500">Website</dt>
              <dd class="flex-1">
                <?= $website !== '' ? $h($website) : '<span class="text-slate-400">Not set</span>' ?>
              </dd>
            </div>
          </dl>
        </div>
      </div>
    </section>

    <!-- Form -->
    <section class="bg-white border border-slate-200 rounded-xl shadow-sm p-4 md:p-5">
      <form method="post" action="<?= $h($module_base) ?>/settings" enctype="multipart/form-data" class="space-y-4 md:space-y-5">
        <?php if ($csrfToken): ?>
          <input type="hidden" name="csrf" value="<?= $h($csrfToken) ?>">
        <?php endif; ?>

        <div class="space-y-1">
          <label for="business_name" class="block text-sm font-medium text-slate-800">
            Property name
          </label>
          <input
            type="text"
            id="business_name"
            name="business_name"
            value="<?= $h($business) ?>"
            class="mt-1 block w-full rounded-md border border-slate-300 px-3 py-2 text-sm shadow-sm focus:border-emerald-500 focus:ring-emerald-500"
            placeholder="Your Hotel Name"
          >
        </div>

        <div class="space-y-1">
          <label for="address" class="block text-sm font-medium text-slate-800">
            Address
          </label>
          <textarea
            id="address"
            name="address"
            rows="3"
            class="mt-1 block w-full rounded-md border border-slate-300 px-3 py-2 text-sm shadow-sm focus:border-emerald-500 focus:ring-emerald-500"
            placeholder="Street, city, district, country"
          ><?= $h($address) ?></textarea>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
          <div class="space-y-1">
            <label for="phone" class="block text-sm font-medium text-slate-800">Phone</label>
            <input
              type="text"
              id="phone"
              name="phone"
              value="<?= $h($phone) ?>"
              class="mt-1 block w-full rounded-md border border-slate-300 px-3 py-2 text-sm shadow-sm focus:border-emerald-500 focus:ring-emerald-500"
              placeholder="+880 1X XXX XXXX"
            >
          </div>

          <div class="space-y-1">
            <label for="email" class="block text-sm font-medium text-slate-800">Email</label>
            <input
              type="email"
              id="email"
              name="email"
              value="<?= $h($email) ?>"
              class="mt-1 block w-full rounded-md border border-slate-300 px-3 py-2 text-sm shadow-sm focus:border-emerald-500 focus:ring-emerald-500"
              placeholder="reservations@example.com"
            >
          </div>

          <div class="space-y-1">
            <label for="website" class="block text-sm font-medium text-slate-800">Website</label>
            <input
              type="text"
              id="website"
              name="website"
              value="<?= $h($website) ?>"
              class="mt-1 block w-full rounded-md border border-slate-300 px-3 py-2 text-sm shadow-sm focus:border-emerald-500 focus:ring-emerald-500"
              placeholder="https://yourhotel.com"
            >
          </div>
        </div>

        <div class="space-y-2 border-t border-slate-200 pt-4 mt-2">
          <div class="flex items-center justify-between gap-2">
            <div>
              <label for="logo" class="block text-sm font-medium text-slate-800">
                Logo for HotelFlow
              </label>
              <p class="text-xs text-slate-500">
                PNG recommended. Used on dashboards, folios and guest documents.
              </p>
            </div>
            <?php if ($hasCustomLogo): ?>
              <div class="flex items-center gap-2 text-xs text-slate-500">
                <span class="text-slate-400">Current</span>
                <img
                  src="<?= $h($logoUrl) ?>"
                  alt="Current logo"
                  class="h-8 w-auto rounded border border-slate-200 bg-white object-contain"
                  loading="lazy"
                  onerror="this.src='<?= $h($asset('brand/logo.png')) ?>';"
                >
              </div>
            <?php endif; ?>
          </div>

          <input
            type="file"
            id="logo"
            name="logo"
            accept="image/png,image/jpeg,image/webp,image/svg+xml"
            class="block w-full text-sm text-slate-600 file:mr-3 file:rounded-md file:border-0
                   file:bg-emerald-50 file:px-3 file:py-1.5 file:text-xs file:font-medium
                   file:text-emerald-700 hover:file:bg-emerald-100"
          >

          <p class="text-[11px] text-slate-400">
            This logo is saved for this organisation only and can be updated at any time.
          </p>
        </div>

        <div class="pt-2">
          <button
            type="submit"
            class="inline-flex items-center justify-center rounded-md border border-transparent
                   bg-emerald-600 px-4 py-2 text-sm font-semibold text-white shadow-sm
                   hover:bg-emerald-700 focus:outline-none focus:ring-2 focus:ring-emerald-500
                   focus:ring-offset-1"
          >
            <span class="mr-1.5 text-base leading-none">✔</span>
            Save branding
          </button>
        </div>
      </form>
    </section>
  </div>

  <!-- How to use this page -->
  <section class="mt-4 border-t border-slate-200 pt-4 md:pt-5">
    <h2 class="text-sm font-semibold text-slate-800 mb-2">How to use this page</h2>
    <ol class="list-decimal list-inside text-xs md:text-sm text-slate-600 space-y-1.5">
      <li>Fill in the <strong>Property name</strong> exactly as you want it to appear on folios and reports.</li>
      <li>Add your full <strong>address, phone, email and website</strong> so guests and partners can contact you easily.</li>
      <li>Upload a <strong>high-quality logo</strong>; after saving, check the preview card on the left.</li>
      <li>These details are stored per organisation and are reused across all HotelFlow dashboards and documents.</li>
      <li>If you ever rebrand, just update the fields and logo here — older folios can still reference the new identity.</li>
    </ol>
  </section>
</div>