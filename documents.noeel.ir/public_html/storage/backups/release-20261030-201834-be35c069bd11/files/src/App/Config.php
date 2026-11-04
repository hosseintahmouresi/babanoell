<?php
declare(strict_types=1);
namespace Babanoell\Documents\App;

final class Config
{
    public function __construct(
        private readonly string $serviceName,
        private readonly string $environment,
        private readonly string $dbHost,
        private readonly int $dbPort,
        private readonly string $dbName,
        private readonly string $dbUser,
        private readonly string $dbPassword,
        private readonly string $dbCharset,
        private readonly string $identityBaseUrl,
        private readonly string $loansBaseUrl,
        private readonly int $httpTimeoutSeconds = 8,
        private readonly int $maxUploadBytes = 10485760,
        private readonly string $serviceAuthSecret = '',
        private readonly string $serviceAuthMode = 'optional',
        private readonly string $partnersBaseUrl = 'https://partners.noeel.ir',
        private readonly string $financeBaseUrl = 'https://finance.noeel.ir',
    ) {}

    public static function fromEnvironment(): self
    {
        require_once dirname(__DIR__, 2) . '/config/env.php';
        \babanoell_documents_load_env();
        $env = static fn(string $key, string $default=''): string => (string)(\babanoell_documents_env($key, $default) ?? $default);
        $port = filter_var($env('DOCUMENTS_DB_PORT','3306'), FILTER_VALIDATE_INT);
        $timeout = filter_var($env('DOCUMENTS_HTTP_TIMEOUT_SECONDS','8'), FILTER_VALIDATE_INT);
        $max = filter_var($env('DOCUMENTS_MAX_UPLOAD_BYTES','10485760'), FILTER_VALIDATE_INT);
        return new self(
            trim($env('SERVICE_NAME','documents')) ?: 'documents',
            strtolower(trim($env('APP_ENV','staging')) ?: 'staging'),
            trim($env('DOCUMENTS_DB_HOST','localhost')) ?: 'localhost',
            $port !== false ? (int)$port : 3306,
            trim($env('DOCUMENTS_DB_NAME')),
            trim($env('DOCUMENTS_DB_USER')),
            $env('DOCUMENTS_DB_PASSWORD'),
            trim($env('DOCUMENTS_DB_CHARSET','utf8mb4')) ?: 'utf8mb4',
            rtrim(trim($env('DOCUMENTS_IDENTITY_BASE_URL','https://identity.noeel.ir')), '/'),
            rtrim(trim($env('DOCUMENTS_LOANS_BASE_URL','https://loans.noeel.ir')), '/'),
            $timeout !== false ? max(2,(int)$timeout) : 8,
            $max !== false ? max(1048576,min(52428800,(int)$max)) : 10485760,
            trim($env('NOEEL_SERVICE_SHARED_SECRET','')),
            strtolower(trim($env('NOEEL_SERVICE_AUTH_MODE','optional'))) ?: 'optional',
            rtrim(trim($env('DOCUMENTS_PARTNERS_BASE_URL','https://partners.noeel.ir')), '/'),
            rtrim(trim($env('DOCUMENTS_FINANCE_BASE_URL','https://finance.noeel.ir')), '/'),
        );
    }

    public function serviceName(): string{return $this->serviceName;}
    public function environment(): string{return $this->environment;}
    public function dbHost(): string{return $this->dbHost;}
    public function dbPort(): int{return $this->dbPort;}
    public function dbName(): string{return $this->dbName;}
    public function dbUser(): string{return $this->dbUser;}
    public function dbPassword(): string{return $this->dbPassword;}
    public function dbCharset(): string{return $this->dbCharset;}
    public function identityBaseUrl(): string{return $this->identityBaseUrl;}
    public function loansBaseUrl(): string{return $this->loansBaseUrl;}
    public function partnersBaseUrl(): string{return $this->partnersBaseUrl;}
    public function financeBaseUrl(): string{return $this->financeBaseUrl;}
    public function httpTimeoutSeconds(): int{return $this->httpTimeoutSeconds;}
    public function maxUploadBytes(): int{return $this->maxUploadBytes;}
    public function serviceAuthSecret(): string{return$this->serviceAuthSecret;}
    public function serviceAuthMode(): string{return in_array($this->serviceAuthMode,['disabled','optional','required'],true)?$this->serviceAuthMode:'optional';}
    public function isProduction(): bool{return $this->environment==='production';}
    public function isReady(): bool
    {
        if($this->dbName===''||$this->dbUser===''||$this->identityBaseUrl===''||$this->loansBaseUrl===''||$this->partnersBaseUrl===''||$this->financeBaseUrl==='')return false;
        if($this->isProduction()&&(!str_starts_with(strtolower($this->identityBaseUrl),'https://')||!str_starts_with(strtolower($this->loansBaseUrl),'https://')||!str_starts_with(strtolower($this->partnersBaseUrl),'https://')||!str_starts_with(strtolower($this->financeBaseUrl),'https://')))return false;
        return true;
    }
}
