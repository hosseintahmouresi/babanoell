<?php
declare(strict_types=1);
$root=dirname(__DIR__);$ui=file_get_contents($root.'/src/Finance/FinanceModule.php');
$keys=['/finance/receipts','/finance/cycles/export','/finance/reconciliation/scan','/remove','_csrf','SpreadsheetSafeCell::escape','finance.receipts.manage','fputcsv','finance/cycles/'];
$pass=0;$fail=0;foreach($keys as$k){if(str_contains($ui,$k)){$pass++;echo"[PASS] $k\n";}else{$fail++;echo"[FAIL] missing $k\n";}}
require_once $root.'/src/Bank/SpreadsheetSafeCell.php';
use Babanoell\Panel\Bank\SpreadsheetSafeCell;
foreach(['=1+1',' +1',"\t@SUM(A1:A2)"]as$unsafe){$safe=SpreadsheetSafeCell::escape($unsafe);if(!str_starts_with($safe,"'")){$fail++;echo"[FAIL] formula not escaped\n";}else{$pass++;}}
echo "Batch6 Panel: ".($pass+$fail)." checks, $fail failed\n";exit($fail?1:0);
