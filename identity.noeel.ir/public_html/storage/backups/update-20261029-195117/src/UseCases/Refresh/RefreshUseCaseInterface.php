<?php
declare(strict_types=1);

namespace Babanoell\Auth\UseCases\Refresh;

interface RefreshUseCaseInterface
{
    public function handle(RefreshRequest $request): RefreshResult;
}
