<?php

declare(strict_types=1);

$kernel = require __DIR__ . '/../bootstrap/app.php';

try {
    $kernel->handle($_SERVER['REQUEST_URI'] ?? '/', $_SERVER['REQUEST_METHOD'] ?? 'GET');
} catch (Throwable $exception) {
    http_response_code(500);
    echo 'Application error.';
}
