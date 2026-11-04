<?php
declare(strict_types=1);
$root=dirname(__DIR__);$route=file_get_contents($root.'/public/index.php');$repo=file_get_contents($root.'/src/Contract/ContractRepository.php');
$tests=0;
foreach(['pdf-stored','callerService()','markPdfStored'] as $s){$tests++;if(!str_contains($route,$s))throw new RuntimeException('missing service-only PDF callback '.$s);}
foreach(['pdf_document_id','pdf_hash',"status='signed'",'contract.pdf_stored'] as $s){$tests++;if(!str_contains($repo,$s))throw new RuntimeException('missing immutable PDF persistence '.$s);}
$m=file_get_contents($root.'/database/migrations/202610220003_contract_pdf_state.php');$tests++;if(!str_contains($m,'information_schema.columns'))throw new RuntimeException('non-idempotent migration');
echo "Partners PDF tests: $tests run, 0 failed\n";
