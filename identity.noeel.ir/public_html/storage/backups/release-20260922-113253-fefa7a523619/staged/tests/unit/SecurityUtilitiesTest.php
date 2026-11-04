<?php
declare(strict_types=1);

use Babanoell\Auth\Security\ConstantTimeComparator;
use Babanoell\Auth\Security\HmacTokenHasher;
use Babanoell\Auth\Security\InputNormalizer;
use Babanoell\Auth\Security\PhpPasswordHasher;
use Babanoell\Auth\Security\SecureTokenGenerator;
use Babanoell\Auth\Tests\Assertions;
use Babanoell\Auth\Tests\TestCase;

return new class extends TestCase {
    public function testPasswordHasherStoresVerifierOnly(): void
    {
        $hasher = new PhpPasswordHasher();
        $plainValue = 'phase-1c-local-check-value';

        $hash = $hasher->hash($plainValue);

        Assertions::notSame($plainValue, $hash);
        Assertions::true($hasher->verify($plainValue, $hash));
        Assertions::false($hasher->verify('wrong-local-check-value', $hash));
        Assertions::false($hasher->needsRehash($hash));
    }

    public function testPasswordHasherRejectsEmptyValue(): void
    {
        $hasher = new PhpPasswordHasher();

        Assertions::throws(InvalidArgumentException::class, static function () use ($hasher): void {
            $hasher->hash('');
        });
    }

    public function testTokenHasherUsesOneWayVerifier(): void
    {
        $hasher = new HmacTokenHasher(str_repeat('k', 32));
        $token = 'local-token-check-value';
        $hash = $hasher->hash($token);

        Assertions::notSame($token, $hash);
        Assertions::matches('/^[a-f0-9]{64}$/', $hash);
        Assertions::true($hasher->verify($token, $hash));
        Assertions::false($hasher->verify('different-local-token', $hash));
    }

    public function testTokenGeneratorReturnsUrlSafeValues(): void
    {
        $generator = new SecureTokenGenerator();

        $first = $generator->generate();
        $second = $generator->generate();

        Assertions::notSame($first, $second);
        Assertions::matches('/^[A-Za-z0-9_-]+$/', $first);
        Assertions::true(strlen($first) >= 22);
    }

    public function testConstantTimeComparator(): void
    {
        $comparator = new ConstantTimeComparator();

        Assertions::true($comparator->equals('known-value', 'known-value'));
        Assertions::false($comparator->equals('known-value', 'other-value'));
        Assertions::false($comparator->equals('', 'other-value'));
    }

    public function testInputNormalizerTrimsAndLowercasesIdentifier(): void
    {
        $normalizer = new InputNormalizer();

        Assertions::same('user@example.test', $normalizer->loginIdentifier('  USER@EXAMPLE.TEST  '));
    }
};
