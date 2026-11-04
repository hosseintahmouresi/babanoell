<?php
declare(strict_types=1);

namespace Babanoell\Auth\Validation\Rules;

use Babanoell\Auth\Validation\ValidationError;

final class RequiredRule implements ValidationRuleInterface
{
    public function validate(string $field, array $payload): ?ValidationError
    {
        if (!array_key_exists($field, $payload)) {
            return new ValidationError($field, 'required', 'This field is required.');
        }

        $value = $payload[$field];

        if ($value === null || (is_string($value) && trim($value) === '')) {
            return new ValidationError($field, 'required', 'This field is required.');
        }

        return null;
    }
}
