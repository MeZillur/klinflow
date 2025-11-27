<?php
/**
 * KlinFlow Unified Shell (CP + Tenant)
 * - Brand color kept intact
 * - Dark mode persists (no flash)
 * - Fixed body; only content scrolls (hidden scrollbars)
 * - Sticky header; independent sticky sidenav
 * - CP sidenav at apps/CP/Views/partials/sidenav.php (primary)
 *   Tenant sidenav at apps/Tenant/Views/partials/sidenav.php (if/when you add it)
 */
declare(strict_types=1);

use Shared\Csrf;

/* -------------------- Defaults -------------------- */
$brand      = $brand      ?? 'KlinFlow';
$brandColor = $brandColor ?? '#228B22';
/* ✅ Your request: default logo under public/Assets/brand */
$logoPath   = $logoPath   ?? '/Assets/brand/logo.png';
$title      = $title      ?? $brand;
$content    = $content    ?? '';

/* -------------------- Current user & scope -------------------- */
if (!isset($currentUser)) {
  if (!empty($_SESSION['cp_user'])) {
    $u = $_SESSION['cp_user'];
    $currentUser = [
      'name'     => $u['name']  ?? 'User',
      'username' => $u['email'] ?? '',
      'email'    => $u['email'] ?? '',
      'role'     => strtolower($u['role'] ?? 'admin'),
    ];
    $scope = $scope ?? 'cp';
  } elseif (!empty($_SESSION['tenant_user'])) {
    $u = $_SESSION['tenant_user'];
    $currentUser = [
      'name'     => $u['name']     ?? 'User',
      'username' => $u['username'] ?? '',
      'email'    => $u['email']    ?? '',
      'role'     => strtolower($u['role'] ?? 'employee'),
    ];
    $scope = $scope ?? 'tenant';
  } else {
    $currentUser = ['name'=>'Guest','username'=>'','email'=>'','role'=>'guest'];
    $scope = $scope ?? 'tenant';
  }
} else {
  $scope = $scope ?? (isset($_SESSION['cp_user']) ? 'cp' : 'tenant');
}

/* -------------------- Helpers -------------------- */
$h    = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
$csrf = class_exists(Csrf::class) ? Csrf::token() : '';

/**
 * Map a web path like /Assets/brand/logo.png to a filesystem path.
 * Works whether the domain docroot is /public_html OR /public_html/public.
 */
$resolveWebToFs = function (string $webPath): ?string {
  $webPath = '/' . ltrim($webPath, '/');
  $candidates = [];

  $docRoot = rtrim($_SERVER['DOCUMENT_ROOT'] ?? '', '/');      // usually .../public_html
  if ($docRoot) {
    $candidates[] = $docRoot . $webPath;                        // if docroot = /public_html/public
    $candidates[] = $docRoot . '/public' . $webPath;            // if docroot = /public_html
  }
  // project root (…/public_html) from /shared/layouts/shell.php
  $project = dirname(__DIR__, 2);
  $candidates[] = $project . '/public' . $webPath;

  foreach ($candidates as $fs) {
    if (is_file($fs)) return $fs;
  }
  return null;
};

$logoFs  = $resolveWebToFs($logoPath);
$hasLogo = (bool)$logoFs;

/* -------------------- Sidenav resolver -------------------- */
/* Priority:
   1) Explicit $SIDENAV (if controller set it)
   2) CP:     apps/CP/Views/partials/sidenav.php
      Tenant: apps/Tenant/Views/partials/sidenav.php
   3) shared/partials/{cp_sidenav|tenant_sidenav}.php (fallback)
*/
$sidePartial = null;
$try = [];

if (isset($SIDENAV) && is_string($SIDENAV)) {
  $try[] = $SIDENAV;
}

$project = dirname(__DIR__, 2); // /.../public_html

if ($scope === 'cp') {
  $try[] = $project . '/apps/CP/Views/partials/sidenav.php';
  $try[] = dirname(__DIR__) . '/partials/cp_sidenav.php'; // /shared/partials/cp_sidenav.php (fallback)
} else {
  $try[] = $project . '/apps/Tenant/Views/partials/sidenav.php';
  $try[] = dirname(__DIR__) . '/partials/tenant_sidenav.php'; // fallback
  $try[] = dirname(__DIR__) . '/partials/sidenav.php';        // legacy name
}

foreach ($try as $p) {
  if (is_string($p) && is_file($p)) { $sidePartial = $p; break; }
}

$logoutAction = ($scope === 'cp') ? '/cp/logout' : '/tenant/logout';
?>
<!doctype html>
<html lang="en" x-data="shell()" x-init="init()" :class="{ 'dark': dark }">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= $h($title) ?></title>

  <!-- Pre-apply theme to avoid flicker -->
  <script>
    (function () {
      try {
        var saved = localStorage.getItem('theme');
        var prefers = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
        var dark = saved ? (saved === 'dark') : prefers;
        if (dark) document.documentElement.classList.add('dark');
      } catch(e){}
    })();
  </script>

  <!-- Tokens + brand color -->
  <link rel="stylesheet" href="/assets/css/ui.css">
  <style>:root { --brand: <?= $h($brandColor) ?> }</style>

  <!-- Tailwind (static utilities) -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" crossorigin="anonymous">
  <!-- Tailwind runtime (preflight off to not disturb your CSS) -->
  <script>
    window.tailwind = { config:{ darkMode:'class', corePlugins:{ preflight:false }, theme:{ extend:{ colors:{ brand:'<?= $h($brandColor) ?>' } } } } };
  </script>
  <script src="https://cdn.tailwindcss.com"></script>

  <!-- Alpine / Chart.js / Font Awesome -->
  <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
  <script defer src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" crossorigin="anonymous" />

  <!-- Shell CSS -->
  <style>
    html, body { box-sizing:border-box; height:100dvh; overflow:hidden; }
    .kf-shell  { display:flex; min-height:100%; height:100%; }
    .kf-main   { display:flex; flex-direction:column; min-width:0; flex:1; height:100%; }

    /* compensate for fixed 16rem sidenav */
    @media (min-width:1024px){ .kf-shell { padding-left:16rem; } }

    .kf-content{ flex:1; min-width:0; overflow:auto; -webkit-overflow-scrolling:touch; scrollbar-width:none; }
    .kf-content::-webkit-scrollbar{ display:none; }

    .kf-sidenav{ overflow:auto; -webkit-overflow-scrolling:touch; scrollbar-width:none; }
    .kf-sidenav::-webkit-scrollbar{ display:none; }

    .btn{display:inline-flex;align-items:center;gap:.5rem;padding:.5rem .75rem;border-radius:.5rem}
    .btn-ghost{background:transparent}
    .btn-ghost:hover{background-color:rgba(0,0,0,.05)}
    .dark .btn-ghost:hover{background-color:rgba(255,255,255,.1)}
    .btn-brand{background:<?= $h($brandColor) ?>;color:#fff}
    .btn-brand:hover{filter:brightness(.95)}
    .avatar{width:2.25rem;height:2.25rem;border-radius:9999px;background:<?= $h($brandColor) ?>;color:#fff;display:flex;align-items:center;justify-content:center;font-weight:600;box-shadow:inset 0 0 0 1px rgba(0,0,0,.05)}
    [x-cloak]{display:none!important}

    /* dark fallbacks so older views look right */
    .dark .bg-white{ background-color:#111827 !important; }
    .dark .bg-gray-50{ background-color:#0b1220 !important; }
    .dark .text-gray-900{ color:#e5e7eb !important; }
    .dark .text-gray-700{ color:#e5e7eb !important; }
    .dark .text-gray-500{ color:#9ca3af !important; }
    .dark .border-gray-200{ border-color:#374151 !important; }
    .dark .shadow{ box-shadow:0 2px 12px rgba(0,0,0,.35)!important; }
  </style>
</head>

<body class="bg-white dark:bg-gray-900 text-gray-900 dark:text-gray-100">
  <!-- Mobile overlay -->
  <div x-show="sidebar" x-cloak class="fixed inset-0 bg-black bg-opacity-40 z-30 lg:hidden" @click="sidebar=false" aria-hidden="true"></div>

  <div class="kf-shell">
    <!-- SIDENAV -->
    <aside class="kf-sidenav fixed z-30 inset-y-0 left-0 w-64 transform bg-white dark:bg-gray-800 shadow-lg transition-transform lg:translate-x-0"
           :class="sidebar ? 'translate-x-0' : '-translate-x-full'">
      <div class="h-16 px-4 border-b dark:border-gray-700 flex items-center gap-3">
        <?php if ($hasLogo): ?>
          <img src="<?= $h($logoPath) ?>" alt="Logo" class="h-8 w-auto">
        <?php else: ?>
          <span class="font-extrabold text-xl" style="color: <?= $h($brandColor) ?>"><?= $h($brand) ?></span>
        <?php endif; ?>
      </div>
      <?php
        if ($sidePartial) {
          require $sidePartial;
        } else {
          // minimal fallback
          echo '<nav class="p-4 text-sm text-gray-500">
                  <div class="mb-2 font-semibold">Navigation</div>
                  <a class="block py-1 hover:underline" href="/cp/dashboard">Dashboard</a>
                </nav>';
        }
      ?>
    </aside>

    <!-- MAIN -->
    <div class="kf-main w-full">
      <header class="sticky top-0 z-40 flex items-center justify-between px-4 py-3 bg-white dark:bg-gray-800 shadow">
        <div class="flex items-center space-x-3">
          <button @click="sidebar=true" class="lg:hidden p-2 rounded hover:bg-gray-100 dark:hover:bg-gray-700" aria-label="Open navigation">
            <i class="fa fa-bars"></i>
          </button>

          <div x-data="typeahead()" x-init="init()" class="relative w-full max-w-3xl" role="search">
            <div class="flex items-center gap-2 bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl px-3 py-2">
              <i class="fa-solid fa-magnifying-glass text-gray-500"></i>
              <input id="global-search" x-model="q" @input="onInput"
                     @keydown.arrow-down.prevent="move(1)" @keydown.arrow-up.prevent="move(-1)"
                     @keydown.enter.prevent="go()" @keydown.escape="close()" @focus="open=true"
                     placeholder="Search… (⌘/Ctrl + K)" class="bg-transparent outline-none w-full" type="search" autocomplete="off">
            </div>
            <div x-show="open && items.length" x-cloak
                 class="absolute z-50 mt-1 w-full bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-lg shadow-lg overflow-hidden">
              <template x-for="(it, i) in items" :key="it.url">
                <a :href="it.url" class="flex items-center gap-3 px-3 py-2 hover:bg-gray-100 dark:hover:bg-gray-800">
                  <i class="fa-solid" :class="it.icon || 'fa-magnifying-glass'" style="color: <?= $h($brandColor) ?>"></i>
                  <span class="truncate" x-text="it.text"></span>
                </a>
              </template>
            </div>
          </div>
        </div>

        <div class="flex items-center gap-2">
          <button @click="toggleDark()" class="p-2 rounded-full hover:bg-gray-100 dark:hover:bg-gray-700" title="Toggle theme">
            <i class="fa" :class="dark ? 'fa-moon' : 'fa-sun'"></i>
          </button>

          <div class="relative" x-data="{open:false}" @keydown.escape.window="open=false" @click.outside="open=false">
            <button @click="open=!open" class="flex items-center gap-2 pl-2 pr-3 py-1.5 rounded-full hover:bg-gray-100 dark:hover:bg-gray-700">
              <div class="avatar"><?= strtoupper(substr((string)($currentUser['name'] ?? 'U'),0,1)) ?></div>
              <div class="hidden sm:flex flex-col items-start leading-tight min-w-0">
                <span class="text-sm font-semibold truncate"><?= $h($currentUser['name'] ?? 'Guest') ?></span>
                <span class="text-xs text-gray-500 dark:text-gray-400 truncate">@<?= $h($currentUser['username'] ?? '') ?></span>
              </div>
              <i class="fa fa-chevron-down text-gray-500 ml-1"></i>
            </button>

            <div x-show="open" x-transition.origin.top.right x-cloak
                 class="absolute right-0 mt-2 w-72 bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-xl shadow-lg overflow-hidden z-50">
              <div class="px-4 py-3 bg-gray-50 dark:bg-gray-800/60 flex items-center gap-3">
                <div class="avatar"><?= strtoupper(substr((string)($currentUser['name'] ?? 'U'),0,1)) ?></div>
                <div class="min-w-0">
                  <div class="font-semibold truncate"><?= $h($currentUser['name'] ?? 'Guest') ?></div>
                  <div class="text-xs text-gray-500 truncate">@<?= $h($currentUser['username'] ?? '') ?></div>
                </div>
                <?php
                  $roleMap = [
                    'super_admin' => 'bg-purple-100 text-purple-700',
                    'owner'       => 'bg-amber-100 text-amber-700',
                    'manager'     => 'bg-sky-100 text-sky-700',
                    'employee'    => 'bg-emerald-100 text-emerald-700',
                    'admin'       => 'bg-gray-100 text-gray-700',
                    'guest'       => 'bg-gray-100 text-gray-700',
                  ];
                  $rb = $roleMap[$currentUser['role'] ?? 'guest'] ?? 'bg-gray-100 text-gray-700';
                ?>
                <span class="ml-auto px-2 py-1 rounded-full text-[11px] capitalize <?= $rb ?>">
                  <?= $h(str_replace('_',' ',$currentUser['role'] ?? '')) ?>
                </span>
              </div>

              <div class="p-2">
                <a href="<?= $scope==='cp' ? '/cp/profile' : '/me' ?>" class="flex items-center gap-3 px-3 py-2 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-800">
                  <i class="fa-regular fa-user" style="color: <?= $h($brandColor) ?>"></i>
                  <div>
                    <div class="text-sm font-medium">My Profile</div>
                    <div class="text-xs text-gray-500">View &amp; update your details</div>
                  </div>
                </a>

                <div class="my-2 border-t border-gray-200 dark:border-gray-700"></div>

                <form method="post" action="<?= $h($logoutAction) ?>" class="px-2 pb-2">
                  <input type="hidden" name="_csrf" value="<?= $h($csrf) ?>">
                  <button class="btn w-full" style="background:#ffe4e6;color:#b91c1c">
                    <i class="fa-solid fa-right-from-bracket"></i><span>Logout</span>
                  </button>
                </form>
              </div>
            </div>
          </div>
        </div>
      </header>

      <main class="kf-content p-6 min-w-0">
        <?= $content ?>
      </main>

      <footer class="p-4 text-xs text-gray-500 dark:text-gray-400">
        © <?= date('Y') ?> <?= $h($brand) ?>
      </footer>
    </div>
  </div>

  <script>
    function shell(){
      return {
        dark:false, sidebar:false,
        init(){
          const pref  = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
          const saved = localStorage.getItem('theme');
          this.dark   = saved ? (saved === 'dark') : !!pref;
          document.documentElement.classList.toggle('dark', this.dark);
          document.addEventListener('keydown', e => {
            if ((e.ctrlKey||e.metaKey) && e.key.toLowerCase()==='k'){
              e.preventDefault();
              document.getElementById('global-search')?.focus();
            }
          });
        },
        toggleDark(){
          this.dark = !this.dark;
          localStorage.setItem('theme', this.dark ? 'dark' : 'light');
          document.documentElement.classList.toggle('dark', this.dark);
        }
      }
    }
    function typeahead(){
      return {
        q:'', open:false, items:[], idx:-1,
        init(){},
        onInput(){
          this.open = true;
          const t = this.q.trim();
          this.items = t ? [
            { text: 'Dashboard', url: '<?= $scope==='cp' ? '/cp/dashboard' : '/dashboard' ?>', icon: 'fa-house' },
            <?php if ($scope==='cp'): ?>
            { text: 'Organizations', url: '/cp/organizations', icon: 'fa-building' },
            { text: 'CP Users',      url: '/cp/users',         icon: 'fa-users' },
            <?php else: ?>
            { text: 'My Apps',       url: '/apps',             icon: 'fa-grid-2' },
            <?php endif; ?>
          ] : [];
          this.idx = this.items.length ? 0 : -1;
        },
        move(d){ if (!this.items.length) return; this.idx = (this.idx + d + this.items.length) % this.items.length; },
        go(){ if (this.idx>=0 && this.items[this.idx]) window.location = this.items[this.idx].url; },
        close(){ this.open=false; }
      }
    }
  </script>
</body>
</html>