<?php
declare(strict_types=1);
namespace Babanoell\Panel\Security;

final class Csrf
{
    public static function token(): string
    {
        if (!isset($_SESSION['_csrf']) || !is_string($_SESSION['_csrf']) || strlen($_SESSION['_csrf']) < 32) {
            $_SESSION['_csrf'] = bin2hex(random_bytes(24));
        }
        return $_SESSION['_csrf'];
    }

    public static function valid(?string $submitted): bool
    {
        $stored = $_SESSION['_csrf'] ?? null;
        return is_string($stored) && is_string($submitted) && $submitted !== '' && hash_equals($stored, $submitted);
    }
}
