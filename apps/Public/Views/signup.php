<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>KlinFlow — Create your account</title>
  <meta name="description" content="Create your KlinFlow account — start free and add your team anytime." />
  <meta name="theme-color" content="#228B22" />
  
  <!-- Tailwind CDN for preview -->
  <script src="https://cdn.tailwindcss.com"></script>
  <script>
    tailwind.config = {
      theme: {
        extend: {
          colors: { brand: '#228B22' },
          boxShadow: { 'xl-soft': '0 30px 60px -20px rgba(0,0,0,.25), 0 18px 36px -18px rgba(0,0,0,.22)' }
        }
      }
    }
  </script>

  <!-- Global tokens (optional) -->
  <link rel="stylesheet" href="/public/assets/styles/tokens.css" />
  <style>
    :root{ --brand:#228B22 }
    .glass{ backdrop-filter: blur(10px); background: color-mix(in srgb, white 70%, transparent); }
    .focus-brand:focus{ outline:none; box-shadow:0 0 0 4px color-mix(in srgb, var(--brand) 25%, transparent); }
    .soft-card{ box-shadow:0 12px 30px rgba(2,8,20,.06); border-radius:1.25rem; }
  </style>
</head>
<body class="antialiased text-slate-800 bg-gradient-to-b from-slate-50 to-white">

  <!-- ===== Header (shared) ===== -->
  <header class="sticky top-0 z-50 bg-white/80 glass border-b border-slate-200/60">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
      <div class="flex items-center justify-between h-16">
        <a href="/" class="flex items-center gap-2 hover:opacity-90 transition">
          <img src="/assets/brand/logo.png" alt="KlinFlow" class="h-7 w-auto"/>
          
        </a>
        <nav class="hidden md:flex items-center gap-8 text-[15px]">
          <a href="/about" class="text-slate-600 hover:text-slate-900">About</a>
          <a href="/contact" class="text-slate-600 hover:text-slate-900">Contact</a>
          <a href="/pricing" class="text-slate-600 hover:text-slate-900">Pricing</a>
          <a href="/tenant/login" class="inline-flex items-center gap-2 font-medium text-slate-700 hover:text-slate-900">Sign in</a>
        </nav>
        <button id="menuBtn" class="md:hidden p-2 rounded-lg hover:bg-slate-100 focus-brand" aria-label="Open menu">
          <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" class="w-6 h-6"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3.75 6.75h16.5M3.75 12h16.5M3.75 17.25h16.5"/></svg>
        </button>
      </div>
    </div>
    <div id="mobileNav" class="md:hidden hidden border-t border-slate-200/60">
      <div class="px-4 py-3 space-y-2 text-[15px]">
        <a href="/about" class="block text-slate-700">About</a>
        <a href="/contact" class="block text-slate-700">Contact</a>
        <a href="/pricing" class="block text-slate-700">Pricing</a>
        <a href="/signin" class="block text-slate-700">Sign in</a>
      </div>
    </div>
  </header>

  <!-- ===== Hero ===== -->
  <section class="relative overflow-hidden">
    <div class="absolute inset-0 -z-10">
      <div class="absolute -top-32 -right-32 w-[36rem] h-[36rem] rounded-full bg-emerald-200/40 blur-3xl"></div>
      <div class="absolute -bottom-24 -left-24 w-[28rem] h-[28rem] rounded-full bg-brand/10 blur-3xl"></div>
    </div>
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-16 lg:py-20">
      <div class="grid lg:grid-cols-[1.05fr_.95fr] gap-12 items-center">
        <div>
          <p class="inline-flex items-center gap-2 text-xs font-medium px-2.5 py-1.5 rounded-full bg-emerald-100 text-emerald-700 ring-1 ring-emerald-200">Start in minutes — no credit card</p>
          <h1 class="mt-4 text-4xl md:text-5xl font-black tracking-tight text-slate-900">Create your KlinFlow account</h1>
          <p class="mt-4 text-lg text-slate-600">One platform for POS, HotelFlow, DMS, School, MedFlow — onboard your org with a free tenant.</p>
          <ul class="mt-6 space-y-2 text-slate-600">
            <li class="flex items-start gap-2"><span class="mt-1 inline-block w-5 h-5 rounded-full bg-emerald-100 text-emerald-700 flex items-center justify-center">✓</span> Multi‑tenant by design</li>
            <li class="flex items-start gap-2"><span class="mt-1 inline-block w-5 h-5 rounded-full bg-emerald-100 text-emerald-700 flex items-center justify-center">✓</span> SSO ready • GDPR friendly</li>
            <li class="flex items-start gap-2"><span class="mt-1 inline-block w-5 h-5 rounded-full bg-emerald-100 text-emerald-700 flex items-center justify-center">✓</span> 14‑day logs • Audit trail</li>
          </ul>
        </div>

        <!-- ===== Sign up Card ===== -->
        <div class="soft-card bg-white p-6 md:p-8 shadow-xl">
          <form id="signupForm" class="space-y-5" novalidate>
            <!-- Company / Tenant -->
            <div class="grid sm:grid-cols-2 gap-4">
              <div>
                <label class="block text-sm font-medium text-slate-700" for="company">Company / Organization</label>
                <input id="company" name="company" type="text" required placeholder="DependCore Ltd." class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3.5 py-2.5 focus-brand" />
              </div>
              <div>
                <label class="block text-sm font-medium text-slate-700" for="slug">Tenant subdomain</label>
                <div class="mt-1 flex rounded-xl border border-slate-200 overflow-hidden">
                  <input id="slug" name="slug" type="text" required pattern="^[a-z0-9-]{3,}$" placeholder="dependcore" class="flex-1 bg-white px-3.5 py-2.5 focus-brand" />
                  <span class="px-3.5 py-2.5 bg-slate-50 text-slate-600 whitespace-nowrap">.klinflow.com</span>
                </div>
                <p id="slugHint" class="mt-1 text-xs text-slate-500">Use letters, digits and dashes. Min 3 chars.</p>
              </div>
            </div>

            <!-- User -->
            <div class="grid sm:grid-cols-2 gap-4">
              <div>
                <label class="block text-sm font-medium text-slate-700" for="name">Your name</label>
                <input id="name" name="name" type="text" required placeholder="Zillur Rahman" class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3.5 py-2.5 focus-brand" />
              </div>
              <div>
                <label class="block text-sm font-medium text-slate-700" for="email">Work email</label>
                <input id="email" name="email" type="email" required placeholder="you@company.com" class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3.5 py-2.5 focus-brand" />
              </div>
            </div>

            <!-- Passwords -->
            <div class="grid sm:grid-cols-2 gap-4">
              <div>
                <label class="block text-sm font-medium text-slate-700" for="password">Password</label>
                <div class="mt-1 relative">
                  <input id="password" name="password" type="password" required minlength="8" class="w-full rounded-xl border border-slate-200 bg-white px-3.5 py-2.5 pr-10 focus-brand" placeholder="At least 8 characters" />
                  <button type="button" class="absolute inset-y-0 right-0 px-3.5 text-slate-500" data-toggle="password" aria-label="Show password">👁</button>
                </div>
                <div class="mt-2 h-1.5 w-full rounded-full bg-slate-100">
                  <div id="pwBar" class="h-1.5 rounded-full bg-emerald-500 w-0 transition-all"></div>
                </div>
                <p id="pwHint" class="mt-1 text-xs text-slate-500">Use upper/lowercase, number, and symbol.</p>
              </div>
              <div>
                <label class="block text-sm font-medium text-slate-700" for="confirm">Confirm password</label>
                <div class="mt-1 relative">
                  <input id="confirm" name="confirm" type="password" required class="w-full rounded-xl border border-slate-200 bg-white px-3.5 py-2.5 pr-10 focus-brand" />
                  <button type="button" class="absolute inset-y-0 right-0 px-3.5 text-slate-500" data-toggle="confirm" aria-label="Show password">👁</button>
                </div>
                <p id="matchHint" class="mt-1 text-xs text-slate-500">Passwords must match.</p>
              </div>
            </div>

            <!-- Plan -->
            <div class="grid sm:grid-cols-3 gap-3">
              <label class="flex items-center gap-3 rounded-xl border border-slate-200 px-3.5 py-2.5 cursor-pointer hover:border-slate-300">
                <input type="radio" name="plan" value="free" class="accent-brand" checked />
                <span class="text-sm">Free</span>
              </label>
              <label class="flex items-center gap-3 rounded-xl border border-slate-200 px-3.5 py-2.5 cursor-pointer hover:border-slate-300">
                <input type="radio" name="plan" value="pro" class="accent-brand" />
                <span class="text-sm">Pro</span>
              </label>
              <label class="flex items-center gap-3 rounded-xl border border-slate-200 px-3.5 py-2.5 cursor-pointer hover:border-slate-300">
                <input type="radio" name="plan" value="enterprise" class="accent-brand" />
                <span class="text-sm">Enterprise</span>
              </label>
            </div>

            <!-- Terms -->
            <div class="flex items-start gap-3">
              <input id="terms" name="terms" type="checkbox" required class="mt-1 w-4 h-4 rounded border-slate-300" />
              <label for="terms" class="text-sm text-slate-600">I agree to the <a href="/terms" class="underline">Terms</a> and <a href="/privacy" class="underline">Privacy Policy</a>.</label>
            </div>

            <button type="submit" class="w-full inline-flex items-center justify-center gap-2 rounded-xl px-4 py-3 bg-slate-900 hover:bg-slate-800 text-white font-semibold shadow">
              Create account
            </button>

            <p class="text-center text-sm text-slate-600">Already have an account? <a href="/signin" class="underline">Sign in</a></p>

            <!-- Social (optional) -->
            <div class="relative py-2">
              <div class="absolute inset-0 flex items-center" aria-hidden="true">
                <div class="w-full border-t border-slate-200"></div>
              </div>
              <div class="relative flex justify-center">
                <span class="bg-white px-3 text-xs text-slate-500">or continue with</span>
              </div>
            </div>
            <div class="grid grid-cols-3 gap-3">
              <button type="button" class="rounded-xl border border-slate-200 px-3.5 py-2.5 hover:border-slate-300">Google</button>
              <button type="button" class="rounded-xl border border-slate-200 px-3.5 py-2.5 hover:border-slate-300">Microsoft</button>
              <button type="button" class="rounded-xl border border-slate-200 px-3.5 py-2.5 hover:border-slate-300">GitHub</button>
            </div>
          </form>
        </div>
      </div>
    </div>
  </section>

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

    // Keyboard shortcuts
    window.addEventListener('keydown', (e) => {
      if (e.key?.toLowerCase() === 'c') window.location.href = '/control/login';
      if (e.key?.toLowerCase() === 't') window.location.href = '/tenant/login';
    });

    // Slug sanitization & availability mock
    const slug = document.getElementById('slug');
    const slugHint = document.getElementById('slugHint');
    slug?.addEventListener('input', () => {
      const v = slug.value.toLowerCase().replace(/[^a-z0-9-]/g, '-').replace(/--+/g,'-').replace(/^-+|-+$/g,'');
      if (v !== slug.value) slug.value = v;
      if (v.length >= 3) {
        slugHint.textContent = 'Checking availability…';
        // Fake async check; replace with real GET /api/tenant/slug-available?slug=...
        setTimeout(() => {
          const taken = ['demo','test','admin'].includes(v);
          slugHint.textContent = taken ? 'This subdomain is taken.' : 'Available ✔';
          slugHint.className = 'mt-1 text-xs ' + (taken ? 'text-rose-600' : 'text-emerald-600');
        }, 350);
      } else {
        slugHint.textContent = 'Use letters, digits and dashes. Min 3 chars.';
        slugHint.className = 'mt-1 text-xs text-slate-500';
      }
    });

    // Password show/hide & strength
    const pw = document.getElementById('password');
    const cf = document.getElementById('confirm');
    const bar = document.getElementById('pwBar');
    const pwHint = document.getElementById('pwHint');
    const matchHint = document.getElementById('matchHint');

    document.querySelector('[data-toggle="password"]').addEventListener('click', () => {
      pw.type = pw.type === 'password' ? 'text' : 'password';
    });
    document.querySelector('[data-toggle="confirm"]').addEventListener('click', () => {
      cf.type = cf.type === 'password' ? 'text' : 'password';
    });

    const calc = (s) => {
      let score = 0;
      if (s.length >= 8) score += 1;
      if (/[A-Z]/.test(s)) score += 1;
      if (/[a-z]/.test(s)) score += 1;
      if (/[0-9]/.test(s)) score += 1;
      if (/[^A-Za-z0-9]/.test(s)) score += 1;
      return score; // 0-5
    };
    const draw = (score) => {
      const w = (score / 5) * 100;
      bar.style.width = w + '%';
      bar.style.backgroundColor = score < 3 ? '#ef4444' : score < 4 ? '#f59e0b' : '#10b981';
      pwHint.textContent = score < 3 ? 'Weak password' : score < 4 ? 'Okay — add more variety' : 'Strong password';
    };
    pw?.addEventListener('input', () => draw(calc(pw.value)));

    // Match check
    const checkMatch = () => {
      const ok = pw.value && cf.value && pw.value === cf.value;
      matchHint.textContent = ok ? 'Passwords match ✔' : 'Passwords must match.';
      matchHint.className = 'mt-1 text-xs ' + (ok ? 'text-emerald-600' : 'text-slate-500');
      return ok;
    };
    pw.addEventListener('input', checkMatch);
    cf.addEventListener('input', checkMatch);

    // Submit
    const form = document.getElementById('signupForm');
    form?.addEventListener('submit', (e) => {
      e.preventDefault();
      // Basic front-end validation
      if (!checkMatch()) return;
      const data = Object.fromEntries(new FormData(form).entries());
      console.log('Payload (mock):', data);
      // Replace with real POST /api/public/signup
      const btn = form.querySelector('button[type="submit"]');
      const txt = btn.textContent;
      btn.disabled = true; btn.textContent = 'Creating…';
      setTimeout(() => {
        btn.disabled = false; btn.textContent = txt;
        window.location.href = `https://${data.slug || 'your'}.klinflow.com/welcome`;
      }, 900);
    });
  </script>
  
  <script> window.chtlConfig = { chatbotId: "8748546719" } </script>
<script async data-id="8748546719" id="chtl-script" type="text/javascript" src="https://chatling.ai/js/embed.js"></script>
  
</body>
</html>
