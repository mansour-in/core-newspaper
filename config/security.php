<?php

declare(strict_types=1);

return [
    'session' => [
        'name' => getenv('SESSION_NAME') ?: 'nr_session',
        'lifetime' => (int) (getenv('SESSION_LIFETIME') ?: 7200),
        'secure' => filter_var(getenv('SESSION_SECURE') ?: false, FILTER_VALIDATE_BOOL),
        'httponly' => filter_var(getenv('SESSION_HTTP_ONLY') ?: true, FILTER_VALIDATE_BOOL),
        'same_site' => getenv('SESSION_SAME_SITE') ?: 'Lax',
    ],
    'csrf' => [
        'ttl' => (int) (getenv('CSRF_TOKEN_TTL') ?: 7200),
        'token_name' => '_csrf',
    ],
    'login' => [
        'rate_limit' => (int) (getenv('LOGIN_RATE_LIMIT') ?: 5),
        'window' => (int) (getenv('LOGIN_RATE_WINDOW') ?: 600),
    ],
    'admin' => [
        'username' => getenv('ADMIN_USERNAME') ?: 'admin',
        'password_hash' => getenv('ADMIN_PASSWORD_HASH') ?: '',
    ],
    'logging' => [
        'path' => getenv('LOG_PATH') ?: __DIR__ . '/../storage/logs/app.log',
        'cron_path' => getenv('CRON_LOG_PATH') ?: __DIR__ . '/../storage/logs/cron.log',
    ],
];
