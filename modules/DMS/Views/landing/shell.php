<?php
$h = fn($s)=>htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
$brand = $brandColor ?? '#228B22';
$tint  = 'rgba(34,139,34,0.10)';
?>
<!doctype html>
<html lang="en" x-data x-init="$nextTick(()=>{ /* alpine hook ready */ })">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title><?= $h($title ?? 'DMS — Home') ?></title>

  <!-- Tailwind / FA / Alpine -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <script defer src="https://cdnjs.cloudflare.com/ajax/libs/alpinejs/3.14.1/cdn.min.js"></script>

  <style>
    :root { --brand: <?= $h($brand) ?>; }
    body { background: <?= $tint ?>; color:#0f172a; font-family: system-ui,-apple-system,"Segoe UI",Roboto,Inter,Arial,sans-serif; }
    .wrap { max-width: 1200px; }
    .badge { background: rgba(34,139,34,.10); color: var(--brand); border: 1px solid rgba(34,139,34,.22);
             border-radius: 9999px; padding: 6px 12px; font-size: 13px; display:inline-flex; align-items:center; gap:8px; }
    .kbd { padding:.18rem .42rem; background:#e5e7eb; border-radius:.375rem; font: 12px/1.2 ui-monospace,Menlo,monospace; }
    .info-card { background:#fff; border-radius:14px; border:1px solid rgba(0,0,0,.06); box-shadow:0 6px 16px rgba(0,0,0,.06); padding:14px; }
    .clock-text { font-weight:800; font-size:32px; letter-spacing:.02em; color:#0b1220; }
    .clock-sub { color:#64748b; font-size:12px; }
    .cal-grid { display:grid; grid-template-columns:repeat(7,1fr); gap:6px; margin-top:8px; }
    .cal-cell { text-align:center; padding:6px 0; border-radius:8px; font-size:12px; color:#0b1220; background:#f8fafc; }
    .cal-dow  { font-weight:700; color:#334155; background:transparent; }
    .cal-today{ background:rgba(34,139,34,.14); color:#064e3b; font-weight:800; border:1px solid rgba(34,139,34,.35); }
  </style>
</head>
<body>
  <main class="wrap mx-auto px-4 md:px-6 py-4 md:py-6">
    <?= $slot /* body content */ ?>
    <form id="logoutForm" action="<?= $h($logoutUrl ?? '/tenant/logout') ?>" method="post" class="hidden"></form>
  </main>

  <script>
    // ---- Logout hotkey (L) ----
    const logoutUrl = <?= json_encode($logoutUrl ?? '/tenant/logout') ?>;
    function shouldHandleHotkey(e){
      const t = (e.target && e.target.tagName || '').toLowerCase();
      if (t === 'input' || t === 'textarea' || t === 'select' || e.isComposing) return false;
      if (e.altKey || e.ctrlKey || e.metaKey) return false;
      return true;
    }
    function tryLogout(){
      fetch(logoutUrl, { method:'POST', credentials:'include' })
        .then(()=> location.replace('/tenant/login'))
        .catch(()=> {
          const f = document.getElementById('logoutForm');
          try { f.submit(); } catch(_) { location.href = logoutUrl; }
        });
    }
    addEventListener('keydown', e => { if (shouldHandleHotkey(e) && (e.key||'').toLowerCase()==='l'){ e.preventDefault(); tryLogout(); }}, {capture:true});
    addEventListener('keyup',   e => { if (shouldHandleHotkey(e) && (e.key||'').toLowerCase()==='l'){ e.preventDefault(); tryLogout(); }}, {capture:true});

    // ---- Clock ----
    function pad(n){ return (n<10?'0':'')+n; }
    function updateClock(){
      const d  = new Date();
      const hh = d.getHours(), mm = d.getMinutes(), ss = d.getSeconds();
      const hh12 = ((hh + 11) % 12) + 1, ampm = hh >= 12 ? 'PM' : 'AM';
      const dateStr = d.toLocaleDateString(undefined, { weekday:'long', year:'numeric', month:'long', day:'numeric' });
      const el = document.getElementById('clock'), sub = document.getElementById('clockSub');
      if (el)  el.textContent = `${pad(hh12)}:${pad(mm)}:${pad(ss)} ${ampm}`;
      if (sub) sub.textContent = dateStr;
    }
    updateClock(); setInterval(updateClock, 1000);

    // ---- Calendar (current month) ----
    function buildCalendar(){
      const now   = new Date();
      const year  = now.getFullYear();
      const month = now.getMonth();
      const first = new Date(year, month, 1);
      const startDow = first.getDay();
      const daysInMonth = new Date(year, month+1, 0).getDate();

      const title = document.getElementById('calTitle');
      if (title) title.textContent = now.toLocaleDateString(undefined, { month:'long', year:'numeric' });

      const cells = document.querySelectorAll('[data-cal-cell]');
      if (!cells.length) return;
      cells.forEach(c => { c.textContent = ''; c.classList.remove('cal-today'); });

      let idx = 0;
      for (let i=0;i<startDow;i++) { if (cells[idx]) cells[idx++].textContent = ''; }
      for (let d=1; d<=daysInMonth; d++){
        const cell = cells[idx++]; if (!cell) break;
        cell.textContent = d;
        if (d === now.getDate()) cell.classList.add('cal-today');
      }
    }
    buildCalendar();
  </script>
</body>
</html>