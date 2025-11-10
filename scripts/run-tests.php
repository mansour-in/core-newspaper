#!/usr/bin/env php
<?php

declare(strict_types=1);

const PROJECT_ROOT = __DIR__ . '/..';

define('PHPUNIT_RUNNING', true);

require PROJECT_ROOT . '/vendor/autoload.php';
require PROJECT_ROOT . '/tests/framework/TestCase.php';

use PHPUnit\Framework\AssertionFailedError;
use PHPUnit\Framework\TestCase;

$testFiles = [];
$iterator = new \RecursiveIteratorIterator(
    new \RecursiveDirectoryIterator(PROJECT_ROOT . '/tests', \FilesystemIterator::SKIP_DOTS)
);

foreach ($iterator as $file) {
    if (!$file->isFile()) {
        continue;
    }

    if (!preg_match('/Test\.php$/', $file->getFilename())) {
        continue;
    }

    $testFiles[] = $file->getPathname();
}

sort($testFiles);

$declaredBefore = get_declared_classes();

foreach ($testFiles as $file) {
    require_once $file;
}

$allClasses = array_diff(get_declared_classes(), $declaredBefore);

$total = 0;
$failures = [];

foreach ($allClasses as $class) {
    if (!is_subclass_of($class, TestCase::class)) {
        continue;
    }

    $reflection = new \ReflectionClass($class);

    if ($reflection->isAbstract()) {
        continue;
    }

    $methods = array_filter(
        $reflection->getMethods(\ReflectionMethod::IS_PUBLIC),
        static fn(\ReflectionMethod $method): bool => str_starts_with($method->getName(), 'test')
    );

    usort(
        $methods,
        static fn(\ReflectionMethod $a, \ReflectionMethod $b): int => $a->getStartLine() <=> $b->getStartLine()
    );

    foreach ($methods as $method) {
        $total++;
        $instance = $reflection->newInstance();

        try {
            $instance->runTest($method->getName());
            echo '.';
        } catch (AssertionFailedError $failure) {
            $failures[] = sprintf(
                "%s::%s\n  %s",
                $class,
                $method->getName(),
                $failure->getMessage()
            );
            echo 'F';
        } catch (\Throwable $exception) {
            $failures[] = sprintf(
                "%s::%s\n  Unexpected exception: %s",
                $class,
                $method->getName(),
                $exception->getMessage()
            );
            echo 'E';
        }
    }
}

echo PHP_EOL . PHP_EOL;

if ($failures !== []) {
    foreach ($failures as $failure) {
        echo $failure, PHP_EOL, PHP_EOL;
    }

    printf('FAILURES! %d tests, %d failures.%s', $total, count($failures), PHP_EOL);
    exit(1);
}

printf('OK (%d tests)%s', $total, PHP_EOL);
