<?php
/** @var string $org_name */
/** @var string $owner_email */
/** @var string $login_url */
/** @var string $tempPassword */
$h = fn($v)=>htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');

// Friendly name from email
$ownerName = ucfirst(explode('@', (string)$owner_email)[0]);

// Build absolute base URL from the login URL (so images resolve in email clients)
$scheme = (string)(parse_url((string)$login_url, PHP_URL_SCHEME) ?: 'https');
$host   = (string)(parse_url((string)$login_url, PHP_URL_HOST)   ?: 'klinflow.com');
$base   = $scheme.'://'.$host;

// Your logo lives in /public/assets/brand/logo.png on the server.
// For email, we MUST remove "/public" and make it absolute:
$logoUrl = $base . '/assets/brand/logo.png';

// Brand
$brandColor = '#228B22';
?>
<!doctype html>
<html>
  <head>
    <meta charset="utf-8">
    <title>Welcome to KlinFlow</title>
    <meta name="x-apple-disable-message-reformatting">
    <meta name="color-scheme" content="light only">
    <style>
      /* Minimal resets for some clients */
      body { margin:0; padding:0; background:#f6f9fc; }
      img { border:0; outline:none; text-decoration:none; display:block; }
      a { text-decoration:none; }
      .btn a { color:#ffffff !important; } /* Make sure the CTA text stays white everywhere */
    </style>
  </head>
  <body style="margin:0; padding:24px; background:#f6f9fc;">
    <!-- Outer wrapper (max width) -->
    <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%">
      <tr>
        <td align="center">
          <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="640" style="max-width:640px; width:100%;">
            <!-- Header: two-part (logo left, brand color right) -->
            <tr>
              <td style="padding:0;">
                <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%">
                  <tr>
                    <td width="60%" align="left" style="padding:16px 16px 16px 20px; background:#ffffff; border:1px solid #eef2f7; border-bottom:none; border-radius:16px 0 0 0;">
                      <img src="<?= $h($logoUrl) ?>" alt="KlinFlow" width="148" height="auto" style="height:32px; width:auto;">
                    </td>
                    <td width="40%" align="right" style="padding:0; background:<?= $h($brandColor) ?>; border:1px solid #eef2f7; border-left:none; border-bottom:none; border-radius:0 16px 0 0;">
                      <!-- Decorative brand block -->
                      <div style="height:64px; line-height:64px; font-size:0;">&nbsp;</div>
                    </td>
                  </tr>
                </table>
              </td>
            </tr>

            <!-- Card body -->
            <tr>
              <td style="background:#ffffff; border:1px solid #eef2f7; border-top:none; border-bottom:none; padding:24px 20px;">
                <h1 style="margin:0 0 8px 0; font-family:Inter,Arial,Helvetica,sans-serif; font-size:22px; line-height:1.3; color:#111827;">
                  Welcome to KlinFlow
                </h1>
                <div style="font-family:Inter,Arial,Helvetica,sans-serif; color:#111827; font-size:15px; line-height:1.6;">
                  <p style="margin:12px 0;">Dear, <?= $h($ownerName) ?>,</p>
                  <p style="margin:12px 0;">
                    Your organization <strong><?= $h($org_name) ?></strong> has been created successfully.
                  </p>
                  <p style="margin:12px 0;">
                    Use the temporary credentials below to access the Control Panel, then change your password.
                  </p>

                  <!-- Credential box -->
                  <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="margin:16px 0; background:#f9fafb; border:1px solid #eef2f7; border-radius:12px;">
                    <tr>
                      <td style="padding:14px 16px;">
                        <p style="margin:0 0 6px 0; font-size:14px;">
                          <strong>Your Username:</strong>
                          <a href="mailto:<?= $h($owner_email) ?>" style="color:#2563eb;"><?= $h($owner_email) ?></a>
                        </p>
                        <p style="margin:0; font-size:14px;">
                          <strong>Temporary Password:</strong>
                          <span style="display:inline-block; padding:6px 10px; border-radius:8px; background:#ffffff; border:1px solid #e5e7eb; font-family:ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, 'Liberation Mono', monospace;">
                            <?= $h($tempPassword) ?>
                          </span>
                        </p>
                      </td>
                    </tr>
                  </table>

                  <!-- Bulletproof CTA button -->
                  <table role="presentation" cellpadding="0" cellspacing="0" border="0" class="btn" style="margin:18px 0;">
                    <tr>
                      <td align="center" bgcolor="<?= $h($brandColor) ?>" style="border-radius:12px;">
                        <a href="<?= $h($login_url) ?>"
                           style="display:inline-block; padding:12px 20px; font-weight:700; font-size:14px; font-family:Inter,Arial,Helvetica,sans-serif; color:#ffffff; background:<?= $h($brandColor) ?>; border-radius:12px;">
                          Open Control Panel
                        </a>
                      </td>
                    </tr>
                  </table>

                  <p style="margin:12px 0; font-size:13px; color:#6b7280;">
                    If the button doesn’t work, copy and paste this URL into your browser:<br>
                    <a href="<?= $h($login_url) ?>" style="color:#2563eb;"><?= $h($login_url) ?></a>
                  </p>

                  <p style="margin:18px 0 0 0;">— Team KlinFlow</p>
                </div>
              </td>
            </tr>

            <!-- Footer with contact -->
            <tr>
              <td style="background:#ffffff; border:1px solid #eef2f7; border-top:none; border-radius:0 0 16px 16px; padding:16px 20px;">
                <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%">
                  <tr>
                    <td valign="top" style="font-family:Inter,Arial,Helvetica,sans-serif; font-size:12px; color:#6b7280;">
                      <strong>KlinFlow</strong><br>
                      House 12, Road 34, Gulshan, Dhaka 1212<br>
                      <a href="mailto:support@klinflow.com" style="color:#2563eb;">support@klinflow.com</a> ·
                      <a href="<?= $h($base) ?>" style="color:#2563eb;"><?= $h($host) ?></a>
                    </td>
                    <td align="right" valign="top" style="font-family:Inter,Arial,Helvetica,sans-serif; font-size:12px; color:#6b7280;">
                      © <?= date('Y') ?> KlinFlow. All rights reserved.
                    </td>
                  </tr>
                </table>
              </td>
            </tr>

          </table>
        </td>
      </tr>
    </table>
  </body>
</html>