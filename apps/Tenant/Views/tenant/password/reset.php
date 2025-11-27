<?php
declare(strict_types=1);
/** @var string $csrf */ /** @var string $token */ /** @var ?string $error */
$h = fn($v)=>htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
  <title>KlinFlow — Reset password</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" crossorigin="anonymous">
</head>
<body class="bg-gray-50 text-gray-900">
  <div class="min-h-screen flex items-center justify-center p-6">
    <div class="w-full max-w-md bg-white rounded-2xl shadow p-6">
      <h1 class="text-2xl font-semibold mb-1">Choose a new password</h1>
      <p class="text-gray-500 mb-4">At least 8 characters.</p>

      <?php if (!empty($error)): ?>
        <div class="mb-3 rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-red-800 text-sm"><?= nl2br($h($error)) ?></div>
      <?php endif; ?>

      <form method="post" action="/tenant/reset" class="space-y-3">
        <input type="hidden" name="_csrf" value="<?= $h($csrf) ?>">
        <input type="hidden" name="token" value="<?= $h($token) ?>">
        <div>
          <label class="block text-sm font-medium mb-1">New password</label>
          <input type="password" name="password" required class="w-full border rounded-lg px-3 py-2">
        </div>
        <div>
          <label class="block text-sm font-medium mb-1">Confirm password</label>
          <input type="password" name="password_confirm" required class="w-full border rounded-lg px-3 py-2">
        </div>
        <button class="w-full py-2 rounded-lg text-white" style="background:#228B22">Update password</button>
      </form>

      <div class="text-sm text-gray-500 mt-4">
        <a class="hover:underline" href="/tenant/login">Back to login</a>
      </div>
    </div>
  </div>
</body>
</html>