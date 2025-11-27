<?php
/** apps/Public/Views/security.php
 * KlinFlow — Security & Privacy Practices (public)
 * - Same header/off-canvas drawer used on other public pages
 * - Light animations with reduced-motion respect
 * - Clear sections with deep links (#tenancy, #encryption, …)
 */

$h = static fn($s)=>htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
$brandLogo   = '/assets/brand/logo.png';
$siteFavicon = '/assets/brand/logo.png';
?>
<!doctype html>
<html lang="en" class="h-full">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />

  <title>KlinFlow — Security & privacy practices</title>
  <meta name="description" content="How KlinFlow keeps your data safe: tenant isolation, TLS everywhere, hashed credentials, RBAC, audit trail, backups, and incident response." />
  <meta name="theme-color" content="#16a34a" />
  <link rel="icon" type="image/png" href="<?= $h($siteFavicon) ?>" />
  <link rel="preconnect" href="https://cdnjs.cloudflare.com" crossorigin>
  <link rel="preload" href="<?= $h($brandLogo) ?>" as="image" />

  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css"
        crossorigin="anonymous" />

  <style>
    /* Tokens (consistent with public pages) */
    .panel { background:#fff; border:1px solid rgba(0,0,0,.06); }
    .soft-shadow { box-shadow:0 10px 25px rgba(0,0,0,.07), 0 2px 8px rgba(0,0,0,.04); }
    .brand-logo { max-height:56px; height:auto; width:auto; }
    .focus-ring:focus-visible { outline:2px solid #22c55e; outline-offset:3px; }

    /* Background */
    body {
      background: radial-gradient(1200px 600px at 10% 10%, rgba(22,163,74,.08), transparent),
                  linear-gradient(180deg, #edfdf2 0%, #effcf3 40%, #f6fdf8 100%);
      color:#111827;
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

    .pill{display:inline-flex;align-items:center;gap:.5rem;padding:.375rem .75rem;border-radius:9999px;background:#ecfdf5;color:#065f46;font-weight:700;border:1px solid #d1fae5}
    .check i{color:#22c55e}
    .kbd{background:#fff;border:1px solid #e5e7eb;border-radius:.5rem;padding:.15rem .4rem;font-size:.8rem}
    .toc a{display:flex;align-items:center;gap:.5rem;padding:.5rem .75rem;border-radius:.75rem}
    .toc a:hover{background:#f1f5f9}
    .badge{display:inline-flex;align-items:center;gap:.4rem;padding:.25rem .55rem;border-radius:9999px;background:#f1f5f9;font-size:.75rem}
  </style>

  <!-- Basic structured data -->
  <script type="application/ld+json">
  {
    "@context":"https://schema.org",
    "@type":"WebPage",
    "name":"KlinFlow Security",
    "description":"Security and privacy practices for KlinFlow multi-tenant platform."
  }
  </script>
</head>
<body class="overflow-x-hidden">
  <a href="#main" class="sr-only focus:not-sr-only focus-ring fixed left-4 top-4 z-50 bg-white text-gray-900 rounded px-3 py-2 soft-shadow">Skip to main</a>

  <!-- HEADER (unified) -->
  <header id="site-header" class="w-full sticky top-0 z-50 sm:static">
    <div class="mx-auto max-w-6xl px-4">
      <nav class="panel soft-shadow mt-4 rounded-2xl px-4 sm:px-6 py-3 flex items-center justify-between" aria-label="Primary">
        <a href="/" class="flex items-center gap-3">
          <img src="<?= $h($brandLogo) ?>" alt="KlinFlow" class="brand-logo" width="160" height="32" loading="eager">
          <span class="sr-only">KlinFlow Home</span>
        </a>
        <button id="nav-toggle"
                class="sm:hidden inline-flex items-center gap-2 px-3 py-2 rounded-lg panel soft-shadow focus-ring"
                aria-controls="mobile-drawer" aria-expanded="false" aria-label="Open menu">
          <i class="fa-solid fa-bars"></i>
        </button>
        <ul class="hidden sm:flex items-center gap-6 text-sm sm:text-base">
          <li><a href="/about"   class="opacity-80 hover:opacity-100">About Us</a></li>
          <li><a href="/contact" class="opacity-80 hover:opacity-100">Contact Us</a></li>
          <li><a href="/pricing" class="opacity-80 hover:opacity-100">Pricing</a></li>
          <li><a href="/ticket"  class="opacity-80 hover:opacity-100">Tickets</a></li>
          <li><a href="/help"    class="opacity-80 hover:opacity-100">Help</a></li>
          <li><a href="/security" class="opacity-100 font-semibold">Security</a></li>
          <li>
            <a href="/signup" class="inline-flex items-center gap-2 rounded-full bg-green-600 text-white px-3.5 py-1.5 font-semibold hover:bg-green-700 transition focus-ring">Sign up free</a>
          </li>
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
          <img src="<?= $h($brandLogo) ?>" alt="KlinFlow" class="h-8 w-auto">
          <span id="drawer-title" class="font-semibold">Menu</span>
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
        <a href="/help"    class="block px-3 py-3 rounded-lg hover:bg-gray-100">Help</a>
        <a href="/security" class="block px-3 py-3 rounded-lg hover:bg-gray-100 font-semibold">Security</a>
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
      <p class="pill"><i class="fa-solid fa-shield-halved"></i> Security & isolation</p>
      <h1 class="mt-4 text-4xl sm:text-5xl font-extrabold leading-tight">Security that respects your tenancy boundaries</h1>
      <p class="mt-3 text-gray-700 max-w-3xl">
        Per-organization data isolation, TLS everywhere, hashed credentials, daily offsite backups, and strict RBAC keep your operations safe.
      </p>
    </section>

    <!-- TOC -->
    <section class="mx-auto max-w-6xl px-4 pb-2 reveal">
      <div class="panel soft-shadow rounded-2xl p-4">
        <nav class="toc grid sm:grid-cols-2 lg:grid-cols-3 gap-2 text-sm text-gray-700">
          <a href="#tenancy"><i class="fa-solid fa-diagram-project"></i><span>Tenant isolation</span></a>
          <a href="#encryption"><i class="fa-solid fa-lock"></i><span>Encryption</span></a>
          <a href="#rbac"><i class="fa-solid fa-user-shield"></i><span>Access & RBAC</span></a>
          <a href="#backups"><i class="fa-solid fa-database"></i><span>Backups & export</span></a>
          <a href="#audit"><i class="fa-solid fa-clipboard-check"></i><span>Audit & logging</span></a>
          <a href="#incident"><i class="fa-solid fa-bug"></i><span>Incident response</span></a>
          <a href="#uptime"><i class="fa-solid fa-gauge-high"></i><span>Uptime & reliability</span></a>
          <a href="#retention"><i class="fa-solid fa-box-archive"></i><span>Data retention</span></a>
          <a href="#disclosure"><i class="fa-solid fa-bullhorn"></i><span>Responsible disclosure</span></a>
        </nav>
      </div>
    </section>

    <!-- Sections -->
    <section class="mx-auto max-w-6xl px-4 pb-12 reveal" id="tenancy">
      <div class="grid lg:grid-cols-3 gap-6">
        <article class="panel soft-shadow rounded-2xl p-6 lg:col-span-2">
          <h2 class="text-2xl font-bold">Tenant isolation</h2>
          <p class="mt-2 text-gray-700">Each organization’s data is isolated by a strict tenant identifier enforced in application logic and queries.</p>
          <ul class="mt-3 space-y-2 text-gray-700">
            <li class="check"><i class="fa-solid fa-check mr-2"></i> Row-level scoping by <span class="kbd">org_id</span> in all read/write paths</li>
            <li class="check"><i class="fa-solid fa-check mr-2"></i> Least-privilege DB users per environment</li>
            <li class="check"><i class="fa-solid fa-check mr-2"></i> Extensive tests for cross-tenant access</li>
          </ul>
        </article>
        <aside class="panel soft-shadow rounded-2xl p-6">
          <div class="badge"><i class="fa-solid fa-circle-info"></i> Tip</div>
          <p class="mt-2 text-sm text-gray-700">Export your data any time as CSV/PDF. Need an offboarding export? Contact <a class="underline" href="mailto:security@klinflow.com">security@klinflow.com</a>.</p>
        </aside>
      </div>
    </section>

    <section class="mx-auto max-w-6xl px-4 pb-12 reveal" id="encryption">
      <div class="panel soft-shadow rounded-2xl p-6">
        <h2 class="text-2xl font-bold">Encryption</h2>
        <p class="mt-2 text-gray-700">All traffic uses TLS. Passwords are one-way hashed with a modern algorithm and per-user salts.</p>
        <ul class="mt-3 grid sm:grid-cols-2 gap-2 text-gray-700">
          <li class="check"><i class="fa-solid fa-check mr-2"></i> TLS for all app endpoints</li>
          <li class="check"><i class="fa-solid fa-check mr-2"></i> Strong password hashing</li>
          <li class="check"><i class="fa-solid fa-check mr-2"></i> Signed session cookies; HTTPOnly</li>
          <li class="check"><i class="fa-solid fa-check mr-2"></i> Optional IP allow-lists on request</li>
        </ul>
      </div>
    </section>

    <section class="mx-auto max-w-6xl px-4 pb-12 reveal" id="rbac">
      <div class="panel soft-shadow rounded-2xl p-6">
        <h2 class="text-2xl font-bold">Access control & RBAC</h2>
        <p class="mt-2 text-gray-700">Role-based access lets you limit what users can see and do.</p>
        <ul class="mt-3 space-y-2 text-gray-700">
          <li class="check"><i class="fa-solid fa-check mr-2"></i> Granular roles per module</li>
          <li class="check"><i class="fa-solid fa-check mr-2"></i> Admin controls for invites and resets</li>
          <li class="check"><i class="fa-solid fa-check mr-2"></i> Session idle-timeout and logout everywhere</li>
        </ul>
      </div>
    </section>

    <section class="mx-auto max-w-6xl px-4 pb-12 reveal" id="backups">
      <div class="panel soft-shadow rounded-2xl p-6">
        <h2 class="text-2xl font-bold">Backups & export</h2>
        <p class="mt-2 text-gray-700">We maintain regular backups and offer exports on demand.</p>
        <ul class="mt-3 grid sm:grid-cols-2 gap-2 text-gray-700">
          <li class="check"><i class="fa-solid fa-check mr-2"></i> Daily offsite backups</li>
          <li class="check"><i class="fa-solid fa-check mr-2"></i> Point-in-time restore (per incident)</li>
          <li class="check"><i class="fa-solid fa-check mr-2"></i> CSV/PDF export tools</li>
          <li class="check"><i class="fa-solid fa-check mr-2"></i> Retention as per policy below</li>
        </ul>
      </div>
    </section>

    <section class="mx-auto max-w-6xl px-4 pb-12 reveal" id="audit">
      <div class="panel soft-shadow rounded-2xl p-6">
        <h2 class="text-2xl font-bold">Audit & logging</h2>
        <p class="mt-2 text-gray-700">Key actions are logged for traceability and compliance needs.</p>
        <ul class="mt-3 space-y-2 text-gray-700">
          <li class="check"><i class="fa-solid fa-check mr-2"></i> Audit events for sensitive actions</li>
          <li class="check"><i class="fa-solid fa-check mr-2"></i> Immutable timestamps</li>
          <li class="check"><i class="fa-solid fa-check mr-2"></i> Timezone: Bangladesh Standard Time</li>
        </ul>
      </div>
    </section>

    <section class="mx-auto max-w-6xl px-4 pb-12 reveal" id="incident">
      <div class="panel soft-shadow rounded-2xl p-6">
        <h2 class="text-2xl font-bold">Incident response</h2>
        <p class="mt-2 text-gray-700">We triage, contain, investigate, and communicate when issues arise.</p>
        <ol class="mt-3 list-decimal ml-5 text-gray-700 space-y-1">
          <li>Triage & severity assignment</li>
          <li>Containment & remediation</li>
          <li>Forensics & root-cause analysis</li>
          <li>Customer communication for material impact</li>
          <li>Post-mortem & prevention tasks</li>
        </ol>
        <p class="mt-3 text-sm text-gray-600">Report a vulnerability: <a class="underline" href="mailto:security@klinflow.com">security@klinflow.com</a></p>
      </div>
    </section>

    <section class="mx-auto max-w-6xl px-4 pb-12 reveal" id="uptime">
      <div class="panel soft-shadow rounded-2xl p-6">
        <h2 class="text-2xl font-bold">Uptime & reliability</h2>
        <p class="mt-2 text-gray-700">We aim for high availability through redundancy, monitoring, and safe deploys.</p>
        <ul class="mt-3 grid sm:grid-cols-2 gap-2 text-gray-700">
          <li class="check"><i class="fa-solid fa-check mr-2"></i> Health checks & alerting</li>
          <li class="check"><i class="fa-solid fa-check mr-2"></i> Zero-downtime rollout where possible</li>
          <li class="check"><i class="fa-solid fa-check mr-2"></i> Rollback strategy for failed releases</li>
          <li class="check"><i class="fa-solid fa-check mr-2"></i> Capacity planning for peak loads</li>
        </ul>
      </div>
    </section>

    <section class="mx-auto max-w-6xl px-4 pb-12 reveal" id="retention">
      <div class="panel soft-shadow rounded-2xl p-6">
        <h2 class="text-2xl font-bold">Data retention</h2>
        <p class="mt-2 text-gray-700">We keep operational data only as long as needed for service delivery, support, and legal obligations.</p>
        <ul class="mt-3 space-y-2 text-gray-700">
          <li class="check"><i class="fa-solid fa-check mr-2"></i> Account data retained while subscription is active</li>
          <li class="check"><i class="fa-solid fa-check mr-2"></i> Backup retention on a rolling window</li>
          <li class="check"><i class="fa-solid fa-check mr-2"></i> Export & deletion available upon verified request</li>
        </ul>
      </div>
    </section>

    <section class="mx-auto max-w-6xl px-4 pb-16 reveal" id="disclosure">
      <div class="panel soft-shadow rounded-2xl p-6 sm:p-8">
        <h2 class="text-2xl font-bold">Responsible disclosure</h2>
        <p class="mt-2 text-gray-700">We appreciate reports from the community. Please give us reasonable time to fix issues before public disclosure.</p>
        <ul class="mt-3 text-gray-700 space-y-1">
          <li class="check"><i class="fa-solid fa-check mr-2"></i> Email: <a class="underline" href="mailto:security@klinflow.com">security@klinflow.com</a></li>
          <li class="check"><i class="fa-solid fa-check mr-2"></i> Include steps to reproduce and impact</li>
          <li class="check"><i class="fa-solid fa-check mr-2"></i> Do not access other tenants’ data during testing</li>
        </ul>
      </div>
    </section>

    <!-- CTA -->
    <section class="mx-auto max-w-6xl px-4 pb-20 reveal">
      <div class="bg-gradient-to-br from-green-600 to-emerald-600 text-white rounded-3xl p-6 sm:p-10 soft-shadow grid lg:grid-cols-3 gap-6 items-center">
        <div class="lg:col-span-2">
          <h2 class="text-2xl sm:text-3xl font-extrabold">Have a security question?</h2>
          <p class="mt-2 opacity-90">Contact our team for assessments, exports, or compliance needs.</p>
        </div>
        <div class="flex gap-3">
          <a href="mailto:security@klinflow.com" class="inline-flex items-center gap-2 bg-white text-green-700 font-semibold px-5 py-3 rounded-xl hover:bg-green-50 transition">
            Email security <i class="fa-solid fa-envelope"></i>
          </a>
          <a href="/ticket" class="inline-flex items-center gap-2 bg-green-700/30 text-white font-semibold px-5 py-3 rounded-xl hover:bg-green-700/40 transition">
            Open a ticket <i class="fa-solid fa-paper-plane"></i>
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

    // Reveal on scroll
    const _revEls = Array.from(document.querySelectorAll('.reveal'));
    if ('IntersectionObserver' in window){
      const io = new IntersectionObserver(es => es.forEach(e => { if(e.isIntersecting) e.target.classList.add('in'); }), {threshold:.12});
      _revEls.forEach(el => io.observe(el));
    } else {
      _revEls.forEach(el => el.classList.add('in'));
    }
  </script>
  
  <script> window.chtlConfig = { chatbotId: "8748546719" } </script>
<script async data-id="8748546719" id="chtl-script" type="text/javascript" src="https://chatling.ai/js/embed.js"></script>
  
</body>
</html>