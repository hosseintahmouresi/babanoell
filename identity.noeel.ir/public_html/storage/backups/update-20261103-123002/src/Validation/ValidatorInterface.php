<?php
declare(strict_types=1);

namespace Babanoell\Auth\Validation;

interface ValidatorInterface
{
    /**
     * @param array<string,mixed> $payload
     */
    public function validate(array $payload): ValidationResult;
}
