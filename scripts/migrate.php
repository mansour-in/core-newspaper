<?php

declare(strict_types=1);

$kernel = require __DIR__ . '/../bootstrap/app.php';
$pdo = $kernel->getPdo();

$migrations = glob(__DIR__ . '/../database/migrations/*.php');
sort($migrations);

foreach ($migrations as $migration) {
    $sql = require $migration;
    $pdo->exec($sql);
    echo "Migrated: {$migration}" . PHP_EOL;
}
