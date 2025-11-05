<?php

declare(strict_types=1);


require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../database/seeders/NewspaperSeeder.php';

$kernel = require __DIR__ . '/../bootstrap/app.php';
NewspaperSeeder::run($kernel->getPdo());

echo "Seeded newspapers." . PHP_EOL;
