<?php
declare(strict_types=1);

/* ───────────────────────────────────────────────────────────────
   KlinFlow HotelFlow Shell — HEADER ONLY (no sidenav)
   - Normalizes $module_base → /t/{slug}/apps/hotelflow when slug is known
   - Brand color #228B22
   - POS-parity header + main view resolver + debug fallback
   ─────────────────────────────────────────────────────────────── */

if (!isset($title))       $title = 'HotelFlow';
if (!isset($module_base)) $module_base = '/apps/hotelflow';
if (!isset($org) || !is_array($org)) $org = [];

$h = fn($s)=>htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');

/* -------- helpers for local assets -------- */
$docRoot = rtrim((string)($_SERVER['DOCUMENT_ROOT'] ?? ''), '/');
$asset   = fn(string $rel) => '/assets/' . ltrim($rel, '/');
$urlIfExists = function(string $url) use ($docRoot): ?string {
  return ($docRoot && is_file($docRoot.$url)) ? $url : null;
};

/* -------- tenant/paths (normalize module_base) -------- */
$slug = (string)($org['slug'] ?? ($_SESSION['tenant_org']['slug'] ?? ''));

$module_base = '/'.ltrim((string)$module_base,'/');         // ensure leading slash
$module_base = rtrim($module_base, '/');                    // no trailing slash
if ($slug !== '' && preg_match('#^/apps/hotelflow(?:$|/)#', $module_base)) {
  $module_base = "/t/{$slug}/apps/hotelflow";               // tenant-aware base
}
$tenantDash = $module_base;                                 // landing / dashboard

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

/* -------- session/user (with robust avatar path) -------- */
$user      = (array)($_SESSION['tenant_user'] ?? []);
$uname     = trim((string)($user['name'] ?? 'User'));
$uusername = trim((string)($user['username'] ?? ''));

/**
 * Avatar resolution rules:
 * - If full http/https URL → use as-is
 * - If starts with /public/uploads/... → strip /public
 * - If any other absolute path starting with / → use as-is
 * - If relative like "18/17/avatar.png" → /uploads/avatars/{path}
 */
$uavatarRaw = (string)($user['avatar_path'] ?? ($user['avatar'] ?? ''));
$uavatar    = '';
if ($uavatarRaw !== '') {
    if (strpos($uavatarRaw, 'http://') === 0 || strpos($uavatarRaw, 'https://') === 0) {
        // full URL
        $uavatar = $uavatarRaw;
    } else {
        if ($uavatarRaw[0] === '/') {
            // absolute path from DB (fix /public prefix)
            $uavatar = preg_replace('#^/public/#', '/', $uavatarRaw);
        } else {
            // stored as relative org/user path "18/17/avatar.png"
            $uavatar = '/uploads/avatars/' . ltrim($uavatarRaw, '/');
        }
    }
}

$uinitial  = strtoupper(substr($uname !== '' ? $uname : ($uusername !== '' ? $uusername : 'U'), 0, 1));
$urole     = strtolower((string)($user['role'] ?? 'employee'));

/* -------- content/locale -------- */
$_content   = $_content ?? '';
$slot       = $slot     ?? $_content;
$currLocale = (string)($_SESSION['hms_locale'] ?? 'en');
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

  <!-- Tokens -->
  <?php if ($p=$urlIfExists($asset('styles/tokens.css'))): ?>
    <link rel="stylesheet" href="<?= $h($p) ?>"/>
  <?php endif; ?>
  <style>:root{--brand:<?= $h($brandColor) ?>}</style>

  <!-- Tailwind (no preflight to keep app CSS control) -->
  <script>window.tailwind={config:{darkMode:'class',corePlugins:{preflight:false}}}</script>
  <script src="https://cdn.tailwindcss.com"></script>

  <!-- Lookup CSS (KF.lookup) -->
  <?php if ($lk=$urlIfExists($asset('ui/autocomplete/kf-lookup.css'))): ?>
    <link rel="stylesheet" href="<?= $h($lk) ?>"/>
  <?php endif; ?>

  <!-- Choices (local first, then CDN) -->
  <?php
    $choicesCssLocal = $urlIfExists($asset('ui/choices/choices.min.css')) ?: $urlIfExists($asset('styles/choices/choices.min.css'));
    $choicesJsLocal  = $urlIfExists($asset('ui/choices/choices.min.js'))  ?: $urlIfExists($asset('js/choices/choices.min.js'));
  ?>
  <link rel="stylesheet" href="<?= $h($choicesCssLocal ?: 'https://cdn.jsdelivr.net/npm/choices.js@10.2.0/public/assets/styles/choices.min.css') ?>"/>
  <script defer src="<?= $h($choicesJsLocal ?: 'https://cdn.jsdelivr.net/npm/choices.js@10.2.0/public/assets/scripts/choices.min.js') ?>"></script>

  <!-- Shell styles -->
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
    .dark .bg-white{background:#111827!important}
    .dark .text-gray-900{color:#e5e7eb!important}
    .dark .border-gray-200{border-color:#374151!important}
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
    <script>console.warn('kf-global.js missing; HotelFlow shell fallback only');</script>
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
    async function hotelSetLocale(base, locale){
      try{
        var u = (String(base||'').replace(/\/$/,'') + '/i18n/set?locale=' + encodeURIComponent(locale));
        await fetch(u, {credentials:'same-origin'});
      }catch(e){}
      location.reload();
    }
    window.hotelSetLocale = hotelSetLocale;
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
          hotelSetLocale('<?= $h($module_base) ?>', next);
        }
      }"
      x-init="$nextTick(()=>{ window.KF && KF.rescan && KF.rescan(document); })">

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
            <?php if ($uusername !== ''): ?>
              <div class="text-xs text-gray-500 truncate">@<?= $h($uusername) ?></div>
            <?php endif; ?>
          </div>
          <a href="/me" class="flex items-center gap-2 px-4 py-2 text-sm hover:bg-gray-50 dark:hover:bg-gray-800/60">
            <i class="fa-regular fa-user"></i><span>My Profile</span>
          </a>
          <?php if ($urole === 'owner' && $slug !== ''): ?>
            <div class="border-t border-gray-200 dark:border-gray-700 my-1"></div>
            <a href="/t/<?= $h($slug) ?>/users" class="flex items-center gap-2 px-4 py-2 text-sm hover:bg-gray-50 dark:hover:bg-gray-800/60">
              <i class="fa-solid fa-users"></i><span>Users</span>
            </a>
          <?php endif; ?>
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
            echo $slot;    // assume pre-rendered HTML
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

  <!-- KF.rescan safety -->
  <script>
    document.addEventListener('DOMContentLoaded', function(){
      try{ if(window.KF && typeof KF.rescan === 'function') KF.rescan(document); }catch(e){}
    });
  </script>

  <!-- ESC → go back to HotelFlow landing (tenantDash) -->
  <script>
    (function(){
      var home = <?= json_encode($tenantDash, JSON_UNESCAPED_SLASHES) ?>;
      document.addEventListener('keydown', function(e){
        var key = (e.key || '').toLowerCase();
        if (key !== 'escape') return;

        // ignore when typing
        var tag = (e.target && e.target.tagName || '').toLowerCase();
        if (tag === 'input' || tag === 'textarea' || (e.target && e.target.isContentEditable)) return;
        if (e.ctrlKey || e.altKey || e.metaKey || e.shiftKey) return;

        // if KF has modal stack, let it close first
        try {
          if (window.KF && KF.modals && typeof KF.modals.closeTop === 'function') {
            var closed = KF.modals.closeTop();
            if (closed) {
              e.preventDefault();
              return;
            }
          }
        } catch (_) {}

        if (!home) return;
        e.preventDefault();
        window.location.href = home;
      });
    })();
  </script>
</body>
</html>