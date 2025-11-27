<?php
declare(strict_types=1);

/**
 * DMS Landing — content-only view (shell renders <html> & <head>)
 *
 * Available:
 * - array  $org
 * - string $brandColor
 * - string $module_base or $base
 * - ?string $orgLogo  (from LandingController; may be data: URL or HTTP URL)
 */

$h = fn($s)=>htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');

$brand   = $brandColor ?? '#228B22';
$orgArr  = $org ?? ($_SESSION['tenant_org'] ?? []);

$orgName    = trim((string)($orgArr['name']    ?? ''));
$orgAddress = trim((string)($orgArr['address'] ?? ''));
$orgPhone   = trim((string)($orgArr['phone']   ?? ''));
$orgEmail   = trim((string)($orgArr['email']   ?? ''));

$baseFromCtx = $module_base ?? ($base ?? '/apps/dms');
$base        = rtrim((string)$baseFromCtx, '/');
$logoutUrl   = '/tenant/logout';

$logoUrl = trim((string)($orgLogo ?? ''));
if ($logoUrl === '') {
    $logoUrl = '/public/assets/brand/logo.png';
}

/* ---------- apps (names EXACTLY as given) ---------- */
$apps = [
  ['Dashboard',        "$base/dashboard",            'ico-dashboard'],
  ['Orders',           "$base/orders",               'ico-orders'],
  ['Sales / Invoices', "$base/sales",                'ico-sales-invoices'],
  ['Payments',         "$base/payments",             'ico-payments'],
  ['Returns',          "$base/returns",              'ico-returns'],
  ['Products',         "$base/products",             'ico-products'],
  ['Inventory',        "$base/inventory",            'ico-inventory'],
  ['Adjustments',      "$base/inventory/adjust",     'ico-adjustments'],
  ['Free — Receive',   "$base/free/receive",         'ico-free-receive'],
  ['Free — Issue',     "$base/free/issue",           'ico-free-issue'],
  ['Accounting',       "$base/accounts",             'ico-accounting'],
  ['Customers',        "$base/customers",            'ico-customers'],
  ['Suppliers',        "$base/suppliers",            'ico-suppliers'],
  ['Reports',          "$base/reports",              'ico-reports'],
];

?>
<style>
  :root { --brand: <?= $h($brand) ?>; }

  /* White, clean canvas like BizFlow */
  body  { background:#ffffff; color:#0f172a; font-family: system-ui,-apple-system,"Segoe UI",Roboto,Inter,Arial,sans-serif; }

  .badge{
    background:#f3f4f6;
    color:var(--brand);
    border:1px solid #e5e7eb;
    border-radius:9999px;
    padding:6px 12px;
    font-size:13px;
    display:inline-flex;
    align-items:center;
    gap:8px;
  }
  .hero h1{ font-weight:900; letter-spacing:-.02em; }

  /* --- Icon grid --- */
  .appgrid{ gap:16px; }
  .tile   { display:flex; flex-direction:column; align-items:center; text-align:center; }
  .iconcard{
    width:86px; height:86px;
    background:#fff;
    border:1px solid rgba(0,0,0,.06);
    border-radius:14px;
    box-shadow:0 6px 16px rgba(0,0,0,.08);
    display:grid; place-items:center;
    transition:transform .12s ease, box-shadow .12s ease, border-color .12s ease;
  }
  .tile:hover .iconcard{
    transform:translateY(-2px);
    box-shadow:0 12px 26px rgba(0,0,0,.14);
    border-color:rgba(0,0,0,.12);
  }
  .tile svg{ width:60%; height:60%; }
  .label { margin-top:10px; font-weight:800; letter-spacing:-.01em; }

  @media (max-width: 767px){
    .appgrid{ gap:12px; }
    .iconcard{ width:72px; height:72px; border-radius:12px; }
    .label   { margin-top:8px; font-size:13px; }
  }

  /* Info cards (left side, pure white) */
  .info-card{
    background:#ffffff;
    border:1px solid #e5e7eb;
    border-radius:16px;
    padding:16px;
    box-shadow:0 6px 16px rgba(0,0,0,.04);
  }
  .info-card--time,
  .info-card--date{
    border-color:#e5e7eb;
    background:#ffffff;
  }
  .clock-text{ font-weight:800; font-size:28px; color:var(--brand); }
  .clock-sub{ color:#4b5563; font-weight:500; }

  /* Mobile header + drawer (white, no green tint) */
  .m-header {
    position: sticky;
    top: 0;
    z-index: 40;
    background:#ffffff;
    border-bottom: 1px solid #e5e7eb;
  }
  .drawer {
    position: fixed; inset: 0; display: none; z-index: 1000;
  }
  .drawer.open { display: block; }
  .drawer .bg {
    position: absolute; inset: 0; background: rgba(15,23,42,.45); z-index: 1000;
  }
  .drawer .panel {
    position: absolute; top: 0; right: 0; width:86%; max-width:380px; height:100%;
    background:#ffffff;
    border-left:1px solid #e5e7eb;
    box-shadow:-10px 0 30px rgba(0,0,0,.25);
    transform: translateX(100%);
    transition: transform .18s ease-in-out;
    display:flex; flex-direction:column; z-index:1001;
  }
  .drawer.open .panel { transform: translateX(0); }
  .drawer .gridwrap { padding:14px; overflow-y:auto; -webkit-overflow-scrolling:touch; }

  .btn { display:inline-flex; align-items:center; gap:8px; font-weight:700; }
  .btn-ghost {
    background:#f1f5f9;
    color:#0f172a;
    border-radius:12px;
    padding:10px 12px;
  }
  .btn-logout {
    background:#ef4444;
    color:#ffffff;
    border-radius:14px;
    padding:13px 14px;
    width:100%;
  }
</style>

<!-- On-page SVG sprite (ALL app icons, unchanged) -->
<svg xmlns="http://www.w3.org/2000/svg" style="display:none">
  <symbol id="ico-dashboard" viewBox="0 0 64 64"><rect width="64" height="64" rx="14" fill="#E2E8F0"/><circle cx="24" cy="40" r="8" fill="#0F172A"/><path d="M28 36l10-10" stroke="#0F172A" stroke-width="4" stroke-linecap="round"/></symbol>
  <symbol id="ico-orders" viewBox="0 0 64 64"><rect width="64" height="64" rx="14" fill="#FEF3C7"/><rect x="18" y="18" width="28" height="28" rx="4" fill="#F59E0B"/><path d="M24 26h16M24 32h12" stroke="#FFFBEB" stroke-width="3" stroke-linecap="round"/></symbol>
  <symbol id="ico-sales-invoices" viewBox="0 0 64 64"><rect width="64" height="64" rx="14" fill="#EDE9FE"/><rect x="20" y="14" width="24" height="36" rx="4" fill="#7C3AED"/><circle cx="24" cy="22" r="3" fill="#EDE9FE"/><path d="M24 28h16M24 34h16M24 40h12" stroke="#EDE9FE" stroke-width="3" stroke-linecap="round"/></symbol>
  <symbol id="ico-payments" viewBox="0 0 64 64"><rect width="64" height="64" rx="14" fill="#DCFCE7"/><rect x="14" y="24" width="36" height="22" rx="6" fill="#10B981"/><circle cx="26" cy="35" r="4" fill="#064E3B"/><rect x="32" y="33" width="14" height="4" rx="2" fill="#BBF7D0"/></symbol>
  <symbol id="ico-returns" viewBox="0 0 64 64"><rect width="64" height="64" rx="14" fill="#ECFEFF"/><path d="M24 28h16a8 8 0 010 16H24" stroke="#06B6D4" stroke-width="5" stroke-linecap="round"/><path d="M24 28l-6 6 6 6" fill="none" stroke="#06B6D4" stroke-width="5" stroke-linecap="round" stroke-linejoin="round"/></symbol>
  <symbol id="ico-products" viewBox="0 0 64 64"><rect width="64" height="64" rx="14" fill="#F5F3FF"/><path d="M20 42h24l-2-14H22l-2 14z" fill="#3730A3"/><path d="M22 28l10-8 10 8" stroke="#A78BFA" stroke-width="4" stroke-linecap="round" stroke-linejoin="round"/></symbol>
  <symbol id="ico-inventory" viewBox="0 0 64 64"><rect width="64" height="64" rx="14" fill="#E2E8F0"/><rect x="18" y="26" width="28" height="18" rx="2" fill="#0F172A"/><path d="M18 22h28" stroke="#94A3B8" stroke-width="4" stroke-linecap="round"/></symbol>
  <symbol id="ico-adjustments" viewBox="0 0 64 64"><rect width="64" height="64" rx="14" fill="#F1F5F9"/><path d="M20 24h24M20 32h24M20 40h24" stroke="#0F172A" stroke-width="4" stroke-linecap="round"/><circle cx="36" cy="24" r="4" fill="#10B981"/><circle cx="28" cy="32" r="4" fill="#F59E0B"/><circle cx="40" cy="40" r="4" fill="#3B82F6"/></symbol>
  <symbol id="ico-free-receive" viewBox="0 0 64 64"><rect width="64" height="64" rx="14" fill="#ECFCCB"/><path d="M20 34l12-12 12 12" stroke="#65A30D" stroke-width="4" stroke-linecap="round" stroke-linejoin="round"/><rect x="20" y="34" width="24" height="12" rx="2" fill="#65A30D"/></symbol>
  <symbol id="ico-free-issue" viewBox="0 0 64 64"><rect width="64" height="64" rx="14" fill="#FFEDD5"/><path d="M20 30l12 12 12-12" stroke="#EA580C" stroke-width="4" stroke-linecap="round" stroke-linejoin="round"/><rect x="20" y="18" width="24" height="12" rx="2" fill="#EA580C"/></symbol>
  <symbol id="ico-accounting" viewBox="0 0 64 64"><rect width="64" height="64" rx="14" fill="#F8FAFC"/><path d="M18 44h28M22 20h8v24h-8zM34 26h8v18h-8z" fill="#0F172A"/></symbol>
  <symbol id="ico-customers" viewBox="0 0 64 64"><rect width="64" height="64" rx="14" fill="#EDE9FE"/><circle cx="26" cy="28" r="6" fill="#4C1D95"/><circle cx="38" cy="28" r="6" fill="#7C3AED"/><path d="M14 46c2-6 8-10 12-10m12 0c4 0 10 4 12 10" stroke="#7C3AED" stroke-width="4" stroke-linecap="round"/></symbol>
  <symbol id="ico-suppliers" viewBox="0 0 64 64"><rect width="64" height="64" rx="14" fill="#EFF6FF"/><path d="M18 38h28l4 6H14l4-6z" fill="#0F172A"/><rect x="20" y="22" width="24" height="14" rx="3" fill="#2563EB"/></symbol>
  <symbol id="ico-reports" viewBox="0 0 64 64"><rect width="64" height="64" rx="14" fill="#FEF3C7"/><path d="M20 42V30M32 42V22M44 42V36" stroke="#F59E0B" stroke-width="5" stroke-linecap="round"/></symbol>
</svg>

<!-- Mobile header: brand logo + menu (white) -->
<header class="m-header md:hidden px-4 py-3 flex items-center justify-between">
  <img src="/public/assets/brand/logo.png" alt="KlinFlow" class="h-7 w-auto" />
  <button id="btnOpen" class="btn-ghost" aria-label="Menu">
    <svg class="w-6 h-6" viewBox="0 0 24 24"><path d="M4 6h16M4 12h16M4 18h16" stroke="#0f172a" stroke-width="2" stroke-linecap="round"/></svg>
  </button>
</header>

<!-- Off-canvas drawer (mobile apps grid) -->
<div id="drawer" class="drawer md:hidden">
  <div id="btnCloseBg" class="bg"></div>
  <aside class="panel">
    <div class="p-4 flex items-center justify-between border-b border-gray-200">
      <div class="font-extrabold text-lg">
        DMS<?= $orgName ? ' — '.$h($orgName) : '' ?>
      </div>
      <button id="btnClose" class="btn-ghost" aria-label="Close">
        <svg class="w-5 h-5" viewBox="0 0 24 24"><path d="M6 6l12 12M18 6L6 18" stroke="#0f172a" stroke-width="2" stroke-linecap="round"/></svg>
      </button>
    </div>

    <div class="gridwrap">
      <div class="grid grid-cols-3 gap-10 justify-items-center">
        <?php foreach ($apps as $a): ?>
          <a href="<?= $h($a[1]) ?>" class="tile" aria-label="<?= $h($a[0]) ?>" onclick="closeDrawer()">
            <div class="iconcard"><svg aria-hidden="true"><use href="#<?= $h($a[2]) ?>"></use></svg></div>
            <div class="label"><?= $h($a[0]) ?></div>
          </a>
        <?php endforeach; ?>
      </div>

      <div class="mt-6 border-t border-gray-200 pt-4">
        <form id="logoutForm" action="<?= $h($logoutUrl) ?>" method="post">
          <button type="submit" class="btn-logout" id="btnLogout">
            <svg class="w-5 h-5 inline-block mr-1 align-[-2px]" viewBox="0 0 24 24">
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

    <!-- RIGHT: Icon grid (first on mobile) -->
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

    <!-- LEFT: org identity + clock + calendar (desktop) -->
    <section class="order-2 md:order-1 hidden md:block hero">
      <div class="badge">
        <svg class="w-4 h-4" viewBox="0 0 24 24"><path d="M12 3l8 4v5c0 5-4 9-8 9s-8-4-8-9V7l8-4z" fill="currentColor"/></svg>
        SECURE • BANGLADESH-READY
      </div>

      <!-- Organisation identity (always shows labels; uses "—" if empty) -->
      <div class="mt-4 max-w-xl rounded-2xl border border-gray-200 bg-white p-4 shadow-md">
        <div class="flex items-start gap-4">
          <div class="flex-shrink-0">
            <img
              src="<?= $h($logoUrl) ?>"
              alt="<?= $h($orgName !== '' ? $orgName : 'KlinFlow') ?>"
              class="h-16 w-auto rounded-lg border border-gray-200 bg-white p-1"
            >
          </div>

          <div class="space-y-2">
            <h1 class="text-3xl md:text-4xl font-extrabold leading-tight text-slate-900">
              <?= $h($orgName !== '' ? $orgName : 'Your Organisation') ?>
            </h1>

            <dl class="space-y-1 text-sm text-gray-700">
              <div class="flex gap-2">
                <dt class="w-20 font-semibold text-gray-500">Address</dt>
                <dd class="flex-1 whitespace-pre-line">
                  <?= $orgAddress !== '' ? nl2br($h($orgAddress)) : '—' ?>
                </dd>
              </div>
              <div class="flex gap-2">
                <dt class="w-20 font-semibold text-gray-500">Phone</dt>
                <dd class="flex-1">
                  <?= $orgPhone !== '' ? $h($orgPhone) : '—' ?>
                </dd>
              </div>
              <div class="flex gap-2">
                <dt class="w-20 font-semibold text-gray-500">Email</dt>
                <dd class="flex-1">
                  <?= $orgEmail !== '' ? $h($orgEmail) : '—' ?>
                </dd>
              </div>
            </dl>
          </div>
        </div>
      </div>

      <!-- Time + calendar -->
      <div class="mt-4 grid grid-cols-1 sm:grid-cols-2 gap-3">
        <div class="info-card info-card--time">
          <div class="font-semibold text-slate-700">Time</div>
          <div id="dmsClock" class="clock-text mt-2">--:--:--</div>
          <div id="dmsClockSub" class="clock-sub mt-1">—</div>
        </div>
        <div class="info-card info-card--date">
          <div class="font-bold" id="dmsCalTitle"></div>
          <div class="grid grid-cols-7 gap-1 mt-2 text-center text-sm">
            <?php foreach (['S','M','T','W','T','F','S'] as $dow): ?>
              <div class="py-1 font-semibold text-gray-500"><?= $h($dow) ?></div>
            <?php endforeach; ?>
            <?php for ($i=0; $i<42; $i++): ?>
              <div class="py-2 rounded-lg bg-gray-50" data-dms-cal-cell></div>
            <?php endfor; ?>
          </div>
        </div>
      </div>

      <div class="mt-4 text-sm text-gray-700">
        Shortcut:
        <span style="padding:.18rem .42rem;background:#e5e7eb;border-radius:.375rem;font:12px/1.2 ui-monospace,Menlo,monospace;">L</span>
        → logout
      </div>
    </section>
  </div>
</main>

<script>
  /* ============================================================
   * SEGMENT 1: Drawer logic (mobile menu)
   * ============================================================
   */
  var drawer = document.getElementById('drawer');

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

  document.getElementById('btnOpen')?.addEventListener('click', openDrawer);
  document.getElementById('btnClose')?.addEventListener('click', closeDrawer);
  document.getElementById('btnCloseBg')?.addEventListener('click', closeDrawer);

  /* ============================================================
   * SEGMENT 2: Logout helper (POST first, then fallbacks)
   * ============================================================
   */
  var logoutForm = document.getElementById('logoutForm');

  function doLogout() {
    var url = <?= json_encode($logoutUrl, JSON_UNESCAPED_SLASHES) ?>;

    fetch(url, { method: 'POST', credentials: 'include' })
      .then(function () {
        window.location.replace('/tenant/login');
      })
      .catch(function () {
        if (logoutForm) {
          try { logoutForm.submit(); return; } catch (e) {}
        }
        window.location.href = url;
      });
  }

  logoutForm?.addEventListener('submit', function (e) {
    e.preventDefault();
    doLogout();
  });

  /* ============================================================
   * SEGMENT 3: Keyboard shortcuts
   *   - L → logout
   *   - First letter of each app label → open that app
   *     (uses .label inside each a.tile; first match wins)
   * ============================================================
   */
  (function () {
    var appShortcuts = {};

    function isTypingTarget(target) {
      if (!target) return false;
      var tag = (target.tagName || '').toLowerCase();
      return tag === 'input' || tag === 'textarea' || target.isContentEditable;
    }

    function buildAppShortcuts() {
      appShortcuts = {};

      // Both main grid and drawer use <a class="tile"> with a .label
      var links = document.querySelectorAll('a.tile');
      links.forEach(function (link) {
        var labelEl = link.querySelector('.label');
        var label =
          (labelEl ? labelEl.textContent : (link.getAttribute('aria-label') || ''))
            .trim();

        if (!label) return;
        var key = label.charAt(0).toLowerCase();
        if (!key || appShortcuts[key]) return; // keep the first app for each letter

        appShortcuts[key] = link.href;
        // Optional: you could mark the DOM for debugging
        // link.dataset.shortcutKey = key;
      });
    }

    // Build once now + once on DOM ready (covers late DOM changes)
    if (document.readyState === 'loading') {
      document.addEventListener('DOMContentLoaded', buildAppShortcuts);
    } else {
      buildAppShortcuts();
    }

    document.addEventListener('keydown', function (e) {
      var key = (e.key || '').toLowerCase();
      if (!key) return;
      if (isTypingTarget(e.target)) return;
      if (e.altKey || e.ctrlKey || e.metaKey) return;

      // L → logout
      if (key === 'l') {
        e.preventDefault();
        doLogout();
        return;
      }

      // First-letter app shortcuts
      var href = appShortcuts[key];
      if (href) {
        e.preventDefault();
        window.location.href = href;
      }
    });
  })();

  /* ============================================================
   * SEGMENT 4: Clock + calendar (left side)
   * ============================================================
   */
  (function () {
    function tick() {
      var now  = new Date(),
          dd   = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'],
          mmn  = ['January', 'February', 'March', 'April', 'May', 'June', 'July',
                  'August', 'September', 'October', 'November', 'December'],
          hh   = String(now.getHours()).padStart(2, '0'),
          mm   = String(now.getMinutes()).padStart(2, '0'),
          ss   = String(now.getSeconds()).padStart(2, '0'),
          time = hh + ':' + mm + ':' + ss;

      var c = document.getElementById('dmsClock');
      var s = document.getElementById('dmsClockSub');
      if (c) c.textContent = time;
      if (s) s.textContent =
        dd[now.getDay()] + ' ' + now.getDate() + ' ' +
        mmn[now.getMonth()] + ' ' + now.getFullYear();
    }

    tick();
    setInterval(tick, 1000);

    function drawCalendar(date) {
      var d        = new Date(date.getFullYear(), date.getMonth(), 1);
      var firstDow = d.getDay();
      var lastDay  = new Date(date.getFullYear(), date.getMonth() + 1, 0).getDate();
      var months   = ['January', 'February', 'March', 'April', 'May', 'June', 'July',
                      'August', 'September', 'October', 'November', 'December'];

      var title = document.getElementById('dmsCalTitle');
      if (title) title.textContent = months[date.getMonth()] + ' ' + date.getFullYear();

      var cells = document.querySelectorAll('[data-dms-cal-cell]');
      cells.forEach(function (c) {
        c.textContent = '';
        c.className = 'py-2 rounded-lg bg-gray-50';
      });

      for (var i = 0; i < lastDay; i++) {
        var idx  = firstDow + i;
        var cell = cells[idx];
        if (!cell) continue;
        cell.textContent = (i + 1);
        if (i + 1 === date.getDate()) {
          cell.className = 'py-2 rounded-lg bg-emerald-100 font-bold';
        }
      }
    }

    drawCalendar(new Date());
  })();
</script>