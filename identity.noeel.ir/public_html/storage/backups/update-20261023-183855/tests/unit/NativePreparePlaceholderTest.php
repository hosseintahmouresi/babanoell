<?php
declare(strict_types=1);

use Babanoell\Auth\Tests\Assertions;
use Babanoell\Auth\Tests\TestCase;

return new class extends TestCase {
    public function testUpdaterLoginUsesDistinctNativePdoPlaceholders(): void
    {
        $source = file_get_contents(dirname(__DIR__, 2) . '/public/update.php');
        Assertions::true(is_string($source));
        Assertions::contains('u.mobile = :mobile_identifier OR u.email = :email_identifier', $source);
        Assertions::false(str_contains($source, 'u.mobile = :identifier OR u.email = :identifier'));
    }

    public function testApplicationLoginUsesDistinctNativePdoPlaceholders(): void
    {
        $source = file_get_contents(dirname(__DIR__, 2) . '/src/Repositories/Pdo/PdoUserRepository.php');
        Assertions::true(is_string($source));
        Assertions::contains('email = :email_identifier OR mobile = :mobile_identifier', $source);
        Assertions::false(str_contains($source, 'email = :identifier OR mobile = :identifier'));
    }
};
