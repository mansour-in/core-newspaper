<?php

declare(strict_types=1);

namespace PHPUnit\Framework;

use RuntimeException;
use Throwable;

final class AssertionFailedError extends RuntimeException
{
}

abstract class TestCase
{
    /** @var list<string> */
    private array $cleanupCallbacks = [];

    protected function setUp(): void
    {
        // Intentionally left blank for child classes to override.
    }

    protected function tearDown(): void
    {
        while ($callback = array_pop($this->cleanupCallbacks)) {
            $callback();
        }
    }

    protected function addTearDown(callable $callback): void
    {
        $this->cleanupCallbacks[] = static function () use ($callback): void {
            $callback();
        };
    }

    public function runTest(string $method): void
    {
        $attemptFile = dirname(__DIR__, 2) . '/storage/cache/login_attempts.json';
        if (is_file($attemptFile)) {
            unlink($attemptFile);
        }
        unset($_SERVER['__redirect_url'], $_SERVER['__redirect_status'], $_SERVER['__json_payload'], $_SERVER['__json_status']);

        $this->setUp();

        try {
            $this->{$method}();
        } catch (Throwable $exception) {
            $this->tearDown();
            throw $exception;
        }

        $this->tearDown();
    }

    protected static function fail(string $message = 'Failed asserting that condition holds.'): void
    {
        throw new AssertionFailedError($message);
    }

    /**
     * @param mixed $expected
     * @param mixed $actual
     */
    protected static function assertSame($expected, $actual, string $message = ''): void
    {
        if ($expected !== $actual) {
            $prefix = $message !== '' ? $message . '\n' : '';
            $expectedString = var_export($expected, true);
            $actualString = var_export($actual, true);
            self::fail(sprintf(
                "%sFailed asserting that %s is identical to %s.",
                $prefix,
                $actualString,
                $expectedString
            ));
        }
    }

    /**
     * @param mixed $actual
     */
    protected static function assertNotNull($actual, string $message = ''): void
    {
        if ($actual === null) {
            self::fail($message !== '' ? $message : 'Failed asserting that value is not null.');
        }
    }

    /**
     * @param mixed $actual
     */
    protected static function assertTrue($actual, string $message = ''): void
    {
        if ($actual !== true) {
            self::fail($message !== '' ? $message : 'Failed asserting that value is true.');
        }
    }

    /**
     * @param mixed $actual
     */
    protected static function assertFalse($actual, string $message = ''): void
    {
        if ($actual !== false) {
            self::fail($message !== '' ? $message : 'Failed asserting that value is false.');
        }
    }

    protected static function assertStringContainsString(string $needle, string $haystack, string $message = ''): void
    {
        if (!str_contains($haystack, $needle)) {
            $prefix = $message !== '' ? $message . '\n' : '';
            self::fail(sprintf(
                "%sFailed asserting that '%s' contains '%s'.",
                $prefix,
                $haystack,
                $needle
            ));
        }
    }
}
