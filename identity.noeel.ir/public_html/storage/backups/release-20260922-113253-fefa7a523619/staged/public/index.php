<?php
declare(strict_types=1);

use Babanoell\Auth\App\App;
use Babanoell\Auth\Http\Request;

$serviceRoot = dirname(__DIR__);

require_once $serviceRoot . '/config/env.php';
babanoell_auth_load_env();

spl_autoload_register(static function (string $class) use ($serviceRoot): void {
    $prefix = 'Babanoell\\Auth\\';

    if (!str_starts_with($class, $prefix)) {
        return;
    }

    $relativeClass = substr($class, strlen($prefix));
    $file = $serviceRoot . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . str_replace('\\', DIRECTORY_SEPARATOR, $relativeClass) . '.php';

    if (is_file($file)) {
        require $file;
    }
});

App::create()->handle(Request::fromGlobals())->send();
