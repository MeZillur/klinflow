<?php
declare(strict_types=1);

/**
 * BhataFlow — Shell Layout (final)
 *
 * Expects:
 *   - string $title
 *   - string $slot               // page content HTML
 *   - string $module_base        // e.g. "/t/{slug}/apps/bhata"
 *   - array  $org                // at least ['slug'=>...]
 *   - string|null $moduleSidenav // optional override path to sidenav
 */

if (!isset($title))       $title = 'BhataFlow';
if (!isset($module_base)) $module_base = '/apps/bhata';
if (!isset($org) || !is_array($org)) $org = [];

$h = static fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');

/* ---------- Brand ---------- */
$brandColor = '#228B22';
$logoLight  = '/public/assets/brand/klinflow-wordmark-dark.svg';   // shown on light bg
$logoDark   = '/public/assets/brand/klinflow-wordmark-light.svg';  // shown on dark bg

/* ---------- Tenant ---------- */
$slug       = (string)($org['slug'] ?? '');
$tenantDash = $slug ? "/t/{$slug}/dashboard" : '/';

/* ---------- User (soft) ---------- */
$user      = (array)($_SESSION['tenant_user'] ?? []);
$uname     = (string)($user['name'] ?? 'User');
$uusername = (string)($user['username'] ?? '');
$uavatar   = (string)($user['avatar'] ?? $user['avatar_path'] ?? '');
$uinitial  = strtoupper(substr($uname, 0, 1));

/* ---------- Sidenav: Bhata module ---------- */
if (empty($moduleSidenav)) {
  $basePath = \defined('BASE_PATH') ? BASE_PATH : dirname(__DIR__, 5);
  $try = [
    $basePath . '/modules/Bhata/Views/shared/partials/sidenav.php',
    $basePath . '/modules/Bhata/Views/shared/sidenav.php',          // alt path
  ];
  $moduleSidenav = null;
  foreach ($try as $p) { if (is_file($p)) { $moduleSidenav = $p; break; } }
}

/* Locale (optional; fall back to 'en') */
$currLocale = (string)($_SESSION['bf_locale'] ?? 'en');
?>
<!doctype html>
<html lang="<?= $h($currLocale==='bn'?'bn':'en') ?>">
<head>
  <meta charset="utf-8"/>
  <meta name="viewport" content="width=device-width,initial-scale=1"/>
  <meta name="color-scheme" content="light dark"/>
  <title><?= $h($title) ?></title>

  <!-- Inter -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

  <!-- no-flash dark boot -->
  <script>
    (function(){try{
      var s=localStorage.getItem('theme'), prefers=matchMedia('(prefers-color-scheme: dark)').matches;
      var dark = s ? (s==='dark') : prefers;
      if(dark) document.documentElement.classList.add('dark');
      document.documentElement.setAttribute('data-theme', dark?'dark':'light');
    }catch(e){}})();
  </script>

  <style>:root{--brand:<?= $h($brandColor) ?>}</style>
  <script>window.tailwind={config:{darkMode:'class',corePlugins:{preflight:false}}}</script>
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" crossorigin="anonymous"/>

  <!-- Choices.js (optional searchable select) -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/choices.js@10.2.0/public/assets/styles/choices.min.css">
  <script defer src="https://cdn.jsdelivr.net/npm/choices.js@10.2.0/public/assets/scripts/choices.min.js"></script>

  <style>
    html,body{height:100dvh;overflow:hidden}
    body{font-family:Inter,system-ui,-apple-system,Segoe UI,Roboto,Helvetica,Arial,"Apple Color Emoji","Segoe UI Emoji"}
    .avatar{width:2.25rem;height:2.25rem;border-radius:9999px;background:var(--brand);color:#fff;display:flex;align-items:center;justify-content:center;font-weight:700}
    .btn-icon{padding:.5rem;border-radius:9999px}
    [x-cloak]{display:none!important}
    .dark .bg-white{background:#111827!important}
    .dark .text-gray-900{color:#e5e7eb!important}
    .dark .border-gray-200{border-color:#374151!important}
    .brand-logo{height:1.75rem;display:block}
    .brand-logo img{height:1.75rem;width:auto;display:block}
  </style>

  <!-- Alpine + lightweight UI hooks -->
  <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>

  <script>
    document.addEventListener('DOMContentLoaded', function () {
      if (window.Choices) {
        document.querySelectorAll('select[data-choices]').forEach(function(sel){
          new Choices(sel, { searchEnabled:true, allowHTML:false, shouldSort:false, removeItemButton:!!sel.hasAttribute('data-remove') });
        });
      }
    });

    // Locale helper for Bhata
    async function bfSetLocale(base, locale){
      try{
        const url = base.replace(/\/$/,'') + '/i18n/set?locale=' + encodeURIComponent(locale);
        await fetch(url, {credentials:'same-origin'});
      }catch(e){}
      location.reload();
    }
    window.bfSetLocale = bfSetLocale;
  </script>
</head>
<body class="bg-white dark:bg-gray-900 text-gray-900 dark:text-gray-100"
      x-data="{sidebar:false,dark:document.documentElement.classList.contains('dark')}">

  <!-- overlay (mobile) -->
  <div x-show="sidebar" x-cloak class="fixed inset-0 bg-black/40 z-30 lg:hidden" @click="sidebar=false"></div>

  <div class="w-full h-full lg:grid lg:grid-cols-[18rem_1fr]">
    <!-- sidebar -->
    <aside class="fixed z-40 inset-y-0 left-0 w-72 lg:static lg:w-auto bg-white dark:bg-gray-800 border-r border-gray-200 dark:border-gray-700 transform transition-transform"
           :class="sidebar ? 'translate-x-0' : '-translate-x-full lg:translate-x-0'">
      <div class="h-16 px-4 border-b border-gray-200 dark:border-gray-700 flex items-center gap-3">
        <a href="<?= $h($tenantDash) ?>" class="flex items-center gap-2 brand-logo" aria-label="KlinFlow">
          <picture>
            <source srcset="<?= $h($logoDark) ?>" media="(prefers-color-scheme: dark)">
            <img src="<?= $h($logoLight) ?>" alt="KlinFlow"
                 onerror="this.replaceWith(Object.assign(document.createElement('span'),{textContent:'KlinFlow',className:'font-semibold text-lg'}));">
          </picture>
        </a>
      </div>
      <div class="h-[calc(100dvh-4rem)] overflow-y-auto">
        <?php
          if (!empty($moduleSidenav) && is_file($moduleSidenav)) {
            // expose expected vars
            $module_base = $module_base ?? '';
            $org         = $org ?? [];
            require $moduleSidenav;
          } else {
            echo '<nav class="p-4 text-sm text-gray-500">No module sidenav.</nav>';
          }
        ?>
      </div>
    </aside>

    <!-- main -->
    <div class="min-w-0 lg:min-w-[0]">
      <header class="h-16 sticky top-0 z-20 bg-white dark:bg-gray-800 border-b border-gray-200 dark:border-gray-700 flex items-center justify-between px-3 lg:px-4">
        <div class="flex items-center gap-2">
          <button class="lg:hidden btn-icon hover:bg-gray-100 dark:hover:bg-gray-700" @click="sidebar=true" aria-label="Open menu">
            <i class="fa fa-bars"></i>
          </button>
          <div class="font-semibold hidden sm:block">BhataFlow</div>
        </div>

        <div class="flex items-center gap-1 sm:gap-2" x-data="{menu:false,langOpen:false}">
          <!-- Language -->
          <div class="relative">
            <button class="btn-icon hover:bg-gray-100 dark:hover:bg-gray-700" @click="langOpen=!langOpen" aria-label="Language">
              <i class="fa-solid fa-globe"></i>
            </button>
            <div x-show="langOpen" x-cloak x-transition.origin.top.right
                 @click.outside="langOpen=false"
                 class="absolute right-0 mt-2 w-40 bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-xl shadow-lg overflow-hidden z-50">
              <button class="w-full text-left px-4 py-2 text-sm hover:bg-gray-50 dark:hover:bg-gray-800/60 <?= $currLocale==='en'?'font-semibold':'' ?>"
                      onclick="bfSetLocale('<?= $h($module_base) ?>','en')">English</button>
              <button class="w-full text-left px-4 py-2 text-sm hover:bg-gray-50 dark:hover:bg-gray-800/60 <?= $currLocale==='bn'?'font-semibold':'' ?>"
                      onclick="bfSetLocale('<?= $h($module_base) ?>','bn')">বাংলা</button>
            </div>
          </div>

          <!-- Theme toggle -->
          <button class="btn-icon hover:bg-gray-100 dark:hover:bg-gray-700"
                  @click="dark=!dark;localStorage.setItem('theme',dark?'dark':'light');
                          document.documentElement.classList.toggle('dark',dark);
                          document.documentElement.setAttribute('data-theme',dark?'dark':'light')"
                  aria-label="Toggle theme">
            <i class="fa" :class="dark ? 'fa-moon' : 'fa-sun'"></i>
          </button>

          <!-- User -->
          <div class="relative">
            <?php $avatarUrl = $uavatar !== '' && $uavatar[0] !== '/' ? '/uploads/avatars/'.$uavatar : $uavatar; ?>
            <button @click="menu=!menu"
                    class="flex items-center gap-2 rounded-full pl-1 pr-2 py-1 hover:bg-gray-100 dark:hover:bg-gray-700"
                    aria-label="User menu">
              <?php if ($avatarUrl): ?>
                <img src="<?= $h($avatarUrl) ?>" alt="Avatar" class="w-9 h-9 rounded-full object-cover">
              <?php else: ?>
                <div class="avatar"><?= $h($uinitial) ?></div>
              <?php endif; ?>
              <i class="fa fa-chevron-down text-gray-400 text-sm"></i>
            </button>

            <div x-show="menu" x-cloak x-transition.origin.top.right
                 @click.outside="menu=false"
                 class="absolute right-0 mt-2 w-56 bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-xl shadow-lg overflow-hidden z-50">
              <div class="px-4 py-3 text-sm border-b border-gray-200 dark:border-gray-700">
                <div class="font-semibold truncate"><?= $h($uname) ?></div>
                <?php if ($uusername !== ''): ?>
                  <div class="text-xs text-gray-500 truncate">@<?= $h($uusername) ?></div>
                <?php endif; ?>
              </div>
              <a href="/me" class="flex items-center gap-2 px-4 py-2 text-sm hover:bg-gray-50 dark:hover:bg-gray-800/60">
                <i class="fa-regular fa-user"></i><span>My Profile</span>
              </a>
              <div class="border-t border-gray-200 dark:border-gray-700 my-1"></div>
              <a href="<?= $h($tenantDash) ?>" class="flex items-center gap-2 px-4 py-2 text-sm hover:bg-gray-50 dark:hover:bg-gray-800/60">
                <i class="fa-solid fa-gauge"></i><span>App dashboard</span>
              </a>
              <a href="<?= $h(rtrim($module_base,'/').'/settings') ?>" class="flex items-center gap-2 px-4 py-2 text-sm hover:bg-gray-50 dark:hover:bg-gray-800/60">
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

      <main class="h-[calc(100dvh-4rem)] overflow-y-auto p-4 lg:p-6">
        <?= $slot ?? '' ?>
      </main>
    </div>
  </div>
</body>
</html>