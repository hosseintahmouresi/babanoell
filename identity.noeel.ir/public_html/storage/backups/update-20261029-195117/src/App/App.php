<?php
declare(strict_types=1);

namespace Babanoell\Auth\App;

use Babanoell\Auth\Http\Request;
use Babanoell\Auth\Http\Response;
use Babanoell\Auth\Http\Router;
use Babanoell\Auth\Http\ResponseMapper;
use Babanoell\Auth\Platform\RequestContext;
use Babanoell\Auth\Platform\ProxyContext;
use Babanoell\Auth\Platform\ServiceAuth;
use Throwable;

final class App
{
    public function __construct(
        private readonly Router $router,
        private readonly ErrorHandler $errorHandler,
        private readonly Config $config,
    ) {
    }

    public static function create(?Config $config = null): self
    {
        return self::createFromContainer(new Container($config ?? Config::fromEnvironment()));
    }

    public static function createFromContainer(Container $container): self
    {
        return new self(Routes::build($container), new ErrorHandler(), $container->config());
    }

    public function handle(Request $request): Response
    {
        RequestContext::initialize($request->header('X-Request-Id'), 'identity');
        try {
            // Production session credentials must never cross an insecure hop.
            // Respect forwarding headers ONLY from explicitly trusted proxies.
            if ($this->config->environment() === 'production'
                && $this->config->httpLoginActivationAllowed()
                && in_array($request->path(), ['/api/v1/auth/login','/api/v1/auth/refresh','/api/v1/auth/logout','/api/v1/auth/me'], true)
                && !ProxyContext::secure($_SERVER)) {
                return \Babanoell\Auth\Http\ResponseMapper::error(403, 'https_required', 'HTTPS is required.');
            }
            if(str_starts_with($request->path(),'/api/v1/')) {
                $service=ServiceAuth::verify($request->header(ServiceAuth::HEADER_SERVICE),$request->header(ServiceAuth::HEADER_TIMESTAMP),$request->header(ServiceAuth::HEADER_SIGNATURE),$request->method(),$request->path(),$request->rawBody(),$this->config->serviceAuthSecret(),$this->config->serviceAuthMode());
                if(!$service['ok']) return ResponseMapper::error($service['error']==='service_auth_not_configured'?503:401,(string)$service['error'],'Service authentication failed.');
                if($service['authenticated']===true) RequestContext::setCallerService(is_string($service['service'])?$service['service']:null);
            }
            return $this->router->dispatch($request);
        } catch (Throwable $throwable) {
            return $this->errorHandler->render($throwable);
        }
    }
}
