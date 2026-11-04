<?php
declare(strict_types=1);
namespace Babanoell\Finance\Platform;
final class ServiceBoundary
{
 public const CONTRACT_VERSION='2026-10-01.phase7';
 public static function describe():array{return[
  'service'=>'finance','contract_version'=>self::CONTRACT_VERSION,
  'owns'=>['bank_settlements','merchant_settlements','settlement_cycles','financial_receipts','financier_payments','financial_reconciliation','finance_audit'],
  'depends_on'=>['identity','loans','partners','documents','integrations'],
  'forbidden'=>['loan_status_machine','loan_calculation','merchant_master_data','document_binary_storage','identity_credentials','provider_specific_payment_http'],
  'rules'=>['database_ownership'=>'single_service','cross_service_access'=>'http_api_only','api_version'=>'v1','request_id'=>'X-Request-Id','idempotency'=>'Idempotency-Key','source_of_truth'=>'finance'],
 ];}
}
