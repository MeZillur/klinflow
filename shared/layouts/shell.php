<?php
declare(strict_types=1);

/**
 * Control Panel Shell (CP only)
 * - Clean, responsive layout (sidebar + sticky header)
 * - No global coupling to tenant shells
 * - Bullet-proof logo finder (handles /public and case differences)
 * - Dark mode (no flash) + mobile drawer
 *
 * Expected Vars (graceful fallbacks supported):
 * - string $title
 * - string $slot                  // main content (preferred)
 * - string $_content              // fallback content name
 * - string|null $SIDENAV          // override sidenav php path
 * - string $brandColor            // hex; default #228B22
 * - string $logoPath              // web path; default /assets/brand/logo.png
 */

$h = fn($s)=>htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');

$title      = $title      ?? 'Control Panel';
$brand      = 'KlinFlow';
$brandColor = $brandColor ?? '#228B22';
$logoPath   = $logoPath   ?? '/assets/brand/logo.png';

// prefer $slot, fall back to $_content
$content = $slot ?? ($_content ?? '');

/* -------------------- Current CP user -------------------- */
if (!isset($currentUser)) {
  $u = $_SESSION['cp_user'] ?? [];
  $currentUser = [
    'name'  => $u['name']  ?? 'Admin',
    'email' => $u['email'] ?? '',
    'role'  => strtolower($u['role'] ?? 'admin'),
  ];
}

/* -------------------- Logo resolver (docroot/public/case-safe) -------------------- */
$normalize = static function (string $p): string {
  $p = preg_replace('#^/?public/#', '/', $p);             // strip leading public/
  if ($p === '' || $p[0] !== '/') $p = '/'.$p;            // ensure leading slash
  return $p;
};
$logoPath = $normalize($logoPath);

$resolveWebToFs = static function (string $webPath): ?string {
  $webPath = '/'.ltrim($webPath, '/');
  $candidates = [];

  $docRoot = rtrim($_SERVER['DOCUMENT_ROOT'] ?? '', '/');
  if ($docRoot) {
    $candidates[] = $docRoot.$webPath;            // direct serve
    $candidates[] = $docRoot.'/public'.$webPath;  // nested public/
  }
  $project = dirname(__DIR__, 3);                 // .../apps/CP/Views/shared/layouts -> project
  $candidates[] = $project.'/public'.$webPath;

  foreach ($candidates as $fs) if (is_file($fs)) return $fs;
  return null;
};

$pick = static function(array $webPaths) use ($resolveWebToFs): array {
  foreach ($webPaths as $wp) {
    $wp = $wp ? '/'.ltrim($wp,'/') : '';
    if (!$wp) continue;
    $fs = $resolveWebToFs($wp);
    if ($fs) return [$wp, $fs];
  }
  return ['',''];
};

// light/dark variants (use if present)
[$logoLightUrl] = $pick(array_unique([
  $logoPath,
  '/assets/brand/logo.png',
  '/assets/brand/klinflow-wordmark-dark.svg',
]));
[$logoDarkUrl ] = $pick(array_unique([
  '/assets/brand/klinflow-wordmark-light.svg',
]));
$hasLight = (bool)$logoLightUrl; $hasDark = (bool)$logoDarkUrl;

/* -------------------- Sidenav path (explicit > CP default > shared fallback) -------------------- */
$sidePartial = null;
$try = [];
if (isset($SIDENAV) && is_string($SIDENAV)) $try[] = $SIDENAV;

$project = dirname(__DIR__, 3); // project root
$try[]   = $project.'/apps/CP/Views/partials/sidenav.php';          // your primary CP sidenav
$try[]   = $project.'/shared/partials/cp_sidenav.php';              // fallback (optional)

foreach ($try as $p) { if (is_file($p)) { $sidePartial = $p; break; } }

$logoutAction = '/cp/logout';
?>
<!doctype html>
<html lang="en" x-data="shell()" x-init="init()" :class="{ 'dark': dark }">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= $h($title) ?></title>
  <meta name="color-scheme" content="light dark">

  <!-- Pre-apply theme (no flash) -->
  <script>
    (function(){
      try{
        var s=localStorage.getItem('theme'), prefers=matchMedia('(prefers-color-scheme: dark)').matches;
        var dark = s ? (s==='dark') : prefers;
        if(dark) document.documentElement.classList.add('dark');
      }catch(e){}
    })();
  </script>

  <?php if ($hasLight): ?><link rel="preload" as="image" href="<?= $h($logoLightUrl) ?>"><?php endif; ?>

  <!-- Tailwind utilities + runtime (preflight off to avoid clashes) -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" crossorigin="anonymous">
  <script>
    window.tailwind = { config:{ darkMode:'class', corePlugins:{ preflight:false } } };
  </script>
  <script src="https://cdn.tailwindcss.com"></script>

  <!-- Alpine / FA -->
  <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" crossorigin="anonymous" />

  <!-- Shell Styles -->
  <style>
    :root{ --brand: <?= $h($brandColor) ?> }
    html, body { height:100dvh; overflow:hidden; }
    .cp-shell  { display:flex; height:100%; }
    .cp-main   { display:flex; flex-direction:column; min-width:0; flex:1; height:100%; }
    @media (min-width:1024px){ .cp-shell { padding-left:16rem; } }

    .cp-content{ flex:1; min-width:0; overflow:auto; -webkit-overflow-scrolling:touch; scrollbar-width:none; }
    .cp-content::-webkit-scrollbar{ display:none; }

    .cp-sidenav{ overflow:auto; -webkit-overflow-scrolling:touch; scrollbar-width:none; }
    .cp-sidenav::-webkit-scrollbar{ display:none; }

    .btn{display:inline-flex;align-items:center;gap:.5rem;padding:.5rem .75rem;border-radius:.5rem}
    .btn-ghost:hover{background-color:rgba(0,0,0,.05)}
    .dark .btn-ghost:hover{background-color:rgba(255,255,255,.10)}
    .avatar{width:2.25rem;height:2.25rem;border-radius:9999px;background:var(--brand);color:#fff;
            display:flex;align-items:center;justify-content:center;font-weight:700}
    .brand-img{height:2rem;width:auto;display:block}
    [x-cloak]{display:none!important}

    /* Dark fallbacks so older views still look right */
    .dark .bg-white{ background:#111827 !important; }
    .dark .text-gray-900{ color:#e5e7eb !important; }
    .dark .text-gray-700{ color:#e5e7eb !important; }
    .dark .text-gray-500{ color:#9ca3af !important; }
    .dark .border-gray-200{ border-color:#374151 !important; }
  </style>
</head>
<body class="bg-white dark:bg-gray-900 text-gray-900 dark:text-gray-100">
  <!-- Mobile overlay -->
  <div x-show="sidebar" x-cloak class="fixed inset-0 bg-black/40 z-30 lg:hidden" @click="sidebar=false"></div>

  <div class="cp-shell">
    <!-- SIDENAV -->
    <aside class="cp-sidenav fixed z-30 inset-y-0 left-0 w-64 transform bg-white dark:bg-gray-800 shadow-lg transition-transform lg:translate-x-0"
           :class="sidebar ? 'translate-x-0' : '-translate-x-full'">
      <div class="h-16 px-4 border-b dark:border-gray-700 flex items-center gap-3">
        <?php if ($hasLight || $hasDark): ?>
          <picture>
            <?php if ($hasDark): ?>
              <source srcset="<?= $h($logoDarkUrl) ?>" media="(prefers-color-scheme: dark)">
            <?php endif; ?>
            <img src="<?= $h($hasLight ? $logoLightUrl : $logoDarkUrl) ?>"
                 alt="KlinFlow" class="brand-img"
                 onerror="this.replaceWith(Object.assign(document.createElement('span'),{textContent:'KlinFlow',className:'font-extrabold text-xl',style:'color:<?= $h($brandColor) ?>'}));">
          </picture>
        <?php else: ?>
          <span class="font-extrabold text-xl" style="color:<?= $h($brandColor) ?>">KlinFlow</span>
        <?php endif; ?>
      </div>

      <?php
        if ($sidePartial) {
          require $sidePartial;
        } else {
          echo '<nav class="p-4 text-sm text-gray-500">
                  <div class="mb-2 font-semibold">Navigation</div>
                  <a class="block py-1 hover:underline" href="/cp/dashboard">Dashboard</a>
                </nav>';
        }
      ?>
    </aside>

    <!-- MAIN -->
    <div class="cp-main w-full">
      <header class="sticky top-0 z-40 flex items-center justify-between px-4 py-3 bg-white dark:bg-gray-800 border-b dark:border-gray-700">
        <div class="flex items-center gap-3">
          <button @click="sidebar=true" class="lg:hidden p-2 rounded hover:bg-gray-100 dark:hover:bg-gray-700" aria-label="Open navigation">
            <i class="fa fa-bars"></i>
          </button>
          <h1 class="text-base sm:text-lg font-semibold truncate"><?= $h($title) ?></h1>
        </div>

        <div class="flex items-center gap-2">
          <button @click="toggleDark()" class="p-2 rounded-full hover:bg-gray-100 dark:hover:bg-gray-700" title="Toggle theme">
            <i class="fa" :class="dark ? 'fa-moon' : 'fa-sun'"></i>
          </button>

          <!-- User dropdown -->
          <div class="relative" x-data="{open:false}" @keydown.escape.window="open=false" @click.outside="open=false">
            <button @click="open=!open" class="flex items-center gap-2 pl-2 pr-3 py-1.5 rounded-full hover:bg-gray-100 dark:hover:bg-gray-700">
              <div class="avatar"><?= strtoupper(substr((string)($currentUser['name'] ?? 'A'),0,1)) ?></div>
              <div class="hidden sm:flex flex-col items-start leading-tight min-w-0">
                <span class="text-sm font-semibold truncate"><?= $h($currentUser['name'] ?? 'Admin') ?></span>
                <span class="text-xs text-gray-500 dark:text-gray-400 truncate"><?= $h($currentUser['email'] ?? '') ?></span>
              </div>
              <i class="fa fa-chevron-down text-gray-500 ml-1"></i>
            </button>

            <div x-show="open" x-transition.origin.top.right x-cloak
                 class="absolute right-0 mt-2 w-72 bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-xl shadow-lg overflow-hidden z-50">
              <div class="px-4 py-3 bg-gray-50 dark:bg-gray-800/60 flex items-center gap-3">
                <div class="avatar"><?= strtoupper(substr((string)($currentUser['name'] ?? 'A'),0,1)) ?></div>
                <div class="min-w-0">
                  <div class="font-semibold truncate"><?= $h($currentUser['name'] ?? 'Admin') ?></div>
                  <div class="text-xs text-gray-500 truncate"><?= $h($currentUser['email'] ?? '') ?></div>
                </div>
                <?php
                  $roleMap = [
                    'super_admin' => 'bg-purple-100 text-purple-700',
                    'admin'       => 'bg-gray-100 text-gray-700',
                    'manager'     => 'bg-sky-100 text-sky-700',
                    'support'     => 'bg-amber-100 text-amber-700',
                    'guest'       => 'bg-gray-100 text-gray-700',
                  ];
                  $rb = $roleMap[$currentUser['role'] ?? 'admin'] ?? 'bg-gray-100 text-gray-700';
                ?>
                <span class="ml-auto px-2 py-1 rounded-full text-[11px] capitalize <?= $rb ?>">
                  <?= $h(str_replace('_',' ',$currentUser['role'] ?? 'admin')) ?>
                </span>
              </div>

              <div class="p-2">
                <a href="/cp/profile" class="flex items-center gap-3 px-3 py-2 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-800">
                  <i class="fa-regular fa-user" style="color: <?= $h($brandColor) ?>"></i>
                  <div>
                    <div class="text-sm font-medium">My Profile</div>
                    <div class="text-xs text-gray-500">Update account info</div>
                  </div>
                </a>

                <div class="my-2 border-t border-gray-200 dark:border-gray-700"></div>

                <form method="post" action="<?= $h($logoutAction) ?>" class="px-2 pb-2">
                  <?php if (class_exists('\\Shared\\Csrf')): ?>
                    <input type="hidden" name="_csrf" value="<?= $h(\Shared\Csrf::token()) ?>">
                  <?php endif; ?>
                  <button class="btn w-full" style="background:#ffe4e6;color:#b91c1c">
                    <i class="fa-solid fa-right-from-bracket"></i><span>Logout</span>
                  </button>
                </form>
              </div>
            </div>
          </div>
        </div>
      </header>

      <main class="cp-content p-4 sm:p-6 min-w-0">
        <?= $content ?>
      </main>

      <footer class="p-4 text-xs text-gray-500 dark:text-gray-400">
        © <?= date('Y') ?> KlinFlow
      </footer>
    </div>
  </div>

  <script>
    function shell(){
      return {
        dark:false, sidebar:false,
        init(){
          const prefers = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
          const saved   = localStorage.getItem('theme');
          this.dark     = saved ? (saved === 'dark') : !!prefers;
          document.documentElement.classList.toggle('dark', this.dark);
        },
        toggleDark(){
          this.dark = !this.dark;
          localStorage.setItem('theme', this.dark ? 'dark' : 'light');
          document.documentElement.classList.toggle('dark', this.dark);
        }
      }
    }
  </script>
</body>
</html>