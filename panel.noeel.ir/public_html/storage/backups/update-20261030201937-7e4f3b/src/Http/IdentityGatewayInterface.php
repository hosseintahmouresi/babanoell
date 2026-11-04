<?php
declare(strict_types=1);
namespace Babanoell\Panel\Http;

interface IdentityGatewayInterface
{
    public function health(): HttpResponse;
    public function login(string $identifier, string $password, ?string $clientIp, ?string $userAgent): HttpResponse;
    public function me(string $accessCredential, ?string $clientIp, ?string $userAgent): HttpResponse;
    public function refresh(string $refreshCredential, ?string $clientIp, ?string $userAgent): HttpResponse;
    public function logout(string $accessCredential, bool $allSessions, ?string $clientIp, ?string $userAgent): HttpResponse;
}
