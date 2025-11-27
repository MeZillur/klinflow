<?php
declare(strict_types=1);

/* POS — Apps (SVG sprite edition, consistent with DMS/HotelFlow) */
$h = fn($s)=>htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');

$brand      = $brandColor ?? '#228B22';
$tint       = 'rgba(34,139,34,0.10)';

$orgArr     = $org ?? ($ctx['org'] ?? ($_SESSION['tenant_org'] ?? []));
$orgName    = trim((string)($orgArr['name'] ?? ''));
$orgAddress = trim((string)($orgArr['address'] ?? ''));
$orgBadge   = $orgName !== '' ? ' — <b>'. $h($orgName) .'</b>' : '';

$base       = rtrim((string)($base ?? '/apps/pos'), '/');
$logoutUrl  = '/tenant/logout';

$orgId      = (int)($orgArr['id'] ?? ($ctx['org_id'] ?? 0));
$orgLogo    = $orgId ? ($base.'/Assets/Brand/logo/'.$orgId.'/logo.png') : '';

/* Apps (labels kept exactly; Dashboard first) — Register removed
   Fourth element is keyboard shortcut key (lowercase) */
$apps = [
  ['Dashboard',  "$base/dashboard",            'ico-dashboard',   'd'],
  ['Sales',      "$base/sales",                'ico-sales',       's'],

  ['Purchases',  "$base/purchases",            'ico-purchases',   'u'],
  ['Products',   "$base/products",             'ico-products',    'p'],

  ['Categories', "$base/categories",           'ico-categories',  'g'],
  ['Inventory',  "$base/inventory",            'ico-inventory',   'i'],
  ['Movements',  "$base/inventory/movements",  'ico-movements',   'm'],
  ['Customers',  "$base/customers",            'ico-customers',   'c'],

  ['Suppliers',  "$base/suppliers",            'ico-suppliers',   'x'],
  ['Payments',   "$base/payments",             'ico-payments',    'y'],
  ['Reports',    "$base/reports",              'ico-reports',     'r'],
  ['Accounting', "$base/accounting",           'ico-accounting',  'a'],

  ['GL Journals',"$base/gl/journals",          'ico-gl-journals', 'j'],
  ['Banking',    "$base/banking/accounts",     'ico-banking',     'b'],
  ['Branches',  "$base/branches",    		   'ico-reconcile',   'n'],

  // Replaces old “Cash Book”
  ['Settings',   "$base/settings",             'ico-settings',    't'],
];
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= $h($title ?? ('POS — Apps' . ($orgName ? ' — '.$orgName : ''))) ?></title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css">
<style>
  :root { --brand: <?= $h($brand) ?>; }
  body  { background: <?= $tint ?>; color:#0f172a; font-family: system-ui,-apple-system,"Segoe UI",Roboto,Inter,Arial,sans-serif; }

  .badge{ background:rgba(34,139,34,.10); color:var(--brand); border:1px solid rgba(34,139,34,.22);
          border-radius:9999px; padding:6px 12px; font-size:13px; display:inline-flex; align-items:center; gap:8px; }
  .hero h1{ font-weight:900; letter-spacing:-.02em; }

  /* Icon grid (Odoo-style) */
  .appgrid{ gap:16px; }
  .tile   { display:flex; flex-direction:column; align-items:center; text-align:center; }
  .iconcard{
    width:86px; height:86px; background:#fff; border:1px solid rgba(0,0,0,.06); border-radius:14px;
    box-shadow:0 6px 16px rgba(0,0,0,.08); display:grid; place-items:center;
    transition:transform .12s ease, box-shadow .12s ease, border-color .12s ease;
  }
  .tile:hover .iconcard{ transform:translateY(-2px); box-shadow:0 12px 26px rgba(0,0,0,.14); border-color:rgba(0,0,0,.12); }
  .tile svg{ width:60%; height:60%; }
  .label { margin-top:10px; font-weight:800; letter-spacing:-.01em; }

  /* Mobile: 3 columns + smaller cards */
  @media (max-width: 767px){
    .appgrid{ gap:12px; }
    .iconcard{ width:72px; height:72px; border-radius:12px; }
    .label   { margin-top:8px; font-size:13px; }
  }

  /* Info cards (desktop left) */
  .info-card{ background:#fff; border:1px solid rgba(15,23,42,.08); border-radius:16px; padding:16px; box-shadow:0 6px 16px rgba(0,0,0,.06); }
  .clock-text{ font-weight:800; font-size:28px; }
  .clock-sub{ color:#475569; }

  /* Mobile header + drawer (overlay) */
  .m-header{ position:sticky; top:0; z-index:40; background:rgba(34,139,34,.10); backdrop-filter:blur(6px); border-bottom:1px solid rgba(0,0,0,.08); }
  .drawer{ position:fixed; inset:0; display:none; z-index:1000; }
  .drawer.open{ display:block; }
  .drawer .bg{ position:absolute; inset:0; background:rgba(15,23,42,.45); z-index:1000; }
  .drawer .panel{
    position:absolute; top:0; right:0; width:86%; max-width:380px; height:100%; background:#fff;
    border-left:1px solid rgba(0,0,0,.08); box-shadow:-10px 0 30px rgba(0,0,0,.25);
    transform:translateX(100%); transition:transform .18s ease-in-out;
    display:flex; flex-direction:column; z-index:1001;
  }
  .drawer.open .panel{ transform:translateX(0); }
  .drawer .gridwrap{ padding:14px; overflow-y:auto; -webkit-overflow-scrolling:touch; }
  .btn-ghost{ background:#f1f5f9; color:#0f172a; border-radius:12px; padding:10px 12px; display:inline-grid; place-items:center; }
  .btn-logout{ background:#ef4444; color:#fff; border-radius:14px; padding:13px 14px; width:100%; font-weight:700; display:inline-flex; align-items:center; gap:8px; }
</style>
</head>
<body>

<!-- On-page SVG sprite (POS icons) -->
<svg xmlns="http://www.w3.org/2000/svg" style="display:none">
  <!-- Dashboard (new) -->
  <symbol id="ico-dashboard" viewBox="0 0 64 64">
    <rect width="64" height="64" rx="14" fill="#ECFCCB"/>
    <path d="M16 40a16 16 0 0 1 32 0" fill="none" stroke="#65A30D" stroke-width="5" stroke-linecap="round"/>
    <circle cx="32" cy="40" r="3" fill="#166534"/>
    <path d="M32 40l8-10" stroke="#166534" stroke-width="4" stroke-linecap="round"/>
    <rect x="18" y="44" width="28" height="4" rx="2" fill="#A7F3D0"/>
  </symbol>

  <!-- Row 1 -->
  <symbol id="ico-register" viewBox="0 0 64 64"><rect width="64" height="64" rx="14" fill="#E2E8F0"/><rect x="14" y="36" width="36" height="14" rx="3" fill="#0F172A"/><rect x="18" y="22" width="20" height="10" rx="2" fill="#94A3B8"/><circle cx="40" cy="27" r="4" fill="#10B981"/></symbol>
  <symbol id="ico-sales" viewBox="0 0 64 64"><rect width="64" height="64" rx="14" fill="#DBEAFE"/><path d="M18 40c8-10 14-14 28-16" stroke="#2563EB" stroke-width="5" stroke-linecap="round"/><rect x="20" y="20" width="24" height="6" rx="3" fill="#3B82F6"/></symbol>
  <symbol id="ico-purchases" viewBox="0 0 64 64"><rect width="64" height="64" rx="14" fill="#ECFCCB"/><path d="M22 28h20l4 16H18l4-16z" fill="#65A30D"/><circle cx="28" cy="46" r="4" fill="#4D7C0F"/><circle cx="40" cy="46" r="4" fill="#4D7C0F"/></symbol>
  <symbol id="ico-products" viewBox="0 0 64 64"><rect width="64" height="64" rx="14" fill="#F5F3FF"/><path d="M20 42h24l-2-14H22l-2 14z" fill="#3730A3"/><path d="M22 28l10-8 10 8" stroke="#A78BFA" stroke-width="4" stroke-linecap="round" stroke-linejoin="round"/></symbol>

  <!-- Row 2 -->
  <symbol id="ico-categories" viewBox="0 0 64 64"><rect width="64" height="64" rx="14" fill="#FCE7F3"/><path d="M24 20h24l-8 12H16l8-12z" fill="#DB2777"/><circle cx="24" cy="44" r="6" fill="#BE185D"/></symbol>
  <symbol id="ico-inventory" viewBox="0 0 64 64"><rect width="64" height="64" rx="14" fill="#E2E8F0"/><rect x="18" y="26" width="28" height="18" rx="2" fill="#0F172A"/><path d="M18 22h28" stroke="#94A3B8" stroke-width="4" stroke-linecap="round"/></symbol>
  <symbol id="ico-movements" viewBox="0 0 64 64"><rect width="64" height="64" rx="14" fill="#CFFAFE"/><path d="M20 40c8 0 8-16 24-16" stroke="#06B6D4" stroke-width="5" stroke-linecap="round"/><path d="M40 20l6 6-6 6" stroke="#0E7490" stroke-width="5" stroke-linecap="round" stroke-linejoin="round"/></symbol>
  <symbol id="ico-customers" viewBox="0 0 64 64"><rect width="64" height="64" rx="14" fill="#EDE9FE"/><circle cx="26" cy="28" r="6" fill="#4C1D95"/><circle cx="38" cy="28" r="6" fill="#7C3AED"/><path d="M14 46c2-6 8-10 12-10m12 0c4 0 10 4 12 10" stroke="#7C3AED" stroke-width="4" stroke-linecap="round"/></symbol>

  <!-- Row 3 -->
  <symbol id="ico-suppliers" viewBox="0 0 64 64"><rect width="64" height="64" rx="14" fill="#EFF6FF"/><path d="M18 38h28l4 6H14l4-6z" fill="#0F172A"/><rect x="20" y="22" width="24" height="14" rx="3" fill="#2563EB"/></symbol>
  <symbol id="ico-payments" viewBox="0 0 64 64"><rect width="64" height="64" rx="14" fill="#DCFCE7"/><rect x="14" y="24" width="36" height="22" rx="6" fill="#10B981"/><circle cx="26" cy="35" r="4" fill="#064E3B"/><rect x="32" y="33" width="14" height="4" rx="2" fill="#BBF7D0"/></symbol>
  <symbol id="ico-reports" viewBox="0 0 64 64"><rect width="64" height="64" rx="14" fill="#FEF3C7"/><path d="M20 42V30M32 42V22M44 42V36" stroke="#F59E0B" stroke-width="5" stroke-linecap="round"/></symbol>
  <symbol id="ico-accounting" viewBox="0 0 64 64"><rect width="64" height="64" rx="14" fill="#F8FAFC"/><path d="M18 44h28M22 20h8v24h-8zM34 26h8v18h-8z" fill="#0F172A"/></symbol>

  <!-- Row 4 -->
  <symbol id="ico-gl-journals" viewBox="0 0 64 64"><rect width="64" height="64" rx="14" fill="#F3E8FF"/><rect x="20" y="16" width="24" height="32" rx="4" fill="#7C3AED"/><path d="M24 24h16M24 32h12M24 40h14" stroke="#EDE9FE" stroke-width="3" stroke-linecap="round"/></symbol>
  <symbol id="ico-banking" viewBox="0 0 64 64"><rect width="64" height="64" rx="14" fill="#DCFCE7"/><path d="M14 28h36M18 44h28" stroke="#065F46" stroke-width="4" stroke-linecap="round"/><path d="M32 16l16 8H16l16-8z" fill="#10B981"/></symbol>
  <symbol id="ico-reconcile" viewBox="0 0 64 64"><rect width="64" height="64" rx="14" fill="#E0F2FE"/><path d="M18 32h28" stroke="#0369A1" stroke-width="6" stroke-linecap="round"/><path d="M18 24h16M30 40h16" stroke="#7DD3FC" stroke-width="4" stroke-linecap="round"/></symbol>
  <symbol id="ico-cashbook" viewBox="0 0 64 64"><rect width="64" height="64" rx="14" fill="#FFFBEB"/><rect x="20" y="18" width="24" height="28" rx="3" fill="#F59E0B"/><path d="M24 24h16M24 30h12M24 36h14" stroke="#FEF3C7" stroke-width="3" stroke-linecap="round"/></symbol>

  <!-- Settings icon -->
  <symbol id="ico-settings" viewBox="0 0 64 64">
    <rect width="64" height="64" rx="14" fill="#F0FDF4"/>
    <path d="M32 22a10 10 0 1 0 .001 20.001A10 10 0 0 0 32 22zm0-6 4 4h8v8l4 4-4 4v8h-8l-4 4-4-4h-8v-8l-4-4 4-4v-8h8l4-4z"
          fill="#166534"/>
  </symbol>
</svg>

<!-- Mobile header -->
<header class="m-header md:hidden px-4 py-3 flex items-center justify-between">
  <img src="<?= $h($orgLogo ?: '/public/assets/brand/logo.png') ?>"
       alt="<?= $h($orgName ?: 'KlinFlow') ?>" class="h-7 w-auto" />
  <button id="btnOpen" class="btn-ghost" aria-label="Menu">
    <svg class="w-6 h-6" viewBox="0 0 24 24"><path d="M4 6h16M4 12h16M4 18h16" stroke="#0f172a" stroke-width="2" stroke-linecap="round"/></svg>
  </button>
</header>

<!-- Off-canvas drawer (mobile) -->
<div id="drawer" class="drawer md:hidden">
  <div id="btnCloseBg" class="bg"></div>
  <aside class="panel">
    <div class="p-4 flex items-center justify-between border-b border-gray-200">
      <div class="font-extrabold text-lg">POS<?= $orgName? ' — '.$h($orgName): '' ?></div>
      <button id="btnClose" class="btn-ghost" aria-label="Close">
        <svg class="w-5 h-5" viewBox="0 0 24 24"><path d="M6 6l12 12M18 6L6 18" stroke="#0f172a" stroke-width="2" stroke-linecap="round"/></svg>
      </button>
    </div>

    <div class="gridwrap">
      <!-- Mobile: 3 columns -->
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
            <svg class="w-5 h-5" viewBox="0 0 24 24"><path d="M15 12H3M11 6l-6 6 6 6M21 3v18" stroke="#fff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
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
      <!-- Mobile: 3 cols | Desktop: 4 cols -->
      <div class="grid grid-cols-3 md:grid-cols-4 appgrid">
        <?php foreach ($apps as $a): ?>
          <a href="<?= $h($a[1]) ?>" class="tile" aria-label="<?= $h($a[0]) ?>">
            <div class="iconcard"><svg aria-hidden="true"><use href="#<?= $h($a[2]) ?>"></use></svg></div>
            <div class="label text-base md:text-lg"><?= $h($a[0]) ?></div>
          </a>
        <?php endforeach; ?>
      </div>
    </section>

    <!-- LEFT: hero + clock + calendar (desktop only) -->
    <section class="order-2 md:order-1 hidden md:block hero">
      <div class="badge">
        <svg class="w-4 h-4" viewBox="0 0 24 24"><path d="M12 3l8 4v5c0 5-4 9-8 9s-8-4-8-9V7l8-4z" fill="currentColor"/></svg>
        SECURE • BANGLADESH-READY
      </div>

      <!-- Org logo + name + address -->
      <div class="mt-4 flex items-start gap-4">
        <?php if ($orgLogo): ?>
          <div class="flex-shrink-0">
            <img src="<?= $h($orgLogo) ?>"
                 alt="<?= $h($orgName ?: 'Organisation Logo') ?>"
                 class="h-16 w-auto rounded-lg border border-gray-200 bg-white p-1"
                 onerror="this.style.display='none'">
          </div>
        <?php endif; ?>

        <div>
          <h1 class="text-3xl md:text-5xl leading-tight">
            <?= $h($orgName ?: 'Your Business Name') ?>
          </h1>
          <?php if ($orgAddress): ?>
            <p class="mt-2 text-sm md:text-base text-gray-600 whitespace-pre-line">
              <?= nl2br($h($orgAddress)) ?>
            </p>
          <?php endif; ?>
        </div>
      </div>

      <div class="mt-6 grid grid-cols-1 sm:grid-cols-2 gap-3">
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

      <div class="mt-4 text-sm text-gray-700">
        Shortcut: <span style="padding:.18rem .42rem;background:#e5e7eb;border-radius:.375rem;font:12px/1.2 ui-monospace,Menlo,monospace;">L</span> → logout
      </div>
    </section>
  </div>
</main>

<script>
  // Drawer logic
  var drawer = document.getElementById('drawer');
  function openDrawer(){ drawer?.classList.add('open'); document.body.style.overflow = 'hidden'; }
  function closeDrawer(){ drawer?.classList.remove('open'); document.body.style.overflow = ''; }
  document.getElementById('btnOpen')?.addEventListener('click', openDrawer);
  document.getElementById('btnClose')?.addEventListener('click', closeDrawer);
  document.getElementById('btnCloseBg')?.addEventListener('click', closeDrawer);

  // Logout (POST + fallbacks)
  var logoutForm = document.getElementById('logoutForm');
  function doLogout(){
    var url = <?= json_encode($logoutUrl, JSON_UNESCAPED_SLASHES) ?>;
    fetch(url, { method:'POST', credentials:'include' })
      .then(function(){ window.location.replace('/tenant/login'); })
      .catch(function(){
        if (logoutForm) { try { logoutForm.submit(); return; } catch(e){} }
        window.location.href = url;
      });
  }
  logoutForm?.addEventListener('submit', function(e){ e.preventDefault(); doLogout(); });

  // Keyboard logout shortcut (L)
  document.addEventListener('keydown', function(e){
    var tag = (e.target && e.target.tagName || '').toLowerCase();
    if (tag === 'input' || tag === 'textarea' || (e.target && e.target.isContentEditable)) return;
    if (e.altKey || e.ctrlKey || e.metaKey) return;
    if ((e.key || '').toLowerCase() === 'l') { e.preventDefault(); doLogout(); }
  });

  /* --- Clock & calendar (desktop only) --- */
  function tick(){
    var now = new Date(), dd = ['Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'],
        mmn = ['January','February','March','April','May','June','July','August','September','October','November','December'],
        hh = String(now.getHours()).padStart(2,'0'),
        mm = String(now.getMinutes()).padStart(2,'0'),
        ss = String(now.getSeconds()).padStart(2,'0');
    var clock = document.getElementById('clock');
    var sub   = document.getElementById('clockSub');
    if (clock) clock.textContent = hh+':'+mm+':'+ss;
    if (sub) sub.textContent = dd[now.getDay()]+' '+now.getDate()+' '+mmn[now.getMonth()]+' '+now.getFullYear();
  }
  setInterval(tick,1000); tick();

  function drawCalendar(date){
    var d = new Date(date.getFullYear(), date.getMonth(), 1);
    var firstDow = d.getDay();
    var lastDay  = new Date(date.getFullYear(), date.getMonth()+1, 0).getDate();
    var months   = ['January','February','March','April','May','June','July','August','September','October','November','December'];
    var title = document.getElementById('calTitle'); if (title) title.textContent = months[date.getMonth()]+' '+date.getFullYear();
    var cells = document.querySelectorAll('[data-cal-cell]');
    cells.forEach(function(c){ c.textContent=''; c.className='py-2 rounded-lg bg-gray-50'; });
    for (var i=0;i<lastDay;i++){
      var idx = firstDow + i, cell = cells[idx]; if (!cell) continue;
      cell.textContent = (i+1); if (i+1===date.getDate()) cell.className='py-2 rounded-lg bg-emerald-100 font-bold';
    }
  }
  drawCalendar(new Date());

  /* ---------------------------------------------------------------
     APP KEYBOARD SHORTCUTS
     safe: ignores inputs / textareas / contentEditable
     keys come from $apps[3] (lowercase)
  ---------------------------------------------------------------- */
  (function(){
    var appShortcuts = {
      <?php foreach ($apps as $a):
        $key = strtolower((string)($a[3] ?? ''));
        if (!$key) continue;
      ?>
      "<?= $h($key) ?>": "<?= $h($a[1]) ?>",
      <?php endforeach; ?>
    };

    document.addEventListener('keydown', function(e){
      var key = (e.key || '').toLowerCase();
      var tag = (e.target && e.target.tagName || '').toLowerCase();
      if (tag === 'input' || tag === 'textarea' || (e.target && e.target.isContentEditable)) return;
      if (e.ctrlKey || e.altKey || e.metaKey) return;

      if (appShortcuts[key]) {
        e.preventDefault();
        window.location.href = appShortcuts[key];
      }
    });
  })();
</script>
</body>
</html>