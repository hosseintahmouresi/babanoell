<?php
declare(strict_types=1);
namespace Babanoell\Auth\Http;
use Babanoell\Auth\Platform\RequestContext;

final class JsonResponse extends Response
{
    public function __construct(int $statusCode, private array $payload, array $headers = [])
    {
        $meta = is_array($this->payload['meta'] ?? null) ? $this->payload['meta'] : [];
        $this->payload['meta'] = array_merge(RequestContext::responseMeta(), $meta);
        $headers = array_merge([
            'Content-Type' => 'application/json; charset=utf-8',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'no-store',
            'X-Request-Id' => RequestContext::id(),
            'X-Noel-Service' => 'identity',
            'X-Noel-Api-Version' => 'v1',
        ], $headers);

        parent::__construct(
            $statusCode,
            json_encode($this->payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            $headers
        );
    }

    public static function ok(array $data = [], int $statusCode = 200, array $meta = []): self
    {
        return new self($statusCode, ['data' => $data, 'meta' => $meta]);
    }

    public static function error(int $statusCode, string $code, string $message, array $details = []): self
    {
        $payload = ['error' => ['code' => $code, 'message' => $message]];
        if ($details !== []) {
            $payload['error']['details'] = $details;
        }
        return new self($statusCode, $payload);
    }
    
    /**
     * @return array<string,mixed>
     */
    public function payload(): array
    {
        return $this->payload;
    }
}