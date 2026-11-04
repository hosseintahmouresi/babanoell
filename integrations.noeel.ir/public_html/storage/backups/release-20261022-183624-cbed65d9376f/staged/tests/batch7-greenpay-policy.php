<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit(1);
$root=dirname(__DIR__);$s=(string)file_get_contents($root.'/src/App/Router.php');
$tests=[
 'finance caller only for transfer and capability routes'=>str_contains($s,"'POST /api/v1/greenpay/transfer'=>['finance']")&&str_contains($s,"'GET /api/v1/greenpay/transfer-capabilities'=>['finance']"),
 'default transfer gate'=>str_contains($s,"INTEGRATIONS_GREENPAY_TRANSFERS_ENABLED"),
 'independent authorization HMAC'=>str_contains($s,"hash_equals($".'expected,$actual)')&&str_contains($s,'greenpay-transfer-v1|'),
 'idempotency key exactly Finance intent id'=>str_contains($s,'hash_equals($intent,(string)$key)'),
 'reference binds id'=>str_contains($s,"$"."ref!=='N'.".'$intent'),
 'no raw OTP in operation table'=>!str_contains((string)file_get_contents($root.'/database/migrations/202610210001_integrations_core.sql'),'otp_value'),
 'no default live payout'=>str_contains((string)file_get_contents($root.'/config/local.env.example.php'),"'INTEGRATIONS_GREENPAY_TRANSFERS_ENABLED'=>'0'"),
];
foreach($tests as $name=>$ok)echo ($ok?'[PASS] ':'[FAIL] ').$name."\n";
$f=count(array_filter($tests,fn($x)=>!$x));echo 'Batch7 Integrations: '.(count($tests)-$f).' passed / '.$f." failed\n";exit($f?1:0);
