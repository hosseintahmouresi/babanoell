<?php
declare(strict_types=1);

namespace Babanoell\Auth\Http;

use InvalidArgumentException;

final class Request
{
    /**
     * @param array<string,string> $headers
     * @param array<string,mixed> $queryParams
     */
    public function __construct(
        private readonly string $method,
        private readonly string $path,
        private readonly array $headers = [],
        private readonly string $rawBody = '',
        private readonly array $queryParams = [],
    ) {
    }

    public static function fromGlobals(): self
    {
        $requestUri = $_SERVER['REQUEST_URI'] ?? '/';
        $path = parse_url($requestUri, PHP_URL_PATH);

        return new self(
            $_SERVER['REQUEST_METHOD'] ?? 'GET',
            is_string($path) && $path !== '' ? $path : '/',
            self::headersFromServer($_SERVER),
            file_get_contents('php://input') ?: '',
            $_GET,
        );
    }

    public function method(): string
    {
        return strtoupper($this->method);
    }

    public function path(): string
    {
        $path = '/' . ltrim($this->path, '/');

        if ($path !== '/') {
            $path = rtrim($path, '/');
        }

        return $path;
    }

    public function header(string $name): ?string
    {
        return $this->headers[strtolower($name)] ?? null;
    }

    /**
     * @return array<string,mixed>
     */
    public function queryParams(): array
    {
        return $this->queryParams;
    }

    public function rawBody(): string
    {
        return $this->rawBody;
    }

    /**
     * @return array<string,mixed>
     */
    public function json(): array
    {
        if (trim($this->rawBody) === '') {
            return [];
        }

        $decoded = json_decode($this->rawBody, true);

        if (!is_array($decoded)) {
            throw new InvalidArgumentException('Request body must contain a valid JSON object.');
        }

        return $decoded;
    }

    /**
     * @param array<string,mixed> $server
     * @return array<string,string>
     */
    private static function headersFromServer(array $server): array
    {
        $headers = [];

        // Some Apache/FastCGI setups expose a forwarded Authorization header
        // as REDIRECT_HTTP_AUTHORIZATION after rewrite processing.
        if (isset($server['REDIRECT_HTTP_AUTHORIZATION']) && is_string($server['REDIRECT_HTTP_AUTHORIZATION'])) {
            $headers['authorization'] = $server['REDIRECT_HTTP_AUTHORIZATION'];
        }

        foreach ($server as $key => $value) {
            if (!is_string($value)) {
                continue;
            }

            if ($key === 'CONTENT_TYPE' || $key === 'CONTENT_LENGTH') {
                $headers[strtolower(str_replace('_', '-', $key))] = $value;
                continue;
            }

            if (!str_starts_with($key, 'HTTP_')) {
                continue;
            }

            $headerName = strtolower(str_replace('_', '-', substr($key, 5)));
            $headers[$headerName] = $value;
        }

        return $headers;
    }
}
