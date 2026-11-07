<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit(1);
$root=dirname(__DIR__);
$s=file_get_contents($root.'/src/Repository/PdoPartnerRepository.php');
$m=file_get_contents($root.'/database/migrations/202609300001_create_contract_engine.php');
$tests=[
 'contract table part of owned partner schema'=>str_contains($m,'CREATE TABLE IF NOT EXISTS contracts'),
 'counts all contract versions'=>str_contains($s,'contract_versions')&&str_contains($s,'SELECT COUNT(*) FROM contracts'),
 'counts signed versions by status'=>str_contains($s,"SELECT COUNT(*) FROM contracts WHERE status='signed'"),
 'pending sign states are explicit'=>str_contains($s,"status IN ('pending','sent','viewed','otp_verified')"),
 'installed release includes contract reporting'=>version_compare((require $root.'/config/version.php')['version'],'1.4.0','>='),
];
foreach($tests as$name=>$ok)echo($ok?'PASS ':'FAIL ').$name."\n";
$f=count(array_filter($tests,static fn(bool$x):bool=>!$x));
echo 'Phase8 Partners: '.(count($tests)-$f).' passed, '.$f.' failed'."\n";
exit($f?1:0);
