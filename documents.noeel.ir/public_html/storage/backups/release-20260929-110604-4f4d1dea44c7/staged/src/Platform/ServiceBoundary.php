<?php
declare(strict_types=1);
namespace Babanoell\Documents\Platform;
final class ServiceBoundary
{
    public const CONTRACT_VERSION='2026-09-29.phase2a';
    /** @return array<string,mixed> */
    public static function describe(): array
    {
        return [
            'service'=>'documents',
            'contract_version'=>self::CONTRACT_VERSION,
            'owns'=>['documents','document_versions','links','reviews','access_tokens','file_audit','storage'],
            'planned_services'=>['partners','finance','integrations'],
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
