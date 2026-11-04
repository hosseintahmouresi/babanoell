<?php
declare(strict_types=1);

namespace Babanoell\Auth\Http\Middleware;

use Babanoell\Auth\Http\Request;
use Babanoell\Auth\Http\Response;

final class RequestIdMiddleware implements MiddlewareInterface
{
    /**
     * @var callable():string
     */
    private $idGenerator;

    /**
     * @param null|callable():string $idGenerator
     */
    public function __construct(?callable $idGenerator = null)
    {
        $this->idGenerator = $idGenerator ?? static fn (): string => bin2hex(random_bytes(16));
    }

    public function process(Request $request, callable $next): Response
    {
        $requestId = $this->validRequestId($request->header('X-Request-Id'))
            ? (string) $request->header('X-Request-Id')
            : ($this->idGenerator)();

        return $next($request)->withHeader('X-Request-Id', $requestId);
    }

    private function validRequestId(?string $requestId): bool
    {
        return is_string($requestId)
            && preg_match('/^[A-Za-z0-9._-]{8,128}$/', $requestId) === 1;
    }
}
