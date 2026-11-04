<?php
declare(strict_types=1);

namespace Babanoell\Auth\Tests;

use Throwable;

final class Assertions
{
    public static function true(bool $condition, string $message = 'Expected condition to be true.'): void
    {
        if (!$condition) {
            throw new TestFailure($message);
        }
    }

    public static function false(bool $condition, string $message = 'Expected condition to be false.'): void
    {
        if ($condition) {
            throw new TestFailure($message);
        }
    }

    public static function same(mixed $expected, mixed $actual, string $message = 'Values are not the same.'): void
    {
        if ($expected !== $actual) {
            throw new TestFailure($message);
        }
    }

    public static function notSame(mixed $unexpected, mixed $actual, string $message = 'Values should not be the same.'): void
    {
        if ($unexpected === $actual) {
            throw new TestFailure($message);
        }
    }

    public static function matches(string $pattern, string $actual, string $message = 'Value did not match pattern.'): void
    {
        if (preg_match($pattern, $actual) !== 1) {
            throw new TestFailure($message);
        }
    }

    public static function contains(string $needle, string $haystack, string $message = 'Expected text was not found.'): void
    {
        if (!str_contains($haystack, $needle)) {
            throw new TestFailure($message);
        }
    }

    /**
     * @param class-string<Throwable> $exceptionClass
     */
    public static function throws(string $exceptionClass, callable $callback, string $message = 'Expected exception was not thrown.'): void
    {
        try {
            $callback();
        } catch (Throwable $exception) {
            if ($exception instanceof $exceptionClass) {
                return;
            }

            throw new TestFailure($message . ' Got ' . $exception::class . '.');
        }

        throw new TestFailure($message);
    }
}

final class TestFailure extends \RuntimeException
{
}
