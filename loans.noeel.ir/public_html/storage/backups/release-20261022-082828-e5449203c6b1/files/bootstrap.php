<?php
declare(strict_types=1);

use Babanoell\Loans\App\Config;
use Babanoell\Loans\Database\Connection;
use Babanoell\Loans\Http\IdentityClient;
use Babanoell\Loans\Http\DocumentsClient;
use Babanoell\Loans\Http\PartnersClient;
use Babanoell\Loans\Http\FinanceClient;
use Babanoell\Loans\Http\NativeHttpTransport;
use Babanoell\Loans\Repository\PdoLoanRepository;
use Babanoell\Loans\Calculation\CalculatorRegistry;
use Babanoell\Loans\Domain\ScheduleGenerator;
use Babanoell\Loans\Domain\StatusMachine;

$serviceRoot = __DIR__;
spl_autoload_register(static function(string $class) use ($serviceRoot): void {
    $prefix='Babanoell\\Loans\\';
    if(!str_starts_with($class,$prefix)) return;
    $file=$serviceRoot.'/src/'.str_replace('\\','/',substr($class,strlen($prefix))).'.php';
    if(is_file($file)) require $file;
});

$config = Config::fromEnvironment();
$pdo = Connection::fromConfig($config);
$httpTransport = new NativeHttpTransport();
$identityClient = new IdentityClient($config, $httpTransport);
$documentsClient = new DocumentsClient($config, $httpTransport);
$partnersClient = new PartnersClient($config, $httpTransport);
$financeClient = new FinanceClient($config, $httpTransport);
$repository = new PdoLoanRepository($pdo);
$calculators = new CalculatorRegistry();
$statusMachine = new StatusMachine();
$scheduleGenerator = new ScheduleGenerator();
