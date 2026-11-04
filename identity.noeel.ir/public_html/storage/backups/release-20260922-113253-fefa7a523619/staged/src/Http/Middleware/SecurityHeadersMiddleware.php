<?php
declare(strict_types=1);

namespace Babanoell\Auth\Http\Middleware;

use Babanoell\Auth\Http\Request;
use Babanoell\Auth\Http\Response;

final class SecurityHeadersMiddleware implements MiddlewareInterface
{
    public function process(Request $request, callable $next): Response
    {
        return $next($request)->withHeaders([
            'X-Content-Type-Options' => 'nosniff',
            'Referrer-Policy' => 'no-referrer',
            'X-Frame-Options' => 'DENY',
        ]);
    }
}
