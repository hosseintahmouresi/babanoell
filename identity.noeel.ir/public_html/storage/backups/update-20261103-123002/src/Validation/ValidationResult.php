<?php
declare(strict_types=1);

namespace Babanoell\Auth\Validation;

final class ValidationResult
{
    /**
     * @param list<ValidationError> $errors
     */
    private function __construct(private readonly array $errors)
    {
    }

    public static function valid(): self
    {
        return new self([]);
    }

    /**
     * @param list<ValidationError> $errors
     */
    public static function invalid(array $errors): self
    {
        return new self(array_values($errors));
    }

    /**
     * @param list<ValidationResult> $results
     */
    public static function combine(array $results): self
    {
        $errors = [];

        foreach ($results as $result) {
            array_push($errors, ...$result->errors());
        }

        return new self($errors);
    }

    public function isValid(): bool
    {
        return $this->errors === [];
    }

    /**
     * @return list<ValidationError>
     */
    public function errors(): array
    {
        return $this->errors;
    }

    /**
     * @return list<array{field:string,code:string,message:string}>
     */
    public function toArray(): array
    {
        return array_map(
            static fn (ValidationError $error): array => $error->toArray(),
            $this->errors,
        );
    }
}
