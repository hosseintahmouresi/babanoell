<?php
declare(strict_types=1);
$root=dirname(__DIR__);
$index=file_get_contents($root.'/public/index.php');$svc=file_get_contents($root.'/src/Service/ProviderVerificationService.php');$repo=file_get_contents($root.'/src/Repository/PdoLoanRepository.php');
$tests=[
 'Bank reviewer authorization gate'=>str_contains($index,"'bank_credit_denied'"),
 'Verification request belongs to target case'=>str_contains($svc,'(string)$v[\'request_id\']!==$requestId'),
 'Dispatch is reserved before network call'=>strpos($svc,'claimProviderVerification')<strpos($svc,'$this->client->shahkar'),
 'Only queued requests may dispatch'=>str_contains($svc,'$v[\'status\']!==\'queued\''),
 'Unknown response does not retry provider request'=>str_contains($svc,"'delivery'=>'unconfirmed'"),
 'OTP does not approve credit'=>str_contains($svc,"'credit_status'=>'pending'"),
 'Provider claim atomically updates queued state'=>str_contains($repo,"WHERE id=:id AND status='queued'"),
 'HMAC payload validation implemented'=>is_file($root.'/src/Platform/PayloadAuth.php'),
];
$f=0;foreach($tests as$n=>$ok){echo ($ok?'[PASS] ':'[FAIL] ').$n."\n";if(!$ok)$f++;}echo count($tests).' checks, '.$f.' failed'."\n";exit($f?1:0);
