<!doctype html>
<html lang="en" class="h-full">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />

  <title>Why KlinFlow — Speed, Security, Support, Zero Downtime</title>
  <meta name="description" content="Why choose KlinFlow: secure multi-tenant architecture, performance under load, daily backups, RBAC with audit trail, and Bangladesh-ready support." />
  <link rel="icon" type="image/png" href="/assets/brand/logo.png" />
  <link rel="preload" href="/assets/brand/logo.png" as="image" />

  <script src="https://cdn.tailwindcss.com"></script>
  <style>
    .panel{background:#fff;border:1px solid rgba(0,0,0,.06)}
    .soft-shadow{box-shadow:0 10px 25px rgba(0,0,0,.07),0 2px 8px rgba(0,0,0,.04)}
    .focus-ring:focus-visible{outline:2px solid #22c55e;outline-offset:3px}
    .hero-bg{background:
      radial-gradient(1000px 600px at 0% 0%, rgba(34,197,94,.10), transparent),
      linear-gradient(180deg,#f6fdf8 0%,#ffffff 60%)}
    .dot-grid{background-image: radial-gradient(rgba(16,185,129,.15) 1px, transparent 1px);
      background-size: 14px 14px; background-position: -3px -3px;}
    .illus{aspect-ratio: 16/10}
    .card-illus{background:linear-gradient(180deg,#f8fffb 0%,#f7faf9 100%)}
    .chev{transition:transform .2s ease}
    .rotate-180{transform:rotate(180deg)}
    @media (prefers-reduced-motion:reduce){.chev{transition:none}}
  </style>

  <!-- FAQ + Breadcrumb schema -->
  <script type="application/ld+json">
  {
    "@context":"https://schema.org",
    "@type":"BreadcrumbList",
    "itemListElement":[
      {"@type":"ListItem","position":1,"name":"Home","item":"https://www.klinflow.com/"},
      {"@type":"ListItem","position":2,"name":"Why KlinFlow","item":"https://www.klinflow.com/why"}
    ]
  }
  </script>
</head>
<body class="text-gray-900 overflow-x-hidden">

  <!-- HEADER (kept consistent & mobile off-canvas) -->
  <header id="site-header" class="w-full sticky top-0 z-50 hero-bg">
    <div class="mx-auto max-w-7xl px-4">
      <nav class="panel soft-shadow mt-4 rounded-2xl px-4 sm:px-6 py-3 flex items-center justify-between" aria-label="Primary">
        <a href="/" class="flex items-center gap-3">
          <img src="/assets/brand/logo.png" alt="KlinFlow" class="h-9 w-auto" loading="eager" />
          <span class="sr-only">KlinFlow</span>
        </a>

        <button id="nav-toggle"
                class="sm:hidden inline-flex items-center gap-2 px-3 py-2 rounded-lg panel soft-shadow focus-ring"
                aria-controls="mobile-drawer" aria-expanded="false" aria-label="Open menu">
          <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
        </button>

        <ul class="hidden sm:flex items-center gap-6 text-sm sm:text-base">
          <li><a href="/about" class="opacity-80 hover:opacity-100">About</a></li>
          <li><a href="/pricing" class="opacity-80 hover:opacity-100">Pricing</a></li>
          <li><a href="/changelog" class="opacity-80 hover:opacity-100">Changelog</a></li>
          <li><a href="/help" class="opacity-80 hover:opacity-100">Help</a></li>
          <li><a href="/why" class="opacity-100 font-semibold text-green-700">Why KlinFlow</a></li>
        </ul>
      </nav>
    </div>
  </header>

  <!-- Drawer/backdrop -->
  <div id="overlay" class="fixed inset-0 bg-black/40 transition-all opacity-0 pointer-events-none sm:hidden z-40"></div>
  <aside id="mobile-drawer"
         class="fixed top-0 right-0 h-full w-64 max-w-full panel soft-shadow transition-all translate-x-full sm:hidden z-50"
         role="dialog" aria-modal="true" aria-labelledby="drawer-title" aria-hidden="true" tabindex="-1">
    <div class="h-full flex flex-col">
      <div class="flex items-center justify-between px-4 py-3 border-b border-gray-200">
        <div class="flex items-center gap-2">
          <img src="/assets/brand/logo.png" alt="KlinFlow" class="h-8 w-auto">
          <span id="drawer-title" class="font-semibold">Menu</span>
        </div>
        <button id="nav-close" class="p-2 rounded-lg focus-ring" aria-label="Close menu">
          <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path stroke-linecap="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
        </button>
      </div>
      <nav class="p-2" aria-label="Mobile">
        <a href="/"        class="block px-3 py-3 rounded-lg hover:bg-gray-100">Home</a>
        <a href="/about"   class="block px-3 py-3 rounded-lg hover:bg-gray-100">About</a>
        <a href="/pricing" class="block px-3 py-3 rounded-lg hover:bg-gray-100">Pricing</a>
        <a href="/changelog" class="block px-3 py-3 rounded-lg hover:bg-gray-100">Changelog</a>
        <a href="/help"    class="block px-3 py-3 rounded-lg hover:bg-gray-100">Help</a>
        <a href="/why"     class="block px-3 py-3 rounded-lg bg-green-50 text-green-700 font-semibold">Why KlinFlow</a>
      </nav>
      <div class="mt-auto p-3 text-xs text-gray-500 border-t border-gray-200">
        © <script>document.write(new Date().getFullYear())</script> KlinFlow
      </div>
    </div>
  </aside>

  <!-- HERO with breadcrumb (right illustration kept) -->
  <section class="hero-bg border-b border-green-100">
    <div class="mx-auto max-w-7xl px-4 py-14">
      <div class="grid lg:grid-cols-2 gap-10 items-center">
        <div>
          <span class="inline-flex items-center gap-2 text-xs font-semibold uppercase tracking-wider text-green-800 bg-green-100 px-3 py-1 rounded-full soft-shadow">
            <svg xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2a10 10 0 100 20 10 10 0 000-20zM7 12h10M12 7v10"/></svg>
            Why KlinFlow
          </span>
          <h1 class="mt-4 text-4xl sm:text-5xl font-extrabold leading-tight">Built for speed, security & no-drama support.</h1>
          <p class="mt-3 text-gray-700 max-w-xl">
            Same clean 2-column layout you liked. We’ve kept the **illustrations on the right**,
            content on the left—focused on low-maintenance ops, performance under load, and
            Bangladesh-ready support.
          </p>
          <div class="mt-5 flex flex-wrap gap-3">
            <a href="/pricing" class="inline-flex items-center gap-2 rounded-xl bg-green-600 text-white px-5 py-3 font-semibold soft-shadow hover:bg-green-700 focus-ring">
              See Pricing
            </a>
            <a href="/security" class="inline-flex items-center gap-2 rounded-xl bg-white border border-green-200 text-green-700 px-5 py-3 font-semibold soft-shadow hover:bg-green-50 focus-ring">
              Security & Isolation
            </a>
          </div>
        </div>
        <!-- hero illustration right -->
        <div class="panel soft-shadow rounded-2xl p-6 dot-grid">
          <div class="card-illus rounded-xl illus grid place-items-center">
            <!-- simple lock/server illustration -->
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 420 240" class="w-full h-full">
              <rect x="20" y="30" width="200" height="120" rx="14" fill="#ffffff" stroke="#d1fae5"/>
              <rect x="240" y="60" width="160" height="90" rx="14" fill="#ecfdf5" stroke="#bbf7d0"/>
              <circle cx="320" cy="105" r="28" fill="#16a34a"/>
              <rect x="305" y="97" width="30" height="16" rx="3" fill="#fff"/>
              <rect x="50" y="55" width="140" height="10" rx="4" fill="#e5e7eb"/>
              <rect x="50" y="75" width="140" height="10" rx="4" fill="#e5e7eb"/>
              <rect x="50" y="95" width="90" height="10" rx="4" fill="#e5e7eb"/>
            </svg>
          </div>
        </div>
      </div>
    </div>
  </section>

  <!-- BLOCK 1: Manage less (left) + security illus (right) -->
  <section class="mx-auto max-w-7xl px-4 py-10">
    <div class="panel soft-shadow rounded-2xl overflow-hidden">
      <div class="grid lg:grid-cols-2">
        <div class="p-6 sm:p-10">
          <h2 class="text-xl font-bold mb-2">Manage Less.</h2>
          <p class="text-gray-700 mb-5">Spend less time on tech and more time on growth.</p>

          <div class="space-y-4 text-sm">
            <div>
              <div class="font-semibold">Updates</div>
              <div class="text-gray-700">We handle server updates, security patches and core features—without breaking your flow.</div>
            </div>
            <div>
              <div class="font-semibold">Always-On Security</div>
              <div class="text-gray-700">TLS everywhere, hashed credentials, DDoS protection and daily offsite backups with retention.</div>
            </div>
            <div>
              <div class="font-semibold">Compliance</div>
              <div class="text-gray-700">Pragmatic SOC-style practices and auditability help you meet NGO/corporate procurement needs.</div>
            </div>
          </div>
        </div>

        <div class="p-6 sm:p-10 bg-white dot-grid">
          <div class="card-illus rounded-xl illus grid place-items-center">
            <!-- shield + checklist -->
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 420 240" class="w-full h-full">
              <rect x="250" y="40" width="140" height="25" rx="8" fill="#d1fae5"/>
              <rect x="250" y="80" width="140" height="25" rx="8" fill="#ecfdf5"/>
              <rect x="250" y="120" width="140" height="25" rx="8" fill="#f0fdf4"/>
              <path d="M90 70l42 14v28c0 30-42 48-42 48s-42-18-42-48V84l42-14z" fill="#16a34a" opacity=".2" stroke="#16a34a"/>
              <circle cx="90" cy="110" r="10" fill="#16a34a"/>
              <path d="M85 110l4 4 8-8" stroke="#fff" stroke-width="3" fill="none" stroke-linecap="round"/>
            </svg>
          </div>
        </div>
      </div>
    </div>
  </section>

  <!-- BLOCK 2: Access Experts (right text on right? keep your pattern: text left, illus right) -->
  <section class="mx-auto max-w-7xl px-4 pb-10">
    <div class="panel soft-shadow rounded-2xl overflow-hidden">
      <div class="grid lg:grid-cols-2">
        <div class="p-6 sm:p-10">
          <h2 class="text-xl font-bold mb-2">Access Experts.</h2>
          <p class="text-gray-700 mb-5">Get support from people who understand your needs.</p>

          <div class="space-y-4 text-sm">
            <div>
              <div class="font-semibold">24/7 Support</div>
              <div class="text-gray-700">Bangladesh-aligned hours with quick response for incidents and how-to questions.</div>
            </div>
            <div>
              <div class="font-semibold">Onboarding</div>
              <div class="text-gray-700">Fully managed setup, guided data import and best-practice RBAC.</div>
            </div>
            <div>
              <div class="font-semibold">Performance Monitoring</div>
              <div class="text-gray-700">Real-time analytics to surface bottlenecks and keep pages fast.</div>
            </div>
          </div>
        </div>

        <div class="p-6 sm:p-10 bg-white dot-grid">
          <div class="card-illus rounded-xl illus grid place-items-center">
            <!-- headset + window -->
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 420 240" class="w-full h-full">
              <rect x="40" y="40" width="220" height="140" rx="12" fill="#fff" stroke="#e5e7eb"/>
              <rect x="40" y="40" width="220" height="28" rx="12" fill="#f0fdf4"/>
              <circle cx="66" cy="54" r="5" fill="#16a34a"/>
              <circle cx="82" cy="54" r="5" fill="#86efac"/>
              <circle cx="98" cy="54" r="5" fill="#bbf7d0"/>
              <rect x="280" y="70" width="90" height="90" rx="45" fill="#16a34a"/>
              <rect x="296" y="108" width="58" height="24" rx="6" fill="#fff"/>
              <rect x="85" y="85" width="160" height="10" rx="4" fill="#e5e7eb"/>
              <rect x="85" y="105" width="120" height="10" rx="4" fill="#e5e7eb"/>
              <rect x="85" y="125" width="100" height="10" rx="4" fill="#e5e7eb"/>
            </svg>
          </div>
        </div>
      </div>
    </div>
  </section>

  <!-- BLOCK 3: Performance & global (same pattern) -->
  <section class="mx-auto max-w-7xl px-4 pb-16">
    <div class="panel soft-shadow rounded-2xl overflow-hidden">
      <div class="grid lg:grid-cols-2">
        <div class="p-6 sm:p-10">
          <h2 class="text-xl font-bold mb-2">Convert More.</h2>
          <p class="text-gray-700 mb-5">Keep customers happy with ultra-fast experiences.</p>

          <div class="space-y-4 text-sm">
            <div>
              <div class="font-semibold">High Performance</div>
              <div class="text-gray-700">Nginx, PHP-FPM tuning, query optimization and caching designed for load.</div>
            </div>
            <div>
              <div class="font-semibold">Advanced Caching</div>
              <div class="text-gray-700">Static + application-aware caching layers so heavy pages still feel instant.</div>
            </div>
            <div>
              <div class="font-semibold">Global Reach</div>
              <div class="text-gray-700">Multi-region infra options; your site stays fast for distributed teams.</div>
            </div>
          </div>
        </div>

        <div class="p-6 sm:p-10 bg-white dot-grid">
          <div class="card-illus rounded-xl illus grid place-items-center">
            <!-- chart + globe dots -->
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 420 240" class="w-full h-full">
              <rect x="40" y="40" width="160" height="110" rx="12" fill="#fff" stroke="#e5e7eb"/>
              <path d="M60 130l28-30 26 12 32-40 28 18" fill="none" stroke="#16a34a" stroke-width="3"/>
              <circle cx="300" cy="120" r="50" fill="#ecfdf5" stroke="#bbf7d0"/>
              <circle cx="280" cy="110" r="4" fill="#16a34a"/>
              <circle cx="320" cy="130" r="4" fill="#16a34a"/>
              <circle cx="305" cy="95" r="4" fill="#16a34a"/>
              <circle cx="330" cy="105" r="4" fill="#16a34a"/>
            </svg>
          </div>
        </div>
      </div>
    </div>
  </section>

  <!-- FOOTER (light brand) -->
  <footer class="bg-green-50 border-t border-green-100">
    <div class="max-w-7xl mx-auto px-4 py-10 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-8 text-sm">
      <div class="sm:col-span-2 lg:col-span-1">
        <img src="/assets/brand/logo.png" alt="KlinFlow" class="h-9 mb-3" />
        <p class="text-gray-700 leading-relaxed">Simple, secure, scalable multi-tenant ERP for Bangladesh.</p>
      </div>
      <div>
        <h3 class="font-semibold mb-3 text-gray-900">Explore</h3>
        <ul class="space-y-2">
          <li><a href="/pricing" class="hover:text-green-700">Pricing</a></li>
          <li><a href="/security" class="hover:text-green-700">Security</a></li>
          <li><a href="/changelog" class="hover:text-green-700">Changelog</a></li>
        </ul>
      </div>
      <div>
        <h3 class="font-semibold mb-3 text-gray-900">Support</h3>
        <ul class="space-y-2">
          <li><a href="/ticket" class="hover:text-green-700">Open Ticket</a></li>
          <li><a href="/help" class="hover:text-green-700">Help Center</a></li>
        </ul>
      </div>
      <div>
        <h3 class="font-semibold mb-3 text-gray-900">Connect</h3>
        <div class="flex gap-3">
          <a href="https://facebook.com/klinflow" aria-label="Facebook" class="hover:text-green-700">
            <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24"><path d="M22 12a10 10 0 1 0-11.5 9.9v-7H8v-3h2.5V9.5A3.5 3.5 0 0 1 14 6h2v3h-1.6c-1 0-1.4.5-1.4 1.3V12H16l-.5 3h-2.5v7A10 10 0 0 0 22 12z"/></svg>
          </a>
          <a href="https://linkedin.com/company/klinflow" aria-label="LinkedIn" class="hover:text-green-700">
            <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24"><path d="M4.98 3.5C4.98 5 3.93 6 2.5 6S0 5 0 3.5 1.05 1 2.5 1s2.48 1 2.48 2.5zM.5 8h4v16h-4V8zm7 0h3.9v2.2h.1c.5-.9 1.7-2.3 3.9-2.3 4.2 0 5 2.8 5 6.5V24h-4v-8c0-1.9 0-4.3-2.6-4.3s-3 2-3 4.1V24h-4V8z"/></svg>
          </a>
        </div>
      </div>
    </div>
    <div class="border-t border-green-100 py-4 text-center text-xs text-gray-600">
      © <script>document.write(new Date().getFullYear())</script> KlinFlow — Designed by <strong>DEPENDCORE</strong>
    </div>
  </footer>

  <!-- SCRIPTS (deduped; drawer + simple reveal) -->
  <script>
    // Drawer + focus management
    const toggleBtn=document.getElementById('nav-toggle'),
          closeBtn=document.getElementById('nav-close'),
          drawer=document.getElementById('mobile-drawer'),
          overlay=document.getElementById('overlay');
    let lastFocus;
    function openDrawer(){
      lastFocus=document.activeElement;
      drawer.classList.remove('translate-x-full'); overlay.classList.remove('opacity-0','pointer-events-none');
      drawer.setAttribute('aria-hidden','false'); toggleBtn?.setAttribute('aria-expanded','true');
      document.documentElement.style.overflow='hidden'; document.body.style.overflow='hidden';
      (drawer.querySelector('a,button,[tabindex="0"]')||drawer).focus({preventScroll:true});
    }
    function closeDrawer(){
      drawer.classList.add('translate-x-full'); overlay.classList.add('opacity-0','pointer-events-none');
      drawer.setAttribute('aria-hidden','true'); toggleBtn?.setAttribute('aria-expanded','false');
      document.documentElement.style.overflow=''; document.body.style.overflow='';
      lastFocus?.focus({preventScroll:true});
    }
    toggleBtn?.addEventListener('click', openDrawer, {passive:true});
    closeBtn?.addEventListener('click', closeDrawer, {passive:true});
    overlay?.addEventListener('click', closeDrawer, {passive:true});
    document.addEventListener('keydown', e=>{ if(e.key==='Escape') closeDrawer(); }, {passive:true});

    // Gentle reveal on scroll
    const revealEls=[...document.querySelectorAll('.panel')];
    if ('IntersectionObserver' in window){
      const io=new IntersectionObserver(es=>es.forEach(e=>{if(e.isIntersecting){e.target.classList.add('soft-shadow'); io.unobserve(e.target);}}),{threshold:.12});
      revealEls.forEach(el=>io.observe(el));
    }
  </script>
  
  <script> window.chtlConfig = { chatbotId: "8748546719" } </script>
<script async data-id="8748546719" id="chtl-script" type="text/javascript" src="https://chatling.ai/js/embed.js"></script>
  
</body>
</html>