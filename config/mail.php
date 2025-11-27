<?php
declare(strict_types=1);

return [
    // drivers: resend | smtp | log
    'driver'   => getenv('MAIL_DRIVER') ?: 'resend',

    // Resend
    'api_key'  => getenv('RESEND_API_KEY') ?: 're_xxxxxxxxx',

    // Default domain/sender identity
    'domain'   => getenv('MAIL_DOMAIN') ?: 'mail.klinflow.com',
    'from'     => [
        'name'  => getenv('MAIL_FROM_NAME')  ?: 'KlinFlow',
        'email' => getenv('MAIL_FROM_EMAIL') ?: 'no-reply@mail.klinflow.com',
    ],

    // Typed senders for auto-selection when you pass 'type' => ...
    'senders'  => [
        'welcome'    => getenv('MAIL_SENDER_WELCOME')    ?: 'welcome@mail.klinflow.com',
        'pass_reset' => getenv('MAIL_SENDER_PASSRESET')  ?: 'pass-reset@mail.klinflow.com',
        'invite'     => getenv('MAIL_SENDER_INVITE')     ?: 'invites@mail.klinflow.com',
        'billing'    => getenv('MAIL_SENDER_BILLING')    ?: 'billing@mail.klinflow.com',
        'reminder'   => getenv('MAIL_SENDER_REMINDER')   ?: 'reminder@mail.klinflow.com',
    ],

    // SMTP (only used if driver=smtp)
    'host'       => getenv('MAIL_HOST') ?: 'smtp.hostinger.com',
    'port'       => (int)(getenv('MAIL_PORT') ?: 587),
    'username'   => getenv('MAIL_USERNAME') ?: '',
    'password'   => getenv('MAIL_PASSWORD') ?: '',
    'encryption' => getenv('MAIL_ENCRYPTION') ?: 'tls',
];