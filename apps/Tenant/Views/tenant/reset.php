<?php
declare(strict_types=1);

use Shared\Csrf;

$h = fn($s)=>htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
$csrf  = $csrf ?? (class_exists(Csrf::class) ? Csrf::token() : '');
$error = $error ?? ($_SESSION['_err'] ?? null); unset($_SESSION['_err']);

$token = $token ?? ($_GET['token'] ?? '');
$org   = $org ?? ($_GET['org'] ?? ''); // you may pass org slug in query or session

$brandColor = $brandColor ?? '#228B22';

/* --- LOGO VISIBILITY FIX ---
 * Normalize any provided paths to absolute `/assets/...`.
 * If a value begins with `/public/` or `public/`, rewrite it to `/`.
 */
$normalize = static function (?string $p): string {
  $p = (string)($p ?? '');
  if ($p === '') return '/assets/brand/logo.png';
  $p = preg_replace('#^/?public/#', '/', $p); // drop leading "public/"
  if ($p[0] !== '/') $p = '/'.$p;             // ensure leading slash
  return $p;
};

$logoPath = $normalize($logoPath ?? '/assets/brand/logo.png');
$favicon  = $normalize($favicon  ?? '/assets/brand/logo.png');

$title = 'KlinFlow — Reset Password';
?>
<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
  <title><?= $h($title) ?></title>

  <!-- Icons (absolute paths to avoid 404 when app served from /) -->
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
      <!-- FIX: normalized absolute logo path + explicit dimensions to prevent CLS -->
      <img src="<?= $h($logoPath) ?>" alt="KlinFlow logo" class="h-10 w-auto mb-2" width="160" height="40" loading="eager" decoding="async" fetchpriority="high">
      <h1 class="text-xl font-semibold">Reset Password</h1>
      <p class="text-gray-500 text-sm">Choose a new password for your tenant account.</p>
    </div>

    <?php if ($error): ?>
      <div class="mb-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-red-800 text-sm"><?= $h($error) ?></div>
    <?php endif; ?>

    <form method="post" action="/tenant/reset" class="space-y-5" novalidate>
      <input type="hidden" name="_csrf" value="<?= $h($csrf) ?>">
      <input type="hidden" name="token" value="<?= $h($token) ?>">
      <input type="hidden" name="organization" value="<?= $h($org) ?>">

      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">New Password</label>
        <input name="password" type="password" required minlength="8"
               class="focus-ring w-full rounded-lg border border-gray-300 px-3 py-2" placeholder="••••••••" autocomplete="new-password">
      </div>
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Confirm Password</label>
        <input name="password_confirm" type="password" required minlength="8"
               class="focus-ring w-full rounded-lg border border-gray-300 px-3 py-2" placeholder="••••••••" autocomplete="new-password">
      </div>
      <button class="w-full focus-ring text-white py-3 px-4 rounded-lg font-semibold shadow" style="background: var(--brand)">Update Password</button>
    </form>

    <div class="mt-6 text-center text-sm text-gray-500">
      <a style="color: var(--brand)" href="/tenant/login">&larr; Back to login</a>
    </div>
  </main>
</body>
</html>