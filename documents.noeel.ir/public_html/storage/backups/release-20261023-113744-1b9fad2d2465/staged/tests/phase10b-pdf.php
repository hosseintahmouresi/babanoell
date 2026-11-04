<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/src/Pdf/ContractPdfRenderer.php';
use Babanoell\Documents\Pdf\ContractPdfRenderer;
$tests=0;
$expect=function(callable $callback):void{global $tests;$tests++;try{$callback();}catch(Throwable $e){fwrite(STDERR,"[FAIL] ".$e->getMessage()."\n");exit(1);}};
$expect(function(){
 $renderer=new ContractPdfRenderer();
 try{$renderer->render(['status'=>'pending']);throw new RuntimeException('non-signed contract unexpectedly rendered');}
 catch(RuntimeException $e){if(str_contains($e->getMessage(),'unexpectedly'))throw $e;}
});
$expect(function(){
 $renderer=new ContractPdfRenderer();
 try{$renderer->render(['status'=>'signed','rendered_content'=>'تست','rendered_hash'=>str_repeat('a',64)]);throw new RuntimeException('tampered snapshot rendered');}
 catch(RuntimeException $e){if(str_contains($e->getMessage(),'rendered'))throw $e;}
});
$expect(function(){
 $source=file_get_contents(dirname(__DIR__).'/src/Service/DocumentService.php');
 foreach(['signed_contract_pdf','signed_artifact_immutable','documents.manage'] as $needle)if(!str_contains($source,$needle))throw new RuntimeException('missing immutable read or ACL: '.$needle);
});
$expect(function(){
 $migration=file_get_contents(dirname(__DIR__).'/database/migrations/202610220001_contract_pdf_archive.php');
 foreach(['PRIMARY KEY','checksum_sha256','source_rendered_hash','ON DELETE RESTRICT'] as $needle)if(!str_contains($migration,$needle))throw new RuntimeException('pdf schema missing: '.$needle);
});
$expect(function(){
 $body=file_get_contents(dirname(__DIR__).'/src/Pdf/ContractPdfRenderer.php');
 foreach(['LIBXML_NONET','safeMarkup','STRING_RETURN','noelyekan'] as $needle)if(!str_contains($body,$needle))throw new RuntimeException('missing PDF renderer guard: '.$needle);
});
echo "Documents PDF tests: $tests run, 0 failed\n";
