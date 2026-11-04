<?php
declare(strict_types=1);

namespace Babanoell\Auth\Services;

interface SessionPolicyInterface
{
    public function accessTokenTtlSeconds(): int;

    public function refreshTokenTtlSeconds(): int;
}
