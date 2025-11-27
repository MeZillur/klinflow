<?php
declare(strict_types=1);

/*
==============================================================================
KlinFlow DMS Shell (Shared Layout)
- Header/topbar + language/theme toggle + user menu
- Loads project-level i18n (app/Support/i18n.php) if available and exposes translations
- Keeps KF.lookup, Choices wiring and Solid bundle inclusion intact
Place at: modules/DMS/Views/shared/layouts/shell.php
==============================================================================
*/

/* Ensure session started */
if (session_status() === PHP_SESSION_NONE) {
    @session_start();
}

/* -----------------------
 * SEGMENT 1: Baseline
 * --------------------- */
if (!isset($title))       $title = 'DMS';
if (!isset($module_base)) $module_base = '/apps/dms';
if (!isset($org) || !is_array($org)) $org = [];

$h = fn($s)=>htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');

$docRoot = rtrim((string)($_SERVER['DOCUMENT_ROOT'] ?? ''), '/');
$asset   = fn(string $rel) => '/assets/' . ltrim($rel, '/');
$urlIfExists = function(string $url) use ($docRoot): ?string {
  return ($docRoot && is_file($docRoot.$url)) ? $url : null;
};

/* -----------------------
 * SEGMENT 1.5: i18n helper (project-level)
 * --------------------- */
$i18nCandidates = [
    __DIR__ . '/../../../../../app/Support/i18n.php', // relative
    '/home/klinflow/htdocs/www.klinflow.com/app/Support/i18n.php', // fallback absolute (adjust if needed)
];
foreach ($i18nCandidates as $p) {
    if (is_file($p)) { require_once $p; break; }
}

// prepare a minimal client strings map for immediate client-side fallbacks
$__clientTexts = [];
if (function_exists('__')) {
    $__clientTexts = [
        'No matches' => __('No matches'),
        'Search'     => __('Search'),
        'EN'         => __('EN'),
        'BN'         => __('BN'),
    ];
}

/* -----------------------
 * SEGMENT 2: Branding
 * --------------------- */
$brandColor = '#228B22';
$logoLight = $urlIfExists($asset('brand/klinflow-wordmark-dark.svg'))
          ?: $urlIfExists($asset('brand/logo.svg'))
          ?: $urlIfExists($asset('brand/logo.png'))
          ?: 'data:image/svg+xml;utf8,'.rawurlencode('<svg xmlns="http://www.w3.org/2000/svg" width="120" height="28"><text x="0" y="20" font-family="Inter,Arial" font-size="18" fill="#0b1220">KlinFlow</text></svg>');
$logoDark  = $urlIfExists($asset('brand/klinflow-wordmark-light.svg'))
          ?: $urlIfExists($asset('brand/logo.svg'))
          ?: $urlIfExists($asset('brand/logo.png'))
          ?: 'data:image/svg+xml;utf8,'.rawurlencode('<svg xmlns="http://www.w3.org/2000/svg" width="120" height="28"><text x="0" y="20" font-family="Inter,Arial" font-size="18" fill="#e5e7eb">KlinFlow</text></svg>');

/* -----------------------
 * SEGMENT 3: Tenant + user
 * --------------------- */
$slug       = (string)($org['slug'] ?? ($_SESSION['tenant_org']['slug'] ?? ''));
$tenantDash = $slug ? "/t/{$slug}/dashboard" : '/';

$user      = (array)($_SESSION['tenant_user'] ?? []);
$uname     = trim((string)($user['name'] ?? 'User'));
$uusername = trim((string)($user['username'] ?? ''));

$orgId = (int)($org['id'] ?? ($_SESSION['tenant_org']['id'] ?? 0));
$uid   = (int)($user['id'] ?? 0);

$uavatar = (string)($user['avatar'] ?? ($user['avatar_path'] ?? ''));
if ($uavatar !== '') {
    if ($uavatar[0] !== '/' && !preg_match('#^https?://#i', $uavatar) && strncmp($uavatar, 'data:', 5) !== 0) {
        if (strpos($uavatar, '/') === false && $orgId && $uid) {
            $uavatar = "/uploads/avatars/{$orgId}/{$uid}/" . ltrim($uavatar, '/');
        } else {
            $uavatar = '/uploads/avatars/' . ltrim($uavatar, '/');
        }
    }
} else {
    if ($orgId && $uid) {
        foreach (['png','jpg','jpeg','webp'] as $ext) {
            $rel = "/uploads/avatars/{$orgId}/{$uid}/avatar.{$ext}";
            if ($docRoot && is_file($docRoot . $rel)) { $uavatar = $rel; break; }
        }
    }
}
$uinitial  = strtoupper(substr($uname !== '' ? $uname : ($uusername !== '' ? $uusername : 'U'), 0, 1));
$urole     = strtolower((string)($user['role'] ?? 'employee'));

/* -----------------------
 * SEGMENT 4: Content & locale
 * --------------------- */
$_content   = $_content ?? '';
$slot       = $slot     ?? $_content;
$currLocale = (string)($_SESSION['dms_locale'] ?? 'en');
?><!doctype html>
<html lang="<?= $h($currLocale === 'bn' ? 'bn' : 'en') ?>">
<head>
  <meta charset="utf-8"/>
  <meta name="viewport" content="width=device-width,initial-scale=1"/>
  <meta name="color-scheme" content="light dark"/>
  <meta name="kf-module-base" content="<?= $h($module_base) ?>"/>
  <title><?= $h($title) ?></title>

  <!-- Fonts + icons -->
  <link rel="preconnect" href="https://fonts.googleapis.com"/>
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin/>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet"/>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" crossorigin="anonymous"/>

  <!-- Core tokens + Tailwind CDN -->
  <?php $tokensCss = $urlIfExists($asset('styles/tokens.css')); if ($tokensCss): ?>
    <link rel="stylesheet" href="<?= $h($tokensCss) ?>"/>
  <?php endif; ?>
  <style>:root{--brand:<?= $h($brandColor) ?>}</style>
  <script>window.tailwind={config:{darkMode:'class',corePlugins:{preflight:false}}}</script>
  <script src="https://cdn.tailwindcss.com"></script>

  <!-- Choices (local-first) -->
  <?php
    $choicesCssLocal = $urlIfExists($asset('ui/choices/choices.min.css')) ?: $urlIfExists($asset('styles/choices/choices.min.css'));
    $choicesJsLocal  = $urlIfExists($asset('ui/choices/choices.min.js'))  ?: $urlIfExists($asset('js/choices/choices.min.js'));
  ?>
  <link rel="stylesheet" href="<?= $h($choicesCssLocal ?: 'https://cdn.jsdelivr.net/npm/choices.js@10.2.0/public/assets/styles/choices.min.css') ?>"/>
  <script defer src="<?= $h($choicesJsLocal ?: 'https://cdn.jsdelivr.net/npm/choices.js@10.2.0/public/assets/scripts/choices.min.js') ?>"></script>

  <!-- Shell-scoped styles (including refined flag rules) -->
  <style>
    html,body{height:100dvh;overflow:hidden}
    body{font-family:Inter,system-ui,-apple-system,Segoe UI,Roboto,Helvetica,Arial}
    [x-cloak]{display:none!important}

    *, .choices, .choices__inner, .choices__list, .choices__list--dropdown,
    .btn, button, input, select, textarea, .avatar, .menu, .card { border-radius:0!important }

    .btn-icon{padding:.5rem}
    .brand-logo{display:flex;align-items:center;height:1.75rem}
    .brand-logo img{display:block;height:1.75rem;width:auto}
    .avatar{width:2.25rem;height:2.25rem;background:var(--brand);color:#fff;display:flex;align-items:center;justify-content:center;font-weight:700;border-radius:9999px;overflow:hidden}
    .avatar img{display:block;width:100%;height:100%;object-fit:cover;border-radius:9999px}

    /* KF.lookup panel */
    .kf-suggest{position:absolute;left:0;right:0;margin-top:.25rem;border:1px solid #c6e6c6;background:#fff;
      box-shadow:0 6px 12px rgba(0,0,0,.06);max-height:14rem;overflow:auto;font-size:.9rem;opacity:0;transform:translateY(-2px);transition:opacity .12s,transform .12s;z-index:100}
    .kf-suggest.open{opacity:1;transform:translateY(0)}
    .kf-suggest .kf-item{padding:.45rem .75rem;cursor:pointer;border-bottom:1px solid #f0fdf4}
    .kf-suggest .kf-item:last-child{border-bottom:0}
    .kf-suggest .kf-item:hover,.kf-suggest .kf-item[aria-selected="true"]{background:#e9fbe9}
    .kf-title{color:#065f46;font-weight:600}
    .kf-sub{color:#475569;font-size:.75rem}

    .dark .bg-white{background:#111827!important}
    .dark .text-gray-900{color:#e5e7eb!important}
    .dark .border-gray-200{border-color:#374151!important}
    .dark .kf-suggest{background:#0f172a;border-color:#334155;box-shadow:0 8px 18px rgba(0,0,0,.6)}
    .dark .kf-suggest .kf-item{border-bottom:1px solid #1f2937}
    .dark .kf-suggest .kf-item:hover,.dark .kf-suggest .kf-item[aria-selected="true"]{background:#114f22}
    .dark .kf-title{color:#86efac}
    .dark .kf-sub{color:#cbd5e1}

    .choices__list--dropdown .choices__item--selectable.is-highlighted{background:#228B22!important;color:#fff!important}

    /* refined flag sizing + wrapper */
    .kf-flag { width:24px; height:16px; display:inline-block; vertical-align:middle; }
    .kf-flag svg { width:100%; height:100%; display:block; }
    .kf-flag-wrapper { display:inline-flex; align-items:center; gap:0.5rem; padding:0.25rem .4rem; border-radius:.5rem; cursor:pointer; }
    .kf-flag-label { font-size:.75rem; color:inherit; line-height:1; }
  </style>

  <!-- Early dark-mode boot -->
  <script>
    (function(){
      try{
        var saved=localStorage.getItem('theme');
        var prefers=matchMedia('(prefers-color-scheme: dark)').matches;
        var dark = saved ? (saved==='dark') : prefers;
        if (dark) document.documentElement.classList.add('dark');
        document.documentElement.setAttribute('data-theme', dark ? 'dark' : 'light');
      }catch(e){}
    })();
  </script>

  <!-- Alpine + KF boot + KF global (local-first) -->
  <?php
    $alpineLocal   = $urlIfExists($asset('js/alpine.min.js'));
    $kfBootLocal   = $urlIfExists($asset('js/kf-boot.js'));
    $kfGlobalLocal = $urlIfExists($asset('js/kf-global.js'));
  ?>
  <script defer src="<?= $h($alpineLocal ?: 'https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js') ?>"></script>
  <?php if ($kfBootLocal): ?>
    <script defer src="<?= $h($kfBootLocal) ?>"></script>
  <?php else: ?>
    <script>window.KF=window.KF||{};window.KF.moduleBase=<?= json_encode($module_base, JSON_UNESCAPED_SLASHES) ?>;</script>
  <?php endif; ?>
  <?php if ($kfGlobalLocal): ?>
    <script defer src="<?= $h($kfGlobalLocal) ?>"></script>
  <?php else: ?>
    <script>console.warn('kf-global.js missing; using shell fallback for KF.lookup');</script>
  <?php endif; ?>

  <!-- Expose immediate client fallback texts -->
  <?php if (!empty($__clientTexts)): ?>
    <script>
      window.KF = window.KF || {};
      window.KF.lang = Object.assign(window.KF.lang || {}, <?= json_encode($__clientTexts, JSON_UNESCAPED_UNICODE) ?>);
      window.KF.t = function(k){ return (window.KF.lang && window.KF.lang[k]) ? window.KF.lang[k] : k; };
    </script>
  <?php endif; ?>

  <!-- Choices init + locale setter -->
  <script>
    document.addEventListener('DOMContentLoaded', function(){
      if (!window.Choices) return;
      document.querySelectorAll('select[data-choices]:not([data-choices-ajax])').forEach(function(sel){
        if (sel.dataset.kfBound === '1') return;
        sel.dataset.kfBound = '1';
        sel.choices = new Choices(sel, {
          searchEnabled: sel.dataset.choicesSearch !== 'false',
          removeItemButton: sel.dataset.choicesRemoveitembutton === 'true',
          allowHTML: false,
          shouldSort: false,
          placeholder: true,
          itemSelectText: ''
        });
      });
    });

    async function dmsSetLocale(base, locale){
      try{
        var u = (String(base||'').replace(/\/$/,'') + '/i18n/set?locale=' + encodeURIComponent(locale));
        await fetch(u, {credentials:'same-origin'});
      }catch(e){}
      location.reload();
    }
    window.dmsSetLocale = dmsSetLocale;
  </script>
</head>

<body class="bg-white dark:bg-gray-900 text-gray-900 dark:text-gray-100"
      x-data="{dark:document.documentElement.classList.contains('dark'), loc: '<?= $h($currLocale) ?>'}"
      x-init="$nextTick(()=>{$store.notify && $store.notify.init('<?= $h($module_base) ?>')})">

  <div class="w-full h-full flex flex-col">
    <!-- Top bar -->
    <header class="h-16 flex items-center justify-between px-3 lg:px-4 border-b border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800">
      <!-- Left: brand -->
      <div class="flex items-center gap-3">
        <a href="<?= $h($tenantDash) ?>" class="brand-logo" aria-label="KlinFlow">
          <picture>
            <source srcset="<?= $h($logoDark) ?>" media="(prefers-color-scheme: dark)">
            <img src="<?= $h($logoLight) ?>" alt="KlinFlow" loading="eager">
          </picture>
        </a>
      </div>

      <!-- Right: language, theme, user -->
      <div class="flex items-center gap-3" x-data="{menu:false}">
        <!-- Language toggle (refined) -->
        <div class="relative" x-data>
          <button
            type="button"
            class="kf-flag-wrapper hover:bg-gray-100 dark:hover:bg-gray-700"
            :title="loc === 'en' ? '<?= $h(function_exists('__')?__('English'):'English') ?>' : '<?= $h(function_exists('__')?__('Bangla'):'Bangla') ?>'"
            @click="loc = (loc === 'en' ? 'bn' : 'en'); dmsSetLocale('<?= $h($module_base) ?>', loc)"
            aria-label="Toggle language"
          >
            <span x-show="loc === 'en'" x-cloak class="kf-flag" aria-hidden="true">
              <svg width="24" height="16" viewBox="0 0 24 14" xmlns="http://www.w3.org/2000/svg" preserveAspectRatio="xMidYMid meet" role="img" aria-hidden="true">
                <rect width="24" height="14" fill="#012169"/>
                <path d="M0 0 L24 14 M24 0 L0 14" stroke="#fff" stroke-width="2"/>
                <path d="M0 0 L24 14 M24 0 L0 14" stroke="#C8102E" stroke-width="1"/>
                <rect x="9" y="0" width="6" height="14" fill="#fff"/>
                <rect x="10.5" y="0" width="3" height="14" fill="#C8102E"/>
                <rect x="0" y="5" width="24" height="4" fill="#fff"/>
                <rect x="0" y="5.8" width="24" height="2.4" fill="#C8102E"/>
              </svg>
            </span>

            <span x-show="loc === 'bn'" x-cloak class="kf-flag" aria-hidden="true">
              <svg width="24" height="16" viewBox="0 0 21 14" xmlns="http://www.w3.org/2000/svg" preserveAspectRatio="xMidYMid meet" role="img" aria-hidden="true">
                <rect width="21" height="14" fill="#006a4e"/>
                <circle cx="10.5" cy="7" r="3.5" fill="#f42a41"/>
              </svg>
            </span>

            <span class="kf-flag-label"
                  x-text="loc === 'en' ? '<?= htmlspecialchars(function_exists('__')?__('EN'):'EN', ENT_QUOTES) ?>' : '<?= htmlspecialchars(function_exists('__')?__('BN'):'BN', ENT_QUOTES) ?>'"></span>
          </button>
        </div>

        <!-- Theme -->
        <button class="btn-icon hover:bg-gray-100 dark:hover:bg-gray-700"
                @click="dark=!dark; localStorage.setItem('theme',dark?'dark':'light'); document.documentElement.classList.toggle('dark',dark)"
                aria-label="Toggle theme">
          <i class="fa" :class="dark ? 'fa-moon' : 'fa-sun'"></i>
        </button>

        <!-- User menu -->
        <div class="relative" x-data="{menu:false}">
          <button @click="menu=!menu"
                  class="flex items-center gap-2 rounded-full pl-1 pr-2 py-1 hover:bg-gray-100 dark:hover:bg-gray-700"
                  aria-label="User menu">
            <?php if ($uavatar !== ''): ?>
              <img src="<?= $h($uavatar) ?>" alt="Avatar" class="w-9 h-9 rounded-full object-cover">
            <?php else: ?>
              <div class="avatar rounded-full"><?= $h($uinitial) ?></div>
            <?php endif; ?>
            <span class="max-w-[12rem] hidden sm:block text-sm font-medium truncate"><?= $h($uname) ?></span>
            <i class="fa fa-chevron-down text-gray-400 text-sm"></i>
          </button>

          <div x-show="menu" x-cloak x-transition.origin.top.right @click.outside="menu=false"
               class="absolute right-0 mt-2 w-56 bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-md shadow-lg overflow-hidden z-50">
            <div class="px-4 py-3 text-sm border-b border-gray-200 dark:border-gray-700">
              <div class="font-semibold truncate"><?= $h($uname) ?></div>
              <?php if ($uusername !== ''): ?>
                <div class="text-xs text-gray-500 truncate">@<?= $h($uusername) ?></div>
              <?php endif; ?>
            </div>

            <a href="/me" class="flex items-center gap-2 px-4 py-2 text-sm hover:bg-gray-50 dark:hover:bg-gray-800/60">
              <i class="fa-regular fa-user"></i><span><?= $h(function_exists('__')?__('My Profile'):'My Profile') ?></span>
            </a>

            <a href="/tenant/forgot" class="flex items-center gap-2 px-4 py-2 text-sm hover:bg-gray-50 dark:hover:bg-gray-800/60">
              <i class="fa-regular fa-circle-question"></i><span><?= $h(function_exists('__')?__('Forgot password'):'Forgot password') ?></span>
            </a>

            

            <div class="border-t border-gray-200 dark:border-gray-700 my-1"></div>
            <a href="<?= $h($tenantDash) ?>" class="flex items-center gap-2 px-4 py-2 text-sm hover:bg-gray-50 dark:hover:bg-gray-800/60">
              <i class="fa-solid fa-gauge"></i><span><?= $h(function_exists('__')?__('App dashboard'):'App dashboard') ?></span>
            </a>
            <a href="<?= $h(rtrim($module_base, '/').'/settings') ?>" class="flex items-center gap-2 px-4 py-2 text-sm hover:bg-gray-50 dark:hover:bg-gray-800/60">
              <i class="fa-solid fa-sliders"></i><span><?= $h(function_exists('__')?__('App settings'):'App settings') ?></span>
            </a>

            <div class="border-t border-gray-200 dark:border-gray-700 my-1"></div>
            <form method="post" action="/tenant/logout" class="block">
              <button type="submit" class="w-full flex items-center gap-2 px-4 py-2 text-left text-red-600 hover:bg-red-50 dark:hover:bg-red-900/30">
                <i class="fa-solid fa-right-from-bracket"></i><span><?= $h(function_exists('__')?__('Sign out'):'Sign out') ?></span>
              </button>
            </form>
          </div>
        </div>
      </div>
    </header>

    <!-- Page content -->
    <main class="flex-1 overflow-y-auto p-4 lg:p-6"><?= $slot ?></main>
  </div>

  <!-- ESC → landing shortcut -->
  <script>
    (function(){
      var landing = <?= json_encode(rtrim((string)$module_base, '/'), JSON_UNESCAPED_SLASHES) ?> || '/apps/dms';
      document.addEventListener('keydown', function(e){
        var key = (e.key || '').toLowerCase();
        if (key !== 'escape' && key !== 'esc') return;
        var tag = (e.target && e.target.tagName || '').toLowerCase();
        if (tag === 'input' || tag === 'textarea' || (e.target && e.target.isContentEditable)) return;
        if (e.altKey || e.ctrlKey || e.metaKey) return;
        e.preventDefault();
        try { if (location.pathname.replace(/\/+$/,'') === landing.replace(/\/+$/,'')) return; } catch(_){}
        window.location.href = landing;
      });
    })();
  </script>

  <!-- Minimal KF.lookup fallback (if KF.lookup.bind missing) -->
  <script>
    (function(){
      window.KF = window.KF || {};
      KF.lookup = KF.lookup || {};
      if (typeof KF.lookup.bind === 'function') return;

      const BASE = (document.querySelector('meta[name="kf-module-base"]')?.content || '').replace(/\/$/,'') || (window.KF.moduleBase||'');
      const make = (tag, cls, html)=>{ const el=document.createElement(tag); if(cls) el.className=cls; if(html!=null) el.innerHTML=html; return el; };

      function attach({el, entity, onPick}){
        el.setAttribute('autocomplete','off');
        el.dataset.kfBound = '1';
        el.style.position = 'relative';
        const wrap = el.closest('.relative') || (function(){ const r=make('div','relative'); el.parentNode.insertBefore(r,el); r.appendChild(el); return r; })();
        const list = make('div','kf-suggest'); wrap.appendChild(list);

        let idx=-1, items=[], lastQ='', inflight=0;
        function close(){ list.classList.remove('open'); list.innerHTML=''; idx=-1; items=[]; }
        function open(){ if(list.children.length){ list.classList.add('open'); } }

        async function search(q){
          const qq=q.trim(); if (!qq){ close(); return; }
          if (qq===lastQ) { open(); return; }
          lastQ = qq; const my = ++inflight;
          try{
            const url = BASE + '/api/lookup/' + encodeURIComponent(entity) + '?q=' + encodeURIComponent(qq) + '&limit=20';
            const r = await fetch(url, {headers:{Accept:'application/json'}});
            const js = r.ok ? await r.json() : [];
            const arr = Array.isArray(js?.items) ? js.items : (Array.isArray(js)?js:[]);
            if (inflight!==my) return;
            list.innerHTML='';
            items = arr.slice(0,50);
            if (!items.length){
              var emptyText = (window.KF && typeof window.KF.t === 'function') ? window.KF.t('No matches') : 'No matches';
              list.appendChild(make('div','kf-item text-slate-400', emptyText)); open(); return;
            }
            items.forEach((it,i)=>{
              const row = make('div','kf-item');
              row.innerHTML = '<div class="kf-title"></div><div class="kf-sub"></div>';
              const title = it.label || it.name || it.code || ('ID '+(it.id??''));
              const sub   = [it.code, it.sku, it.barcode].filter(Boolean).join(' · ');
              row.querySelector('.kf-title').textContent = title;
              row.querySelector('.kf-sub').textContent = sub;
              row.addEventListener('mouseenter',()=>highlight(i));
              row.addEventListener('mousedown',(ev)=>{ ev.preventDefault(); pick(i); });
              list.appendChild(row);
            });
            open();
          }catch{}
        }
        function highlight(i){
          const rows=[...list.querySelectorAll('.kf-item')];
          rows.forEach(r=>r.removeAttribute('aria-selected'));
          const r = rows[i]; if (!r) return;
          r.setAttribute('aria-selected','true'); idx=i;
        }
        function pick(i){
          const it = items[i]; if(!it) return;
          el.value = it.label || it.name || it.code || '';
          close();
          if (typeof onPick === 'function') onPick(it);
          el.dispatchEvent(new Event('change', {bubbles:true}));
        }
        function onKey(e){
          if(!list.classList.contains('open')) return;
          const rows=[...list.querySelectorAll('.kf-item')].filter(r=>!r.classList.contains('text-slate-400'));
          if(e.key==='ArrowDown'){ e.preventDefault(); highlight(Math.min((idx+1), rows.length-1)); }
          else if(e.key==='ArrowUp'){ e.preventDefault(); highlight(Math.max((idx-1), 0)); }
          else if(e.key==='Enter'){ e.preventDefault(); if(idx>=0) pick(idx); }
          else if(e.key==='Escape'){ close(); }
        }

        el.addEventListener('input', (e)=>search(e.target.value||''));
        el.addEventListener('keydown', onKey);
        document.addEventListener('click', (e)=>{ if(!wrap.contains(e.target)) close(); });
      }

      KF.lookup.bind = function(opts){
        if (!opts || !opts.el || !opts.entity) return;
        attach(opts);
      };
    })();
  </script>

  <!-- Bind [data-kf-lookup] inputs -->
  <script>
    (function(){
      function autoWire(){
        if (!window.KF || !KF.lookup || !KF.lookup.bind) return;
        document.querySelectorAll('[data-kf-lookup]').forEach(function(inp){
          if (inp.dataset.kfBound === '1') return;
          var entity = String(inp.dataset.kfLookup||'').toLowerCase(); if (!entity) return;
          var idEl    = inp.dataset.kfTargetId    ? document.querySelector(inp.dataset.kfTargetId)    : null;
          var nameEl  = inp.dataset.kfTargetName  ? document.querySelector(inp.dataset.kfTargetName)  : null;
          var codeEl  = inp.dataset.kfTargetCode  ? document.querySelector(inp.dataset.kfTargetCode)  : null;
          var priceEl = inp.dataset.kfTargetPrice ? (inp.closest('tr')?.querySelector(inp.dataset.kfTargetPrice) || document.querySelector(inp.dataset.kfTargetPrice)) : null;

          KF.lookup.bind({
            el: inp,
            entity: entity,
            onPick: function(r){
              if (idEl)   idEl.value   = r.id || '';
              if (nameEl) nameEl.value = r.name || r.label || '';
              if (codeEl) codeEl.value = r.code || r.sku || '';
              if (priceEl && r.price!=null) priceEl.value = r.price;
              inp.value = r.label || r.name || r.code || '';
              inp.dispatchEvent(new Event('change', {bubbles:true}));
            }
          });
        });
      }
      if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', autoWire);
      else autoWire();
      window.KF = window.KF || {}; var prevRescan = KF.rescan;
      KF.rescan = function(root){ if (prevRescan) prevRescan(root); autoWire(); };
    })();
  </script>

  <!-- Choices ajax wiring -->
  <script>
    (function(){
      function debounce(fn,ms){let t;return function(){clearTimeout(t);t=setTimeout(()=>fn.apply(this,arguments),ms);};}
      function bindChoicesAjax(root){
        (root||document).querySelectorAll('select[data-choices][data-choices-ajax]:not([data-kf-ajax])').forEach(function(sel){
          sel.setAttribute('data-kf-ajax','1');
          var url       = sel.dataset.choicesAjax || '';
          var valueKey  = sel.dataset.valueKey  || 'id';
          var labelKey  = sel.dataset.labelKey  || 'label';

          if (sel.choices && sel.choices.destroy) try { sel.choices.destroy(); } catch(e){}
          var inst = new Choices(sel, {searchEnabled:true,placeholder:true,itemSelectText:'',allowHTML:false,shouldSort:false});
          sel.choices = inst;

          var input = sel.parentElement.querySelector('.choices__input--cloned');
          if (!input || !url) return;

          var doFetch = debounce(function(q){
            var full = url + (url.indexOf('?')>-1 ? '&' : '?') + 'q=' + encodeURIComponent(q||'');
            fetch(full, {headers:{Accept:'application/json'}})
              .then(function(r){ return r.ok ? r.json() : []; })
              .then(function(js){
                var arr = Array.isArray(js) ? js : (Array.isArray(js.items) ? js.items : []);
                var mapped = arr.map(function(r){
                  var label = (r[labelKey] || r.name || r.code || ('ID-'+r[valueKey]));
                  return { value:r[valueKey], label:label, customProperties:{ raw:r } };
                });
                inst.setChoices(mapped,'value','label',true);
              })
              .catch(function(){ inst.clearChoices(); });
          }, 200);

          input.addEventListener('input', function(e){ doFetch(e.target.value||''); });
        });
      }
      if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', function(){ bindChoicesAjax(document); });
      else bindChoicesAjax(document);
      window.KF = window.KF || {}; var prev = KF.rescan;
      KF.rescan = function(root){ if (prev) prev(root); bindChoicesAjax(root||document); };
    })();
  </script>

  <!-- SOLID: include built bundle (manifest-based) -->
  <?php
    $assetHelper = __DIR__ . '/../../../../../app/Support/asset_helper.php';
    if (is_file($assetHelper)) {
      require_once $assetHelper;
      echo asset_css_tag('kf-solid');
      echo asset_script_tag('kf-solid');
    } else {
      $viteDev = getenv('KF_DEV_VITE_SERVER') ?: '';
      if ($viteDev) {
        echo '<script type="module" src="' . htmlspecialchars(rtrim($viteDev, '/').'/src/entry-client.tsx', ENT_QUOTES) . '"></script>';
      } else {
        echo '<!-- kf-solid build not found; run `npm run build` or set KF_DEV_VITE_SERVER -->';
      }
    }
  ?>

  <!-- Client: load full translations into window.KF.lang -->
  <script>
    (function(){
      try {
        var base = document.querySelector('meta[name="kf-module-base"]')?.content || '';
        var u = (base.replace(/\/+$/,'') || '') + '/i18n/strings';
        fetch(u, { credentials: 'same-origin' })
          .then(function(r){ if(!r.ok) throw new Error('i18n fetch failed'); return r.json(); })
          .then(function(js){
            window.KF = window.KF || {};
            window.KF.lang = Object.assign(window.KF.lang || {}, js.strings || {});
            window.KF.locale = js.locale || (window.KF.locale || 'en');
          }).catch(function(){ /* silent */ });
      } catch(e){}
    })();
  </script>
</body>
</html>