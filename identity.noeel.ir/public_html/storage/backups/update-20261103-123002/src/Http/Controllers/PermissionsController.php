<?php
declare(strict_types=1);

namespace Babanoell\Auth\Http\Controllers;

use Babanoell\Auth\Http\JsonResponse;
use Babanoell\Auth\Http\Request;
use Babanoell\Auth\Http\ResponseMapper;

final class PermissionsController
{
    public function index(Request $request): JsonResponse
    {
        return ResponseMapper::notImplemented();
    }
}
