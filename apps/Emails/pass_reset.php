<?php
declare(strict_types=1);

/**
 * Password Reset Email Template
 * Expected vars:
 *  - string $brand
 *  - string $brandColor
 *  - string $org_name
 *  - string $reset_url
 *  - int    $ttl_minutes
 */
$h = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
$brand       = $brand       ?? 'KlinFlow';
$brandColor  = $brandColor  ?? '#228B22';
$org_name    = $org_name    ?? '';
$ttl_minutes = $ttl_minutes ?? 60;
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title><?= $h($brand) ?> — Password Reset</title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <style>
    body{margin:0;background:#f5f7fb;font-family:Inter,Segoe UI,Roboto,Arial,sans-serif;color:#111827}
    .card{max-width:640px;margin:24px auto;background:#fff;border-radius:16px;
          box-shadow:0 6px 30px rgba(16,24,40,.05);overflow:hidden;border:1px solid #eef2f7}
    .header{display:flex;align-items:center;justify-content:space-between;
            padding:20px 28px;border-bottom:1px solid #eef2f7}
    .brand-left{display:flex;align-items:center;gap:8px}
    .logo-dot{width:28px;height:28px;border-radius:8px;background:<?= $h($brandColor) ?>}
    .brand-title{font-weight:700;font-size:17px;letter-spacing:.3px}
    .content{padding:28px 28px 12px 28px;line-height:1.55}
    .btn{display:inline-block;margin-top:16px;background:<?= $h($brandColor) ?>;
         color:#fff;text-decoration:none;font-weight:600;border-radius:10px;
         padding:12px 20px}
    .muted{color:#6b7280;font-size:13px;margin-top:18px;line-height:1.55}
    .footer{padding:16px 28px;background:#f9fafb;border-top:1px solid #eef2f7;
            color:#9ca3af;font-size:12px;line-height:1.6;text-align:center}
  </style>
</head>
<body>
  <div class="card">
    <div class="header">
      <div class="brand-left">
        <div class="logo-dot"></div>
        <div class="brand-title"><?= $h($brand) ?></div>
      </div>
    </div>

    <div class="content">
      <h2 style="font-size:20px;font-weight:700;margin:0 0 6px 0;">Reset your password</h2>
      <p>
        We received a request to reset your tenant password for
        <strong><?= $h($org_name) ?></strong>.
      </p>
      <p>Click the button below to create a new password.</p>
      <p>
        <a href="<?= $h($reset_url) ?>" class="btn">Reset Password</a>
      </p>
      <p class="muted">
        If the button doesn’t work, copy and paste this link into your browser:<br>
        <span style="word-break:break-all;color:#374151"><?= $h($reset_url) ?></span><br>
        <br>This link expires in <strong><?= $h($ttl_minutes) ?> minutes</strong>.
      </p>
    </div>

    <div class="footer">
      If you didn’t request this, you can safely ignore this message.<br>
      © <?= date('Y') ?> <?= $h($brand) ?> • All rights reserved.<br>
      <span>KlinFlow HQ • support@klinflow.com</span>
    </div>
  </div>
</body>
</html>