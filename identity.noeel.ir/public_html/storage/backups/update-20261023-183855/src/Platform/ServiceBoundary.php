<?php
declare(strict_types=1);
namespace Babanoell\Auth\Platform;
final class ServiceBoundary
{
    public const CONTRACT_VERSION='2026-10-01.phase7';
    /** @return array<string,mixed> */
    public static function describe(): array
    {
        return [
            'service'=>'identity',
            'contract_version'=>self::CONTRACT_VERSION,
            'owns'=>['users','roles','permissions','sessions','organizations','branches','memberships','authorization_scopes','auth_audit'],
            'external_services'=>['partners'=>'active','finance'=>'active','integrations'=>'planned'],
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
