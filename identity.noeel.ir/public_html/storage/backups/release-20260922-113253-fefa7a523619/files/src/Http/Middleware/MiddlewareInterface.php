<?php
declare(strict_types=1);

namespace Babanoell\Auth\Http\Middleware;

use Babanoell\Auth\Http\Request;
use Babanoell\Auth\Http\Response;

interface MiddlewareInterface
{
    /**
     * @param callable(Request):Response $next
     */
    public function process(Request $request, callable $next): Response;
}
