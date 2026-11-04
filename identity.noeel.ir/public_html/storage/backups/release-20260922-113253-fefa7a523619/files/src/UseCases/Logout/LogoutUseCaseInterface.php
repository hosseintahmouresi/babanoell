<?php
declare(strict_types=1);

namespace Babanoell\Auth\UseCases\Logout;

interface LogoutUseCaseInterface
{
    public function handle(LogoutRequest $request): LogoutResult;
}
