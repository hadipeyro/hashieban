<?php

declare(strict_types=1);

namespace Hashieban\Tests;

use RuntimeException;
use Throwable;

abstract class TestCase
{
    private int $assertions = 0;

    final public function run(): array
    {
        $methods = array_filter(
            get_class_methods($this),
            static function (string $method): bool {
                return strpos($method, 'test') === 0;
            }
        );

        $passed = 0;
        $failures = array();

        foreach ($methods as $method) {
            try {
                $this->{$method}();
                $passed++;
            } catch (Throwable $exception) {
                $failures[] = array(
                    'test' => static::class . '::' . $method,
                    'message' => $exception->getMessage(),
                );
            }
        }

        return array(
            'tests' => count($methods),
            'passed' => $passed,
            'failed' => count($failures),
            'assertions' => $this->assertions,
            'failures' => $failures,
        );
    }

    final protected function assertSame($expected, $actual, string $message = ''): void
    {
        $this->assertions++;

        if ($expected !== $actual) {
            throw new RuntimeException(
                $message !== ''
                    ? $message
                    : 'Expected ' . var_export($expected, true)
                        . ', got ' . var_export($actual, true) . '.'
            );
        }
    }

    final protected function assertTrue(bool $condition, string $message = ''): void
    {
        $this->assertions++;

        if (! $condition) {
            throw new RuntimeException($message !== '' ? $message : 'Expected true.');
        }
    }

    final protected function assertFalse(bool $condition, string $message = ''): void
    {
        $this->assertions++;

        if ($condition) {
            throw new RuntimeException($message !== '' ? $message : 'Expected false.');
        }
    }

    final protected function assertNull($actual, string $message = ''): void
    {
        $this->assertions++;

        if ($actual !== null) {
            throw new RuntimeException($message !== '' ? $message : 'Expected null.');
        }
    }

    final protected function assertFloatEquals(float $expected, ?float $actual, float $delta = 0.0001): void
    {
        $this->assertions++;

        if ($actual === null || abs($expected - $actual) > $delta) {
            throw new RuntimeException(
                'Expected approximately ' . $expected . ', got ' . var_export($actual, true) . '.'
            );
        }
    }

    final protected function expectException(string $className, callable $callback): void
    {
        $this->assertions++;

        try {
            $callback();
        } catch (Throwable $exception) {
            if ($exception instanceof $className) {
                return;
            }

            throw new RuntimeException(
                'Expected exception ' . $className . ', got ' . get_class($exception) . '.'
            );
        }

        throw new RuntimeException('Expected exception ' . $className . ' was not thrown.');
    }
}
