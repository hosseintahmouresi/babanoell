<?php
declare(strict_types=1);
namespace Babanoell\Panel\Security;

use RuntimeException;

final class TokenVault
{
    private readonly string $key;

    public function __construct(string $encodedKey)
    {
        $normalized = strtr($encodedKey, '-_', '+/');
        $padding = strlen($normalized) % 4;
        if ($padding !== 0) $normalized .= str_repeat('=', 4 - $padding);
        $decoded = base64_decode($normalized, true);
        if ($decoded === false || strlen($decoded) !== 32) {
            throw new RuntimeException('Panel session encryption key is invalid.');
        }
        $this->key = $decoded;
    }

    public static function generateKey(): string
    {
        return rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
    }

    public function seal(string $plaintext): string
    {
        $iv = random_bytes(12);
        $tag = '';
        $cipher = openssl_encrypt($plaintext, 'aes-256-gcm', $this->key, OPENSSL_RAW_DATA, $iv, $tag, 'babanoell-panel', 16);
        if ($cipher === false || strlen($tag) !== 16) throw new RuntimeException('Could not protect session credential.');
        return rtrim(strtr(base64_encode($iv . $tag . $cipher), '+/', '-_'), '=');
    }

    public function open(string $sealed): string
    {
        $padding = strlen($sealed) % 4;
        if ($padding !== 0) $sealed .= str_repeat('=', 4 - $padding);
        $raw = base64_decode(strtr($sealed, '-_', '+/'), true);
        if ($raw === false || strlen($raw) < 29) throw new RuntimeException('Protected session credential is invalid.');
        $iv = substr($raw, 0, 12);
        $tag = substr($raw, 12, 16);
        $cipher = substr($raw, 28);
        $plain = openssl_decrypt($cipher, 'aes-256-gcm', $this->key, OPENSSL_RAW_DATA, $iv, $tag, 'babanoell-panel');
        if ($plain === false) throw new RuntimeException('Could not read protected session credential.');
        return $plain;
    }
}
