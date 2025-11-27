<?php
declare(strict_types=1);
/**
 * apps/Public/Views/changelog.php
 * Public changelog page (auto-hydrated)
 * - Pulls updates from /assets/changelog.json if present
 * - Matches KlinFlow’s public layout & mobile drawer
 */

$h = fn($s)=>htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
?>
<!doctype html>
<html lang="en" class="h-full">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>KlinFlow Changelog — Latest Updates & Improvements</title>
  <meta name="description" content="Track KlinFlow product updates, module releases, and security improvements — continuously improving your ERP experience." />
  <link rel="canonical" href="https://www.klinflow.com/changelog" />

  <!-- Open Graph -->
  <meta property="og:title" content="KlinFlow Changelog — What’s New" />
  <meta property="og:description" content="Latest product updates, fixes, and improvements across POS, HotelFlow, Bhata, School, MedFlow, and DMS modules." />
  <meta property="og:type" content="website" />
  <meta property="og:url" content="https://www.klinflow.com/changelog" />
  <meta property="og:image" content="https://www.klinflow.com/assets/brand/klinflow-social.jpg" />

  <!-- Feeds -->
  <link rel="alternate" type="application/rss+xml"  title="KlinFlow Changelog (RSS)"  href="/changelog.xml" />
  <link rel="alternate" type="application/atom+xml" title="KlinFlow Changelog (Atom)" href="/changelog.atom" />

  <link rel="icon" type="image/png" href="/assets/brand/favicon.png" />
  <script src="https://cdn.tailwindcss.com"></script>

  <style>
    html { scroll-behavior:smooth; }
    .panel { background:#fff; border:1px solid rgba(0,0,0,.06); }
    .soft-shadow { box-shadow:0 10px 25px rgba(0,0,0,.07), 0 2px 8px rgba(0,0,0,.04); }
    .brand-logo  { height:40px; width:auto; }
    .focus-ring:focus-visible { outline:2px solid #22c55e; outline-offset:3px; }

    /* Drawer */
    .drawer-enter{transform:translateX(100%);opacity:0}
    .drawer-open{transform:translateX(0);opacity:1}
    .overlay-hidden{opacity:0;pointer-events:none}
    .overlay-open{opacity:1;pointer-events:auto}
    .transition-basic{transition:transform .25s ease,opacity .25s ease}
    @media (prefers-reduced-motion:reduce){.transition-basic{transition:none!important}}

    /* Reveal */
    .fade-in {opacity:0; transform:translateY(10px); transition:all .6s;}
    .fade-in.in {opacity:1; transform:none;}
  </style>

  <!-- Minimal Schema for a changelog list -->
  <script type="application/ld+json">
  {
    "@context":"https://schema.org",
    "@type":"CollectionPage",
    "name":"KlinFlow Changelog",
    "url":"https://www.klinflow.com/changelog",
    "isPartOf":{"@type":"WebSite","name":"KlinFlow","url":"https://www.klinflow.com/"}
  }
  </script>
</head>

<body class="bg-white text-gray-800 overflow-x-hidden">

  <a href="#main" class="sr-only focus:not-sr-only focus-ring fixed left-4 top-4 z-50 bg-white text-gray-900 rounded px-3 py-2 soft-shadow">Skip to main</a>

  <!-- HEADER (consistent with your other public pages) -->
  <header id="site-header" class="w-full sticky top-0 z-50 bg-white/85 backdrop-blur border-b border-gray-200">
    <div class="mx-auto max-w-7xl px-6">
      <nav class="panel soft-shadow mt-4 rounded-2xl px-4 sm:px-6 py-3 flex items-center justify-between" aria-label="Primary">
        <a href="/" class="flex items-center gap-3" aria-label="KlinFlow Home">
          <img src="/assets/brand/logo.png" alt="KlinFlow" class="brand-logo" loading="eager" decoding="async">
        </a>

        <!-- Mobile menu button -->
        <button id="nav-toggle"
                class="sm:hidden inline-flex items-center gap-2 px-3 py-2 rounded-lg panel soft-shadow focus-ring"
                aria-controls="mobile-drawer"
                aria-expanded="false"
                aria-label="Open menu">
          <!-- Bars icon -->
          <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16"/>
          </svg>
          Menu
        </button>

        <!-- Desktop nav -->
        <ul class="hidden sm:flex items-center gap-6 text-sm sm:text-base">
          <li><a href="/about"    class="opacity-80 hover:opacity-100">About Us</a></li>
          <li><a href="/contact"  class="opacity-80 hover:opacity-100">Contact Us</a></li>
          <li><a href="/pricing"  class="opacity-80 hover:opacity-100">Pricing</a></li>
          <li><a href="/help"     class="opacity-80 hover:opacity-100">Help</a></li>
          <li><a href="/changelog" class="opacity-100 font-semibold text-green-700">Changelog</a></li>
        </ul>
      </nav>
    </div>
  </header>

  <!-- Drawer/backdrop -->
  <div id="overlay" class="fixed inset-0 bg-black/40 transition-basic overlay-hidden sm:hidden z-40"></div>
  <aside id="mobile-drawer"
         class="fixed top-0 right-0 h-full w-64 max-w-full panel soft-shadow transition-basic drawer-enter sm:hidden z-50"
         role="dialog" aria-modal="true" aria-labelledby="drawer-title" aria-hidden="true" tabindex="-1">
    <div class="h-full flex flex-col">
      <div class="flex items-center justify-between px-4 py-3 border-b border-gray-200">
        <div class="flex items-center gap-2">
          <img src="/assets/brand/logo.png" alt="KlinFlow" class="h-8 w-auto">
          <span id="drawer-title" class="font-semibold">Menu</span>
        </div>
        <button id="nav-close" class="p-2 rounded-lg focus-ring" aria-label="Close menu">
          <!-- X icon -->
          <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
          </svg>
        </button>
      </div>
      <nav class="p-2" aria-label="Mobile">
        <a href="/"         class="block px-3 py-3 rounded-lg hover:bg-gray-100">Home</a>
        <a href="/about"    class="block px-3 py-3 rounded-lg hover:bg-gray-100">About Us</a>
        <a href="/contact"  class="block px-3 py-3 rounded-lg hover:bg-gray-100">Contact Us</a>
        <a href="/pricing"  class="block px-3 py-3 rounded-lg hover:bg-gray-100">Pricing</a>
        <a href="/help"     class="block px-3 py-3 rounded-lg hover:bg-gray-100">Help</a>
        <a href="/changelog" class="block px-3 py-3 rounded-lg hover:bg-gray-100 font-semibold">Changelog</a>
      </nav>
      <div class="mt-auto p-3 text-xs text-gray-500 border-t border-gray-200">
        © <script>document.write(new Date().getFullYear())</script> KlinFlow
      </div>
    </div>
  </aside>

  <!-- MAIN -->
  <main id="main" class="relative w-full">
    <section class="mx-auto max-w-3xl px-6 pt-12 pb-4">
      <div class="text-center mb-10">
        <span class="inline-block px-3 py-1 rounded-full text-xs font-semibold bg-green-100 text-green-700">Product updates</span>
        <h1 class="text-4xl font-extrabold mt-3 text-gray-900">What’s new at KlinFlow</h1>
        <p class="mt-3 text-gray-600">
          Latest improvements, module releases, and security updates — built to keep your operations smoother every day.
        </p>

        <div class="mt-5 flex flex-wrap items-center justify-center gap-3 text-sm">
          <a href="/changelog.xml"  class="inline-flex items-center gap-2 px-3 py-2 rounded-lg border border-green-200 text-green-700 hover:bg-green-50">
            <!-- RSS icon -->
            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="currentColor"><path d="M6 18a2 2 0 11.001 4.001A2 2 0 016 18zm-4-8v3a11 11 0 0111 11h3C16 16.477 7.523 8 2 8zm0-6v3c11.598 0 21 9.402 21 21h3C26 12.536 13.464 0 2 0z"/></svg>
            RSS
          </a>
          <a href="/changelog.atom" class="inline-flex items-center gap-2 px-3 py-2 rounded-lg border border-green-200 text-green-700 hover:bg-green-50">
            <!-- Atom icon -->
            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2C6 2 2 6 2 12s4 10 10 10 10-4 10-10S18 2 12 2zm1 17.93V20a8 8 0 110-16v.07A10.001 10.001 0 0020 12a10 10 0 00-7 7.93z"/></svg>
            Atom
          </a>
        </div>
      </div>

      <!-- Timeline -->
      <ul id="changelog-list" class="relative border-l border-green-200 space-y-8"></ul>

      <!-- Loading / fallback -->
      <div id="changelog-fallback" class="text-center text-gray-500 mt-8 hidden">
        <p>No updates found yet. Check back soon!</p>
      </div>
    </section>
  </main>

  <!-- FOOTER (light brand) -->
  <footer class="bg-green-50 border-t border-green-100 mt-12">
    <div class="max-w-7xl mx-auto px-6 py-10 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-8 text-sm">
      <!-- Brand info -->
      <div class="sm:col-span-2 lg:col-span-1">
        <img src="/assets/brand/logo.png" alt="KlinFlow" class="h-9 mb-3" />
        <p class="text-gray-700 leading-relaxed text-sm">
          KlinFlow — multi-tenant ERP platform for Bangladesh. Simple, secure, scalable.
        </p>
      </div>

      <!-- Explore -->
      <div>
        <h3 class="font-semibold mb-3 text-gray-900">Explore</h3>
        <ul class="space-y-2">
          <li><a href="/" class="hover:text-green-600">Home</a></li>
          <li><a href="/pricing" class="hover:text-green-600">Pricing</a></li>
          <li><a href="/help" class="hover:text-green-600">Help Center</a></li>
          <li><a href="/contact" class="hover:text-green-600">Contact</a></li>
        </ul>
      </div>

      <!-- Resources -->
      <div>
        <h3 class="font-semibold mb-3 text-gray-900">Resources</h3>
        <ul class="space-y-2">
          <li><a href="/security" class="hover:text-green-600">Security</a></li>
          <li><a href="/terms" class="hover:text-green-600">Terms</a></li>
          <li><a href="/privacy" class="hover:text-green-600">Privacy</a></li>
        </ul>
      </div>

      <!-- Social -->
      <div class="sm:col-span-2 lg:col-span-1">
        <h3 class="font-semibold mb-3 text-gray-900">Stay Connected</h3>
        <div class="flex gap-3">
          <a href="https://facebook.com/klinflow" class="hover:text-green-600" aria-label="Facebook">
            <svg xmlns="http://www.w3.org/2000/svg" fill="currentColor" class="w-5 h-5" viewBox="0 0 24 24"><path d="M22 12a10 10 0 1 0-11.5 9.9v-7H8v-3h2.5V9.5A3.5 3.5 0 0 1 14 6h2v3h-1.6c-1 0-1.4.5-1.4 1.3V12H16l-.5 3h-2.5v7A10 10 0 0 0 22 12z"/></svg>
          </a>
          <a href="https://linkedin.com/company/klinflow" class="hover:text-green-600" aria-label="LinkedIn">
            <svg xmlns="http://www.w3.org/2000/svg" fill="currentColor" class="w-5 h-5" viewBox="0 0 24 24"><path d="M4.98 3.5C4.98 5 3.93 6 2.5 6S0 5 0 3.5 1.05 1 2.5 1s2.48 1 2.48 2.5zM.5 8h4v16h-4V8zm7 0h3.9v2.2h.1c.5-.9 1.7-2.3 3.9-2.3 4.2 0 5 2.8 5 6.5V24h-4v-8c0-1.9 0-4.3-2.6-4.3s-3 2-3 4.1V24h-4V8z"/></svg>
          </a>
        </div>
      </div>
    </div>
    <div class="border-t border-green-100 py-4 text-center text-xs text-gray-600">
      © <script>document.write(new Date().getFullYear())</script> KlinFlow — Designed by <strong>DEPENDCORE</strong>
    </div>
  </footer>

  <!-- SCRIPTS (single, deduped) -->
  <script>
    // Drawer + focus management
    const toggleBtn=document.getElementById('nav-toggle'),
          closeBtn=document.getElementById('nav-close'),
          drawer=document.getElementById('mobile-drawer'),
          overlay=document.getElementById('overlay');
    let lastFocus;

    function openDrawer(){
      lastFocus=document.activeElement;
      drawer.classList.remove('drawer-enter'); drawer.classList.add('drawer-open');
      overlay.classList.remove('overlay-hidden'); overlay.classList.add('overlay-open');
      drawer.setAttribute('aria-hidden','false'); toggleBtn && toggleBtn.setAttribute('aria-expanded','true');
      document.documentElement.style.overflow='hidden'; document.body.style.overflow='hidden';
      (drawer.querySelector('a,button,[tabindex="0"]')||drawer).focus({preventScroll:true});
    }
    function closeDrawer(){
      drawer.classList.add('drawer-enter'); drawer.classList.remove('drawer-open');
      overlay.classList.add('overlay-hidden'); overlay.classList.remove('overlay-open');
      drawer.setAttribute('aria-hidden','true'); toggleBtn && toggleBtn.setAttribute('aria-expanded','false');
      document.documentElement.style.overflow=''; document.body.style.overflow='';
      lastFocus && lastFocus.focus({preventScroll:true});
    }
    toggleBtn && toggleBtn.addEventListener('click', openDrawer, {passive:true});
    closeBtn  && closeBtn.addEventListener('click', closeDrawer, {passive:true});
    overlay   && overlay.addEventListener('click', closeDrawer, {passive:true});
    document.addEventListener('keydown', (e)=>{ if(e.key==='Escape') closeDrawer(); }, {passive:true});

    // Hydrate changelog list from JSON (with graceful fallback)
    (async () => {
      const list = document.getElementById('changelog-list');
      const fallback = document.getElementById('changelog-fallback');

      try {
        const res = await fetch('/assets/changelog.json', {cache:'no-store'});
        const data = res.ok ? await res.json() : [];
        const updates = Array.isArray(data) && data.length ? data : [
          {version:'v2.4', date:'2025-11-01', note:'Added multi-organization purchase order workflow.'},
          {version:'v2.3', date:'2025-10-12', note:'Improved POS performance and supplier lookup speed.'},
          {version:'v2.2', date:'2025-09-25', note:'Security hardening and improved API authentication.'}
        ];

        list.innerHTML = updates.map((u)=>`
          <li class="fade-in relative pl-8">
            <span class="absolute -left-[9px] top-2 w-4 h-4 rounded-full bg-green-600 shadow-md"></span>
            <div class="bg-white border border-green-100 rounded-2xl p-5 shadow-sm hover:shadow-md transition">
              <div class="flex items-center justify-between gap-4">
                <h3 class="font-semibold text-lg text-gray-900">${(u.version||'vNext')}</h3>
                <span class="text-sm text-gray-500">${(u.date||'')}</span>
              </div>
              <p class="mt-2 text-gray-700 text-sm leading-relaxed">${(u.note||'')}</p>
            </div>
          </li>
        `).join('');

        // Reveal on scroll
        const els = Array.from(document.querySelectorAll('.fade-in'));
        if ('IntersectionObserver' in window){
          const io = new IntersectionObserver(es => es.forEach(e => { if(e.isIntersecting) e.target.classList.add('in'); }), {threshold:.12});
          els.forEach(el => io.observe(el));
        } else {
          els.forEach(el => el.classList.add('in'));
        }
      } catch (e) {
        fallback.classList.remove('hidden');
      }
    })();
  </script>
</body>
</html>