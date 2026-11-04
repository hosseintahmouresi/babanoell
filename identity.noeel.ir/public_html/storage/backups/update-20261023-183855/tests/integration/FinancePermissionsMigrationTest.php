<?php
declare(strict_types=1);
use Babanoell\Auth\Tests\Assertions;use Babanoell\Auth\Tests\TestCase;
return new class extends TestCase{
 public function testFinancePermissionsAreSeededForPhase7():void{$root=dirname(__DIR__,2);$file=$root.'/database/migrations/202610010001_seed_finance_permissions.php';Assertions::true(is_file($file));$src=(string)file_get_contents($file);foreach(['finance.dashboard.read','finance.bank_settlement.manage','finance.merchant_settlement.manage','finance.financier_payment.manage','finance.cycles.manage','finance.receipts.manage','finance.reconciliation.manage','finance.migration.manage']as$p)Assertions::contains("'{$p}'",$src);Assertions::contains("'finance_expert'",$src);Assertions::contains("'bank_expert'",$src);}
};
