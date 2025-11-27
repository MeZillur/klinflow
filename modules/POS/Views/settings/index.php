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

$orgId     = (int)($org['id'] ?? 0);
$baseUrl   = rtrim((string)$base, '/');

/* Fallbacks */
$liveName    = $brandName !== '' ? $brandName : ($orgName !== '' ? $orgName : 'Your Business Name');
$liveAddr    = $address   !== '' ? $address   : $orgAddr;
$livePhone   = $phone     !== '' ? $phone     : $orgPhone;
$liveEmail   = $email     !== '' ? $email     : $orgEmail;
$liveWebsite = $website;

$logoPreview = $logoPath !== '' ? $logoPath
  : ($orgId > 0 ? $baseUrl . '/Assets/Brand/logo/' . $orgId . '/logo.png' : '/assets/brand/logo.png');
?>
<div class="max-w-5xl mx-auto space-y-6">
  <!-- Page title -->
  <header class="flex items-center justify-between gap-3">
    <div>
      <h1 class="text-2xl md:text-3xl font-bold tracking-tight text-slate-900">
        POS Branding &amp; Identity
      </h1>
      <p class="mt-1 text-sm text-slate-600">
        Control how your business name, contact details, and logo appear on POS invoices and the POS landing page.
      </p>
    </div>
    <span class="inline-flex items-center rounded-full bg-emerald-50 px-3 py-1 text-xs font-semibold text-emerald-700 border border-emerald-100">
      <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 mr-2"></span>
      Organisation&nbsp;ID:&nbsp;<?= $h((string)($org['id'] ?? '—')) ?>
    </span>
  </header>

  <!-- Single form wrapping both columns -->
  <form action="<?= $h($baseUrl . '/settings') ?>" method="post" enctype="multipart/form-data" class="space-y-6">
    <?php if (\class_exists('\\Shared\\Csrf')): ?>
      <?= \Shared\Csrf::field() ?>
    <?php endif; ?>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 items-start">
      <!-- LEFT COLUMN -->
      <div class="space-y-4">
        

        <!-- Logo upload + LIVE PREVIEW (BizFlow-style) -->
        <section class="bg-white border border-slate-200 rounded-2xl shadow-sm p-5 space-y-4">
          <div class="flex items-center justify-between gap-3">
            <div>
              <h2 class="text-sm font-semibold text-slate-900">Logo &amp; live POS preview</h2>
              <p class="text-xs text-slate-500">
                This is how your invoices and POS landing identity will look after saving.
              </p>
            </div>
            <?php if (isset($_GET['saved']) && $_GET['saved'] === '1'): ?>
              <span class="inline-flex items-center rounded-full bg-emerald-50 px-3 py-1 text-[11px] font-semibold text-emerald-700 border border-emerald-100">
                <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 mr-1.5"></span>
                Saved
              </span>
            <?php endif; ?>
          </div>

          <div class="flex flex-col gap-4">
            <!-- Upload control -->
            <div>
              <label class="block text-xs font-semibold text-slate-700 mb-2">
                Logo file (PNG preferred)
              </label>
              <input
                type="file"
                name="logo"
                accept="image/*"
                class="block w-full text-xs text-slate-600 file:mr-3 file:py-1.5 file:px-3 file:rounded-full file:border-0
                       file:text-xs file:font-semibold file:bg-emerald-50 file:text-emerald-700 hover:file:bg-emerald-100"
              >
              <p class="mt-1 text-[11px] text-slate-500">
                Stored at
                <code class="bg-slate-50 px-1 rounded text-[11px]">
                  /modules/POS/Assets/Brand/logo/&lt;org_id&gt;/logo.png
                </code>
                and reused by POS landing and invoice prints.
              </p>
            </div>

            <!-- Preview card -->
            <div class="border border-slate-200 rounded-2xl bg-slate-50 px-4 py-3">
              <div class="flex items-start gap-4">
                <div class="flex-shrink-0 border border-slate-200 rounded-xl bg-white px-3 py-2 min-w-[140px] min-h-[60px] flex items-center justify-center">
                  <img
                    src="<?= $h($logoPreview) ?>"
                    alt="Current POS logo"
                    class="max-h-12 w-auto object-contain"
                    onerror="this.src='/assets/brand/logo.png';"
                  >
                </div>
                <div class="flex-1 space-y-1 text-sm">
                  <div class="font-semibold text-slate-900 text-base">
                    <?= $h($liveName) ?>
                  </div>
                  <?php if ($liveAddr !== ''): ?>
                    <div class="text-slate-700 whitespace-pre-line">
                      <?= nl2br($h($liveAddr)) ?>
                    </div>
                  <?php endif; ?>
                  <div class="flex flex-wrap gap-x-4 gap-y-1 text-xs text-slate-600 mt-1">
                    <?php if ($livePhone !== ''): ?>
                      <span><span class="font-semibold">Phone:</span> <?= $h($livePhone) ?></span>
                    <?php endif; ?>
                    <?php if ($liveEmail !== ''): ?>
                      <span><span class="font-semibold">Email:</span> <?= $h($liveEmail) ?></span>
                    <?php endif; ?>
                    <?php if ($liveWebsite !== ''): ?>
                      <span><span class="font-semibold">Web:</span> <?= $h($liveWebsite) ?></span>
                    <?php endif; ?>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </section>
      </div>

      <!-- RIGHT COLUMN: POS branding form fields -->
      <section class="bg-white border border-slate-200 rounded-2xl shadow-sm p-5 space-y-4">
        <div class="flex items-center justify-between gap-3">
          <div>
            <h2 class="text-sm font-semibold text-slate-900">POS Branding</h2>
            <p class="text-xs text-slate-500">
              Used for POS invoices, slips, and the POS apps landing screen.
            </p>
          </div>
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
            value="<?= $h($brandName !== '' ? $brandName : ($orgName !== '' ? $orgName : '')) ?>"
            class="block w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:outline-none focus:ring-1 focus:ring-emerald-500 focus:border-emerald-500"
            placeholder="e.g. DEPENDCORE"
            required
          >
        </div>

        <!-- Address -->
        <div>
          <label class="block text-xs font-semibold text-slate-700 mb-1" for="address">
            Address (appears on invoices)
          </label>
          <textarea
            id="address"
            name="address"
            rows="3"
            class="block w-full rounded-lg border border-slate-300 px-3 py-2 text-sm resize-y focus:outline-none focus:ring-1 focus:ring-emerald-500 focus:border-emerald-500"
            placeholder="House, road, area, city..."
          ><?= $h($address !== '' ? $address : $orgAddr) ?></textarea>
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
              value="<?= $h($phone !== '' ? $phone : $orgPhone) ?>"
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
              value="<?= $h($email !== '' ? $email : $orgEmail) ?>"
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
      </section>
    </div>

    <!-- Submit bar -->
    <div class="pt-2 flex items-center justify-between gap-3">
      <p class="text-[11px] text-slate-500">
        Changes apply immediately to new invoices and POS landing. Existing historical invoices keep their stored PDFs.
      </p>
      <button
        type="submit"
        class="inline-flex items-center rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-emerald-700 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-1"
      >
        Save branding
      </button>
    </div>
  </form>

  <!-- How to use this page -->
  <section class="bg-slate-50 border border-slate-200 rounded-2xl px-4 py-3 text-xs text-slate-700 space-y-1.5">
    <h2 class="text-[11px] font-semibold tracking-wide uppercase text-slate-500">
      How to use this page
    </h2>
    <ul class="list-disc list-inside space-y-1">
      <li><strong>Fill in “Business / Trading name”</strong> exactly as you want it to appear on POS invoices and reports.</li>
      <li>Update the <strong>address, phone, and email</strong> – these print in the invoice header and on the POS landing.</li>
      <li><strong>Upload your logo</strong> once. It is stored under
        <code class="bg-white px-1 rounded text-[11px]">/modules/POS/Assets/Brand/logo/&lt;org_id&gt;/logo.png</code>
        and reused everywhere in POS.</li>
      <li>After saving, check the <strong>live preview card</strong> on the left and open a sample POS invoice to confirm everything looks correct.</li>
      <li>If you later change the logo or identity, just update here and save – other POS pages will pick up the new branding automatically.</li>
    </ul>
  </section>
</div>