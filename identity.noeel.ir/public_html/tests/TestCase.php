<?php
declare(strict_types=1);

namespace Babanoell\Auth\Tests;

use Throwable;

abstract class TestCase
{
    private string $suiteName = '';

    final public function setSuiteName(string $suiteName): void
    {
        $this->suiteName = $suiteName;
    }

    /**
     * @return array<int,array{name:string,passed:bool,error:string|null}>
     */
    final public function run(): array
    {
        $results = [];

        foreach (get_class_methods($this) as $method) {
            if (!str_starts_with($method, 'test')) {
                continue;
            }

            try {
                $this->{$method}();
                $results[] = [
                    'name' => $this->displayName($method),
                    'passed' => true,
                    'error' => null,
                ];
            } catch (Throwable $exception) {
                $results[] = [
                    'name' => $this->displayName($method),
                    'passed' => false,
                    'error' => $exception->getMessage(),
                ];
            }
        }

        return $results;
    }

    private function displayName(string $method): string
    {
        $suiteName = $this->suiteName !== '' ? $this->suiteName : static::class;

        return $suiteName . '::' . $method;
    }
}
