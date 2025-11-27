<?php
declare(strict_types=1);

use Shared\Csrf;

$h = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');

/** CSRF */
$csrf = $csrf ?? (class_exists(Csrf::class) ? Csrf::token() : '');

/** Flash + error */
$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

$error = $error ?? ($_SESSION['_err'] ?? null);
unset($_SESSION['_err']);

/** Branding */
$brandColor = $brandColor ?? '#228B22';
$logoPath   = $logoPath   ?? '/assets/brand/logo.png';

/** Page meta */
$title = 'KlinFlow — Control Panel Login';
?>
<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title><?= $h($title) ?></title>

  <!-- Tailwind 2 (kept as-is) -->
  <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet" />
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" />

  <meta name="theme-color" content="<?= $h($brandColor) ?>" />
  <style>
    :root{ --brand: <?= $h($brandColor) ?>; }
    .focus-ring:focus-visible { outline: 2px solid var(--brand); outline-offset: 3px; }
    @media (prefers-reduced-motion: reduce){
      *{animation-duration:.001ms!important;animation-iteration-count:1!important;transition-duration:.001ms!important;scroll-behavior:auto!important}
    }
  </style>
</head>
<body class="bg-gray-50 min-h-screen flex items-center justify-center p-6 text-gray-900">
  <main class="w-full max-w-md bg-white shadow-lg rounded-2xl p-8 border border-gray-100" role="main" aria-labelledby="title">
    <div class="flex flex-col items-center mb-6">
      <img src="<?= $h($logoPath) ?>" alt="KlinFlow logo" class="h-10 w-auto mb-2" width="40" height="40" decoding="async" />
      <h1 id="title" class="text-xl font-semibold">Control Panel Login</h1>
      <p class="text-gray-500 text-sm">Sign in to manage organizations, modules and users.</p>
    </div>

    <?php if ($flash): ?>
      <div class="mb-4 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-emerald-800 text-sm" role="status" aria-live="polite">
        <i class="fa-solid fa-circle-check mr-2" aria-hidden="true"></i><?= $h(is_array($flash)?($flash['msg']??''): $flash) ?>
      </div>
    <?php endif; ?>

    <?php if (!empty($error)): ?>
      <div class="mb-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-red-800 text-sm" role="alert" aria-live="assertive">
        <i class="fa-solid fa-triangle-exclamation mr-2" aria-hidden="true"></i><?= $h($error) ?>
      </div>
    <?php endif; ?>

    <form method="post" action="/cp/login" class="space-y-5" autocomplete="on" novalidate>
      <input type="hidden" name="_csrf" value="<?= $h($csrf) ?>" />

      <!-- Email / Username / Mobile -->
      <div>
        <label for="login" class="block text-sm font-medium text-gray-700 mb-1">Email / Username / Mobile</label>
        <input id="login" name="login" type="text" required
               class="focus-ring w-full rounded-lg border border-gray-300 bg-white px-3 py-2 shadow-sm focus:border-green-600"
               placeholder="you@example.com or username or 01XXXXXXXXX"
               inputmode="email" autocapitalize="none" spellcheck="false"
               autocomplete="username" autofocus>
      </div>

      <!-- Password -->
      <div>
        <label for="password" class="block text-sm font-medium text-gray-700 mb-1">Password</label>
        <div class="relative">
          <input id="password" name="password" type="password" required
                 class="focus-ring w-full rounded-lg border border-gray-300 bg-white px-3 py-2 pr-10 shadow-sm focus:border-green-600"
                 placeholder="••••••••" autocomplete="current-password" aria-describedby="pw-hint">
          <button type="button" class="absolute inset-y-0 right-2 px-2 text-gray-500 hover:text-gray-700"
                  aria-label="Toggle password visibility" aria-pressed="false"
                  onclick="
                    const i=this.previousElementSibling;
                    const isPw=i.type==='password';
                    i.type=isPw?'text':'password';
                    this.setAttribute('aria-pressed', String(isPw));
                    this.querySelector('i').classList.toggle('fa-eye');
                    this.querySelector('i').classList.toggle('fa-eye-slash');
                  ">
            <i class="fa-regular fa-eye" aria-hidden="true"></i>
          </button>
        </div>
        <p id="pw-hint" class="sr-only">Press the button to show or hide the password.</p>
      </div>

      <!-- Remember + forgot -->
      <div class="flex items-center justify-between">
        <label class="inline-flex items-center gap-2 text-sm text-gray-700">
          <input type="checkbox" name="remember" value="1" class="rounded border-gray-300">
          Keep me signed in
        </label>
        <a href="/cp/forgot" class="text-sm" style="color: var(--brand)">Forgot Password?</a>
      </div>

      <!-- Submit -->
      <button type="submit" class="w-full focus-ring text-white py-3 px-4 rounded-lg font-semibold shadow" style="background: var(--brand)">
        <i class="fa-solid fa-right-to-bracket mr-1" aria-hidden="true"></i> Sign In
      </button>
    </form>

    <div class="mt-6 text-center text-sm text-gray-500">
      Not a CP user? <a style="color: var(--brand)" href="/tenant/login">Go to Tenant login</a>
    </div>
  </main>
</body>
</html>