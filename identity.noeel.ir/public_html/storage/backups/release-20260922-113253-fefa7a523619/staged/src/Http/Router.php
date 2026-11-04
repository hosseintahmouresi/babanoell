<?php
declare(strict_types=1);

namespace Babanoell\Auth\Http;

final class Router
{
    /**
     * @var array<string,array<string,callable(Request):Response>>
     */
    private array $routes = [];

    /**
     * @param callable(Request):Response $handler
     */
    public function get(string $path, callable $handler): void
    {
        $this->add('GET', $path, $handler);
    }

    /**
     * @param callable(Request):Response $handler
     */
    public function post(string $path, callable $handler): void
    {
        $this->add('POST', $path, $handler);
    }

    /**
     * @param callable(Request):Response $handler
     */
    public function add(string $method, string $path, callable $handler): void
    {
        $this->routes[$this->normalizePath($path)][strtoupper($method)] = $handler;
    }

    public function dispatch(Request $request): Response
    {
        $path = $request->path();

        if (!isset($this->routes[$path])) {
            return ResponseMapper::notFound();
        }

        $method = $request->method();
        $pathRoutes = $this->routes[$path];

        if (!isset($pathRoutes[$method])) {
            return ResponseMapper::methodNotAllowed(implode(', ', array_keys($pathRoutes)));
        }

        return $pathRoutes[$method]($request);
    }

    private function normalizePath(string $path): string
    {
        $normalized = '/' . ltrim($path, '/');

        if ($normalized !== '/') {
            $normalized = rtrim($normalized, '/');
        }

        return $normalized;
    }
}
