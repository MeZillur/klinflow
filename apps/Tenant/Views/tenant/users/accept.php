<?php
declare(strict_types=1);
/** tenant/users/accept.php (drop-in)
 * Expects: $csrf, $token, $email (optional), $error (optional), $flash (optional)
 */
$h = fn($s)=>htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
$csrf   = $csrf   ?? '';
$token  = $token  ?? '';
$email  = $email  ?? '';  // optional: shown as read-only hint
$error  = $error  ?? '';
$flash  = $flash  ?? '';
?>
<div class="max-w-md mx-auto px-4 py-6">
  <h1 class="text-2xl font-semibold mb-2">Accept Invitation</h1>
  <p class="text-gray-600 dark:text-gray-400 mb-6">
    <?php if ($email): ?>
      You’re creating your account for <strong><?= $h($email) ?></strong>.
    <?php else: ?>
      Set your password to activate your account.
    <?php endif; ?>
  </p>

  <?php if ($error): ?>
    <div class="mb-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-red-800 text-sm"><?= nl2br($h($error)) ?></div>
  <?php endif; ?>
  <?php if ($flash): ?>
    <div class="mb-4 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-emerald-800 text-sm"><?= nl2br($h($flash)) ?></div>
  <?php endif; ?>

  <form id="accept-form" method="post" action="/tenant/invite/accept" class="space-y-5" novalidate>
    <input type="hidden" name="_csrf" value="<?= $h($csrf) ?>">
    <input type="hidden" name="token" value="<?= $h($token) ?>">

    <div>
      <label class="block text-sm font-medium mb-1" for="name">Your Name</label>
      <input id="name" name="name" type="text" required
             class="w-full rounded-lg border px-3 py-2 dark:bg-gray-900 dark:border-gray-700"
             placeholder="e.g. Jane Doe" autocomplete="name">
      <p class="mt-1 text-xs text-gray-500">This is how your teammates will see you.</p>
    </div>

    <?php if ($email): ?>
      <div>
        <label class="block text-sm font-medium mb-1">Email</label>
        <div class="w-full rounded-lg border px-3 py-2 bg-gray-50 dark:bg-gray-900 dark:border-gray-700 text-gray-600 dark:text-gray-300 select-all cursor-text">
          <?= $h($email) ?>
        </div>
        <p class="mt-1 text-xs text-gray-500">Email is pre-approved for this invite and cannot be changed here.</p>
      </div>
    <?php endif; ?>

    <div>
      <label class="block text-sm font-medium mb-1" for="password">Password</label>
      <div class="relative">
        <input id="password" name="password" type="password" required
               class="w-full rounded-lg border px-3 py-2 pr-10 dark:bg-gray-900 dark:border-gray-700"
               placeholder="••••••••" autocomplete="new-password" minlength="8" inputmode="text">
        <button type="button" id="togglePw" class="absolute right-2 top-1/2 -translate-y-1/2 text-xs text-gray-500">Show</button>
      </div>
      <div class="mt-2 h-1.5 w-full bg-gray-200 dark:bg-gray-800 rounded">
        <div id="pwbar" class="h-1.5 rounded" style="width:0%; background:#ef4444;"></div>
      </div>
      <p id="pwhint" class="mt-1 text-xs text-gray-500">Use at least 8 characters. Longer is stronger.</p>
    </div>

    <div>
      <label class="block text-sm font-medium mb-1" for="password_confirm">Confirm Password</label>
      <input id="password_confirm" name="password_confirm" type="password" required
             class="w-full rounded-lg border px-3 py-2 dark:bg-gray-900 dark:border-gray-700"
             placeholder="••••••••" autocomplete="new-password">
      <p id="matchhint" class="mt-1 text-xs"></p>
    </div>

    <button id="submitBtn" class="btn btn-brand text-white w-full disabled:opacity-60 disabled:cursor-not-allowed">
      Create Account
    </button>
  </form>

  <p class="text-xs text-gray-500 mt-4">
    If your link expired, ask your organization admin to resend the invite.
  </p>
</div>

<script>
(function(){
  const $ = (sel) => document.querySelector(sel);
  const form   = $('#accept-form');
  const nameEl = $('#name');
  const pw     = $('#password');
  const pw2    = $('#password_confirm');
  const bar    = $('#pwbar');
  const hint   = $('#pwhint');
  const match  = $('#matchhint');
  const btn    = $('#submitBtn');
  const toggle = $('#togglePw');

  function score(s){
    let n = 0;
    if (s.length >= 8) n++;
    if (/[A-Z]/.test(s)) n++;
    if (/[a-z]/.test(s)) n++;
    if (/\d/.test(s))    n++;
    if (/[^A-Za-z0-9]/.test(s)) n++;
    return Math.min(n, 5);
  }
  function renderStrength(){
    const s = score(pw.value);
    const pct = (s/5)*100;
    bar.style.width = pct + '%';
    const colors = ['#ef4444','#f59e0b','#eab308','#84cc16','#22c55e'];
    bar.style.background = colors[Math.max(0,s-1)] || colors[0];
    hint.textContent = (s>=4) ? 'Strong password' : 'Use upper/lowercase, numbers, and symbols for a stronger password.';
  }
  function renderMatch(){
    if (!pw2.value) { match.textContent=''; return; }
    if (pw.value === pw2.value){
      match.textContent = 'Passwords match.';
      match.className = 'mt-1 text-xs text-emerald-600';
    } else {
      match.textContent = 'Passwords do not match.';
      match.className = 'mt-1 text-xs text-red-600';
    }
  }
  function validateAll(){
    const okName = nameEl.value.trim().length >= 1;
    const okLen  = pw.value.length >= 8;
    const okMatch= pw.value !== '' && pw.value === pw2.value;
    btn.disabled = !(okName && okLen && okMatch);
  }

  pw.addEventListener('input', ()=>{ renderStrength(); renderMatch(); validateAll(); });
  pw2.addEventListener('input', ()=>{ renderMatch(); validateAll(); });
  nameEl.addEventListener('input', validateAll);

  toggle.addEventListener('click', ()=>{
    const isPwd = pw.type === 'password';
    pw.type = isPwd ? 'text' : 'password';
    toggle.textContent = isPwd ? 'Hide' : 'Show';
  });

  // initial
  renderStrength(); validateAll();

  form.addEventListener('submit', (e)=>{
    // Basic guard in case HTML5 validation is bypassed
    if (btn.disabled) { e.preventDefault(); }
  });
})();
</script>