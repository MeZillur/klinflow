<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>KlinFlow — Contact</title>
  <meta name="description" content="Get in touch with KlinFlow — questions, sales, support, and partnership inquiries." />
  <meta name="theme-color" content="#228B22" />
  
  <!-- Tailwind (CDN for preview). In production, use your compiled Tailwind. -->
  <script src="https://cdn.tailwindcss.com"></script>
  
  <!-- Global tokens (colors, spacing, etc.) -->
  <link rel="stylesheet" href="/public/assets/styles/tokens.css" />

  <style>
    :root{ --brand:#228B22; }
    .brand { color: var(--brand); }
    .brand-bg { background: var(--brand); }
    .brand-ring { box-shadow: 0 0 0 6px color-mix(in srgb, var(--brand) 20%, transparent); }
    .glass { backdrop-filter: blur(10px); background: color-mix(in srgb, white 70%, transparent); }
    .soft-card { box-shadow: 0 10px 30px rgba(0,0,0,.06); border-radius: 1.25rem; }
    .focus-brand:focus { outline: none; box-shadow: 0 0 0 4px color-mix(in srgb, var(--brand) 25%, transparent); }
  </style>
</head>
<body class="antialiased text-slate-800 bg-gradient-to-b from-slate-50 to-white">

  <!-- ===== Header (shared across pages) ===== -->
  <header class="sticky top-0 z-50 bg-white/80 glass border-b border-slate-200/60">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
      <div class="flex items-center justify-between h-16">
        <!-- Left: Logo -->
        <a href="/" class="flex items-center gap-2 hover:opacity-90 transition">
                  <img src="/assets/brand/logo.png" alt="KlinFlow" class="h-10 w-auto" />
        </a>

        <!-- Center: Nav -->
        <nav class="hidden md:flex items-center gap-8 text-[15px]">
          <a href="/about" class="text-slate-600 hover:text-slate-900">About</a>
          <a href="/contact" class="text-slate-900 font-semibold">Contact</a>
          <a href="/pricing" class="text-slate-600 hover:text-slate-900">Pricing</a>
          <a href="/signup" class="inline-flex items-center gap-2 font-medium text-slate-700 hover:text-slate-900">
            <span>Sign up free</span>
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="w-4 h-4"><path d="M13.5 4.5a.75.75 0 0 1 .75-.75h5.25a.75.75 0 0 1 .75.75v5.25a.75.75 0 0 1-1.5 0V6.31l-6.97 6.97a.75.75 0 1 1-1.06-1.06L17.69 5.25H14.25a.75.75 0 0 1-.75-.75Z"/><path d="M5.25 6a.75.75 0 0 1 .75-.75h6a.75.75 0 0 1 0 1.5h-6A.75.75 0 0 1 5.25 6Zm0 4.5a.75.75 0 0 1 .75-.75h6a.75.75 0 0 1 0 1.5h-6a.75.75 0 0 1-.75-.75Zm0 4.5a.75.75 0 0 1 .75-.75h6a.75.75 0 0 1 0 1.5h-6a.75.75 0 0 1-.75-.75Z"/></svg>
          </a>
        </nav>

        <!-- Right: Mobile menu button -->
        <button id="menuBtn" class="md:hidden p-2 rounded-lg hover:bg-slate-100 focus-brand" aria-label="Open menu">
          <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" class="w-6 h-6"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3.75 6.75h16.5M3.75 12h16.5M3.75 17.25h16.5"/></svg>
        </button>
      </div>
    </div>

    <!-- Mobile nav -->
    <div id="mobileNav" class="md:hidden hidden border-t border-slate-200/60">
      <div class="px-4 py-3 space-y-2 text-[15px]">
        <a href="/about" class="block text-slate-700">About</a>
        <a href="/contact" class="block font-semibold text-slate-900">Contact</a>
        <a href="/pricing" class="block text-slate-700">Pricing</a>
        <a href="/signup" class="block text-slate-700">Sign up free</a>
      </div>
    </div>
  </header>

  <!-- ===== Hero ===== -->
  <section class="relative">
    <div class="absolute inset-0 -z-10 bg-gradient-to-b from-emerald-50 via-white to-white"></div>
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-16 lg:py-24">
      <div class="grid lg:grid-cols-2 gap-12 items-center">
        <div>
          <p class="inline-flex items-center gap-2 text-xs font-medium px-2.5 py-1.5 rounded-full bg-emerald-100 text-emerald-700 ring-1 ring-emerald-200">We reply within 24 hours <span class="inline-block w-1.5 h-1.5 rounded-full bg-emerald-600"></span></p>
          <h1 class="mt-5 text-4xl md:text-5xl font-black tracking-tight text-slate-900">Let’s talk — we’re here to help.</h1>
          <p class="mt-4 text-lg text-slate-600">Whether you’re exploring KlinFlow for your business or need support, our team is just a message away.</p>
          <div class="mt-8 flex flex-wrap gap-3">
            <a href="mailto:hello@klinflow.com" class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl border border-slate-200 hover:border-slate-300 text-slate-700 hover:text-slate-900 bg-white shadow-sm">
              <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" class="w-5 h-5"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M21.75 8.25v7.5a2.25 2.25 0 0 1-2.25 2.25h-15A2.25 2.25 0 0 1 2.25 15.75v-7.5m19.5 0A2.25 2.25 0 0 0 19.5 6H4.5a2.25 2.25 0 0 0-2.25 2.25m19.5 0v.243a2.25 2.25 0 0 1-1.07 1.916l-6.75 3.857a2.25 2.25 0 0 1-2.16 0L4.32 10.41a2.25 2.25 0 0 1-1.07-1.916V8.25"/></svg>
              hello@klinflow.com
            </a>
            <a href="https://wa.me/00000000000" class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-slate-900 hover:bg-slate-800 text-white shadow">
              <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="w-5 h-5"><path d="M20.52 3.48A11.89 11.89 0 0 0 12.06 0 12 12 0 0 0 0 12a11.87 11.87 0 0 0 1.64 6l-1.07 4 4.1-1.07A12 12 0 0 0 12 24 12 12 0 0 0 24 12a11.9 11.9 0 0 0-3.48-8.52Z"/></svg>
              WhatsApp us
            </a>
          </div>
        </div>
        <div>
          <div class="soft-card bg-white p-6 md:p-8">
            <h2 class="text-xl font-semibold">Send us a message</h2>
            <p class="mt-1 text-sm text-slate-500">We’ll reach out by email within one business day.</p>

            <form class="mt-6 grid grid-cols-1 sm:grid-cols-2 gap-4" id="contactForm">
              <div>
                <label class="block text-sm font-medium text-slate-700" for="name">Full name</label>
                <input id="name" name="name" type="text" required class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3.5 py-2.5 focus-brand" placeholder="Jane Doe" />
              </div>
              <div>
                <label class="block text-sm font-medium text-slate-700" for="org">Organization</label>
                <input id="org" name="org" type="text" class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3.5 py-2.5 focus-brand" placeholder="Acme Ltd." />
              </div>
              <div>
                <label class="block text-sm font-medium text-slate-700" for="email">Email</label>
                <input id="email" name="email" type="email" required class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3.5 py-2.5 focus-brand" placeholder="you@company.com" />
              </div>
              <div>
                <label class="block text-sm font-medium text-slate-700" for="phone">Phone</label>
                <input id="phone" name="phone" type="tel" class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3.5 py-2.5 focus-brand" placeholder="+880 1XXX-XXXXXX" />
              </div>
              <div class="sm:col-span-2">
                <label class="block text-sm font-medium text-slate-700" for="subject">Subject</label>
                <input id="subject" name="subject" type="text" required class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3.5 py-2.5 focus-brand" placeholder="I’d like to learn more about KlinFlow" />
              </div>
              <div class="sm:col-span-2">
                <label class="block text-sm font-medium text-slate-700" for="message">Message</label>
                <textarea id="message" name="message" rows="5" required class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3.5 py-2.5 focus-brand" placeholder="Tell us what you’re looking for…"></textarea>
              </div>
              <div class="sm:col-span-2">
                <label class="block text-sm font-medium text-slate-700" for="file">Attach RFQ / brief (optional)</label>
                <input id="file" name="file" type="file" class="mt-1 w-full rounded-xl border border-dashed border-slate-300 bg-slate-50 px-3.5 py-2.5" />
              </div>
              <div class="sm:col-span-2 flex items-center gap-3">
                <input id="consent" name="consent" type="checkbox" required class="w-4 h-4 rounded border-slate-300" />
                <label for="consent" class="text-sm text-slate-600">You agree to our <a href="/privacy" class="underline">Privacy Policy</a>.</label>
              </div>
              <div class="sm:col-span-2 flex items-center gap-3">
                <button type="submit" class="inline-flex items-center justify-center gap-2 rounded-xl px-4 py-2.5 brand-bg text-white font-semibold hover:opacity-95 shadow">
                  <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="w-5 h-5"><path d="M2.25 12l18-9-4.5 18-6-6-7.5-3z"/></svg>
                  Send message
                </button>
                <p id="formHint" class="text-sm text-slate-500">We’ll email you shortly.</p>
              </div>
            </form>
          </div>
        </div>
      </div>
    </div>
  </section>

  <!-- ===== Quick contacts ===== -->
  <section class="py-10">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
      <div class="grid md:grid-cols-3 gap-6">
        <div class="soft-card bg-white p-6">
          <div class="w-11 h-11 rounded-xl bg-emerald-100 text-emerald-700 flex items-center justify-center mb-3">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="w-6 h-6"><path d="M1.5 4.5A1.5 1.5 0 0 1 3 3h18a1.5 1.5 0 0 1 1.5 1.5v15A1.5 1.5 0 0 1 21 21H3a1.5 1.5 0 0 1-1.5-1.5v-15ZM3 6.75l7.72 5.56a3 3 0 0 0 3.56 0L22.5 6.75"/></svg>
          </div>
          <h3 class="font-semibold">General</h3>
          <p class="mt-1 text-sm text-slate-600">hello@klinflow.com</p>
        </div>
        <div class="soft-card bg-white p-6">
          <div class="w-11 h-11 rounded-xl bg-indigo-100 text-indigo-700 flex items-center justify-center mb-3">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="w-6 h-6"><path d="M3.75 4.5h16.5v3H3.75v-3Zm0 6h16.5v9.75H3.75V10.5Z"/></svg>
          </div>
          <h3 class="font-semibold">Sales</h3>
          <p class="mt-1 text-sm text-slate-600">sales@klinflow.com</p>
        </div>
        <div class="soft-card bg-white p-6">
          <div class="w-11 h-11 rounded-xl bg-rose-100 text-rose-700 flex items-center justify-center mb-3">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="w-6 h-6"><path d="M12 3c4.97 0 9 4.03 9 9s-4.03 9-9 9-9-4.03-9-9 4.03-9 9-9Zm0 3.75a.75.75 0 0 0-.75.75v5.25c0 .414.336.75.75.75h4.5a.75.75 0 0 0 0-1.5h-3.75V7.5A.75.75 0 0 0 12 6.75Z"/></svg>
          </div>
          <h3 class="font-semibold">Support</h3>
          <p class="mt-1 text-sm text-slate-600">support@klinflow.com</p>
        </div>
      </div>
    </div>
  </section>

  <!-- ===== Map / Office ===== -->
  <section class="py-12">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
      <div class="soft-card bg-white p-0 overflow-hidden">
        <div class="grid md:grid-cols-2">
          <div class="p-8">
            <h3 class="text-lg font-semibold">Our office</h3>
            <p class="mt-2 text-slate-600">House 00, Road 00, Banani, Dhaka 1212, Bangladesh</p>
            <dl class="mt-6 space-y-2 text-sm text-slate-600">
              <div class="flex items-center gap-2"><span class="font-medium w-28">Phone</span><span>+880 1XXX-XXXXXX</span></div>
              <div class="flex items-center gap-2"><span class="font-medium w-28">Hours</span><span>Sun–Thu, 10:00–18:00</span></div>
            </dl>
            <a href="https://maps.google.com" class="mt-6 inline-flex items-center gap-2 text-slate-700 hover:text-slate-900 underline">Open in Google Maps
              <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="w-4 h-4"><path d="M13.5 4.5a.75.75 0 0 1 .75-.75h5.25a.75.75 0 0 1 .75.75v5.25a.75.75 0 0 1-1.5 0V6.31l-6.97 6.97a.75.75 0 1 1-1.06-1.06L17.69 5.25H14.25a.75.75 0 0 1-.75-.75Z"/></svg>
            </a>
          </div>
          <div class="min-h-[320px]">
            <iframe title="KlinFlow Office" class="w-full h-full" loading="lazy" referrerpolicy="no-referrer-when-downgrade" src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3648.0000000000005!2d90.412!3d23.780!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x0%3A0x0!2sKlinFlow!5e0!3m2!1sen!2sbd!4v1700000000000"></iframe>
          </div>
        </div>
      </div>
    </div>
  </section>
  
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

  <!-- ===== Footer ===== -->
  <footer class="border-t border-slate-200/60">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 flex flex-col md:flex-row items-center justify-between gap-4">
      <p class="text-sm text-slate-500">© <span id="y"></span> KlinFlow. All rights reserved.</p>
      <div class="flex items-center gap-6 text-sm">
        <a href="/privacy" class="text-slate-600 hover:text-slate-900">Privacy</a>
        <a href="/terms" class="text-slate-600 hover:text-slate-900">Terms</a>
        <span class="text-slate-400">Tip: press <kbd class="px-1 py-0.5 rounded bg-slate-100 border">C</kbd> for Control Panel or <kbd class="px-1 py-0.5 rounded bg-slate-100 border">T</kbd> for Tenant login.</span>
      </div>
    </div>
  </footer>

  <script>
    // Year
    document.getElementById('y').textContent = new Date().getFullYear();

    // Mobile menu toggle
    const btn = document.getElementById('menuBtn');
    const nav = document.getElementById('mobileNav');
    btn?.addEventListener('click', () => nav.classList.toggle('hidden'));

    // Keyboard shortcuts from your hero pattern
    window.addEventListener('keydown', (e) => {
      if (e.key?.toLowerCase() === 'c') window.location.href = '/control/login';
      if (e.key?.toLowerCase() === 't') window.location.href = '/tenant/login';
    });

    // Fake submit (replace with your POST endpoint)
    const form = document.getElementById('contactForm');
    const hint = document.getElementById('formHint');
    form?.addEventListener('submit', (e) => {
      e.preventDefault();
      hint.textContent = 'Sending…';
      setTimeout(() => {
        hint.textContent = 'Thanks! Your message has been sent.';
        form.reset();
      }, 700);
    });
  </script>
  <script> window.chtlConfig = { chatbotId: "8748546719" } </script>
<script async data-id="8748546719" id="chtl-script" type="text/javascript" src="https://chatling.ai/js/embed.js"></script>
  
</body>
</html>
