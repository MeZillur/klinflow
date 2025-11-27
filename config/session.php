<?php
declare(strict_types=1);

return [
    'name'      => getenv('SESSION_NAME') ?: 'KLINFLOW_SESS',
    'lifetime'  => 120, // minutes
    'path'      => '/',
    'domain'    => '',

    'secure'    => filter_var(getenv('FORCE_HTTPS') ?: false, FILTER_VALIDATE_BOOLEAN),
    'http_only' => true,
    'same_site' => 'Lax',

    'save_path' => __DIR__ . '/../storage/sessions',
];