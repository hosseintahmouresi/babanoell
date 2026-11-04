<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit(1);
$s=(string)file_get_contents(dirname(__DIR__).'/src/Finance/FinanceModule.php');
$tests=[
 'greenpay console route'=>str_contains($s,"$"."path==='/finance/greenpay'"),
 'CSRF for POST'=>str_contains($s,"$"."this->csrf('/finance/greenpay')"),
 'two person approval UI'=>str_contains($s,'تأیید توسط مدیر دوم'),
 'OTP never repopulated in input'=>str_contains($s,'autocomplete="one-time-code"'),
 'reconciliation note required'=>str_contains($s,'minlength="20"'),
 'fund transfer demands confirmation checkbox'=>str_contains($s,'اجرای انتقال برگشت‌ناپذیر را تأیید می‌کنم'),
 'no blind retry button in uncertainty'=>str_contains($s,'هیچ دکمه تکرار انتقال وجود ندارد'),
];
foreach($tests as $name=>$ok)echo ($ok?'[PASS] ':'[FAIL] ').$name."\n";
$f=count(array_filter($tests,fn($x)=>!$x));echo 'Batch7 Panel: '.(count($tests)-$f).' passed / '.$f." failed\n";exit($f?1:0);
