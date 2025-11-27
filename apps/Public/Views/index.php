<!doctype html>
<html lang="en" class="h-full">
<head>
  <meta charset="utf-8" />
  <!-- Robots + indexing intent -->
<meta name="robots" content="index, follow, max-snippet:-1, max-image-preview:large, max-video-preview:-1" />

<!-- Hreflang (English default + Bangla); remove if you don’t have bn version yet -->
<link rel="alternate" href="https://www.klinflow.com/" hreflang="x-default" />
<link rel="alternate" href="https://www.klinflow.com/" hreflang="en-bd" />
<link rel="alternate" href="https://www.klinflow.com/bn/" hreflang="bn-bd" />

<!-- Twitter cards -->
<meta name="twitter:card" content="summary_large_image" />
<meta name="twitter:title" content="Klinflow: All-in-One Business Software for Bangladesh-Best ERP Software in Bangladesh - POS, Hotel, School Management" />
<meta name="twitter:description" content="Simple, efficient, and affordable ERP with multi-tenant control, POS, HotelFlow, MedFlow, and more." />
<meta name="twitter:image" content="https://www.klinflow.com/klinflow-social-share-image.jpg" />

<!-- Performance hints (helps Core Web Vitals) -->
<link rel="preconnect" href="https://cdnjs.cloudflare.com" crossorigin />
<link rel="preconnect" href="https://cdn.tailwindcss.com" crossorigin />
<link rel="dns-prefetch" href="https://cdnjs.cloudflare.com" />
<link rel="dns-prefetch" href="https://cdn.tailwindcss.com" />

<!-- Preload above-the-fold assets (swap to your actual hero/illustration if different) -->
<link rel="preload" as="image" href="/assets/brand/logo.png" />
<link rel="preload" as="image" href="/assets/illustrations/erp-dashboard.png" />
<link rel="apple-touch-icon" href="/assets/brand/favicon.png" />

<!-- Sitemap (discovery) -->
<link rel="sitemap" type="application/xml" title="Sitemap" href="https://www.klinflow.com/sitemap.xml" />
 
  
<!---Site Scripts---->
<script src="https://cdn.tailwindcss.com"></script>

<!-- Consolidated Structured Data (@graph) -->
<script type="application/ld+json">
{
  "@context": "https://schema.org",
  "@graph": [
    {
      "@type": "Organization",
      "@id": "https://www.klinflow.com/#org",
      "name": "KlinFlow",
      "url": "https://www.klinflow.com/",
      "logo": {
        "@type": "ImageObject",
        "url": "https://www.klinflow.com/assets/brand/logo.png"
      },
      "sameAs": [
        "https://www.facebook.com/klinflow",
        "https://www.linkedin.com/company/klinflow"
      ],
      "contactPoint": [{
        "@type": "ContactPoint",
        "contactType": "customer support",
        "areaServed": "BD",
        "availableLanguage": ["en","bn"],
        "email": "support@klinflow.com",
        "telephone": "+8801712378526"
      }]
    },
    {
      "@type": "WebSite",
      "@id": "https://www.klinflow.com/#website",
      "url": "https://www.klinflow.com/",
      "name": "KlinFlow",
      "publisher": { "@id": "https://www.klinflow.com/#org" },
      "inLanguage": "en-BD",
      "potentialAction": [{
        "@type": "SearchAction",
        "target": "https://www.klinflow.com/search?q={query}",
        "query-input": "required name=query"
      }]
    },

    /* Software application (main SaaS) */
    {
      "@type": "SoftwareApplication",
      "@id": "https://www.klinflow.com/#app",
      "name": "KlinFlow — Multi-tenant ERP",
      "applicationCategory": "BusinessApplication",
      "operatingSystem": "Web",
      "offers": {
        "@type": "Offer",
        "price": "2000",
        "priceCurrency": "BDT",
        "priceSpecification": {
          "@type": "UnitPriceSpecification",
          "price": "2000",
          "priceCurrency": "BDT",
          "billingDuration": "P1M"
        },
        "availability": "https://schema.org/InStock",
        "url": "https://www.klinflow.com/pricing"
      },
      "aggregateRating": {
        "@type": "AggregateRating",
        "ratingValue": "4.8",
        "reviewCount": "37"
      },
      "applicationSuite": "Retail POS, HotelFlow, Bhata, School, MedFlow, Distribution",
      "publisher": { "@id": "https://www.klinflow.com/#org" }
    },

    /* Optional: individual modules as sub-apps (helps long-tail queries) */
    { "@type":"SoftwareApplication","name":"KlinFlow Retail POS","applicationCategory":"PointOfSaleApplication","operatingSystem":"Web","offers":{"@type":"Offer","price":"2000","priceCurrency":"BDT"},"url":"https://www.klinflow.com/#pos" },
    { "@type":"SoftwareApplication","name":"KlinFlow HotelFlow","applicationCategory":"HotelManagementSystem","operatingSystem":"Web","offers":{"@type":"Offer","price":"2000","priceCurrency":"BDT"},"url":"https://www.klinflow.com/#hotelflow" },
    { "@type":"SoftwareApplication","name":"KlinFlow Bhata (Brick Field)","applicationCategory":"BusinessApplication","operatingSystem":"Web","offers":{"@type":"Offer","price":"2000","priceCurrency":"BDT"},"url":"https://www.klinflow.com/#bhata" },
    { "@type":"SoftwareApplication","name":"KlinFlow School","applicationCategory":"SchoolManagementSoftware","operatingSystem":"Web","offers":{"@type":"Offer","price":"2000","priceCurrency":"BDT"},"url":"https://www.klinflow.com/#school" },
    { "@type":"SoftwareApplication","name":"KlinFlow MedFlow","applicationCategory":"MedicalSoftware","operatingSystem":"Web","offers":{"@type":"Offer","price":"2000","priceCurrency":"BDT"},"url":"https://www.klinflow.com/#medflow" },
    { "@type":"SoftwareApplication","name":"KlinFlow Distribution","applicationCategory":"EnterpriseApplication","operatingSystem":"Web","offers":{"@type":"Offer","price":"2000","priceCurrency":"BDT"},"url":"https://www.klinflow.com/#dms" },

    /* Breadcrumbs (helps sitelinks; adjust paths if needed) */
    {
      "@type": "BreadcrumbList",
      "itemListElement": [
        { "@type":"ListItem","position":1,"name":"Home","item":"https://www.klinflow.com/" },
        { "@type":"ListItem","position":2,"name":"Pricing","item":"https://www.klinflow.com/pricing" },
        { "@type":"ListItem","position":3,"name":"Contact","item":"https://www.klinflow.com/contact" }
      ]
    },

    /* FAQ — keep in sync with on-page content to avoid mismatch */
    {
      "@type": "FAQPage",
      "mainEntity": [
        {"@type":"Question","name":"What is KlinFlow?","acceptedAnswer":{"@type":"Answer","text":"KlinFlow is a multi-tenant business platform with modules for Retail POS, HotelFlow, Bhata, School, MedFlow, and Distribution. English and Bangla UI are supported."}},
        {"@type":"Question","name":"How does pricing work?","acceptedAnswer":{"@type":"Answer","text":"Each module is a flat monthly fee in BDT with optional annual savings. See the Pricing page for details."}},
        {"@type":"Question","name":"How does KlinFlow keep data secure?","acceptedAnswer":{"@type":"Answer","text":"Per-organization isolation, TLS, hashed credentials, RBAC with audit, and daily offsite backups. See the Security page."}},
        {"@type":"Question","name":"Can I manage multiple organizations or branches?","acceptedAnswer":{"@type":"Answer","text":"Yes. KlinFlow is multi-tenant by design; create organizations and branches and assign roles per module."}},
        {"@type":"Question","name":"Do you support VAT invoices and reports?","acceptedAnswer":{"@type":"Answer","text":"Yes—Bangladesh-ready VAT invoicing and exportable CSV/PDF reports are available in supported modules."}},
        {"@type":"Question","name":"Is there a demo or free trial?","acceptedAnswer":{"@type":"Answer","text":"Yes—sign up for a sandbox or request a short video walkthrough."}}
      ]
    }
  ]
}
</script>

<!-- Basic consent-friendly analytics (replace G-XXXXX if you add GA; keeps CWV unaffected) -->
<script>
  // Example placeholder to avoid blocking rendering; integrate your real analytics later.
  // window.dataLayer = window.dataLayer || []; function gtag(){dataLayer.push(arguments)}
  // gtag('js', new Date()); gtag('config', 'G-XXXXXXX', { anonymize_ip: true });
</script>
  
  <style>
    body {
      background: linear-gradient(180deg, #f8fef9 0%, #f3fcf5 40%, #ffffff 100%);
      color: #1f2937;
    }
    .soft-shadow { box-shadow: 0 10px 25px rgba(0,0,0,.07), 0 2px 8px rgba(0,0,0,.04); }
    .icon-bubble { width: 3rem; height: 3rem; display: grid; place-content: center; border-radius: 0.75rem; }
    .transition-basic { transition: all .25s ease; }
    .drawer-enter { transform: translateX(100%); opacity: 0; }
    .drawer-open { transform: translateX(0); opacity: 1; }
    .overlay-hidden { opacity: 0; pointer-events: none; }
    .overlay-open { opacity: 1; pointer-events: auto; }
    #toast { position: fixed; bottom: 1.5rem; right: 1.5rem; background: #15803d; color: #fff; padding: .75rem 1.25rem; border-radius: .75rem; box-shadow: 0 4px 10px rgba(0,0,0,.2); opacity: 0; transform: translateY(20px); transition: all .3s ease; font-size: .875rem; }
    #toast.show { opacity: 1; transform: translateY(0); }
    
    /* Reveal animation (keeps reduced-motion respected) */
.reveal { opacity: 0; transform: translateY(16px); transition: opacity .6s ease, transform .6s ease; }
.reveal.in { opacity: 1; transform: translateY(0); }
@media (prefers-reduced-motion: reduce){
  .reveal, .reveal.in { opacity: 1 !important; transform: none !important; transition: none !important; }
}
    
  </style>
  
  
  
</head>

<body class="font-sans overflow-x-hidden">

  <!-- HEADER -->
<header class="w-full sticky top-0 bg-white z-50 soft-shadow">
  <div class="max-w-7xl mx-auto px-6 py-4 flex items-center justify-between">
    <a href="/" class="flex items-center gap-2" aria-label="KlinFlow — Home">
      <img src="/assets/brand/logo.png" alt="KlinFlow" class="h-8 w-auto">
    </a>

    <!-- Desktop nav -->
    <nav class="hidden sm:flex items-center gap-6 text-sm">
      <a href="/about"   class="hover:text-green-600">About</a>
      <a href="/contact" class="hover:text-green-600">Contact</a>
      <a href="/pricing" class="font-semibold text-green-600">Pricing</a>
      <a href="/why"     class="hover:text-green-600">Why KlinFlow</a>
      <a href="/ticket"  class="block px-2 py-2 rounded hover:bg-gray-100">Support Ticket</a>
      <a href="/signup"  class="block px-2 py-2 rounded hover:bg-gray-100">Sign up free</a>
    </nav>

    <!-- Mobile toggle -->
    <button id="nav-toggle" aria-label="Open menu" aria-expanded="false"
            class="sm:hidden focus:outline-none">
      <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
           stroke-width="2" stroke="currentColor" class="w-7 h-7">
        <path stroke-linecap="round" stroke-linejoin="round"
              d="M4 6h16M4 12h16M4 18h16"/>
      </svg>
    </button>
  </div>
</header>

<!-- Overlay + Drawer -->
<div id="overlay" class="fixed inset-0 bg-black/40 transition-basic overlay-hidden sm:hidden z-40"></div>

<aside id="mobile-drawer"
       class="fixed top-0 right-0 h-full w-64 bg-white soft-shadow transition-basic drawer-enter sm:hidden z-50"
       role="dialog" aria-modal="true" aria-labelledby="drawer-title" aria-hidden="true">
  <div class="flex items-center justify-between px-5 py-4 border-b border-gray-200">
    <h2 id="drawer-title" class="font-semibold">Menu</h2>
    <button id="nav-close" aria-label="Close menu">
      <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
           stroke-width="2" stroke="currentColor" class="w-6 h-6">
        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
      </svg>
    </button>
  </div>
  <nav class="p-4 space-y-3">
    <a href="/about"   class="block px-2 py-2 rounded hover:bg-gray-100">About</a>
    <a href="/contact" class="block px-2 py-2 rounded hover:bg-gray-100">Contact</a>
    <a href="/pricing" class="block px-2 py-2 rounded hover:bg-gray-100">Pricing</a>
    <a href="/why"     class="block px-2 py-2 rounded hover:bg-gray-100">Why KlinFlow</a>
    <a href="/ticket"  class="block px-2 py-2 rounded hover:bg-gray-100">Support Ticket</a>
    <a href="/signup"  class="block px-2 py-2 rounded hover:bg-gray-100">Sign up free</a>
  </nav>
</aside>

<!-- HERO + GRID -->
<section class="max-w-7xl mx-auto px-6 py-16 grid lg:grid-cols-2 gap-12 items-center">
  <!-- LEFT column -->
  <div class="space-y-6">
    <h1 class="text-5xl font-extrabold leading-tight text-gray-900">
      All your business on <span class="text-green-600">one platform</span>.
    </h1>
    <p class="text-lg text-gray-700">
      Manage operations — from POS to School — securely in KlinFlow.
    </p>

    <!-- CTA: CP / Tenant (SVG icons + shortcut hints) -->
    <div class="flex flex-wrap gap-4">
      <!-- Control Panel -->
      <a href="/cp/login"
         class="inline-flex items-center gap-2 bg-gray-900 text-white px-6 py-3 rounded-xl font-semibold soft-shadow hover:bg-gray-800 transition-basic"
         data-kf-shortcut="c" aria-label="Control Panel Login (shortcut: C)">
        <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                d="M14.7 6.3a4.5 4.5 0 0 1 6.36 6.36l-3.18-1.06-3.6 3.6 1.07 3.18A4.5 4.5 0 0 1 8.99 12L4 7.01 6.12 4.9l4.99 4.99a2.25 2.25 0 1 0 3.18-3.18l-.59-.59z"/>
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                d="M3.75 20.25l6.5-6.5"/>
        </svg>
        <span>Control Panel Login</span>
      </a>

      <!-- Tenant -->
      <a href="/tenant/login"
         class="inline-flex items-center gap-2 bg-green-600 text-white px-6 py-3 rounded-xl font-semibold soft-shadow hover:bg-green-700 transition-basic"
         data-kf-shortcut="t" aria-label="Tenant Login (shortcut: T)">
        <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">
          <circle cx="12" cy="12" r="9" stroke-width="1.8"/>
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                d="M3 12h18M12 3c3 3.5 3 14 0 18M12 3c-3 3.5-3 14 0 18"/>
        </svg>
        <span>Tenant Login</span>
      </a>
    </div>

    <p class="text-sm text-gray-600">
      Tip: Press <kbd class="bg-white border px-1 rounded">C</kbd> for Control Panel
      or <kbd class="bg-white border px-1 rounded">T</kbd> for Tenant.
    </p>
  </div> <!-- ✅ CLOSE LEFT COLUMN -->

  <!-- RIGHT column: MODULE GRID -->
  <div>
    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-3 xl:grid-cols-3 gap-5 mt-2">
      <!-- Retail POS -->
      <div class="group bg-white rounded-xl soft-shadow hover:-translate-y-0.5 transition-all p-4 flex flex-col items-center text-center">
        <div class="icon-bubble bg-green-100 mb-3 w-12 h-12 flex items-center justify-center">
          <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="#15803d" class="w-6 h-6">
            <path stroke-linecap="round" stroke-linejoin="round" d="M3 3h18v6H3zM4 9v10a2 2 0 002 2h12a2 2 0 002-2V9"/>
          </svg>
        </div>
        <h3 class="font-semibold text-gray-900 text-sm">Retail POS</h3>
        <p class="text-xs text-gray-600 mt-0.5">Billing, inventory, VAT</p>
      </div>

      <!-- HotelFlow -->
      <div class="group bg-white rounded-xl soft-shadow hover:-translate-y-0.5 transition-all p-4 flex flex-col items-center text-center">
        <div class="icon-bubble bg-blue-100 mb-3 w-12 h-12 flex items-center justify-center">
          <svg xmlns="http://www.w3.org/2000/svg" fill="#0369a1" viewBox="0 0 24 24" class="w-6 h-6">
            <path d="M3 10h18v10H3zM7 6h10v2H7zM5 12v6h14v-6z"/>
          </svg>
        </div>
        <h3 class="font-semibold text-gray-900 text-sm">HotelFlow</h3>
        <p class="text-xs text-gray-600 mt-0.5">Reservations & POS</p>
      </div>

      <!-- Bhata -->
      <div class="group bg-white rounded-xl soft-shadow hover:-translate-y-0.5 transition-all p-4 flex flex-col items-center text-center">
        <div class="icon-bubble bg-amber-100 mb-3 w-12 h-12 flex items-center justify-center">
          <svg xmlns="http://www.w3.org/2000/svg" fill="#b45309" viewBox="0 0 24 24" class="w-6 h-6">
            <path d="M3 4h18v16H3zM5 6v12h14V6z"/>
          </svg>
        </div>
        <h3 class="font-semibold text-gray-900 text-sm">Bhata</h3>
        <p class="text-xs text-gray-600 mt-0.5">Dispatch & Ledger</p>
      </div>

      <!-- School -->
      <div class="group bg-white rounded-xl soft-shadow hover:-translate-y-0.5 transition-all p-4 flex flex-col items-center text-center">
        <div class="icon-bubble bg-purple-100 mb-3 w-12 h-12 flex items-center justify-center">
          <svg xmlns="http://www.w3.org/2000/svg" fill="#7e22ce" viewBox="0 0 24 24" class="w-6 h-6">
            <path d="M12 2L1 9l11 7 9-5.7V20h2V9z"/>
          </svg>
        </div>
        <h3 class="font-semibold text-gray-900 text-sm">School</h3>
        <p class="text-xs text-gray-600 mt-0.5">Fees & Results</p>
      </div>

      <!-- MedFlow -->
      <div class="group bg-white rounded-xl soft-shadow hover:-translate-y-0.5 transition-all p-4 flex flex-col items-center text-center">
        <div class="icon-bubble bg-rose-100 mb-3 w-12 h-12 flex items-center justify-center">
          <svg xmlns="http://www.w3.org/2000/svg" fill="#be123c" viewBox="0 0 24 24" class="w-6 h-6">
            <path d="M10 2h4v8h8v4h-8v8h-4v-8H2v-4h8z"/>
          </svg>
        </div>
        <h3 class="font-semibold text-gray-900 text-sm">MedFlow</h3>
        <p class="text-xs text-gray-600 mt-0.5">Pharmacy & Clinic</p>
      </div>

      <!-- Distribution -->
      <div class="group bg-white rounded-xl soft-shadow hover:-translate-y-0.5 transition-all p-4 flex flex-col items-center text-center">
        <div class="icon-bubble bg-indigo-100 mb-3 w-12 h-12 flex items-center justify-center">
          <svg xmlns="http://www.w3.org/2000/svg" fill="#4338ca" viewBox="0 0 24 24" class="w-6 h-6">
            <path d="M3 3h18v6H3zM4 9v10a2 2 0 002 2h12a2 2 0 002-2V9M8 13h8v2H8z"/>
          </svg>
        </div>
        <h3 class="font-semibold text-gray-900 text-sm">Distribution</h3>
        <p class="text-xs text-gray-600 mt-0.5">Supply & Sales Network</p>
      </div>
    </div>
  </div>
</section>
  
  
 <!-- ===== SECURITY ===== -->
<section id="security" class="relative py-14 bg-gradient-to-b from-green-50 to-white border-t border-gray-100 reveal">
  <div class="max-w-7xl mx-auto px-6 grid lg:grid-cols-2 gap-10 items-center">
    <div>
      <h2 class="text-3xl font-extrabold text-gray-900">Security & tenancy isolation</h2>
      <p class="text-gray-700 mt-3">Per-organization data isolation, encryption in transit, daily backups, and strict RBAC keep your operations safe.</p>
      <ul class="mt-4 space-y-2 text-sm text-gray-800">
        <li class="flex items-start gap-2"><i class="fa-solid fa-shield-halved text-green-700 mt-0.5"></i> Isolated org data with strong tenancy boundaries</li>
        <li class="flex items-start gap-2"><i class="fa-solid fa-lock text-green-700 mt-0.5"></i> TLS everywhere; credentials hashed</li>
        <li class="flex items-start gap-2"><i class="fa-solid fa-database text-green-700 mt-0.5"></i> Daily offsite backups & export (CSV/PDF)</li>
        <li class="flex items-start gap-2"><i class="fa-solid fa-user-shield text-green-700 mt-0.5"></i> Role-based access with audit trail</li>
      </ul>
      <a href="/security" class="inline-flex items-center gap-2 mt-5 font-semibold text-green-700">
        See our security practices
        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="#16a34a" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="m9 5 7 7-7 7"/></svg>
      </a>
    </div>
    <div class="bg-white rounded-2xl soft-shadow p-6">
      <img src="/assets/illustrations/security-shield.png" alt="Security & isolation" class="w-full h-auto rounded-xl">
    </div>
  </div>
</section>
  
 <!-- ========================= -->
<!-- KlinFlow FAQ (configurable accordion/multi) -->
<!-- ========================= -->
<section id="faq" class="relative bg-gradient-to-b from-white via-green-50/40 to-white py-16">
  <div class="mx-auto max-w-7xl px-6">
    <div class="grid lg:grid-cols-[1.1fr_.9fr] gap-10 items-start">
      <div>
        <p class="inline-flex items-center gap-2 text-xs font-semibold uppercase tracking-wider text-green-800 bg-green-100 px-3 py-1 rounded-full shadow-sm">
          <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="#166534" class="w-4 h-4">
            <path d="M12 2 3 5v6c0 5.55 3.84 10.74 9 12 5.16-1.26 9-6.45 9-12V5l-9-3zm0 2.18L18.5 7v4.5c0 4.25-2.94 8.37-6.5 9.67-3.56-1.3-6.5-5.42-6.5-9.67V7L12 4.18zM11 10v2h2v-2h-2zm0 4v2h2v-2h-2z"/>
          </svg>
          FAQ
        </p>
        <h2 class="mt-4 text-4xl sm:text-5xl font-extrabold leading-tight">
          Frequently asked questions
        </h2>
        <p class="mt-3 text-gray-700 max-w-2xl">
          Quick answers about KlinFlow’s pricing, security, multi-tenant model, modules, and support.
          Search below or browse popular topics.
        </p>

        <!-- Search -->
        <div class="mt-6">
          <label for="faq-search" class="sr-only">Search FAQs</label>
          <div class="relative group">
            <svg xmlns="http://www.w3.org/2000/svg" class="absolute left-4 top-1/2 -translate-y-1/2 w-5 h-5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <circle cx="11" cy="11" r="7"></circle>
              <line x1="16.65" y1="16.65" x2="21" y2="21"></line>
            </svg>
            <input id="faq-search" type="search" placeholder="Search: pricing, VAT, backups, scanner…"
                   class="w-full pl-12 pr-4 py-3 rounded-2xl border border-gray-200 bg-white/80 backdrop-blur focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500 shadow-sm">
          </div>
          <p id="faq-count" class="mt-2 text-xs text-gray-500"></p>
        </div>
      </div>

      <!-- “Can't find?” CTA -->
      <aside class="panel soft-shadow rounded-3xl p-6 bg-white">
        <div class="flex items-start gap-4">
          <div class="h-12 w-12 rounded-2xl bg-green-600/10 text-green-700 grid place-items-center">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="#166534" class="w-6 h-6">
              <path d="M20 2H4a2 2 0 0 0-2 2v16l4-4h14a2 2 0 0 0 2-2V4a2 2 0 0 0-2-2z"/>
            </svg>
          </div>
          <div>
            <h3 class="font-semibold text-lg">Can’t find what you need?</h3>
            <p class="text-gray-600 text-sm mt-1">We reply within one business day (Bangladesh time).</p>
            <div class="mt-3 flex flex-wrap gap-2">
              <a href="/ticket" class="inline-flex items-center gap-2 rounded-xl bg-green-600 text-white px-3.5 py-2 text-sm font-semibold hover:bg-green-700 transition">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="w-4 h-4">
                  <path d="M22 2 11 13"></path>
                  <path d="M22 2 15 22 11 13 2 9l20-7z"></path>
                </svg>
                Open a ticket
              </a>
              <button type="button" data-open-chat
                      class="inline-flex items-center gap-2 rounded-xl bg-white border border-gray-200 px-3.5 py-2 text-sm font-semibold hover:bg-gray-50 transition">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="#15803d" class="w-4 h-4">
                  <path d="M21 11.5a8.38 8.38 0 0 1-1.9 5.4 8.5 8.5 0 0 1-6.6 3.1 8.38 8.38 0 0 1-5.4-1.9L3 21l2.9-4.1A8.38 8.38 0 0 1 4 11.5 8.5 8.5 0 0 1 7.1 4.9 8.38 8.38 0 0 1 12.5 3a8.5 8.5 0 0 1 8.5 8.5Z"/>
                </svg>
                Start chat
              </button>
            </div>
          </div>
        </div>
      </aside>
    </div>

    <!-- Rendered list -->
    <div id="faq-list" class="mt-10 grid grid-cols-1 lg:grid-cols-2 gap-5"></div>
  </div>

  <div class="pointer-events-none absolute inset-0 bg-[radial-gradient(900px_250px_at_10%_0%,rgba(22,163,74,.08),transparent)]"></div>
</section>

<!-- Hidden SEO Helper for Klinflow (On-page text for search visibility) -->
<div class="sr-only" aria-hidden="true">
    <h2>Best Business Software and ERP Solution in Bangladesh – KlinFlow Modules</h2>
    <p>KlinFlow is the **Best ERP Software in Bangladesh**, offering simple, efficient, and affordable **cloud-based ERP solutions** tailored for **small business** and large industries across Dhaka, Chittagong, and all divisions. We specialize in comprehensive **Business Management Software BD** and **Integrated Accounting Software for Bangladesh**.</p>
    <p>Our complete suite of software solutions includes the **best Inventory Management Software**, **HR & Payroll Software**, and **CRM software** designed for the local market:</p>
    <ul>
        <li><a href="/pos">Retail POS Software Bangladesh</a> – The ultimate Point of Sale solution for stores, supermarkets, and managing **retail inventory**.</li>
        <li><a href="/hotelflow">Hotel Management System Dhaka</a> – Complete software for resorts, hotels, and guesthouses (HotelFlow), including **guest management** and **booking systems**.</li>
        <li><a href="/medflow">Medical & Hospital Software</a> – Manage clinics, pharmacies, and patient records with MedFlow, including **pharmacy management**.</li>
        <li><a href="/school">School Management Software</a> – Simplify administration, attendance, and fee collection for educational institutions (EdTech software).</li>
        <li><a href="/bhata">Brick Field Management System</a> – Specialized **Bhata ERP** and business application for the Brick Field industry.</li>
        <li><a href="/distribution">Distribution Management System (DMS)</a> – Control inventory, sales, and logistics for distributors and wholesalers (DMS software).</li>
        <li><a href="/pricing">Affordable ERP pricing in BDT</a> – View our monthly subscription plans starting at ৳2000 for **complete ERP system implementation**.</li>
    </ul>
    <p>If you need the **most affordable and best ERP software solution in Bangladesh**, choose KlinFlow. We help businesses manage **inventory**, **sales**, **accounting**, **HR**, and **multi-branch operations** efficiently with our fully **customizable ERP software**.</p>
    <p>KlinFlow provides the best **SME ERP solution in Bangladesh** for all your business management needs.</p>
</div>
  
  <div class="sr-only" aria-hidden="true">
    <h3>Advanced Enterprise ERP Solutions & Digital Transformation Consulting Bangladesh</h3>
    <p>KlinFlow offers next-generation **Tier 1 ERP system implementation** and **digital transformation consultancy** for large enterprises and conglomerates in Bangladesh. We provide **custom cloud ERP software development** and specialize in integrating advanced technologies like **Business Intelligence (BI)** and **Data Analytics** into your core business processes.</p>
    <p>Our expertise includes:</p>
    <ul>
        <li>**Comprehensive ERP Modules:** Advanced **Financial Management System (FMS)**, **Supply Chain Management (SCM) optimization**, **Human Capital Management (HCM) software**, and **Customer Relationship Management (CRM) integration**.</li>
        <li>**IT Modernization:** **Legacy system migration** and **ERP customization services** for complex operational workflows.</li>
        <li>**Scalable Solutions:** **Multi-currency and multi-branch management** for large-scale operations across Dhaka and Chittagong.</li>
        <li>**Industry Focus:** Specialized **manufacturing ERP software**, **Construction Project Management Systems**, and **Retail ERP solutions**.</li>
    </ul>
    <p>Searching for the **best enterprise resource planning software provider** or **top IT consulting firm in Dhaka**? KlinFlow delivers **affordable custom software solutions** with complete **post-implementation support** and **data security** compliance. Request a **free ERP consultation** today for your business process re-engineering needs.</p>
    <p>We are the leading provider of **scalable ERP solutions for the Bangladeshi market**.</p>
</div>

<!-- ===== CHANGELOG ===== -->
<section id="changelog" class="max-w-7xl mx-auto px-6 py-14 reveal">
  <div class="flex items-end justify-between gap-4 mb-6">
    <h2 class="text-3xl font-extrabold text-gray-900">Changelog</h2>
    <a href="/changelog" class="hidden sm:inline-flex items-center gap-2 text-green-700 font-semibold">
      View all
      <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="#16a34a" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="m9 5 7 7-7 7"/></svg>
    </a>
  </div>

  <ol id="changelog-list" class="relative border-l border-dashed border-green-300 pl-6 space-y-6">
    <!-- Fallback static entries; replaced by JSON if available -->
    <li>
      <span class="absolute -left-[9px] top-1.5 w-4 h-4 rounded-full bg-green-600"></span>
      <div class="flex items-center gap-2 text-sm"><span class="font-semibold">v1.7.0</span> <span class="text-gray-500">— Oct 2025</span></div>
      <p class="text-sm text-gray-700">New Ticket page, improved public header, and POS barcode stability fixes.</p>
    </li>
    <li>
      <span class="absolute -left-[9px] top-1.5 w-4 h-4 rounded-full bg-green-600"></span>
      <div class="flex items-center gap-2 text-sm"><span class="font-semibold">v1.6.2</span> <span class="text-gray-500">— Sep 2025</span></div>
      <p class="text-sm text-gray-700">HotelFlow housekeeping board and DMS retention tweaks.</p>
    </li>
    <li>
      <span class="absolute -left-[9px] top-1.5 w-4 h-4 rounded-full bg-green-600"></span>
      <div class="flex items-center gap-2 text-sm"><span class="font-semibold">v1.6.0</span> <span class="text-gray-500">— Aug 2025</span></div>
      <p class="text-sm text-gray-700">EN/BN language toggle across public pages.</p>
    </li>
  </ol>

  <div class="mt-6 sm:hidden">
    <a href="/changelog" class="inline-flex items-center gap-2 text-green-700 font-semibold">
      View all
      <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="#16a34a" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="m9 5 7 7-7 7"/></svg>
    </a>
  </div>
</section>

<!-- ===== HELP (Docs preview) ===== -->
<section id="help" class="py-14 bg-white border-y border-gray-100 reveal">
  <div class="max-w-7xl mx-auto px-6">
    <div class="flex items-end justify-between gap-4 mb-6">
      <h2 class="text-3xl font-extrabold text-gray-900">Help Center</h2>
      <a href="/help" class="hidden sm:inline-flex items-center gap-2 text-green-700 font-semibold">
        Browse all articles
        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="#16a34a" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="m9 5 7 7-7 7"/></svg>
      </a>
    </div>

    <!-- Search (non-functional placeholder for now) -->
    <div class="mb-6">
      <label for="help-search" class="sr-only">Search help</label>
      <div class="relative">
        <input id="help-search" type="search" placeholder="Search help articles…"
               class="w-full rounded-2xl border border-gray-200 px-4 py-3 pr-10 soft-shadow">
        <i class="fa-solid fa-magnifying-glass absolute right-3 top-1/2 -translate-y-1/2 text-gray-400"></i>
      </div>
    </div>

    <div class="grid md:grid-cols-3 gap-5">
      <article class="bg-white rounded-2xl soft-shadow p-5">
        <h3 class="font-semibold mb-2">Getting Started</h3>
        <ul class="text-sm text-gray-700 space-y-1">
          <li><a href="/help" class="hover:text-green-700">Create your first organization</a></li>
          <li><a href="/help" class="hover:text-green-700">Invite users & set roles</a></li>
          <li><a href="/help" class="hover:text-green-700">Connect receipt printers</a></li>
        </ul>
      </article>
      <article class="bg-white rounded-2xl soft-shadow p-5">
        <h3 class="font-semibold mb-2">Billing</h3>
        <ul class="text-sm text-gray-700 space-y-1">
          <li><a href="/help" class="hover:text-green-700">Plans & discounts</a></li>
          <li><a href="/help" class="hover:text-green-700">Invoices & VAT</a></li>
          <li><a href="/help" class="hover:text-green-700">Cancel or switch modules</a></li>
        </ul>
      </article>
      <article class="bg-white rounded-2xl soft-shadow p-5">
        <h3 class="font-semibold mb-2">Troubleshooting</h3>
        <ul class="text-sm text-gray-700 space-y-1">
          <li><a href="/help" class="hover:text-green-700">POS scanner not reading</a></li>
          <li><a href="/help" class="hover:text-green-700">HotelFlow room sync</a></li>
          <li><a href="/help" class="hover:text-green-700">Data import tips</a></li>
        </ul>
      </article>
    </div>

    <div class="mt-6 sm:hidden">
      <a href="/help" class="inline-flex items-center gap-2 text-green-700 font-semibold">
        Browse all articles
        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="#16a34a" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="m9 5 7 7-7 7"/></svg>
      </a>
    </div>
  </div>
</section>

<!-- ===== DEMO CTA ===== -->
<section id="demo" class="max-w-7xl mx-auto px-6 py-14 reveal">
  <div class="bg-gradient-to-br from-green-600 to-emerald-600 text-white rounded-3xl p-8 sm:p-12 soft-shadow grid lg:grid-cols-3 gap-8 items-center">
    
    <!-- Left: Text + Buttons -->
    <div class="lg:col-span-2">
      <h2 class="text-3xl sm:text-4xl font-extrabold">Try KlinFlow before you decide</h2>
      <p class="mt-2 opacity-90">Launch a sandbox tenant (auto-resets daily) or watch a quick walkthrough video.</p>

      <div class="mt-5 flex flex-wrap gap-3">
        <a href="#"
           class="inline-flex items-center gap-2 bg-white text-green-700 font-semibold px-5 py-3 rounded-xl hover:bg-green-50 transition-basic">
          <!-- rocket svg -->
          <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-5 h-5">
            <path stroke-linecap="round" stroke-linejoin="round" d="M5 19l2-2m0 0l2-2m-2 2v-3.5a5.5 5.5 0 015.5-5.5H18l-4 4h2a2 2 0 012 2v2.5a5.5 5.5 0 01-5.5 5.5H9l-4 4v-3.5a5.5 5.5 0 015.5-5.5H10l-3 3z" />
          </svg>
          <span>Launch demo tenant</span>
        </a>

        <a href="youtube.com/klinflow"
           class="inline-flex items-center gap-2 bg-green-700/30 text-white font-semibold px-5 py-3 rounded-xl hover:bg-green-700/40 transition-basic">
          <!-- play svg -->
          <svg xmlns="http://www.w3.org/2000/svg" fill="currentColor" viewBox="0 0 24 24" class="w-5 h-5">
            <path d="M5 3v18l15-9L5 3z" />
          </svg>
          <span>Watch video</span>
        </a>
      </div>

      <p class="text-xs mt-3 opacity-90">
        Note: Demo resets every 24 hours. Don’t store real customer data.
      </p>
    </div>

    <!-- Right: Inline SVG Illustration -->
    <div class="flex justify-center">
      <svg viewBox="0 0 320 200" class="w-full max-w-sm h-auto rounded-2xl shadow-2xl ring-1 ring-white/20" xmlns="http://www.w3.org/2000/svg">
        <defs>
          <linearGradient id="grad" x1="0" y1="0" x2="0" y2="1">
            <stop offset="0%" stop-color="rgba(255,255,255,0.2)" />
            <stop offset="100%" stop-color="rgba(255,255,255,0.05)" />
          </linearGradient>
        </defs>
        <rect width="320" height="200" rx="16" fill="url(#grad)" />
        <rect x="20" y="20" width="280" height="20" rx="5" fill="rgba(255,255,255,0.15)" />
        <circle cx="35" cy="30" r="3" fill="#fff" opacity="0.7"/>
        <circle cx="45" cy="30" r="3" fill="#fff" opacity="0.5"/>
        <circle cx="55" cy="30" r="3" fill="#fff" opacity="0.3"/>

        <rect x="20" y="60" width="120" height="100" rx="8" fill="rgba(255,255,255,0.15)" />
        <rect x="160" y="60" width="140" height="40" rx="8" fill="rgba(255,255,255,0.1)" />
        <rect x="160" y="110" width="140" height="50" rx="8" fill="rgba(255,255,255,0.08)" />

        <text x="30" y="90" fill="#fff" font-size="12" font-family="sans-serif" opacity="0.9">Dashboard</text>
        <text x="170" y="85" fill="#fff" font-size="12" font-family="sans-serif" opacity="0.9">Recent Activity</text>
        <text x="170" y="135" fill="#fff" font-size="12" font-family="sans-serif" opacity="0.8">Analytics</text>
      </svg>
    </div>
  </div>
</section>
  
  
  
 
  
 
  
  
  <!-- ===== FEATURES ===== -->
<section id="features" class="max-w-7xl mx-auto px-6 pb-16 reveal">
  <div class="flex items-end justify-between gap-4 mb-6">
    <h2 class="text-3xl font-extrabold text-gray-900">All your modules — one platform</h2>
    <a href="/features" class="hidden sm:inline-flex items-center gap-2 text-green-700 font-semibold">
      Learn more
      <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="#16a34a" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="m9 5 7 7-7 7"/></svg>
    </a>
  </div>

  <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-5">
    <!-- 6 compact, modern cards -->
    <article class="bg-white rounded-2xl soft-shadow p-5 hover:-translate-y-0.5 transition-basic">
      <div class="flex items-center gap-3">
        <div class="icon-bubble bg-green-100"><svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="#15803d" stroke-width="2"><rect x="3" y="3" width="18" height="6"/><path d="M4 9v9a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9"/></svg></div>
        <h3 class="font-semibold">Retail POS</h3>
      </div>
      <p class="text-sm text-gray-600 mt-2">Fast billing, barcode, stock & VAT reports.</p>
    </article>

    <article class="bg-white rounded-2xl soft-shadow p-5 hover:-translate-y-0.5 transition-basic">
      <div class="flex items-center gap-3">
        <div class="icon-bubble bg-sky-100"><i class="fa-solid fa-hotel text-sky-700"></i></div>
        <h3 class="font-semibold">HotelFlow</h3>
      </div>
      <p class="text-sm text-gray-600 mt-2">Reservations, front desk, housekeeping & F&amp;B.</p>
    </article>

    <article class="bg-white rounded-2xl soft-shadow p-5 hover:-translate-y-0.5 transition-basic">
      <div class="flex items-center gap-3">
        <div class="icon-bubble bg-amber-100"><i class="fa-solid fa-industry text-amber-700"></i></div>
        <h3 class="font-semibold">Bhata</h3>
      </div>
      <p class="text-sm text-gray-600 mt-2">Kiln loads, dispatch, party ledger & dues.</p>
    </article>

    <article class="bg-white rounded-2xl soft-shadow p-5 hover:-translate-y-0.5 transition-basic">
      <div class="flex items-center gap-3">
        <div class="icon-bubble bg-purple-100"><i class="fa-solid fa-graduation-cap text-purple-700"></i></div>
        <h3 class="font-semibold">School</h3>
      </div>
      <p class="text-sm text-gray-600 mt-2">Admissions, fees, attendance & results.</p>
    </article>

    <article class="bg-white rounded-2xl soft-shadow p-5 hover:-translate-y-0.5 transition-basic">
      <div class="flex items-center gap-3">
        <div class="icon-bubble bg-rose-100"><i class="fa-solid fa-briefcase-medical text-rose-700"></i></div>
        <h3 class="font-semibold">MedFlow</h3>
      </div>
      <p class="text-sm text-gray-600 mt-2">Pharmacy, clinic billing & stock.</p>
    </article>

    <article class="bg-white rounded-2xl soft-shadow p-5 hover:-translate-y-0.5 transition-basic">
      <div class="flex items-center gap-3">
        <div class="icon-bubble bg-indigo-100"><i class="fa-solid fa-diagram-project text-indigo-700"></i></div>
        <h3 class="font-semibold">DMS</h3>
      </div>
      <p class="text-sm text-gray-600 mt-2">Documents, approvals, retention & search.</p>
    </article>
  </div>

  <div class="mt-6 sm:hidden">
    <a href="#" class="inline-flex items-center gap-2 text-green-700 font-semibold">
      Learn more
      <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="#16a34a" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="m9 5 7 7-7 7"/></svg>
    </a>
  </div>
</section>
  

  <!-- FOOTER (Light brand style) -->
<footer class="relative mt-16 text-gray-900">
  <!-- soft wave divider (optional) -->
  <svg class="absolute -top-6 left-0 w-full h-6 text-green-100" viewBox="0 0 1440 48" fill="currentColor" aria-hidden="true">
    <path d="M0,16 C120,32 240,32 360,24 C480,16 600,0 720,0 C840,0 960,16 1080,24 C1200,32 1320,32 1440,16 L1440,48 L0,48 Z"></path>
  </svg>

  <!-- background -->
  <div class="bg-gradient-to-b from-green-50 via-green-50 to-white">
    <div class="max-w-7xl mx-auto px-6 py-12 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-8">

      <!-- Brand / tagline + social -->
      <section class="space-y-4">
        <div class="flex items-center gap-3">
          <img src="/assets/brand/logo.png" alt="KlinFlow" class="h-10 w-auto">
          <span class="sr-only">KlinFlow</span>
        </div>
        <p class="text-sm text-gray-700 leading-relaxed">
          Your trusted multi-tenant ERP for Bangladesh — simple, secure, and scalable across Retail POS, HotelFlow, Bhata, School, MedFlow, and Distribution.
        </p>
        <div class="flex items-center gap-3 pt-1">
          <a href="https://facebook.com/klinflow" aria-label="Facebook"
             class="grid h-9 w-9 place-items-center rounded-full bg-white border border-green-200 text-green-700 hover:bg-green-600 hover:text-white transition">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" class="h-4 w-4" fill="currentColor"><path d="M22 12a10 10 0 1 0-11.5 9.9v-7H8v-3h2.5V9.5A3.5 3.5 0 0 1 14 6h2v3h-1.6c-1 0-1.4.5-1.4 1.3V12H16l-.5 3h-2.5v7A10 10 0 0 0 22 12z"/></svg>
          </a>
          <a href="https://linkedin.com/company/klinflow" aria-label="LinkedIn"
             class="grid h-9 w-9 place-items-center rounded-full bg-white border border-green-200 text-green-700 hover:bg-green-600 hover:text-white transition">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" class="h-4 w-4" fill="currentColor"><path d="M4.98 3.5C4.98 5 3.93 6 2.5 6S0 5 0 3.5 1.05 1 2.5 1s2.48 1 2.48 2.5zM.5 8h4v16h-4V8zm7 0h3.9v2.2h.1c.5-.9 1.7-2.3 3.9-2.3 4.2 0 5 2.8 5 6.5V24h-4v-8c0-1.9 0-4.3-2.6-4.3s-3 2-3 4.1V24h-4V8z"/></svg>
          </a>
          <a href="/contact" aria-label="Email"
             class="grid h-9 w-9 place-items-center rounded-full bg-white border border-green-200 text-green-700 hover:bg-green-600 hover:text-white transition">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="currentColor"><path d="M20 4H4a2 2 0 0 0-2 2v.4l10 6.3l10-6.3V6a2 2 0 0 0-2-2zm0 4.9l-8 5.1l-8-5.1V18a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8.9z"/></svg>
          </a>
          <a href="tel:+8801712378526" aria-label="Phone"
             class="grid h-9 w-9 place-items-center rounded-full bg-white border border-green-200 text-green-700 hover:bg-green-600 hover:text-white transition">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="currentColor"><path d="M6.6 10.8a15.8 15.8 0 0 0 6.6 6.6l2.2-2.2a1.5 1.5 0 0 1 1.5-.37c1.62.54 3.38.83 5.1.83a1.5 1.5 0 0 1 1.5 1.5V21a1.5 1.5 0 0 1-1.5 1.5C10.86 22.5 1.5 13.14 1.5 1.5A1.5 1.5 0 0 1 3 0h2.9A1.5 1.5 0 0 1 7.4 1.5c0 1.72.29 3.48.83 5.1a1.5 1.5 0 0 1-.37 1.5L6.6 10.8z"/></svg>
          </a>
        </div>
      </section>

      <!-- Bangladesh — Head Office -->
      <section>
        <h3 class="text-lg font-semibold flex items-center gap-2">
          <span class="text-2xl">🇧🇩</span> Bangladesh — Head Office
        </h3>
        <ul class="mt-3 space-y-2 text-sm text-gray-700">
          <li>House 20, Road 17, Nikunja-2, Khilkhet, Dhaka-1229</li>
          <li class="flex items-center gap-2">
            <svg class="h-4 w-4 text-green-700" viewBox="0 0 24 24" fill="currentColor"><path d="M6.6 10.8a15.8 15.8 0 0 0 6.6 6.6l2.2-2.2a1.5 1.5 0 0 1 1.5-.37c1.62.54 3.38.83 5.1.83a1.5 1.5 0 0 1 1.5 1.5V21a1.5 1.5 0 0 1-1.5 1.5C10.86 22.5 1.5 13.14 1.5 1.5A1.5 1.5 0 0 1 3 0h2.9A1.5 1.5 0 0 1 7.4 1.5c0 1.72.29 3.48.83 5.1a1.5 1.5 0 0 1-.37 1.5L6.6 10.8z"/></svg>
            <a href="tel:+8801712378526" class="hover:text-green-700">+880 1712 378526</a>
          </li>
          <li class="flex items-center gap-2">
            <svg class="h-4 w-4 text-green-700" viewBox="0 0 24 24" fill="currentColor"><path d="M20 4H4a2 2 0 0 0-2 2v.4l10 6.3l10-6.3V6a2 2 0 0 0-2-2zm0 4.9l-8 5.1l-8-5.1V18a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8.9z"/></svg>
            <a href="mailto:support@klinflow.com" class="hover:text-green-700">support@klinflow.com</a>
          </li>
        </ul>
      </section>

      <!-- Rangpur Office -->
      <section>
        <h3 class="text-lg font-semibold flex items-center gap-2">
          <span class="text-2xl">🇧🇩</span> Rangpur Office
        </h3>
        <ul class="mt-3 space-y-2 text-sm text-gray-700">
          <li>House 41/1, Dhap PTI, Kotkipara, Rangpur</li>
          <li class="flex items-center gap-2">
            <svg class="h-4 w-4 text-green-700" viewBox="0 0 24 24" fill="currentColor"><path d="M6.6 10.8a15.8 15.8 0 0 0 6.6 6.6l2.2-2.2a1.5 1.5 0 0 1 1.5-.37c1.62.54 3.38.83 5.1.83a1.5 1.5 0 0 1 1.5 1.5V21a1.5 1.5 0 0 1-1.5 1.5C10.86 22.5 1.5 13.14 1.5 1.5A1.5 1.5 0 0 1 3 0h2.9A1.5 1.5 0 0 1 7.4 1.5c0 1.72.29 3.48.83 5.1a1.5 1.5 0 0 1-.37 1.5L6.6 10.8z"/></svg>
            <a href="tel:+8801712378526" class="hover:text-green-700">+880 1712 378526</a>
          </li>
        </ul>
      </section>

      <!-- Working hours -->
      <section>
        <h3 class="text-lg font-semibold flex items-center gap-2">
          <svg class="h-5 w-5 text-green-700" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2a10 10 0 1 0 .001 20.001A10 10 0 0 0 12 2zm1 11h5v-2h-4V7h-2v6z"/></svg>
          Working Hours
        </h3>
        <div class="mt-3 rounded-2xl border border-green-200 bg-white/80 backdrop-blur px-4 py-4 shadow-sm">
          <div class="text-sm text-gray-800">
            <div class="font-semibold">Sunday – Thursday</div>
            <div class="text-green-700 font-bold">9:00 AM – 6:00 PM</div>
          </div>
          <div class="mt-3 text-sm text-gray-600">
            <span class="font-medium">Friday – Saturday:</span> Closed
          </div>
        </div>
      </section>
    </div>

    <!-- bottom bar -->
    <div class="border-t border-green-100">
      <div class="max-w-7xl mx-auto px-6 py-4 flex flex-col sm:flex-row items-center justify-between text-xs text-gray-600">
        <div class="mb-2 sm:mb-0">
          © <script>document.write(new Date().getFullYear())</script> KlinFlow — Designed by
          <strong>DEPENDCORE</strong>
        </div>
        <nav class="flex items-center gap-4">
          <a href="/about"   class="hover:text-green-700">About Us</a>
          <a href="/pricing" class="hover:text-green-700">Pricing</a>
          <a href="/ticket"  class="hover:text-green-700">Support</a>
          <a href="/privacy" class="hover:text-green-700">Privacy</a>
          <a href="/terms"   class="hover:text-green-700">Terms</a>
          <a href="/contact" class="hover:text-green-700">Request Quotation</a>
        </nav>
      </div>
    </div>
  </div>
</footer>

  <!-- TOAST -->
  <div id="toast" role="alert"></div>

 <!-- Inline FAQ data (fallback if /assets/faq.json is absent) -->
<script id="faq-seed" type="application/json">
[
  { "q": "What is KlinFlow?", "tags": "intro modules platform", "a": "KlinFlow is a secure multi-tenant platform with modules for Retail POS, HotelFlow, Bhata, School, MedFlow, and Distribution." },
  { "q": "Which languages are supported?", "tags": "language english bangla", "a": "English and Bangla UI are supported across public pages and the app." },
  { "q": "How does pricing work?", "tags": "pricing cost monthly annual discount", "a": "Each module is a flat monthly fee in BDT. Annual plans offer savings. See the <a class='text-green-700 underline' href='/pricing'>Pricing</a> page." },
  { "q": "Is there a free trial or demo?", "tags": "trial demo sandbox", "a": "Yes. We can enable a sandbox tenant or share a short video walkthrough—just contact us." },
  { "q": "Can I cancel anytime?", "tags": "billing cancel switch", "a": "Yes. You can cancel future renewals anytime from billing; access remains until the current period ends." },
  { "q": "Do you issue VAT invoices?", "tags": "billing vat invoice", "a": "Yes, VAT-ready invoices and exportable reports (CSV/PDF) are supported for Bangladesh." },
  { "q": "How does KlinFlow keep data secure?", "tags": "security isolation rbac tls encryption", "a": "Per-organization data isolation, TLS everywhere, hashed credentials, role-based access with audit, and daily offsite backups." },
  { "q": "Do you do backups? What’s retention?", "tags": "backup retention disaster recovery", "a": "Daily offsite backups with rolling retention; point-in-time restore options are available for critical incidents." },
  { "q": "Can I export my data?", "tags": "export csv pdf ownership", "a": "Yes—export lists and reports (CSV/PDF) anytime. Need a full export? Contact <a class='text-green-700 underline' href='mailto:support@klinflow.com'>support@klinflow.com</a>." },
  { "q": "Can I manage multiple organizations or branches?", "tags": "multi tenant organizations branches", "a": "Yes. KlinFlow is multi-tenant; create orgs/branches and assign roles per module." },
  { "q": "Do you have user permissions and audit?", "tags": "rbac roles audit logs", "a": "Granular role-based permissions with audit trail for sensitive actions." },
  { "q": "Which hardware works with POS?", "tags": "pos hardware barcode scanner receipt printer", "a": "Most keyboard-mode (HID) barcode scanners work. Receipt printers that emulate standard drivers are supported; contact us for a list." },
  { "q": "POS scanner isn’t reading—what can I try?", "tags": "troubleshoot scanner", "a": "<ul class='list-disc pl-5'><li>Ensure HID (keyboard) mode.</li><li>Focus the barcode field and try a known EAN/UPC.</li><li>Check code symbologies in settings.</li></ul>" },
  { "q": "Does it work on mobile/tablet?", "tags": "mobile responsive tablet", "a": "Yes—KlinFlow is responsive and works on modern mobile and tablet browsers." },
  { "q": "Which browsers are supported?", "tags": "browser support chrome edge firefox safari", "a": "Latest Chrome, Edge, Firefox, and Safari are supported. Keep auto-update on for best results." },
  { "q": "Do you integrate with payment gateways or SMS?", "tags": "integration payment sms email", "a": "Local payment and SMS/email gateways can be integrated on request; contact us for current options." },
  { "q": "How fast is support?", "tags": "support sla response", "a": "Tickets typically receive a response within one business day (Bangladesh time). Urgent incidents are prioritized." },
  { "q": "Do you help with onboarding or migration?", "tags": "onboarding migration import", "a": "Yes—starter templates and guided import tools are available; we can assist with structured migrations." }
]
</script>
  
  
  

<script>
/* =========================================================
 * KlinFlow — Site Scripts (index/help/changelog compatible)
 * ---------------------------------------------------------
 * Organized by sections so any developer can scan & modify.
 * =======================================================*/
(function () {
  /* ================================
   * 0) Config & tiny helpers
   * ============================== */
  const FAQ_MODE = 'accordion';           // 'accordion' | 'multi'
  const KEY_TARGETS = { c: '/cp/login', t: '/tenant/login' }; // keyboard fallbacks
  const $  = (s, r = document) => r.querySelector(s);
  const $$ = (s, r = document) => Array.from(r.querySelectorAll(s));
  const safeFocus = (el) => { try { el && el.focus({ preventScroll: true }); } catch (_) {} };
  const lockScroll = (on) => {
    document.documentElement.style.overflow = on ? 'hidden' : '';
    document.body.style.overflow = on ? 'hidden' : '';
  };

  /* =========================================
   * 1) Drawer (mobile menu) — guarded wiring
   * ======================================= */
  (function drawer() {
    const toggleBtn = $('#nav-toggle');
    const closeBtn  = $('#nav-close');
    const drawer    = $('#mobile-drawer');
    const overlay   = $('#overlay');
    if (!drawer || !overlay) return;

    let lastFocus;
    function openDrawer() {
      lastFocus = document.activeElement;
      drawer.classList.remove('drawer-enter'); drawer.classList.add('drawer-open');
      overlay.classList.remove('overlay-hidden'); overlay.classList.add('overlay-open');
      drawer.setAttribute('aria-hidden', 'false');
      toggleBtn?.setAttribute('aria-expanded', 'true');
      lockScroll(true);
      safeFocus(drawer.querySelector('a,button,[tabindex="0"]') || drawer);
    }
    function closeDrawer() {
      drawer.classList.add('drawer-enter'); drawer.classList.remove('drawer-open');
      overlay.classList.add('overlay-hidden'); overlay.classList.remove('overlay-open');
      drawer.setAttribute('aria-hidden', 'true');
      toggleBtn?.setAttribute('aria-expanded', 'false');
      lockScroll(false);
      safeFocus(lastFocus);
    }
    toggleBtn?.addEventListener('click', openDrawer, { passive: true });
    closeBtn ?.addEventListener('click', closeDrawer, { passive: true });
    overlay  ?.addEventListener('click', closeDrawer, { passive: true });
    document.addEventListener('keydown', (e) => { if (e.key === 'Escape') closeDrawer(); }, { passive: true });
  })();

  /* ===========================================
   * 2) Reveal-on-scroll (adds .in once visible)
   * ========================================= */
  (function revealOnScroll() {
    const els = $$('.reveal');
    if (!els.length) return;
    if ('IntersectionObserver' in window) {
      const io = new IntersectionObserver((entries) => {
        entries.forEach((e) => { if (e.isIntersecting) e.target.classList.add('in'); });
      }, { threshold: 0.12 });
      els.forEach((el) => io.observe(el));
    } else {
      els.forEach((el) => el.classList.add('in'));
    }
  })();

  /* =================================
   * 3) Changelog: optional hydration
   * =============================== */
  (async function hydrateChangelog() {
    const box = $('#changelog-list');
    if (!box) return;
    try {
      const res = await fetch('/assets/changelog.json', { cache: 'no-store' });
      if (!res.ok) return;
      const items = await res.json();
      if (!Array.isArray(items)) return;
      box.innerHTML = items.slice(0, 3).map((it) => `
        <li>
          <span class="absolute -left-[9px] top-1.5 w-4 h-4 rounded-full bg-green-600"></span>
          <div class="flex items-center gap-2 text-sm">
            <span class="font-semibold">${(it.version || 'vNext')}</span>
            <span class="text-gray-500">— ${it.date || ''}</span>
          </div>
          <p class="text-sm text-gray-700">${(it.note || '')}</p>
        </li>
      `).join('');
    } catch (_) { /* silent fallback */ }
  })();

  /* ===========================================
   * 4) FAQ: render + behavior + search (guarded)
   * ========================================= */
  (async function faq() {
    const listEl   = $('#faq-list');
    const searchEl = $('#faq-search');
    const countEl  = $('#faq-count');
    if (!listEl) return; // page has no FAQ

    async function loadFaqData() {
      // Prefer external JSON; if missing, use inline seed
      try {
        const res = await fetch('/assets/faq.json', { cache: 'no-store' });
        if (res.ok) {
          const data = await res.json();
          if (Array.isArray(data) && data.length) return data;
        }
      } catch (_) {}
      try {
        return JSON.parse($('#faq-seed')?.textContent || '[]');
      } catch (_) { return []; }
    }

    function renderFaq(items) {
      listEl.innerHTML = items.map((item) => `
        <article class="faq-item panel soft-shadow rounded-2xl overflow-hidden bg-white"
                 data-faq="${(item.tags || '').replace(/"/g, '%22')}">
          <button class="faq-q w-full text-left px-6 py-5 flex items-center justify-between font-semibold hover:bg-gray-50 transition">
            <span class="pr-4">${(item.q || '').replace(/</g, '&lt;')}</span>
            <svg class="chev w-5 h-5 text-gray-400 transition-transform" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" d="M6 9l6 6 6-6"/>
            </svg>
          </button>
          <div class="faq-a px-6 pb-5 hidden text-gray-700">${item.a || ''}</div>
        </article>
      `).join('');
    }

    function wireFaqBehavior() {
      const cards = $$('.faq-item', listEl);

      // Toggle open/close
      listEl.addEventListener('click', (e) => {
        const btn = e.target.closest('.faq-q');
        if (!btn) return;
        const card = btn.closest('.faq-item');
        const ans  = $('.faq-a', card);
        const icon = $('.chev', card);
        const willOpen = ans.classList.contains('hidden');

        if (FAQ_MODE === 'accordion') {
          cards.forEach((it) => {
            $('.faq-a', it)?.classList.add('hidden');
            $('.chev', it)?.classList.remove('rotate-180');
          });
        }
        ans.classList.toggle('hidden', !willOpen ? true : false);
        icon?.classList.toggle('rotate-180', willOpen);
      });

      // Filter by text/tags
      function filter() {
        const q = (searchEl?.value || '').toLowerCase().trim();
        let shown = 0, firstShown = null;
        cards.forEach((it) => {
          const head = $('.faq-q span', it)?.textContent.toLowerCase() || '';
          const hay  = (it.dataset.faq || '') + ' ' + head;
          const match = q === '' || hay.includes(q);
          it.classList.toggle('hidden', !match);
          $('.faq-a', it)?.classList.add('hidden');
          $('.chev', it)?.classList.remove('rotate-180');
          if (match) { shown++; if (!firstShown) firstShown = it; }
        });
        if (countEl) countEl.textContent = q ? `${shown} result${shown === 1 ? '' : 's'} for “${searchEl.value}”` : '';
        if (q && firstShown) {
          $('.faq-a', firstShown)?.classList.remove('hidden');
          $('.chev', firstShown)?.classList.add('rotate-180');
        }
      }
      searchEl?.addEventListener('input', filter);
    }

    const data = await loadFaqData();
    renderFaq(data);
    wireFaqBehavior();
  })();

  /* ==========================================
   * 5) Footer year (safe if element absent)
   * ======================================== */
  (function footerYear() {
    const el = $('#copyright');
    if (el) el.textContent = `© ${new Date().getFullYear()} KlinFlow`;
  })();

  /* ==================================================
   * 6) Keyboard shortcuts (C/T + data-kf-shortcut)
   *    - Works even when FAQ or other sections absent
   * ================================================= */
  (function shortcuts() {
    // Click any anchor/button with data-kf-shortcut="c" / "t"
    const shortcutMap = new Map();
    $$('[data-kf-shortcut]').forEach((el) => {
      const key = String(el.dataset.kfShortcut || '').toLowerCase();
      if (key) shortcutMap.set(key, el);
    });

    document.addEventListener('keydown', (e) => {
      if (e.altKey || e.ctrlKey || e.metaKey) return;

      const k = String(e.key || '').toLowerCase();
      // 1) custom element with data-kf-shortcut
      const el = shortcutMap.get(k);
      if (el) { el.click(); return; }

      // 2) fallback route map (C/T)
      if (KEY_TARGETS[k]) {
        location.href = KEY_TARGETS[k];
      }
    }, { passive: true });
  })();

  /* =====================================
   * 7) Chat trigger (placeholder hook)
   * =================================== */
  (function chatHook() {
    $('[data-open-chat]')?.addEventListener('click', () => {
      // Replace with your widget launcher, e.g.:
      // window.Intercom?.('show'); window.$crisp?.push(['do', 'chat:open']);
      alert('Chat launcher placeholder — integrate your widget.');
    }, { passive: true });
  })();
})();
</script>
  
  
<script> window.chtlConfig = { chatbotId: "8748546719" } </script>
<script async data-id="8748546719" id="chtl-script" type="text/javascript" src="https://chatling.ai/js/embed.js"></script>
                    

</body>
</html>