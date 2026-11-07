<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit(1);
$root=dirname(__DIR__);require_once $root.'/src/Contract/ContractRepository.php';
$class=new ReflectionClass(\Babanoell\Partners\Contract\ContractRepository::class);$repo=$class->newInstanceWithoutConstructor();$method=$class->getMethod('artifactContent');
$contract=['contract_no'=>'SYNTHETIC-1','mode'=>'digital','rendered_content'=>'<p>متن قرارداد مصنوعی</p>','signed_at'=>'2026-01-02 03:04:05.000000','rendered_hash'=>str_repeat('a',64)];
// This is the deployed pre-upgrade wire envelope. Its digest is part of already-signed evidence.
$legacy='<!doctype html><html lang="fa" dir="rtl"><meta charset="utf-8"><title>SYNTHETIC-1</title><body><p>متن قرارداد مصنوعی</p><hr><p>وضعیت: امضا شده</p><p>زمان امضا: 2026-01-02 03:04:05.000000</p><p>هش محتوا: '.str_repeat('a',64).'</p></body></html>';
if($method->invoke($repo,$contract)!==$legacy)throw new RuntimeException('Existing digital artifact bytes changed');
$contract['mode']='physical';if($method->invoke($repo,$contract)!==$legacy)throw new RuntimeException('Historical physical artifact bytes changed');
$contract['physical_document_id']=str_repeat('b',26);$contract['physical_version_id']=str_repeat('c',26);$contract['physical_sha256']=str_repeat('d',64);
$new=$method->invoke($repo,$contract);if(!str_contains($new,$contract['physical_sha256'])||!str_contains($new,$contract['physical_version_id']))throw new RuntimeException('New physical certificate has no scan evidence');
echo "PASS signed artifact compatibility and physical evidence\n";
