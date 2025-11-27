<?php
declare(strict_types=1);

use Shared\Csrf;

$h = fn($s)=>htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
$csrf  = $csrf ?? (class_exists(Csrf::class) ? Csrf::token() : '');
$msg   = $msg ?? ($_SESSION['_msg'] ?? null); unset($_SESSION['_msg']);
$error = $error ?? ($_SESSION['_err'] ?? null); unset($_SESSION['_err']);

/* --- LOGO VISIBILITY FIX ---
 * Normalize any provided paths to absolute `/assets/...`.
 * If a value begins with `/public/`, rewrite it to `/`.
 */
$normalize = static function (?string $p): string {
  $p = (string)($p ?? '');
  if ($p === '') return '/assets/brand/logo.png';
  return preg_replace('#^/public/#', '/', $p);
};

$brandColor = $brandColor ?? '#228B22';
$logoPath   = $normalize($logoPath ?? '/assets/brand/logo.png');
$favicon    = $normalize($favicon ?? '/assets/brand/logo.png');

$title = 'KlinFlow — Tenant Password Reset';
?>
<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
  <title><?= $h($title) ?></title>

  <!-- Icons (absolute paths) -->
  <link rel="icon" type="image/png" href="<?= $h($favicon) ?>">
  <link rel="preload" href="<?= $h($logoPath) ?>" as="image" imagesizes="(max-width: 768px) 144px, 160px" imagesrcset="<?= $h($logoPath) ?> 1x">

  <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet" crossorigin>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" crossorigin>
  <meta name="theme-color" content="<?= $h($brandColor) ?>">
  <style>
    :root{--brand:<?= $h($brandColor) ?>}
    .focus-ring:focus-visible{outline:2px solid var(--brand);outline-offset:3px}
    @media (prefers-reduced-motion: reduce){
      *{animation-duration:.001ms!important;animation-iteration-count:1!important;transition-duration:.001ms!important;scroll-behavior:auto!important}
    }
  </style>
</head>
<body class="bg-gray-50 min-h-screen flex items-center justify-center p-6 text-gray-900">
  <main class="w-full max-w-md bg-white shadow-lg rounded-2xl p-8 border border-gray-100">
    <div class="flex flex-col items-center mb-6">
      <!-- FIX: use normalized absolute path + explicit dimensions to avoid CLS -->
      <img src="<?= $h($logoPath) ?>" alt="KlinFlow logo" class="h-10 w-auto mb-2" width="160" height="40" loading="eager" decoding="async" fetchpriority="high">
      <h1 class="text-xl font-semibold">Forgot Password</h1>
      <p class="text-gray-500 text-sm">Enter your email, username, or mobile to receive a reset link.</p>
    </div>

    <?php if ($msg): ?>
      <div class="mb-4 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-emerald-800 text-sm">
        <i class="fa-solid fa-circle-check mr-2"></i><?= $h($msg) ?>
      </div>
    <?php endif; ?>

    <?php if ($error): ?>
      <div class="mb-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-red-800 text-sm">
        <i class="fa-solid fa-triangle-exclamation mr-2"></i><?= $h($error) ?>
      </div>
    <?php endif; ?>

    <form method="post" action="/tenant/forgot" class="space-y-5" novalidate>
      <input type="hidden" name="_csrf" value="<?= $h($csrf) ?>">

      <!-- Email / Username / Mobile -->
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Email / Username / Mobile</label>
        <input name="identity" required
               class="focus-ring w-full rounded-lg border border-gray-300 px-3 py-2"
               placeholder="you@example.com or username or 01XXXXXXXXX"
               autocapitalize="none" spellcheck="false" autocomplete="username">
        <p class="text-xs text-gray-500 mt-1">We’ll detect your organization automatically.</p>
      </div>

      <button type="submit"
              class="w-full focus-ring text-white py-3 px-4 rounded-lg font-semibold shadow"
              style="background: var(--brand)">
        <i class="fa-solid fa-paper-plane mr-1"></i> Send Reset Link
      </button>
    </form>

    <div class="mt-6 text-center text-sm text-gray-500">
      <a style="color: var(--brand)" href="/tenant/login">&larr; Back to login</a>
    </div>
  </main>
</body>
</html>