<?php
declare(strict_types=1);

/* ───────────────────────────────────────────────────────────────
   KlinFlow BizFlow Shell — HEADER ONLY (no sidenav)
   - Normalizes $module_base → /t/{slug}/apps/bizflow when slug is known
   - Brand color #228B22
   ─────────────────────────────────────────────────────────────── */

if (!isset($title))       $title = 'BizFlow';
if (!isset($module_base)) $module_base = '/apps/bizflow';
if (!isset($org) || !is_array($org)) $org = [];

$h = fn($s)=>htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');

/* -------- helpers for local assets -------- */
$docRoot = rtrim((string)($_SERVER['DOCUMENT_ROOT'] ?? ''), '/');
$asset   = fn(string $rel) => '/assets/' . ltrim($rel, '/');
$urlIfExists = function(string $url) use ($docRoot): ?string {
  return ($docRoot && is_file($docRoot.$url)) ? $url : null;
};

/* -------- tenant/paths (FIX: normalize module_base) -------- */
$slug = (string)($org['slug'] ?? ($_SESSION['tenant_org']['slug'] ?? ''));

$module_base = '/'.ltrim((string)$module_base,'/');         // ensure leading slash
$module_base = rtrim($module_base, '/');                    // no trailing slash
if ($slug !== '' && preg_match('#^/apps/bizflow(?:$|/)#', $module_base)) {
  $module_base = "/t/{$slug}/apps/bizflow";                 // tenant-aware base
}
$tenantDash = $module_base;                                 // logo/link target

/* -------- branding -------- */
$brandColor = '#228B22';
$logoLight  = $urlIfExists($asset('brand/klinflow-wordmark-dark.svg'))
           ?: $urlIfExists($asset('brand/logo.svg'))
           ?: $urlIfExists($asset('brand/logo.png'))
           ?: 'data:image/svg+xml;utf8,'.rawurlencode('<svg xmlns="http://www.w3.org/2000/svg" width="120" height="28"><text x="0" y="20" font-family="Inter,Arial" font-size="18" fill="#0b1220">KlinFlow</text></svg>');
$logoDark   = $urlIfExists($asset('brand/klinflow-wordmark-light.svg'))
           ?: $urlIfExists($asset('brand/logo.svg'))
           ?: $urlIfExists($asset('brand/logo.png'))
           ?: 'data:image/svg+xml;utf8,'.rawurlencode('<svg xmlns="http://www.w3.org/2000/svg" width="120" height="28"><text x="0" y="20" font-family="Inter,Arial" font-size="18" fill="#e5e7eb">KlinFlow</text></svg>');

/* -------- session/user -------- */
/* -------- session/user -------- */
$user      = (array)($_SESSION['tenant_user'] ?? []);
$uname     = trim((string)($user['name'] ?? 'User'));
$uusername = trim((string)($user['username'] ?? ''));
$uinitial  = strtoupper(substr($uname !== '' ? $uname : ($uusername !== '' ? $uusername : 'U'), 0, 1));
$urole     = strtolower((string)($user['role'] ?? 'employee'));

/**
 * Avatar resolution rules
 *  1) Try session field: avatar / avatar_path (url or relative)
 *  2) If empty or file missing → look in /uploads/avatars/{org_id}/{user_id}/*
 */
$orgId  = (int)($org['id'] ?? ($_SESSION['tenant_org']['id'] ?? 0));
$userId = (int)($user['id'] ?? 0);

$uavatarRaw = (string)($user['avatar'] ?? ($user['avatar_path'] ?? ''));
$uavatar    = trim($uavatarRaw);

// Normalise simple stored values
if ($uavatar !== '') {
    // remote URL (CDN etc.) → use as–is
    if (stripos($uavatar, 'http://') === 0 || stripos($uavatar, 'https://') === 0) {
        // ok
    } else {
        // ensure leading slash + uploads root
        if ($uavatar[0] !== '/') {
            if (strpos($uavatar, 'uploads/avatars') === 0) {
                $uavatar = '/' . $uavatar;
            } else {
                $uavatar = '/uploads/avatars/' . $uavatar;
            }
        }
    }
    // If file does not exist on disk, treat as empty
    if ($docRoot && !is_file($docRoot . $uavatar)) {
        $uavatar = '';
    }
}

/**
 * Fallback: auto-detect from /uploads/avatars/{org_id}/{user_id}
 * This matches the folder you showed:  /public/uploads/avatars/20/19/...
 */
if ($uavatar === '' && $docRoot && $orgId > 0 && $userId > 0) {
    $dirFs = $docRoot . '/uploads/avatars/' . $orgId . '/' . $userId;
    if (is_dir($dirFs)) {
        // accept common image extensions
        $candidates = glob($dirFs . '/*.{png,jpg,jpeg,webp,svg}', GLOB_BRACE) ?: [];
        if (!empty($candidates)) {
            // just take the first match
            $fs  = $candidates[0];
            $rel = substr($fs, strlen($docRoot));   // → /uploads/avatars/20/19/file.png
            $uavatar = $rel ?: '';
        }
    }
}

/* -------- content/locale -------- */
$_content   = $_content ?? '';
$slot       = $slot     ?? $_content;
$currLocale = (string)($_SESSION['biz_locale'] ?? 'en');
?>
<!doctype html>
<html lang="<?= $h($currLocale === 'bn' ? 'bn' : 'en') ?>">
<head>
  <!-- Head meta -->
  <meta charset="utf-8"/>
  <meta name="viewport" content="width=device-width,initial-scale=1"/>
  <meta name="color-scheme" content="light dark"/>
  <meta name="kf-module-base" content="<?= $h($module_base) ?>"/>
  <title><?= $h($title) ?></title>

  <!-- Fonts & icons -->
  <link rel="preconnect" href="https://fonts.googleapis.com"/>
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin/>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet"/>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" crossorigin="anonymous"/>

  <!-- Tokens + Tailwind (preflight off; we keep app CSS control) -->
  <?php if ($p=$urlIfExists($asset('styles/tokens.css'))): ?>
    <link rel="stylesheet" href="<?= $h($p) ?>"/>
  <?php endif; ?>
  <style>:root{--brand:<?= $h($brandColor) ?>}</style>
  <script>window.tailwind={config:{darkMode:'class',corePlugins:{preflight:false}}}</script>
  <script src="https://cdn.tailwindcss.com"></script>

  <!-- Choices (local first, then CDN) -->
  <?php
    $choicesCssLocal = $urlIfExists($asset('ui/choices/choices.min.css')) ?: $urlIfExists($asset('styles/choices/choices.min.css'));
    $choicesJsLocal  = $urlIfExists($asset('ui/choices/choices.min.js'))  ?: $urlIfExists($asset('js/choices/choices.min.js'));
  ?>
  <link rel="stylesheet" href="<?= $h($choicesCssLocal ?: 'https://cdn.jsdelivr.net/npm/choices.js@10.2.0/public/assets/styles/choices.min.css') ?>"/>
  <script defer src="<?= $h($choicesJsLocal ?: 'https://cdn.jsdelivr.net/npm/choices.js@10.2.0/public/assets/scripts/choices.min.js') ?>"></script>

  <!-- Shell styles (square look + dark tweaks) -->
  <style>
    html,body{height:100dvh}
    body{overflow:hidden;font-family:Inter,system-ui,-apple-system,Segoe UI,Roboto,Helvetica,Arial}
    [x-cloak]{display:none!important}

    *, .choices, .choices__inner, .choices__list, .choices__list--dropdown,
    .btn, button, input, select, textarea, .avatar, .menu, .card { border-radius:0!important }

    .brand-logo{display:flex;align-items:center;height:1.75rem}
    .brand-logo img{display:block;height:1.75rem;width:auto}
    .avatar{width:2.25rem;height:2.25rem;background:var(--brand);color:#fff;display:flex;align-items:center;justify-content:center;font-weight:700;border-radius:9999px}
    .pill{font-size:.72rem;padding:.25rem .5rem;border-radius:.6rem;border:1px solid #e5e7eb}
    .dark .pill{border-color:#374151}
    .choices__list--dropdown .choices__item--selectable.is-highlighted{background:#228B22!important;color:#fff!important}

    /* KF.lookup panel (same behavior as DMS/POS) */
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

  <!-- Alpine + KF boot + KF global -->
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

  <!-- Choices init for non-ajax selects -->
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
    async function bizSetLocale(base, locale){
      try{
        var u = (String(base||'').replace(/\/$/,'') + '/i18n/set?locale=' + encodeURIComponent(locale));
        await fetch(u, {credentials:'same-origin'});
      }catch(e){}
      location.reload();
    }
    // alias so copied POS views still work
    window.bizSetLocale = bizSetLocale;
    window.posSetLocale = bizSetLocale;
  </script>
</head>

<body class="bg-white dark:bg-gray-900 text-gray-900 dark:text-gray-100"
      x-data="{
        dark:document.documentElement.classList.contains('dark'),
        loc:'<?= $currLocale==='bn' ? 'bn' : 'en' ?>',
        toggleTheme(){
          this.dark=!this.dark;
          localStorage.setItem('theme', this.dark?'dark':'light');
          document.documentElement.classList.toggle('dark', this.dark);
          document.documentElement.setAttribute('data-theme', this.dark ? 'dark' : 'light');
        },
        toggleLocale(){
          const next = (this.loc==='en'?'bn':'en');
          this.loc=next;
          bizSetLocale('<?= $h($module_base) ?>', next);
        }
      }"
      x-init="$nextTick(()=>{$store && $store.notify && $store.notify.init('<?= $h($module_base) ?>')})">

  <!-- HEADER ONLY -->
  <header class="h-16 sticky top-0 z-20 bg-white dark:bg-gray-800 border-b border-gray-200 dark:border-gray-700 flex items-center justify-between px-3 lg:px-4">
    <div class="flex items-center gap-3">
      <a href="<?= $h($tenantDash) ?>" class="brand-logo" aria-label="KlinFlow">
        <picture>
          <source srcset="<?= $h($logoDark) ?>" media="(prefers-color-scheme: dark)">
          <img src="<?= $h($logoLight) ?>" alt="KlinFlow" loading="eager">
        </picture>
      </a>
    </div>

    <div class="flex items-center gap-2 sm:gap-3">
      <!-- Language toggle -->
      <button class="px-2 py-2 rounded hover:bg-gray-100 dark:hover:bg-gray-700 flex items-center gap-2"
              @click="toggleLocale()" aria-label="Toggle language">
        <span class="pill" x-text="loc==='bn' ? 'BN' : 'EN'"></span>
      </button>

      <!-- Theme toggle -->
      <button class="px-2 py-2 rounded hover:bg-gray-100 dark:hover:bg-gray-700"
              @click="toggleTheme()" aria-label="Toggle theme">
        <i class="fa" :class="dark ? 'fa-moon' : 'fa-sun'"></i>
      </button>

      <!-- User dropdown -->
      <div class="relative" x-data="{open:false}">
        <button @click="open=!open"
                class="flex items-center gap-2 rounded-full pl-1 pr-2 py-1 hover:bg-gray-100 dark:hover:bg-gray-700"
                aria-label="User menu">
          <?php if ($uavatar !== ''): ?>
            <img src="<?= $h($uavatar) ?>" alt="Avatar" class="w-9 h-9 rounded-full object-cover">
          <?php else: ?>
            <div class="avatar"><?= $h($uinitial) ?></div>
          <?php endif; ?>
          <span class="max-w-[12rem] hidden sm:block text-sm font-medium truncate"><?= $h($uname) ?></span>
          <i class="fa fa-chevron-down text-gray-400 text-sm"></i>
        </button>

        <div x-show="open" x-cloak x-transition.origin.top.right
             @click.outside="open=false"
             class="absolute right-0 mt-2 w-56 bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-md shadow-lg overflow-hidden z-50">
          <div class="px-4 py-3 text-sm border-b border-gray-200 dark:border-gray-700">
            <div class="font-semibold truncate"><?= $h($uname) ?></div>
            <?php if ($uusername !== ''): ?><div class="text-xs text-gray-500 truncate">@<?= $h($uusername) ?></div><?php endif; ?>
          </div>
          <a href="/me" class="flex items-center gap-2 px-4 py-2 text-sm hover:bg-gray-50 dark:hover:bg-gray-800/60">
            <i class="fa-regular fa-user"></i><span>My Profile</span>
          </a>
          
          <div class="border-t border-gray-200 dark:border-gray-700 my-1"></div>
          <a href="<?= $h($tenantDash) ?>" class="flex items-center gap-2 px-4 py-2 text-sm hover:bg-gray-50 dark:hover:bg-gray-800/60">
            <i class="fa-solid fa-gauge"></i><span>App dashboard</span>
          </a>
          <a href="<?= $h(rtrim($module_base, '/').'/settings') ?>" class="flex items-center gap-2 px-4 py-2 text-sm hover:bg-gray-50 dark:hover:bg-gray-800/60">
            <i class="fa-solid fa-sliders"></i><span>App settings</span>
          </a>
          <div class="border-t border-gray-200 dark:border-gray-700 my-1"></div>
          <form method="post" action="/tenant/logout" class="block">
            <button type="submit" class="w-full flex items-center gap-2 px-4 py-2 text-left text-red-600 hover:bg-red-50 dark:hover:bg-red-900/30">
              <i class="fa-solid fa-right-from-bracket"></i><span>Sign out</span>
            </button>
          </form>
        </div>
      </div>
    </div>
  </header>

  <!-- MAIN -->
  <main class="h-[calc(100dvh-4rem)] overflow-y-auto p-4 lg:p-6">
    <?php
    // 1) Preferred: controller set $_content to an absolute file path
    if (isset($_content) && is_string($_content) && $_content !== '' && is_file($_content)) {
        require $_content;

    // 2) Some controllers pass $slot
    } elseif (isset($slot) && is_string($slot) && $slot !== '') {
        if (is_file($slot)) {
            require $slot; // it's a path
        } else {
            // assume $slot already contains rendered HTML
            echo $slot;
        }

    // 3) Fallback: a view key may be provided
    } elseif (isset($view) && is_string($view) && $view !== '') {
        $md = isset($ctx['module_dir']) ? rtrim((string)$ctx['module_dir'], '/') : dirname(__DIR__, 1);
        $vf = $md . '/Views/' . ltrim($view, '/');
        if (!str_ends_with($vf, '.php')) $vf .= '.php';
        if (is_file($vf)) {
            require $vf;
        } else {
            echo '<div class="text-red-600">View content missing (key not found): '
               . htmlspecialchars($view, ENT_QUOTES, 'UTF-8') . '</div>';
        }

    // 4) Last resort: show a helpful message (more detail in debug)
    } else {
        echo '<div class="text-red-600">View content missing.</div>';
        if (!empty($_GET['_debug']) && $_GET['_debug'] === '1') {
            echo '<pre style="margin-top:12px;padding:12px;background:#0b1220;color:#e5e7eb;white-space:pre-wrap">';
            echo "DEBUG view resolver\n\n";
            echo '$module_dir: ' . htmlspecialchars($ctx['module_dir'] ?? '(none)', ENT_QUOTES, 'UTF-8') . "\n";
            echo '$_content: '   . var_export($_content ?? null, true) . "\n";
            echo '$slot: '       . (isset($slot) ? (is_string($slot) ? (is_file($slot) ? "[file] $slot" : "[html/string]") : gettype($slot)) : '(unset)') . "\n";
            echo '$view: '       . var_export($view ?? null, true) . "\n";
            echo '</pre>';
        }
    }
    ?>
  </main>

  <!-- SEGMENT M0: Minimal KF.lookup fallback (only if KF.lookup.bind missing) -->
<script>
    (function(){
      window.KF = window.KF || {};
      KF.lookup = KF.lookup || {};
      if (typeof KF.lookup.bind === 'function') return;

      const BASE = (document.querySelector('meta[name="kf-module-base"]')?.content || '').replace(/\/$/,'') || (window.KF.moduleBase||'');
      const make = (t,c,h)=>{const e=document.createElement(t); if(c) e.className=c; if(h!=null) e.innerHTML=h; return e;};

      function attach({el, entity, onPick}){
        el.setAttribute('autocomplete','off');
        el.dataset.kfBound = '1';
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
              list.appendChild(make('div','kf-item text-slate-400','No matches')); open(); return;
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

  <!-- SEGMENT M: Auto-bind all [data-kf-lookup] -->
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

  <!-- SEGMENT N: Choices AJAX wiring -->
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

  <!-- SEGMENT P: BizFlow – ESC → landing (inner pages) -->
  <script>
    (function(){
      try {
        // Get module base from meta or KF.moduleBase → e.g. "/t/slug/apps/bizflow"
        var base = (document.querySelector('meta[name="kf-module-base"]')?.content || (window.KF && KF.moduleBase) || '').replace(/\/+$/,'');
        if (!base) return;
        var homeUrl = base; // BizFlow landing

        function norm(p){ return (p || '').replace(/\/+$/,''); }

        document.addEventListener('keydown', function(e){
          var tag = (e.target && e.target.tagName || '').toLowerCase();
          if (tag === 'input' || tag === 'textarea' || (e.target && e.target.isContentEditable)) return;
          if (e.altKey || e.ctrlKey || e.metaKey) return;

          var key = (e.key || '').toLowerCase();
          if (key === 'escape') {
            // If already on landing, don't reload
            if (norm(window.location.pathname) === norm(homeUrl)) {
              return;
            }
            e.preventDefault();
            window.location.href = homeUrl;
          }
        });
      } catch(e){}
    })();
  </script>
</body>
</html>