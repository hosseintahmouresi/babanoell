<?php
declare(strict_types=1);

namespace Babanoell\Auth\Http;

class Response
{
    /**
     * @param array<string,string> $headers
     */
    public function __construct(
        private readonly int $statusCode,
        private readonly string $body,
        private readonly array $headers = [],
    ) {
    }

    public function statusCode(): int
    {
        return $this->statusCode;
    }

    public function body(): string
    {
        return $this->body;
    }

    /**
     * @return array<string,string>
     */
    public function headers(): array
    {
        return $this->headers;
    }

    public function withHeader(string $name, string $value): self
    {
        $headers = $this->headers;
        $headers[$name] = $value;

        return new self($this->statusCode, $this->body, $headers);
    }

    /**
     * @param array<string,string> $headers
     */
    public function withHeaders(array $headers): self
    {
        $response = $this;

        foreach ($headers as $name => $value) {
            $response = $response->withHeader($name, $value);
        }

        return $response;
    }

    public function send(): never
    {
        http_response_code($this->statusCode);

        foreach ($this->headers as $name => $value) {
            header($name . ': ' . $value);
        }

        echo $this->body;
        exit;
    }
}
