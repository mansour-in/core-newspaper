<?php

declare(strict_types=1);

use App\Controllers\Admin\AuthController;
use App\Controllers\Admin\DashboardController;
use App\Controllers\PublicController;

/** @var App\Kernel $kernel */
$kernel->register('GET', '/', PublicController::class . '@index');
$kernel->register('GET', '/admin/login', AuthController::class . '@showLogin');
$kernel->register('POST', '/admin/login', AuthController::class . '@login', ['csrf']);
$kernel->register('POST', '/admin/logout', AuthController::class . '@logout', ['auth', 'csrf']);
$kernel->register('GET', '/admin', DashboardController::class . '@index', ['auth']);
$kernel->register('POST', '/admin/newspapers/{slug}/increment', DashboardController::class . '@increment', ['auth', 'csrf']);
$kernel->register('POST', '/admin/newspapers/{slug}/decrement', DashboardController::class . '@decrement', ['auth', 'csrf']);
$kernel->register('POST', '/admin/newspapers/{slug}/set', DashboardController::class . '@set', ['auth', 'csrf']);
