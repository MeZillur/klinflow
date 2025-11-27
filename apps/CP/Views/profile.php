<!doctype html>
<html lang="en"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>My Profile — KlinFlow CP</title>
<script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen bg-gray-50">
  <header class="bg-white shadow">
    <div class="max-w-4xl mx-auto px-6 py-4 flex items-center justify-between">
      <div class="font-semibold">KlinFlow CP</div>
      <nav class="flex items-center gap-3 text-sm">
        <a class="underline" href="/cp/dashboard">Dashboard</a>
        <a class="underline" href="/cp/users">Users</a>
        <form method="post" action="/cp/logout">
          <input type="hidden" name="_csrf" value="<?=htmlspecialchars($_SESSION['_csrf']??'')?>">
          <button class="px-3 py-2 rounded bg-gray-900 text-white">Logout</button>
        </form>
      </nav>
    </div>
  </header>

  <main class="max-w-4xl mx-auto p-6">
    <h1 class="text-2xl font-semibold mb-4">My Profile</h1>

    <?php if (!empty($msg)): ?>
      <div class="mb-4 rounded-lg bg-green-50 text-green-700 px-4 py-2"><?=htmlspecialchars($msg)?></div>
    <?php endif; ?>
    <?php if (!empty($error)): ?>
      <div class="mb-4 rounded-lg bg-red-50 text-red-700 px-4 py-2"><?=htmlspecialchars($error)?></div>
    <?php endif; ?>

    <form method="post" action="/cp/profile/update" class="grid md:grid-cols-2 gap-6 bg-white rounded-xl shadow p-6">
      <input type="hidden" name="_csrf" value="<?=htmlspecialchars($csrf)?>">

      <div class="md:col-span-2">
        <label class="block text-sm mb-1">Name</label>
        <input name="name" type="text" required class="w-full border rounded-lg px-3 py-2"
               value="<?=htmlspecialchars($u['name']??'')?>">
      </div>

      <div>
        <label class="block text-sm mb-1">Email</label>
        <input name="email" type="email" required class="w-full border rounded-lg px-3 py-2"
               value="<?=htmlspecialchars($u['email']??'')?>">
      </div>

      <div>
        <label class="block text-sm mb-1">Username <span class="text-gray-500 text-xs">(optional)</span></label>
        <input name="username" type="text" class="w-full border rounded-lg px-3 py-2"
               placeholder="letters/numbers/_" value="<?=htmlspecialchars($u['username']??'')?>">
      </div>

      <div>
        <label class="block text-sm mb-1">Mobile <span class="text-gray-500 text-xs">(digits only)</span></label>
        <input name="mobile" type="text" class="w-full border rounded-lg px-3 py-2"
               placeholder="017XXXXXXXX" value="<?=htmlspecialchars($u['mobile']??'')?>">
      </div>

      <div class="md:col-span-2 pt-2">
        <h2 class="text-lg font-semibold mb-2">Change Password</h2>
        <p class="text-sm text-gray-600 mb-3">Leave blank to keep your current password.</p>

        <div class="grid md:grid-cols-3 gap-4">
          <div>
            <label class="block text-sm mb-1">Current password</label>
            <input name="current_password" type="password" class="w-full border rounded-lg px-3 py-2">
          </div>
          <div>
            <label class="block text-sm mb-1">New password</label>
            <input name="new_password" type="password" class="w-full border rounded-lg px-3 py-2" minlength="8">
          </div>
          <div>
            <label class="block text-sm mb-1">Confirm new password</label>
            <input name="new_password_confirm" type="password" class="w-full border rounded-lg px-3 py-2" minlength="8">
          </div>
        </div>
      </div>

      <div class="md:col-span-2">
        <button class="px-5 py-2 rounded-lg bg-gray-900 text-white">Save changes</button>
      </div>
    </form>
  </main>
</body>
</html>