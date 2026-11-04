<?php
declare(strict_types=1);
$root=dirname(__DIR__);
$index=file_get_contents($root.'/public/index.php');
$config=file_get_contents($root.'/config/local.env.example.php');
$migration=file_get_contents($root.'/database/migrations/202610210004_greenpay_otp_intents.php');
$service=file_get_contents($root.'/src/Service/GreenpayBridgeService.php');
$tests=[
 'Money transfer remains explicitly disabled'=>str_contains($index,"'transfer_not_enabled'"),
 'OTP feature is disabled by default'=>str_contains($config,"'FINANCE_GREENPAY_OTP_ENABLED'=>'0'"),
 'OTP request requires finance administrator'=>str_contains($index,'if(!$admin)JsonResponse::error(403,'),
 'OTP requires unique idempotency'=>str_contains($index,"'idempotency_required'"),
 'OTP intent table has unique actor/key'=>str_contains($migration,'UNIQUE KEY uq_fin_greenpay_otp_actor_key'),
 'OTP intent recorded before provider call'=>strpos($service,'INSERT INTO finance_greenpay_otp_intents')<strpos($service,'$this->client->greenpayOtp'),
 'No raw pocket number in financial persistence'=>str_contains($service,'hash_hmac(\'sha256\',$pocket'),
 'Finance identifies itself in API headers'=>str_contains(file_get_contents($root.'/src/Http/JsonResponse.php'),'X-Noel-Service: finance'),
];
$f=0;foreach($tests as$n=>$ok){echo ($ok?'[PASS] ':'[FAIL] ').$n."\n";if(!$ok)$f++;}echo count($tests).' checks, '.$f.' failed'."\n";exit($f?1:0);
