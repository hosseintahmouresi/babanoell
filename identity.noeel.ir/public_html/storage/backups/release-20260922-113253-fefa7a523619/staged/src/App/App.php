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
    ) {
    }

    public static function create(?Config $config = null): self
    {
        return self::createFromContainer(new Container($config ?? Config::fromEnvironment()));
    }

    public static function createFromContainer(Container $container): self
    {
        return new self(Routes::build($container), new ErrorHandler());
    }

    public function handle(Request $request): Response
    {
        try {
            return $this->router->dispatch($request);
        } catch (Throwable $throwable) {
            return $this->errorHandler->render($throwable);
        }
    }
}
