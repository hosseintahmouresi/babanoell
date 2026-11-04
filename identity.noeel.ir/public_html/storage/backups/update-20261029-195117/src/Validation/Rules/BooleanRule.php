<?php
declare(strict_types=1);

namespace Babanoell\Auth\Validation\Rules;

use Babanoell\Auth\Validation\ValidationError;

final class BooleanRule implements ValidationRuleInterface
{
    public function validate(string $field, array $payload): ?ValidationError
    {
        if (!array_key_exists($field, $payload) || $payload[$field] === null) {
            return null;
        }

        if (!is_bool($payload[$field])) {
            return new ValidationError($field, 'invalid_type', 'This field must be a boolean.');
        }

        return null;
    }
}
