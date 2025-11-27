<?php
declare(strict_types=1);
/** @var string $csrf */ /** @var ?string $msg */ /** @var ?string $error */
$h = fn($v)=>htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
  <title>KlinFlow — Forgot password</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" crossorigin="anonymous">
</head>
<body class="bg-gray-50 text-gray-900">
  <div class="min-h-screen flex items-center justify-center p-6">
    <div class="w-full max-w-md bg-white rounded-2xl shadow p-6">
      <h1 class="text-2xl font-semibold mb-1">Forgot password</h1>
      <p class="text-gray-500 mb-4">Enter your email to get a reset link.</p>

      <?php if (!empty($msg)): ?>
        <div class="mb-3 rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2 text-emerald-800 text-sm"><?= $h($msg) ?></div>
      <?php endif; ?>
      <?php if (!empty($error)): ?>
        <div class="mb-3 rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-red-800 text-sm"><?= nl2br($h($error)) ?></div>
      <?php endif; ?>

      <form method="post" action="/tenant/forgot" class="space-y-3">
        <input type="hidden" name="_csrf" value="<?= $h($csrf) ?>">
        <div>
          <label class="block text-sm font-medium mb-1">Email</label>
          <input name="email" type="email" required class="w-full border rounded-lg px-3 py-2">
        </div>
        <button class="w-full py-2 rounded-lg text-white" style="background:#228B22">Send reset link</button>
      </form>

      <div class="text-sm text-gray-500 mt-4">
        <a class="hover:underline" href="/tenant/login">Back to login</a>
      </div>
    </div>
  </div>
</body>
</html>