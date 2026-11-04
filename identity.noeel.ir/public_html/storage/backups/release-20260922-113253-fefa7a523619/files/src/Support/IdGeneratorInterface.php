<?php
declare(strict_types=1);

namespace Babanoell\Auth\Support;

interface IdGeneratorInterface
{
    public function generate(): string;
}
