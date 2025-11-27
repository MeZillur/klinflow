<?php
declare(strict_types=1);

return [
    // ── Primary driver: use 'resend' (recommended), 'smtp', or 'log'
    'driver'   => getenv('MAIL_DRIVER') ?: 'resend',

    // ── Resend (required when driver=resend)
    'api_key'  => getenv('RESEND_API_KEY') ?: '',   // put re_xxx in .env

    // ── Default domain/sender (DKIM verified domain)
    'domain'   => getenv('MAIL_DOMAIN') ?: 'mail.klinflow.com',
    'from'     => [
        'name'  => getenv('MAIL_FROM_NAME')  ?: 'KlinFlow',
        'email' => getenv('MAIL_FROM_EMAIL') ?: 'no-reply@mail.klinflow.com',
    ],

    // ── Typed senders you can select via `type => 'welcome' | 'pass_reset' | ...`
    'senders'  => [
        'welcome'    => getenv('MAIL_SENDER_WELCOME')    ?: 'welcome@mail.klinflow.com',
        'pass_reset' => getenv('MAIL_SENDER_PASSRESET')  ?: 'pass-reset@mail.klinflow.com',
        'invite'     => getenv('MAIL_SENDER_INVITE')     ?: 'invite@mail.klinflow.com',
        'billing'    => getenv('MAIL_SENDER_BILLING')    ?: 'billing@mail.klinflow.com',
        'reminder'   => getenv('MAIL_SENDER_REMINDER')   ?: 'reminder@mail.klinflow.com',
    ],

    // ── SMTP (used only if driver='smtp'); left blank to avoid accidental use
    'host'       => getenv('MAIL_HOST') ?: '',
    'port'       => (int)(getenv('MAIL_PORT') ?: 0),
    'username'   => getenv('MAIL_USERNAME') ?: '',
    'password'   => getenv('MAIL_PASSWORD') ?: '',
    'encryption' => getenv('MAIL_ENCRYPTION') ?: '',

    // Optional: force no implicit fallbacks
    'strict'     => true,  // your Mailer can read this and refuse to send if misconfigured
];