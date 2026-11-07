<?php
declare(strict_types=1);
use Babanoell\Partners\App\Config;
use Babanoell\Partners\Database\Connection;
use Babanoell\Partners\Http\IdentityClient;
use Babanoell\Partners\Http\LoansClient;
use Babanoell\Partners\Http\DocumentsClient;
use Babanoell\Partners\Http\NativeHttpTransport;
use Babanoell\Partners\Repository\PdoPartnerRepository;
use Babanoell\Partners\Service\PartnerService;
use Babanoell\Partners\Contract\ContractRepository;
use Babanoell\Partners\Contract\ContractService;
use Babanoell\Partners\Contract\ContractRevisionService;
$serviceRoot=__DIR__;
spl_autoload_register(static function(string$class)use($serviceRoot):void{$prefix='Babanoell\\Partners\\';if(!str_starts_with($class,$prefix))return;$file=$serviceRoot.'/src/'.str_replace('\\','/',substr($class,strlen($prefix))).'.php';if(is_file($file))require$file;});
$config=Config::fromEnvironment();if(strtolower($config->environment())==='production'&&!$config->isReady()){http_response_code(503);header('Content-Type:text/plain;charset=utf-8');exit('Production configuration requires HTTPS service endpoints and complete settings.');}
$pdo=Connection::fromConfig($config);$httpTransport=new NativeHttpTransport();$identityClient=new IdentityClient($config,$httpTransport);$loansClient=new LoansClient($config,$httpTransport);$documentsClient=new DocumentsClient($config,$httpTransport);$repository=new PdoPartnerRepository($pdo);$partnerService=new PartnerService($repository,$loansClient,$documentsClient,$identityClient);$contractRepository=new ContractRepository($pdo);$integrationsClient=new \Babanoell\Partners\Http\IntegrationsClient($httpTransport,rtrim(\babanoell_partners_env('PARTNERS_INTEGRATIONS_BASE_URL','https://integrations.noeel.ir'),'/'),$config->serviceAuthSecret(),(int)\babanoell_partners_env('PARTNERS_SMS_CONTRACT_BODY_ID','0'));$contractService=new ContractService($contractRepository,$loansClient,$documentsClient,$config->isProduction(),$integrationsClient);$contractRevisionService=new ContractRevisionService($pdo,$contractRepository);
