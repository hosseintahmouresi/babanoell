<?php
declare(strict_types=1);

use Babanoell\Auth\Tests\Assertions;
use Babanoell\Auth\Tests\TestCase;
use Babanoell\Auth\Validation\Rules\AllowedValueRule;
use Babanoell\Auth\Validation\Rules\BooleanRule;
use Babanoell\Auth\Validation\Rules\MaxLengthRule;
use Babanoell\Auth\Validation\Rules\MinLengthRule;
use Babanoell\Auth\Validation\Rules\RequiredRule;
use Babanoell\Auth\Validation\Rules\StringRule;
use Babanoell\Auth\Validation\ValidationResult;

return new class extends TestCase {
    public function testRequiredRuleRejectsMissingAndBlankValues(): void
    {
        $rule = new RequiredRule();

        Assertions::same('required', $rule->validate('identifier', [])->code());
        Assertions::same('required', $rule->validate('identifier', ['identifier' => '  '])->code());
    }

    public function testTypeAndLengthRulesDoNotExposeValues(): void
    {
        $stringError = (new StringRule())->validate('password', ['password' => ['raw' => 'secret']]);
        $lengthError = (new MaxLengthRule(4))->validate('identifier', ['identifier' => 'abcdef']);

        Assertions::same('invalid_type', $stringError->code());
        Assertions::same('max_length', $lengthError->code());
        Assertions::false(str_contains($stringError->message(), 'secret'));
        Assertions::false(str_contains($lengthError->message(), 'abcdef'));
    }

    public function testMinLengthAllowedValueAndBooleanRulesAreDeterministic(): void
    {
        Assertions::same('min_length', (new MinLengthRule(3))->validate('code', ['code' => 'ab'])->code());
        Assertions::same('not_allowed', (new AllowedValueRule(['active']))->validate('status', ['status' => 'inactive'])->code());
        Assertions::same('invalid_type', (new BooleanRule())->validate('all_sessions', ['all_sessions' => 'true'])->code());
    }

    public function testValidationResultCombinesErrors(): void
    {
        $combined = ValidationResult::combine([
            ValidationResult::invalid([(new RequiredRule())->validate('identifier', [])]),
            ValidationResult::invalid([(new RequiredRule())->validate('password', [])]),
        ]);

        Assertions::false($combined->isValid());
        Assertions::same(2, count($combined->errors()));
        Assertions::same('identifier', $combined->toArray()[0]['field']);
        Assertions::same('password', $combined->toArray()[1]['field']);
    }
};
