<?php
declare(strict_types=1);

namespace Babanoell\Auth\UseCases\Logout;

final class InactiveLogoutUseCase implements LogoutUseCaseInterface
{
    public function handle(LogoutRequest $request): LogoutResult
    {
        return LogoutResult::inactive();
    }
}
