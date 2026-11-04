<?php
declare(strict_types=1);

namespace Babanoell\Auth\UseCases\Refresh;

use Babanoell\Auth\Validation\Rules\MaxLengthRule;
use Babanoell\Auth\Validation\Rules\MinLengthRule;
use Babanoell\Auth\Validation\Rules\RequiredRule;
use Babanoell\Auth\Validation\Rules\StringRule;
use Babanoell\Auth\Validation\ValidationResult;
use Babanoell\Auth\Validation\ValidatorInterface;

final class RefreshRequestValidator implements ValidatorInterface
{
    public function validate(array $payload): ValidationResult
    {
        $errors = [];

        foreach ([
            new RequiredRule(),
            new StringRule(),
            new MinLengthRule(1),
            new MaxLengthRule(4096),
        ] as $rule) {
            $error = $rule->validate('refresh_token', $payload);

            if ($error !== null) {
                $errors[] = $error;
                break;
            }
        }

        if ($errors !== []) {
            return ValidationResult::invalid($errors);
        }

        return ValidationResult::valid();
    }
}
