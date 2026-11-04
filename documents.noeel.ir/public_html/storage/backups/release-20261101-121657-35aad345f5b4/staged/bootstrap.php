<?php
declare(strict_types=1);
use Babanoell\Documents\App\Config;
use Babanoell\Documents\Database\Connection;
use Babanoell\Documents\Http\IdentityClient;
use Babanoell\Documents\Http\LoansClient;
use Babanoell\Documents\Http\PartnersClient;
use Babanoell\Documents\Http\FinanceClient;
use Babanoell\Documents\Http\NativeHttpTransport;
use Babanoell\Documents\Repository\PdoDocumentRepository;
use Babanoell\Documents\Storage\PrivateStorage;
use Babanoell\Documents\Domain\UploadValidator;
use Babanoell\Documents\Service\EntityAccessService;
use Babanoell\Documents\Service\DocumentService;
use Babanoell\Documents\Service\ContractArtifactService;
use Babanoell\Documents\Service\ContractPdfService;
use Babanoell\Documents\Pdf\ContractPdfRenderer;

$serviceRoot=__DIR__;
spl_autoload_register(static function(string$class)use($serviceRoot):void{$prefix='Babanoell\\Documents\\';if(!str_starts_with($class,$prefix))return;$file=$serviceRoot.'/src/'.str_replace('\\','/',substr($class,strlen($prefix))).'.php';if(is_file($file))require$file;});
$config=Config::fromEnvironment();
if (strtolower($config->environment()) === 'production' && !$config->isReady()) {
    http_response_code(503);
    header('Content-Type: text/plain; charset=utf-8');
    exit('Production configuration requires HTTPS service endpoints and complete settings.');
}
$pdo=Connection::fromConfig($config);
$transport=new NativeHttpTransport();
$identityClient=new IdentityClient($config,$transport);
$loansClient=new LoansClient($config,$transport);
$partnersClient=new PartnersClient($config,$transport);
$financeClient=new FinanceClient($config,$transport);
$repository=new PdoDocumentRepository($pdo);
$privateStorage=new PrivateStorage($serviceRoot.'/storage/private');
$entityAccess=new EntityAccessService($loansClient,$partnersClient,$financeClient);
$documentService=new DocumentService($repository,$privateStorage,new UploadValidator($config->maxUploadBytes()),$entityAccess);$contractArtifactService=new ContractArtifactService($repository,$privateStorage,$partnersClient,new ContractPdfService($repository,$privateStorage,$partnersClient,new ContractPdfRenderer()));
