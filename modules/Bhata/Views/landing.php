<?php
declare(strict_types=1);

/**
 * BhataFlow — Landing (SVG sprite edition, consistent with DMS/HotelFlow/POS)
 * - Mobile: 3-col icon grid, logo + hamburger header, off-canvas drawer + Logout (POST)
 * - Desktop: Left hero with clock & calendar; Right 4-col icon grid (labels under icons)
 * - Uses on-page SVG sprite (no external icon fonts)
 */

$h = fn($s)=>htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');

/* ---- Brand / theme ---- */
$brand     = $brandColor ?? '#228B22';
$tint      = 'rgba(34,139,34,0.10)';
$logoutUrl = '/tenant/logout';

/* ---- Org / ctx ---- */
$orgArr   = $org ?? ($ctx['org'] ?? ($_SESSION['tenant_org'] ?? []));
$orgName  = trim((string)($orgArr['name'] ?? ''));
$orgBadge = $orgName !== '' ? ' — <b>'. $h($orgName) .'</b>' : '';

/* ---- Canonical web base for this module ---- */
$ctxModuleBase = (string)($ctx['module_base'] ?? '');
$givenBase     = isset($base) ? (string)$base : '';

$normalizeBase = static function (string $maybe): string {
    $maybe = trim($maybe);
    if ($maybe === '') return '';
    if (preg_match('~^https?://~i', $maybe)) { $p = parse_url($maybe); $maybe = (string)($p['path'] ?? '/'); }
    if (preg_match('~/(modules|home|var|srv|opt)/~i', $maybe)) return '';
    return '/' . ltrim(rtrim($maybe, '/'), '/');
};

$nbGiven = $normalizeBase($givenBase);
$nbCtx   = $normalizeBase($ctxModuleBase);

if ($nbCtx !== '') {
    $base = $nbCtx;
} elseif ($nbGiven !== '') {
    $base = $nbGiven;
} else {
    $slug = (string)($orgArr['slug'] ?? '');
    $base = $slug !== '' ? '/t/' . rawurlencode($slug) . '/apps/bhata' : '/apps/bhata';
}

/* ---- Bhata app grid (labels kept, now mapped to SVG symbols) ---- */
$apps = [
  ['Production',         $base . '/production',            'ico-production'],
  ['Kiln & Firing',      $base . '/production/firing',     'ico-firing'],
  ['Moulding (Green)',   $base . '/production/moulding',   'ico-moulding'],
  ['Dispatch',           $base . '/dispatch',              'ico-dispatch'],

  ['Clay',               $base . '/materials/clay',        'ico-clay'],
  ['Coal / Fuel',        $base . '/materials/coal',        'ico-coal'],
  ['Sand',               $base . '/materials/sand',        'ico-sand'],
  ['Stock',              $base . '/materials/stock',       'ico-stock'],

  ['Attendance',         $base . '/hr/attendance',         'ico-attendance'],
  ['Piece Rate',         $base . '/hr/piece-rate',         'ico-piece-rate'],
  ['Expenses',           $base . '/finance/expenses',      'ico-expenses'],
  ['Banking',            $base . '/finance/banking',       'ico-banking'],

  ['Reports',            $base . '/reports',               'ico-reports'],
  ['Prices',             $base . '/prices',                'ico-prices'],
  ['Settings',           $base . '/settings',              'ico-settings'],
  ['Reconcile',          $base . '/banking/reconcile',     'ico-reconcile'],
];
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= $h($title ?? ('BhataFlow — Apps' . ($orgName ? ' — '.$orgName : ''))) ?></title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css">
<style>
  :root { --brand: <?= $h($brand) ?>; }
  body  { background: <?= $tint ?>; color:#0f172a; font-family: system-ui,-apple-system,"Segoe UI",Roboto,Inter,Arial,sans-serif; }

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

  /* Layout wraps */
  .wrap { max-width: 1200px; }
</style>
</head>
<body>

<!-- On-page SVG sprite (BhataFlow icons) -->
<svg xmlns="http://www.w3.org/2000/svg" style="display:none">
  <!-- Production -->
  <symbol id="ico-production" viewBox="0 0 64 64">
    <rect width="64" height="64" rx="14" fill="#E2E8F0"/>
    <rect x="12" y="36" width="40" height="14" rx="3" fill="#0F172A"/>
    <rect x="16" y="22" width="10" height="8" rx="2" fill="#94A3B8"/>
    <rect x="28" y="20" width="10" height="10" rx="2" fill="#CBD5E1"/>
    <rect x="40" y="18" width="10" height="12" rx="2" fill="#E5E7EB"/>
  </symbol>

  <!-- Kiln & Firing -->
  <symbol id="ico-firing" viewBox="0 0 64 64">
    <rect width="64" height="64" rx="14" fill="#FFEDD5"/>
    <path d="M22 44c0-10 8-14 10-20 2 6 10 10 10 20 0 6-4 10-10 10s-10-4-10-10z" fill="#EA580C"/>
    <path d="M26 44c0-6 6-8 6-12 1 3 6 6 6 12 0 3-3 6-6 6s-6-3-6-6z" fill="#FDBA74"/>
  </symbol>

  <!-- Moulding (Green) -->
  <symbol id="ico-moulding" viewBox="0 0 64 64">
    <rect width="64" height="64" rx="14" fill="#CFFAFE"/>
    <rect x="16" y="34" width="32" height="12" rx="3" fill="#06B6D4"/>
    <rect x="18" y="30" width="28" height="4" rx="2" fill="#A5F3FC"/>
    <rect x="18" y="24" width="28" height="4" rx="2" fill="#67E8F9"/>
  </symbol>

  <!-- Dispatch -->
  <symbol id="ico-dispatch" viewBox="0 0 64 64">
    <rect width="64" height="64" rx="14" fill="#DBEAFE"/>
    <rect x="16" y="30" width="32" height="14" rx="3" fill="#3B82F6"/>
    <circle cx="24" cy="46" r="4" fill="#1E3A8A"/>
    <circle cx="40" cy="46" r="4" fill="#1E3A8A"/>
    <path d="M36 30l6-6h6" stroke="#93C5FD" stroke-width="4" stroke-linecap="round"/>
  </symbol>

  <!-- Clay -->
  <symbol id="ico-clay" viewBox="0 0 64 64">
    <rect width="64" height="64" rx="14" fill="#FDE68A"/>
    <path d="M18 44h28l-6-10H24l-6 10z" fill="#D97706"/>
    <rect x="22" y="24" width="20" height="8" rx="2" fill="#F59E0B"/>
  </symbol>

  <!-- Coal / Fuel -->
  <symbol id="ico-coal" viewBox="0 0 64 64">
    <rect width="64" height="64" rx="14" fill="#E5E7EB"/>
    <path d="M20 44l6-10 8 4 10-8 4 10-6 4H22l-2 0z" fill="#334155"/>
    <path d="M32 22c2 4 8 6 8 12 0 4-2 6-6 6" stroke="#94A3B8" stroke-width="3" fill="none"/>
  </symbol>

  <!-- Sand -->
  <symbol id="ico-sand" viewBox="0 0 64 64">
    <rect width="64" height="64" rx="14" fill="#FEF3C7"/>
    <path d="M16 44c8-6 24-6 32 0" stroke="#D97706" stroke-width="5" stroke-linecap="round"/>
    <circle cx="22" cy="28" r="3" fill="#F59E0B"/>
    <circle cx="42" cy="24" r="2" fill="#F59E0B"/>
  </symbol>

  <!-- Stock -->
  <symbol id="ico-stock" viewBox="0 0 64 64">
    <rect width="64" height="64" rx="14" fill="#DCFCE7"/>
    <rect x="18" y="22" width="28" height="24" rx="3" fill="#059669"/>
    <path d="M18 28h28" stroke="#A7F3D0" stroke-width="4" stroke-linecap="round"/>
  </symbol>

  <!-- Attendance -->
  <symbol id="ico-attendance" viewBox="0 0 64 64">
    <rect width="64" height="64" rx="14" fill="#EDE9FE"/>
    <circle cx="24" cy="28" r="6" fill="#7C3AED"/>
    <path d="M14 46c2-6 10-10 16-10" stroke="#A78BFA" stroke-width="4" stroke-linecap="round"/>
    <path d="M40 20v16h8" stroke="#7C3AED" stroke-width="4" stroke-linecap="round"/>
  </symbol>

  <!-- Piece Rate -->
  <symbol id="ico-piece-rate" viewBox="0 0 64 64">
    <rect width="64" height="64" rx="14" fill="#ECFCCB"/>
    <path d="M22 40h14c6 0 10-4 10-8s-4-8-10-8h-6" stroke="#65A30D" stroke-width="5" stroke-linecap="round"/>
    <circle cx="22" cy="24" r="4" fill="#4D7C0F"/>
  </symbol>

  <!-- Expenses -->
  <symbol id="ico-expenses" viewBox="0 0 64 64">
    <rect width="64" height="64" rx="14" fill="#FFE4E6"/>
    <rect x="20" y="16" width="24" height="32" rx="4" fill="#F43F5E"/>
    <path d="M24 24h16M24 32h12M24 40h14" stroke="#FECDD3" stroke-width="3" stroke-linecap="round"/>
  </symbol>

  <!-- Banking -->
  <symbol id="ico-banking" viewBox="0 0 64 64">
    <rect width="64" height="64" rx="14" fill="#CCFBF1"/>
    <path d="M32 16l16 8H16l16-8z" fill="#14B8A6"/>
    <path d="M18 44h28M18 30h28" stroke="#0F766E" stroke-width="4" stroke-linecap="round"/>
  </symbol>

  <!-- Reports -->
  <symbol id="ico-reports" viewBox="0 0 64 64">
    <rect width="64" height="64" rx="14" fill="#FEF3C7"/>
    <path d="M20 42V30M32 42V22M44 42V36" stroke="#F59E0B" stroke-width="5" stroke-linecap="round"/>
  </symbol>

  <!-- Prices -->
  <symbol id="ico-prices" viewBox="0 0 64 64">
    <rect width="64" height="64" rx="14" fill="#FDF2F8"/>
    <rect x="20" y="20" width="24" height="24" rx="4" fill="#EC4899"/>
    <path d="M24 28h16M24 36h12" stroke="#FCE7F3" stroke-width="3" stroke-linecap="round"/>
  </symbol>

  <!-- Settings -->
  <symbol id="ico-settings" viewBox="0 0 64 64">
    <rect width="64" height="64" rx="14" fill="#F8FAFC"/>
    <path d="M32 24a8 8 0 100 16 8 8 0 000-16z" fill="#0F172A"/>
    <path d="M32 14v8M32 42v8M14 32h8M42 32h8M21 21l6 6M37 37l6 6M21 43l6-6M37 27l6-6" stroke="#94A3B8" stroke-width="3" stroke-linecap="round"/>
  </symbol>

  <!-- Reconcile -->
  <symbol id="ico-reconcile" viewBox="0 0 64 64">
    <rect width="64" height="64" rx="14" fill="#E0F2FE"/>
    <path d="M18 32h28" stroke="#0369A1" stroke-width="6" stroke-linecap="round"/>
    <path d="M18 24h16M30 40h16" stroke="#7DD3FC" stroke-width="4" stroke-linecap="round"/>
  </symbol>
</svg>

<!-- Mobile header: logo + hamburger -->
<header class="m-header md:hidden px-4 py-3 flex items-center justify-between">
  <img src="/public/assets/brand/logo.png" alt="KlinFlow" class="h-7 w-auto" />
  <button id="btnOpen" class="btn-ghost" aria-label="Menu">
    <svg class="w-6 h-6" viewBox="0 0 24 24"><path d="M4 6h16M4 12h16M4 18h16" stroke="#0f172a" stroke-width="2" stroke-linecap="round"/></svg>
  </button>
</header>

<!-- Off-canvas drawer (mobile) -->
<div id="drawer" class="drawer md:hidden">
  <div id="btnCloseBg" class="bg"></div>
  <aside class="panel">
    <div class="p-4 flex items-center justify-between border-b border-gray-200">
      <div class="font-extrabold text-lg">BhataFlow<?= $orgName? ' — '.$h($orgName): '' ?></div>
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
    <section class="order-2 md:order-1 hidden md:block">
      <div class="inline-flex items-center gap-2 rounded-full px-3 py-1 text-sm"
           style="background:rgba(34,139,34,.10); color:var(--brand); border:1px solid rgba(34,139,34,.22)">
        <svg class="w-4 h-4" viewBox="0 0 24 24"><path d="M12 3l8 4v5c0 5-4 9-8 9s-8-4-8-9V7l8-4z" fill="currentColor"/></svg>
        SECURE • BANGLADESH-READY
      </div>

      <h1 class="text-3xl md:text-5xl leading-tight mt-3 font-extrabold">
        One-Stop <span style="color:var(--brand)">Multi-Tenant</span><br class="hidden md:block">
        Platform for Bangladesh<?= $orgBadge ?>
      </h1>

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

  // Keyboard logout shortcut
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
    document.getElementById('clock') && (document.getElementById('clock').textContent = hh+':'+mm+':'+ss);
    document.getElementById('clockSub') && (document.getElementById('clockSub').textContent = dd[now.getDay()]+' '+now.getDate()+' '+mmn[now.getMonth()]+' '+now.getFullYear());
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
</script>
</body>
</html>