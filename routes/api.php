<?php

declare(strict_types=1);

use App\Helpers\Http;
use App\Services\HealthService;

/** @var App\Kernel $kernel */
$kernel->register('GET', '/admin/status.json', function () use ($kernel): void {
    $service = $kernel->make(HealthService::class);
    Http::json($service->status());
});
