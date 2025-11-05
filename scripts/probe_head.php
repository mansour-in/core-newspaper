<?php

declare(strict_types=1);

use App\Models\Newspaper;
use App\Services\RedirectBuilder;
use DateTimeImmutable;
use DateTimeZone;

$kernel = require __DIR__ . '/../bootstrap/app.php';
$pdo = $kernel->getPdo();

/** @var RedirectBuilder $builder */
$builder = $kernel->make(App\Services\RedirectBuilder::class);

$timezone = new DateTimeZone('Asia/Riyadh');
$now = new DateTimeImmutable('now', $timezone);

foreach (Newspaper::all($pdo) as $paper) {
    $url = $builder->buildFor($paper, $now);
    printf("%s => %s\n", $paper->slug(), $url);
}
