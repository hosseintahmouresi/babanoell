<?php
declare(strict_types=1);

namespace Babanoell\Auth\Validation\Rules;

use Babanoell\Auth\Validation\ValidationError;

interface ValidationRuleInterface
{
    /**
     * @param array<string,mixed> $payload
     */
    public function validate(string $field, array $payload): ?ValidationError;
}
