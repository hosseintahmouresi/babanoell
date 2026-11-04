<?php
declare(strict_types=1);

namespace Babanoell\Auth\Http;

final class ApiError
{
    /**
     * @param list<array{field:string,code:string,message:string}> $details
     */
    public function __construct(
        private readonly int $statusCode,
        private readonly string $code,
        private readonly string $message,
        private readonly array $details = [],
    ) {
    }

    public function statusCode(): int
    {
        return $this->statusCode;
    }

    /**
     * @return array{error:array{code:string,message:string,details?:list<array{field:string,code:string,message:string}>}}
     */
    public function toPayload(): array
    {
        $error = [
            'code' => $this->code,
            'message' => $this->message,
        ];

        if ($this->details !== []) {
            $error['details'] = $this->details;
        }

        return ['error' => $error];
    }
}
