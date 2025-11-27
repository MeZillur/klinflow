<?php
declare(strict_types=1);
/** tenant/users/edit.php (drop-in)
 * Expects: $slug, $csrf, $user (id,name,email,username,mobile,role,is_active), $roles, $error, $flash
 */
$h     = fn($s)=>htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
$slug  = $slug  ?? '';
$csrf  = $csrf  ?? '';
$user  = $user  ?? [];
$roles = $roles ?? ['owner','manager','employee','viewer'];
?>
<div class="max-w-2xl px-4 py-6">
  <h1 class="text-2xl font-semibold mb-6">Edit User</h1>

  <?php if (!empty($error)): ?>
    <div class="mb-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-red-800 text-sm"><?= nl2br($h($error)) ?></div>
  <?php endif; ?>
  <?php if (!empty($flash)): ?>
    <div class="mb-4 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-emerald-800 text-sm"><?= nl2br($h($flash)) ?></div>
  <?php endif; ?>

  <form id="tenant-user-edit" method="post" action="/t/<?= $h($slug) ?>/users/<?= (int)($user['id'] ?? 0) ?>/edit" class="space-y-6" novalidate>
    <input type="hidden" name="_csrf" value="<?= $h($csrf) ?>">

    <div class="grid sm:grid-cols-2 gap-4">
      <label class="block">
        <span class="text-sm">Name</span>
        <input name="name" value="<?= $h($user['name'] ?? '') ?>" required
               class="mt-1 w-full rounded-lg border px-3 py-2 dark:bg-gray-900 dark:border-gray-700" autocomplete="name">
      </label>

      <label class="block">
        <span class="text-sm">Email</span>
        <input name="email" type="email" value="<?= $h($user['email'] ?? '') ?>" required
               class="mt-1 w-full rounded-lg border px-3 py-2 dark:bg-gray-900 dark:border-gray-700" autocomplete="email">
      </label>

      <label class="block">
        <span class="text-sm">Username</span>
        <input name="username" value="<?= $h($user['username'] ?? '') ?>"
               class="mt-1 w-full rounded-lg border px-3 py-2 dark:bg-gray-900 dark:border-gray-700" autocomplete="username" placeholder="optional">
      </label>

      <label class="block">
        <span class="text-sm">Mobile</span>
        <input name="mobile" value="<?= $h($user['mobile'] ?? '') ?>"
               class="mt-1 w-full rounded-lg border px-3 py-2 dark:bg-gray-900 dark:border-gray-700" inputmode="tel" placeholder="optional">
      </label>
    </div>

    <div class="grid sm:grid-cols-2 gap-4">
      <label class="block">
        <span class="text-sm">Role</span>
        <select name="role" class="mt-1 w-full rounded-lg border px-3 py-2 dark:bg-gray-900 dark:border-gray-700">
          <?php foreach ($roles as $r): ?>
            <option value="<?= $h($r) ?>" <?= (($user['role'] ?? 'employee')===$r)?'selected':'' ?>>
              <?= ucfirst($h($r)) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </label>

      <label class="inline-flex items-center gap-2 mt-6">
        <input type="checkbox" name="is_active" value="1" <?= ((int)($user['is_active'] ?? 0)===1)?'checked':'' ?>>
        <span class="text-sm">Active</span>
      </label>
    </div>

    <!-- Optional: change password (leave blank to keep current) -->
    <details class="rounded-lg border dark:border-gray-700 p-4">
      <summary class="cursor-pointer text-sm font-medium">Change password (optional)</summary>
      <div class="mt-4 space-y-4">
        <div>
          <label class="block text-sm mb-1" for="password">New password</label>
          <div class="relative">
            <input id="password" name="password" type="password"
                   class="w-full rounded-lg border px-3 py-2 pr-10 dark:bg-gray-900 dark:border-gray-700"
                   placeholder="Leave blank to keep current" autocomplete="new-password" minlength="8">
            <button type="button" id="togglePw" class="absolute right-2 top-1/2 -translate-y-1/2 text-xs text-gray-500">Show</button>
          </div>
          <div class="mt-2 h-1.5 w-full bg-gray-200 dark:bg-gray-800 rounded">
            <div id="pwbar" class="h-1.5 rounded" style="width:0%; background:#ef4444;"></div>
          </div>
          <p id="pwhint" class="mt-1 text-xs text-gray-500">Use 8+ characters. Mix upper/lowercase, numbers, and symbols.</p>
        </div>

        <div>
          <label class="block text-sm mb-1" for="password_confirm">Confirm new password</label>
          <input id="password_confirm" name="password_confirm" type="password"
                 class="w-full rounded-lg border px-3 py-2 dark:bg-gray-900 dark:border-gray-700"
                 autocomplete="new-password" placeholder="Repeat new password">
          <p id="matchhint" class="mt-1 text-xs"></p>
        </div>
      </div>
    </details>

    <div class="flex items-center gap-2">
      <button class="btn btn-brand text-white">Save</button>
      <a href="/t/<?= $h($slug) ?>/users" class="btn btn-ghost">Cancel</a>
    </div>
  </form>
</div>

<script>
(function(){
  const $ = (s)=>document.querySelector(s);
  const pw = $('#password');
  const pw2 = $('#password_confirm');
  const bar = $('#pwbar');
  const hint = $('#pwhint');
  const match = $('#matchhint');
  const toggle = $('#togglePw');

  if (toggle && pw){
    toggle.addEventListener('click', ()=>{
      const isPwd = pw.type === 'password';
      pw.type = isPwd ? 'text' : 'password';
      toggle.textContent = isPwd ? 'Hide' : 'Show';
    });
  }

  function score(s){
    if (!s) return 0;
    let n = 0;
    if (s.length >= 8) n++;
    if (/[A-Z]/.test(s)) n++;
    if (/[a-z]/.test(s)) n++;
    if (/\d/.test(s))    n++;
    if (/[^A-Za-z0-9]/.test(s)) n++;
    return Math.min(n,5);
  }
  function renderStrength(){
    const s = score(pw.value || '');
    const pct = (s/5)*100;
    bar.style.width = pct + '%';
    const colors = ['#ef4444','#f59e0b','#eab308','#84cc16','#22c55e'];
    bar.style.background = colors[Math.max(0,s-1)] || colors[0];
    hint.textContent = s>=4 ? 'Strong password' : 'Use upper/lowercase, numbers & symbols to strengthen.';
  }
  function renderMatch(){
    if (!pw.value && !pw2.value){ match.textContent=''; return; }
    if ((pw.value||'') === (pw2.value||'')){
      match.textContent = 'Passwords match.';
      match.className = 'mt-1 text-xs text-emerald-600';
    } else {
      match.textContent = 'Passwords do not match.';
      match.className = 'mt-1 text-xs text-red-600';
    }
  }

  if (pw){
    pw.addEventListener('input', ()=>{ renderStrength(); renderMatch(); });
  }
  if (pw2){
    pw2.addEventListener('input', renderMatch);
  }
})();
</script>