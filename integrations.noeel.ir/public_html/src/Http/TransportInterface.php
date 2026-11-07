<?php
declare(strict_types=1);
namespace Babanoell\Integrations\Http;

interface TransportInterface
{
    public function request(string $method, string $url, ?array $payload = null, array $headers = [],
        int $timeout = 18, string $encoding = 'json', array $hosts = []): array;
}
