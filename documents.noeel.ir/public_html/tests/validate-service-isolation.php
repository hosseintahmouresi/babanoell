<?php
declare(strict_types=1);
$root=dirname(__DIR__);$checks=[];
$checks['tracking table is Documents-specific']=str_contains((string)file_get_contents($root.'/src/Database/MigrationRunner.php'),'documents_schema_migrations');
$guard=(string)file_get_contents($root.'/src/Database/DatabaseIsolationGuard.php');
$checks['Identity markers are rejected']=str_contains($guard,"'users'")&&str_contains($guard,"'sessions'");
$checks['Loans markers are rejected']=str_contains($guard,"'installment_plans'")&&str_contains($guard,"'loan_requests'");
$checks['No cross-service DB configuration exists']=!preg_match('/IDENTITY_DB_|LOANS_DB_/i',(string)file_get_contents($root.'/config/local.env.example.php'));
$failed=0;foreach($checks as$n=>$ok){echo($ok?'[PASS] ':'[FAIL] ').$n."\n";if(!$ok)$failed++;}echo count($checks)." isolation checks, {$failed} failed.\n";exit($failed?1:0);
