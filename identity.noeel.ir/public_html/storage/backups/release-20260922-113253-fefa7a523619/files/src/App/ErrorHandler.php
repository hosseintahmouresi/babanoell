<?php
declare(strict_types=1);

namespace Babanoell\Auth\App;

use Babanoell\Auth\Http\JsonResponse;
use Babanoell\Auth\Http\ResponseMapper;
use InvalidArgumentException;
use Throwable;

final class ErrorHandler
{
    public function render(Throwable $throwable): JsonResponse
    {
        if ($throwable instanceof InvalidArgumentException) {
            return ResponseMapper::invalidRequest();
        }

        return ResponseMapper::internalError();
    }
}
