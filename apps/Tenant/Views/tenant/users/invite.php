<?php
declare(strict_types=1);
/**
 * tenant/users/invite.php (drop-in view)
 * Expects: $slug, $csrf
 * Optional: $prefill = ['email'=>'','role'=>'employee'], $roles = [...]
 * Optional: $invites = [ ['email'=>..,'role'=>..,'expires_at'=>..,'token'=>..,'accept_url'=>..], ... ]
 * Optional: $flash, $error
 */
$h        = fn($s)=>htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
$slug     = (string)($slug ?? '');
$csrf     = (string)($csrf ?? '');
$prefill  = is_array($prefill ?? null) ? $prefill : [];
$roles    = is_array($roles ?? null) ? $roles : ['owner','manager','employee','viewer'];
$invites  = is_array($invites ?? null) ? $invites : [];
$flash    = (string)($flash ?? '');
$error    = (string)($error ?? '');
?>
<div class="max-w-xl mx-auto px-4 py-6">
  <div class="flex items-center justify-between mb-4">
    <h1 class="text-2xl font-semibold">Invite User</h1>
    <a class="text-sm text-gray-600 dark:text-gray-400 hover:underline" href="/t/<?= $h($slug) ?>/users">&larr; Back to Users</a>
  </div>

  <?php if ($flash): ?>
    <div class="mb-4 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-emerald-800 text-sm"><?= nl2br($h($flash)) ?></div>
  <?php endif; ?>
  <?php if ($error): ?>
    <div class="mb-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-red-800 text-sm"><?= nl2br($h($error)) ?></div>
  <?php endif; ?>

  <form method="post" action="/t/<?= $h($slug) ?>/users/invite" class="space-y-6">
    <input type="hidden" name="_csrf" value="<?= $h($csrf) ?>">

    <div>
      <label class="block text-sm font-medium mb-1">Email</label>
      <input
        name="email"
        type="email"
        required
        value="<?= $h($prefill['email'] ?? '') ?>"
        class="w-full rounded-lg border px-3 py-2 dark:bg-gray-900 dark:border-gray-700"
        placeholder="teammate@company.com"
        autocomplete="off"
      >
      <p class="text-xs text-gray-500 mt-1">We’ll send them a secure link to create their account.</p>
    </div>

    <div>
      <label class="block text-sm font-medium mb-1">Role</label>
      <select
        name="role"
        class="w-full rounded-lg border px-3 py-2 dark:bg-gray-900 dark:border-gray-700"
      >
        <?php foreach ($roles as $r): $r = (string)$r; ?>
          <option value="<?= $h($r) ?>" <?= (($prefill['role'] ?? 'employee') === $r) ? 'selected' : '' ?>>
            <?= ucfirst($h($r)) ?>
          </option>
        <?php endforeach; ?>
      </select>
      <p class="text-xs text-gray-500 mt-1">Owner has full control, Manager manages teams, Employee has standard access, Viewer is read-only.</p>
    </div>

    <div class="flex items-center gap-2">
      <button class="btn btn-brand text-white rounded-lg px-4 py-2">Send Invite</button>
      <a class="btn btn-ghost rounded-lg px-4 py-2" href="/t/<?= $h($slug) ?>/users">Cancel</a>
    </div>
  </form>

  <div class="mt-10">
    <div class="flex items-center justify-between">
      <h2 class="font-semibold">Pending Invites</h2>
      <form method="post" action="/t/<?= $h($slug) ?>/users/invite" class="hidden">
        <!-- (Optional) You could add a “Resend all” later -->
        <input type="hidden" name="_csrf" value="<?= $h($csrf) ?>">
      </form>
    </div>

    <div class="mt-2 overflow-auto border border-gray-200 dark:border-gray-700 rounded-xl">
      <table class="min-w-full text-sm">
        <thead class="bg-gray-50 dark:bg-gray-800/60">
          <tr>
            <th class="text-left p-3">Email</th>
            <th class="text-left p-3">Role</th>
            <th class="text-left p-3">Expires</th>
            <th class="text-left p-3">Link</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
          <?php if (!$invites): ?>
            <tr>
              <td colspan="4" class="p-4 text-center text-gray-500 dark:text-gray-400">
                No pending invites.
              </td>
            </tr>
          <?php else: foreach ($invites as $i): ?>
            <?php
              $i_email   = (string)($i['email']      ?? '');
              $i_role    = (string)($i['role']       ?? 'employee');
              $i_exp     = (string)($i['expires_at'] ?? '');
              // Prefer accept_url if you pass it from controller; otherwise derive from token.
              $i_url     = (string)($i['accept_url'] ?? '');
              if ($i_url === '' && !empty($i['token']) && preg_match('/^[a-f0-9]{64}$/', (string)$i['token'])) {
                  $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on')
                        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https') ? 'https://' : 'http://';
                  $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
                  $i_url  = $scheme.$host.'/tenant/invite/accept?token='.rawurlencode((string)$i['token']);
              }
            ?>
            <tr>
              <td class="p-3"><?= $h($i_email) ?></td>
              <td class="p-3 capitalize"><?= $h($i_role) ?></td>
              <td class="p-3 whitespace-nowrap"><?= $h($i_exp) ?></td>
              <td class="p-3">
                <?php if ($i_url): ?>
                  <div class="flex items-center gap-2">
                    <a href="<?= $h($i_url) ?>" class="text-brand hover:underline" target="_blank" rel="noopener">Open</a>
                    <button
                      type="button"
                      class="text-xs px-2 py-1 rounded border border-gray-300 dark:border-gray-600 hover:bg-gray-50 dark:hover:bg-gray-800"
                      onclick="navigator.clipboard.writeText('<?= $h($i_url) ?>').then(()=>{ this.textContent='Copied'; setTimeout(()=>this.textContent='Copy',1500); })"
                      aria-label="Copy invite link"
                    >Copy</button>
                  </div>
                <?php else: ?>
                  <span class="text-xs text-gray-400">—</span>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>