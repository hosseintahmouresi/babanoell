<?php
declare(strict_types=1);

namespace Babanoell\Auth\UseCases\Login;

interface LoginUseCaseInterface
{
    public function handle(LoginRequest $request): LoginResult;
}
