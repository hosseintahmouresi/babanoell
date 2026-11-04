<?php
declare(strict_types=1);

use Babanoell\Auth\App\App;
use Babanoell\Auth\App\Config;
use Babanoell\Auth\Http\Request;
use Babanoell\Auth\RateLimit\InactiveRateLimiter;
use Babanoell\Auth\RateLimit\RateLimitContext;
use Babanoell\Auth\Tests\Assertions;
use Babanoell\Auth\Tests\TestCase;

return new class extends TestCase {
    public function testSecurityConfigLoadsSafeDefaults(): void
    {
        $this->clearSecurityEnvironment();

        $config = require dirname(__DIR__, 2) . '/config/security.php';

        Assertions::false($config['login_enabled']);
        Assertions::false($config['session_issuing_enabled']);
        Assertions::false($config['refresh_enabled']);
        Assertions::false($config['allow_http_login_in_local']);
        Assertions::same('local', $config['auth_environment']);
        Assertions::true($config['require_token_pepper']);
        Assertions::same(900, $config['session_ttl_seconds']);
        Assertions::same(1209600, $config['refresh_ttl_seconds']);
        Assertions::same('', $config['token_pepper']);
        Assertions::same(900, $config['login_rate_limit_window_seconds']);
        Assertions::same(5, $config['login_rate_limit_max_attempts']);
        Assertions::same(365, $config['audit_retention_days']);
        Assertions::same(65536, $config['password_hash_memory_cost']);
        Assertions::same(4, $config['password_hash_time_cost']);
        Assertions::same(2, $config['password_hash_threads']);
    }

    public function testSecurityConfigKeysAreDocumentedInEnvironmentExample(): void
    {
        $example = file_get_contents(dirname(__DIR__, 2) . '/config/.env.example') ?: '';

        foreach ([
            'AUTH_SESSION_TTL_SECONDS',
            'AUTH_REFRESH_TTL_SECONDS',
            'AUTH_TOKEN_PEPPER',
            'AUTH_ENVIRONMENT',
            'AUTH_ALLOW_HTTP_LOGIN_IN_LOCAL',
            'AUTH_LOGIN_ENABLED',
            'AUTH_SESSION_ISSUING_ENABLED',
            'AUTH_REFRESH_ENABLED',
            'AUTH_REQUIRE_TOKEN_PEPPER',
            'AUTH_LOGIN_RATE_LIMIT_WINDOW_SECONDS',
            'AUTH_LOGIN_RATE_LIMIT_MAX_ATTEMPTS',
            'AUTH_PASSWORD_HASH_MEMORY_COST',
            'AUTH_PASSWORD_HASH_TIME_COST',
            'AUTH_PASSWORD_HASH_THREADS',
        ] as $key) {
            Assertions::contains($key . '=', $example, "Missing {$key} from config example.");
        }
    }

    public function testConfigPlaceholdersDoNotActivateLogin(): void
    {
        $response = App::create(new Config('auth-service', 'local'))->handle(new Request(
            'POST',
            '/api/v1/auth/login',
            [],
            '{"identifier":"local-user@example.test","password":"local-placeholder"}',
        ));

        Assertions::same(501, $response->statusCode());
        Assertions::false(str_contains($response->body(), 'access_token'));
        Assertions::false(str_contains($response->body(), 'refresh_token'));
    }

    public function testInactiveRateLimiterStillDoesNotActivateLogin(): void
    {
        $decision = (new InactiveRateLimiter())->check(RateLimitContext::fromRawValues(
            action: 'auth.login',
            subject: 'local-user@example.test',
            source: '192.0.2.25',
        ));

        Assertions::false($decision->allowed());
        Assertions::same('rate_limit_inactive', $decision->code());
    }

    public function testApplicationConfigExposesDisabledFeatureFlagsByDefault(): void
    {
        $config = new Config('auth-service', 'local');

        Assertions::false($config->loginEnabled());
        Assertions::false($config->sessionIssuingEnabled());
        Assertions::false($config->refreshEnabled());
        Assertions::false($config->allowHttpLoginInLocal());
        Assertions::false($config->httpLoginActivationAllowed());
        Assertions::same('local', $config->authEnvironment());
        Assertions::true($config->tokenPepperRequired());
        Assertions::same('', $config->tokenPepper());
        Assertions::false($config->tokenPepperReady());
        Assertions::same(900, $config->sessionTtlSeconds());
        Assertions::same(1209600, $config->refreshTtlSeconds());
    }

    public function testHttpLoginActivationRequiresLocalOrTestEnvironment(): void
    {
        $local = new Config('auth-service', 'local', [
            'auth_environment' => 'local',
            'allow_http_login_in_local' => true,
            'login_enabled' => true,
        ]);
        $production = new Config('auth-service', 'production', [
            'auth_environment' => 'production',
            'allow_http_login_in_local' => true,
            'login_enabled' => true,
        ]);

        Assertions::true($local->httpLoginActivationAllowed());
        Assertions::false($production->httpLoginActivationAllowed());
    }

    private function clearSecurityEnvironment(): void
    {
        foreach ([
            'AUTH_SESSION_TTL_SECONDS',
            'AUTH_REFRESH_TTL_SECONDS',
            'AUTH_TOKEN_PEPPER',
            'AUTH_ENVIRONMENT',
            'AUTH_ALLOW_HTTP_LOGIN_IN_LOCAL',
            'AUTH_LOGIN_ENABLED',
            'AUTH_SESSION_ISSUING_ENABLED',
            'AUTH_REFRESH_ENABLED',
            'AUTH_REQUIRE_TOKEN_PEPPER',
            'AUTH_LOGIN_RATE_LIMIT_WINDOW_SECONDS',
            'AUTH_LOGIN_RATE_LIMIT_MAX_ATTEMPTS',
            'AUTH_AUDIT_RETENTION_DAYS',
            'AUTH_PASSWORD_HASH_MEMORY_COST',
            'AUTH_PASSWORD_HASH_TIME_COST',
            'AUTH_PASSWORD_HASH_THREADS',
        ] as $key) {
            putenv($key);
        }
    }
    public function testStagingActivationRequiresDedicatedFlagAndNeverEnablesProduction(): void
    {
        $stagingOff = new \Babanoell\Auth\App\Config('auth-service', 'staging', [
            'auth_environment' => 'staging',
            'allow_http_login_in_staging' => false,
            'login_enabled' => true,
        ]);
        Assertions::false($stagingOff->httpLoginActivationAllowed());

        $stagingOn = new \Babanoell\Auth\App\Config('auth-service', 'staging', [
            'auth_environment' => 'staging',
            'allow_http_login_in_staging' => true,
            'login_enabled' => true,
        ]);
        Assertions::true($stagingOn->httpLoginActivationAllowed());

        $production = new \Babanoell\Auth\App\Config('auth-service', 'production', [
            'auth_environment' => 'production',
            'allow_http_login_in_local' => true,
            'allow_http_login_in_staging' => true,
            'login_enabled' => true,
        ]);
        Assertions::false($production->httpLoginActivationAllowed());
    }

};
