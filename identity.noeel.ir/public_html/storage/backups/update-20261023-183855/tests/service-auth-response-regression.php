<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { exit(1); }
$root = dirname(__DIR__);
spl_autoload_register(static function (string $class) use ($root): void {
    $prefix = 'Babanoell\\Auth\\';
    if (!str_starts_with($class, $prefix)) return;
    $path = $root.'/src/'.str_replace('\\', '/', substr($class, strlen($prefix))).'.php';
    if (is_file($path)) require $path;
});
use Babanoell\Auth\App\App;
use Babanoell\Auth\App\Config;
use Babanoell\Auth\App\ErrorHandler;
use Babanoell\Auth\Http\Router;
use Babanoell\Auth\Http\Request;
use Babanoell\Auth\Platform\ServiceAuth;
use Babanoell\Auth\Platform\RequestContext;
$body = '{"identifier":"example-admin","password":"fake-password"}';
$path = '/api/v1/auth/login';
$secret = str_repeat('x', 32);
function statusOf(array $headers, string $secret, string $body): array {
    $app = new App(new Router(), new ErrorHandler(), new Config('identity', 'test', [
        'service_auth_secret' => $secret, 'service_auth_mode' => 'required'
    ]));
    $response = $app->handle(new Request('POST', '/api/v1/auth/login', array_change_key_case($headers, CASE_LOWER), $body));
    $payload = json_decode($response->body(), true);
    return [$response->statusCode(), $payload['error']['code'] ?? ''];
}
RequestContext::set('regression_req001');
$bad = ServiceAuth::outbound('integrations', 'POST', $path, $body, str_repeat('y', 32));
$ok = ServiceAuth::outbound('integrations', 'POST', $path, $body, $secret);
$cases = [
    'invalid_signature' => [statusOf($bad, $secret, $body), [401,'service_signature_invalid']],
    'missing_signature' => [statusOf([], $secret, $body), [401,'service_auth_required']],
    'secret_not_configured' => [statusOf($bad, '', $body), [503,'service_auth_not_configured']],
    'valid_signature' => [statusOf($ok, $secret, $body), [404,'not_found']],
];
foreach ($cases as $name => [$actual, $expected]) {
    if ($actual !== $expected) { throw new RuntimeException($name.' expected '.json_encode($expected).' got '.json_encode($actual)); }
    echo "PASS $name\n";
}
