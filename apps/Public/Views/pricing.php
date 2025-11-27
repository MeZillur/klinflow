<!doctype html>
<html lang="en" class="h-full scroll-smooth">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>KlinFlow — Pricing (Bangladesh)</title>
  <meta name="description" content="Transparent pricing for KlinFlow modules in Bangladesh. Retail POS, HotelFlow, Bhata, School, MedFlow, and DMS — ৳2,000 BDT per module per month. Annual discounts available." />
  <meta name="theme-color" content="#228B22" />

  <!-- Tailwind (CDN for static public pages). In prod, use your compiled build. -->
  <script src="https://cdn.tailwindcss.com"></script>
  <script>
    tailwind.config = {
      theme: {
        extend: {
          colors: { brand: '#228B22' },
          boxShadow: {
            'soft': '0 10px 25px rgba(0,0,0,.07), 0 2px 8px rgba(0,0,0,.04)',
            'header': '0 10px 30px -12px rgba(0,0,0,0.15)'
          }
        }
      }
    }
  </script>
  <link rel="preconnect" href="https://cdnjs.cloudflare.com" crossorigin>
  <link rel="icon" type="image/png" href="/assets/brand/logo.png" />
  <link rel="preload" href="/assets/brand/logo.png" as="image" />

  <link rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css"
        crossorigin="anonymous" />

  <style>
    :root { --brand:#228B22; }
    .glass { backdrop-filter: blur(10px); background: color-mix(in srgb, white 78%, transparent); }
    .focus-ring:focus-visible { outline:2px solid color-mix(in srgb, var(--brand) 65%, white); outline-offset:3px; }
    .panel { background:#fff; border:1px solid rgba(0,0,0,.06); }
    .soft-shadow { box-shadow:0 10px 25px rgba(0,0,0,.07), 0 2px 8px rgba(0,0,0,.04); }
    .brand-logo { max-height:56px; height:auto; width:auto; }
    @media (max-width:639.98px){ .brand-logo{ max-height:44px; } }

    /* Page background */
    body {
      background:
        radial-gradient(1200px 600px at 10% 10%, rgba(34,139,34,.08), transparent),
        linear-gradient(180deg, #eefbf1 0%, #f5fdf7 50%, #ffffff 100%);
    }

    /* Reveal (fade+rise) */
    .reveal { opacity:0; transform: translateY(16px); transition: opacity .6s ease, transform .6s ease; }
    .reveal.in { opacity:1; transform: translateY(0); }
    .reveal-slow { transition-duration: .9s; }
    @media (prefers-reduced-motion: reduce) {
      .reveal, .reveal.in, .reveal-slow { opacity: 1 !important; transform: none !important; transition: none !important; }
    }
  </style>
</head>
<body class="text-gray-900 overflow-x-hidden">
  <a href="#main" class="sr-only focus:not-sr-only focus-ring fixed left-4 top-4 z-50 bg-white text-gray-900 rounded px-3 py-2 soft-shadow">Skip to main</a>

  <!-- HEADER (shared style) -->
  <header class="sticky top-0 z-50 border-b border-slate-200/60 bg-white/80 glass">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
      <div class="flex items-center justify-between h-16">
        <a href="/" class="flex items-center gap-3 hover:opacity-95 transition">
          <img src="/assets/brand/logo.png" alt="KlinFlow" class="brand-logo" width="auto" height="10" />
          <span class="sr-only">KlinFlow Home</span>
        </a>

        <!-- Desktop nav -->
        <nav class="hidden md:flex items-center gap-8 text-[15px]">
          <a href="/about" class="text-slate-600 hover:text-slate-900">About</a>
          <a href="/contact" class="text-slate-600 hover:text-slate-900">Contact</a>
          <a href="/pricing" class="text-slate-900 font-semibold">Pricing</a>
          <a href="/signup" class="inline-flex items-center gap-2 font-medium text-slate-700 hover:text-slate-900">
            <span>Sign up free</span>
            <i class="fa-solid fa-arrow-right-to-bracket"></i>
          </a>
        </nav>

        <!-- Mobile trigger -->
        <button id="menuBtn" class="md:hidden p-2 rounded-lg hover:bg-slate-100 focus-ring" aria-label="Open menu">
          <i class="fa-solid fa-bars"></i>
        </button>
      </div>
    </div>

    <!-- Mobile drawer -->
    <div id="mobileNav" class="md:hidden hidden border-t border-slate-200/60">
      <div class="px-4 py-3 space-y-2 text-[15px]">
        <a href="/about" class="block text-slate-700">About</a>
        <a href="/contact" class="block text-slate-700">Contact</a>
        <a href="/pricing" class="block font-semibold text-slate-900">Pricing</a>
        <a href="/signup" class="block text-slate-700">Sign up free</a>
      </div>
    </div>
  </header>

  <!-- MAIN -->
  <main id="main" class="relative w-full">
    <!-- Hero -->
    <section class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 pt-10 pb-6 reveal">
      <p class="inline-flex items-center gap-2 text-xs font-semibold uppercase tracking-wider text-emerald-800 bg-emerald-100 px-3 py-1 rounded-full soft-shadow">
        <i class="fa-solid fa-wallet"></i> Simple, transparent pricing
      </p>
      <h1 class="mt-4 text-4xl sm:text-5xl font-extrabold leading-tight">
        Pricing for <span class="text-emerald-600">Bangladesh</span>
      </h1>
      <p class="mt-3 text-slate-700 max-w-2xl">
        Each core module is a flat, predictable price — ideal for SMEs and multi-branch operations. Billed monthly; cancel anytime. Save with annual billing.
      </p>

      <!-- Billing Toggle -->
      <div class="mt-6 inline-flex items-center gap-3 panel soft-shadow rounded-2xl p-2">
        <span class="text-sm text-slate-700 px-2">Billing:</span>
        <button id="bill-monthly" class="px-3 py-1.5 rounded-lg text-sm font-semibold bg-emerald-600 text-white">Monthly</button>
        <button id="bill-annual"  class="px-3 py-1.5 rounded-lg text-sm font-semibold bg-white border border-slate-200 hover:bg-slate-50 transition">
          Annual <span class="ml-1 text-emerald-700 font-bold">(Save 10%)</span>
        </button>
      </div>
    </section>

    <!-- Pricing Grid -->
    <section class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 pb-12">
      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
        <!-- POS -->
        <article class="panel soft-shadow rounded-2xl p-5 reveal">
          <div class="flex items-start justify-between">
            <div>
              <h3 class="text-lg font-semibold">Retail POS</h3>
              <p class="text-sm text-slate-600 mt-1">Billing, inventory, loyalty, VAT reports.</p>
            </div>
            <div class="w-10 h-10 rounded-xl bg-emerald-100 grid place-content-center">
              <i class="fa-solid fa-cash-register text-emerald-700"></i>
            </div>
          </div>
          <div class="mt-2">
            <span class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-emerald-50 text-emerald-900 font-semibold border border-emerald-100" data-price="2000">
              <i class="fa-solid fa-bangladeshi-taka-sign"></i><span class="price">2,000</span> BDT / mo
            </span>
          </div>
          <a href="/contact" class="mt-3 inline-flex items-center gap-2 rounded-xl bg-slate-900 hover:bg-slate-800 text-white px-4 py-2.5 font-semibold focus-ring">
            <i class="fa-solid fa-rocket"></i> Start now
          </a>
          <ul class="mt-3 text-sm text-slate-600 space-y-1">
            <li><i class="fa-solid fa-check text-emerald-600 mr-2"></i> Unlimited invoices</li>
            <li><i class="fa-solid fa-check text-emerald-600 mr-2"></i> Stock & barcode</li>
            <li><i class="fa-solid fa-check text-emerald-600 mr-2"></i> Basic CRM & loyalty</li>
          </ul>
        </article>

        <!-- HotelFlow -->
        <article class="panel soft-shadow rounded-2xl p-5 reveal">
          <div class="flex items-start justify-between">
            <div>
              <h3 class="text-lg font-semibold">HotelFlow</h3>
              <p class="text-sm text-slate-600 mt-1">Reservations, front desk, F&amp;B POS, folios.</p>
            </div>
            <div class="w-10 h-10 rounded-xl bg-sky-100 grid place-content-center">
              <i class="fa-solid fa-hotel text-sky-700"></i>
            </div>
          </div>
          <div class="mt-2">
            <span class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-emerald-50 text-emerald-900 font-semibold border border-emerald-100" data-price="2000">
              <i class="fa-solid fa-bangladeshi-taka-sign"></i><span class="price">2,000</span> BDT / mo
            </span>
          </div>
          <a href="/contact" class="mt-3 inline-flex items-center gap-2 rounded-xl bg-slate-900 hover:bg-slate-800 text-white px-4 py-2.5 font-semibold focus-ring">
            <i class="fa-solid fa-rocket"></i> Start now
          </a>
          <ul class="mt-3 text-sm text-slate-600 space-y-1">
            <li><i class="fa-solid fa-check text-emerald-600 mr-2"></i> Room & rate plans</li>
            <li><i class="fa-solid fa-check text-emerald-600 mr-2"></i> Housekeeping & tasks</li>
            <li><i class="fa-solid fa-check text-emerald-600 mr-2"></i> F&amp;B POS integration</li>
          </ul>
        </article>

        <!-- Bhata -->
        <article class="panel soft-shadow rounded-2xl p-5 reveal">
          <div class="flex items-start justify-between">
            <div>
              <h3 class="text-lg font-semibold">Bhata (Brick Field)</h3>
              <p class="text-sm text-slate-600 mt-1">Production, dispatch, ledger & logistics.</p>
            </div>
            <div class="w-10 h-10 rounded-xl bg-amber-100 grid place-content-center">
              <i class="fa-solid fa-industry text-amber-700"></i>
            </div>
          </div>
          <div class="mt-2">
            <span class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-emerald-50 text-emerald-900 font-semibold border border-emerald-100" data-price="2000">
              <i class="fa-solid fa-bangladeshi-taka-sign"></i><span class="price">2,000</span> BDT / mo
            </span>
          </div>
          <a href="/contact" class="mt-3 inline-flex items-center gap-2 rounded-xl bg-slate-900 hover:bg-slate-800 text-white px-4 py-2.5 font-semibold focus-ring">
            <i class="fa-solid fa-rocket"></i> Start now
          </a>
          <ul class="mt-3 text-sm text-slate-600 space-y-1">
            <li><i class="fa-solid fa-check text-emerald-600 mr-2"></i> Kiln loads & batches</li>
            <li><i class="fa-solid fa-check text-emerald-600 mr-2"></i> Fleet & dispatch</li>
            <li><i class="fa-solid fa-check text-emerald-600 mr-2"></i> Party ledger & dues</li>
          </ul>
        </article>

        <!-- School -->
        <article class="panel soft-shadow rounded-2xl p-5 reveal">
          <div class="flex items-start justify-between">
            <div>
              <h3 class="text-lg font-semibold">School</h3>
              <p class="text-sm text-slate-600 mt-1">Admissions, fees, attendance, results.</p>
            </div>
            <div class="w-10 h-10 rounded-xl bg-purple-100 grid place-content-center">
              <i class="fa-solid fa-graduation-cap text-purple-700"></i>
            </div>
          </div>
          <div class="mt-2">
            <span class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-emerald-50 text-emerald-900 font-semibold border border-emerald-100" data-price="2000">
              <i class="fa-solid fa-bangladeshi-taka-sign"></i><span class="price">2,000</span> BDT / mo
            </span>
          </div>
          <a href="/contact" class="mt-3 inline-flex items-center gap-2 rounded-xl bg-slate-900 hover:bg-slate-800 text-white px-4 py-2.5 font-semibold focus-ring">
            <i class="fa-solid fa-rocket"></i> Start now
          </a>
          <ul class="mt-3 text-sm text-slate-600 space-y-1">
            <li><i class="fa-solid fa-check text-emerald-600 mr-2"></i> Student records</li>
            <li><i class="fa-solid fa-check text-emerald-600 mr-2"></i> Fee & scholarship</li>
            <li><i class="fa-solid fa-check text-emerald-600 mr-2"></i> Parent portal (EN/BN)</li>
          </ul>
        </article>

        <!-- MedFlow -->
        <article class="panel soft-shadow rounded-2xl p-5 reveal">
          <div class="flex items-start justify-between">
            <div>
              <h3 class="text-lg font-semibold">MedFlow</h3>
              <p class="text-sm text-slate-600 mt-1">Clinic/pharmacy operations & inventory.</p>
            </div>
            <div class="w-10 h-10 rounded-xl bg-rose-100 grid place-content-center">
              <i class="fa-solid fa-briefcase-medical text-rose-700"></i>
            </div>
          </div>
          <div class="mt-2">
            <span class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-emerald-50 text-emerald-900 font-semibold border border-emerald-100" data-price="2000">
              <i class="fa-solid fa-bangladeshi-taka-sign"></i><span class="price">2,000</span> BDT / mo
            </span>
          </div>
          <a href="/contact" class="mt-3 inline-flex items-center gap-2 rounded-xl bg-slate-900 hover:bg-slate-800 text-white px-4 py-2.5 font-semibold focus-ring">
            <i class="fa-solid fa-rocket"></i> Start now
          </a>
          <ul class="mt-3 text-sm text-slate-600 space-y-1">
            <li><i class="fa-solid fa-check text-emerald-600 mr-2"></i> OPD/IPD basics</li>
            <li><i class="fa-solid fa-check text-emerald-600 mr-2"></i> Pharmacy stock</li>
            <li><i class="fa-solid fa-check text-emerald-600 mr-2"></i> Billing & receipts</li>
          </ul>
        </article>

        <!-- DMS -->
        <article class="panel soft-shadow rounded-2xl p-5 reveal">
          <div class="flex items-start justify-between">
            <div>
              <h3 class="text-lg font-semibold">DMS</h3>
              <p class="text-sm text-slate-600 mt-1">Docs, approvals, retention & search.</p>
            </div>
            <div class="w-10 h-10 rounded-xl bg-slate-100 grid place-content-center">
              <i class="fa-solid fa-folder-tree text-slate-700"></i>
            </div>
          </div>
          <div class="mt-2">
            <span class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-emerald-50 text-emerald-900 font-semibold border border-emerald-100" data-price="2000">
              <i class="fa-solid fa-bangladeshi-taka-sign"></i><span class="price">2,000</span> BDT / mo
            </span>
          </div>
          <a href="/contact" class="mt-3 inline-flex items-center gap-2 rounded-xl bg-slate-900 hover:bg-slate-800 text-white px-4 py-2.5 font-semibold focus-ring">
            <i class="fa-solid fa-rocket"></i> Start now
          </a>
          <ul class="mt-3 text-sm text-slate-600 space-y-1">
            <li><i class="fa-solid fa-check text-emerald-600 mr-2"></i> Versioning & audit</li>
            <li><i class="fa-solid fa-check text-emerald-600 mr-2"></i> Workflows & approvals</li>
            <li><i class="fa-solid fa-check text-emerald-600 mr-2"></i> Powerful search</li>
          </ul>
        </article>
      </div>

      <p class="mt-6 text-sm text-slate-600 reveal">
        All prices exclude VAT where applicable. Add or remove modules anytime. Discounts available for multi-branch rollouts.
      </p>
    </section>

    <!-- Comparison -->
    <section class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 pb-12 reveal">
      <div class="panel soft-shadow rounded-2xl p-6">
        <h2 class="text-xl font-bold mb-4">What’s included with every module</h2>
        <div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-4 text-sm">
          <div class="flex items-start gap-3"><i class="fa-solid fa-shield-halved text-emerald-600 mt-1"></i><span>Secure tenancy isolation & RBAC</span></div>
          <div class="flex items-start gap-3"><i class="fa-solid fa-cloud-arrow-up text-emerald-600 mt-1"></i><span>Daily backups & export (CSV/PDF)</span></div>
          <div class="flex items-start gap-3"><i class="fa-solid fa-language text-emerald-600 mt-1"></i><span>English & Bangla UI</span></div>
          <div class="flex items-start gap-3"><i class="fa-solid fa-headset text-emerald-600 mt-1"></i><span>Bangladesh timezone support</span></div>
        </div>
      </div>
    </section>

    <!-- FAQs -->
    <section class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 pb-16 reveal reveal-slow">
      <div class="grid lg:grid-cols-2 gap-6">
        <div class="panel soft-shadow rounded-2xl p-6">
          <h3 class="font-semibold text-lg">Do you offer annual billing?</h3>
          <p class="mt-2 text-sm text-slate-600">Yes — annual plans reflect a 10% discount. Invoices can be issued in BDT with bank transfer or MFS.</p>
        </div>
        <div class="panel soft-shadow rounded-2xl p-6">
          <h3 class="font-semibold text-lg">Is onboarding included?</h3>
          <p class="mt-2 text-sm text-slate-600">Lite onboarding is free (tenant setup, roles, basic training). Extended implementation is available on request.</p>
        </div>
        <div class="panel soft-shadow rounded-2xl p-6">
          <h3 class="font-semibold text-lg">Can I add modules later?</h3>
          <p class="mt-2 text-sm text-slate-600">Absolutely. Modules are add-on friendly. Billing updates from the next cycle.</p>
        </div>
        <div class="panel soft-shadow rounded-2xl p-6">
          <h3 class="font-semibold text-lg">Where is my data hosted?</h3>
          <p class="mt-2 text-sm text-slate-600">Your tenant runs in secure cloud infrastructure with encrypted transport (HTTPS). Backups are retained per policy.</p>
        </div>
      </div>
    </section>

    <!-- CTA -->
    <section class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 pb-16 reveal">
      <div class="panel soft-shadow rounded-2xl p-6 sm:p-8 text-center">
        <h2 class="text-2xl sm:text-3xl font-extrabold">Ready to get started?</h2>
        <p class="mt-2 text-slate-700">Talk to our team to activate your modules and invite your organization.</p>
        <div class="mt-4 flex flex-col sm:flex-row gap-3 justify-center">
          <a href="/cp/login" class="focus-ring inline-flex items-center gap-2 rounded-xl bg-slate-900 text-white px-5 py-3 font-semibold soft-shadow hover:bg-slate-800 transition">
            <i class="fa-solid fa-screwdriver-wrench"></i> Control Panel
          </a>
          <a href="/tenant/login" class="focus-ring inline-flex items-center gap-2 rounded-xl bg-emerald-600 text-white px-5 py-3 font-semibold soft-shadow hover:bg-emerald-700 transition">
            <i class="fa-solid fa-right-to-bracket"></i> Tenant Login
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
  
  <!-- Hidden SEO Helper for Klinflow Pricing Page -->
<div class="sr-only" aria-hidden="true">
    <h2>Affordable ERP Software Pricing and Plans in Bangladesh (BDT)</h2>
    <p>Find the **most affordable ERP pricing in Bangladesh** with Klinflow. Our transparent **monthly subscription costs** start at just **৳2000 BDT** per module, offering the best value for small to medium-sized businesses.</p>
    <p>Explore our cost-effective plans for:</p>
    <ul>
      <li>**Retail POS Software Price BDT** – Check the low monthly fee for our Point of Sale module.</li>
      <li>**Hotel Management System Cost** – Review the subscription plans for HotelFlow.</li>
      <li>**School Management Software Pricing** – Get details on budget-friendly fees for educational institutions.</li>
      <li>**Free Trial ERP Bangladesh** – Learn how to start your zero-cost trial today.</li>
      <li>**Best Value Business Software** – Compare features and find the plan that fits your budget.</li>
    </ul>
    <p>Klinflow provides clear, fixed, **upfront pricing with no hidden fees**. Invest in the best, low-cost business management software available locally.</p>
</div>

  <!-- FOOTER -->
  <footer class="border-t border-slate-200/60">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 text-sm flex flex-col sm:flex-row items-center justify-between">
      <p class="opacity-80">© <span id="y"></span> KlinFlow</p>
      <div class="opacity-80 flex items-center gap-6">
        <a href="/privacy" class="hover:text-slate-900">Privacy</a>
        <a href="/terms" class="hover:text-slate-900">Terms</a>
        <a href="/contact" class="hover:text-slate-900">Contact</a>
      </div>
    </div>
  </footer>

  <script>
    // Footer year
    document.getElementById('y').textContent = new Date().getFullYear();

    // Mobile menu toggle
    const btn = document.getElementById('menuBtn');
    const nav = document.getElementById('mobileNav');
    btn?.addEventListener('click', () => nav.classList.toggle('hidden'));

    // Billing toggle (Monthly default: 2,000; Annual: 10% OFF → 1,800/mo equiv)
    const monthlyBtn = document.getElementById('bill-monthly');
    const annualBtn  = document.getElementById('bill-annual');
    const priceBadges = document.querySelectorAll('[data-price]');

    function setMonthly(){
      priceBadges.forEach(b => {
        b.querySelector('.price').textContent = '2,000';
        b.lastChild.textContent = ' BDT / mo';
      });
      monthlyBtn.classList.add('bg-emerald-600','text-white');
      monthlyBtn.classList.remove('bg-white','border','border-slate-200');
      annualBtn.classList.remove('bg-emerald-600','text-white');
      annualBtn.classList.add('bg-white','border','border-slate-200');
    }
    function setAnnual(){
      priceBadges.forEach(b => {
        b.querySelector('.price').textContent = '1,800';
        b.lastChild.textContent = ' BDT / mo (annual)';
      });
      annualBtn.classList.add('bg-emerald-600','text-white');
      annualBtn.classList.remove('bg-white','border','border-slate-200');
      monthlyBtn.classList.remove('bg-emerald-600','text-white');
      monthlyBtn.classList.add('bg-white','border','border-slate-200');
    }
    monthlyBtn?.addEventListener('click', setMonthly);
    annualBtn?.addEventListener('click', setAnnual);

    // Reveal on scroll (fade-in)
    const els = Array.from(document.querySelectorAll('.reveal'));
    const io = new IntersectionObserver((entries) => {
      entries.forEach(e => { if (e.isIntersecting) e.target.classList.add('in'); });
    }, { threshold: .12 });
    els.forEach(el => io.observe(el));
  </script>
  
  <script> window.chtlConfig = { chatbotId: "8748546719" } </script>
<script async data-id="8748546719" id="chtl-script" type="text/javascript" src="https://chatling.ai/js/embed.js"></script>
  
</body>
</html>