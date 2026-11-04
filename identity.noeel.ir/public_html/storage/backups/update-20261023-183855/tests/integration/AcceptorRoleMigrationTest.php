<?php
declare(strict_types=1);

namespace Babanoell\Auth\Tests\Integration;

use Babanoell\Auth\Tests\Assertions;
use Babanoell\Auth\Tests\TestCase;

final class AcceptorRoleMigrationTest extends TestCase
{
    public function testAcceptorRoleIsSeededWithLeastPrivilegePermissions(): void
    {
        $root = dirname(__DIR__, 2);
        $file = $root . '/database/migrations/202609220002_seed_acceptor_role.php';
        Assertions::true(is_file($file));
        $source = (string) file_get_contents($file);

        foreach ([
            "'acceptor'",
            "'پذیرنده'",
            "'loans.plans.read'",
            "'loans.calculate'",
            "'loans.requests.read'",
            "'loans.requests.create'",
            "'loans.requests.update'",
            "'loans.requests.audit'",
            "'documents.view'",
            "'documents.upload'",
            "'documents.download'",
            "'documents.replace'",
        ] as $expected) {
            Assertions::contains($expected, $source);
        }

        foreach ([
            "'loans.plans.manage'",
            "'loans.requests.transition'",
            "'documents.review'",
            "'documents.archive'",
            "'documents.audit'",
            "'documents.manage'",
        ] as $forbidden) {
            Assertions::false(str_contains($source, $forbidden), 'Acceptor role must not receive privileged permission: ' . $forbidden);
        }
    }
}

return new AcceptorRoleMigrationTest();
