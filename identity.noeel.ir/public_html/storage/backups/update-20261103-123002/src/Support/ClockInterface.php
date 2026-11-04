<?php
declare(strict_types=1);

namespace Babanoell\Auth\Support;

use DateTimeImmutable;

interface ClockInterface
{
    public function now(): DateTimeImmutable;
}
