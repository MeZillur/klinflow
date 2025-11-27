<?php
declare(strict_types=1);

/** @var string   $csrf */
/** @var string   $login */
/** @var string   $password */
/** @var string   $remember */
/** @var array    $candidates */

$h = fn($v)=>htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
$title = 'Choose Your Organization • Tenant Login';
$brand = '#228B22';
?>
<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title><?= $h($title) ?></title>
  <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet" crossorigin>
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" rel="stylesheet" crossorigin>
  <style>:root{--brand:<?= $h($brand) ?>}</style>
</head>
<body class="bg-gray-50 min-h-screen flex items-center justify-center p-6 text-gray-900">
  <main class="w-full max-w-lg bg-white shadow-lg rounded-2xl p-6 border border-gray-100">
    <header class="mb-4">
      <h1 class="text-xl font-semibold">Choose your organization</h1>
      <p class="text-sm text-gray-600">
        We found multiple organizations for <span class="font-medium"><?= $h($login) ?></span>.  
        Select the one you want to sign in to.
      </p>
    </header>

    <?php if (empty($candidates)): ?>
      <div class="rounded-xl border border-yellow-200 bg-yellow-50 px-4 py-3 text-yellow-900 text-sm">
        No organizations are linked to this account.
      </div>
      <div class="mt-6 flex justify-between">
        <a href="/tenant/login" class="px-4 py-2 rounded-lg border">Back</a>
      </div>
    <?php else: ?>
      <form method="post" action="/tenant/login" class="space-y-4">
        <input type="hidden" name="_csrf" value="<?= $h($csrf) ?>">
        <input type="hidden" name="login" value="<?= $h($login) ?>">
        <input type="hidden" name="password" value="<?= $h($password) ?>">
        <input type="hidden" name="remember" value="<?= $h($remember) ?>">

        <fieldset class="space-y-2">
          <?php foreach ($candidates as $i => $org): ?>
            <?php
              $id    = (int)$org['org_id'];
              $name  = $org['name'] ?? ('Org #'.$id);
              $slug  = $org['slug'] ?? '';
              $plan  = ucfirst((string)($org['plan'] ?? ''));
              $st    = ucfirst((string)($org['status'] ?? ''));
            ?>
            <label class="flex items-start gap-3 p-3 rounded-lg border hover:bg-gray-50 cursor-pointer">
              <input class="mt-1.5" type="radio" name="org_id" value="<?= $id ?>" <?= $i===0?'checked':'' ?>>
              <div class="flex-1">
                <div class="font-medium"><?= $h($name) ?></div>
                <div class="text-xs text-gray-500">/<?= $h($slug) ?> · Plan: <?= $h($plan) ?> · Status: <?= $h($st) ?></div>
              </div>
            </label>
          <?php endforeach; ?>
        </fieldset>

        <div class="pt-2 flex items-center justify-between">
          <a href="/tenant/login" class="px-4 py-2 rounded-lg border">Back</a>
          <button type="submit"
                  class="px-5 py-2.5 rounded-lg text-white font-semibold"
                  style="background:var(--brand)">
            Continue
          </button>
        </div>
      </form>
    <?php endif; ?>
  </main>
</body>
</html>