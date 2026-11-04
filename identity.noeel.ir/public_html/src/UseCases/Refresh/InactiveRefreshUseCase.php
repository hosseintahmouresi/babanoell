<?php
declare(strict_types=1);

namespace Babanoell\Auth\UseCases\Refresh;

final class InactiveRefreshUseCase implements RefreshUseCaseInterface
{
    public function handle(RefreshRequest $request): RefreshResult
    {
        return RefreshResult::inactive();
    }
}
