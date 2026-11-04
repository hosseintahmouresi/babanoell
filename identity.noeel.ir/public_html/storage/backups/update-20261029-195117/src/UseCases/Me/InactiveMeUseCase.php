<?php
declare(strict_types=1);

namespace Babanoell\Auth\UseCases\Me;

final class InactiveMeUseCase implements MeUseCaseInterface
{
    public function handle(MeRequest $request): MeResult
    {
        return MeResult::inactive();
    }
}
