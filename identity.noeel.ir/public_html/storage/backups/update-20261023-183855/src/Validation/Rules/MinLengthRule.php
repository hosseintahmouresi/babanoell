<?php
declare(strict_types=1);

namespace Babanoell\Auth\Validation\Rules;

use Babanoell\Auth\Validation\ValidationError;

final class MinLengthRule implements ValidationRuleInterface
{
    public function __construct(private readonly int $minLength)
    {
    }

    public function validate(string $field, array $payload): ?ValidationError
    {
        if (!array_key_exists($field, $payload) || !is_string($payload[$field])) {
            return null;
        }

        if (strlen($payload[$field]) < $this->minLength) {
            return new ValidationError($field, 'min_length', sprintf('This field must be at least %d characters.', $this->minLength));
        }

        return null;
    }
}
