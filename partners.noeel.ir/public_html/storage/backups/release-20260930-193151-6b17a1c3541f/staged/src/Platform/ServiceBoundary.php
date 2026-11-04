<?php
declare(strict_types=1);
namespace Babanoell\Partners\Platform;
final class ServiceBoundary
{
 public const CONTRACT_VERSION='2026-09-29.phase3';
 public static function describe():array{return['service'=>'partners','contract_version'=>self::CONTRACT_VERSION,'owns'=>['merchants','merchant_memberships','merchant_plan_permissions','cooperation_requests','cooperation_documents','marketplace_orchestration','merchant_invitations','partner_events'],'depends_on'=>['identity','loans','documents'],'forbidden'=>['loan_calculations','loan_status_machine','document_binary_storage','financial_settlement_ledger','provider_credentials'],'rules'=>['database_ownership'=>'single_service','cross_service_access'=>'http_api_only','api_version'=>'v1','request_id'=>'X-Request-Id','idempotency'=>'Idempotency-Key','service_identity'=>'X-Noel-Service + optional HMAC v1']];}
}
