<?php
declare(strict_types=1);

namespace Babanoell\Auth\Validation\Rules;

use Babanoell\Auth\Validation\ValidationError;

final class AllowedValueRule implements ValidationRuleInterface
{
    /**
     * @param list<string> $allowedValues
     */
    public function __construct(private readonly array $allowedValues)
    {
    }

    public function validate(string $field, array $payload): ?ValidationError
    {
        if (!array_key_exists($field, $payload) || $payload[$field] === null || $payload[$field] === '') {
            return null;
        }

        if (!is_string($payload[$field]) || !in_array($payload[$field], $this->allowedValues, true)) {
            return new ValidationError($field, 'not_allowed', 'This field contains an unsupported value.');
        }

        return null;
    }
}
