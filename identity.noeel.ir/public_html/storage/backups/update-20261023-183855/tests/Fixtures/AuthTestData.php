<?php
declare(strict_types=1);

namespace Babanoell\Auth\Tests\Fixtures;

use Babanoell\Auth\Models\User;
use Babanoell\Auth\Security\PasswordHasherInterface;
use DateTimeImmutable;
use DateTimeZone;

final class AuthTestData
{
    public const USER_ID = 'usr_local_login_test_0001';
    public const EMAIL = 'local-login-test@example.invalid';
    public const PASSWORD = 'local-login-test-password';

    public static function user(
        PasswordHasherInterface $passwordHasher,
        string $status = 'active',
        ?DateTimeImmutable $lockedUntil = null,
        ?DateTimeImmutable $disabledAt = null,
    ): User {
        $now = new DateTimeImmutable('2026-06-14 00:00:00', new DateTimeZone('UTC'));

        return new User(
            id: self::USER_ID,
            mobile: null,
            email: self::EMAIL,
            displayName: 'Local Login Test',
            status: $status,
            passwordHash: $passwordHasher->hash(self::PASSWORD),
            passwordChangedAt: $now,
            lastLoginAt: null,
            lockedUntil: $lockedUntil,
            disabledAt: $disabledAt,
            createdAt: $now,
            updatedAt: $now,
        );
    }
}
