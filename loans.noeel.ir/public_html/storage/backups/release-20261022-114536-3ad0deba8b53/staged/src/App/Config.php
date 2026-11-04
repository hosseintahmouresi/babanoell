<?php
declare(strict_types=1);
namespace Babanoell\Loans\App;

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
        private readonly int $httpTimeoutSeconds = 6,
        private readonly string $documentsBaseUrl = 'https://documents.noeel.ir',
        private readonly string $serviceAuthSecret = '',
        private readonly string $serviceAuthMode = 'optional',
        private readonly string $partnersBaseUrl = 'https://partners.noeel.ir',
        private readonly string $financeBaseUrl = 'https://finance.noeel.ir',
        private readonly string $integrationsBaseUrl = 'https://integrations.noeel.ir',
    ) {}

    public static function fromEnvironment(): self
    {
        require_once dirname(__DIR__, 2) . '/config/env.php';
        \babanoell_loans_load_env();
        $env = static fn(string $key, string $default=''): string => (string) (\babanoell_loans_env($key, $default) ?? $default);
        $port = filter_var($env('LOANS_DB_PORT','3306'), FILTER_VALIDATE_INT);
        $timeout = filter_var($env('LOANS_HTTP_TIMEOUT_SECONDS','6'), FILTER_VALIDATE_INT);
        return new self(
            trim($env('SERVICE_NAME','loans')) ?: 'loans',
            strtolower(trim($env('APP_ENV','staging')) ?: 'staging'),
            trim($env('LOANS_DB_HOST','localhost')) ?: 'localhost',
            $port !== false ? (int)$port : 3306,
            trim($env('LOANS_DB_NAME')),
            trim($env('LOANS_DB_USER')),
            $env('LOANS_DB_PASSWORD'),
            trim($env('LOANS_DB_CHARSET','utf8mb4')) ?: 'utf8mb4',
            rtrim(trim($env('LOANS_IDENTITY_BASE_URL')), '/'),
            $timeout !== false ? max(1,(int)$timeout) : 6,
            rtrim(trim($env('LOANS_DOCUMENTS_BASE_URL','https://documents.noeel.ir')), '/'),
            trim($env('NOEEL_SERVICE_SHARED_SECRET','')),
            strtolower(trim($env('NOEEL_SERVICE_AUTH_MODE','optional'))) ?: 'optional',
            rtrim(trim($env('LOANS_PARTNERS_BASE_URL','https://partners.noeel.ir')), '/'),
            rtrim(trim($env('LOANS_FINANCE_BASE_URL','https://finance.noeel.ir')), '/'),
            rtrim(trim($env('LOANS_INTEGRATIONS_BASE_URL','https://integrations.noeel.ir')), '/'),
        );
    }

    public function serviceName(): string { return $this->serviceName; }
    public function environment(): string { return $this->environment; }
    public function dbHost(): string { return $this->dbHost; }
    public function dbPort(): int { return $this->dbPort; }
    public function dbName(): string { return $this->dbName; }
    public function dbUser(): string { return $this->dbUser; }
    public function dbPassword(): string { return $this->dbPassword; }
    public function dbCharset(): string { return $this->dbCharset; }
    public function identityBaseUrl(): string { return $this->identityBaseUrl; }
    public function documentsBaseUrl(): string { return $this->documentsBaseUrl; }
    public function httpTimeoutSeconds(): int { return $this->httpTimeoutSeconds; }
    public function serviceAuthSecret(): string { return $this->serviceAuthSecret; }
    public function serviceAuthMode(): string { return in_array($this->serviceAuthMode,['disabled','optional','required'],true)?$this->serviceAuthMode:'optional'; }
    public function partnersBaseUrl(): string { return $this->partnersBaseUrl; }
    public function financeBaseUrl(): string { return $this->financeBaseUrl; }
    public function integrationsBaseUrl(): string { return $this->integrationsBaseUrl; }
    public function isProduction(): bool { return $this->environment === 'production'; }
    public function isReady(): bool
    {
        if ($this->dbName==='' || $this->dbUser==='' || $this->identityBaseUrl==='' || $this->documentsBaseUrl==='' || $this->partnersBaseUrl==='' || $this->financeBaseUrl==='' || $this->integrationsBaseUrl==='') return false;
        if(!$this->isProduction())return true;
        return str_starts_with(strtolower($this->identityBaseUrl),'https://')&&str_starts_with(strtolower($this->documentsBaseUrl),'https://')&&str_starts_with(strtolower($this->partnersBaseUrl),'https://')&&str_starts_with(strtolower($this->financeBaseUrl),'https://')&&str_starts_with(strtolower($this->integrationsBaseUrl),'https://');
    }
}
