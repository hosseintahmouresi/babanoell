<?php
declare(strict_types=1);

namespace Babanoell\Auth\UseCases\Logout;

use Babanoell\Auth\Validation\Rules\BooleanRule;
use Babanoell\Auth\Validation\ValidationResult;
use Babanoell\Auth\Validation\ValidatorInterface;

final class LogoutRequestValidator implements ValidatorInterface
{
    public function validate(array $payload): ValidationResult
    {
        $error = (new BooleanRule())->validate('all_sessions', $payload);

        if ($error !== null) {
            return ValidationResult::invalid([$error]);
        }

        return ValidationResult::valid();
    }
}
