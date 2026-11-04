<?php
declare(strict_types=1);

namespace Babanoell\Auth\App;

use Babanoell\Auth\Http\Request;
use Babanoell\Auth\Http\Response;
use Babanoell\Auth\Http\Router;
use Throwable;

final class App
{
    public function __construct(
        private readonly Router $router,
        private readonly ErrorHandler $errorHandler,
        private readonly ?Config $config = null,
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
        try {
            if (strtolower($this->config?->environment() ?? '') === 'production'
                && str_starts_with($request->path(), '/api/v1/')
                && !$request->secure()) {
                return new \Babanoell\Auth\Http\JsonResponse(403, ['error' => ['code' => 'https_required', 'message' => 'HTTPS is required.']]);
            }
            return $this->router->dispatch($request);
        } catch (Throwable $throwable) {
            return $this->errorHandler->render($throwable);
        }
    }
}
