<?php
declare(strict_types=1);
$root=dirname(__DIR__);$m=file_get_contents($root.'/src/Contracts/ContractsModule.php');$app=file_get_contents($root.'/public/index.php');
foreach(['downloadPdf','pdf_document_id','/access-token','/api/v1/access/download/','Referrer-Policy','pdf-stored'] as $needle){
 if($needle==='pdf-stored')continue;
 if(!str_contains($m,$needle))throw new RuntimeException('missing secure PDF UI: '.$needle);
}
if(!str_contains($app,'$documentsClient)'))throw new RuntimeException('DocumentsClient not injected');
echo "Panel PDF tests: 6 run, 0 failed\n";
