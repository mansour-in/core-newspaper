<?php

declare(strict_types=1);

use App\Helpers\Env;
use App\Kernel;

require_once __DIR__ . '/../vendor/autoload.php';

Env::load(__DIR__ . '/../.env');

$appConfig = require __DIR__ . '/../config/app.php';
$databaseConfig = require __DIR__ . '/../config/database.php';
$securityConfig = require __DIR__ . '/../config/security.php';

if (!is_dir(__DIR__ . '/../storage/logs')) {
    mkdir(__DIR__ . '/../storage/logs', 0755, true);
}

$logPath = $securityConfig['logging']['path'];
if (!is_string($logPath)) {
    $logPath = __DIR__ . '/../storage/logs/app.log';
}

if (!is_file($logPath)) {
    touch($logPath);
}

$timezone = $appConfig['timezone'] ?? 'Asia/Riyadh';
if (!date_default_timezone_set($timezone)) {
    date_default_timezone_set('Asia/Riyadh');
}

$sessionConfig = $securityConfig['session'];
session_name($sessionConfig['name']);
session_set_cookie_params([
    'lifetime' => $sessionConfig['lifetime'],
    'path' => '/',
    'domain' => '',
    'secure' => $sessionConfig['secure'],
    'httponly' => $sessionConfig['httponly'],
    'samesite' => $sessionConfig['same_site'],
]);
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$pdo = new PDO(
    (string) $databaseConfig['dsn'],
    (string) $databaseConfig['username'],
    (string) $databaseConfig['password'],
    [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]
);

$kernel = new Kernel($pdo, $appConfig, $securityConfig, $logPath);

require __DIR__ . '/../routes/web.php';
require __DIR__ . '/../routes/api.php';

return $kernel;
