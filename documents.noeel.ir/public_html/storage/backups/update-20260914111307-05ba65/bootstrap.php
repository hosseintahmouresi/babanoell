<?php
declare(strict_types=1);
use Babanoell\Documents\App\Config;
use Babanoell\Documents\Database\Connection;
use Babanoell\Documents\Http\IdentityClient;
use Babanoell\Documents\Http\LoansClient;
use Babanoell\Documents\Http\NativeHttpTransport;
use Babanoell\Documents\Repository\PdoDocumentRepository;
use Babanoell\Documents\Storage\PrivateStorage;
use Babanoell\Documents\Domain\UploadValidator;
use Babanoell\Documents\Service\EntityAccessService;
use Babanoell\Documents\Service\DocumentService;

$serviceRoot=__DIR__;
spl_autoload_register(static function(string$class)use($serviceRoot):void{$prefix='Babanoell\\Documents\\';if(!str_starts_with($class,$prefix))return;$file=$serviceRoot.'/src/'.str_replace('\\','/',substr($class,strlen($prefix))).'.php';if(is_file($file))require$file;});
$config=Config::fromEnvironment();
$pdo=Connection::fromConfig($config);
$transport=new NativeHttpTransport();
$identityClient=new IdentityClient($config,$transport);
$loansClient=new LoansClient($config,$transport);
$repository=new PdoDocumentRepository($pdo);
$privateStorage=new PrivateStorage($serviceRoot.'/storage/private');
$entityAccess=new EntityAccessService($loansClient);
$documentService=new DocumentService($repository,$privateStorage,new UploadValidator($config->maxUploadBytes()),$entityAccess);
