<?php
declare(strict_types=1);

namespace Babanoell\Auth\Http\Middleware;

use Babanoell\Auth\Http\JsonResponse;
use Babanoell\Auth\Http\Request;
use Babanoell\Auth\Http\Response;

final class AuthorizationMiddleware implements MiddlewareInterface
{
    /**
     * @param callable(Request):Response $next
     */
    public function process(Request $request, callable $next): Response
    {
        if ($this->isInactiveSafeRoute($request)) {
            return $next($request);
        }

        return JsonResponse::error(403, 'authorization_inactive', 'Authorization is not active for this request.');
    }

    private function isInactiveSafeRoute(Request $request): bool
    {
        return $request->method() === 'GET'
            && in_array($request->path(), ['/', '/health', '/healthz'], true);
    }
}
