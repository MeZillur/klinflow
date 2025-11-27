<?php
declare(strict_types=1);

namespace Shared;

final class MailTemplates
{
    /* ========================= Core rendering pieces ========================= */

    private static function esc($v): string {
        return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
    }

    /**
     * Email shell with split header:
     * - Left 50%: white background with logo
     * - Right 50%: brand color panel with title
     * Footer includes corporate address.
     */
    private static function shell(
        string $brand,
        string $brandHex,
        string $title,
        string $preheader,
        string $bodyHtml,
        ?string $logoUrl = null
    ): string {
        $brand     = self::esc($brand ?: 'KlinFlow');
        $brandHex  = self::esc($brandHex ?: '#228B22');
        $title     = self::esc($title ?: $brand);
        $prehead   = self::esc($preheader ?: '');
        $logoUrl   = self::esc($logoUrl ?: '/assets/brand/logo.png');
        $year      = date('Y');
        $addr      = 'Corporate office: House-20, Road-17, Nikunjo-2, Dhaka-1229';

        return <<<HTML
<!doctype html>
<html>
<head>
  <meta charset="utf-8"/>
  <meta name="viewport" content="width=device-width,initial-scale=1"/>
  <title>{$title}</title>
  <style>
    .preheader{display:none!important;visibility:hidden;opacity:0;color:transparent;height:0;width:0;max-height:0;max-width:0;overflow:hidden;mso-hide:all}
    a.btn:hover{filter:brightness(0.95)}
    @media (prefers-color-scheme: dark) {
      .card { background:#0b1220 !important; }
      .ink  { color:#e5e7eb !important; }
      .muted{ color:#9aa4b2 !important; }
      .foot { background:#0b1220 !important; color:#9aa4b2 !important; }
    }
  </style>
</head>
<body style="margin:0;background:#f6f8fb;-webkit-font-smoothing:antialiased;">
  <div class="preheader">{$prehead}</div>

  <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f6f8fb;">
    <tr>
      <td align="center" style="padding:26px 14px;">
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:680px;background:#ffffff;border-radius:16px;overflow:hidden;box-shadow:0 6px 24px rgba(15,23,42,.08);" class="card">
          <!-- Split header -->
          <tr>
            <td>
              <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
                <tr>
                  <!-- Left: logo, white -->
                  <td width="50%" align="left" style="background:#ffffff;padding:18px 20px;">
                    <img src="{$logoUrl}" alt="{$brand} logo" width="148" style="display:block;height:auto;border:0;outline:none;text-decoration:none;"/>
                  </td>
                  <!-- Right: brand panel with title -->
                  <td width="50%" align="right" style="background:{$brandHex};padding:18px 20px;">
                    <div style="font:700 16px/1.2 system-ui,Segoe UI,Roboto,Arial;color:#ffffff;">{$title}</div>
                  </td>
                </tr>
              </table>
            </td>
          </tr>

          <!-- Body -->
          <tr>
            <td style="padding:24px 22px;" class="ink">
              {$bodyHtml}
            </td>
          </tr>

          <!-- Footer with address -->
          <tr>
            <td style="background:#f8fafc;color:#64748b;padding:14px 22px;font:12px/1.6 system-ui,Segoe UI,Roboto,Arial" class="foot">
              <div>© {$year} {$brand}. All rights reserved.</div>
              <div style="margin-top:2px;">{$addr}</div>
            </td>
          </tr>
        </table>
        <div style="height:8px"></div>
      </td>
    </tr>
  </table>
</body>
</html>
HTML;
    }

    /** Primary CTA button */
    private static function button(string $label, string $href, string $brandHex): string
    {
        $label = self::esc($label);
        $href  = self::esc($href);
        $brand = self::esc($brandHex ?: '#228B22');
        return '<a class="btn" href="'.$href.'" style="display:inline-block;background:'.$brand.';color:#fff;text-decoration:none;padding:11px 16px;border-radius:10px;font:600 14px system-ui,Segoe UI,Roboto,Arial;">'.$label.'</a>';
    }

    /** Key/Value row for compact detail sections */
    private static function kv(string $k, string $v): string
    {
        $k = self::esc($k); $v = self::esc($v);
        return '<tr><td style="color:#64748b;font:600 13px system-ui,Segoe UI,Roboto,Arial;white-space:nowrap;padding:6px 0;">'.$k.'</td><td style="padding:6px 0 6px 12px;color:#0f172a;font:14px system-ui,Segoe UI,Roboto,Arial;" class="ink">'.$v.'</td></tr>';
    }

    /* ============================ Ready templates ============================ */

    /**
     * Organization welcome (tenant created)
     * d: ['brand','brandHex','orgName','ownerName','ownerEmail','loginUrl','trialEnd?','trialDays?','logoUrl?']
     */
    public static function tenantWelcome(array $d): string
    {
        $brand     = (string)($d['brand']     ?? 'KlinFlow');
        $brandHex  = (string)($d['brandHex']  ?? '#228B22');
        $orgName   = (string)($d['orgName']   ?? '');
        $ownerName = (string)($d['ownerName'] ?? '');
        $ownerEmail= (string)($d['ownerEmail']?? '');
        $loginUrl  = (string)($d['loginUrl']  ?? '/tenant/login');
        $trialEnd  = (string)($d['trialEnd']  ?? '');
        $trialDays = (string)($d['trialDays'] ?? '');
        $logoUrl   = (string)($d['logoUrl']   ?? '/assets/brand/logo.png');

        $rows = [];
        $rows[] = self::kv('Organization', $orgName);
        $rows[] = self::kv('Owner email', $ownerEmail);
        if ($trialEnd !== '') {
            $label = 'Trial ends';
            if ($trialDays !== '') $label .= " ({$trialDays} days left)";
            $rows[] = self::kv($label, $trialEnd);
        }

        $body = ''
          . '<p style="margin:0 0 10px;color:#0f172a;font:16px/1.45 system-ui,Segoe UI,Roboto,Arial" class="ink">'
          . 'Hi <strong>'.self::esc($ownerName).'</strong>,'
          . '</p>'
          . '<p style="margin:0 0 14px;color:#334155;font:14px/1.6 system-ui,Segoe UI,Roboto,Arial">'
          . 'Your organization is ready. You can sign in using the button below.'
          . '</p>'
          . '<table role="presentation" cellspacing="0" cellpadding="0" style="margin:8px 0 12px 0;">'.implode('', $rows).'</table>'
          . '<div style="margin-top:18px;">'.self::button('Go to Login', $loginUrl, $brandHex).'</div>'
          . '<p style="margin:14px 0 0;color:#64748b;font:12px system-ui,Segoe UI,Roboto,Arial" class="muted">'
          . 'For security, never share your password.'
          . '</p>';

        return self::shell($brand, $brandHex, 'Organization Ready', 'Your organization is ready to use.', $body, $logoUrl);
    }

    /**
     * Password reset (CP)
     * d: ['brand','brandHex','name','resetUrl','ttlMinutes'(? default 60),'logoUrl?']
     */
    public static function passwordReset(array $d): string
    {
        $brand      = (string)($d['brand'] ?? 'KlinFlow');
        $brandHex   = (string)($d['brandHex'] ?? '#228B22');
        $name       = (string)($d['name'] ?? '');
        $resetUrl   = (string)($d['resetUrl'] ?? '#');
        $ttlMinutes = (int)($d['ttlMinutes'] ?? 60);
        $logoUrl    = (string)($d['logoUrl'] ?? '/assets/brand/logo.png');

        $body = ''
          . '<p style="margin:0 0 10px;color:#0f172a;font:16px/1.45 system-ui,Segoe UI,Roboto,Arial" class="ink">Hi '
          . self::esc($name).',</p>'
          . '<p style="margin:0 0 14px;color:#334155;font:14px/1.6 system-ui,Segoe UI,Roboto,Arial">'
          . 'We received a request to reset your password. This link will expire in <strong>'.$ttlMinutes.' minutes</strong>.'
          . '</p>'
          . '<div style="margin-top:18px;">'.self::button('Choose a new password', $resetUrl, $brandHex).'</div>'
          . '<p style="margin:14px 0 0;color:#64748b;font:12px system-ui,Segoe UI,Roboto,Arial" class="muted">'
          . 'If you didn’t request this, you can safely ignore this message.'
          . '</p>';

        return self::shell($brand, $brandHex, 'Reset your password', 'Reset your KlinFlow password.', $body, $logoUrl);
    }

    /**
     * User invitation (tenant)
     * d: ['brand','brandHex','invitee','orgName','inviteUrl','logoUrl?']
     */
    public static function userInvite(array $d): string
    {
        $brand     = (string)($d['brand'] ?? 'KlinFlow');
        $brandHex  = (string)($d['brandHex'] ?? '#228B22');
        $invitee   = (string)($d['invitee'] ?? '');
        $orgName   = (string)($d['orgName'] ?? '');
        $inviteUrl = (string)($d['inviteUrl'] ?? '#');
        $logoUrl   = (string)($d['logoUrl'] ?? '/assets/brand/logo.png');

        $body = ''
          . '<p style="margin:0 0 10px;color:#0f172a;font:16px/1.45 system-ui,Segoe UI,Roboto,Arial" class="ink">Hi '
          . self::esc($invitee).',</p>'
          . '<p style="margin:0 0 14px;color:#334155;font:14px/1.6 system-ui,Segoe UI,Roboto,Arial">'
          . 'You’ve been invited to join <strong>'.self::esc($orgName).'</strong> on KlinFlow.'
          . '</p>'
          . '<div style="margin-top:18px;">'.self::button('Accept invitation', $inviteUrl, $brandHex).'</div>';

        return self::shell($brand, $brandHex, 'You’re invited', 'You have a new KlinFlow invitation.', $body, $logoUrl);
    }

    /**
     * Billing notice (simple)
     * d: ['brand','brandHex','orgName','amount','dueDate','invoiceUrl','logoUrl?']
     */
    public static function billingNotice(array $d): string
    {
        $brand      = (string)($d['brand'] ?? 'KlinFlow');
        $brandHex   = (string)($d['brandHex'] ?? '#228B22');
        $orgName    = (string)($d['orgName'] ?? '');
        $amount     = (string)($d['amount'] ?? '');
        $dueDate    = (string)($d['dueDate'] ?? '');
        $invoiceUrl = (string)($d['invoiceUrl'] ?? '#');
        $logoUrl    = (string)($d['logoUrl'] ?? '/assets/brand/logo.png');

        $rows = [];
        if ($orgName !== '') $rows[] = self::kv('Organization', $orgName);
        if ($amount  !== '') $rows[] = self::kv('Amount', $amount);
        if ($dueDate !== '') $rows[] = self::kv('Due date', $dueDate);

        $body = ''
          . '<p style="margin:0 0 12px;color:#334155;font:14px/1.6 system-ui,Segoe UI,Roboto,Arial">'
          . 'Here’s a quick summary of your billing.'
          . '</p>'
          . '<table role="presentation" cellspacing="0" cellpadding="0" style="margin:6px 0 12px 0;">'.implode('', $rows).'</table>'
          . '<div style="margin-top:16px;">'.self::button('View invoice', $invoiceUrl, $brandHex).'</div>';

        return self::shell($brand, $brandHex, 'Billing notice', 'A billing update is available.', $body, $logoUrl);
    }

    /**
     * Generic reminder
     * d: ['brand','brandHex','title','message','ctaLabel?','ctaUrl?','logoUrl?']
     */
    public static function genericReminder(array $d): string
    {
        $brand    = (string)($d['brand'] ?? 'KlinFlow');
        $brandHex = (string)($d['brandHex'] ?? '#228B22');
        $title    = (string)($d['title'] ?? 'Reminder');
        $message  = (string)($d['message'] ?? '');
        $ctaLabel = (string)($d['ctaLabel'] ?? '');
        $ctaUrl   = (string)($d['ctaUrl'] ?? '');
        $logoUrl  = (string)($d['logoUrl'] ?? '/assets/brand/logo.png');

        $body = '<p style="margin:0 0 12px;color:#334155;font:14px/1.6 system-ui,Segoe UI,Roboto,Arial">'
              . self::esc($message).'</p>';

        if ($ctaLabel !== '' && $ctaUrl !== '') {
            $body .= '<div style="margin-top:16px;">'.self::button($ctaLabel, $ctaUrl, $brandHex).'</div>';
        }

        return self::shell($brand, $brandHex, $title, $title.' — KlinFlow', $body, $logoUrl);
    }
}