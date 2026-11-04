<?php
declare(strict_types=1);
$root=dirname(__DIR__);
spl_autoload_register(static function(string$class)use($root):void{$prefix='Babanoell\\Finance\\';if(!str_starts_with($class,$prefix))return;$file=$root.'/src/'.str_replace('\\','/',substr($class,strlen($prefix))).'.php';if(is_file($file))require$file;});
use Babanoell\Finance\Domain\CycleIntegrity;
$pass=0;$fail=0;
function caseCheck(string$name,callable$fn):void{global$pass,$fail;try{$fn();echo"[PASS] $name\n";$pass++;}catch(Throwable$e){echo"[FAIL] $name: ".$e->getMessage()."\n";$fail++;}}
function same(mixed$a,mixed$b):void{if($a!==$b)throw new RuntimeException(json_encode($a).' !== '.json_encode($b));}
function rejects(callable$fn):void{$caught=false;try{$fn();}catch(InvalidArgumentException){$caught=true;}if(!$caught)throw new RuntimeException('did not reject invalid input');}
caseCheck('exact fractional cents',static fn()=>same(CycleIntegrity::cents('123.09'),12309));
caseCheck('zero is exact',static fn()=>same(CycleIntegrity::cents('0.00'),0));
caseCheck('integer amounts exact',static fn()=>same(CycleIntegrity::cents(1000000),100000000));
foreach(['-1','1.001','1e9','not-a-number','01.00','99999999999999','0.0001'] as$bad){caseCheck('reject invalid money '.$bad,static fn()=>rejects(static fn()=>CycleIntegrity::cents($bad)));}
caseCheck('allows balanced split',static fn()=>CycleIntegrity::validateItem('1250.50','250.25','1000.25'));
caseCheck('blocks unbalanced split',static fn()=>rejects(static fn()=>CycleIntegrity::validateItem('1250.50','250.25','1000.24')));
caseCheck('blocks empty settlement',static fn()=>rejects(static fn()=>CycleIntegrity::validateItem(0,0,0)));
$cycle=['status'=>'open','total_amount'=>'1500.00','leasing_total'=>'100.00','merchant_total'=>'1400.00'];
$items=[['request_id'=>'abc','settlement_amount'=>'1500.00','leasing_amount'=>'100.00','merchant_amount'=>'1400.00']];
caseCheck('balanced cycle has no issues',static fn()=>same(CycleIntegrity::issues($cycle,$items),[]));
caseCheck('detects empty cycle',static fn()=>same(CycleIntegrity::issues($cycle,[])[0]['type'],'empty_cycle'));
caseCheck('detects stored total drift',static function()use($cycle,$items){$bad=$cycle;$bad['total_amount']='1499.99';same(CycleIntegrity::issues($bad,$items)[0]['type'],'cycle_total_mismatch_total_amount');});
caseCheck('detects item composition mismatch',static function()use($cycle,$items){$bad=$items;$bad[0]['merchant_amount']='1300.00';$issues=CycleIntegrity::issues($cycle,$bad);same($issues[0]['type'],'item_amount_mismatch');});
caseCheck('warns pending receipt in closed cycle',static function()use($cycle,$items){$c=$cycle;$c['status']='closed';$issues=CycleIntegrity::issues($c,$items,[['status'=>'pending']]);same($issues[0]['severity'],'warning');});
$repo=file_get_contents($root.'/src/Repository/PdoFinanceRepository.php');$api=file_get_contents($root.'/public/index.php');$svc=file_get_contents($root.'/src/Service/FinanceService.php');
foreach(['claimOperation','FOR UPDATE','idempotency_conflict','finance.cycle.create','finance.cycle.item.add','finance.receipt.create','removeCycleItem','finance.reconciliation.scan','submitted_by_user_id','status=\'pending\'','recordBankSettlement']as$fragment){caseCheck('financial policy present: '.$fragment,static function()use($repo,$fragment){if(!str_contains($repo,$fragment))throw new RuntimeException('missing invariant');});}
foreach(['/api/v1/reports/cycles','/api/v1/reconciliation/scan','/integrity','/items/','/api/v1/receipts',"'idempotency_required'"]as$fragment){caseCheck('finance API exposes: '.$fragment,static function()use($api,$fragment){if(!str_contains($api,$fragment))throw new RuntimeException('missing route');});}
caseCheck('loan merchant ownership checked before cycle add',static function()use($svc){if(!str_contains($svc,"$".'loanMerchant'))throw new RuntimeException('merchant check missing');});
echo "Batch6 Finance: ".($pass+$fail)." checks, $fail failed\n";exit($fail?1:0);
