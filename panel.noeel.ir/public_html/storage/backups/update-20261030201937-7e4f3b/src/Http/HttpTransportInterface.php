<?php
declare(strict_types=1);
namespace Babanoell\Panel\Http;

interface HttpTransportInterface
{
    /** @param array<string,string> $headers */
    public function request(string $method, string $url, array $headers = [], ?string $body = null, int $timeoutSeconds = 6): HttpResponse;
}
