<?php
declare(strict_types=1);

namespace Babanoell\Auth\Validation\Rules;

use Babanoell\Auth\Validation\ValidationError;

final class MaxLengthRule implements ValidationRuleInterface
{
    public function __construct(private readonly int $maxLength)
    {
    }

    public function validate(string $field, array $payload): ?ValidationError
    {
        if (!array_key_exists($field, $payload) || !is_string($payload[$field])) {
            return null;
        }

        if (strlen($payload[$field]) > $this->maxLength) {
            return new ValidationError($field, 'max_length', sprintf('This field must be at most %d characters.', $this->maxLength));
        }

        return null;
    }
}
