<!doctype html>
<html lang="en"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Forgot Password — KlinFlow CP</title>
<script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen bg-gray-50 flex items-center justify-center p-6">
  <?php /* Toast (top-right) */ ?>
  <?php if (!empty($msg)): ?>
    <div id="toast" class="fixed top-4 right-4 z-50">
      <div class="rounded-xl bg-green-600 text-white shadow-lg px-4 py-3">
        <strong class="block">Request Successful</strong>
        <span class="text-sm">A password reset link has been sent from contact@klinflow.com. Please check your email inbox and reset your password.</span>
      </div>
    </div>
    <script>
      setTimeout(()=>{ const t=document.getElementById('toast'); if(t){ t.style.transition='opacity .3s'; t.style.opacity='0'; setTimeout(()=>t.remove(),300);} }, 4000);
    </script>
  <?php endif; ?>

  <div class="w-full max-w-md bg-white rounded-2xl shadow p-8">
    <h1 class="text-2xl font-semibold mb-6">Forgot your password?</h1>

    <?php if (!empty($msg)): ?>
      <div class="mb-4 rounded-lg bg-green-50 text-green-700 px-4 py-2">
        <?=htmlspecialchars($msg)?>
      </div>
    <?php endif; ?>

    <?php if (!empty($error)): ?>
      <div class="mb-4 rounded-lg bg-red-50 text-red-700 px-4 py-2">
        <?=htmlspecialchars($error)?>
      </div>
    <?php endif; ?>

    <form method="post" action="/cp/forgot" class="space-y-4">
      <input type="hidden" name="_csrf" value="<?=htmlspecialchars($csrf)?>">
      <div>
        <label class="block text-sm mb-1">Email</label>
        <input name="email" type="email" required class="w-full border rounded-lg px-3 py-2" placeholder="you@example.com">
      </div>
      <button class="w-full rounded-lg px-4 py-2 bg-gray-900 text-white">Send reset link</button>
    </form>

    <div class="text-sm mt-4"><a class="text-gray-700 underline" href="/cp/login">Back to sign in</a></div>
  </div>
</body>
</html>