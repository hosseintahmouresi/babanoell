<?php
declare(strict_types=1);
$root=dirname(__DIR__);
require $root.'/src/Bank/SpreadsheetSafeCell.php';
use Babanoell\Panel\Bank\SpreadsheetSafeCell;
$tests=[
 'csv_sanitizes_excel_formulas'=>static fn():bool=>(SpreadsheetSafeCell::escape('=HYPERLINK("x")')==="'=HYPERLINK(\"x\")") && SpreadsheetSafeCell::escape('   +SUM(1,1)')==="'   +SUM(1,1)" && SpreadsheetSafeCell::escape("\t@hack")==="'\t@hack",
 'csv_preserves_plain_persian'=>static fn():bool=>SpreadsheetSafeCell::escape('علی رضایی')==='علی رضایی' && SpreadsheetSafeCell::escape('09123456789')==='09123456789',
 'panel_exposes_provider_actions'=>static function()use($root):bool{$s=file_get_contents($root.'/src/Bank/BankModule.php');foreach(['providerAction(',"/verify/([a-f0-9]{26})/(dispatch|otp|refresh)",'Csrf::valid','در انتظار است'] as $term)if(!str_contains($s,$term))return false;return true;},
 'panel_bank_csv_columns'=>static function()use($root):bool{$s=file_get_contents($root.'/src/Bank/BankModule.php');return str_contains($s,"'تعداد اقساط','تاریخ اعطا'")&&str_contains($s,"SpreadsheetSafeCell::class,'escape'");},
 'bank_provider_controls_prevent_duplicate_otp_form'=>static function()use($root):bool{
     require_once $root.'/src/Bank/BankModule.php';
     require_once $root.'/src/Security/Csrf.php';
     require_once $root.'/src/View/Renderer.php';
     $obj=(new ReflectionClass(\Babanoell\Panel\Bank\BankModule::class))->newInstanceWithoutConstructor();
     $method=new ReflectionMethod($obj,'providerControls');
     $base=['id'=>str_repeat('a',26),'verification_type'=>'credit_report','status'=>'pending','provider_reference'=>'ABCDEF123456'];
     $initial=$method->invoke($obj,str_repeat('b',26),$base);
     $done=$method->invoke($obj,str_repeat('b',26),array_merge($base,['metadata'=>['otp_verified'=>true]]));
     return str_contains($initial,'name="otp"') && !str_contains($done,'name="otp"') && str_contains($done,'refresh');
 },
 'panel_bank_links_workflow_tasks'=>static function()use($root):bool{$s=file_get_contents($root.'/src/Bank/BankModule.php');return str_contains($s,'&amp;queue=bank&amp;status=all');},
];
$errors=0;foreach($tests as $name=>$run){try{if(!$run())throw new RuntimeException('assertion failed');echo "[PASS] $name\n";}catch(Throwable $e){$errors++;echo "[FAIL] $name: {$e->getMessage()}\n";}}exit($errors?1:0);
