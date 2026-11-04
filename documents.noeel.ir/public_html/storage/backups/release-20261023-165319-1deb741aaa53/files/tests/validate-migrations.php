<?php
declare(strict_types=1);
$root=dirname(__DIR__);require_once $root.'/src/Database/Migration.php';
$files=glob($root.'/database/migrations/*.php')?:[];sort($files,SORT_STRING);$seen=[];$failed=0;
foreach($files as$f){try{$m=require$f;if(!$m instanceof Babanoell\Documents\Database\Migration)throw new RuntimeException('invalid migration object');if(preg_match('/^\d{12}$/',$m->version())!==1)throw new RuntimeException('invalid version');if(isset($seen[$m->version()]))throw new RuntimeException('duplicate version');$seen[$m->version()]=true;echo"[PASS] {$m->version()} {$m->name()}\n";}catch(Throwable$e){$failed++;echo'[FAIL] '.basename($f).': '.$e->getMessage()."\n";}}
echo count($files)." migration files, {$failed} failed.\n";exit($failed?1:0);
