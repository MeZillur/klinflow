<?php
declare(strict_types=1);

/* ============================================================
 * HotelFlow Landing (Apps Grid) — index_standalone.php
 * - Called by DashboardController (renderStandaloneFromModuleDir)
 * - White background, org identity block, clock + calendar
 * - App icons + hidden keyboard shortcuts preserved
 * ========================================================== */

$h = fn($s)=>htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');

/* ---------- Brand + base + org ---------- */
$brand     = $brandColor ?? '#228B22';
$tint      = 'rgba(34,139,34,0.10)'; // used in small pills if needed
$logoutUrl = '/tenant/logout';

$orgArr     = $org ?? ($ctx['org'] ?? ($_SESSION['tenant_org'] ?? []));
$orgName    = trim((string)($orgArr['name']    ?? ''));
$orgAddress = trim((string)($orgArr['address'] ?? ''));
$orgPhone   = trim((string)($orgArr['phone']   ?? ''));
$orgEmail   = trim((string)($orgArr['email']   ?? ''));
$orgWebsite = trim((string)($orgArr['website'] ?? ''));

$tenant      = (string)($orgArr['slug'] ?? '');
$module_base = ($tenant !== '') ? '/t/'.rawurlencode($tenant).'/apps/hotelflow' : '/apps/hotelflow';

$orgId   = (int)($orgArr['id'] ?? ($ctx['org_id'] ?? 0));
$orgLogo = $orgId
  ? ($module_base . '/Assets/Brand/logo/' . $orgId . '/logo.png')
  : '/assets/brand/logo.png';

/* ---------- Override from HotelFlow branding session ---------- */
/** @var array<string,mixed>|null $brandingSession */
$brandingSession = $_SESSION['hotelflow_branding'] ?? null;
if (is_array($brandingSession)) {
    $orgName    = trim((string)($brandingSession['business_name'] ?? $orgName));
    $orgAddress = trim((string)($brandingSession['address']       ?? $orgAddress));
    $orgPhone   = trim((string)($brandingSession['phone']         ?? $orgPhone));
    $orgEmail   = trim((string)($brandingSession['email']         ?? $orgEmail));
    $orgWebsite = trim((string)($brandingSession['website']       ?? $orgWebsite));
    if (!empty($brandingSession['logo_path'])) {
        $orgLogo = (string)$brandingSession['logo_path'];
    }
}

/**
 * Apps (hotel focused)
 * 0: Label
 * 1: URL
 * 2: SVG icon id
 * 3: keyboard shortcut (can be 1–2 letters)
 */
$apps = [
  ['Front Desk Board',     "$module_base/frontdesk",      'ico-frontdesk',      'f'],
  ['Reservations',         "$module_base/reservations",   'ico-reservations',   'r'],
  ['Guests',               "$module_base/guests",         'ico-guests',         'g'],
  ['Rooms & Types',        "$module_base/rooms",          'ico-rooms',          'ro'],
  ['Check-in',             "$module_base/checkin",        'ico-checkin',        'c'],

  ['Rates & Availability', "$module_base/rates",          'ico-rates',          'ra'],
  ['Housekeeping',         "$module_base/housekeeping",   'ico-housekeeping',   'h'],
  ['Folio & Billing',      "$module_base/folios",         'ico-folio-billing',  'fb'],
  ['Payments',             "$module_base/payments",       'ico-payments',       'p'],

  ['Night Audit',          "$module_base/night-audit",    'ico-night-audit',    'n'],
  ['Reports',              "$module_base/reports",        'ico-reports',        'rp'],
  ['Restaurant / POS',     "$module_base/restaurant",     'ico-restaurant-pos', 'rs'],
  ['Accounting',           "$module_base/accounting",     'ico-accounting',     'a'],

  ['Settings',             "$module_base/settings",       'ico-settings',       's'],
];
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= $h($title ?? ('HotelFlow — Apps' . ($orgName ? ' — '.$orgName : ''))) ?></title>

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css">
<style>
  :root { --brand: <?= $h($brand) ?>; }
  body  {
    background:#f9fafb; /* white-ish background like BizFlow */
    color:#0f172a;
    font-family: system-ui,-apple-system,"Segoe UI",Roboto,Inter,Arial,sans-serif;
  }

  .badge{
    background:rgba(34,139,34,.06);
    color:var(--brand);
    border:1px solid rgba(34,139,34,.22);
    border-radius:9999px;
    padding:6px 12px;
    font-size:13px;
    display:inline-flex;
    align-items:center;
    gap:8px;
  }
  .hero h1{ font-weight:900; letter-spacing:-.02em; }

  /* Icon grid */
  .appgrid{ gap:16px; }
  .tile   {
    display:flex;
    flex-direction:column;
    align-items:center;
    text-align:center;
  }
  .iconcard{
    width:86px;
    height:86px;
    background:#fff;
    border:1px solid rgba(0,0,0,.06);
    border-radius:20px;
    box-shadow:0 10px 30px rgba(15,23,42,.12);
    display:grid;
    place-items:center;
    transition:transform .12s ease, box-shadow .12s ease, border-color .12s ease;
  }
  .tile:hover .iconcard{
    transform:translateY(-2px);
    box-shadow:0 16px 40px rgba(15,23,42,.18);
    border-color:rgba(0,0,0,.12);
  }
  .tile svg{ width:60%; height:60%; }
  .label {
    margin-top:10px;
    font-weight:800;
    letter-spacing:-.01em;
  }

  @media (max-width: 767px){
    .appgrid{ gap:12px; }
    .iconcard{ width:72px; height:72px; border-radius:16px; }
    .label   { margin-top:8px; font-size:13px; }
  }

  .info-card{
    background:#fff;
    border:1px solid rgba(15,23,42,.06);
    border-radius:20px;
    padding:16px;
    box-shadow:0 10px 30px rgba(15,23,42,.06);
  }
  .clock-text{ font-weight:800; font-size:28px; color:var(--brand); }
  .clock-sub{ color:#475569; }

  /* Mobile header + drawer */
  .m-header {
    position: sticky;
    top:0;
    z-index:40;
    background:#f9fafb;
    backdrop-filter: blur(6px);
    border-bottom: 1px solid rgba(148,163,184,.35);
  }
  .drawer { position:fixed; inset:0; display:none; z-index:1000; }
  .drawer.open { display:block; }
  .drawer .bg {
    position:absolute; inset:0;
    background:rgba(15,23,42,.45);
    z-index:1000;
  }
  .drawer .panel{
    position:absolute;
    top:0; right:0;
    width:86%; max-width:380px; height:100%;
    background:#fff;
    border-left:1px solid rgba(148,163,184,.4);
    box-shadow:-10px 0 30px rgba(15,23,42,.35);
    transform:translateX(100%);
    transition:transform .18s ease-in-out;
    display:flex;
    flex-direction:column;
    z-index:1001;
  }
  .drawer.open .panel{ transform:translateX(0); }
  .drawer .gridwrap{
    padding:14px;
    overflow-y:auto;
    -webkit-overflow-scrolling:touch;
  }

  .btn-ghost{
    background:#e5e7eb;
    color:#0f172a;
    border-radius:9999px;
    padding:8px 12px;
    display:inline-grid;
    place-items:center;
  }
  .btn-logout{
    background:#ef4444;
    color:#fff;
    border-radius:14px;
    padding:13px 14px;
    width:100%;
    font-weight:700;
    display:inline-flex;
    align-items:center;
    gap:8px;
  }

  .ident-label{width:70px; font-size:12px; color:#64748b;}
  .ident-value{font-size:13px; color:#0f172a;}
</style>
</head>
<body>

<!-- SVG sprite -->
<svg xmlns="http://www.w3.org/2000/svg" style="display:none">
  <!-- Row 1 -->
  <symbol id="ico-frontdesk" viewBox="0 0 64 64">
    <rect width="64" height="64" rx="14" fill="#E2E8F0"/>
    <rect x="14" y="34" width="36" height="16" rx="4" fill="#0F172A"/>
    <circle cx="22" cy="24" r="6" fill="#10B981"/>
  </symbol>
  <symbol id="ico-reservations" viewBox="0 0 64 64">
    <rect width="64" height="64" rx="14" fill="#DBEAFE"/>
    <rect x="18" y="16" width="28" height="32" rx="6" fill="#3B82F6"/>
    <path d="M24 26h16M24 34h12" stroke="#EFF6FF" stroke-width="3" stroke-linecap="round"/>
  </symbol>
  <symbol id="ico-guests" viewBox="0 0 64 64">
    <rect width="64" height="64" rx="14" fill="#EDE9FE"/>
    <circle cx="26" cy="28" r="6" fill="#4C1D95"/>
    <circle cx="38" cy="28" r="6" fill="#7C3AED"/>
    <path d="M14 46c2-6 8-10 12-10m12 0c4 0 10 4 12 10"
          stroke="#7C3AED" stroke-width="4" stroke-linecap="round"/>
  </symbol>
  <symbol id="ico-rooms" viewBox="0 0 64 64">
    <rect width="64" height="64" rx="14" fill="#F5F3FF"/>
    <rect x="16" y="32" width="32" height="14" rx="4" fill="#3730A3"/>
    <rect x="22" y="22" width="20" height="12" rx="3" fill="#A78BFA"/>
  </symbol>
  
  <symbol id="ico-checkin" viewBox="0 0 24 24">
    <rect x="5.5" y="3.5" width="9" height="17"
          rx="1.5" ry="1.5"
          fill="none"
          stroke="currentColor"
          stroke-width="1.6" />
    <circle cx="12" cy="12" r="0.9" fill="currentColor" />
    <line x1="4" y1="21" x2="20" y2="21"
          stroke="currentColor"
          stroke-width="1.6"
          stroke-linecap="round" />
    <path d="M14.5 8.5 H18 V6.5 L21 9 18 11.5 V9.5 H14.5 Z"
          fill="currentColor" />
  </symbol>

  <!-- Row 2 -->
  <symbol id="ico-rates" viewBox="0 0 64 64">
    <rect width="64" height="64" rx="14" fill="#FEF3C7"/>
    <path d="M18 40c6-8 14-12 28-14" stroke="#F59E0B" stroke-width="5" stroke-linecap="round"/>
    <circle cx="46" cy="26" r="5" fill="#F59E0B"/>
  </symbol>
  <symbol id="ico-housekeeping" viewBox="0 0 64 64">
    <rect width="64" height="64" rx="14" fill="#ECFCCB"/>
    <path d="M20 42l8-16 16 16" stroke="#65A30D" stroke-width="5" stroke-linecap="round"/>
    <rect x="18" y="42" width="28" height="6" rx="3" fill="#4D7C0F"/>
  </symbol>
  <symbol id="ico-folio-billing" viewBox="0 0 64 64">
    <rect width="64" height="64" rx="14" fill="#E2E8F0"/>
    <rect x="20" y="14" width="24" height="36" rx="4" fill="#0F172A"/>
    <path d="M24 24h16M24 32h16M24 40h12"
          stroke="#94A3B8" stroke-width="3" stroke-linecap="round"/>
  </symbol>
  <symbol id="ico-payments" viewBox="0 0 64 64">
    <rect width="64" height="64" rx="14" fill="#DCFCE7"/>
    <rect x="14" y="24" width="36" height="22" rx="6" fill="#10B981"/>
    <circle cx="26" cy="35" r="4" fill="#064E3B"/>
    <rect x="32" y="33" width="14" height="4" rx="2" fill="#BBF7D0"/>
  </symbol>

  <!-- Row 3 -->
  <symbol id="ico-night-audit" viewBox="0 0 64 64">
    <rect width="64" height="64" rx="14" fill="#E2E8F0"/>
    <circle cx="26" cy="26" r="10" fill="#0F172A"/>
    <path d="M22 26a10 10 0 1010-10" stroke="#94A3B8" stroke-width="3" fill="none"/>
  </symbol>
  <symbol id="ico-reports" viewBox="0 0 64 64">
    <rect width="64" height="64" rx="14" fill="#FEF3C7"/>
    <path d="M20 42V30M32 42V22M44 42V36"
          stroke="#F59E0B" stroke-width="5" stroke-linecap="round"/>
  </symbol>
  <symbol id="ico-restaurant-pos" viewBox="0 0 64 64">
    <rect width="64" height="64" rx="14" fill="#FFE4E6"/>
    <path d="M22 18v28M28 18v28"
          stroke="#E11D48" stroke-width="4" stroke-linecap="round"/>
    <path d="M42 18v10M42 38v8"
          stroke="#9F1239" stroke-width="4" stroke-linecap="round"/>
  </symbol>

  <!-- Row 4: Accounting + Settings -->
  <symbol id="ico-accounting" viewBox="0 0 64 64">
    <rect width="64" height="64" rx="14" fill="#F0FDF4"/>
    <rect x="16" y="18" width="32" height="28" rx="4" fill="#16A34A"/>
    <path d="M22 26h12M22 32h10M22 38h14"
          stroke="#DCFCE7" stroke-width="3" stroke-linecap="round"/>
    <path d="M36 20v24" stroke="#22C55E" stroke-width="3" stroke-linecap="round"/>
  </symbol>
  <symbol id="ico-settings" viewBox="0 0 64 64">
    <rect width="64" height="64" rx="14" fill="#F8FAFC"/>
    <path d="M32 24a8 8 0 100 16 8 8 0 000-16z" fill="#0F172A"/>
    <path d="M32 14v8M32 42v8M14 32h8M42 32h8M21 21l6 6M37 37l6 6M21 43l6-6M37 27l6-6"
          stroke="#94A3B8" stroke-width="3" stroke-linecap="round"/>
  </symbol>
</svg>

<!-- Mobile header -->
<header class="m-header md:hidden px-4 py-3 flex items-center justify-between">
  <img src="<?= $h($orgLogo) ?>"
       alt="<?= $h($orgName ?: 'KlinFlow') ?>"
       class="h-7 w-auto" />
  <button id="btnOpen" class="btn-ghost" aria-label="Menu">
    <svg class="w-6 h-6" viewBox="0 0 24 24">
      <path d="M4 6h16M4 12h16M4 18h16"
            stroke="#0f172a" stroke-width="2" stroke-linecap="round"/>
    </svg>
  </button>
</header>

<!-- Mobile drawer -->
<div id="drawer" class="drawer md:hidden">
  <div id="btnCloseBg" class="bg"></div>
  <aside class="panel">
    <div class="p-4 flex items-center justify-between border-b border-gray-200">
      <div class="font-extrabold text-lg">HotelFlow<?= $orgName? ' — '.$h($orgName): '' ?></div>
      <button id="btnClose" class="btn-ghost" aria-label="Close">
        <svg class="w-5 h-5" viewBox="0 0 24 24">
          <path d="M6 6l12 12M18 6L6 18"
                stroke="#0f172a" stroke-width="2" stroke-linecap="round"/>
        </svg>
      </button>
    </div>

    <div class="gridwrap">
      <div class="grid grid-cols-3 gap-10 justify-items-center">
        <?php foreach ($apps as $a): ?>
          <a href="<?= $h($a[1]) ?>" class="tile"
             aria-label="<?= $h($a[0]) ?>"
             onclick="closeDrawer()">
            <div class="iconcard">
              <svg aria-hidden="true"><use href="#<?= $h($a[2]) ?>"></use></svg>
            </div>
            <div class="label"><?= $h($a[0]) ?></div>
          </a>
        <?php endforeach; ?>
      </div>

      <div class="mt-6 border-t border-gray-200 pt-4">
        <form id="logoutForm" action="<?= $h($logoutUrl) ?>" method="post">
          <button type="submit" class="btn-logout" id="btnLogout">
            <svg class="w-5 h-5" viewBox="0 0 24 24">
              <path d="M15 12H3M11 6l-6 6 6 6M21 3v18"
                    stroke="#fff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
            Logout
          </button>
        </form>
      </div>
    </div>
  </aside>
</div>

<main class="max-w-7xl mx-auto px-4 md:px-6 py-4 md:py-6">
  <div class="grid grid-cols-1 md:grid-cols-2 md:gap-10 items-start">

    <!-- Right: apps grid -->
    <section class="order-1 md:order-2">
      <div class="grid grid-cols-3 md:grid-cols-4 appgrid">
        <?php foreach ($apps as $a): ?>
          <a href="<?= $h($a[1]) ?>" class="tile" aria-label="<?= $h($a[0]) ?>">
            <div class="iconcard">
              <svg aria-hidden="true"><use href="#<?= $h($a[2]) ?>"></use></svg>
            </div>
            <div class="label text-base md:text-lg"><?= $h($a[0]) ?></div>
          </a>
        <?php endforeach; ?>
      </div>
    </section>

    <!-- Left: org branding + clock + calendar -->
    <section class="order-2 md:order-1 hidden md:block hero">
      <div class="badge">
        <svg class="w-4 h-4" viewBox="0 0 24 24">
          <path d="M12 3l8 4v5c0 5-4 9-8 9s-8-4-8-9V7l8-4z" fill="currentColor"/>
        </svg>
        HOTELFLOW • BANGLADESH-READY
      </div>

      <!-- Identity card -->
      <div class="mt-4 info-card">
        <div class="flex items-start gap-4">
          <?php if ($orgLogo): ?>
            <div class="flex-shrink-0">
              <img src="<?= $h($orgLogo) ?>"
                   alt="<?= $h($orgName ?: 'Organisation Logo') ?>"
                   class="h-16 w-auto rounded-lg border border-gray-200 bg-white p-1"
                   onerror="this.style.display='none'">
            </div>
          <?php endif; ?>

          <div class="flex-1">
            <h1 class="text-3xl md:text-4xl font-extrabold leading-tight">
              <?= $h($orgName ?: 'Your Hotel Name') ?>
            </h1>
            <p class="mt-1 text-xs uppercase tracking-wide text-slate-500">
              HotelFlow • Property identity
            </p>

            <dl class="mt-3 space-y-1">
              <div class="flex gap-2">
                <dt class="ident-label">Address</dt>
                <dd class="ident-value whitespace-pre-line">
                  <?= $orgAddress !== '' ? nl2br($h($orgAddress)) : 'Not set' ?>
                </dd>
              </div>
              <div class="flex gap-2">
                <dt class="ident-label">Phone</dt>
                <dd class="ident-value">
                  <?= $orgPhone !== '' ? $h($orgPhone) : 'Not set' ?>
                </dd>
              </div>
              <div class="flex gap-2">
                <dt class="ident-label">Email</dt>
                <dd class="ident-value">
                  <?= $orgEmail !== '' ? $h($orgEmail) : 'Not set' ?>
                </dd>
              </div>
              <div class="flex gap-2">
                <dt class="ident-label">Website</dt>
                <dd class="ident-value">
                  <?= $orgWebsite !== '' ? $h($orgWebsite) : 'Not set' ?>
                </dd>
              </div>
            </dl>

            <p class="mt-3 text-xs text-slate-500">
              This preview is how your header block will appear on HotelFlow
              reports, folios and dashboards.
            </p>
          </div>
        </div>
      </div>

      <!-- Time + calendar -->
      <div class="mt-4 grid grid-cols-1 sm:grid-cols-2 gap-3">
        <div class="info-card">
          <div class="font-semibold text-slate-700">Time</div>
          <div id="clock" class="clock-text mt-2">--:--:--</div>
          <div id="clockSub" class="clock-sub mt-1">—</div>
        </div>
        <div class="info-card">
          <div class="font-bold" id="calTitle"></div>
          <div class="grid grid-cols-7 gap-1 mt-2 text-center text-sm">
            <?php foreach (['S','M','T','W','T','F','S'] as $dow): ?>
              <div class="py-1 font-semibold text-gray-500"><?= $h($dow) ?></div>
            <?php endforeach; ?>
            <?php for ($i=0; $i<42; $i++): ?>
              <div class="py-2 rounded-lg bg-gray-50" data-cal-cell></div>
            <?php endfor; ?>
          </div>
        </div>
      </div>

      <div class="mt-4 text-sm text-gray-700 space-y-1">
        <div>
          Logout shortcut:
          <span style="padding:.18rem .42rem;background:#e5e7eb;border-radius:.375rem;font:12px/1.2 ui-monospace,Menlo,monospace;">L</span>
        </div>
       
      </div>
    </section>
  </div>
</main>



<script>
(function () {
  // =========================================================
  // SEGMENT 1 — Drawer (mobile)
  // =========================================================
  var drawer    = document.getElementById('drawer');
  var btnOpen   = document.getElementById('btnOpen');
  var btnClose  = document.getElementById('btnClose');
  var btnCloseBg= document.getElementById('btnCloseBg');

  function openDrawer() {
    if (!drawer) return;
    drawer.classList.add('open');
    document.body.style.overflow = 'hidden';
  }
  function closeDrawer() {
    if (!drawer) return;
    drawer.classList.remove('open');
    document.body.style.overflow = '';
  }

  // expose for inline onclick on tiles
  window.closeDrawer = closeDrawer;

  if (btnOpen)    btnOpen.addEventListener('click', openDrawer);
  if (btnClose)   btnClose.addEventListener('click', closeDrawer);
  if (btnCloseBg) btnCloseBg.addEventListener('click', closeDrawer);

  // =========================================================
  // SEGMENT 2 — Logout handler + shortcut (L)
  // =========================================================
  var logoutForm = document.getElementById('logoutForm');
  var logoutUrl  = <?= json_encode($logoutUrl, JSON_UNESCAPED_SLASHES) ?>;

  function fallbackLogout() {
    if (logoutForm) {
      try { logoutForm.submit(); return; } catch (e) {}
    }
    window.location.href = logoutUrl;
  }

  function doLogout() {
    if (window.fetch) {
      try {
        fetch(logoutUrl, { method: 'POST', credentials: 'include' })
          .then(function () {
            window.location.replace('/tenant/login');
          })
          .catch(function () {
            fallbackLogout();
          });
      } catch (e) {
        fallbackLogout();
      }
    } else {
      fallbackLogout();
    }
  }

  if (logoutForm) {
    logoutForm.addEventListener('submit', function (e) {
      e.preventDefault();
      doLogout();
    });
  }

  // logout shortcut: L (only when not typing)
  document.addEventListener('keydown', function (e) {
    var tag = (e.target && e.target.tagName ? e.target.tagName : '').toLowerCase();
    if (tag === 'input' || tag === 'textarea') return;
    if (e.target && e.target.isContentEditable) return;
    if (e.altKey || e.ctrlKey || e.metaKey) return;

    var key = (e.key || '').toLowerCase();
    if (key === 'l') {
      e.preventDefault();
      doLogout();
    }
  });

  // =========================================================
  // SEGMENT 3 — Clock + Calendar
  // =========================================================
  function tick() {
    var now = new Date();
    var dd  = ['Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'];
    var mmn = ['January','February','March','April','May','June','July','August','September','October','November','December'];

    var hh  = String(now.getHours()).padStart(2,'0');
    var mm  = String(now.getMinutes()).padStart(2,'0');
    var ss  = String(now.getSeconds()).padStart(2,'0');

    var clock = document.getElementById('clock');
    var sub   = document.getElementById('clockSub');
    if (clock) clock.textContent = hh + ':' + mm + ':' + ss;
    if (sub)   sub.textContent   = dd[now.getDay()] + ' ' + now.getDate() + ' ' + mmn[now.getMonth()] + ' ' + now.getFullYear();
  }

  function drawCalendar(date) {
    var d        = new Date(date.getFullYear(), date.getMonth(), 1);
    var firstDow = d.getDay();
    var lastDay  = new Date(date.getFullYear(), date.getMonth() + 1, 0).getDate();
    var months   = ['January','February','March','April','May','June','July','August','September','October','November','December'];

    var title = document.getElementById('calTitle');
    if (title) title.textContent = months[date.getMonth()] + ' ' + date.getFullYear();

    var cells = document.querySelectorAll('[data-cal-cell]');
    var i, cell;

    for (i = 0; i < cells.length; i++) {
      cells[i].textContent = '';
      cells[i].className   = 'py-2 rounded-lg bg-gray-50';
    }

    for (i = 0; i < lastDay; i++) {
      var idx = firstDow + i;
      cell = cells[idx];
      if (!cell) continue;
      var dayNum = i + 1;
      cell.textContent = dayNum;
      if (dayNum === date.getDate()) {
        cell.className = 'py-2 rounded-lg bg-emerald-100 font-bold';
      }
    }
  }

  tick();
  setInterval(tick, 1000);
  drawCalendar(new Date());

  // =========================================================
  // SEGMENT 4 — Hidden app shortcuts (from $apps[3])
  // =========================================================
  var appShortcuts = {
    <?php
    $first = true;
    foreach ($apps as $a):
      $key = strtolower((string)($a[3] ?? ''));
      if (!$key) continue;
      if (!$first) echo ",\n    ";
      $first = false;
    ?>
"<?= $h($key) ?>": "<?= $h($a[1]) ?>"
    <?php endforeach; ?>

  };

  var buffer   = '';
  var lastTime = 0;
  var TIMEOUT  = 800;

  document.addEventListener('keydown', function (e) {
    var tag = (e.target && e.target.tagName ? e.target.tagName : '').toLowerCase();
    if (tag === 'input' || tag === 'textarea') return;
    if (e.target && e.target.isContentEditable) return;
    if (e.altKey || e.ctrlKey || e.metaKey) return;

    var key = (e.key || '').toLowerCase();
    if (!key || key.length !== 1 || key < 'a' || key > 'z') return;

    var now = Date.now();
    if (now - lastTime > TIMEOUT) buffer = '';
    lastTime = now;

    buffer += key;
    if (buffer.length > 2) buffer = buffer.slice(-2);

    if (appShortcuts[buffer]) {
      e.preventDefault();
      window.location.href = appShortcuts[buffer];
      buffer = '';
      return;
    }

    if (appShortcuts[key]) {
      e.preventDefault();
      window.location.href = appShortcuts[key];
      buffer = '';
      return;
    }
  });
})();
</script>
</body>
</html>