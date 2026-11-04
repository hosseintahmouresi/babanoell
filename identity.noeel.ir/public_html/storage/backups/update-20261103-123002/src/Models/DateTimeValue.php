<?php
declare(strict_types=1);

namespace Babanoell\Auth\Models;

use DateTimeImmutable;
use DateTimeZone;

trait DateTimeValue
{
    private static function optionalDateTime(mixed $value): ?DateTimeImmutable
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($value instanceof DateTimeImmutable) {
            return $value;
        }

        return new DateTimeImmutable((string) $value, new DateTimeZone('UTC'));
    }
}
