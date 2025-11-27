<?php
/** apps/Public/Views/ticket.php
 *  KlinFlow — Support Tickets (Public)
 *  Keeps shared header style, mobile off-canvas, and adds guidelines.
 */

/* Path normalizer (web-root absolute) */
$normalize = static function (?string $p): string {
    $p = (string)($p ?? '');
    if ($p === '') return '/assets/brand/logo.png';
    return preg_replace('#^/public/#', '/', $p);
};

$pageTitle   = $pageTitle   ?? 'KlinFlow — Support Tickets';
$pageDesc    = $pageDesc    ?? 'Open a support ticket or check your ticket status with KlinFlow. Bangladesh-ready support with quick responses.';
$brandLogo   = $normalize($brandLogo   ?? '/assets/brand/logo.png');
$siteFavicon = $normalize($siteFavicon ?? '/assets/brand/logo.png');

$apiCreate   = $apiCreate   ?? '/api/tickets';        // POST JSON
$apiShowTpl  = $apiShowTpl  ?? '/api/tickets/__ID__'; // GET JSON; __ID__ replaced in JS

$h = static fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
?>
<!doctype html>
<html lang="en" class="h-full scroll-smooth">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />

  <title><?= $h($pageTitle) ?></title>
  <meta name="description" content="<?= $h($pageDesc) ?>" />
  <meta name="theme-color" content="#228B22" />

  <link rel="preconnect" href="https://cdnjs.cloudflare.com" crossorigin>
  <link rel="icon" type="image/png" href="<?= $h($siteFavicon) ?>" />
  <link rel="preload" href="<?= $h($brandLogo) ?>" as="image" />

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
  <link rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css"
        crossorigin="anonymous" />

  <style>
    :root { --brand:#228B22; }
    .panel{background:#fff;border:1px solid rgba(0,0,0,.06);}
    .soft-shadow{box-shadow:0 10px 25px rgba(0,0,0,.07), 0 2px 8px rgba(0,0,0,.04);}
    .focus-ring:focus-visible{outline:2px solid var(--brand);outline-offset:3px;}
    .brand-logo{max-height:56px;width:auto;}
    @media (max-width:639.98px){ .brand-logo{max-height:44px;} }

    /* Background */
    body{
      background:
        radial-gradient(1200px 600px at 10% 10%, rgba(34,139,34,.08), transparent),
        linear-gradient(180deg, #eefbf1 0%, #f5fdf7 50%, #ffffff 100%);
    }

    /* Off-canvas drawer */
    .drawer-enter{transform:translateX(100%);opacity:0;}
    .drawer-open{transform:translateX(0);opacity:1;}
    .overlay-hidden{opacity:0;pointer-events:none;}
    .overlay-open{opacity:1;pointer-events:auto;}
    .transition-basic{transition:transform .25s ease,opacity .25s ease;}

    /* Reveal (fade+rise) */
    .reveal{opacity:0;transform:translateY(16px);transition:opacity .6s ease, transform .6s ease;}
    .reveal.in{opacity:1;transform:translateY(0);}
    @media (prefers-reduced-motion: reduce){
      .transition-basic,.reveal,.reveal.in{transition:none!important;opacity:1!important;transform:none!important;}
    }

    /* Toast */
    .toast{position:fixed;right:1rem;bottom:1rem;z-index:60}
    .mono{font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", monospace;}
  </style>

  <!-- Schema: SupportPage -->
  <script type="application/ld+json">
  {
    "@context": "https://schema.org",
    "@type": "SupportPage",
    "name": "KlinFlow Support Tickets",
    "url": "https://www.klinflow.com/ticket",
    "about": { "@type": "Organization", "name": "KlinFlow" },
    "areaServed": { "@type": "Country", "name": "Bangladesh" }
  }
  </script>
</head>
<body class="text-gray-900 overflow-x-hidden">
  <a href="#main" class="sr-only focus:not-sr-only focus-ring fixed left-4 top-4 z-50 bg-white text-gray-900 rounded px-3 py-2 soft-shadow">Skip to main</a>

  <!-- ========== HEADER (glassy, consistent with other public pages) ========== -->
  <header id="site-header" class="fixed top-0 left-0 w-full z-50 backdrop-blur bg-white/80 shadow-sm">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
      <nav class="flex items-center justify-between h-16" aria-label="Primary">
        <!-- Logo -->
        <a href="/" class="flex items-center gap-3">
          <img src="<?= $h($brandLogo) ?>" alt="KlinFlow" class="h-10 w-auto brand-logo" width="160" height="32" loading="eager" decoding="async" fetchpriority="high">
          <span class="sr-only">KlinFlow Home</span>
        </a>

        <!-- Desktop nav -->
        <ul class="hidden sm:flex items-center gap-8 text-[15px] font-medium">
          <li><a href="/"        class="text-slate-700 hover:text-brand transition">Home</a></li>
          <li><a href="/about"   class="text-slate-700 hover:text-brand transition">About</a></li>
          <li><a href="/contact" class="text-slate-700 hover:text-brand transition">Contact</a></li>
          <li><a href="/pricing" class="text-slate-700 hover:text-brand transition">Pricing</a></li>
          <li><a href="/ticket"  class="text-brand font-semibold border-b-2 border-brand pb-1">Tickets</a></li>
        </ul>

        <!-- Mobile trigger -->
        <button id="nav-toggle"
                class="sm:hidden inline-flex items-center gap-2 px-3 py-2 rounded-lg bg-white/70 backdrop-blur shadow focus-ring"
                aria-controls="mobile-drawer" aria-expanded="false" aria-label="Open menu">
          <i class="fa-solid fa-bars text-brand"></i>
        </button>
      </nav>
    </div>
  </header>

  <!-- Drawer/backdrop (kept consistent across pages) -->
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
        <a href="/about"   class="block px-3 py-3 rounded-lg hover:bg-gray-100">About</a>
        <a href="/contact" class="block px-3 py-3 rounded-lg hover:bg-gray-100">Contact</a>
        <a href="/pricing" class="block px-3 py-3 rounded-lg hover:bg-gray-100">Pricing</a>
        <a href="/ticket"  class="block px-3 py-3 rounded-lg hover:bg-gray-100 font-semibold">Tickets</a>
      </nav>
      <div class="mt-auto p-3 text-xs text-gray-500 border-t border-gray-200">
        © <script>document.write(new Date().getFullYear())</script> KlinFlow
      </div>
    </div>
  </aside>

  <!-- MAIN (add top padding for fixed header) -->
  <main id="main" class="relative w-full pt-20">
    <!-- Hero -->
    <section class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 pt-2 pb-6 reveal">
      <p class="inline-flex items-center gap-2 text-xs font-semibold uppercase tracking-wider text-emerald-800 bg-emerald-100 px-3 py-1 rounded-full soft-shadow">
        <i class="fa-solid fa-ticket"></i> Support Tickets
      </p>
      <h1 class="mt-4 text-4xl sm:text-5xl font-extrabold leading-tight">Open a ticket or check status</h1>
      <p class="mt-3 text-slate-700 max-w-2xl">
        We aim to reply within one business day (Bangladesh time). Keep your <span class="font-semibold">Ticket ID</span> to track updates quickly.
      </p>
    </section>

    <section class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 pb-16 grid lg:grid-cols-3 gap-6">
      <!-- Open Ticket -->
      <form id="ticketForm" novalidate class="panel soft-shadow rounded-2xl p-6 lg:col-span-2 reveal">
        <h2 class="text-xl font-bold mb-4">Open a new ticket</h2>
        <div class="grid sm:grid-cols-2 gap-4">
          <div>
            <label for="name" class="block text-sm font-medium text-slate-700">Full name *</label>
            <input id="name" name="name" required class="mt-1 w-full rounded-xl border border-gray-200 px-3 py-2 focus-ring" placeholder="Jamal Uddin">
            <p class="mt-1 hidden text-xs text-red-600" data-err="name"></p>
          </div>
          <div>
            <label for="email" class="block text-sm font-medium text-slate-700">Email *</label>
            <input id="email" name="email" type="email" required class="mt-1 w-full rounded-xl border border-gray-200 px-3 py-2 focus-ring" placeholder="you@company.com">
            <p class="mt-1 hidden text-xs text-red-600" data-err="email"></p>
          </div>
          <div>
            <label for="phone" class="block text-sm font-medium text-slate-700">Phone (optional)</label>
            <input id="phone" name="phone" class="mt-1 w-full rounded-xl border border-gray-200 px-3 py-2 focus-ring" placeholder="+8801XXXXXXXXX">
          </div>
          <div>
            <label for="module" class="block text-sm font-medium text-slate-700">Module</label>
            <select id="module" name="module" class="mt-1 w-full rounded-xl border border-gray-200 px-3 py-2 focus-ring">
              <option value="">Select module</option>
              <option>Retail POS</option>
              <option>HotelFlow</option>
              <option>Bhata (Brick Field)</option>
              <option>School</option>
              <option>MedFlow</option>
              <option>DMS</option>
            </select>
          </div>
          <div class="sm:col-span-2">
            <label for="subject" class="block text-sm font-medium text-slate-700">Subject *</label>
            <input id="subject" name="subject" required class="mt-1 w-full rounded-xl border border-gray-200 px-3 py-2 focus-ring" placeholder="POS barcode scanner issue">
            <p class="mt-1 hidden text-xs text-red-600" data-err="subject"></p>
          </div>
          <div class="sm:col-span-2">
            <label for="body" class="block text-sm font-medium text-slate-700">Describe the problem *</label>
            <textarea id="body" name="body" rows="5" required class="mt-1 w-full rounded-xl border border-gray-200 px-3 py-2 focus-ring" placeholder="What happened? When did it start? Any error message?"></textarea>
            <p class="mt-1 hidden text-xs text-red-600" data-err="body"></p>
          </div>
        </div>

        <div class="mt-5 flex items-center flex-wrap gap-3">
          <button id="openBtn" type="submit" class="inline-flex items-center gap-2 rounded-xl bg-brand text-white px-5 py-3 font-semibold soft-shadow hover:bg-emerald-700 transition focus-ring">
            <i class="fa-solid fa-paper-plane"></i> Submit ticket
          </button>
          <span id="openResult" class="text-sm"></span>
          <button id="copyIdBtn" type="button" class="hidden text-sm text-slate-700 underline underline-offset-4" title="Copy Ticket ID">
            <i class="fa-regular fa-copy"></i> Copy Ticket ID
          </button>
        </div>

        <div id="formAlert" class="mt-4 hidden rounded-xl border px-4 py-3"></div>
        <p class="mt-3 text-xs text-slate-600">
          Tip: Include screenshots and exact error messages in your description. Our team will follow up via email.
        </p>
      </form>

      <!-- Guidelines / Status -->
      <div class="space-y-6">
        <!-- Check Status -->
        <div class="panel soft-shadow rounded-2xl p-6 reveal">
          <h2 class="text-xl font-bold mb-4">Check ticket status</h2>
          <label for="ticketId" class="block text-sm font-medium text-slate-700">Ticket ID</label>
          <input id="ticketId" class="mt-1 w-full rounded-xl border border-gray-200 px-3 py-2 focus-ring mono" placeholder="e.g., KF-20251020-AB12">
          <div class="mt-3 flex gap-2">
            <button id="checkBtn" class="inline-flex items-center gap-2 rounded-xl bg-slate-900 text-white px-4 py-2 font-semibold soft-shadow hover:bg-slate-800 transition focus-ring">
              <i class="fa-solid fa-magnifying-glass"></i> Check status
            </button>
            <button id="pasteIdBtn" class="inline-flex items-center gap-2 rounded-xl bg-white border border-gray-200 px-4 py-2 font-semibold soft-shadow hover:bg-gray-50 transition focus-ring">
              <i class="fa-regular fa-clipboard"></i> Paste last ID
            </button>
          </div>

          <div id="statusCard" class="mt-4 hidden panel rounded-xl p-4">
            <div class="text-sm text-slate-700" id="statusText"></div>
          </div>
        </div>

        <!-- Clear Guidelines -->
        <div class="panel soft-shadow rounded-2xl p-6 reveal">
          <h2 class="text-xl font-bold mb-3">How to open a great ticket</h2>
          <ol class="list-decimal pl-5 text-sm text-slate-700 space-y-2">
            <li>Use your work email so we can verify your organization.</li>
            <li>Pick the correct <strong>Module</strong> (POS, HotelFlow, DMS, etc.).</li>
            <li>In <strong>Subject</strong>, keep it short and specific (e.g., “POS: scanner stops after 3 scans”).</li>
            <li>In <strong>Description</strong>, include steps to reproduce, expected vs. actual result, and any <strong>error messages</strong>.</li>
            <li>Mention <strong>when</strong> it started and whether it’s <em>blocking</em> operations.</li>
            <li>Attach screenshots or copy the error text into the description.</li>
          </ol>

          <h3 class="font-semibold mt-4">Severity & target response</h3>
          <ul class="text-sm text-slate-700 space-y-1 mt-1">
            <li><span class="font-semibold text-red-700">Critical (S1)</span>: System down / sales blocked — we prioritize immediately.</li>
            <li><span class="font-semibold text-amber-700">High (S2)</span>: Major feature broken, workaround exists — same day.</li>
            <li><span class="font-semibold text-sky-700">Normal (S3)</span>: Minor bug/UX — next release window.</li>
          </ul>

          <h3 class="font-semibold mt-4">Support window (Bangladesh)</h3>
          <p class="text-sm text-slate-700">Sun–Thu, 10:00–18:00 BST. Emergency coverage for S1 outside these hours.</p>
        </div>
      </div>
    </section>

    <!-- CTA -->
    <section class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 pb-16 reveal">
      <div class="panel soft-shadow rounded-2xl p-6 sm:p-8 text-center">
        <h2 class="text-2xl sm:text-3xl font-extrabold">Need urgent help?</h2>
        <p class="mt-2 text-slate-700">Open a ticket and mark it as <strong>Critical (S1)</strong>. Our team will jump in.</p>
        <div class="mt-4 flex flex-col sm:flex-row gap-3 justify-center">
          <a href="/contact" class="focus-ring inline-flex items-center gap-2 rounded-xl bg-slate-900 text-white px-5 py-3 font-semibold soft-shadow hover:bg-slate-800 transition">
            <i class="fa-solid fa-headset"></i> Contact
          </a>
          <a href="/pricing" class="focus-ring inline-flex items-center gap-2 rounded-xl bg-white border border-emerald-200 text-emerald-700 px-5 py-3 font-semibold soft-shadow hover:bg-emerald-50 transition">
            <i class="fa-solid fa-tags"></i> See Pricing
          </a>
        </div>
      </div>
    </section>
  </main>

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

  <!-- Toast -->
  <div id="toast" class="toast hidden">
    <div class="panel soft-shadow rounded-xl px-4 py-3 flex items-center gap-3">
      <i id="toastIcon" class="fa-solid fa-circle-check text-emerald-600"></i>
      <span id="toastMsg" class="text-sm"></span>
      <button id="toastClose" class="ml-2 text-gray-500 hover:text-gray-700"><i class="fa-solid fa-xmark"></i></button>
    </div>
  </div>

  <script>
    // Footer year
    document.getElementById('y').textContent = new Date().getFullYear();

    // Drawer behavior + focus management (shared)
    const toggleBtn = document.getElementById('nav-toggle');
    const closeBtn  = document.getElementById('nav-close');
    const drawer    = document.getElementById('mobile-drawer');
    const overlay   = document.getElementById('overlay');
    let lastFocus;

    function openDrawer(){
      lastFocus = document.activeElement;
      drawer.classList.remove('drawer-enter'); drawer.classList.add('drawer-open');
      overlay.classList.remove('overlay-hidden'); overlay.classList.add('overlay-open');
      drawer.setAttribute('aria-hidden','false');
      toggleBtn?.setAttribute('aria-expanded','true');
      document.documentElement.style.overflow='hidden'; document.body.style.overflow='hidden';
      (drawer.querySelector('a,button,[tabindex="0"]')||drawer).focus({preventScroll:true});
    }
    function closeDrawer(){
      drawer.classList.add('drawer-enter'); drawer.classList.remove('drawer-open');
      overlay.classList.add('overlay-hidden'); overlay.classList.remove('overlay-open');
      drawer.setAttribute('aria-hidden','true');
      toggleBtn?.setAttribute('aria-expanded','false');
      document.documentElement.style.overflow=''; document.body.style.overflow='';
      lastFocus && lastFocus.focus({preventScroll:true});
    }
    toggleBtn?.addEventListener('click', openDrawer, {passive:true});
    closeBtn?.addEventListener('click', closeDrawer, {passive:true});
    overlay?.addEventListener('click', closeDrawer, {passive:true});
    document.addEventListener('keydown', e => { if (e.key === 'Escape') closeDrawer(); }, {passive:true});

    // Toast helpers
    const toast     = document.getElementById('toast');
    const toastMsg  = document.getElementById('toastMsg');
    const toastIcon = document.getElementById('toastIcon');
    document.getElementById('toastClose').addEventListener('click', ()=>toast.classList.add('hidden'), {passive:true});
    function showToast(m, ok=true){
      toastMsg.textContent = m;
      toastIcon.className = ok ? 'fa-solid fa-circle-check text-emerald-600' : 'fa-solid fa-triangle-exclamation text-amber-600';
      toast.classList.remove('hidden');
      setTimeout(()=>toast.classList.add('hidden'), 5000);
    }

    // API endpoints from PHP
    const apiCreate  = <?= json_encode($apiCreate) ?>;
    const apiShowTpl = <?= json_encode($apiShowTpl) ?>;

    // Form elements
    const form       = document.getElementById('ticketForm');
    const openBtn    = document.getElementById('openBtn');
    const formAlert  = document.getElementById('formAlert');
    const openResult = document.getElementById('openResult');
    const copyIdBtn  = document.getElementById('copyIdBtn');

    function setAlert(type,msg){
      formAlert.className='mt-4 rounded-xl border px-4 py-3 ' + (type==='ok' ? 'border-emerald-200 bg-emerald-50 text-emerald-800' : 'border-red-200 bg-red-50 text-red-700');
      formAlert.textContent=msg; formAlert.classList.remove('hidden');
    }
    function fieldErr(name,msg){
      const el=document.querySelector(`[data-err="${name}"]`);
      if(el){ el.textContent=msg; el.classList.remove('hidden'); }
    }
    function clearErrs(){
      ['name','email','subject','body'].forEach(n=>{
        const el=document.querySelector(`[data-err="${n}"]`);
        if(el){ el.textContent=''; el.classList.add('hidden'); }
      });
      formAlert.classList.add('hidden');
    }

    // Submit: Open ticket
    form.addEventListener('submit', async (e)=>{
      e.preventDefault(); clearErrs();
      const payload = {
        name: form.name.value.trim(),
        email: form.email.value.trim(),
        phone: form.phone.value.trim(),
        module: form.module.value,
        subject: form.subject.value.trim(),
        body: form.body.value.trim()
      };

      let ok=true;
      if(!payload.name){ fieldErr('name','Required'); ok=false; }
      if(!payload.email || !/^[^@\s]+@[^@\s]+\.[^@\s]+$/.test(payload.email)){ fieldErr('email','Valid email required'); ok=false; }
      if(!payload.subject){ fieldErr('subject','Required'); ok=false; }
      if(!payload.body){ fieldErr('body','Required'); ok=false; }
      if(!ok) return;

      openBtn.disabled=true; const old=openBtn.innerHTML; openBtn.innerHTML='<i class="fa-solid fa-spinner fa-spin"></i> Submitting...';

      try{
        const resp = await fetch(apiCreate, {
          method:'POST',
          headers:{'Content-Type':'application/json'},
          body: JSON.stringify(payload)
        });
        if(!resp.ok) throw new Error(await resp.text().catch(()=> ''));
        const data = await resp.json().catch(()=> ({}));
        if(data.ok && data.ticketId){
          setAlert('ok', `Ticket submitted. Your Ticket ID: ${data.ticketId}`);
          openResult.textContent = `Save this ID: ${data.ticketId}`;
          localStorage.setItem('kf:lastTicketId', data.ticketId);
          copyIdBtn.dataset.ticketId = data.ticketId;
          copyIdBtn.classList.remove('hidden');
          showToast('Ticket created successfully.');
          form.reset();
        }else{
          throw new Error(data.error || 'Could not create ticket.');
        }
      }catch(err){
        setAlert('err','Could not submit right now. Please try again.');
        showToast('Submission failed.', false);
      }finally{
        openBtn.disabled=false; openBtn.innerHTML=old;
      }
    });

    // Copy Ticket ID
    copyIdBtn.addEventListener('click', async ()=>{
      const id = copyIdBtn.dataset.ticketId || localStorage.getItem('kf:lastTicketId');
      if(!id) return;
      try{ await navigator.clipboard.writeText(id); showToast('Ticket ID copied.'); }
      catch{ showToast('Copy failed.', false); }
    }, {passive:true});

    // Check status
    const ticketIdInput = document.getElementById('ticketId');
    const checkBtn      = document.getElementById('checkBtn');
    const pasteIdBtn    = document.getElementById('pasteIdBtn');
    const statusCard    = document.getElementById('statusCard');
    const statusText    = document.getElementById('statusText');

    pasteIdBtn.addEventListener('click', ()=>{
      const last = localStorage.getItem('kf:lastTicketId');
      if(last){ ticketIdInput.value = last; showToast('Pasted last Ticket ID.'); }
      else{ showToast('No Ticket ID saved yet.', false); }
    });

    checkBtn.addEventListener('click', async ()=>{
      const id = ticketIdInput.value.trim();
      if(!id){ showToast('Please enter a Ticket ID.', false); return; }
      checkBtn.disabled=true; const old=checkBtn.innerHTML; checkBtn.innerHTML='<i class="fa-solid fa-spinner fa-spin"></i> Checking...';
      statusCard.classList.add('hidden');

      try{
        const url = apiShowTpl.replace('__ID__', encodeURIComponent(id));
        const resp = await fetch(url);
        if(!resp.ok) throw new Error(await resp.text().catch(()=> ''));
        const data = await resp.json().catch(()=> ({}));
        if(data.ok && data.ticket){
          statusText.innerHTML =
            `<strong>Status:</strong> ${data.ticket.status}<br>` +
            `<strong>Subject:</strong> ${data.ticket.subject}<br>` +
            `<strong>Opened:</strong> ${data.ticket.created_at}`;
          statusCard.classList.remove('hidden');
          showToast('Status fetched.');
        }else{
          showToast(data.error || 'Ticket not found', false);
        }
      }catch(err){
        showToast('Could not fetch status.', false);
      }finally{
        checkBtn.disabled=false; checkBtn.innerHTML=old;
      }
    });

    // Reveal on scroll
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