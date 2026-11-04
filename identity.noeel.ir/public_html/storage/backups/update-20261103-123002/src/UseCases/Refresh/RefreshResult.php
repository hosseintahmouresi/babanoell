<?php
declare(strict_types=1);

namespace Babanoell\Auth\UseCases\Refresh;

final class RefreshResult
{
    /**
     * @param array<string,mixed> $data
     */
    private function __construct(
        private readonly bool $active,
        private readonly int $statusCode,
        private readonly string $errorCode,
        private readonly string $message,
        private readonly array $data = [],
    ) {
    }

    public static function inactive(): self
    {
        return new self(false, 501, 'not_implemented', 'This endpoint is not active yet.');
    }

    public static function invalidCredentials(): self
    {
        return new self(false, 401, 'invalid_credentials', 'Invalid credentials.');
    }

    public static function unavailable(): self
    {
        return new self(false, 503, 'auth_unavailable', 'Authentication is temporarily unavailable.');
    }

    /**
     * @param array<string,mixed> $data
     */
    public static function refreshed(array $data): self
    {
        return new self(true, 200, '', '', $data);
    }

    public function active(): bool
    {
        return $this->active;
    }

    public function statusCode(): int
    {
        return $this->statusCode;
    }

    public function errorCode(): string
    {
        return $this->errorCode;
    }

    public function message(): string
    {
        return $this->message;
    }

    /**
     * @return array<string,mixed>
     */
    public function data(): array
    {
        return $this->data;
    }

    /**
     * @return array{data:array<string,mixed>}|array{error:array{code:string,message:string}}
     */
    public function toPayload(): array
    {
        if ($this->active) {
            return ['data' => $this->data];
        }

        return $this->toErrorPayload();
    }

    /**
     * @return array{error:array{code:string,message:string}}
     */
    public function toErrorPayload(): array
    {
        return [
            'error' => [
                'code' => $this->errorCode,
                'message' => $this->message,
            ],
        ];
    }
}
