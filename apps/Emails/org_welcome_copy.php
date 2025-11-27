<?php
/** @var string $org_name */
/** @var string $owner_email */
/** @var string $login_url */
/** @var string $tempPassword */
$h = fn($v)=>htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');

// derive friendly name
$ownerName = ucfirst(explode('@', $owner_email)[0]);
?>
<!doctype html>
<html>
  <head>
    <meta charset="utf-8">
    <title>Welcome to KlinFlow</title>
    <style>
      body{font-family:Inter,Arial,Helvetica,sans-serif;background:#f6f9fc;margin:0;padding:24px;color:#111827}
      .card{max-width:640px;margin:0 auto;background:#fff;border-radius:16px;padding:24px;border:1px solid #eef2f7}
      .btn{display:inline-block;padding:12px 18px;border-radius:10px;background:#228B22;color:#fff;text-decoration:none;font-weight:600}
      .muted{color:#6b7280;font-size:13px}
    </style>
  </head>
  <body>
    <div class="card">
      <h2>Welcome to KlinFlow 👋</h2>
      <p>Hi <?= $h($ownerName) ?>,</p>
      <p>Your organization <strong><?= $h($org_name) ?></strong> has been created successfully.</p>
      <p>You can log in to the Control Panel using the temporary credentials below:</p>
      <ul>
        <li><strong>Username:</strong> <?= $h($owner_email) ?></li>
        <li><strong>Temporary Password:</strong> <?= $h($tempPassword) ?></li>
      </ul>
      <p><a class="btn" href="<?= $h($login_url) ?>">Open Control Panel</a></p>
      <p class="muted">If the button doesn’t work, copy and paste this URL:<br><?= $h($login_url) ?></p>
      <p>Please change your password after logging in.</p>
      <p>— Team KlinFlow</p>
    </div>
  </body>
</html>