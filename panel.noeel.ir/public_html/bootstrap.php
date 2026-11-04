<?php
declare(strict_types=1);

$serviceRoot = __DIR__;
require_once $serviceRoot . '/config/env.php';
babanoell_panel_load_env();

spl_autoload_register(static function (string $class) use ($serviceRoot): void {
    $prefix = 'Babanoell\\Panel\\';
    if (!str_starts_with($class, $prefix)) return;
    $relative = substr($class, strlen($prefix));
    $file = $serviceRoot . '/src/' . str_replace('\\', '/', $relative) . '.php';
    if (is_file($file)) require $file;
});

use Babanoell\Panel\App\Config;
use Babanoell\Panel\Auth\PanelSession;
use Babanoell\Panel\Http\IdentityClient;
use Babanoell\Panel\Http\LoansClient;
use Babanoell\Panel\Http\DocumentsClient;
use Babanoell\Panel\Http\PartnersClient;
use Babanoell\Panel\Http\FinanceClient;
use Babanoell\Panel\Http\IntegrationsClient;
use Babanoell\Panel\Security\TokenVault;

$config = Config::fromEnvironment();
\Babanoell\Panel\Platform\RequestContext::initializeFromServer($_SERVER,'panel');

$sessionDir = $serviceRoot . '/storage/sessions';
if (!is_dir($sessionDir)) @mkdir($sessionDir, 0770, true);
if (is_dir($sessionDir) && is_writable($sessionDir)) session_save_path($sessionDir);
ini_set('session.use_strict_mode', '1');
ini_set('session.use_only_cookies', '1');
session_name('babanoell_panel');
session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'domain' => '',
    'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['SERVER_PORT'] ?? '') === '443'),
    'httponly' => true,
    'samesite' => 'Lax',
]);
if (session_status() !== PHP_SESSION_ACTIVE) session_start();

header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: strict-origin-when-cross-origin');
header("Permissions-Policy: camera=(), microphone=(), geolocation=()");
header("Content-Security-Policy: default-src 'self'; img-src 'self' data:; style-src 'self'; script-src 'self'; font-src 'self'; connect-src 'self'; frame-ancestors 'none'; base-uri 'self'; form-action 'self'");
header('Cache-Control: no-store, max-age=0');

$identityClient = new IdentityClient($config);
$loansClient = new LoansClient($config);
$documentsClient = new DocumentsClient($config);
$partnersClient = new PartnersClient($config);
$financeClient = new FinanceClient($config);
$integrationsClient = new IntegrationsClient($config);
$panelSession = new PanelSession($config, $identityClient, new TokenVault($config->sessionEncryptionKey()));
