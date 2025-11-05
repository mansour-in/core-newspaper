<?php

declare(strict_types=1);

return [
    'dsn' => getenv('DB_DSN') ?: 'mysql:host=127.0.0.1;dbname=newspaper;charset=utf8mb4',
    'username' => getenv('DB_USERNAME') ?: 'newspaper',
    'password' => getenv('DB_PASSWORD') ?: 'secret',
];
