<!doctype html>
<html lang="en" class="h-full scroll-smooth">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>About KlinFlow — Built in Bangladesh for Multi-Tenant Excellence</title>
  <meta name="description" content="Learn about KlinFlow — mission, story, and team. Secure multi-tenant software for Bangladesh: POS, HotelFlow, Bhata, School, MedFlow, and DMS." />
  <meta name="theme-color" content="#228B22" />
  <link rel="icon" type="image/png" href="/assets/brand/logo.png" />
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" crossorigin="anonymous" />

  <style>
    :root { --brand:#228B22; }
    .panel{background:#fff;border:1px solid rgba(0,0,0,.06);}
    .soft-shadow{box-shadow:0 10px 25px rgba(0,0,0,.07),0 2px 8px rgba(0,0,0,.04);}
    .brand-logo{max-height:56px;width:auto;}
    .focus-ring:focus-visible{outline:2px solid var(--brand);outline-offset:3px;}
    body{
      background:
        radial-gradient(1000px 600px at 10% 10%,rgba(34,139,34,.08),transparent),
        linear-gradient(180deg,#edfdf2 0%,#effcf3 40%,#f6fdf8 100%);
    }
    /* Drawer */
    .drawer-enter{transform:translateX(100%);opacity:0;}
    .drawer-open{transform:translateX(0);opacity:1;}
    .overlay-hidden{opacity:0;pointer-events:none;}
    .overlay-open{opacity:1;pointer-events:auto;}
    .transition-basic{transition:transform .25s ease,opacity .25s ease;}
    /* Reveal */
    .reveal{opacity:0;transform:translateY(20px);transition:opacity .6s ease,transform .6s ease;}
    .reveal.in{opacity:1;transform:translateY(0);}
    @media(prefers-reduced-motion:reduce){.reveal,.reveal.in{transition:none!important;opacity:1!important;transform:none!important;}}
  </style>
</head>

<body class="text-gray-900 overflow-x-hidden">
<a href="#main" class="sr-only focus:not-sr-only focus-ring fixed left-4 top-4 z-50 bg-white rounded px-3 py-2 soft-shadow">Skip to main</a>

<!-- HEADER -->
<header id="site-header" class="fixed top-0 left-0 w-full z-50 backdrop-blur bg-white/80 shadow-sm">
  <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
    <nav class="flex items-center justify-between h-16" aria-label="Primary">
      <!-- Logo -->
      <a href="/" class="flex items-center gap-3">
        <img src="/assets/brand/logo.png" alt="KlinFlow" class="h-10 w-auto" loading="eager">
        <span class="sr-only">KlinFlow Home</span>
      </a>

      <!-- Desktop nav -->
      <ul class="hidden sm:flex items-center gap-8 text-sm font-medium">
        <li><a href="/"        class="text-gray-700 hover:text-green-700 transition">Home</a></li>
        <li><a href="/about"   class="text-green-700 font-semibold border-b-2 border-green-700 pb-1">About</a></li>
        <li><a href="/contact" class="text-gray-700 hover:text-green-700 transition">Contact</a></li>
        <li><a href="/pricing" class="text-gray-700 hover:text-green-700 transition">Pricing</a></li>
      </ul>

      <!-- Mobile button -->
      <button id="nav-toggle"
              class="sm:hidden inline-flex items-center gap-2 px-3 py-2 rounded-lg bg-white/70 backdrop-blur shadow focus-ring"
              aria-controls="mobile-drawer"
              aria-expanded="false"
              aria-label="Open menu">
        <i class="fa-solid fa-bars text-green-700"></i>
      </button>
    </nav>
  </div>
</header>

<!-- Drawer/backdrop -->
<div id="overlay" class="fixed inset-0 bg-black/40 transition-basic overlay-hidden sm:hidden z-40"></div>
<aside id="mobile-drawer" class="fixed top-0 right-0 h-full w-64 max-w-full panel soft-shadow transition-basic drawer-enter sm:hidden z-50" role="dialog" aria-modal="true" aria-labelledby="drawer-title" aria-hidden="true" tabindex="-1">
  <div class="h-full flex flex-col">
    <div class="flex items-center justify-between px-4 py-3 border-b border-gray-200">
      <div class="flex items-center gap-2">
        <img src="/assets/brand/logo.png" alt="KlinFlow" class="h-8 w-auto">
        <span id="drawer-title" class="font-semibold">Menu</span>
      </div>
      <button id="nav-close" class="p-2 rounded-lg focus-ring" aria-label="Close menu"><i class="fa-solid fa-xmark"></i></button>
    </div>
    <nav class="p-2" aria-label="Mobile">
      <a href="/"        class="block px-3 py-3 rounded-lg hover:bg-gray-100">Home</a>
      <a href="/about"   class="block px-3 py-3 rounded-lg hover:bg-gray-100 font-semibold">About Us</a>
      <a href="/contact" class="block px-3 py-3 rounded-lg hover:bg-gray-100">Contact Us</a>
      <a href="/pricing" class="block px-3 py-3 rounded-lg hover:bg-gray-100">Pricing</a>
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
    <p class="inline-flex items-center gap-2 text-xs font-semibold uppercase tracking-wider text-green-800 bg-green-100 px-3 py-1 rounded-full soft-shadow">
      <i class="fa-solid fa-leaf"></i> Our story
    </p>
    <h1 class="mt-4 text-4xl sm:text-5xl font-extrabold leading-tight">
      Building <span class="text-green-600">secure multi-tenant</span> software for Bangladesh
    </h1>
    <p class="mt-3 text-gray-700 max-w-2xl">
      KlinFlow started with a simple idea: <em>reliable, affordable cloud apps that scale across organizations</em>. Today, our platform powers POS, HotelFlow, Bhata, School, MedFlow, and DMS — all on a single, secure tenancy model.
    </p>
    <div class="mt-6 grid grid-cols-2 sm:grid-cols-4 gap-4">
      <div class="panel soft-shadow rounded-2xl p-4 flex items-center gap-3"><i class="fa-solid fa-building text-green-600"></i><div><div class="font-bold text-lg">Multi-Tenant</div><div class="text-sm text-gray-600">Isolated org data</div></div></div>
      <div class="panel soft-shadow rounded-2xl p-4 flex items-center gap-3"><i class="fa-solid fa-lock text-green-600"></i><div><div class="font-bold text-lg">Security-First</div><div class="text-sm text-gray-600">RBAC &amp; audit trail</div></div></div>
      <div class="panel soft-shadow rounded-2xl p-4 flex items-center gap-3"><i class="fa-solid fa-language text-green-600"></i><div><div class="font-bold text-lg">EN/BN</div><div class="text-sm text-gray-600">Bilingual UI</div></div></div>
      <div class="panel soft-shadow rounded-2xl p-4 flex items-center gap-3"><i class="fa-solid fa-headset text-green-600"></i><div><div class="font-bold text-lg">Local Support</div><div class="text-sm text-gray-600">Bangladesh time</div></div></div>
    </div>
  </section>
  
    <!-- Mission & Values -->
  <section class="mx-auto max-w-6xl px-4 pb-10 grid lg:grid-cols-3 gap-6 reveal">
    <article class="panel soft-shadow rounded-2xl p-6 lg:col-span-2">
      <h2 class="text-2xl font-bold mb-2">Our Mission</h2>
      <p class="text-gray-700">
        Empower Bangladesh businesses with cloud software that’s
        <strong>secure</strong>, <strong>scalable</strong>, and <strong>simple</strong>.
        We obsess over uptime, usable design, and real-world workflows across
        retail, hospitality, brick fields, schools, and healthcare.
      </p>
      <div class="mt-4 grid sm:grid-cols-3 gap-3 text-sm">
        <div class="flex items-start gap-3"><i class="fa-solid fa-shield-halved text-green-600 mt-1"></i><span>Defense-in-depth security</span></div>
        <div class="flex items-start gap-3"><i class="fa-solid fa-gauge-high text-green-600 mt-1"></i><span>Performance under load</span></div>
        <div class="flex items-start gap-3"><i class="fa-solid fa-users-gear text-green-600 mt-1"></i><span>Admin-friendly controls</span></div>
      </div>
    </article>

    <aside class="panel soft-shadow rounded-2xl p-6 reveal">
      <h3 class="text-lg font-semibold mb-2">Core Values</h3>
      <ul class="space-y-2 text-gray-700 text-sm">
        <li><i class="fa-solid fa-check text-green-600 mr-2"></i>Customer empathy over features</li>
        <li><i class="fa-solid fa-check text-green-600 mr-2"></i>Simple UX beats complexity</li>
        <li><i class="fa-solid fa-check text-green-600 mr-2"></i>Privacy by design</li>
        <li><i class="fa-solid fa-check text-green-600 mr-2"></i>Results, not vanity metrics</li>
      </ul>
    </aside>
  </section>

  <!-- Story / Timeline -->
  <section class="mx-auto max-w-6xl px-4 pb-12 reveal">
    <div class="panel soft-shadow rounded-2xl p-6">
      <h2 class="text-2xl font-bold mb-4">How we got here</h2>
      <ol class="relative border-l border-dashed border-green-300 pl-6 space-y-6">
        <li><span class="absolute -left-[9px] top-1.5 w-4 h-4 rounded-full bg-green-600"></span>
          <h3 class="font-semibold">The idea</h3>
          <p class="text-sm text-gray-700">We saw SMEs juggling siloed tools. KlinFlow began as a unified, multi-tenant platform.</p>
        </li>
        <li><span class="absolute -left-[9px] top-1.5 w-4 h-4 rounded-full bg-green-600"></span>
          <h3 class="font-semibold">First modules</h3>
          <p class="text-sm text-gray-700">Retail POS and HotelFlow launched with role-based access and strong audit logs.</p>
        </li>
        <li><span class="absolute -left-[9px] top-1.5 w-4 h-4 rounded-full bg-green-600"></span>
          <h3 class="font-semibold">Vertical expansion</h3>
          <p class="text-sm text-gray-700">Bhata, School, MedFlow, and DMS joined — all sharing the same secure tenancy foundation.</p>
        </li>
        <li><span class="absolute -left-[9px] top-1.5 w-4 h-4 rounded-full bg-green-600"></span>
          <h3 class="font-semibold">Today</h3>
          <p class="text-sm text-gray-700">We continue to optimize for Bangladesh: VAT reports, EN/BN support, and local integrations.</p>
        </li>
      </ol>
    </div>
  </section>

  <!-- Leadership -->
  <section class="mx-auto max-w-6xl px-4 pb-12 reveal">
    <h2 class="text-2xl font-bold mb-4">Leadership</h2>
    <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-5">
      <article class="panel soft-shadow rounded-2xl p-5">
        <div class="flex items-center gap-4">
          <img src="/assets/team/zillur.png" alt="" class="h-14 w-14 rounded-xl object-cover bg-green-50">
          <div><h3 class="font-semibold">Zillur Rahman</h3><p class="text-sm text-gray-600">Founder & Lead Strategist</p></div>
        </div>
        <p class="mt-3 text-sm text-gray-700">Platform strategy, product direction, and partnerships.</p>
        <div class="mt-3 text-gray-500 text-sm flex gap-3">
          <a href="#" class="hover:text-green-700"><i class="fa-brands fa-linkedin"></i></a>
          <a href="#" class="hover:text-green-700"><i class="fa-brands fa-x-twitter"></i></a>
        </div>
      </article>

      <article class="panel soft-shadow rounded-2xl p-5">
        <div class="flex items-center gap-4">
          <img src="/assets/team/avatar-2.png" alt="" class="h-14 w-14 rounded-xl object-cover bg-green-50">
          <div><h3 class="font-semibold">N. Chowdhury</h3><p class="text-sm text-gray-600">CTO</p></div>
        </div>
        <p class="mt-3 text-sm text-gray-700">Security architecture, performance, and developer experience.</p>
        <div class="mt-3 text-gray-500 text-sm flex gap-3">
          <a href="#" class="hover:text-green-700"><i class="fa-brands fa-linkedin"></i></a>
          <a href="#" class="hover:text-green-700"><i class="fa-brands fa-github"></i></a>
        </div>
      </article>

      <article class="panel soft-shadow rounded-2xl p-5">
        <div class="flex items-center gap-4">
          <img src="/assets/team/avatar-3.png" alt="" class="h-14 w-14 rounded-xl object-cover bg-green-50">
          <div><h3 class="font-semibold">S. Akter</h3><p class="text-sm text-gray-600">Head of Customer Success</p></div>
        </div>
        <p class="mt-3 text-sm text-gray-700">Onboarding, training, and long-term customer outcomes.</p>
        <div class="mt-3 text-gray-500 text-sm flex gap-3">
          <a href="#" class="hover:text-green-700"><i class="fa-brands fa-linkedin"></i></a>
          <a href="#" class="hover:text-green-700"><i class="fa-solid fa-envelope"></i></a>
        </div>
      </article>
    </div>
  </section>

  <!-- Bangladesh Focus & Security -->
  <section class="mx-auto max-w-6xl px-4 pb-12 grid lg:grid-cols-2 gap-6">
    <article class="panel soft-shadow rounded-2xl p-6 reveal">
      <h2 class="text-2xl font-bold mb-2">Made for Bangladesh</h2>
      <p class="text-gray-700">We design with local realities in mind — bilingual UI, VAT reports, and timezone-aligned support.</p>
      <div class="mt-4 grid sm:grid-cols-2 gap-3 text-sm">
        <div class="flex items-start gap-3"><i class="fa-solid fa-file-invoice text-green-600 mt-1"></i><span>VAT & exportable reports</span></div>
        <div class="flex items-start gap-3"><i class="fa-solid fa-language text-green-600 mt-1"></i><span>English & Bangla</span></div>
        <div class="flex items-start gap-3"><i class="fa-solid fa-phone text-green-600 mt-1"></i><span>Local support hours</span></div>
        <div class="flex items-start gap-3"><i class="fa-solid fa-credit-card text-green-600 mt-1"></i><span>Local payments (on request)</span></div>
      </div>
    </article>

    <article class="panel soft-shadow rounded-2xl p-6 reveal">
      <h2 class="text-2xl font-bold mb-2">Security & Reliability</h2>
      <p class="text-gray-700">Data isolation per tenant, encryption in transit, robust access controls, and daily backups help protect your business.</p>
      <div class="mt-4 grid sm:grid-cols-2 gap-3 text-sm">
        <div class="flex items-start gap-3"><i class="fa-solid fa-user-shield text-green-600 mt-1"></i><span>RBAC with audit trail</span></div>
        <div class="flex items-start gap-3"><i class="fa-solid fa-database text-green-600 mt-1"></i><span>Daily offsite backups</span></div>
        <div class="flex items-start gap-3"><i class="fa-solid fa-lock text-green-600 mt-1"></i><span>TLS everywhere</span></div>
        <div class="flex items-start gap-3"><i class="fa-solid fa-gauge-simple-high text-green-600 mt-1"></i><span>Performance under load</span></div>
      </div>
    </article>
  </section>

  <!-- CTA -->
  <section class="mx-auto max-w-6xl px-4 pb-16 reveal">
    <div class="panel soft-shadow rounded-2xl p-6 sm:p-8 text-center">
      <h2 class="text-2xl sm:text-3xl font-extrabold">Join our journey</h2>
      <p class="mt-2 text-gray-700">We’re a pragmatic, product-obsessed team. If you love building reliable software that real businesses use daily, come work with us.</p>
      <div class="mt-4 flex flex-col sm:flex-row gap-3 justify-center">
        <a href="/contact" class="focus-ring inline-flex items-center gap-2 rounded-xl bg-green-600 text-white px-5 py-3 font-semibold soft-shadow hover:bg-green-700 transition">
          <i class="fa-solid fa-envelope-open-text"></i> Contact Us
        </a>
        <a href="/pricing" class="focus-ring inline-flex items-center gap-2 rounded-xl bg-white border border-green-200 text-green-700 px-5 py-3 font-semibold soft-shadow hover:bg-green-50 transition">
          <i class="fa-solid fa-tags"></i> See Pricing
        </a>
      </div>
    </div>
  </section>
</main>
  
  <!-- Hidden SEO Helper for Klinflow (On-page text for search visibility) -->
<div class="sr-only" aria-hidden="true">
    <h2>Best Business Software and ERP Solution in Bangladesh – KlinFlow Modules</h2>
    <p>KlinFlow is the **Best ERP Software in Bangladesh**, offering simple, efficient, and affordable solutions tailored for local businesses and industries across Dhaka, Chittagong, and all divisions.</p>
    <p>Our comprehensive **Business Management Software BD** includes:</p>
    <ul>
      <li><a href="/pos">Retail POS Software Bangladesh</a> – The ultimate Point of Sale solution for stores and supermarkets.</li>
      <li><a href="/hotelflow">Hotel Management System Dhaka</a> – Complete software for resorts, hotels, and guesthouses (HotelFlow).</li>
      <li><a href="/medflow">Medical & Hospital Software</a> – Manage clinics, pharmacies, and patient records with MedFlow.</li>
      <li><a href="/school">School Management Software</a> – Simplify administration, attendance, and fee collection for educational institutions.</li>
      <li><a href="/bhata">Brick Field Management System</a> – Specialized business application for the Bhata (Brick Field) industry.</li>
      <li><a href="/distribution">Distribution Management System (DMS)</a> – Control inventory and sales for distributors and wholesalers.</li>
      <li><a href="/pricing">Affordable ERP pricing in BDT</a> – View our monthly subscription plans starting at ৳2000.</li>
    </ul>
    <p>If you need the **most affordable and best ERP software solution in Bangladesh**, choose KlinFlow. We help businesses manage inventory, sales, accounting, and multi-branch operations efficiently.</p>
</div>

<!-- FOOTER -->
<footer id="site-footer" class="border-t">
  <div class="mx-auto max-w-6xl px-4 py-6 text-sm flex flex-col sm:flex-row items-center justify-between">
    <div class="opacity-80" id="copyright"></div>
    <div class="opacity-80">Designed by: <span class="font-semibold">DEPENDCORE</span></div>
  </div>
</footer>

<script>
  // Footer year
  document.getElementById('copyright').textContent = `© ${new Date().getFullYear()} KlinFlow`;

  // Drawer behavior + focus management
  const toggleBtn = document.getElementById('nav-toggle');
  const closeBtn  = document.getElementById('nav-close');
  const drawer    = document.getElementById('mobile-drawer');
  const overlay   = document.getElementById('overlay');
  let lastFocus;

  function openDrawer() {
    lastFocus = document.activeElement;
    drawer.classList.remove('drawer-enter'); drawer.classList.add('drawer-open');
    overlay.classList.remove('overlay-hidden'); overlay.classList.add('overlay-open');
    drawer.setAttribute('aria-hidden','false');
    toggleBtn && toggleBtn.setAttribute('aria-expanded','true');
    document.documentElement.style.overflow='hidden'; document.body.style.overflow='hidden';
    const firstItem = drawer.querySelector('a,button,[tabindex=\"0\"]'); (firstItem||drawer).focus({preventScroll:true});
  }
  function closeDrawer() {
    drawer.classList.add('drawer-enter'); drawer.classList.remove('drawer-open');
    overlay.classList.add('overlay-hidden'); overlay.classList.remove('overlay-open');
    drawer.setAttribute('aria-hidden','true');
    toggleBtn && toggleBtn.setAttribute('aria-expanded','false');
    document.documentElement.style.overflow=''; document.body.style.overflow='';
    lastFocus && lastFocus.focus({preventScroll:true});
  }
  toggleBtn && toggleBtn.addEventListener('click',openDrawer,{passive:true});
  closeBtn && closeBtn.addEventListener('click',closeDrawer,{passive:true});
  overlay && overlay.addEventListener('click',closeDrawer,{passive:true});
  document.addEventListener('keydown',e=>{if(e.key==='Escape')closeDrawer();},{passive:true});

  // Fade-in reveal animation
  const revealEls = document.querySelectorAll('.reveal');
  const io = new IntersectionObserver(entries=>{
    entries.forEach(entry=>{ if(entry.isIntersecting) entry.target.classList.add('in'); });
  },{threshold:.12});
  revealEls.forEach(el=>io.observe(el));
</script>
  
  <script> window.chtlConfig = { chatbotId: "8748546719" } </script>
<script async data-id="8748546719" id="chtl-script" type="text/javascript" src="https://chatling.ai/js/embed.js"></script>
  
</body>
</html>