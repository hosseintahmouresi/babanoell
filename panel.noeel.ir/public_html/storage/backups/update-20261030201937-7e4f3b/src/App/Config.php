<?php
declare(strict_types=1);
namespace Babanoell\Panel\App;
final class Config
{
    public function __construct(
        private readonly string $serviceName,
        private readonly string $environment,
        private readonly string $identityBaseUrl,
        private readonly string $sessionEncryptionKey,
        private readonly int $httpTimeoutSeconds=6,
        private readonly int $sessionIdleSeconds=1800,
        private readonly int $sessionAbsoluteSeconds=28800,
        private readonly string $loansBaseUrl='https://loans.noeel.ir',
        private readonly string $documentsBaseUrl='https://documents.noeel.ir',
        private readonly string $serviceAuthSecret='',
        private readonly string $partnersBaseUrl='https://partners.noeel.ir',
        private readonly string $financeBaseUrl='https://finance.noeel.ir',
        private readonly string $integrationsBaseUrl='https://integrations.noeel.ir',
    ){}
    public static function fromEnvironment():self
    {
        require_once dirname(__DIR__,2).'/config/env.php';\babanoell_panel_load_env();$env=static fn(string$key,string$default=''):string=>(string)(\babanoell_panel_env($key,$default)??$default);$int=static function(string$key,int$default)use($env):int{$v=$env($key,(string)$default);return filter_var($v,FILTER_VALIDATE_INT)!==false?max(1,(int)$v):$default;};
        return new self(trim($env('SERVICE_NAME','panel'))?:'panel',strtolower(trim($env('APP_ENV','staging'))?:'staging'),rtrim(trim($env('PANEL_IDENTITY_BASE_URL','')),'/'),trim($env('PANEL_SESSION_ENCRYPTION_KEY','')),$int('PANEL_HTTP_TIMEOUT_SECONDS',6),$int('PANEL_SESSION_IDLE_SECONDS',1800),$int('PANEL_SESSION_ABSOLUTE_SECONDS',28800),rtrim(trim($env('PANEL_LOANS_BASE_URL','https://loans.noeel.ir')),'/'),rtrim(trim($env('PANEL_DOCUMENTS_BASE_URL','https://documents.noeel.ir')),'/'),trim($env('NOEEL_SERVICE_SHARED_SECRET','')),rtrim(trim($env('PANEL_PARTNERS_BASE_URL','https://partners.noeel.ir')),'/'),rtrim(trim($env('PANEL_FINANCE_BASE_URL','https://finance.noeel.ir')),'/'),rtrim(trim($env('PANEL_INTEGRATIONS_BASE_URL','https://integrations.noeel.ir')),'/'));
    }
    public function serviceName():string{return$this->serviceName;} public function environment():string{return$this->environment;} public function identityBaseUrl():string{return$this->identityBaseUrl;} public function loansBaseUrl():string{return$this->loansBaseUrl;} public function documentsBaseUrl():string{return$this->documentsBaseUrl;} public function partnersBaseUrl():string{return$this->partnersBaseUrl;} public function financeBaseUrl():string{return$this->financeBaseUrl;} public function integrationsBaseUrl():string{return$this->integrationsBaseUrl;} public function sessionEncryptionKey():string{return$this->sessionEncryptionKey;} public function httpTimeoutSeconds():int{return$this->httpTimeoutSeconds;} public function sessionIdleSeconds():int{return$this->sessionIdleSeconds;} public function sessionAbsoluteSeconds():int{return$this->sessionAbsoluteSeconds;} public function serviceAuthSecret():string{return$this->serviceAuthSecret;} public function isProduction():bool{return$this->environment==='production';}
    public function isReady():bool{if($this->identityBaseUrl===''||$this->sessionEncryptionKey===''||$this->loansBaseUrl===''||$this->documentsBaseUrl===''||$this->partnersBaseUrl===''||$this->financeBaseUrl===''||$this->integrationsBaseUrl==='')return false;if($this->isProduction()&&(!str_starts_with(strtolower($this->identityBaseUrl),'https://')||!str_starts_with(strtolower($this->loansBaseUrl),'https://')||!str_starts_with(strtolower($this->documentsBaseUrl),'https://')||!str_starts_with(strtolower($this->partnersBaseUrl),'https://')||!str_starts_with(strtolower($this->financeBaseUrl),'https://')||!str_starts_with(strtolower($this->integrationsBaseUrl),'https://')))return false;return true;}
}
