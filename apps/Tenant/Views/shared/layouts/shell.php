<?php
declare(strict_types=1);

use Shared\Csrf;

/**
 * Tenant Shell — header only, NO module sidenav
 * Used for /me, /t/{slug}/settings, simple tenant pages.
 *
 * Expects:
 * - string $title
 * - string $module_base  (e.g. /t/{slug}/apps/dms — used by "Back to Apps")
 * - array  $org          (tenant org array, optional)
 * - string $slot OR $_content (view content; either raw HTML or path)
 */

if (!isset($title))       $title = 'Tenant';
if (!isset($module_base)) $module_base = '/';

$org = isset($org) && is_array($org) ? $org : ($_SESSION['tenant_org'] ?? []);
$h   = fn($s)=>htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');

/* ---------- helpers for local assets ---------- */
$docRoot = rtrim((string)($_SERVER['DOCUMENT_ROOT'] ?? ''), '/');
$asset   = fn(string $rel) => '/assets/' . ltrim($rel, '/');
$urlIfExists = function(string $url) use ($docRoot): ?string {
    return ($docRoot && is_file($docRoot.$url)) ? $url : null;
};

/* ---------- branding ---------- */
$brandColor = '#228B22';
$logoLight  = $urlIfExists($asset('brand/klinflow-wordmark-dark.svg'))
           ?: $urlIfExists($asset('brand/logo.svg'))
           ?: $urlIfExists($asset('brand/logo.png'))
           ?: 'data:image/svg+xml;utf8,'.rawurlencode(
                '<svg xmlns="http://www.w3.org/2000/svg" width="120" height="28">
                   <text x="0" y="20" font-family="Inter,Arial" font-size="18" fill="#0b1220">KlinFlow</text>
                 </svg>'
              );
$logoDark   = $urlIfExists($asset('brand/klinflow-wordmark-light.svg'))
           ?: $urlIfExists($asset('brand/logo.svg'))
           ?: $urlIfExists($asset('brand/logo.png'))
           ?: 'data:image/svg+xml;utf8,'.rawurlencode(
                '<svg xmlns="http://www.w3.org/2000/svg" width="120" height="28">
                   <text x="0" y="20" font-family="Inter,Arial" font-size="18" fill="#e5e7eb">KlinFlow</text>
                 </svg>'
              );

/* ---------- tenant user (for avatar in header) ---------- */
$tenantUser = (array)($_SESSION['tenant_user'] ?? []);
$uname      = trim((string)($tenantUser['name']  ?? 'User'));
$uemail     = trim((string)($tenantUser['email'] ?? ''));
$uavatar    = (string)($tenantUser['avatar'] ?? ($tenantUser['avatar_path'] ?? ''));
$uinitial   = strtoupper(substr($uname !== '' ? $uname : 'U', 0, 1));
if ($uavatar !== '' && $uavatar[0] !== '/') {
    $uavatar = '/uploads/avatars/' . $uavatar;
}

/* ---------- content wiring ---------- */
$_content = $_content ?? '';
$slot     = $slot     ?? $_content;

/* ---------- CSRF for logout ---------- */
$csrf = class_exists(Csrf::class) ? Csrf::token() : '';

/* ---------- locale ---------- */
$currLocale = 'en';
?>
<!doctype html>
<html lang="<?= $h($currLocale) ?>">
<head>
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

  <!-- Tokens + Tailwind -->
  <?php if ($p=$urlIfExists($asset('styles/tokens.css'))): ?>
    <link rel="stylesheet" href="<?= $h($p) ?>"/>
  <?php endif; ?>
  <style>:root{--brand:<?= $h($brandColor) ?>}</style>
  <script>window.tailwind={config:{darkMode:'class',corePlugins:{preflight:false}}}</script>
  <script src="https://cdn.tailwindcss.com"></script>

  <!-- Basic shell styles (no sidenav at all) -->
  <style>
    html,body{height:100dvh}
    body{overflow:hidden;font-family:Inter,system-ui,-apple-system,Segoe UI,Roboto,Helvetica,Arial}
    [x-cloak]{display:none!important}
    .brand-logo{display:flex;align-items:center;height:1.75rem}
    .brand-logo img{display:block;height:1.75rem;width:auto}
    .avatar{width:2.25rem;height:2.25rem;background:#228B22;color:#fff;
            display:flex;align-items:center;justify-content:center;
            font-weight:700;border-radius:9999px}
  </style>

  <!-- Early dark-mode boot -->
  <script>
    (function(){
      try{
        var saved   = localStorage.getItem('theme');
        var prefers = matchMedia('(prefers-color-scheme: dark)').matches;
        var dark = saved ? (saved==='dark') : prefers;
        if (dark) document.documentElement.classList.add('dark');
        document.documentElement.setAttribute('data-theme', dark ? 'dark' : 'light');
      }catch(e){}
    })();
  </script>

  <!-- Alpine + KF boot/global -->
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
  <?php endif; ?>
</head>

<body class="bg-white dark:bg-gray-900 text-gray-900 dark:text-gray-100"
      x-data="{
        dark:document.documentElement.classList.contains('dark'),
        toggleTheme(){
          this.dark=!this.dark;
          localStorage.setItem('theme', this.dark?'dark':'light');
          document.documentElement.classList.toggle('dark', this.dark);
          document.documentElement.setAttribute('data-theme', this.dark ? 'dark' : 'light');
        }
      }">

  <!-- HEADER (tenant) -->
  <header class="h-16 sticky top-0 z-20 bg-white dark:bg-gray-800 border-b border-gray-200 dark:border-gray-700 flex items-center justify-between px-3 lg:px-4">
    <div class="flex items-center gap-3">
      <a href="<?= $h($module_base) ?>" class="brand-logo" aria-label="Back to apps">
        <picture>
          <source srcset="<?= $h($logoDark) ?>" media="(prefers-color-scheme: dark)">
          <img src="<?= $h($logoLight) ?>" alt="KlinFlow" loading="eager">
        </picture>
      </a>
    </div>

    <div class="flex items-center gap-2 sm:gap-3">
      <!-- Theme toggle -->
      <button class="px-2 py-2 rounded hover:bg-gray-100 dark:hover:bg-gray-700"
              @click="toggleTheme()" aria-label="Toggle theme">
        <i class="fa" :class="dark ? 'fa-moon' : 'fa-sun'"></i>
      </button>

      <!-- User dropdown -->
      <div class="relative" x-data="{open:false}">
        <button @click="open=!open"
                class="flex items-center gap-2 rounded-full pl-1 pr-2 py-1 hover:bg-gray-100 dark:hover:bg-gray-700"
                aria-label="Tenant user menu">
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
             class="absolute right-0 mt-2 w-64 bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-md shadow-lg overflow-hidden z-50">
          <div class="px-4 py-3 text-sm border-b border-gray-200 dark:border-gray-700 flex items-center gap-3">
            <div class="avatar"><?= $h($uinitial) ?></div>
            <div class="min-w-0">
              <div class="font-semibold truncate"><?= $h($uname) ?></div>
              <?php if ($uemail !== ''): ?>
                <div class="text-xs text-gray-500 truncate"><?= $h($uemail) ?></div>
              <?php endif; ?>
            </div>
          </div>

          <a href="/me" class="flex items-center gap-2 px-4 py-2 text-sm hover:bg-gray-50 dark:hover:bg-gray-800/60">
            <i class="fa-regular fa-user"></i><span>My profile</span>
          </a>
          <a href="<?= $h($module_base) ?>" class="flex items-center gap-2 px-4 py-2 text-sm hover:bg-gray-50 dark:hover:bg-gray-800/60">
            <i class="fa-solid fa-grid-2"></i><span>Back to apps</span>
          </a>

          <div class="border-t border-gray-200 dark:border-gray-700 my-1"></div>
          <form method="post" action="/tenant/logout" class="block">
            <?php if ($csrf !== ''): ?>
              <input type="hidden" name="_csrf" value="<?= $h($csrf) ?>">
            <?php endif; ?>
            <button type="submit" class="w-full flex items-center gap-2 px-4 py-2 text-left text-red-600 hover:bg-red-50 dark:hover:bg-red-900/30">
              <i class="fa-solid fa-right-from-bracket"></i><span>Sign out</span>
            </button>
          </form>
        </div>
      </div>
    </div>
  </header>

  <!-- MAIN (no sidenav, full-width content) -->
  <main class="h-[calc(100dvh-4rem)] overflow-y-auto p-4 lg:p-6">
    <?php
    // 1) Preferred: controller sets $_content to an absolute file path
    if (isset($_content) && is_string($_content) && $_content !== '' && is_file($_content)) {
        require $_content;

    // 2) Or $slot
    } elseif (isset($slot) && is_string($slot) && $slot !== '') {
        if (is_file($slot)) {
            require $slot;
        } else {
            echo $slot;
        }

    // 3) Or $view + module_dir (for older controllers)
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

    } else {
        echo '<div class="text-red-600">View content missing.</div>';
    }
    ?>
  </main>
</body>
</html>