<?php
declare(strict_types=1);

namespace Babanoell\Auth\UseCases\Me;

use Babanoell\Auth\Validation\Rules\MaxLengthRule;
use Babanoell\Auth\Validation\Rules\StringRule;
use Babanoell\Auth\Validation\ValidationResult;
use Babanoell\Auth\Validation\ValidatorInterface;

final class MeRequestValidator implements ValidatorInterface
{
    public function validate(array $payload): ValidationResult
    {
        $errors = [];

        foreach ([new StringRule(), new MaxLengthRule(128)] as $rule) {
            $error = $rule->validate('request_id', $payload);

            if ($error !== null) {
                $errors[] = $error;
            }
        }

        if ($errors !== []) {
            return ValidationResult::invalid($errors);
        }

        return ValidationResult::valid();
    }
}
