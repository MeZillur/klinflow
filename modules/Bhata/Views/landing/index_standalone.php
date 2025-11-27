<?php
declare(strict_types=1);

/**
 * BhataFlow — Landing (Standalone)
 * Mirrors the working HotelFlow landing pattern, but for BhataFlow.
 */

$h = fn($s)=>htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');

/* ---- Brand + base + org ---- */
$brand      = $brandColor ?? '#228B22';
$tint       = 'rgba(34,139,34,0.10)';
$logoutUrl  = '/tenant/logout';

$orgArr   = $org ?? ($ctx['org'] ?? ($_SESSION['tenant_org'] ?? []));
$orgName  = trim((string)($orgArr['name'] ?? ''));
$orgBadge = $orgName !== '' ? ' — <b>'. $h($orgName) .'</b>' : '';

$tenant      = (string)($orgArr['slug'] ?? '');
$module_base = ($tenant !== '') ? '/t/'.rawurlencode($tenant).'/apps/bhata' : '/apps/bhata';

/* ---- Metrics (safe defaults) ---- */
$M  = is_array($metrics ?? null) ? $metrics : [];
$mx = function(string $k, $def='0') use ($M) {
  $v = $M[$k] ?? $def;
  if (is_numeric($v)) return (string)(int)$v;
  return (string)$v;
};

/* ---- BhataFlow tiles (label, href, icon, textTone, bgTone, metricKey) ---- */
$apps = [
  // Production
  ['Production',        "$module_base/production",         'fa-industry',            'text-emerald-700', 'bg-emerald-100', 'production_today'],
  ['Kiln & Firing',     "$module_base/production/kiln",    'fa-fire',                'text-orange-700',  'bg-orange-100',  'kiln_batches'],
  ['Moulding (Green)',  "$module_base/production/moulding", 'fa-grip',               'text-sky-700',     'bg-sky-100',     'moulding_green'],
  ['Dispatch',          "$module_base/dispatch",           'fa-truck-fast',          'text-indigo-700',  'bg-indigo-100',  'dispatch_today'],

  // Materials
  ['Clay',              "$module_base/materials/clay",     'fa-mountain',            'text-lime-700',    'bg-lime-100',    'stock_clay'],
  ['Coal / Fuel',       "$module_base/materials/coal",     'fa-fire-flame-simple',   'text-slate-700',   'bg-slate-100',   'stock_coal'],
  ['Sand',              "$module_base/materials/sand",     'fa-hill-rockslide',      'text-amber-700',   'bg-amber-100',   'stock_sand'],
  ['Stock',             "$module_base/materials/stock",    'fa-cubes-stacked',       'text-emerald-900', 'bg-emerald-100', 'stock_total'],

  // HR
  ['Attendance',        "$module_base/hr/attendance",      'fa-user-check',          'text-purple-700',  'bg-purple-100',  'attendance_today'],
  ['Piece Rate',        "$module_base/hr/piece-rate",      'fa-money-bill',          'text-teal-700',    'bg-teal-100',    'piece_rate_entries'],

  // Finance
  ['Expenses',          "$module_base/finance/expenses",   'fa-file-invoice-dollar', 'text-rose-700',    'bg-rose-100',    'expenses_today'],
  ['Banking',           "$module_base/finance/banking",    'fa-building-columns',    'text-cyan-700',    'bg-cyan-100',    'bank_accounts'],

  // Reports / Settings
  ['Reports',           "$module_base/reports",            'fa-chart-line',          'text-amber-800',   'bg-amber-100',   'reports_ready'],
  ['Prices',            "$module_base/prices",             'fa-tags',                'text-pink-700',    'bg-pink-100',    'price_bands'],
  ['Settings',          "$module_base/settings",           'fa-gear',                'text-gray-700',    'bg-gray-100',    'settings_users'],
  ['Reconcile',         "$module_base/banking/reconcile",  'fa-equals',              'text-cyan-800',    'bg-cyan-100',    'reports_ready'],
];
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title><?= $h($title ?? ('BhataFlow — Landing' . ($orgName ? ' — '.$orgName : ''))) ?></title>

  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

  <style>
    :root { --brand: <?= $h($brand) ?>; }
    body { background: <?= $tint ?>; color:#111827; font-family: system-ui,-apple-system,"Segoe UI",Roboto,Inter,Arial,sans-serif; }

    .wrap { max-width: 1200px; }
    .left { order: 2; }
    .right { order: 1; }
    @media (min-width: 768px) { .left { order: 1; } .right { order: 2; } }

    .badge {
      background: rgba(34,139,34,.10);
      color: var(--brand);
      border: 1px solid rgba(34,139,34,.22);
      border-radius: 9999px;
      padding: 6px 12px;
      font-size: 13px;
      display:inline-flex; align-items:center; gap:8px;
    }
    .hero h1 { font-weight: 900; letter-spacing:-.02em; }
    .apps-grid { gap:12px; }
    @media (max-width:767px){ .apps-grid { gap:8px; } }

    .card {
      background:#fff; border-radius:14px; border:1px solid rgba(0,0,0,.05);
      box-shadow: 0 4px 10px rgba(0,0,0,.05);
      transition: transform .12s ease, box-shadow .12s ease, background .12s ease;
      display:flex; flex-direction:column; justify-content:space-between;
      height:140px; padding:12px;
    }
    .card:hover { transform: translateY(-2px); box-shadow: 0 10px 20px rgba(0,0,0,.10); }
    .tile { display:flex; align-items:flex-start; justify-content:space-between; }
    .tile-title { font-size:14px; font-weight:700; line-height:1.2; }
    .bubble { width:40px; height:40px; border-radius:12px; display:flex; align-items:center; justify-content:center; font-size:18px; box-shadow: inset 0 1px 0 rgba(255,255,255,.6), 0 1px 1px rgba(0,0,0,.04); }

    .count { font-weight:900; font-size:28px; line-height:1; text-align:right; color:#0b1220; opacity:.9; }
    @media (max-width:767px){ .count{ font-size:22px; } }

    .kbd { padding:.18rem .42rem; background:#e5e7eb; border-radius:.375rem; font: 12px/1.2 ui-monospace,SFMono-Regular,Menlo,monospace; }
    .hint {
      display:inline-flex; align-items:center; gap:8px;
      background:#fff; border:1px dashed var(--brand); color:#0f172a;
      padding:6px 10px; border-radius:10px; font-weight:600;
    }

    .info-card { background:#fff; border-radius:14px; border:1px solid rgba(0,0,0,.06); box-shadow:0 6px 16px rgba(0,0,0,.06); padding:14px; }
    .clock-text { font-weight:800; font-size:32px; letter-spacing:.02em; color:#0b1220; }
    .clock-sub { color:#64748b; font-size:12px; }

    .cal { width:100%; }
    .cal-head { display:flex; align-items:center; justify-content:space-between; font-weight:700; }
    .cal-grid { display:grid; grid-template-columns:repeat(7,1fr); gap:6px; margin-top:8px; }
    .cal-cell { text-align:center; padding:6px 0; border-radius:8px; font-size:12px; color:#0b1220; background:#f8fafc; }
    .cal-dow  { font-weight:700; color:#334155; background:transparent; }
    .cal-today{ background:rgba(34,139,34,.14); color:#064e3b; font-weight:800; border:1px solid rgba(34,139,34,.35); }
  </style>
</head>
<body>
  <main class="wrap mx-auto px-4 md:px-6 py-4 md:py-6">
    <div class="grid grid-cols-1 md:grid-cols-2 md:gap-8 items-start">
      <!-- RIGHT grid (first on mobile) -->
      <section class="right">
        <div class="grid grid-cols-2 md:grid-cols-4 apps-grid">
          <?php foreach ($apps as $a): [$label,$href,$icon,$txt,$bg,$key] = $a; ?>
            <a href="<?= $h($href) ?>" class="focus:outline-none card">
              <div class="tile">
                <div><div class="tile-title"><?= $h($label) ?></div></div>
                <div class="bubble <?= $h($bg) ?> <?= $h($txt) ?>"><i class="fa-solid <?= $h($icon) ?>"></i></div>
              </div>
              <div class="count"><?= $h($mx($key)) ?></div>
              <div class="text-right text-gray-700 text-xs opacity-80">Open →</div>
            </a>
          <?php endforeach; ?>
        </div>
      </section>

      <!-- LEFT hero -->
      <section class="left hero">
        <div class="badge"><i class="fa-solid fa-shield-halved"></i> SECURE • BANGLADESH-READY</div>

        <h1 class="text-3xl md:text-5xl leading-tight mt-3">
          One-Stop <span style="color:var(--brand)">Multi-Tenant</span><br class="hidden md:block">
          Platform for Bangladesh<?= $orgBadge ?>
        </h1>

        <!-- Row: clock (left) + calendar (right) -->
        <div class="mt-4 grid grid-cols-1 sm:grid-cols-2 gap-3">
          <!-- Clock -->
          <div class="info-card">
            <div class="flex items-center justify-between">
              <div class="font-semibold text-slate-700"><i class="fa-regular fa-clock text-emerald-600"></i> Time</div>
            </div>
            <div id="clock" class="clock-text mt-2">--:--:--</div>
            <div id="clockSub" class="clock-sub mt-1">—</div>
          </div>

          <!-- Calendar -->
          <div class="info-card">
            <div class="cal">
              <div class="cal-head">
                <span id="calTitle"></span>
              </div>
              <div class="cal-grid mt-2">
                <?php
                foreach (['S','M','T','W','T','F','S'] as $dow) echo '<div class="cal-cell cal-dow">'.$h($dow).'</div>';
                for ($i=0; $i<42; $i++) echo '<div class="cal-cell" data-cal-cell></div>';
                ?>
              </div>
            </div>
          </div>
        </div>

        <!-- Feature line -->
        <div class="mt-4 grid grid-cols-2 sm:grid-cols-3 gap-x-3 gap-y-2 text-sm text-gray-700">
          <div class="flex items-center gap-2"><i class="fa-solid fa-lock text-emerald-600"></i> SSL/TLS</div>
          <div class="flex items-center gap-2"><i class="fa-solid fa-rotate text-emerald-600"></i> Daily Backups</div>
          <div class="flex items-center gap-2"><i class="fa-solid fa-money-check-dollar text-emerald-600"></i> Local Payments</div>
        </div>

        <!-- Highlighted shortcut -->
        <div class="mt-4 text-sm">
          <span class="hint"><i class="fa-solid fa-keyboard" style="color:var(--brand)"></i> Shortcut: <span class="kbd">L</span> → logout</span>
        </div>
      </section>
    </div>

    <!-- Hidden form fallback -->
    <form id="logoutForm" action="<?= $h($logoutUrl) ?>" method="post" class="hidden"></form>
  </main>

  <script>
    /* ---------- Logout hotkey (L) ---------- */
    const logoutUrl = <?= json_encode($logoutUrl) ?>;
    function shouldHandleHotkey(e){
      const t = (e.target && e.target.tagName || '').toLowerCase();
      if (t === 'input' || t === 'textarea' || t === 'select' || e.isComposing) return false;
      if (e.altKey || e.ctrlKey || e.metaKey) return false;
      return true;
    }
    function tryLogout(){
      fetch(logoutUrl, { method:'POST', credentials:'include' })
        .then(() => { location.replace('/tenant/login'); })
        .catch(() => {
          const f = document.getElementById('logoutForm');
          if (f) { try { f.submit(); return; } catch(_){} }
          location.href = logoutUrl;
        });
    }
    addEventListener('keydown', e => { if (shouldHandleHotkey(e) && (e.key||'').toLowerCase()==='l'){ e.preventDefault(); tryLogout(); }}, {capture:true});
    addEventListener('keyup',   e => { if (shouldHandleHotkey(e) && (e.key||'').toLowerCase()==='l'){ e.preventDefault(); tryLogout(); }}, {capture:true});

    /* ---------- Digital clock ---------- */
    function pad(n){ return (n<10?'0':'')+n; }
    function updateClock(){
      const d  = new Date();
      const hh = d.getHours(), mm = d.getMinutes(), ss = d.getSeconds();
      const hh12 = ((hh + 11) % 12) + 1, ampm = hh >= 12 ? 'PM' : 'AM';
      const dateStr = d.toLocaleDateString(undefined, { weekday:'long', year:'numeric', month:'long', day:'numeric' });
      const c = document.getElementById('clock');
      const s = document.getElementById('clockSub');
      if (c) c.textContent = `${pad(hh12)}:${pad(mm)}:${pad(ss)} ${ampm}`;
      if (s) s.textContent = dateStr;
    }
    updateClock(); setInterval(updateClock, 1000);

    /* ---------- Simple dynamic calendar (current month) ---------- */
    function buildCalendar(){
      const now   = new Date();
      const year  = now.getFullYear();
      const month = now.getMonth();
      const first = new Date(year, month, 1);
      const startDow = first.getDay();
      const daysInMonth = new Date(year, month+1, 0).getDate();

      const t = document.getElementById('calTitle');
      if (t) t.textContent = now.toLocaleDateString(undefined, { month:'long', year:'numeric' });

      const cells = document.querySelectorAll('[data-cal-cell]');
      cells.forEach(c => { c.textContent = ''; c.classList.remove('cal-today'); });

      let idx = 0;
      for (let i=0;i<startDow;i++) { if (cells[idx]) cells[idx++].textContent = ''; }
      for (let d=1; d<=daysInMonth; d++){
        const cell = cells[idx++];
        if (!cell) break;
        cell.textContent = d;
        const today = (d===now.getDate());
        if (today) cell.classList.add('cal-today');
      }
    }
    buildCalendar();
  </script>
</body>
</html>