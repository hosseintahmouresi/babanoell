<?php
declare(strict_types=1);

namespace Babanoell\Auth\Validation;

final class ValidationError
{
    public function __construct(
        private readonly string $field,
        private readonly string $code,
        private readonly string $message,
    ) {
    }

    public function field(): string
    {
        return $this->field;
    }

    public function code(): string
    {
        return $this->code;
    }

    public function message(): string
    {
        return $this->message;
    }

    /**
     * @return array{field:string,code:string,message:string}
     */
    public function toArray(): array
    {
        return [
            'field' => $this->field,
            'code' => $this->code,
            'message' => $this->message,
        ];
    }
}
