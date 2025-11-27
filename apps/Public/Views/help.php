<?php
/** apps/Public/Views/help.php
 * KlinFlow — Help Center (public)
 * - Consistent header + drawer used across public pages
 * - Reveal-on-scroll animations (respects reduced motion)
 * - Works standalone; optionally hydrates from /assets/help.json
 */

/* ---- Tiny helpers / normalization ---- */
$normalize = static function (?string $p): string {
    $p = (string)($p ?? '');
    if ($p === '') return '/assets/brand/logo.png';
    return preg_replace('#^/public/#', '/', $p);
};
$h = static fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');

/* ---- Page vars (override as needed before include) ---- */
$pageTitle   = $pageTitle   ?? 'KlinFlow — Help Center';
$pageDesc    = $pageDesc    ?? 'Find quick answers, tutorials, and guides for KlinFlow modules.';
$brandLogo   = $normalize($brandLogo   ?? '/assets/brand/logo.png');
$siteFavicon = $normalize($siteFavicon ?? '/assets/brand/logo.png');
?>
<!doctype html>
<html lang="en" class="h-full">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />

  <title><?= $h($pageTitle) ?></title>
  <meta name="description" content="<?= $h($pageDesc) ?>" />
  <meta name="theme-color" content="#16a34a" />

  <link rel="icon" type="image/png" href="<?= $h($siteFavicon) ?>" />
  <link rel="preconnect" href="https://cdnjs.cloudflare.com" crossorigin>
  <link rel="preload" href="<?= $h($brandLogo) ?>" as="image" />

  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css"
        crossorigin="anonymous" />

  <style>
    /* Visual tokens (match other public pages) */
    .panel { background:#fff; border:1px solid rgba(0,0,0,.06); }
    .soft-shadow { box-shadow:0 10px 25px rgba(0,0,0,.07), 0 2px 8px rgba(0,0,0,.04); }
    .brand-logo { max-height:56px; height:auto; width:auto; }
    .focus-ring:focus-visible { outline:2px solid #22c55e; outline-offset:3px; }

    /* Background (light, calm green) */
    body{
      background: radial-gradient(1200px 600px at 10% 10%, rgba(22,163,74,.08), transparent),
                  linear-gradient(180deg, #edfdf2 0%, #effcf3 40%, #f6fdf8 100%);
      color:#1f2937;
    }

    /* Drawer */
    .drawer-enter{transform:translateX(100%);opacity:0}
    .drawer-open{transform:translateX(0);opacity:1}
    .overlay-hidden{opacity:0;pointer-events:none}
    .overlay-open{opacity:1;pointer-events:auto}
    .transition-basic{transition:transform .25s ease,opacity .25s ease}
    @media (prefers-reduced-motion:reduce){.transition-basic{transition:none!important}}

    /* Reveal */
    .reveal{opacity:0;transform:translateY(14px);transition:opacity .6s ease, transform .6s ease}
    .reveal.in{opacity:1;transform:none}
    @media (prefers-reduced-motion:reduce){.reveal,.reveal.in{opacity:1;transform:none;transition:none}}

    /* Pill badges */
    .pill{display:inline-flex;align-items:center;gap:.5rem;padding:.375rem .75rem;border-radius:9999px;background:#ecfdf5;color:#065f46;font-weight:700;border:1px solid #d1fae5}
  </style>

  <!-- Schema: CollectionPage of FAQs -->
  <script type="application/ld+json">
  {
    "@context":"https://schema.org",
    "@type":"CollectionPage",
    "name":"KlinFlow Help Center",
    "about":{"@type":"Organization","name":"KlinFlow"}
  }
  </script>
</head>
<body class="text-gray-900 overflow-x-hidden">
  <a href="#main" class="sr-only focus:not-sr-only focus-ring fixed left-4 top-4 z-50 bg-white text-gray-900 rounded px-3 py-2 soft-shadow">Skip to main</a>

<!-- HEADER (unified public header) -->
<header id="site-header" class="w-full sticky top-0 z-50 sm:static">
  <div class="mx-auto max-w-6xl px-4">
    <nav class="panel soft-shadow mt-4 rounded-2xl px-4 sm:px-6 py-3 flex items-center justify-between" aria-label="Primary">
      <a href="/" class="flex items-center gap-3">
        <!-- absolute path + stable dimensions to avoid CLS -->
        <img src="/assets/brand/logo.png" alt="KlinFlow" class="brand-logo" loading="eager" width="160" height="32">
        <span class="sr-only">KlinFlow Home</span>
      </a>

      <!-- Mobile toggle -->
      <button id="nav-toggle"
              class="sm:hidden inline-flex items-center gap-2 px-3 py-2 rounded-lg panel soft-shadow focus-ring"
              aria-controls="mobile-drawer"
              aria-expanded="false"
              aria-label="Open menu">
        <i class="fa-solid fa-bars"></i>
      </button>

      <!-- Desktop nav (keep labels/ordering identical across pages) -->
      <ul class="hidden sm:flex items-center gap-6 text-sm sm:text-base">
        <li><a href="/about"   class="opacity-80 hover:opacity-100">About Us</a></li>
        <li><a href="/contact" class="opacity-80 hover:opacity-100">Contact Us</a></li>
        <li><a href="/pricing" class="opacity-80 hover:opacity-100">Pricing</a></li>
        <li><a href="/ticket"  class="opacity-80 hover:opacity-100">Tickets</a></li>
        <li><a href="/help"    class="opacity-100 font-semibold">Help</a></li>
        <li>
          <a href="/signup" class="inline-flex items-center gap-2 rounded-full bg-green-600 text-white px-3.5 py-1.5 font-semibold hover:bg-green-700 transition focus-ring">
            Sign up free
          </a>
        </li>
      </ul>
    </nav>
  </div>
</header>

<!-- Drawer/backdrop (mobile) -->
<div id="overlay" class="fixed inset-0 bg-black/40 transition-basic overlay-hidden sm:hidden z-40"></div>
<aside id="mobile-drawer"
       class="fixed top-0 right-0 h-full w-64 max-w-full panel soft-shadow transition-basic drawer-enter sm:hidden z-50"
       role="dialog" aria-modal="true" aria-labelledby="drawer-title" aria-hidden="true" tabindex="-1">
  <div class="h-full flex flex-col">
    <div class="flex items-center justify-between px-4 py-3 border-b border-gray-200">
      <div class="flex items-center gap-2">
        <img src="/assets/brand/logo.png" alt="KlinFlow" class="h-8 w-auto">
        
      </div>
      <button id="nav-close" class="p-2 rounded-lg focus-ring" aria-label="Close menu">
        <i class="fa-solid fa-xmark"></i>
      </button>
    </div>
    <nav class="p-2" aria-label="Mobile">
      <a href="/"        class="block px-3 py-3 rounded-lg hover:bg-gray-100">Home</a>
      <a href="/about"   class="block px-3 py-3 rounded-lg hover:bg-gray-100">About Us</a>
      <a href="/contact" class="block px-3 py-3 rounded-lg hover:bg-gray-100">Contact Us</a>
      <a href="/pricing" class="block px-3 py-3 rounded-lg hover:bg-gray-100">Pricing</a>
      <a href="/ticket"  class="block px-3 py-3 rounded-lg hover:bg-gray-100">Tickets</a>
      <a href="/help"    class="block px-3 py-3 rounded-lg hover:bg-gray-100 font-semibold">Help</a>
      <a href="/signup"  class="mt-2 mx-3 inline-flex items-center justify-center rounded-full bg-green-600 text-white px-4 py-2 font-semibold hover:bg-green-700 transition w-[calc(100%-1.5rem)]">Sign up free</a>
    </nav>
    <div class="mt-auto p-3 text-xs text-gray-500 border-t border-gray-200">
      © <script>document.write(new Date().getFullYear())</script> KlinFlow
    </div>
  </div>
</aside>

  <!-- MAIN -->
  <main id="main" class="relative w-full">
    <!-- Hero -->
    <section class="mx-auto max-w-6xl px-4 pt-10 pb-6 reveal">
      <p class="pill"><i class="fa-solid fa-life-ring"></i> Help Center</p>
      <h1 class="mt-4 text-4xl sm:text-5xl font-extrabold leading-tight">How can we help?</h1>
      <p class="mt-3 text-gray-700 max-w-2xl">Find quick answers, tutorials, and guides for KlinFlow. If you’re stuck, open a support ticket — we’ll get back within one business day (Bangladesh time).</p>
    </section>

    <!-- Search -->
    <section class="mx-auto max-w-6xl px-4 pb-6 reveal">
      <label for="help-search" class="sr-only">Search help</label>
      <div class="panel soft-shadow rounded-2xl p-3 relative">
        <input id="help-search" type="search" placeholder="Search help articles…"
               class="w-full rounded-xl border border-gray-200 px-4 py-3 pr-12 focus:outline-none focus:ring-2 focus:ring-green-500/60"
               autocomplete="off" />
        <i class="fa-solid fa-magnifying-glass absolute right-5 top-1/2 -translate-y-1/2 text-gray-400"></i>
      </div>
      <p class="mt-2 text-xs text-gray-500">Try: “receipt printer”, “RBAC”, “demo tenant”.</p>
    </section>

    <!-- Topic grid -->
    <section class="mx-auto max-w-6xl px-4 pb-12 reveal">
      <div class="grid md:grid-cols-3 gap-5" id="help-topic-grid">
        <!-- Getting Started -->
        <article class="panel soft-shadow rounded-2xl p-5">
          <div class="flex items-center gap-3">
            <span class="icon-bubble bg-green-100 w-10 h-10 rounded-xl grid place-content-center">
              <i class="fa-solid fa-flag-checkered text-green-700"></i>
            </span>
            <h2 class="font-semibold text-lg">Getting Started</h2>
          </div>
          <ul class="mt-3 text-sm text-gray-700 space-y-1">
            <li><a href="/help/getting-started/first-organization" class="hover:text-green-700">Create your first organization</a></li>
            <li><a href="/help/getting-started/invite-users" class="hover:text-green-700">Invite users & set roles</a></li>
            <li><a href="/help/getting-started/connect-printers" class="hover:text-green-700">Connect receipt printers</a></li>
            <li><a href="/help/getting-started/enable-modules" class="hover:text-green-700">Enable/disable modules</a></li>
            <li><a href="/help/getting-started/import-data" class="hover:text-green-700">Import products/customers</a></li>
          </ul>
          <a href="/help#getting-started" class="inline-flex items-center gap-2 mt-3 text-green-700 font-semibold">All getting started <i class="fa-solid fa-angle-right"></i></a>
        </article>

        <!-- Billing -->
        <article class="panel soft-shadow rounded-2xl p-5">
          <div class="flex items-center gap-3">
            <span class="icon-bubble bg-amber-100 w-10 h-10 rounded-xl grid place-content-center">
              <i class="fa-solid fa-wallet text-amber-700"></i>
            </span>
            <h2 class="font-semibold text-lg">Billing</h2>
          </div>
          <ul class="mt-3 text-sm text-gray-700 space-y-1">
            <li><a href="/help/billing/plans" class="hover:text-green-700">Plans & discounts</a></li>
            <li><a href="/help/billing/invoices-vat" class="hover:text-green-700">Invoices & VAT</a></li>
            <li><a href="/help/billing/payment-methods" class="hover:text-green-700">Payment methods</a></li>
            <li><a href="/help/billing/change-modules" class="hover:text-green-700">Cancel or switch modules</a></li>
            <li><a href="/help/billing/refunds" class="hover:text-green-700">Refunds</a></li>
          </ul>
          <a href="/help#billing" class="inline-flex items-center gap-2 mt-3 text-green-700 font-semibold">All billing <i class="fa-solid fa-angle-right"></i></a>
        </article>

        <!-- Troubleshooting -->
        <article class="panel soft-shadow rounded-2xl p-5">
          <div class="flex items-center gap-3">
            <span class="icon-bubble bg-sky-100 w-10 h-10 rounded-xl grid place-content-center">
              <i class="fa-solid fa-screwdriver-wrench text-sky-700"></i>
            </span>
            <h2 class="font-semibold text-lg">Troubleshooting</h2>
          </div>
          <ul class="mt-3 text-sm text-gray-700 space-y-1">
            <li><a href="/help/troubleshooting/pos-scanner" class="hover:text-green-700">POS scanner not reading</a></li>
            <li><a href="/help/troubleshooting/hotel-room-sync" class="hover:text-green-700">HotelFlow room sync</a></li>
            <li><a href="/help/troubleshooting/print-issues" class="hover:text-green-700">Thermal print issues</a></li>
            <li><a href="/help/troubleshooting/slow-inventory" class="hover:text-green-700">Inventory slow? Quick checks</a></li>
            <li><a href="/help/troubleshooting/data-import" class="hover:text-green-700">Data import tips</a></li>
          </ul>
          <a href="/help#troubleshooting" class="inline-flex items-center gap-2 mt-3 text-green-700 font-semibold">All troubleshooting <i class="fa-solid fa-angle-right"></i></a>
        </article>

        <!-- Account & Security -->
        <article class="panel soft-shadow rounded-2xl p-5">
          <div class="flex items-center gap-3">
            <span class="icon-bubble bg-rose-100 w-10 h-10 rounded-xl grid place-content-center">
              <i class="fa-solid fa-user-shield text-rose-700"></i>
            </span>
            <h2 class="font-semibold text-lg">Account & Security</h2>
          </div>
          <ul class="mt-3 text-sm text-gray-700 space-y-1">
            <li><a href="/help/security/reset-password" class="hover:text-green-700">Reset your password</a></li>
            <li><a href="/help/security/2fa" class="hover:text-green-700">Two-factor authentication</a></li>
            <li><a href="/help/security/rbac" class="hover:text-green-700">Role-based access (RBAC)</a></li>
            <li><a href="/help/security/audit-trail" class="hover:text-green-700">View audit logs</a></li>
            <li><a href="/help/security/backups" class="hover:text-green-700">Backups & export</a></li>
          </ul>
          <a href="/help#security" class="inline-flex items-center gap-2 mt-3 text-green-700 font-semibold">All security topics <i class="fa-solid fa-angle-right"></i></a>
        </article>

        <!-- Integrations -->
        <article class="panel soft-shadow rounded-2xl p-5">
          <div class="flex items-center gap-3">
            <span class="icon-bubble bg-indigo-100 w-10 h-10 rounded-xl grid place-content-center">
              <i class="fa-solid fa-plug text-indigo-700"></i>
            </span>
            <h2 class="font-semibold text-lg">Integrations</h2>
          </div>
          <ul class="mt-3 text-sm text-gray-700 space-y-1">
            <li><a href="/help/integrations/sms" class="hover:text-green-700">SMS gateways</a></li>
            <li><a href="/help/integrations/payments" class="hover:text-green-700">Local payment providers</a></li>
            <li><a href="/help/integrations/printers" class="hover:text-green-700">Printer models</a></li>
            <li><a href="/help/integrations/webhooks" class="hover:text-green-700">Webhooks</a></li>
            <li><a href="/help/integrations/api" class="hover:text-green-700">API basics</a></li>
          </ul>
          <a href="/help#integrations" class="inline-flex items-center gap-2 mt-3 text-green-700 font-semibold">All integrations <i class="fa-solid fa-angle-right"></i></a>
        </article>

        <!-- Others -->
        <article class="panel soft-shadow rounded-2xl p-5">
          <div class="flex items-center gap-3">
            <span class="icon-bubble bg-slate-100 w-10 h-10 rounded-xl grid place-content-center">
              <i class="fa-solid fa-circle-info text-slate-700"></i>
            </span>
            <h2 class="font-semibold text-lg">Other topics</h2>
          </div>
          <ul class="mt-3 text-sm text-gray-700 space-y-1">
            <li><a href="/help/other/language" class="hover:text-green-700">English & Bangla UI</a></li>
            <li><a href="/help/other/timezone" class="hover:text-green-700">Bangladesh timezone</a></li>
            <li><a href="/help/other/accessibility" class="hover:text-green-700">Accessibility</a></li>
            <li><a href="/help/other/data-retention" class="hover:text-green-700">Data retention policy</a></li>
            <li><a href="/help/other/contact" class="hover:text-green-700">Contact & SLA</a></li>
          </ul>
          <a href="/help#other" class="inline-flex items-center gap-2 mt-3 text-green-700 font-semibold">More topics <i class="fa-solid fa-angle-right"></i></a>
        </article>
      </div>
    </section>

    <!-- CTA: Still need help -->
    <section class="mx-auto max-w-6xl px-4 pb-16 reveal">
      <div class="bg-gradient-to-br from-green-600 to-emerald-600 text-white rounded-3xl p-6 sm:p-10 soft-shadow grid lg:grid-cols-3 gap-6 items-center">
        <div class="lg:col-span-2">
          <h2 class="text-2xl sm:text-3xl font-extrabold">Still need help?</h2>
          <p class="mt-2 opacity-90">Open a support ticket and we’ll respond within one business day (BST).</p>
        </div>
        <div class="flex gap-3">
          <a href="/ticket" class="inline-flex items-center gap-2 bg-white text-green-700 font-semibold px-5 py-3 rounded-xl hover:bg-green-50 transition">
            Open a ticket <i class="fa-solid fa-paper-plane"></i>
          </a>
          <a href="/contact" class="inline-flex items-center gap-2 bg-green-700/30 text-white font-semibold px-5 py-3 rounded-xl hover:bg-green-700/40 transition">
            Contact us <i class="fa-solid fa-envelope-open-text"></i>
          </a>
        </div>
      </div>
    </section>
  </main>

  <!-- FOOTER -->
  <footer id="site-footer" class="border-t">
    <div class="mx-auto max-w-6xl px-4 py-6 text-sm flex flex-col sm:flex-row items-center justify-between">
      <div class="opacity-80">© <script>document.write(new Date().getFullYear())</script> KlinFlow</div>
      <div class="opacity-80">Designed by: <span class="font-semibold">DEPENDCORE</span></div>
    </div>
  </footer>

  <script>
  // ---------------------------------------------------------------------
  // Drawer + focus management (mobile)
  // ---------------------------------------------------------------------
  const toggleBtn = document.getElementById('nav-toggle'),
        closeBtn  = document.getElementById('nav-close'),
        drawer    = document.getElementById('mobile-drawer'),
        overlay   = document.getElementById('overlay');
  let lastFocus;

  function openDrawer() {
    lastFocus = document.activeElement;
    drawer.classList.remove('drawer-enter');
    drawer.classList.add('drawer-open');
    overlay.classList.remove('overlay-hidden');
    overlay.classList.add('overlay-open');
    drawer.setAttribute('aria-hidden', 'false');
    toggleBtn?.setAttribute('aria-expanded', 'true');
    document.documentElement.style.overflow = 'hidden';
    document.body.style.overflow = 'hidden';
    (drawer.querySelector('a,button,[tabindex="0"]') || drawer)
      .focus({ preventScroll: true });
  }

  function closeDrawer() {
    drawer.classList.add('drawer-enter');
    drawer.classList.remove('drawer-open');
    overlay.classList.add('overlay-hidden');
    overlay.classList.remove('overlay-open');
    drawer.setAttribute('aria-hidden', 'true');
    toggleBtn?.setAttribute('aria-expanded', 'false');
    document.documentElement.style.overflow = '';
    document.body.style.overflow = '';
    lastFocus?.focus({ preventScroll: true });
  }

  toggleBtn?.addEventListener('click', openDrawer, { passive: true });
  closeBtn?.addEventListener('click', closeDrawer, { passive: true });
  overlay?.addEventListener('click', closeDrawer, { passive: true });
  document.addEventListener('keydown', e => { if (e.key === 'Escape') closeDrawer(); }, { passive: true });


  // ---------------------------------------------------------------------
  // Reveal on scroll (fade + lift animation)
  // ---------------------------------------------------------------------
  const _revEls = Array.from(document.querySelectorAll('.reveal'));
  if ('IntersectionObserver' in window) {
    const io = new IntersectionObserver(entries => {
      entries.forEach(e => { if (e.isIntersecting) e.target.classList.add('in'); });
    }, { threshold: .12 });
    _revEls.forEach(el => io.observe(el));
  } else {
    _revEls.forEach(el => el.classList.add('in'));
  }


  // ---------------------------------------------------------------------
  // Optional: hydrate topics from /assets/help.json
  // ---------------------------------------------------------------------
  // Expected shape:
  // {
  //   "Getting Started":[{"title":"...","href":"/help/..."}],
  //   "Billing":[...],
  //   "Troubleshooting":[...],
  //   "Account & Security":[...],
  //   "Integrations":[...],
  //   "Other":[...]
  // }

  (async () => {
    try {
      const res = await fetch('/assets/help.json', { cache: 'no-store' });
      if (!res.ok) return;
      const data = await res.json();
      if (!data || typeof data !== 'object') return;

      const map = {
        'Getting Started': 'Getting Started',
        'Billing': 'Billing',
        'Troubleshooting': 'Troubleshooting',
        'Account & Security': 'Account & Security',
        'Integrations': 'Integrations',
        'Other': 'Other topics'
      };

      for (const [key, list] of Object.entries(data)) {
        const title = map[key] || key;
        const card = Array.from(document.querySelectorAll('#help-topic-grid article'))
          .find(a => (a.querySelector('h2')?.textContent.trim() === title));
        if (!card || !Array.isArray(list)) continue;

        const ul = card.querySelector('ul');
        if (!ul) continue;

        ul.innerHTML = list.slice(0, 5).map(it => {
          const t = (it.title || '').replace(/</g, '&lt;');
          const h = (it.href || '#').replace(/"/g, '%22');
          return `<li><a class="hover:text-green-700" href="${h}">${t}</a></li>`;
        }).join('');
      }
    } catch (_) { /* silent fail – keep static content */ }
  })();
</script>
  
  <script> window.chtlConfig = { chatbotId: "8748546719" } </script>
<script async data-id="8748546719" id="chtl-script" type="text/javascript" src="https://chatling.ai/js/embed.js"></script>
  
</body>
</html>