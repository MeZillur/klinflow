<!doctype html>
<html lang="en"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Reset Password — KlinFlow CP</title>
<script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen bg-gray-50 flex items-center justify-center p-6">
  <div class="w-full max-w-md bg-white rounded-2xl shadow p-8">
    <h1 class="text-2xl font-semibold mb-6">Set a new password</h1>

    <?php if (!empty($error)): ?>
      <div class="mb-4 rounded-lg bg-red-50 text-red-700 px-4 py-2"><?=htmlspecialchars($error)?></div>
    <?php endif; ?>

    <form method="post" action="/cp/reset" class="space-y-4">
      <input type="hidden" name="_csrf" value="<?=htmlspecialchars($csrf)?>">
      <input type="hidden" name="token" value="<?=htmlspecialchars($token)?>">
      <div>
        <label class="block text-sm mb-1">New password</label>
        <input name="password" type="password" minlength="8" required class="w-full border rounded-lg px-3 py-2">
      </div>
      <div>
        <label class="block text-sm mb-1">Confirm password</label>
        <input name="password_confirm" type="password" minlength="8" required class="w-full border rounded-lg px-3 py-2">
      </div>
      <button class="w-full rounded-lg px-4 py-2 bg-gray-900 text-white">Update password</button>
    </form>
    <div class="text-sm mt-4"><a class="text-gray-700 underline" href="/cp/login">Back to sign in</a></div>
  </div>
</body>
</html>