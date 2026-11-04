<?php
declare(strict_types=1);

namespace Babanoell\Auth\UseCases\Login;

final class UnavailableLoginUseCase implements LoginUseCaseInterface
{
    public function handle(LoginRequest $request): LoginResult
    {
        return LoginResult::unavailable();
    }
}
