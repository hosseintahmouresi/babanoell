<?php
declare(strict_types=1);
namespace Babanoell\Panel\Platform;
final class ServiceBoundary
{
    public const CONTRACT_VERSION='2026-10-22.phase10';
    /** @return array<string,mixed> */
    public static function describe(): array
    {
        return [
            'service'=>'panel',
            'contract_version'=>self::CONTRACT_VERSION,
            'owns'=>['presentation','browser_session_facade','service_clients'],
            'external_services'=>['partners'=>'active','finance'=>'active','integrations'=>'active'],
            'rules'=>[
                'database_ownership'=>'single_service',
                'cross_service_access'=>'http_api_only',
                'api_version'=>'v1',
                'request_id'=>'X-Request-Id',
                'idempotency'=>'Idempotency-Key',
                'service_identity'=>'X-Noel-Service + optional HMAC v1',
            ],
        ];
    }
}
