<?php

declare(strict_types=1);

$kernel = require __DIR__ . '/../../bootstrap/app.php';

/** @var App\Controllers\PublicController $controller */
$controller = $kernel->make(App\Controllers\PublicController::class);
$controller->redirect('ring');
