<?php
declare(strict_types=1);

namespace Babanoell\Auth\UseCases\Login;

use Babanoell\Auth\Validation\Rules\MaxLengthRule;
use Babanoell\Auth\Validation\Rules\MinLengthRule;
use Babanoell\Auth\Validation\Rules\RequiredRule;
use Babanoell\Auth\Validation\Rules\StringRule;
use Babanoell\Auth\Validation\Rules\ValidationRuleInterface;
use Babanoell\Auth\Validation\ValidationResult;
use Babanoell\Auth\Validation\ValidatorInterface;

final class LoginRequestValidator implements ValidatorInterface
{
    /**
     * @var array<string,list<ValidationRuleInterface>>
     */
    private array $rules;

    public function __construct()
    {
        $this->rules = [
            'identifier' => [
                new RequiredRule(),
                new StringRule(),
                new MinLengthRule(3),
                new MaxLengthRule(255),
            ],
            'password' => [
                new RequiredRule(),
                new StringRule(),
                new MinLengthRule(1),
                new MaxLengthRule(4096),
            ],
            'second_factor_code' => [
                new StringRule(),
                new MaxLengthRule(128),
            ],
        ];
    }

    public function validate(array $payload): ValidationResult
    {
        return ValidationResult::combine($this->validateRules($payload));
    }

    /**
     * @param array<string,mixed> $payload
     * @return list<ValidationResult>
     */
    private function validateRules(array $payload): array
    {
        $results = [];

        foreach ($this->rules as $field => $rules) {
            foreach ($rules as $rule) {
                $error = $rule->validate($field, $payload);

                if ($error !== null) {
                    $results[] = ValidationResult::invalid([$error]);
                    break;
                }
            }
        }

        return $results;
    }
}
