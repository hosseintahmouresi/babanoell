<?php
declare(strict_types=1);

namespace Babanoell\Auth\Validation\Rules;

use Babanoell\Auth\Validation\ValidationError;

final class EmailRule implements ValidationRuleInterface
{
    public function validate(string $field, array $payload): ?ValidationError
    {
        if (!array_key_exists($field, $payload) || $payload[$field] === null || $payload[$field] === '') {
            return null;
        }

        if (!is_string($payload[$field])) {
            return null;
        }

        if (filter_var($payload[$field], FILTER_VALIDATE_EMAIL) === false) {
            return new ValidationError($field, 'invalid_email', 'This field must contain a valid email address.');
        }

        return null;
    }
}
