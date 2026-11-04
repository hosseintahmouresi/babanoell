<?php
declare(strict_types=1);

namespace Babanoell\Auth\Models;

use DateTimeImmutable;

final class User
{
    use DateTimeValue;

    public function __construct(
        public readonly string $id,
        public readonly ?string $mobile,
        public readonly ?string $email,
        public readonly ?string $displayName,
        public readonly string $status,
        public readonly ?string $passwordHash,
        public readonly ?DateTimeImmutable $passwordChangedAt,
        public readonly ?DateTimeImmutable $lastLoginAt,
        public readonly ?DateTimeImmutable $lockedUntil,
        public readonly ?DateTimeImmutable $disabledAt,
        public readonly ?DateTimeImmutable $createdAt,
        public readonly ?DateTimeImmutable $updatedAt
    ) {
    }

    /**
     * @param array<string,mixed> $row
     */
    public static function fromRow(array $row): self
    {
        return new self(
            id: (string) $row['id'],
            mobile: self::nullableString($row['mobile'] ?? null),
            email: self::nullableString($row['email'] ?? null),
            displayName: self::nullableString($row['display_name'] ?? null),
            status: (string) $row['status'],
            passwordHash: self::nullableString($row['password_hash'] ?? null),
            passwordChangedAt: self::optionalDateTime($row['password_changed_at'] ?? null),
            lastLoginAt: self::optionalDateTime($row['last_login_at'] ?? null),
            lockedUntil: self::optionalDateTime($row['locked_until'] ?? null),
            disabledAt: self::optionalDateTime($row['disabled_at'] ?? null),
            createdAt: self::optionalDateTime($row['created_at'] ?? null),
            updatedAt: self::optionalDateTime($row['updated_at'] ?? null)
        );
    }

    private static function nullableString(mixed $value): ?string
    {
        return $value === null || $value === '' ? null : (string) $value;
    }
}
