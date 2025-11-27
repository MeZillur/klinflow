<?php
declare(strict_types=1);

$brand      = 'KlinFlow';
$brandColor = '#228B22';
$logoPath   = '/assets/brand/logo.png';
$h = fn($s)=>htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');

$org         = $org ?? ($_SESSION['tenant_org'] ?? []);
$slug        = (string)($org['slug'] ?? '');
$orgName     = (string)($org['name'] ?? 'School');
$module_base = $module_base ?? ($slug ? "/t/{$slug}/apps/school" : '/apps/school');
$tenantDash  = $slug ? "/t/{$slug}/dashboard" : '/';

$moduleSidenav = (is_string($moduleSidenav ?? '') && is_file($moduleSidenav))
    ? $moduleSidenav
    : (function () {
        $root = dirname(__DIR__, 3); // modules/school
        foreach ([
            $root.'/Views/shared/partials/sidenav.php',
            $root.'/src/Views/shared/partials/sidenav.php',
        ] as $p) { if (is_file($p)) return $p; }
        return null;
      })();
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <meta name="color-scheme" content="light dark" />
  <title><?= $h($title ?? $orgName) ?></title>

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
  <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>

  <style>
    html,body{height:100dvh;overflow:hidden}
    .avatar{width:2.25rem;height:2.25rem;border-radius:9999px;background:var(--brand);color:#fff;display:flex;align-items:center;justify-content:center;font-weight:700}
    .dark .bg-white{background:#111827!important}
    .dark .text-gray-900{color:#e5e7eb!important}
    .dark .border-gray-200{border-color:#374151!important}
    [x-cloak]{display:none!important}
  </style>
</head>
<body class="bg-white dark:bg-gray-900 text-gray-900 dark:text-gray-100"
      x-data="{sidebar:false,dark:document.documentElement.classList.contains('dark')}">

  <div x-show="sidebar" x-cloak class="fixed inset-0 bg-black/40 z-30 lg:hidden" @click="sidebar=false"></div>

  <div class="w-full h-full lg:grid lg:grid-cols-[18rem_1fr]">
    <!-- SIDENAV -->
    <aside class="fixed z-40 inset-y-0 left-0 w-72 lg:static lg:w-auto bg-white dark:bg-gray-800 border-r border-gray-200 dark:border-gray-700 transform transition-transform"
           :class="sidebar ? 'translate-x-0' : '-translate-x-full lg:translate-x-0'">
      <div class="h-16 px-4 border-b border-gray-200 dark:border-gray-700 flex items-center gap-3">
        <a href="<?= $h($tenantDash) ?>" class="flex items-center gap-2">
          <img src="<?= $h($logoPath) ?>" alt="Logo" class="h-8 w-auto">
        </a>
      </div>
      <div class="h-[calc(100dvh-4rem)] overflow-y-auto">
        <?php
          if ($moduleSidenav) { $module_base = $module_base; $org = $org; require $moduleSidenav; }
          else echo '<nav class="p-4 text-sm text-gray-500">No module sidenav.</nav>';
        ?>
      </div>
    </aside>

    <!-- MAIN -->
    <div class="min-w-0 lg:min-w-[0]">
      <header class="h-16 sticky top-0 z-20 bg-white dark:bg-gray-800 border-b border-gray-200 dark:border-gray-700 flex items-center justify-between px-3 lg:px-4">
        <div class="flex items-center gap-2">
          <button class="lg:hidden p-2 rounded hover:bg-gray-100 dark:hover:bg-gray-700" @click="sidebar=true" aria-label="Open menu">
            <i class="fa fa-bars"></i>
          </button>
          <div class="font-semibold truncate max-w-[50vw]"><?= $h($orgName) ?></div>
        </div>

        <div class="flex items-center gap-2" x-data="{menu:false}">
          <button class="p-2 rounded-full hover:bg-gray-100 dark:hover:bg-gray-700"
                  @click="dark=!dark;localStorage.setItem('theme',dark?'dark':'light');
                          document.documentElement.classList.toggle('dark',dark);
                          document.documentElement.setAttribute('data-theme',dark?'dark':'light')">
            <i class="fa" :class="dark ? 'fa-moon' : 'fa-sun'"></i>
          </button>

          <div class="relative">
            <button @click="menu=!menu" class="flex items-center gap-2 rounded-full px-2 py-1 hover:bg-gray-100 dark:hover:bg-gray-700">
              <div class="avatar"><?= strtoupper(substr((string)($_SESSION['tenant_user']['name'] ?? 'U'),0,1)) ?></div>
              <i class="fa fa-chevron-down text-gray-400"></i>
            </button>

            <div x-show="menu" x-cloak x-transition.origin.top.right
                 @click.outside="menu=false"
                 class="absolute right-0 mt-2 w-56 bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-xl shadow-lg overflow-hidden z-50">
              <div class="px-4 py-3 text-sm border-b border-gray-200 dark:border-gray-700">
                <div class="font-semibold"><?= $h($_SESSION['tenant_user']['name'] ?? 'User') ?></div>
                <div class="text-xs text-gray-500 truncate"><?= $h($_SESSION['tenant_user']['email'] ?? '') ?></div>
              </div>
              <form method="post" action="/tenant/logout" class="block">
                <button type="submit"
                        class="w-full flex items-center gap-2 px-4 py-2 text-left hover:bg-red-50 dark:hover:bg-red-900/30 text-red-600">
                  <i class="fa-solid fa-right-from-bracket"></i>
                  <span>Sign out</span>
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