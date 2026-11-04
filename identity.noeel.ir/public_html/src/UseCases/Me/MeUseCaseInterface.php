<?php
declare(strict_types=1);

namespace Babanoell\Auth\UseCases\Me;

interface MeUseCaseInterface
{
    public function handle(MeRequest $request): MeResult;
}
