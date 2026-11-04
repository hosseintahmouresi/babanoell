<?php
declare(strict_types=1);

namespace Babanoell\Auth\Http;

final class JsonResponse extends Response
{
    /**
     * @param array<string,mixed> $payload
     * @param array<string,string> $headers
     */
    public function __construct(
        int $statusCode,
        private readonly array $payload,
        array $headers = [],
    ) {
        $headers = array_merge([
            'Content-Type' => 'application/json; charset=utf-8',
            'X-Content-Type-Options' => 'nosniff',
        ], $headers);

        parent::__construct(
            $statusCode,
            json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
            $headers,
        );
    }

    public static function error(int $statusCode, string $code, string $message): self
    {
        return new self($statusCode, [
            'error' => [
                'code' => $code,
                'message' => $message,
            ],
        ]);
    }

    /**
     * @return array<string,mixed>
     */
    public function payload(): array
    {
        return $this->payload;
    }
}
